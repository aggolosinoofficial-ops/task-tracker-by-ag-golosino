import os
import copy
import signal
import sys
from flask import Flask, render_template, request, redirect, url_for, flash, jsonify, session
from flask_login import LoginManager, login_user, logout_user, login_required, current_user
from flask_wtf.csrf import CSRFProtect
from flask_wtf.csrf import CSRFError
from lxml import etree  # type: ignore
from auth_service import AuthService
from task_service import TaskService
from activity_service import ActivityService
from archive_service import ArchiveService
from flask import send_from_directory
from xml_service import XMLService

app = Flask(__name__)
app.secret_key = os.environ.get('SECRET_KEY', 'task-tracker-secure-key-312')

# Initialize CSRF Protection
csrf = CSRFProtect(app)

# Ensure CSRF works with AJAX form posts (it relies on session cookies).
# If cookies are missing, Flask-WTF will return HTML 400; instead, keep XHR JSON consistent.
@app.after_request
def add_header(resp):
    """Add headers to prevent caching of API responses for real-time updates."""
    if request.path.startswith('/api/'):
        resp.headers["Cache-Control"] = "no-cache, no-store, must-revalidate"
        resp.headers["Pragma"] = "no-cache"
        resp.headers["Expires"] = "0"
    return resp

def shutdown_handler(signal_received, frame):
    """Ensures a clean exit, releasing file handles and terminating child processes."""
    print("\n[Server] Shutdown signal received. Cleaning up handles and exiting...")
    # sys.exit(0) triggers Python's internal cleanup and atexit handlers
    sys.exit(0)

# Register signal handlers for graceful shutdown (Ctrl+C and termination)
signal.signal(signal.SIGINT, shutdown_handler)
signal.signal(signal.SIGTERM, shutdown_handler)

# Initialize Services
xml_service = XMLService()
auth_service = AuthService(xml_service)
task_service = TaskService(xml_service)
activity_service = ActivityService(xml_service)
archive_service = ArchiveService(xml_service)

# Flask-Login Setup
login_manager = LoginManager()
login_manager.init_app(app)
login_manager.login_view = 'login'  # type: ignore

@login_manager.user_loader
def load_user(user_id):
    return auth_service.get_user_by_id(user_id)

@app.errorhandler(CSRFError)
def handle_csrf_error(e):
    print(f"[Security] CSRF Blocked: {e.description}")
    # Detect Fetch/XHR by checking headers or content type
    is_ajax = (request.headers.get('X-Requested-With') == 'XMLHttpRequest' or 
               request.headers.get('X-CSRFToken') is not None or
               request.is_json)
    if is_ajax:
        return jsonify({'success': False, 'error': 'Security token expired. Please refresh the page.'}), 400
    return render_template('login.html', error="Security token expired. Please refresh."), 400

@app.route('/')
@login_required
def index():
    try:
        stats = task_service.get_dashboard_stats(current_user.id)
    except Exception as e:
        print(f"[Dashboard] Error calculating stats: {e}")
        stats = {'total': 0, 'completed': 0, 'pending': 0, 'archived': 0, 'completion_rate': 0}
    
    try:
        recent_activity = activity_service.get_recent_logs(limit=5) or []
    except Exception as e:
        print(f"[Dashboard] Error loading activity: {e}")
        recent_activity = []
        
    return render_template('dashboard.html', stats=stats, activities=recent_activity)

@app.route('/login', methods=['GET', 'POST'])
def login():
    if current_user.is_authenticated:
        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
            return jsonify({'success': True})
        return redirect(url_for('index'))

    if request.method == 'POST':
        data = request.get_json() if request.is_json else request.form
        username = data.get('username')
        password = data.get('password')

        # Always keep XHR login responses JSON (never Werkzeug HTML).
        try:
            user = auth_service.authenticate(username, password)
        except Exception as e:
            app.logger.error(f"[Login] Unhandled exception during authentication: {e}")
            if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                return jsonify({'success': False, 'error': 'Login failed due to a server error'}), 500
            flash('Login failed due to a server error', 'error')
            return render_template('login.html'), 500

        if user:
            login_user(user)
            activity_service.log_activity(user.username, "User logged in")
            if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                return jsonify({'success': True})
            return redirect(url_for('index'))

        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
            return jsonify({'success': False, 'error': 'Invalid username or password'})
        flash('Invalid username or password', 'error')

    return render_template('login.html')

@app.route('/register', methods=['GET', 'POST'])
@csrf.exempt
def register():
    if current_user.is_authenticated:
        return redirect(url_for('index'))

    if request.method == 'POST':
        data = request.get_json() if request.is_json else request.form
        username = data.get('username')
        password = data.get('password')
        confirm = data.get('confirm_password')
        
        if password != confirm:
            if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                return jsonify({'success': False, 'error': 'Passwords do not match'})
            flash('Passwords do not match', 'error')
            return redirect(url_for('register'))
        
        success, msg = auth_service.create_user(username, password)
        if success:
            activity_service.log_activity(username, "New user registered")
            if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                return jsonify({'success': True})
            flash('Registration successful! Please sign in.', 'success')
            return redirect(url_for('login'))
        
        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
            return jsonify({'success': False, 'error': msg})
        flash(msg, 'error')
        return redirect(url_for('register'))
    return render_template('register.html')

@app.route('/api/check_username', methods=['POST'])
@csrf.exempt
def check_username():
    data = request.get_json() if request.is_json else request.form
    username = data.get('username')
    exists = auth_service.username_exists(username)
    return jsonify({'available': not exists})

@app.route('/logout')
@login_required
def logout():
    # Log activity before clearing user data
    activity_service.log_activity(current_user.username, "User logged out")
    logout_user()
    session.clear()  # Ensure all session data is wiped
    flash('You have been successfully logged out.', 'success')
    return redirect(url_for('login'))

@app.route('/tasks')
@login_required
def tasks():
    user_id = current_user.id if current_user.role != 'admin' else None
    all_tasks = task_service.get_all_tasks(user_id=user_id)
    return render_template('tasks.html', tasks=all_tasks)

@app.route('/add')
@login_required
def add_task_view():
    return render_template('add_task.html')

@app.route('/insights')
@login_required
def insights():
    stats = task_service.get_dashboard_stats(current_user.id)
    return render_template('insights.html', stats=stats)

@app.route('/archive')
@login_required
def archive_task_view():
    archived = task_service.get_archived_tasks(user_id=current_user.id)
    return render_template('archive.html', tasks=archived)

@app.route('/add_task', methods=['POST'])
@login_required
def add_task():
    data = request.get_json() if request.is_json else request.form
    task_data = {
        'title': data.get('title'),
        'description': data.get('description'),
        'priority': data.get('priority'),
        'due_date': data.get('due_date'),
        'user_id': current_user.id,
        'assigned_to': data.get('assigned_to', current_user.id)
    }
    
    success, message = task_service.create_task(task_data)
    if success:
        activity_service.log_activity(current_user.username, f"Task created: {task_data['title']}")
        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
            return jsonify({'success': True}), 200
        flash('Task added successfully', 'success')
        return redirect(url_for('tasks'))
    
    if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
        return jsonify({'success': False, 'error': message}), 400
    flash(message, 'error')
    return redirect(url_for('add_task_view'))

@app.route('/edit_task/<task_id>', methods=['POST'])
@login_required
def edit_task(task_id):
    raw_data = request.get_json() if request.is_json else request.form
    # Only include keys that are actually present in the request to allow partial updates
    update_data = {}
    for key in ['title', 'description', 'priority', 'status', 'due_date']:
        if key in raw_data:
            update_data[key] = raw_data.get(key)
            
    success, msg = task_service.update_task(task_id, update_data, current_user.id, current_user.role == 'admin')
    if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
        # Return 200 even on logic failure so AJAX .then() can handle the {success: false} object
        return jsonify({'success': success, 'error': msg if not success else None}), 200
    flash(msg if not success else 'Task updated', 'success' if success else 'error')
    return redirect(url_for('tasks'))

@app.route('/archive_task/<task_id>', methods=['POST'])
@login_required
def archive_task(task_id):
    success, msg = archive_service.archive_task(task_id, current_user.id, current_user.role == 'admin')
    if success:
        activity_service.log_activity(current_user.username, f"Archived task {task_id}")
        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
            return jsonify({'success': True}), 200
        flash('Task archived', 'success')
    else:
        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
            return jsonify({'success': False, 'error': msg}), 400
        flash(msg, 'error')
    return redirect(url_for('tasks'))

@app.route('/api/tasks/restore_bulk', methods=['POST'])
@login_required
def restore_bulk():
    """AJAX endpoint to restore selected or all archived tasks."""
    data = request.get_json() or {}
    task_ids = data.get('task_ids') # Optional list of strings
    success, msg = archive_service.bulk_restore_tasks(current_user.id, current_user.role == 'admin', task_ids)
    return jsonify({'success': success, 'error': msg if not success else None})

@app.route('/restore_task/<task_id>', methods=['POST'])
@login_required
def restore_task(task_id):
    success, msg = archive_service.restore_task(task_id, current_user.id, current_user.role == 'admin')
    if success:
        activity_service.log_activity(current_user.username, f"Restored task {task_id}")
        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
            return jsonify({'success': True}), 200
        flash('Task restored', 'success')
    else:
        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
            return jsonify({'success': False, 'error': msg}), 400
        flash(msg, 'error')
    return redirect(url_for('tasks'))

@app.route('/api/tasks/clear_completed', methods=['POST'])
@login_required
def clear_completed():
    """AJAX endpoint to bulk delete completed tasks."""
    success, msg = task_service.bulk_delete_tasks('completed', current_user.id, current_user.role == 'admin')
    return jsonify({'success': success, 'error': msg if not success else None})

@app.route('/api/raw_tasks')
@login_required
def get_raw_tasks():
    """Serves the raw XML tasks for frontend XSLT processing."""
    tree = xml_service.get_element_tree('tasks')
    # Create a deep copy to avoid corrupting the global cache during filtering
    root = copy.deepcopy(tree.getroot())
    
    # Privacy filter: If not admin, only include tasks owned by or assigned to the user
    if current_user.role != 'admin':
        user_id = str(current_user.id)
        # Filter the tree in memory before sending to frontend
        xpath_filter = f"//task[normalize-space(user_id)!='{user_id}' and normalize-space(assigned_to)!='{user_id}']"
        for task in root.xpath(xpath_filter):
            task.getparent().remove(task)
            
    # Frontend Tweak: Link the XSLT stylesheet so the browser can render the XML interactively
    pi = etree.ProcessingInstruction("xml-stylesheet", 'type="text/xsl" href="/schema/tasks.xsl"')
    root.addprevious(pi)

    xml_output = etree.tostring(root.getroottree(), encoding='UTF-8', xml_declaration=True, pretty_print=True)
    return xml_output, 200, {
        'Content-Type': 'text/xml; charset=utf-8',
        'Content-Disposition': 'inline'
    }

@app.route('/api/raw_archive')
@login_required
def get_raw_archive():
    """Serves the raw XML archive tasks for frontend processing."""
    tree = xml_service.get_element_tree('archive_tasks')
    root = copy.deepcopy(tree.getroot())
    
    if current_user.role != 'admin':
        user_id = str(current_user.id)
        xpath_filter = f"//task[normalize-space(user_id)!='{user_id}' and normalize-space(assigned_to)!='{user_id}']"
        for task in root.xpath(xpath_filter):
            task.getparent().remove(task)
            
    # Add stylesheet link for archive as well
    pi = etree.ProcessingInstruction("xml-stylesheet", 'type="text/xsl" href="/schema/archive.xsl"')
    root.addprevious(pi)

    xml_output = etree.tostring(root.getroottree(), encoding='UTF-8', xml_declaration=True, pretty_print=True)
    return xml_output, 200, {'Content-Type': 'text/xml; charset=utf-8'}

@app.route('/api/tasks/rendered')
@login_required
def get_rendered_tasks():
    """Serves tasks rendered as HTML via server-side XSLT."""
    # This replaces the need for browser-side XSLTProcessor
    # It assumes you have a 'tasks.xsl' in your schema or root folder
    html = xml_service.apply_xslt('tasks', 'tasks') 
    if html is not None:
        return html, 200, {'Content-Type': 'text/html'}
    return "Error: Could not render tasks on server.", 500

@app.route('/schema/<path:filename>')
@login_required
def get_schema_file(filename):
    """Serves XSLT and XSD files to the frontend for client-side processing."""
    return send_from_directory('schema', filename)

@app.route('/api/sync_tasks', methods=['POST'])
@login_required
def sync_tasks():
    """
    Accepts a raw XML string from the frontend and persists it.
    This supports the frontend-only XML manipulation layer.
    """
    try:
        # Extract the raw XML from the request body
        xml_data = request.data
        if not xml_data:
            return jsonify({'success': False, 'error': 'No XML data received'}), 400

        # Parse the string into an ElementTree
        # remove_blank_text=True ensures we don't save redundant whitespace
        parser = etree.XMLParser(remove_blank_text=True)
        root = etree.fromstring(xml_data, parser)

        # SECURITY CHECK: If not admin, verify all tasks belong to current_user
        if current_user.role != 'admin':
            user_id_str = str(current_user.id)
            for task in root.xpath("//task"):
                uid = (task.findtext('user_id') or "").strip()
                aid = (task.findtext('assigned_to') or "").strip()
                if uid != user_id_str and aid != user_id_str:
                    return jsonify({'success': False, 'error': 'Unauthorized: Task ownership or assignment mismatch detected.'}), 403

        # Load existing tree to merge/replace safely
        full_tree = xml_service.get_element_tree('tasks')
        full_root = full_tree.getroot()

        if current_user.role != 'admin':
            user_id_str = str(current_user.id)
            # 1. Identify and remove existing tasks belonging to this user in the master file
            xpath_remove = f"//task[normalize-space(user_id)='{user_id_str}' or normalize-space(assigned_to)='{user_id_str}']"
            for old_task in full_root.xpath(xpath_remove):
                full_root.remove(old_task)
            
            # 2. Append the new/edited tasks received from the frontend
            for new_task in root.xpath("//task"):
                full_root.append(new_task)
            
            save_tree = full_tree
        else:
            # Admins see everything, so they can perform a full replacement
            save_tree = etree.ElementTree(root)

        success, msg = xml_service.save_safely('tasks', save_tree)
        
        if success:
            activity_service.log_activity(current_user.username, "Synced task data from UI")
            return jsonify({'success': True}), 200
        return jsonify({'success': False, 'error': msg}), 400

    except etree.XMLSyntaxError as e:
        return jsonify({'success': False, 'error': f"Malformed XML: {str(e)}"}), 400
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/tasks/bulk_status', methods=['POST'])
@login_required
def bulk_status_update():
    """AJAX endpoint to bulk update task statuses (e.g., Mark all pending as in_progress)."""
    data = request.get_json()
    curr = data.get('current_status')
    new = data.get('new_status')
    
    if not curr or not new:
        return jsonify({'success': False, 'error': 'Current and new status are required'}), 400
        
    success, msg = task_service.bulk_update_status(curr, new, current_user.id, current_user.role == 'admin')
    return jsonify({'success': success, 'error': msg if not success else None})

@app.route('/api/search')
@login_required
def search_tasks():
    query = request.args.get('q', '').lower()
    status = request.args.get('status')
    priority = request.args.get('priority')
    page = int(request.args.get('page', 1))
    limit = int(request.args.get('limit', 10))
    offset = (page - 1) * limit
    
    results = task_service.search(
        query=query, 
        status=status, 
        priority=priority, 
        user_id=current_user.id if current_user.role != 'admin' else None
    )
    
    total_results = len(results)
    paginated_results = results[offset : offset + limit]
    
    return jsonify({
        'data': paginated_results,
        'pagination': {
            'page': page,
            'limit': limit,
            'total_pages': (total_results + limit - 1) // limit,
            'total_count': total_results
        }
    })

@app.route('/api/health')
@login_required
def get_health():
    """Endpoint for the System Health dashboard."""
    # Admins get full file status; users get basic active task health
    is_admin = current_user.role == 'admin'
    return jsonify(xml_service.get_health_status(include_all=is_admin))

@app.route('/api/insights')
@login_required
def get_insights_data():
    try:
        stats = task_service.get_dashboard_stats(current_user.id)
        return jsonify({
            'total_active': stats['total'],
            'pending': stats['pending'],
            'completed': stats['completed'],
            'archived': stats['archived'],
            'priorities': stats['priorities'],
            'total_all_time': stats['total'] + stats['archived'],
            'completion_rate': stats['completion_rate'],
            'productivity_score': stats['productivity_score'],
            'avg_per_day': stats['avg_per_day'],
            'productivity_level': stats['productivity_level'],
            'daily_data': stats['daily_data']
        })
    except Exception as e:
        app.logger.error(f"Insights API error: {e}")
        return jsonify({'success': False, 'error': 'Failed to load insights data'}), 500

@app.route('/admin/logs')
@login_required
def admin_logs():
    if current_user.role != 'admin':
        return redirect(url_for('index'))
    logs = activity_service.get_all_logs()
    return render_template('admin.html', logs=logs)

if __name__ == '__main__':
    # Ensure data directory exists
    if not os.path.exists('data'):
        os.makedirs('data')
    if not os.path.exists('schema'):
        os.makedirs('schema')
        
    # Self-heal: Force cleanup stale locks on startup
    # We only do this in the main process (skipping the reloader's first pass)
    if os.environ.get("WERKZEUG_RUN_MAIN") != "true":
        print("[Startup] Sweeping for orphaned file locks...")
        # Use a 1-second expiry since we are the only intended active process right now
        xml_service.cleanup_orphaned_locks(expiry_seconds=1)

    app.run(debug=True, host='0.0.0.0', port=5000)
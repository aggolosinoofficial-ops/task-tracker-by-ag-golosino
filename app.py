import os
from flask import Flask, render_template, request, redirect, url_for, flash, jsonify, session
from flask_login import LoginManager, login_user, logout_user, login_required, current_user
from flask_wtf.csrf import CSRFProtect
from flask_wtf.csrf import CSRFError
from auth_service import AuthService
from task_service import TaskService
from activity_service import ActivityService
from archive_service import ArchiveService
from xml_service import XMLService

app = Flask(__name__)
app.secret_key = os.environ.get('SECRET_KEY', 'task-tracker-secure-key-312')

# Initialize CSRF Protection
csrf = CSRFProtect(app)

# Ensure CSRF works with AJAX form posts (it relies on session cookies).
# If cookies are missing, Flask-WTF will return HTML 400; instead, keep XHR JSON consistent.
@app.after_request
def _set_xhr_content_type(resp):
    # No-op placeholder for future headers; keep function to avoid template changes.
    return resp

# Initialize Services
xml_service = XMLService()
auth_service = AuthService(xml_service)
task_service = TaskService(xml_service)
activity_service = ActivityService(xml_service)
archive_service = ArchiveService(xml_service)

# Flask-Login Setup
login_manager = LoginManager()
login_manager.init_app(app)
login_manager.login_view = 'login'

@login_manager.user_loader
def load_user(user_id):
    return auth_service.get_user_by_id(user_id)

@app.errorhandler(CSRFError)
def handle_csrf_error(e):
    print(f"[Security] CSRF Blocked: {e.description}")
    if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
        return jsonify({'success': False, 'error': 'Security token expired. Please refresh the page.'}), 400
    return render_template('login.html', error="Security token expired. Please refresh."), 400

@app.route('/')
@login_required
def index():
    stats = task_service.get_dashboard_stats(current_user.id)
    recent_activity = activity_service.get_recent_logs(limit=5) or []
    return render_template('dashboard.html', stats=stats, activities=recent_activity)

@app.route('/login', methods=['GET', 'POST'])
def login():
    if current_user.is_authenticated:
        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
            return jsonify({'success': True})
        return redirect(url_for('index'))

    if request.method == 'POST':
        username = request.form.get('username')
        password = request.form.get('password')

        # Always keep XHR login responses JSON (never Werkzeug HTML).
        try:
            user = auth_service.authenticate(username, password)
        except Exception as e:
            print(f"[Login] Unhandled exception during authentication: {e}")
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
def register():
    if current_user.is_authenticated:
        return redirect(url_for('index'))

    if request.method == 'POST':
        username = request.form.get('username')
        password = request.form.get('password')
        confirm = request.form.get('confirm_password')
        
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
def check_username():
    username = request.form.get('username')
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
    task_data = {
        'title': request.form.get('title'),
        'description': request.form.get('description'),
        'priority': request.form.get('priority'),
        'due_date': request.form.get('due_date'),
        'created_by': current_user.id,
        'assigned_to': request.form.get('assigned_to', current_user.id)
    }
    
    success, message = task_service.create_task(task_data)
    if success:
        activity_service.log_activity(current_user.username, f"Task created: {task_data['title']}")
        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
            return jsonify({'success': True})
        flash('Task added successfully', 'success')
    else:
        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
            return jsonify({'success': False, 'error': message})
        flash(message, 'error')
    return redirect(url_for('tasks'))

@app.route('/edit_task/<task_id>', methods=['POST'])
@login_required
def edit_task(task_id):
    data = {
        'title': request.form.get('title'),
        'description': request.form.get('description'),
        'priority': request.form.get('priority'),
        'status': request.form.get('status')
    }
    success, msg = task_service.update_task(task_id, data, current_user.id, current_user.role == 'admin')
    if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
        return jsonify({'success': success, 'error': msg if not success else None})
    flash(msg if not success else 'Task updated', 'success' if success else 'error')
    return redirect(url_for('tasks'))

@app.route('/archive_task/<task_id>')
@login_required
def archive_task(task_id):
    success, msg = archive_service.archive_task(task_id, current_user.id, current_user.role == 'admin')
    if success:
        activity_service.log_activity(current_user.username, f"Archived task {task_id}")
        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
            return jsonify({'success': True})
        flash('Task archived', 'success')
    else:
        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
            return jsonify({'success': False, 'error': msg})
        flash(msg, 'error')
    return redirect(url_for('tasks'))

@app.route('/restore_task/<task_id>')
@login_required
def restore_task(task_id):
    success, msg = archive_service.restore_task(task_id, current_user.id, current_user.role == 'admin')
    if success:
        activity_service.log_activity(current_user.username, f"Restored task {task_id}")
        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
            return jsonify({'success': True})
        flash('Task restored', 'success')
    else:
        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
            return jsonify({'success': False, 'error': msg})
        flash(msg, 'error')
    return redirect(url_for('tasks'))

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

@app.route('/api/insights')
@login_required
def get_insights_data():
    stats = task_service.get_dashboard_stats(current_user.id)
    return jsonify({
        'total_active': stats['total'],
        'pending': stats['pending'],
        'completed': stats['completed'],
        'archived': stats['archived'],
        'total_all_time': stats['total'] + stats['archived'],
        'completion_rate': stats['completion_rate'],
        'avg_per_day': stats['avg_per_day'],
        'productivity_level': stats['productivity_level'],
        'daily_data': stats['daily_data']
    })

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
        
    app.run(debug=True, host='0.0.0.0', port=5000)
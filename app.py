import os
from flask import Flask, render_template, request, redirect, url_for, flash, jsonify
from flask_login import LoginManager, login_user, logout_user, login_required, current_user
from flask_wtf.csrf import CSRFProtect
from auth_service import AuthService
from task_service import TaskService
from activity_service import ActivityService
from archive_service import ArchiveService
from xml_service import XMLService

app = Flask(__name__)
app.secret_key = os.environ.get('SECRET_KEY', 'task-tracker-secure-key-312')

# Initialize CSRF Protection
csrf = CSRFProtect(app)

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

@app.route('/')
@login_required
def index():
    stats = task_service.get_dashboard_stats(current_user.id)
    recent_activity = activity_service.get_recent_logs(limit=5)
    return render_template('dashboard.html', stats=stats, activities=recent_activity)

@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        username = request.form.get('username')
        password = request.form.get('password')
        user = auth_service.authenticate(username, password)
        if user:
            login_user(user)
            activity_service.log_activity(user.username, "User logged in")
            return jsonify({'success': True})
        return jsonify({'success': False, 'error': 'Invalid username or password'})
    return render_template('login.html')

@app.route('/register', methods=['GET', 'POST'])
def register():
    if request.method == 'POST':
        username = request.form.get('username')
        password = request.form.get('password')
        confirm = request.form.get('confirm_password')
        
        if password != confirm:
            return jsonify({'success': False, 'error': 'Passwords do not match'})
        
        success, msg = auth_service.create_user(username, password)
        if success:
            activity_service.log_activity(username, "New user registered")
            return jsonify({'success': True})
        return jsonify({'success': False, 'error': msg})
    return render_template('register.html')

@app.route('/api/check_username', methods=['POST'])
def check_username():
    username = request.form.get('username')
    exists = auth_service.username_exists(username)
    return jsonify({'available': not exists})

@app.route('/logout')
@login_required
def logout():
    activity_service.log_activity(current_user.username, "User logged out")
    logout_user()
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
        flash('Task added successfully', 'success')
    else:
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
    flash(msg if not success else 'Task updated', 'success' if success else 'error')
    return redirect(url_for('tasks'))

@app.route('/archive_task/<task_id>')
@login_required
def archive_task(task_id):
    success, msg = archive_service.archive_task(task_id, current_user.id, current_user.role == 'admin')
    if success:
        activity_service.log_activity(current_user.username, f"Archived task {task_id}")
        flash('Task archived', 'success')
    else:
        flash(msg, 'error')
    return redirect(url_for('tasks'))

@app.route('/restore_task/<task_id>')
@login_required
def restore_task(task_id):
    success, msg = archive_service.restore_task(task_id, current_user.id, current_user.role == 'admin')
    if success:
        activity_service.log_activity(current_user.username, f"Restored task {task_id}")
        flash('Task restored', 'success')
    else:
        flash(msg, 'error')
    return redirect(url_for('tasks'))

@app.route('/api/search')
@login_required
def search_tasks():
    query = request.args.get('q', '').lower()
    status = request.args.get('status')
    priority = request.args.get('priority')
    
    results = task_service.search(
        query=query, 
        status=status, 
        priority=priority, 
        user_id=current_user.id if current_user.role != 'admin' else None
    )
    return jsonify(results)

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
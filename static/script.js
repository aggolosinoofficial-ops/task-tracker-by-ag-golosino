// OPTIMIZATION: Global state tracking to prevent memory leaks
const state = {
    currentPage: 1,
    pageSize: 50,
    totalPages: 1,
    isLoading: false
};

// Initialize on page load - check if DOM is ready
function initializeTaskForm() {
    const taskForm = document.getElementById('taskForm');
    if (taskForm && !taskForm._initialized) {
        taskForm.addEventListener('submit', function(e) {
            e.preventDefault();
            addTask();
        });
        taskForm._initialized = true;
    }
}

function initializeTaskListHandlers() {
    const taskList = document.getElementById('taskList');
    if (taskList && !taskList._initialized) {
        taskList.addEventListener('click', handleTaskListClick);
        taskList.addEventListener('submit', handleTaskListSubmit);
        taskList._initialized = true;
    }
}

function handleTaskListClick(event) {
    const button = event.target.closest('button[data-action]');
    if (!button) {
        return;
    }

    const taskId = button.dataset.taskId;
    const action = button.dataset.action;

    if (!taskId || !action) {
        return;
    }

    switch (action) {
        case 'toggle':
            toggleTask(taskId, button.dataset.status);
            break;
        case 'edit':
            showEditForm(taskId);
            break;
        case 'delete':
            deleteTask(taskId);
            break;
        case 'restore':
            restoreTask(taskId);
            break;
        case 'cancel':
            hideEditForm(taskId);
            break;
    }
}

function handleTaskListSubmit(event) {
    const form = event.target.closest('form.edit-form');
    if (!form) {
        return;
    }

    event.preventDefault();
    saveEdit(form.dataset.taskId);
}

// Notification system
function showNotification(message, type = 'info', duration = 4000) {
    const container = document.getElementById('notificationContainer');
    if (!container) {
        alert(message);
        return;
    }
    
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    container.appendChild(notification);

    if (container.children.length > 4) {
        container.removeChild(container.firstElementChild);
    }

    const timeoutId = setTimeout(() => {
        notification.classList.add('hide');
        setTimeout(() => {
            if (container.contains(notification)) {
                container.removeChild(notification);
            }
        }, 300);
    }, duration);
    notification._timeoutId = timeoutId;
}

// OPTIMIZED: Load tasks with pagination support
function loadTasks(page = 1) {
    if (state.isLoading) return;
    
    state.isLoading = true;
    const loading = document.getElementById('loading');
    if (loading) loading.style.display = 'block';
    
    fetch(`/api/search?page=${page}&limit=${state.pageSize}`, {
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => {
            if (response.status === 401) {
                if (loading) loading.style.display = 'none';
                state.isLoading = false;
                window.location.href = '/login';
                return null;
            }
            return response.json();
        })
        .then(data => {
            if (!data) return;
            if (loading) loading.style.display = 'none';
            
            // Handle error responses
            if (data.error) {
                showNotification('✗ Error: ' + data.error, 'error');
                state.isLoading = false;
                return;
            }

            // Handle both new paginated format and legacy format
            let tasks = [];
            let pagination = null;
            
            if (data.data !== undefined) {
                tasks = data.data || [];
                pagination = data.pagination;
                if (pagination) {
                    state.currentPage = pagination.page;
                    state.totalPages = pagination.total_pages;
                }
            } else if (Array.isArray(data)) {
                tasks = data;
            } else if (data.success === true) {
                tasks = data.tasks || [];
            }

            const taskList = document.getElementById('taskList');
            if (!taskList) {
                if (loading) loading.style.display = 'none';
                state.isLoading = false;
                return;
            }
            taskList.innerHTML = '';
            
            const fragment = document.createDocumentFragment();
            if (!tasks || tasks.length === 0) {
                const emptyDiv = document.createElement('div');
                emptyDiv.style.textAlign = 'center';
                emptyDiv.style.padding = '40px';
                emptyDiv.style.color = '#999';
                emptyDiv.textContent = 'No tasks found.';
                fragment.appendChild(emptyDiv);
            } else {
                tasks.forEach(task => {
                    fragment.appendChild(createTaskElement(task));
                });
            }
            taskList.appendChild(fragment);
            
            if (pagination && pagination.total_pages > 1) {
                createPaginationControls(pagination, taskList);
            }
            
            state.isLoading = false;
        })
        .catch(error => {
            if (loading) loading.style.display = 'none';
            console.error('Error loading tasks:', error);
            showNotification('✗ Network error: Could not load tasks', 'error');
            state.isLoading = false;
        });
}

function createTaskElement(task) {
    const card = document.createElement('div');
    card.className = task.status === 'completed' ? 'card completed' : 'card';
    card.dataset.taskId = task.id;

    const titleDiv = document.createElement('div');
    const strong = document.createElement('strong');
    strong.className = 'card-title';
    strong.textContent = task.title;
    titleDiv.appendChild(strong);
    
    const descDiv = document.createElement('div');
    const p = document.createElement('p');
    p.className = 'card-text';
    p.textContent = task.description;
    descDiv.appendChild(p);
    
    const actionsDiv = document.createElement('div');
    actionsDiv.className = 'card-actions';
    
    const toggleBtn = document.createElement('button');
    toggleBtn.type = 'button';
    toggleBtn.textContent = task.status === 'completed' ? 'Mark Pending' : 'Mark Complete';
    toggleBtn.dataset.action = 'toggle';
    toggleBtn.dataset.taskId = task.id;
    toggleBtn.dataset.status = task.status;
    
    const editBtn = document.createElement('button');
    editBtn.type = 'button';
    editBtn.textContent = 'Edit';
    editBtn.dataset.action = 'edit';
    editBtn.dataset.taskId = task.id;
    
    const deleteBtn = document.createElement('button');
    deleteBtn.type = 'button';
    deleteBtn.textContent = 'Delete';
    deleteBtn.dataset.action = 'delete';
    deleteBtn.dataset.taskId = task.id;
    
    actionsDiv.appendChild(toggleBtn);
    actionsDiv.appendChild(editBtn);
    actionsDiv.appendChild(deleteBtn);

    const editForm = document.createElement('form');
    editForm.id = `editForm${task.id}`;
    editForm.className = 'edit-form';
    editForm.dataset.taskId = task.id;
    editForm.style.display = 'none';

    const editInput = document.createElement('input');
    editInput.type = 'text';
    editInput.value = task.title;
    editInput.required = true;
    
    const editTextarea = document.createElement('textarea');
    editTextarea.textContent = task.description;
    
    const saveBtn = document.createElement('button');
    saveBtn.type = 'submit';
    saveBtn.textContent = 'Save';
    
    const cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.textContent = 'Cancel';
    cancelBtn.dataset.action = 'cancel';
    cancelBtn.dataset.taskId = task.id;
    
    editForm.appendChild(editInput);
    editForm.appendChild(editTextarea);
    editForm.appendChild(saveBtn);
    editForm.appendChild(cancelBtn);

    card.appendChild(titleDiv);
    card.appendChild(descDiv);
    card.appendChild(actionsDiv);
    card.appendChild(editForm);
    
    return card;
}

function createPaginationControls(pagination, container) {
    const paginationDiv = document.createElement('div');
    paginationDiv.className = 'pagination-controls';

    if (pagination.page > 1) {
        const prevBtn = document.createElement('button');
        prevBtn.textContent = '← Previous';
        prevBtn.addEventListener('click', () => loadTasks(pagination.page - 1));
        paginationDiv.appendChild(prevBtn);
    }

    const pageInfo = document.createElement('span');
    pageInfo.textContent = `Page ${pagination.page} of ${pagination.total_pages}`;
    paginationDiv.appendChild(pageInfo);

    if (pagination.page < pagination.total_pages) {
        const nextBtn = document.createElement('button');
        nextBtn.textContent = 'Next →';
        nextBtn.addEventListener('click', () => loadTasks(pagination.page + 1));
        paginationDiv.appendChild(nextBtn);
    }

    container.appendChild(paginationDiv);
}

function addTask() {
    const title = document.getElementById('title').value.trim();
    const description = document.getElementById('description').value.trim();
    const category = document.getElementById('category').value.trim();
    const csrfToken = document.getElementById('csrf_token')?.value || '';

    if (!title) {
        showNotification('Task title is required', 'error');
        return;
    }
    
    const submitBtn = document.querySelector('#taskForm button[type="submit"]');
    submitBtn.disabled = true;

    fetch('/add_task', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `title=${encodeURIComponent(title)}&description=${encodeURIComponent(description)}&priority=${encodeURIComponent(category)}&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(r => r.redirected ? {success: true} : r.json())
    .then(result => {
        submitBtn.disabled = false;
        if (result.success) {
            showNotification('✓ Task saved!', 'success');
            document.getElementById('taskForm').reset();
            loadTasks(1);
        } else {
            showNotification('✗ Error: ' + result.error, 'error');
        }
    })
    .catch(() => {
        submitBtn.disabled = false;
        showNotification('✗ Network error.', 'error');
    });
}

function fetchJsonSafe(url, options = {}) {
    return fetch(url, options).then(async (r) => {
        if (!r.ok) throw new Error('Network error');
        return r.json();
    });
}

async function getCsrfTokenOrThrow() {
    // Read from meta tag in base.html
    const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (metaToken) return metaToken;
    
    const inputToken = document.getElementById('csrf_token')?.value;
    if (inputToken) return inputToken;
    
    throw new Error('CSRF token not found');
}

function toggleTask(id, currentStatus) {
    const newStatus = currentStatus === 'completed' ? 'pending' : 'completed';
    getCsrfTokenOrThrow().then(csrf => {
        return fetchJsonSafe(`/edit_task/${id}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}&status=${newStatus}&csrf_token=${csrf}`
        });
    }).then(res => {
        if (res.success) {
            showNotification('✓ Updated!', 'success');
            loadTasks();
        }
    });
}

function restoreTask(id) {
    getCsrfTokenOrThrow()
        .then(csrfToken => {
            return fetch(`/restore_task/${id}`).then(r => r.redirected ? {success: true} : r.json());
        })
        .then(result => {
            if (result && result.success) {
                showNotification('✓ Task restored!', 'success');
                loadTasks();
            } else {
                showNotification('✗ Error: ' + (result.error || 'Failed'), 'error');
            }
        })
        .catch(err => {
            showNotification('✗ Error: ' + err.message, 'error');
        });
}

function showEditForm(id) {
    const form = document.getElementById(`editForm${id}`);
    if (form) form.style.display = 'block';
}

function hideEditForm(id) {
    document.getElementById(`editForm${id}`).style.display = 'none';
}

function saveEdit(id) {
    const form = document.getElementById(`editForm${id}`);
    const title = form.querySelector('input').value.trim();
    const description = form.querySelector('textarea').value.trim();
    getCsrfTokenOrThrow().then(csrf => {
        return fetchJsonSafe(`/edit_task/${id}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}&title=${title}&description=${description}&csrf_token=${csrf}`
        });
    }).then(res => {
        if (res.success) {
            showNotification('✓ Updated!', 'success');
            hideEditForm(id);
            loadTasks();
        }
    });
}

function deleteTask(id) {
    if (!confirm('Archive this task?')) return;
    getCsrfTokenOrThrow().then(csrf => {
        return fetch(`/archive_task/${id}`).then(r => r.redirected ? {success: true} : r.json());
    }).then(res => {
        if (res.success) {
            showNotification('✓ Archived!', 'success');
            loadTasks();
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initializeTaskListHandlers();
    initializeTaskForm(); 
    loadTasks(1);         
});
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
    
    fetch(`get_tasks.php?page=${page}&limit=${state.pageSize}`, {
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => {
            if (response.status === 401) {
                if (loading) loading.style.display = 'none';
                state.isLoading = false;
                window.location.href = 'login.html';
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
                // New paginated format from get_tasks.php
                tasks = data.data || [];
                pagination = data.pagination;
                if (pagination) {
                    state.currentPage = pagination.page;
                    state.totalPages = pagination.total_pages;
                }
            } else if (Array.isArray(data)) {
                // Legacy array format
                tasks = data;
            } else if (data.success === true) {
                // Success response with data field
                tasks = data.tasks || [];
            }

            const taskList = document.getElementById('taskList');
            if (!taskList) {
                console.error('[loadTasks] Missing #taskList container in DOM');
                if (loading) loading.style.display = 'none';
                state.isLoading = false;
                return;
            }
            taskList.innerHTML = '';

            
            const fragment = document.createDocumentFragment();
            if (!tasks || tasks.length === 0) {
                const emptyDiv = document.createElement('div');
                emptyDiv.style.gridColumn = '1 / -1';
                emptyDiv.style.textAlign = 'center';
                emptyDiv.style.padding = '40px';
                emptyDiv.style.color = '#999';
                emptyDiv.textContent = 'No tasks found. Add one to get started!';
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

// OPTIMIZED: Extract task element creation for better memory management
function createTaskElement(task) {
    const card = document.createElement('div');
    card.className = task.status === 'completed' ? 'card completed' : 'card';
    card.dataset.taskId = task.id;

    const titleDiv = document.createElement('div');
    const strong = document.createElement('strong');
    strong.className = 'card-title';
    const categoryIcon = task.category === 'technical' ? '⚙️' : '📝';
    strong.textContent = categoryIcon + ' ' + task.title;
    titleDiv.appendChild(strong);
    
    const descDiv = document.createElement('div');
    const p = document.createElement('p');
    p.className = 'card-text';
    p.textContent = task.description;
    descDiv.appendChild(p);
    
    const infoDiv = document.createElement('div');
    const small = document.createElement('small');
    small.style.color = '#999';
    small.style.fontSize = '0.85em';
    small.textContent = 'Created: ' + new Date(task.created_at).toLocaleString();
    infoDiv.appendChild(small);

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

    // Create edit form
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
    card.appendChild(infoDiv);
    card.appendChild(actionsDiv);
    card.appendChild(editForm);
    
    return card;
}

// Pagination controls
function createPaginationControls(pagination, container) {
    const paginationDiv = document.createElement('div');
    paginationDiv.className = 'pagination-controls';

    if (pagination.page > 1) {
        const prevBtn = document.createElement('button');
        prevBtn.type = 'button';
        prevBtn.textContent = '← Previous';
        prevBtn.addEventListener('click', () => loadTasks(pagination.page - 1));
        paginationDiv.appendChild(prevBtn);
    } else {
        const prevBtn = document.createElement('button');
        prevBtn.type = 'button';
        prevBtn.textContent = '← Previous';
        prevBtn.disabled = true;
        paginationDiv.appendChild(prevBtn);
    }

    const pageInfo = document.createElement('span');
    pageInfo.textContent = `Page ${pagination.page} of ${pagination.total_pages}`;
    paginationDiv.appendChild(pageInfo);

    if (pagination.page < pagination.total_pages) {
        const nextBtn = document.createElement('button');
        nextBtn.type = 'button';
        nextBtn.textContent = 'Next →';
        nextBtn.addEventListener('click', () => loadTasks(pagination.page + 1));
        paginationDiv.appendChild(nextBtn);
    } else {
        const nextBtn = document.createElement('button');
        nextBtn.type = 'button';
        nextBtn.textContent = 'Next →';
        nextBtn.disabled = true;
        paginationDiv.appendChild(nextBtn);
    }

    container.appendChild(paginationDiv);
}

function addTask() {
    console.log('[addTask] Function called');
    // Get form inputs and trim whitespace
    const title = document.getElementById('title').value.trim();
    const description = document.getElementById('description').value.trim();
    const category = document.getElementById('category').value.trim();
    const csrfToken = document.getElementById('csrf_token')?.value || '';

    console.log('[addTask] Title:', title);
    console.log('[addTask] Description:', description);
    console.log('[addTask] Category:', category);

    // Validate that title is provided
    if (!title) {
        console.log('[addTask] Error: Title is empty');
        showNotification('Task title is required', 'error');
        return;
    }
    
    // Validate CSRF token
    if (!csrfToken) {
        console.error('[addTask] Error: CSRF token not found');
        showNotification('Security token missing. Please refresh the page.', 'error');
        return;
    }

    // Find and disable the submit button to prevent duplicate submissions
    const submitBtn = document.querySelector('#taskForm button[type="submit"]');
    if (!submitBtn) {
        console.error('[addTask] Error: Submit button not found');
        showNotification('Form error: button not found', 'error');
        return;
    }

    // Disable button and show loading state
    submitBtn.disabled = true;
    submitBtn.textContent = 'Adding...';

    console.log('[addTask] Sending request to add_task.php');

    // Send POST request with form data
    fetch('add_task.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        // URL encode the data to prevent special characters from breaking the request
        body: `title=${encodeURIComponent(title)}&description=${encodeURIComponent(description)}&category=${encodeURIComponent(category)}&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => {
        // Check if response is valid JSON before parsing
        console.log('[addTask] Response received, status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(result => {
        console.log('[addTask] Response data:', result);
        // Re-enable submit button
        submitBtn.disabled = false;
        submitBtn.textContent = 'Add Task';
        
        // Check if server returned success
        if (result.success) {
            console.log('[addTask] Success! Task ID:', result.task_id);
            // Show success notification
            showNotification('✓ Task saved successfully!', 'success');
            // Clear the form fields for next entry
            document.getElementById('taskForm').reset();
            console.log('[addTask] Loading tasks to refresh list...');
            // Reload tasks to show the new one
            // Only refresh tasks list when the container exists on the current page.
            // (Do not redirect automatically; user controls navigation.)
            if (document.getElementById('taskList')) {
                loadTasks(1); // Reset to page 1 to see new task
            }

        } else {

            console.log('[addTask] Error:', result.error);
            // Show error from server
            showNotification('✗ Error: ' + (result.error || 'Failed to add task'), 'error');
        }
    })
    .catch(error => {
        console.error('[addTask] Network error:', error);
        // Re-enable button on error
        submitBtn.disabled = false;
        submitBtn.textContent = 'Add Task';
        // Show network error notification
        showNotification('✗ Network error: Could not save task. Check your connection.', 'error');
    });
}

function fetchJsonSafe(url, fetchOptions = {}) {
    return fetch(url, fetchOptions).then(async (response) => {
        const contentType = response.headers.get('content-type') || '';

        // If server returns HTML/error page, return it as text so we can display it.
        if (!contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error(`Non-JSON response (${response.status}). ${text.slice(0, 200)}`);
        }

        return response.json();
    });
}

async function getCsrfTokenOrThrow() {
    const existing = document.getElementById('csrf_token')?.value;
    if (existing) return existing;

    const r = await fetch('get_csrf_token.php', {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await r.json();
    if (!data || !data.token) throw new Error('Security token missing');
    const el = document.getElementById('csrf_token');
    if (el) el.value = data.token;
    return data.token;
}

function toggleTask(id, currentStatus) {
    const newStatus = currentStatus === 'completed' ? 'pending' : 'completed';

    getCsrfTokenOrThrow()
        .then(csrfToken => {
            return fetchJsonSafe('toggle_task.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `id=${encodeURIComponent(id)}&status=${encodeURIComponent(newStatus)}&csrf_token=${encodeURIComponent(csrfToken)}`
            });
        })
        .then(result => {
            if (result && result.success) {
                showNotification('✓ Task marked ' + newStatus + '!', 'success');
                loadTasks();
            } else {
                showNotification('✗ Error: ' + ((result && result.error) || 'Failed to update task'), 'error');
            }
        })
        .catch(err => {
            console.error('toggleTask error:', err);
            showNotification('✗ Error: ' + err.message, 'error', 6000);
        });
}


function showEditForm(id) {
    const form = document.getElementById(`editForm${id}`);
    if (form) {
        form.style.display = 'block';
        const titleInput = form.querySelector('input');
        if (titleInput) {
            titleInput.focus();
        }
    }
}

function hideEditForm(id) {
    document.getElementById(`editForm${id}`).style.display = 'none';
}

function saveEdit(id) {
    const form = document.getElementById(`editForm${id}`);
    const title = form.querySelector('input').value.trim();
    const description = form.querySelector('textarea').value.trim();

    if (!title) {
        showNotification('Task title is required', 'error');
        return;
    }

    getCsrfTokenOrThrow()
        .then(csrfToken => {
            return fetchJsonSafe('edit_task.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `id=${encodeURIComponent(id)}&title=${encodeURIComponent(title)}&description=${encodeURIComponent(description)}&csrf_token=${encodeURIComponent(csrfToken)}`
            });
        })
        .then(result => {
            if (result && result.success) {
                showNotification('✓ Task updated successfully!', 'success');
                hideEditForm(id);
                loadTasks();
            } else {
                showNotification('✗ Error: ' + ((result && result.error) || 'Failed to update task'), 'error');
            }
        })
        .catch(err => {
            console.error('saveEdit error:', err);
            showNotification('✗ Error: ' + err.message, 'error', 6000);
        });
}



function deleteTask(id) {
    if (!confirm('Archive this task? You can restore it later from the Archive.')) {
        return;
    }

    getCsrfTokenOrThrow()
        .then(csrfToken => {
            return fetchJsonSafe('delete_task.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `id=${encodeURIComponent(id)}&csrf_token=${encodeURIComponent(csrfToken)}`
            });
        })
        .then(result => {
            if (result && result.success) {
                showNotification('✓ Task archived successfully! Visit Archive to restore.', 'success');
                loadTasks();
            } else {
                showNotification('✗ Error: ' + ((result && result.error) || 'Failed to archive task'), 'error');
            }
        })
        .catch(err => {
            console.error('deleteTask error:', err);
            showNotification('✗ Error: ' + err.message, 'error', 6000);
        });
}



document.addEventListener('DOMContentLoaded', initializeTaskListHandlers);
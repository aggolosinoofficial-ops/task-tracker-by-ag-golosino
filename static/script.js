// OPTIMIZATION: Global state tracking to prevent memory leaks
const appState = {
    currentPage: 1,
    pageSize: 50,
    totalPages: 1,
    isLoading: false,
    sortBy: 'id',
    sortOrder: 'descending'
};
let taskXmlDoc = null;
let autoSaveTimeout = null;

/**
 * Helper to highlight matching text for UI search
 */
function highlightText(text, term) {
    if (!term || !text) return text;
    const regex = new RegExp(`(${term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return text.replace(regex, '<mark class="search-highlight">$1</mark>');
}

/**
 * Modernized Render Function
 */
async function renderTasksModern(tasksToRender = null, forceFetch = false) {
    const container = document.getElementById('taskList') || document.getElementById('archiveList');
    const loader = document.getElementById('loading');
    if (!container) return;

    const isArchiveView = container.id === 'archiveList';
    const apiEndpoint = isArchiveView ? '/api/raw_archive' : '/api/raw_tasks';

    // Cache invalidation: If we switched between Archive and Active views, force fetch
    const lastViewType = localStorage.getItem('lastViewType');
    const currentViewType = isArchiveView ? 'archive' : 'active';
    if (lastViewType !== currentViewType) {
        forceFetch = true;
        localStorage.setItem('lastViewType', currentViewType);
    }

    try {
        // Optimization: Only fetch from server if we don't have data yet or explicitly forced
        if (!taskXmlDoc || forceFetch) {
            if (loader) loader.style.display = 'block';
            const xmlResponse = await fetch(apiEndpoint, { cache: 'no-store' });
            const xmlText = await xmlResponse.text();
            const parser = new DOMParser();
            taskXmlDoc = parser.parseFromString(xmlText, "application/xml");
            // Clear local search/filter if forcing a fresh fetch from server
            if (forceFetch && !tasksToRender) document.getElementById('taskSearchInput').value = '';
        }

        // Use provided tasks (filtered) or all tasks from the XML
        const tasks = tasksToRender || Array.from(taskXmlDoc.getElementsByTagName('task'));

        // Get current search term for highlighting
        const searchTerm = document.getElementById('taskSearchInput')?.value.trim() || '';

        container.innerHTML = '';
        if (loader) loader.style.display = 'none';

        if (tasks.length === 0) {
            container.innerHTML = '<div class="alert alert-info w-100">No active tasks found.</div>';
            return;
        }

        // Add Select All header for Archive View
        if (isArchiveView) {
            const header = document.createElement('div');
            header.className = 'select-all-header mb-3 p-3 rounded shadow-sm d-flex align-items-center';
            header.innerHTML = `
                <div class="form-check ms-1">
                    <input class="form-check-input" type="checkbox" id="selectAllTasks" onchange="toggleSelectAll(this.checked)">
                    <label class="form-check-label ms-2 fw-bold text-secondary" for="selectAllTasks">Select All Archived Tasks</label>
                </div>
            `;
            container.appendChild(header);
        }

        tasks.forEach(task => {
            const getVal = (tag) => task.getElementsByTagName(tag)[0]?.textContent || '';
            const id = getVal('id');
            const title = getVal('title');
            const description = getVal('description');
            const priority = getVal('priority');
            const status = getVal('status');
            const dueDate = getVal('due_date');

            const highlightedTitle = highlightText(title, searchTerm);
            const highlightedDescription = highlightText(description, searchTerm);

            const checkboxHtml = isArchiveView ? `
                <div class="form-check me-3">
                    <input class="form-check-input task-selector" type="checkbox" value="${id}">
                </div>
            ` : '';

            const card = document.createElement('div');
            card.className = `card task-card priority-${priority.toLowerCase()} status-${status}`;
            card.innerHTML = `
                <div class="card-body">
                    <div class="d-flex align-items-start mb-2">
                        ${checkboxHtml}
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <h5 class="card-title mb-0">${highlightedTitle}</h5>
                                <span class="badge priority-badge priority-${priority.toLowerCase()}">${priority}</span>
                            </div>
                        </div>
                    </div>
                    <p class="card-text text-muted">${highlightedDescription || 'No description provided.'}</p>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <small class="text-secondary">📅 ${dueDate || 'No date'}</small>
                        <div class="card-actions">
                            ${isArchiveView ? `
                                <button type="button" class="btn btn-sm btn-edit" data-action="restore" data-task-id="${id}">Restore</button>
                            ` : `
                                <button type="button" class="btn btn-sm btn-complete" data-action="toggle" data-task-id="${id}" data-status="${status}">
                                    ${status === 'completed' ? 'Reopen' : 'Done'}
                                </button>
                                <button type="button" class="btn btn-sm btn-edit" data-action="edit" data-task-id="${id}">Edit</button>
                                <button type="button" class="btn btn-sm btn-delete" data-action="delete" data-task-id="${id}">Archive</button>
                            `}
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(card);
        });
        
        // Trigger the Neon Border logic from ui.js
        document.dispatchEvent(new CustomEvent('taskUpdated'));
        
    } catch (err) {
        console.error("XSLT Error:", err);
    }
}

function applyFilters() {
    if (!taskXmlDoc) return;
    
    const term = document.getElementById('taskSearchInput').value.toLowerCase();
    const priority = document.getElementById('priorityFilter').value;
    
    const allTasks = Array.from(taskXmlDoc.getElementsByTagName('task'));
    
    const filtered = allTasks.filter(t => {
        const title = t.getElementsByTagName('title')[0]?.textContent.toLowerCase() || '';
        const desc = t.getElementsByTagName('description')[0]?.textContent.toLowerCase() || '';
        const p = t.getElementsByTagName('priority')[0]?.textContent || 'Medium';
        
        const matchesSearch = title.includes(term) || desc.includes(term);
        const matchesPriority = priority === 'All' || p === priority;
        
        return matchesSearch && matchesPriority;
    });
    
    renderTasksModern(filtered, false);
}

/**
 * Triggers a debounced auto-save for the task currently in the edit modal
 */
function triggerAutoSave() {
    const id = document.getElementById('editTaskId').value;
    if (!id) return;

    // Clear existing timer
    if (autoSaveTimeout) clearTimeout(autoSaveTimeout);

    // Set new timer (1.5 seconds delay)
    autoSaveTimeout = setTimeout(() => {
        const title = document.getElementById('editTitle').value.trim();
        const description = document.getElementById('editDescription').value.trim();
        const priority = document.getElementById('editPriority').value;
        const dueDate = document.getElementById('editDueDate').value;

        // Don't auto-save if title is empty
        if (!title) return;

        fetchJsonSafe(`/edit_task/${id}`, {
            method: 'POST',
            body: JSON.stringify({ title, description, priority, due_date: dueDate })
        }).then(res => {
            if (res.success) {
                // Silently refresh the XML data so the background UI is current
                // We pass true to forceFetch to ensure the cache is updated
                renderTasksModern(null, true);
                document.dispatchEvent(new CustomEvent('taskUpdated'));
            }
        }).catch(err => {
            console.warn('Auto-save background sync failed:', err.message);
        });
    }, 1500);
}

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

    const editTaskForm = document.getElementById('editTaskForm');
    if (editTaskForm && !editTaskForm._initialized) {
        editTaskForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveEdit();
        });
        editTaskForm._initialized = true;

        // Attach auto-save listeners to edit fields
        const autoSaveFields = ['editTitle', 'editDescription', 'editPriority', 'editDueDate'];
        autoSaveFields.forEach(fieldId => {
            const el = document.getElementById(fieldId);
            if (!el) return;
            el.addEventListener(el.tagName === 'SELECT' || el.type === 'date' ? 'change' : 'input', triggerAutoSave);
        });
    }
}

function initializeTaskListHandlers() {
    const taskList = document.getElementById('taskList');
    if (taskList && !taskList._initialized) {
        taskList.addEventListener('click', handleTaskListClick);
        taskList.addEventListener('submit', handleTaskListSubmit);
        taskList._initialized = true;
    }

    const archiveList = document.getElementById('archiveList');
    if (archiveList && !archiveList._initialized) {
        archiveList.addEventListener('click', handleTaskListClick);
        archiveList._initialized = true;
    }
}

function handleTaskListClick(event) {
    // Handle Header Sorting
    const header = event.target.closest('.sort-header');
    if (header) {
        const column = header.dataset.column;
        if (appState.sortBy === column) {
            appState.sortOrder = appState.sortOrder === 'ascending' ? 'descending' : 'ascending';
        } else {
            appState.sortBy = column;
            appState.sortOrder = 'ascending';
        }
        renderTasksModern(); // Refresh with current sort state
        return;
    }

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

function animateValue(id, start, end, duration) {
    const obj = document.getElementById(id);
    if (!obj) return;
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        obj.innerHTML = Math.floor(progress * (end - start) + start);
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    obj.classList.add('count-glow');
    window.requestAnimationFrame(step);
    setTimeout(() => obj.classList.remove('count-glow'), duration);
}

function updateDashboardSummary() {
    // Fetch real stats from server instead of counting DOM elements (which breaks with pagination)
    fetchJsonSafe('/api/insights')
    .then(stats => {
        if (!stats) return;
        // Update Dashboard Summary Cards
        animateValue('total-count', 0, stats.total_active || 0, 1000);
        animateValue('completed-count', 0, stats.completed || 0, 1000);
        animateValue('pending-count', 0, stats.pending || 0, 1000);
        animateValue('archived-count', 0, stats.archived || 0, 1000);

        // Update Insights Page specific stats if they exist
        const rateEl = document.getElementById('completion-rate');
        if (rateEl) {
            const rate = Math.round(stats.completion_rate || 0);
            animateValue('completion-rate', 0, rate, 1000);
            // Update progress bar if applicable
            const progressBar = document.querySelector('.progress-bar');
            if (progressBar) progressBar.style.width = `${rate}%`;
        }

        // Update High-Priority Productivity Score with color coding
        const scoreEl = document.getElementById('productivity-score');
        if (scoreEl) {
            const score = Math.round(stats.productivity_score || 0);
            animateValue('productivity-score', 0, score, 1000);

            // Set performance indicator color
            const parent = scoreEl.parentElement;
            parent.classList.remove('perf-low', 'perf-mid', 'perf-high');
            if (score <= 40) parent.classList.add('perf-low');
            else if (score <= 75) parent.classList.add('perf-mid');
            else parent.classList.add('perf-high');
        }
    })
    .catch(err => console.error('Could not update dashboard summary:', err));
}

/**
 * Periodically checks system health (lingering locks, storage status)
 */
function checkSystemHealth() {
    const indicator = document.getElementById('healthStatus');
    if (!indicator) return;

    fetchJsonSafe('/api/health')
        .then(health => {
            indicator.classList.remove('status-healthy', 'status-warning', 'status-error');
            
            if (health.locks > 0 && health.status.includes('Warning')) {
                indicator.classList.add('status-warning');
                indicator.title = 'Warning: Active or lingering file locks detected.';
            } else if (health.status === 'Healthy') {
                indicator.classList.add('status-healthy');
                indicator.title = 'System Healthy: Storage synchronized.';
            } else {
                indicator.classList.add('status-error');
                indicator.title = 'Error: Storage issues detected.';
            }
        })
        .catch(() => {
            indicator.classList.add('status-error');
            indicator.title = 'Cannot connect to health service.';
        });
}

function createTaskElement(task) {
    const card = document.createElement('div');
    card.className = task.status === 'completed' ? 'card task-card completed' : 'card task-card';
    card.dataset.taskId = task.id;
    card.dataset.status = task.status || 'pending';

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
    
    if (task.due_date) {
        const dateSmall = document.createElement('small');
        dateSmall.className = 'text-muted d-block mb-2';
        dateSmall.textContent = `Due: ${task.due_date}`;
        descDiv.appendChild(dateSmall);
    }

    const actionsDiv = document.createElement('div');
    actionsDiv.className = 'card-actions';
    
    const toggleBtn = document.createElement('button');
    toggleBtn.type = 'button';
    toggleBtn.className = 'btn-complete';
    toggleBtn.textContent = task.status === 'completed' ? 'Mark Pending' : 'Mark Complete';
    toggleBtn.dataset.action = 'toggle';
    toggleBtn.dataset.taskId = task.id;
    toggleBtn.dataset.status = task.status;
    
    const editBtn = document.createElement('button');
    editBtn.type = 'button';
    editBtn.className = 'btn-edit';
    editBtn.textContent = 'Edit';
    editBtn.dataset.action = 'edit';
    editBtn.dataset.taskId = task.id;
    
    const deleteBtn = document.createElement('button');
    deleteBtn.type = 'button';
    deleteBtn.className = 'btn-delete';
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
    const dueDateEl = document.getElementById('due_date');
    const due_date = dueDateEl ? dueDateEl.value : '';
    
    // Support both 'category' and 'priority' IDs to be safe
    if (!title) {
        showNotification('Task title is required', 'error');
        return;
    }
    
    const priorityEl = document.getElementById('priority') || document.getElementById('category');
    const priority = priorityEl ? priorityEl.value.trim() : 'Medium';

    const submitBtn = document.querySelector('#taskForm button[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;

    fetchJsonSafe('/add_task', {
        method: 'POST',
        body: JSON.stringify({ title, description, priority, due_date })
    })
    .then(result => {
        if (submitBtn) submitBtn.disabled = false;
        if (result.success) {
            showNotification('✓ Task saved!', 'success');
            document.getElementById('taskForm').reset();
            renderTasksModern(null, true);
            document.dispatchEvent(new CustomEvent('taskUpdated'));
        } else {
            showNotification('✗ Error: ' + result.error, 'error');
        }
    })
    .catch((err) => {
        if (submitBtn) submitBtn.disabled = false;
        showNotification('✗ Error: ' + err.message, 'error');
    });
}

function quickAddTask() {
    const input = document.getElementById('taskInput');
    if (!input) return;
    const title = input.value.trim();
    if (!title) {
        showNotification('Please enter a task title', 'error');
        return;
    }
    
    fetchJsonSafe('/add_task', {
        method: 'POST',
        body: JSON.stringify({ title, description: '', priority: 'Medium' })
    })
    .then(result => {
        if (result.success) {
            input.value = '';
            showNotification('✓ Task added!', 'success');
            if (document.getElementById('taskList')) {
                renderTasksModern(null, true);
                document.dispatchEvent(new CustomEvent('taskUpdated'));
            }
        } else {
            showNotification('✗ ' + result.error, 'error');
        }
    })
    .catch((err) => showNotification('✗ Error: ' + err.message, 'error'));
}

/**
 * UI helper to show/hide the Saving pill
 */
function toggleSavingIndicator(show) {
    let el = document.getElementById('saveIndicator');
    if (!el) {
        el = document.createElement('div');
        el.id = 'saveIndicator';
        el.className = 'save-indicator';
        el.innerHTML = '<span>⚡</span> Saving...';
        document.body.appendChild(el);
    }
    el.style.display = show ? 'flex' : 'none';
}

async function fetchJsonSafe(url, options = {}) {
    // Automatically inject CSRF token for POST/PUT/DELETE requests
    const isMutation = options.method && options.method !== 'GET';
    
    if (options.method && options.method !== 'GET') {
        const token = await getCsrfTokenOrThrow();
        options.headers = {
            ...options.headers,
            'X-CSRFToken': token,
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
    }
    
    if (isMutation) toggleSavingIndicator(true);
    
    try {
        const r = await fetch(url, options);
        const data = await r.json();
        if (!r.ok) {
            throw new Error(data.error || `Server Error (${r.status})`);
        }
        return data;
    } catch (err) {
        throw new Error(err.message || "Network request failed");
    } finally {
        if (isMutation) toggleSavingIndicator(false);
    }
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

    // If completing, check if user wants to archive it right away
    if (newStatus === 'completed') {
        if (confirm('Task completed! Move to archive?')) {
            // First update status to completed, then archive
            return fetchJsonSafe(`/edit_task/${id}`, {
                method: 'POST',
                body: JSON.stringify({ status: 'completed' })
            }).then(() => {
                return fetchJsonSafe(`/archive_task/${id}`, { method: 'POST' }).then(() => {
                    showNotification('✓ Task completed and archived', 'success');
                    renderTasksModern(null, true);
                    document.dispatchEvent(new CustomEvent('taskUpdated'));
                });
            });
        }
    } else {
        if (!confirm('Mark as pending?')) return;
    }

    fetchJsonSafe(`/edit_task/${id}`, {
        method: 'POST',
        body: JSON.stringify({ status: newStatus })
    }).then(res => {
        if (res.success) {
            showNotification('✓ Updated!', 'success');
            renderTasksModern(null, true);
            document.dispatchEvent(new CustomEvent('taskUpdated'));
        } else {
            showNotification('✗ Error: ' + (res.error || 'Failed to update status'), 'error');
        }
    })
    .catch(err => showNotification('✗ Error: ' + err.message, 'error'));
}

async function restoreTask(id) {
    try {
        const result = await fetchJsonSafe(`/restore_task/${id}`, { method: 'POST' });
        if (result.success) {
            showNotification('✓ Task restored!', 'success');
            // Force re-render with fresh data from server
            await renderTasksModern(null, true);
            document.dispatchEvent(new CustomEvent('taskUpdated'));
        } else {
            showNotification('✗ Error: ' + (result.error || 'Failed to restore'), 'error');
        }
    } catch (err) {
        showNotification('✗ Error: ' + err.message, 'error');
    }
}

function toggleSelectAll(checked) {
    const list = document.getElementById('archiveList');
    if (!list) return;
    const checkboxes = list.querySelectorAll('.task-selector');
    checkboxes.forEach(cb => cb.checked = checked);
}

function restoreAllTasks() {
    if (!confirm('Move all archived tasks back to the active list?')) return;
    
    fetchJsonSafe('/api/tasks/restore_bulk', { method: 'POST' })
        .then(res => {
            if (res.success) {
                showNotification('✓ All tasks restored!', 'success');
                renderTasksModern(null, true);
                document.dispatchEvent(new CustomEvent('taskUpdated'));
            }
        })
        .catch(err => showNotification('✗ Error: ' + err.message, 'error'));
}

function restoreSelectedTasks() {
    const selectedIds = Array.from(document.querySelectorAll('.task-selector:checked')).map(cb => cb.value);
    if (selectedIds.length === 0) {
        showNotification('Please select tasks to restore.', 'info');
        return;
    }

    fetchJsonSafe('/api/tasks/restore_bulk', { 
        method: 'POST', 
        body: JSON.stringify({ task_ids: selectedIds }) 
    }).then(res => {
        if (res.success) {
            showNotification(`✓ ${selectedIds.length} tasks restored!`, 'success');
            renderTasksModern(null, true);
            document.dispatchEvent(new CustomEvent('taskUpdated'));
        }
    })
    .catch(err => showNotification('✗ Error: ' + err.message, 'error'));
}

function showEditForm(id) {
    if (!taskXmlDoc) return;
    
    const tasks = Array.from(taskXmlDoc.getElementsByTagName('task'));
    const task = tasks.find(t => t.getElementsByTagName('id')[0]?.textContent === id);
    
    if (!task) return;

    const getVal = (tag) => task.getElementsByTagName(tag)[0]?.textContent || '';

    document.getElementById('editTaskId').value = id;
    document.getElementById('editTitle').value = getVal('title');
    document.getElementById('editDescription').value = getVal('description');
    document.getElementById('editPriority').value = getVal('priority') || 'Medium';
    document.getElementById('editDueDate').value = getVal('due_date');

    const modal = new bootstrap.Modal(document.getElementById('editTaskModal'));
    modal.show();
}

function hideEditForm(id) {
    // No longer needed with Bootstrap Modal
}

function saveEdit() {
    // Cancel any pending auto-save if manual save is clicked
    if (autoSaveTimeout) clearTimeout(autoSaveTimeout);

    const id = document.getElementById('editTaskId').value;
    const title = document.getElementById('editTitle').value.trim();
    const description = document.getElementById('editDescription').value.trim();
    const priority = document.getElementById('editPriority').value;
    const dueDate = document.getElementById('editDueDate').value;

    fetchJsonSafe(`/edit_task/${id}`, {
        method: 'POST',
        body: JSON.stringify({ title, description, priority, due_date: dueDate })
    }).then(res => {
        if (res.success) {
            showNotification('✓ Updated!', 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('editTaskModal'));
            modal.hide();
            renderTasksModern(null, true);
            document.dispatchEvent(new CustomEvent('taskUpdated'));
        } else {
            showNotification('✗ Error: ' + (res.error || 'Failed to save changes'), 'error');
        }
    })
    .catch(err => showNotification('✗ Error: ' + err.message, 'error'));
}

function clearCompletedTasks() {
    if (!confirm('Are you sure you want to move all completed tasks to the archive?')) return;

    fetchJsonSafe('/api/tasks/clear_completed', {
        method: 'POST'
    }).then(res => {
        if (res.success) {
            showNotification('✓ Completed tasks archived!', 'success');
            // Force re-fetch of XML and re-render the task list
            renderTasksModern(null, true);
            // Trigger dashboard and insight updates
            document.dispatchEvent(new CustomEvent('taskUpdated'));
        } else {
            showNotification('✗ Error: ' + (res.error || 'Failed to clear tasks'), 'error');
        }
    }).catch(err => {
        console.error('Clear completed error:', err);
        showNotification('✗ Network error occurred.', 'error');
    });
}

function deleteTask(id) {
    if (!confirm('Archive this task?')) return;
    fetchJsonSafe(`/archive_task/${id}`, {
        method: 'POST'
    })
    .then(res => {
        if (res.success) {
            showNotification('✓ Archived!', 'success');
            renderTasksModern(null, true);
            document.dispatchEvent(new CustomEvent('taskUpdated'));
        } else {
            showNotification('✗ Error: ' + (res.error || 'Failed to archive task'), 'error');
        }
    })
    .catch(err => showNotification('✗ Error: ' + err.message, 'error'));
}

function initializeTheme() {
    const toggle = document.getElementById('darkModeToggle');
    const isDark = localStorage.getItem('theme') === 'dark';
    
    if (isDark) document.body.classList.add('dark-mode');

    toggle?.addEventListener('click', (e) => {
        e.preventDefault();
        const wasDark = document.body.classList.toggle('dark-mode');
        localStorage.setItem('theme', wasDark ? 'dark' : 'light');
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initializeTheme();
    initializeTaskListHandlers();
    initializeTaskForm(); 
    
    // Real-time Insights: Update dashboard whenever a task is changed
    document.addEventListener('taskUpdated', () => {
        if (typeof updateDashboardSummary === 'function') updateDashboardSummary();
    });

    // Only load tasks if we are on a page with a task list
    if (document.getElementById('taskList') || document.getElementById('archiveList')) {
        renderTasksModern();
    }

    // Health Check: Initial run and then every 30 seconds
    checkSystemHealth();
    setInterval(checkSystemHealth, 30000);
});
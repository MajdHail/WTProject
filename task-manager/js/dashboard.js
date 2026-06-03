/**
 * Dashboard — js/dashboard.js
 *
 * This is a Single-Page Application (SPA) pattern: the PHP page only renders
 * the HTML shell. All task data is loaded and manipulated via fetch() calls to
 * the JSON API (api/tasks.php and api/attachments.php), and the DOM is rebuilt
 * in JavaScript — no full page reloads after the initial load.
 *
 * State lives in three variables:
 *   tasks      — the current list fetched from the server
 *   filterMode — which tab is active ('all' | 'pending' | 'completed')
 *   editingId  — the id of the task open in the modal, or null for a new task
 */

// ── Application state ─────────────────────────────────────────────────────────
let tasks      = [];      // in-memory copy of today's tasks
let filterMode = 'all';   // active filter tab
let editingId  = null;    // task currently open in the modal (null = new task)
let deletingId = null;    // task queued for deletion in the confirm dialog
let pendingFiles = [];

// ── DOM references (cached at startup to avoid repeated querySelector calls) ──
const taskList      = document.getElementById('taskList');
const modalOverlay  = document.getElementById('modalOverlay');
const modalTitle    = document.getElementById('modalTitle');
const deleteOverlay = document.getElementById('deleteOverlay');
const attachPanel   = document.getElementById('attachPanel');
const attachList    = document.getElementById('attachList');

// Form field references grouped in one object for easy bulk read/write.
const fields = {
    id:          document.getElementById('taskId'),
    title:       document.getElementById('taskTitle'),
    description: document.getElementById('taskDescription'),
    dueDate:     document.getElementById('taskDueDate'),
    dueTime:     document.getElementById('taskDueTime'),
    priority:    document.getElementById('taskPriority'),
    status:      document.getElementById('taskStatus'),
    notes:       document.getElementById('taskNotes'),
};

// ── API helpers ───────────────────────────────────────────────────────────────

/**
 * Thin wrapper around fetch() that always sends/expects JSON and throws on
 * non-2xx responses, so all callers can use try/catch for error handling.
 */
async function apiFetch(url, opts = {}) {
    const res = await fetch(url, { headers: { 'Content-Type': 'application/json' }, ...opts });
    if (!res.ok) throw new Error((await res.json()).error || 'Request failed');
    return res.json();
}

async function loadTasks() {
    try {
        tasks = await apiFetch(`api/tasks.php?date=${TODAY}`);
        renderTasks();
        updateStats();
    } catch (e) {
        taskList.innerHTML = `<div class="empty-state"><p style="color:var(--danger)">Error loading tasks: ${e.message}</p></div>`;
    }
}

function renderTasks() {
    let filtered = tasks;
    if (filterMode === 'pending')   filtered = tasks.filter(t => t.status === 'pending');
    if (filterMode === 'completed') filtered = tasks.filter(t => t.status === 'completed');

    if (filtered.length === 0) {
        taskList.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">📋</div>
                <h4>No tasks ${filterMode !== 'all' ? `marked as <strong>${filterMode}</strong>` : 'for today'}</h4>
                <p>Click <strong>+ New Task</strong> to get started.</p>
            </div>`;
        return;
    }

    taskList.innerHTML = filtered.map(task => taskCard(task)).join('');
    taskList.querySelectorAll('.task-checkbox').forEach(cb => {
        cb.addEventListener('change', () => toggleStatus(parseInt(cb.dataset.id), cb.checked));
    });
}

function taskCard(t) {
    const checked     = t.status === 'completed';
    const priorityLbl = { low: 'Low', medium: 'Medium', high: 'High' }[t.priority] || t.priority;
    const timeLabel   = t.due_time ? `<span class="task-time">⏱ ${formatTime(t.due_time)}</span>` : '';
    const notesIcon   = t.notes ? '<span class="task-has-notes" title="Has notes">📝</span>' : '';

    return `
    <div class="task-card ${checked ? 'task-done' : ''}" data-id="${t.id}">
        <div class="task-left">
            <input type="checkbox" class="task-checkbox" data-id="${t.id}" ${checked ? 'checked' : ''}>
        </div>
        <div class="task-body">
            <div class="task-title">${escHtml(t.title)}</div>
            ${t.description ? `<div class="task-desc">${escHtml(t.description)}</div>` : ''}
            <div class="task-meta">
                <span class="priority-badge priority-${t.priority}">${priorityLbl}</span>
                ${timeLabel}
                ${notesIcon}
            </div>
            ${t.notes ? `<div class="task-notes">${escHtml(t.notes)}</div>` : ''}
        </div>
        <div class="task-actions">
            <button class="icon-btn edit-btn" data-id="${t.id}" title="Edit">✏</button>
            <button class="icon-btn delete-btn" data-id="${t.id}" title="Delete">🗑</button>
        </div>
    </div>`;
}

function updateStats() {
    const total     = tasks.length;
    const completed = tasks.filter(t => t.status === 'completed').length;
    const pending   = tasks.filter(t => t.status === 'pending').length;
    const high      = tasks.filter(t => t.priority === 'high').length;

    document.getElementById('statTotal').textContent     = total;
    document.getElementById('statCompleted').textContent = completed;
    document.getElementById('statPending').textContent   = pending;
    document.getElementById('statHigh').textContent      = high;
}

async function toggleStatus(id, checked) {
    try {
        const updated = await apiFetch('api/tasks.php', {
            method: 'PUT',
            body: JSON.stringify({ id, status: checked ? 'completed' : 'pending' }),
        });
        tasks = tasks.map(t => t.id === id ? updated : t);
        renderTasks();
        updateStats();
    } catch (e) { showToast(e.message, 'error'); }
}

async function ensureTaskSaved() {
    if (editingId) return true;

    const title = fields.title.value.trim();
    if (!title) {
        showToast('Please enter a title before uploading files.', 'error');
        fields.title.focus();
        return false;
    }

    const payload = {
        title,
        description: fields.description.value.trim(),
        notes:       fields.notes.value.trim(),
        due_date:    fields.dueDate.value || TODAY,
        due_time:    fields.dueTime.value,
        priority:    fields.priority.value,
        status:      fields.status.value,
    };

    try {
        const created = await apiFetch('api/tasks.php', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
        editingId = created.id;
        if (created.due_date === TODAY) tasks.unshift(created);
        modalTitle.textContent = 'Edit Task';
        renderTasks();
        updateStats();
        return true;
    } catch (e) {
        showToast(e.message, 'error');
        return false;
    }
}

async function saveTask() {
    const title = fields.title.value.trim();
    if (!title) { fields.title.focus(); showToast('Title is required', 'error'); return; }

    const payload = {
        title,
        description: fields.description.value.trim(),
        notes:       fields.notes.value.trim(),
        due_date:    fields.dueDate.value || TODAY,
        due_time:    fields.dueTime.value,
        priority:    fields.priority.value,
        status:      fields.status.value,
    };

    try {
        if (editingId) {
            const updated = await apiFetch('api/tasks.php', {
                method: 'PUT',
                body: JSON.stringify({ id: editingId, ...payload }),
            });
            tasks = tasks.map(t => t.id === editingId ? updated : t);
            showToast('Task saved!', 'success');
        } else {
            const created = await apiFetch('api/tasks.php', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            editingId = created.id;
            if (created.due_date === TODAY) tasks.unshift(created);
            showToast('Task created!', 'success');
        }
        closeModal();
        renderTasks();
        updateStats();
    } catch (e) { showToast(e.message, 'error'); }
}

async function deleteTask() {
    if (!deletingId) return;
    try {
        await apiFetch('api/tasks.php', {
            method: 'DELETE',
            body: JSON.stringify({ id: deletingId }),
        });
        tasks = tasks.filter(t => t.id !== deletingId);
        closeDeleteModal();
        renderTasks();
        updateStats();
        showToast('Task deleted.', 'success');
    } catch (e) { showToast(e.message, 'error'); }
}

function openCreateModal() {
    editingId = null;
    modalTitle.textContent = 'New Task';
    Object.values(fields).forEach(f => { if (f) f.value = ''; });
    fields.dueDate.value  = TODAY;
    fields.priority.value = 'medium';
    fields.status.value   = 'pending';
    attachPanel.style.display = '';
    attachList.innerHTML = '<p class="attach-empty">Save or add a title to enable uploads.</p>';
    modalOverlay.classList.add('visible');
    fields.title.focus();
}

function openEditModal(id) {
    const task = tasks.find(t => t.id === id);
    if (!task) return;
    editingId = task.id;
    modalTitle.textContent       = 'Edit Task';
    fields.title.value           = task.title;
    fields.description.value     = task.description || '';
    fields.notes.value           = task.notes || '';
    fields.dueDate.value         = task.due_date || '';
    fields.dueTime.value         = task.due_time || '';
    fields.priority.value        = task.priority;
    fields.status.value          = task.status;
    attachPanel.style.display    = '';
    loadAttachments(task.id);
    modalOverlay.classList.add('visible');
    fields.title.focus();
}

function closeModal() {
    modalOverlay.classList.remove('visible');
    editingId = null;
}

function openDeleteModal(id) {
    deletingId = id;
    deleteOverlay.classList.add('visible');
}

function closeDeleteModal() {
    deleteOverlay.classList.remove('visible');
    deletingId = null;
}

async function loadAttachments(taskId) {
    attachList.innerHTML = '<p class="attach-empty">Loading…</p>';
    try {
        const atts = await fetch(`api/attachments.php?task_id=${taskId}`).then(r => r.json());
        renderAttachments(atts);
    } catch {
        attachList.innerHTML = '<p class="attach-empty" style="color:var(--danger)">Failed to load.</p>';
    }
}

function renderAttachments(atts) {
    if (!atts.length) {
        attachList.innerHTML = '<p class="attach-empty">No attachments yet.</p>';
        return;
    }
    attachList.innerHTML = atts.map(a => `
        <div class="attach-item" data-id="${a.id}">
            <span class="attach-icon">${fileIcon(a.mime_type)}</span>
            <div class="attach-info">
                <a href="download.php?id=${a.id}" class="attach-name" target="_blank">${escHtml(a.original_name)}</a>
                <span class="attach-size">${formatBytes(a.file_size)}</span>
            </div>
            <button class="icon-btn delete-attach-btn" data-id="${a.id}" title="Remove">🗑</button>
        </div>`).join('');

    attachList.querySelectorAll('.delete-attach-btn').forEach(btn => {
        btn.addEventListener('click', () => deleteAttachment(parseInt(btn.dataset.id)));
    });
}

async function uploadAttachments(files) {
    const ok = await ensureTaskSaved();
    if (!ok) return;

    for (const file of files) {
        const fd = new FormData();
        fd.append('task_id', editingId);
        fd.append('file', file);
        try {
            const res = await fetch('api/attachments.php', { method: 'POST', body: fd });
            if (!res.ok) {
                const err = await res.json();
                showToast(err.error || 'Upload failed', 'error');
            } else {
                showToast(`"${file.name}" uploaded!`, 'success');
            }
        } catch { showToast('Upload error', 'error'); }
    }
    loadAttachments(editingId);
}

async function deleteAttachment(id) {
    try {
        const res = await fetch('api/attachments.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        });
        if (!res.ok) throw new Error((await res.json()).error);
        showToast('Attachment removed.', 'success');
        loadAttachments(editingId);
    } catch (e) { showToast(e.message, 'error'); }
}

function showToast(msg, type = 'success') {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = msg;
    container.appendChild(toast);
    setTimeout(() => toast.classList.add('toast-show'), 10);
    setTimeout(() => {
        toast.classList.remove('toast-show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

/**
 * Escapes a value for safe insertion into HTML.
 * All task data from the server is passed through this before being set as
 * innerHTML — this prevents XSS if a task title contains <script> tags.
 */
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function formatTime(t) {
    if (!t) return '';
    const [h, m] = t.split(':');
    const hr = parseInt(h);
    return `${hr > 12 ? hr - 12 : hr || 12}:${m} ${hr >= 12 ? 'PM' : 'AM'}`;
}

function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function fileIcon(mime) {
    if (mime.startsWith('image/')) return '🖼';
    if (mime === 'application/pdf') return '📄';
    if (mime.includes('word')) return '📝';
    if (mime.includes('excel') || mime.includes('spreadsheet')) return '📊';
    if (mime === 'application/zip') return '🗜';
    return '📎';
}

document.getElementById('openCreateModal').addEventListener('click', openCreateModal);
document.getElementById('modalClose').addEventListener('click', closeModal);
document.getElementById('modalCancel').addEventListener('click', closeModal);
document.getElementById('saveTask').addEventListener('click', saveTask);
document.getElementById('deleteClose').addEventListener('click', closeDeleteModal);
document.getElementById('deleteCancelBtn').addEventListener('click', closeDeleteModal);
document.getElementById('confirmDelete').addEventListener('click', deleteTask);

modalOverlay.addEventListener('click', e => { if (e.target === modalOverlay) closeModal(); });
deleteOverlay.addEventListener('click', e => { if (e.target === deleteOverlay) closeDeleteModal(); });

document.querySelectorAll('.filter-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.filter-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        filterMode = btn.dataset.filter;
        renderTasks();
    });
});

taskList.addEventListener('click', e => {
    const editBtn   = e.target.closest('.edit-btn');
    const deleteBtn = e.target.closest('.delete-btn');
    if (editBtn)   openEditModal(parseInt(editBtn.dataset.id));
    if (deleteBtn) openDeleteModal(parseInt(deleteBtn.dataset.id));
});

document.getElementById('attachFileInput').addEventListener('change', function() {
    if (this.files.length) uploadAttachments([...this.files]);
    this.value = '';
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeModal(); closeDeleteModal(); }
    if (e.key === 'Enter' && e.ctrlKey && modalOverlay.classList.contains('visible')) saveTask();
});

loadTasks();

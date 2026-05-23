let tasks      = [];

let filterMode = 'all';

let editingId  = null;

let deletingId = null;

const taskList      = document.getElementById('taskList');

const modalOverlay  = document.getElementById('modalOverlay');

const modalTitle    = document.getElementById('modalTitle');

const deleteOverlay = document.getElementById('deleteOverlay');

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

// Wrapper around fetch — sets JSON headers, throws on non-2xx

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

    } catch (e) { alert(e.message); }

}

// Handles both create (editingId null) and edit (editingId set)

async function saveTask() {

    const title = fields.title.value.trim();

    if (!title) { fields.title.focus(); return; }

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

        } else {

            const created = await apiFetch('api/tasks.php', {

                method: 'POST',

                body: JSON.stringify(payload),

            });

            if (created.due_date === TODAY) tasks.unshift(created);

        }

        closeModal();

        renderTasks();

        updateStats();

    } catch (e) { alert(e.message); }

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

    } catch (e) { alert(e.message); }

}

function openCreateModal() {

    editingId = null;

    modalTitle.textContent = 'New Task';

    Object.values(fields).forEach(f => { if (f) f.value = ''; });

    fields.dueDate.value  = TODAY;

    fields.priority.value = 'medium';

    fields.status.value   = 'pending';

    modalOverlay.classList.add('visible');

    fields.title.focus();

}

function openEditModal(id) {

    const task = tasks.find(t => t.id === id);

    if (!task) return;

    editingId = id;

    modalTitle.textContent       = 'Edit Task';

    fields.title.value           = task.title;

    fields.description.value     = task.description || '';

    fields.notes.value           = task.notes || '';

    fields.dueDate.value         = task.due_date || '';

    fields.dueTime.value         = task.due_time || '';

    fields.priority.value        = task.priority;

    fields.status.value          = task.status;

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

function escHtml(s) {

    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

}

function formatTime(t) {

    if (!t) return '';

    const [h, m] = t.split(':');

    const hr = parseInt(h);

    return `${hr > 12 ? hr - 12 : hr || 12}:${m} ${hr >= 12 ? 'PM' : 'AM'}`;

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

document.addEventListener('keydown', e => {

    if (e.key === 'Escape') { closeModal(); closeDeleteModal(); }

    if (e.key === 'Enter' && e.ctrlKey && modalOverlay.classList.contains('visible')) saveTask();

});

loadTasks();

const TODAY       = new Date().toISOString().slice(0, 10);

let   currentView = 'weekly';

let   anchor      = new Date();

const DAYS   = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const MONTHS = ['January','February','March','April','May','June',

                'July','August','September','October','November','December'];

const calendarView    = document.getElementById('calendarView');

const rangeLabel      = document.getElementById('calendarRangeLabel');

const dayModalOverlay = document.getElementById('dayModalOverlay');

const dayModalTitle   = document.getElementById('dayModalTitle');

const dayModalBody    = document.getElementById('dayModalBody');

async function fetchTasksForMonth(year, month) {

    const ym  = `${year}-${String(month + 1).padStart(2, '0')}`;

    const res = await fetch(`api/tasks.php?month=${ym}`);

    return res.ok ? res.json() : [];

}

async function render() {

    calendarView.innerHTML = '<div class="loading-spinner">Loading…</div>';

    if (currentView === 'weekly') await renderWeekly();

    else await renderMonthly();

}

// Builds the 7-day grid starting from the Monday of the current anchor week.

// May fetch two months if the week spans a month boundary.

async function renderWeekly() {

    const monday = new Date(anchor);

    monday.setDate(monday.getDate() - ((monday.getDay() + 6) % 7));

    const dates = Array.from({ length: 7 }, (_, i) => {

        const d = new Date(monday);

        d.setDate(monday.getDate() + i);

        return d;

    });

    const months = [...new Set(dates.map(d => `${d.getFullYear()}-${d.getMonth()}`))];

    const allTasks = (await Promise.all(

        months.map(key => {

            const [y, m] = key.split('-');

            return fetchTasksForMonth(parseInt(y), parseInt(m));

        })

    )).flat();

    const tasksByDate = groupByDate(allTasks);

    rangeLabel.textContent = `${formatFullDate(dates[0])} – ${formatFullDate(dates[6])}`;

    let html = '<div class="weekly-grid">';

    dates.forEach(d => {

        const ds       = fmtDate(d);

        const isToday  = ds === TODAY;

        const dayTasks = tasksByDate[ds] || [];

        html += `

        <div class="week-day ${isToday ? 'week-day-today' : ''}">

            <div class="week-day-header">

                <span class="week-day-name">${DAYS[d.getDay()]}</span>

                <span class="week-day-num ${isToday ? 'today-badge' : ''}">${d.getDate()}</span>

            </div>

            <div class="week-day-tasks">

                ${dayTasks.length === 0

                    ? '<div class="no-tasks-week">No tasks</div>'

                    : dayTasks.map(t => miniTaskCard(t)).join('')}

            </div>

        </div>`;

    });

    html += '</div>';

    calendarView.innerHTML = html;

}

async function renderMonthly() {

    const year  = anchor.getFullYear();

    const month = anchor.getMonth();

    const tasks       = await fetchTasksForMonth(year, month);

    const tasksByDate = groupByDate(tasks);

    rangeLabel.textContent = `${MONTHS[month]} ${year}`;

    const firstDay  = new Date(year, month, 1).getDay();

    const daysCount = new Date(year, month + 1, 0).getDate();

    let html = `

    <div class="monthly-grid">

        <div class="month-header-row">

            ${DAYS.map(d => `<div class="month-col-label">${d}</div>`).join('')}

        </div>

        <div class="month-body">`;

    for (let i = 0; i < firstDay; i++) html += '<div class="month-cell month-cell-empty"></div>';

    for (let day = 1; day <= daysCount; day++) {

        const ds        = `${year}-${String(month + 1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;

        const isToday   = ds === TODAY;

        const dayTasks  = tasksByDate[ds] || [];

        const completed = dayTasks.filter(t => t.status === 'completed').length;

        html += `

        <div class="month-cell ${isToday ? 'month-cell-today' : ''}" data-date="${ds}">

            <div class="month-cell-num ${isToday ? 'today-badge' : ''}">${day}</div>

            <div class="month-task-dots">

                ${dayTasks.slice(0, 3).map(t => `<span class="task-dot priority-dot-${t.priority}" title="${escHtml(t.title)}"></span>`).join('')}

                ${dayTasks.length > 3 ? `<span class="task-dot-more">+${dayTasks.length - 3}</span>` : ''}

            </div>

            ${dayTasks.length > 0 ? `<div class="month-cell-count">${completed}/${dayTasks.length}</div>` : ''}

        </div>`;

    }

    html += '</div></div>';

    calendarView.innerHTML = html;

    calendarView.querySelectorAll('.month-cell[data-date]').forEach(cell => {

        cell.addEventListener('click', () => showDayModal(cell.dataset.date, tasksByDate[cell.dataset.date] || []));

    });

}

function showDayModal(date, dayTasks) {

    const d = new Date(date + 'T00:00:00');

    dayModalTitle.textContent = formatFullDate(d);

    if (dayTasks.length === 0) {

        dayModalBody.innerHTML = '<div class="empty-state"><div class="empty-icon">📋</div><p>No tasks on this day.</p></div>';

    } else {

        dayModalBody.innerHTML = dayTasks.map(t => `

            <div class="task-card ${t.status === 'completed' ? 'task-done' : ''}">

                <div class="task-body">

                    <div class="task-title">${escHtml(t.title)}</div>

                    ${t.description ? `<div class="task-desc">${escHtml(t.description)}</div>` : ''}

                    <div class="task-meta">

                        <span class="priority-badge priority-${t.priority}">${t.priority}</span>

                        ${t.due_time ? `<span class="task-time">⏱ ${t.due_time}</span>` : ''}

                        <span class="status-badge status-${t.status}">${t.status}</span>

                    </div>

                    ${t.notes ? `<div class="task-notes">${escHtml(t.notes)}</div>` : ''}

                </div>

            </div>`).join('');

    }

    dayModalOverlay.classList.add('visible');

}

function miniTaskCard(t) {

    return `<div class="mini-task priority-border-${t.priority} ${t.status === 'completed' ? 'task-done' : ''}">

        <span class="mini-task-title">${escHtml(t.title)}</span>

        ${t.due_time ? `<span class="mini-task-time">${t.due_time}</span>` : ''}

    </div>`;

}

// Groups a flat task array into an object keyed by due_date

function groupByDate(tasks) {

    return tasks.reduce((acc, t) => {

        if (!t.due_date) return acc;

        (acc[t.due_date] = acc[t.due_date] || []).push(t);

        return acc;

    }, {});

}

function fmtDate(d) {

    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;

}

function formatFullDate(d) {

    return `${DAYS[d.getDay()]}, ${MONTHS[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}`;

}

function escHtml(s) {

    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

}

function navigate(direction) {

    if (currentView === 'weekly') anchor.setDate(anchor.getDate() + direction * 7);

    else { anchor.setDate(1); anchor.setMonth(anchor.getMonth() + direction); }

    render();

}

document.getElementById('prevBtn').addEventListener('click', () => navigate(-1));

document.getElementById('nextBtn').addEventListener('click', () => navigate(1));

document.getElementById('todayBtn').addEventListener('click', () => { anchor = new Date(); render(); });

document.getElementById('btnWeekly').addEventListener('click', () => {

    currentView = 'weekly';

    document.getElementById('btnWeekly').classList.add('active');

    document.getElementById('btnMonthly').classList.remove('active');

    render();

});

document.getElementById('btnMonthly').addEventListener('click', () => {

    currentView = 'monthly';

    document.getElementById('btnMonthly').classList.add('active');

    document.getElementById('btnWeekly').classList.remove('active');

    render();

});

document.getElementById('dayModalClose').addEventListener('click', () => {

    dayModalOverlay.classList.remove('visible');

});

dayModalOverlay.addEventListener('click', e => {

    if (e.target === dayModalOverlay) dayModalOverlay.classList.remove('visible');

});

document.addEventListener('keydown', e => {

    if (e.key === 'Escape')     dayModalOverlay.classList.remove('visible');

    if (e.key === 'ArrowLeft')  navigate(-1);

    if (e.key === 'ArrowRight') navigate(1);

});

render();

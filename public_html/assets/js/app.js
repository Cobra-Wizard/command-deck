// ============================================================
// Command Deck
//
// Single-file dashboard logic. All API calls go through
// api.php which handles data, status checks, and scanning.
// ============================================================

const API = 'api.php';

// --- State ---
let resources  = [];
let statuses   = {};
let editingId  = null;

// --- Boot ---
document.addEventListener('DOMContentLoaded', () => {
    setupTabs();
    setupSearch();
    loadResources();
});


// =============================================================
//  API helpers
// =============================================================

async function apiGet(action, params) {
    const url = new URL(API, location.href);
    url.searchParams.set('action', action);
    if (params) {
        for (const [k, v] of Object.entries(params)) {
            url.searchParams.set(k, v);
        }
    }
    const res = await fetch(url);
    if (res.status === 401) { location.reload(); return null; }
    return res.json();
}

async function apiPost(action, body, params) {
    const url = new URL(API, location.href);
    url.searchParams.set('action', action);
    if (params) {
        for (const [k, v] of Object.entries(params)) {
            url.searchParams.set(k, v);
        }
    }
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body || {})
    });
    if (res.status === 401) { location.reload(); return null; }
    return res.json();
}


// =============================================================
//  Load resources + render
// =============================================================

async function loadResources() {
    const resp = await apiGet('list');
    if (!resp) return;
    resources = resp.data || [];
    renderDashboard();
    checkStatuses();   // fires automatically on load
}

function renderDashboard() {
    const grid  = document.getElementById('grid');
    const empty = document.getElementById('empty');
    const query = document.getElementById('search').value.toLowerCase();
    const cat   = document.getElementById('category-filter').value;

    let list = resources;

    if (query) {
        list = list.filter(r =>
            r.name.toLowerCase().includes(query) ||
            (r.description || '').toLowerCase().includes(query) ||
            (r.url || '').toLowerCase().includes(query) ||
            (r.tags || []).some(t => t.toLowerCase().includes(query))
        );
    }
    if (cat) {
        list = list.filter(r => r.category === cat);
    }

    // Sort by category then name
    list.sort((a, b) => {
        const c = a.category.localeCompare(b.category);
        return c !== 0 ? c : a.name.localeCompare(b.name);
    });

    if (list.length === 0) {
        grid.innerHTML = '';
        empty.style.display = 'block';
        return;
    }

    empty.style.display = 'none';
    grid.innerHTML = list.map(buildCard).join('');
}

function buildCard(r) {
    const st       = statuses[r.id] || 'checking';
    const dotClass = st === 'online' ? 'online'
                   : st === 'offline' ? 'offline'
                   : 'checking';
    const initial  = (r.name || '?').charAt(0).toUpperCase();
    const tags     = (r.tags || []).map(t =>
        '<span class="badge tag">' + esc(t) + '</span>'
    ).join('');
    const shortUrl = (r.url || '').replace(/^https?:\/\//, '');
    const safeUrl  = /^(https?|smb|ftp):\/\//i.test(r.url || '') ? r.url : '#';

    return '<div class="card">' +
        '<div class="card-top">' +
            '<span class="dot ' + dotClass + '" title="' + esc(st) + '"></span>' +
            '<span class="card-initial" style="background:' + esc(r.color || '#3b82f6') + '">' + initial + '</span>' +
            '<div class="card-info">' +
                '<div class="card-name">' + esc(r.name) + '</div>' +
                '<div class="card-desc">' + esc(r.description || '') + '</div>' +
            '</div>' +
        '</div>' +
        '<div class="card-url"><a href="' + esc(safeUrl) + '" target="_blank" rel="noopener">' + esc(shortUrl) + '</a></div>' +
        '<div class="card-meta">' +
            '<span class="badge cat">' + esc(r.category) + '</span>' +
            tags +
        '</div>' +
        '<div class="card-actions">' +
            '<button class="btn-sm" onclick="openEditModal(' + r.id + ')">Edit</button>' +
            '<button class="btn-sm btn-danger" onclick="handleDelete(' + r.id + ')">Del</button>' +
            '<a class="btn-sm btn-open" href="' + esc(safeUrl) + '" target="_blank" rel="noopener">Open &rarr;</a>' +
        '</div>' +
    '</div>';
}


// =============================================================
//  Status checks — server-side via api.php (no CORS issues)
// =============================================================

async function checkStatuses() {
    const info = document.getElementById('status-info');
    info.textContent = 'Checking\u2026';

    const resp = await apiGet('check_all');
    if (!resp || !resp.data) {
        info.textContent = 'Status check failed';
        return;
    }

    statuses = {};
    for (const [id, st] of Object.entries(resp.data)) {
        statuses[parseInt(id)] = st;
    }

    const online = Object.values(statuses).filter(s => s === 'online').length;
    const total  = Object.values(statuses).length;
    const time   = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    info.textContent = online + '/' + total + ' online \u00b7 ' + time;

    renderDashboard();
}


// =============================================================
//  Add / Edit modal
// =============================================================

function openAddModal(prefill) {
    const p = prefill || {};
    editingId = null;
    document.getElementById('modal-title').textContent = 'Add Resource';
    document.getElementById('f-id').value    = '';
    document.getElementById('f-name').value  = p.name || '';
    document.getElementById('f-url').value   = p.url || '';
    document.getElementById('f-category').value = p.category || 'Other';
    document.getElementById('f-tags').value  = p.tags || '';
    document.getElementById('f-desc').value  = p.description || '';
    document.getElementById('f-color').value = p.color || '#3b82f6';
    document.getElementById('modal').style.display = 'flex';
    document.getElementById('f-name').focus();
}

function openEditModal(id) {
    const r = resources.find(x => x.id === id);
    if (!r) return;
    editingId = id;
    document.getElementById('modal-title').textContent = 'Edit Resource';
    document.getElementById('f-id').value    = id;
    document.getElementById('f-name').value  = r.name;
    document.getElementById('f-url').value   = r.url;
    document.getElementById('f-category').value = r.category;
    document.getElementById('f-tags').value  = (r.tags || []).join(', ');
    document.getElementById('f-desc').value  = r.description || '';
    document.getElementById('f-color').value = r.color || '#3b82f6';
    document.getElementById('modal').style.display = 'flex';
    document.getElementById('f-name').focus();
}

function closeModal() {
    document.getElementById('modal').style.display = 'none';
    editingId = null;
}

async function handleSave(e) {
    e.preventDefault();
    const data = {
        name:        document.getElementById('f-name').value.trim(),
        url:         document.getElementById('f-url').value.trim(),
        category:    document.getElementById('f-category').value,
        tags:        document.getElementById('f-tags').value,
        description: document.getElementById('f-desc').value.trim(),
        color:       document.getElementById('f-color').value,
    };

    if (!data.name || !data.url) {
        alert('Name and URL are required.');
        return;
    }

    let resp;
    if (editingId !== null) {
        resp = await apiPost('update', data, { id: editingId });
    } else {
        resp = await apiPost('add', data);
    }

    if (resp && resp.status === 'ok') {
        closeModal();
        await loadResources();
    } else {
        alert('Save failed: ' + (resp ? resp.message : 'Unknown error'));
    }
}

async function handleDelete(id) {
    const r = resources.find(x => x.id === id);
    if (!confirm('Delete "' + (r ? r.name : '') + '"?')) return;

    const resp = await apiPost('delete', {}, { id: id });
    if (resp && resp.status === 'ok') {
        await loadResources();
    }
}


// =============================================================
//  Network scanner
// =============================================================

async function startScan() {
    const btn     = document.getElementById('scan-btn');
    const status  = document.getElementById('scan-status');
    const results = document.getElementById('scan-results');
    const subnet  = document.getElementById('scan-subnet').value.trim();

    if (!/^\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(subnet)) {
        alert('Enter a valid subnet prefix, e.g. 10.0.0');
        return;
    }

    btn.disabled = true;
    status.textContent = 'Scanning ' + subnet + '.1\u2013254 \u2026 this may take a moment.';
    results.innerHTML = '<div class="scan-loading">Scanning network\u2026</div>';

    const resp = await apiGet('scan', { subnet: subnet });
    btn.disabled = false;

    if (!resp || !resp.data) {
        status.textContent = 'Scan failed.';
        results.innerHTML = '';
        return;
    }

    status.textContent = 'Found ' + resp.data.length + ' device(s)';

    if (resp.data.length === 0) {
        results.innerHTML = '<div class="scan-loading">No devices found on ' + esc(subnet) + '.x</div>';
        return;
    }

    let html = '<table class="scan-table"><thead><tr>' +
        '<th>IP Address</th><th>Open Ports</th><th>Services</th><th></th>' +
        '</tr></thead><tbody>';

    for (const d of resp.data) {
        const ports = d.ports.join(', ');
        const svcs  = (d.services || []).join(', ');
        let action;
        if (d.known) {
            action = '<span class="scan-known">Already tracked</span>';
        } else {
            const url = buildScanUrl(d.ip, d.ports);
            action = '<button class="btn-sm btn-primary" ' +
                'onclick="addFromScan(\'' + esc(d.ip) + '\',\'' + esc(url) + '\')">+ Add</button>';
        }
        html += '<tr><td>' + esc(d.ip) + '</td><td>' + esc(ports) + '</td>' +
                '<td>' + esc(svcs) + '</td><td>' + action + '</td></tr>';
    }

    html += '</tbody></table>';
    results.innerHTML = html;
}

/** Pick the most useful port and build a URL for the Add form */
function buildScanUrl(ip, ports) {
    // Prefer common web ports in priority order
    const prio = [443, 8443, 80, 8080, 8006, 8083, 8123, 8188, 8008, 32400, 3000, 5000, 8000, 9090];
    let best = null;
    for (const p of prio) {
        if (ports.includes(p)) { best = p; break; }
    }
    if (!best) best = ports[0];

    const proto = [443, 8443].includes(best) ? 'https' : 'http';
    if (best === 80)  return 'http://' + ip;
    if (best === 443) return 'https://' + ip;
    return proto + '://' + ip + ':' + best;
}

function addFromScan(ip, url) {
    openAddModal({ url: url, description: 'Discovered at ' + ip });
}


// =============================================================
//  Tabs & search wiring
// =============================================================

function setupTabs() {
    document.querySelectorAll('.tab').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(s => s.style.display = 'none');
            btn.classList.add('active');
            const section = document.getElementById('tab-' + btn.dataset.tab);
            if (section) section.style.display = 'block';
        });
    });
}

function setupSearch() {
    let timer;
    document.getElementById('search').addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(renderDashboard, 200);
    });
    document.getElementById('category-filter').addEventListener('change', renderDashboard);
}


// =============================================================
//  Utility
// =============================================================

/** Escape HTML to prevent XSS */
function esc(str) {
    if (str == null) return '';
    const d = document.createElement('div');
    d.textContent = String(str);
    return d.innerHTML;
}

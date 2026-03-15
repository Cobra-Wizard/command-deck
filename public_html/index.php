<?php
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/config.php';
$assetVersion = '20260315e';

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /');
    exit;
}

// Login attempt
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === AUTH_PASSWORD) {
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();
        header('Location: /');
        exit;
    }
    $error = 'Wrong password.';
}

// Session timeout
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_TIMEOUT) {
    session_destroy();
    header('Location: /');
    exit;
}

$loggedIn = !empty($_SESSION['authenticated']);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title><?= htmlspecialchars(SITE_TITLE) ?></title>
    <link rel="stylesheet" href="assets/css/styles.css?v=<?= urlencode($assetVersion) ?>">
</head>
<body>
<?php if (!$loggedIn): ?>

<div class="login-wrap">
    <form method="post" class="login-box">
        <div class="login-brand">
            <div>
                <h1><?= htmlspecialchars(SITE_TITLE) ?></h1>
                <p><?= htmlspecialchars(SITE_SUBTITLE) ?></p>
            </div>
        </div>
        <label class="login-label" for="login-password">Password</label>
        <input id="login-password" type="password" name="password" placeholder="Enter password" autofocus required>
        <button type="submit">Unlock Dashboard</button>
        <div class="login-hint">Session expires automatically after inactivity.</div>
        <?php if ($error): ?>
            <div class="login-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
    </form>
</div>

<?php else: ?>

<header>
    <div class="header-left">
        <h1><?= htmlspecialchars(SITE_TITLE) ?></h1>
    </div>
    <div class="header-right">
        <span id="status-info"></span>
        <a href="?logout" class="btn btn-sm">Logout</a>
    </div>
</header>

<nav class="tabs">
    <button class="tab active" data-tab="dashboard">Dashboard</button>
    <button class="tab" data-tab="scanner">Network Scanner</button>
</nav>

<!-- Dashboard Tab -->
<section id="tab-dashboard" class="tab-content active">
    <div class="toolbar">
        <input type="text" id="search" placeholder="Search by name, tag, or URL...">
        <select id="category-filter">
            <option value="">All Categories</option>
            <?php foreach (CATEGORIES as $cat): ?>
                <option value="<?= $cat ?>"><?= $cat ?></option>
            <?php endforeach; ?>
        </select>
        <div class="view-toggle">
            <button class="view-btn active" data-view="grid" title="Table view">&#9776;</button>
            <button class="view-btn" data-view="cards" title="Card view">&#9638;</button>
        </div>
        <button class="btn btn-primary" onclick="openAddModal()">+ Add</button>
    </div>
    <div id="grid" class="grid"></div>
    <div id="empty" class="empty" style="display:none">No resources match your filter.</div>
</section>

<!-- Scanner Tab -->
<section id="tab-scanner" class="tab-content" style="display:none">
    <div class="toolbar">
        <label>Subnet:
            <input type="text" id="scan-subnet" value="<?= htmlspecialchars(DEFAULT_SUBNET) ?>" size="12">
        </label>
        <button class="btn btn-primary" id="scan-btn" onclick="startScan()">Scan Network</button>
        <span id="scan-status"></span>
    </div>
    <div id="scan-results" class="scan-wrap"></div>
</section>

<!-- Add / Edit Modal -->
<div id="modal" class="modal" style="display:none">
    <div class="modal-overlay" onclick="closeModal()"></div>
    <div class="modal-box">
        <div class="modal-header">
            <h2 id="modal-title">Add Resource</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="resource-form" onsubmit="handleSave(event)">
            <input type="hidden" name="id" id="f-id">
            <label>Name *
                <input type="text" name="name" id="f-name" required>
            </label>
            <label>URL *
                <input type="text" name="url" id="f-url" required placeholder="http://10.0.0.x:port">
            </label>
            <label>Category *
                <select name="category" id="f-category">
                    <?php foreach (CATEGORIES as $cat): ?>
                        <option value="<?= $cat ?>"><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Tags
                <input type="text" name="tags" id="f-tags" placeholder="comma-separated tags">
            </label>
            <label>Description
                <input type="text" name="description" id="f-desc">
            </label>
            <label>Color
                <input type="color" name="color" id="f-color" value="#3b82f6">
            </label>
            <label>Runs on
                <select name="parent_id" id="f-parent">
                    <option value="">(None — top level)</option>
                </select>
            </label>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" class="btn" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/app.js?v=<?= urlencode($assetVersion) ?>"></script>
<?php endif; ?>
</body>
</html>

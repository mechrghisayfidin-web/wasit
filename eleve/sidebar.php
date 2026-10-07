<?php
$current = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-logo">Wassit</div>
    <nav>
        <a href="dashboard.php" class="<?= $current === 'dashboard.php' ? 'active' : '' ?>">
            📊 لوحتي
        </a>
        <a href="notes.php" class="<?= $current === 'notes.php' ? 'active' : '' ?>">
            📈 أعدادي ومعدلاتي
        </a>
        <a href="devoirs.php" class="<?= $current === 'devoirs.php' ? 'active' : '' ?>">
            📝 الواجبات
        </a> 
        <a href="fichiers.php" class="<?= $current === 'fichiers.php' ? 'active' : '' ?>">
            📁 الملفات
        </a>
        <a href="chat.php" class="<?= $current === 'chat.php' ? 'active' : '' ?>">
        💬 دردشة القسم
        </a>
        <a href="../auth/logout.php" class="logout">🚪 خروج</a>
    </nav>
</div>

<style>
.sidebar {
    width: 220px;
    min-height: 100vh;
    background: #1a73e8;
    position: fixed;
    right: 0;
    top: 0;
    display: flex;
    flex-direction: column;
    padding: 24px 0;
}
.sidebar-logo {
    color: white;
    font-size: 24px;
    font-weight: bold;
    text-align: center;
    margin-bottom: 32px;
    letter-spacing: 1px;
}
.sidebar nav {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 0 12px;
}
.sidebar nav a {
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 15px;
    transition: all 0.2s;
}
.sidebar nav a:hover {
    background: rgba(255,255,255,0.15);
    color: white;
}
.sidebar nav a.active {
    background: rgba(255,255,255,0.25);
    color: white;
    font-weight: bold;
}
.sidebar nav a.logout {
    margin-top: auto;
    color: rgba(255,255,255,0.6);
}
.main-content {
    margin-right: 220px;
    padding: 30px;
    min-height: 100vh;
    background: #f0f4f8;
}
</style>
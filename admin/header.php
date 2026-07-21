<?php
// $pageTitle 繧貞他縺ｳ蜃ｺ縺怜・縺ｧ繧ｻ繝・ヨ縺励※縺九ｉ include 縺吶ｋ
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle ?? '管理画面') ?> | 戦国経済圏</title>
<style>
:root {
    --gold:       #c9a84c;
    --gold-lt:    #e8c96e;
    --gold-dk:    #8B6914;
    --ink:        #1a1410;
    --smoke:      #221e1a;
    --sidebar:    #1e1a16;
    --paper:      #f5f0e8;
    --red:        #8b1a1a;
    --green:      #2d6a4f;
    --border:     rgba(201,168,76,.18);
    --text-muted: rgba(245,240,232,.5);
    --active-bg:  rgba(201,168,76,.12);
    /* 蜍慕噪繝・・繝槫､画焚 */
    --bg:         #221e1a;
    --surface:    #1a1410;
    --text:       #f5f0e8;
    --text-sub:   rgba(245,240,232,.65);
    --bd:         rgba(201,168,76,.18);
}


*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { width: 100%; overflow-x: hidden; }
body {
    font-family: 'Noto Sans JP', 'Hiragino Sans', sans-serif;
    background: var(--bg);
    color: var(--paper);
    min-height: 100vh;
    display: flex;
    width: 100%;
    overflow-x: hidden;
}
/* 繧ｵ繧､繝峨ヰ繝ｼ */
.sidebar {
    width: 240px;
    height: 100vh;
    height: 100dvh;
    max-height: 100dvh;
    background: var(--sidebar); color: var(--text);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 100;
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
}
.sidebar-logo {
    padding: 1.5rem 1.25rem;
    border-bottom: 1px solid var(--border);
    font-family: 'Noto Serif JP', serif;
    font-size: .85rem;
    font-weight: 700;
    color: var(--gold);
    letter-spacing: .1em;
}
.sidebar-logo span { display: block; font-size: 1.1rem; }
nav a {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .85rem 1.25rem;
    color: rgba(245,240,232,.6);
    text-decoration: none;
    font-size: .9rem;
    transition: background .15s, color .15s;
    border-left: 3px solid transparent;
}
nav a:hover { background: rgba(201,168,76,.08); color: var(--paper); }
nav a.active { background: rgba(201,168,76,.12); color: var(--gold); border-left-color: var(--gold); }
nav .nav-icon { width: 18px; text-align: center; }
.sidebar-footer {
    flex-shrink: 0;
    margin-top: auto;
    padding: 1rem 1.25rem;
    border-top: 1px solid var(--border);
}
.sidebar-footer a { font-size: .8rem; color: var(--text-muted); text-decoration: none; }
.sidebar-footer a:hover { color: var(--paper); }
/* 繝｡繧､繝ｳ繧ｳ繝ｳ繝・Φ繝・*/
.main {
    margin-left: 240px;
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.topbar {
    background: var(--sidebar);
    border-bottom: 1px solid var(--border);
    padding: 1rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 50;
    min-width: 0;
}
.topbar h1 { font-size: 1.1rem; font-weight: 700; color: var(--paper); min-width: 0; overflow-wrap: anywhere; }
.topbar-user { font-size: .85rem; color: var(--text-muted); }
.content { padding: 2rem; flex: 1; min-width: 0; max-width: 100%; }
/* 繧ｫ繝ｼ繝・*/
.card {
    background: rgba(255,255,255,.04);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    max-width: 100%;
    overflow-wrap: anywhere;
}
.card-title {
    font-size: .8rem;
    letter-spacing: .1em;
    color: var(--gold);
    text-transform: uppercase;
    margin-bottom: 1.25rem;
    font-weight: 700;
}
/* 繝・・繝悶Ν */
table { width: 100%; border-collapse: collapse; }
th {
    text-align: left;
    padding: .75rem 1rem;
    font-size: .78rem;
    color: var(--text-muted);
    letter-spacing: .08em;
    border-bottom: 1px solid var(--border);
    font-weight: 600;
    white-space: nowrap;
}
td {
    padding: .85rem 1rem;
    font-size: .88rem;
    border-bottom: 1px solid rgba(201,168,76,.07);
    vertical-align: middle;
}
tr:hover td { background: rgba(201,168,76,.04); }
tr:last-child td { border-bottom: none; }
/* 繝舌ャ繧ｸ */
.badge {
    display: inline-block;
    padding: .2rem .6rem;
    border-radius: 2rem;
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .05em;
}
.badge-active  { background: rgba(45,106,79,.3);  color: #5ecb9b; border: 1px solid rgba(94,203,155,.3); }
.badge-inactive{ background: rgba(139,26,26,.2);  color: #e08080; border: 1px solid rgba(224,128,128,.3); }
.badge-new     { background: rgba(201,168,76,.15); color: var(--gold); border: 1px solid rgba(201,168,76,.3); }
.badge-contacted{ background: rgba(50,100,200,.2); color: #88aaee; border: 1px solid rgba(136,170,238,.3); }
.badge-closed  { background: rgba(80,80,80,.3);   color: #aaa; border: 1px solid rgba(170,170,170,.2); }
/* 繝懊ち繝ｳ */
.btn {
    display: inline-block;
    padding: .45rem 1rem;
    border-radius: 4px;
    font-size: .82rem;
    font-weight: 700;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: opacity .15s;
    font-family: inherit;
}
.btn:hover { opacity: .85; }
.btn-gold { background: var(--gold); color: var(--ink); }
.btn-sm   { padding: .3rem .7rem; font-size: .78rem; }
.btn-outline { background: transparent; border: 1px solid var(--border); color: var(--paper); }
.btn-danger { background: rgba(139,26,26,.6); color: #faa; border: 1px solid var(--red); }
/* 繝輔か繝ｼ繝 */
.form-group { margin-bottom: 1.25rem; }
.form-group label { display: block; font-size: .82rem; color: var(--gold); margin-bottom: .35rem; letter-spacing: .05em; }
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: .65rem .9rem;
    background: rgba(255,255,255,.06);
    border: 1px solid var(--border);
    border-radius: 4px;
    color: var(--paper);
    font-family: inherit;
    font-size: .9rem;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus { outline: none; border-color: var(--gold); }
.form-group select option,
select option {
    background: #fff !important;
    color: #1a1410 !important;
}
.form-group textarea { resize: vertical; min-height: 100px; }
.form-check { display: flex; align-items: center; gap: .5rem; margin-bottom: .5rem; font-size: .9rem; cursor: pointer; }
.form-check input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--gold); cursor: pointer; }
/* 繧｢繝ｩ繝ｼ繝・*/
.alert { padding: .75rem 1rem; border-radius: 4px; margin-bottom: 1rem; font-size: .88rem; }
.alert-success { background: rgba(45,106,79,.2); border: 1px solid rgba(94,203,155,.3); color: #5ecb9b; }
.alert-error   { background: rgba(139,26,26,.15); border: 1px solid rgba(224,128,128,.3); color: #e08080; }
/* 繧ｹ繧ｿ繝・ヨ */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.stat-card { background: rgba(255,255,255,.04); border: 1px solid var(--border); border-radius: 6px; padding: 1.25rem; }
.stat-label { font-size: .75rem; color: var(--text-muted); letter-spacing: .08em; margin-bottom: .5rem; }
.stat-val { font-size: 2rem; font-weight: 700; color: var(--gold); font-family: 'Noto Serif JP', serif; }
.stat-sub  { font-size: .75rem; color: var(--text-muted); margin-top: .25rem; }
/* 繝壹・繧ｸ繝阪・繧ｷ繝ｧ繝ｳ */
.pagination { display: flex; gap: .4rem; margin-top: 1rem; }
.pagination a, .pagination span {
    padding: .4rem .75rem;
    border-radius: 3px;
    font-size: .82rem;
    border: 1px solid var(--border);
    color: var(--paper);
    text-decoration: none;
}
.pagination a:hover { border-color: var(--gold); color: var(--gold); }
.pagination .current { background: var(--gold); color: var(--ink); border-color: var(--gold); font-weight: 700; }
/* 繝ｬ繧ｹ繝昴Φ繧ｷ繝・*/
@media (max-width: 900px) {
    .sidebar { display: none; }
    .main { margin-left: 0; }
}

/* ===== 繝｢繝舌う繝ｫ蟇ｾ蠢懶ｼ遺捉・・===== */
@media (max-width: 900px) {
    .sidebar {
        display: flex !important;
        position: fixed;
        left: -240px;
        top: 0; bottom: 0;
        z-index: 1000;
        transition: left .25s ease;
        width: 240px;
        max-width: 82vw;
        height: 100vh;
        height: 100dvh;
        max-height: 100dvh;
        overflow-y: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
    }
    .sidebar.open { left: 0; box-shadow: 4px 0 20px rgba(0,0,0,.5); }
    .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.5);
        z-index: 999;
    }
    .sidebar-overlay.open { display: block; }
    .main-wrap { margin-left: 0 !important; }
    .topbar { padding: .7rem .85rem; gap: .6rem; align-items: flex-start; }
    .topbar > div { min-width: 0; }
    .topbar h1 { font-size: 1rem; line-height: 1.35; }
    .topbar-user { display: none; }
    .content { padding: 1rem; }
    .menu-btn {
        display: flex !important;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        background: none;
        border: 1px solid var(--border);
        border-radius: 4px;
        color: var(--paper);
        font-size: 1.1rem;
        cursor: pointer;
        flex-shrink: 0;
    }
    .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
    table { font-size: .78rem; }
    th, td { padding: .5rem .6rem; }
    .card { padding: 1rem; border-radius: 5px; }
    .btn { white-space: normal; text-align: center; justify-content: center; }
    .pagination { flex-wrap: wrap; }
    .content [style*="display:grid"] {
        grid-template-columns: 1fr !important;
    }
    .content form[style*="display:grid"] {
        grid-template-columns: 1fr !important;
    }
    .content [style*="grid-column"] {
        grid-column: auto !important;
    }
    .content [style*="display:flex"] {
        flex-wrap: wrap !important;
    }
    .content form[style*="display:flex"],
    .content form[style*="display:inline-flex"] {
        width: 100%;
    }
    .content input,
    .content select,
    .content textarea {
        max-width: 100%;
    }
    .content code,
    .content a {
        overflow-wrap: anywhere;
        word-break: break-word;
    }
    /* 繝・・繝悶Ν繧呈ｨｪ繧ｹ繧ｯ繝ｭ繝ｼ繝ｫ */
    .card { overflow-x: auto; }
    .card table { min-width: 720px; }
    .card > table,
    .card table {
        display: table;
        table-layout: auto;
    }
    .card table textarea {
        width: 100%;
        min-width: 160px;
        min-height: 54px;
        resize: vertical;
    }
    .card table input[type="date"],
    .card table input[type="text"],
    .card table select {
        min-width: 120px;
    }
    .card table form {
        min-width: max-content;
    }
    .card table .btn,
    .card table select,
    .card table input {
        font-size: .75rem !important;
    }
}
@media (max-width: 520px) {
    .content { padding: .75rem; }
    .stats-grid { grid-template-columns: 1fr !important; }
    .card { padding: .85rem; margin-bottom: 1rem; }
    .card table { min-width: 640px; }
    .btn { width: 100%; }
table .btn { width: auto; }
    .topbar { position: sticky; top: 0; z-index: 900; }
}
@media (min-width: 901px) {
    .menu-btn { display: none !important; }
}

.table-scroll {
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    overflow-y: visible;
    -webkit-overflow-scrolling: touch;
}
.table-scroll table {
    width: max-content;
    min-width: 100%;
}
.table-scroll::-webkit-scrollbar {
    height: 10px;
}
.table-scroll::-webkit-scrollbar-track {
    background: rgba(255,255,255,.05);
}
.table-scroll::-webkit-scrollbar-thumb {
    background: rgba(201,168,76,.45);
    border-radius: 999px;
}
.admin-agents-table {
    min-width: 1480px;
}
.admin-agents-table th,
.admin-agents-table td {
    overflow-wrap: normal;
    word-break: normal;
}
.admin-agents-table .agent-actions {
    min-width: 590px;
}
.admin-agents-table .agent-actions form {
    white-space: nowrap;
}
.admin-agents-table .agent-actions select,
.admin-agents-table .agent-actions .btn {
    vertical-align: middle;
}
.password-toggle-wrap {
    position: relative;
    display: inline-block;
    width: 100%;
    max-width: 100%;
}
.password-toggle-wrap > input[type="password"],
.password-toggle-wrap > input[type="text"] {
    padding-right: 3rem !important;
}
.password-toggle-btn {
    position: absolute;
    right: .45rem;
    top: 50%;
    transform: translateY(-50%);
    border: 1px solid var(--border);
    background: rgba(201,168,76,.12);
    color: var(--gold);
    border-radius: 3px;
    width: 2.15rem;
    height: 2.15rem;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    line-height: 1;
}
.password-toggle-btn svg {
    width: 17px;
    height: 17px;
    stroke: currentColor;
    fill: none;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}
body[data-theme="light"] .password-toggle-btn {
    background: rgba(201,168,76,.16);
    color: #8B6914 !important;
}


/* ===== 繝ｩ繧､繝医Δ繝ｼ繝・===== */
body[data-theme="light"] {
    background: #f8f5ef !important;
    color: #2a1f14 !important;
    /* 繧､繝ｳ繝ｩ繧､繝ｳ繧ｹ繧ｿ繧､繝ｫ縺ｮcolor繧貞・縺ｦ荳頑嶌縺・*/
    --paper:      #2a1f14;
    --text-muted: rgba(42,31,20,.5);
    --border:     rgba(139,105,20,.22);
}
/* 蜈ｨ繝・く繧ｹ繝郁ｦ∫ｴ繧貞ｼｷ蛻ｶ荳頑嶌縺・*/
body[data-theme="light"] *:not(.btn-gold):not(.btn-danger):not(.badge):not(svg):not(path) {
    color: inherit;
}
body[data-theme="light"],
body[data-theme="light"] p,
body[data-theme="light"] span,
body[data-theme="light"] div,
body[data-theme="light"] td,
body[data-theme="light"] li,
body[data-theme="light"] h1,
body[data-theme="light"] h2,
body[data-theme="light"] h3,
body[data-theme="light"] label,
body[data-theme="light"] a:not(.btn-gold) { color: #2a1f14 !important; }
body[data-theme="light"] .sidebar { background: #faf7f2 !important; border-right-color: rgba(139,105,20,.2) !important; }
body[data-theme="light"] .topbar  { background: #ffffff !important; border-bottom-color: rgba(139,105,20,.2) !important; }
body[data-theme="light"] .card    { background: #ffffff !important; border-color: rgba(139,105,20,.2) !important; }
body[data-theme="light"] .content { background: #f8f5ef !important; }
body[data-theme="light"] th       { background: rgba(201,168,76,.1) !important; }
body[data-theme="light"] .form-group input,
body[data-theme="light"] .form-group select,
body[data-theme="light"] .form-group textarea { background: #f9f7f2 !important; border-color: rgba(139,105,20,.25) !important; }
body[data-theme="light"] .stat-card { background: rgba(201,168,76,.08) !important; border-color: rgba(139,105,20,.2) !important; }
body[data-theme="light"] .btn-outline { border-color: rgba(139,105,20,.3) !important; }
body[data-theme="light"] nav a { color: rgba(42,31,20,.65) !important; }
body[data-theme="light"] nav a.active { color: #8B6914 !important; background: rgba(201,168,76,.15) !important; }
body[data-theme="light"] .btn-gold { color: #1a1410 !important; }
body[data-theme="light"] .badge-active   { color: #1a6b47 !important; }
body[data-theme="light"] .badge-inactive { color: rgba(42,31,20,.5) !important; }
body[data-theme="light"] .gold, body[data-theme="light"] [style*="color:#c9a84c"],
body[data-theme="light"] [style*="color:var(--gold)"] { color: #8B6914 !important; }
body[data-theme="light"] .sidebar-footer a { color: rgba(42,31,20,.5) !important; }

</style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;700&family=Noto+Serif+JP:wght@700&display=swap" rel="stylesheet">
<script>
var THEME_KEY = 'sengoku_theme';
function _getTheme() {
    try { return localStorage.getItem(THEME_KEY) || 'dark'; } catch(e) { return 'dark'; }
}
function _applyTheme(t) {
    document.documentElement.setAttribute('data-theme', t);
    if (document.body) document.body.setAttribute('data-theme', t);
    try { localStorage.setItem(THEME_KEY, t); } catch(e) {}
    document.querySelectorAll('.theme-toggle').forEach(function(b) {
        b.textContent = t === 'dark' ? 'Light' : 'Dark';
        b.title = t === 'dark' ? 'ライトモードに切り替え' : 'ダークモードに切り替え';
    });
}
function toggleTheme() {
    var cur = document.documentElement.getAttribute('data-theme') || 'dark';
    _applyTheme(cur === 'dark' ? 'light' : 'dark');
}
_applyTheme(_getTheme());
document.addEventListener('DOMContentLoaded', function() {
    _applyTheme(_getTheme());
    document.querySelectorAll('.theme-toggle').forEach(function(b) {
        b.onclick = function() { toggleTheme(); };
    });
});
window.toggleTheme = toggleTheme;
</script>
</head>
<body>
<aside class="sidebar" id="adminSidebar">
    <div class="sidebar-logo">
        <span>戦国経済圏</span>
        管理パネル
    </div>
    <nav>
        <?php
        $current = basename($_SERVER['PHP_SELF']);
        function navLink(string $href, string $iconHtml, string $label, string $current): void {
            $file = basename($href);
            $active = ($file === $current) ? ' active' : '';
            echo '<a href="' . h($href) . '" class="' . h(trim($active)) . '"><span class="nav-icon">' . $iconHtml . '</span>' . h($label) . '</a>';
        }
        navLink('/admin/dashboard.php', '&#128200;', 'ダッシュボード', $current);
        navLink('/admin/applicants.php', '&#128221;', 'エージェント申請', $current);
        navLink('/admin/promotion_requests.php', '&#11014;', '昇格申請承認', $current);
        navLink('/admin/agents.php', '&#128101;', 'メンバー管理', $current);
        navLink('/admin/projects.php', '&#127919;', 'プロジェクト管理', $current);
        navLink('/admin/templates.php', '&#127912;', 'テンプレート管理', $current);
        navLink('/admin/template_reports.php', '&#128200;', 'LP成果分析', $current);
        navLink('/admin/agent_activity.php', '&#128202;', '代理店活動', $current);
        navLink('/admin/materials.php', '&#128230;', '紹介素材管理', $current);
        navLink('/admin/notices.php', '&#128226;', 'お知らせ管理', $current);
        navLink('/admin/broadcast.php', '&#128231;', '一斉メール送信', $current);
        navLink('/admin/leads.php', '&#128229;', '問い合わせ管理', $current);
        navLink('/admin/action_logs.php', '&#128336;', '操作ログ', $current);
        navLink('/admin/login_logs.php', '&#128273;', 'ログイン記録', $current);
        navLink('/admin/operations.php', '&#128680;', '運用チェック', $current);
        navLink('/admin/sso_settings.php', '&#128274;', 'SSO連携', $current);
        navLink('/admin/external_partners.php', '&#128268;', '外部API連携', $current);
        navLink('/admin/integration_logs.php', '&#128225;', '外部連携ログ', $current);
        navLink('/admin/common_id.php', '&#128279;', '共通ID連携', $current);
        navLink('/admin/common_id_mappings.php', '&#128269;', '共通ID検索', $current);
        navLink('/admin/common_hub.php', '&#128101;', '共通顧客HUB', $current);
        navLink('/admin/common_hub_alerts.php', '&#9888;', 'HUB確認', $current);
        navLink('/admin/common_hub_fix.php', '&#128295;', 'HUB修正', $current);
        if (isSuperAdmin()) {
            navLink('/admin/staff.php', '&#128188;', '管理スタッフ', $current);
        }
        navLink('/admin/settings.php', '&#9881;', 'システム設定', $current);
        navLink('/admin/update.php', '&#128260;', 'アップデート', $current);
        ?>
    </nav>
    <div class="sidebar-footer">
        <a href="/admin/logout.php">ログアウト</a>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<main class="main">
    <div class="topbar">
        <div style="display:flex;align-items:center;gap:.65rem;min-width:0;">
            <button class="menu-btn" type="button" aria-label="メニューを開く" aria-controls="adminSidebar" aria-expanded="false">Menu</button>
            <h1><?= h($pageTitle ?? '管理画面') ?></h1>
        </div>
        <div style="display:flex;align-items:center;gap:.75rem;">
            <button class="theme-toggle" id="themeBtn"
                style="background:none;border:1px solid var(--border);border-radius:3px;
                       padding:.3rem .6rem;font-size:.82rem;cursor:pointer;color:var(--paper);
                       transition:border-color .2s;position:relative;z-index:9999;pointer-events:auto;"
                title="テーマ切替">Light</button>
            <span class="topbar-user">管理者</span>
        </div>
    </div>    <div class="content">
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const menuBtn = document.querySelector('.menu-btn');
    const eyeIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    const eyeOffIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"></path><path d="M10.6 10.6A2 2 0 0 0 12 14a2 2 0 0 0 1.4-.6"></path><path d="M9.9 5.1A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a16 16 0 0 1-3.1 4.1"></path><path d="M6.1 6.1C3.5 7.8 2 12 2 12s3.5 7 10 7c1.2 0 2.3-.2 3.3-.7"></path></svg>';
    document.querySelectorAll('input[type="password"]:not([data-no-toggle])').forEach(function(input) {
        if (input.dataset.passwordToggleReady === '1') return;
        input.dataset.passwordToggleReady = '1';
        const wrapper = document.createElement('span');
        wrapper.className = 'password-toggle-wrap';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'password-toggle-btn';
        button.innerHTML = eyeIcon;
        button.setAttribute('aria-label', '繝代せ繝ｯ繝ｼ繝峨ｒ陦ｨ遉ｺ');
        button.addEventListener('click', function() {
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            button.innerHTML = visible ? eyeOffIcon : eyeIcon;
            button.setAttribute('aria-label', visible ? '繝代せ繝ｯ繝ｼ繝峨ｒ髱櫁｡ｨ遉ｺ' : '繝代せ繝ｯ繝ｼ繝峨ｒ陦ｨ遉ｺ');
        });
        wrapper.appendChild(button);
    });

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('open');
        menuBtn?.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('open');
        menuBtn?.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    function toggleSidebar() {
        if (sidebar.classList.contains('open')) closeSidebar();
        else openSidebar();
    }

    menuBtn?.addEventListener('click', toggleSidebar);
    overlay?.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });
    document.querySelectorAll('.sidebar nav a').forEach(a => {
        a.addEventListener('click', () => { if (window.innerWidth <= 900) closeSidebar(); });
    });
});
</script>

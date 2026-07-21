<?php
/**
 * アドバイザーマイページ 共通ヘッダー
 * $pageTitle を定義してから include すること
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// setup.php と login.php 以外は認証チェック
$noAuthPages = ['login.php', 'setup.php'];
$currentFile = basename($_SERVER['PHP_SELF']);
if (!in_array($currentFile, $noAuthPages)) {
    if (empty($_SESSION['agent_id'])) {
        header('Location: /agent/login.php');
        exit;
    }
    // セッションのエージェント情報を再取得（最新状態）
    $db         = getDB();
    $agentStmt  = $db->prepare("SELECT * FROM agents WHERE id = ? AND status = 'active'");
    $agentStmt->execute([$_SESSION['agent_id']]);
    $currentAgent = $agentStmt->fetch();
    if (!$currentAgent) {
        session_destroy();
        header('Location: /agent/login.php?err=inactive');
        exit;
    }
}

$agentRoleLabel = 'マイページ';
if (!empty($currentAgent)) {
    $level = (int)($currentAgent['level'] ?? 1);
    if ($level === 1) {
        $agentRoleLabel = getAdvisorPositionLabel($currentAgent['position_type'] ?? null, $currentAgent['position_label'] ?? null);
    } else {
        $levelLabels = getLevelLabels();
        $agentRoleLabel = $levelLabels[$level] ?? 'メンバー';
    }
}
$agentPortalLabel = $agentRoleLabel === 'マイページ' ? 'マイページ' : $agentRoleLabel . 'マイページ';

$agentProjectOptions = [];
$selectedAgentProject = null;
$selectedAgentProjectId = 0;
$selectedAgentProjectLpUrl = '';
if (!empty($currentAgent)) {
    try {
        $agentProjectOptions = function_exists('getProjects') ? getProjects(true) : [];
    } catch (Throwable $e) {
        $agentProjectOptions = [];
    }

    if (isset($_GET['switch_project_id'])) {
        $switchProjectId = (int)$_GET['switch_project_id'];
        $isValidProject = false;
        foreach ($agentProjectOptions as $projectOption) {
            if ((int)($projectOption['id'] ?? 0) === $switchProjectId) {
                $isValidProject = true;
                break;
            }
        }
        if ($isValidProject) {
            $_SESSION['agent_selected_project_id'] = $switchProjectId;
        }

        $redirectPath = parse_url($_SERVER['REQUEST_URI'] ?? '/agent/dashboard.php', PHP_URL_PATH) ?: '/agent/dashboard.php';
        $redirectQuery = $_GET;
        unset($redirectQuery['switch_project_id']);
        $queryString = http_build_query($redirectQuery);
        header('Location: ' . $redirectPath . ($queryString ? '?' . $queryString : ''));
        exit;
    }

    $selectedAgentProjectId = (int)($_SESSION['agent_selected_project_id'] ?? 0);
    foreach ($agentProjectOptions as $projectOption) {
        if ((int)($projectOption['id'] ?? 0) === $selectedAgentProjectId) {
            $selectedAgentProject = $projectOption;
            break;
        }
    }
    if (!$selectedAgentProject && !empty($agentProjectOptions)) {
        $selectedAgentProject = $agentProjectOptions[0];
        $selectedAgentProjectId = (int)($selectedAgentProject['id'] ?? 0);
        $_SESSION['agent_selected_project_id'] = $selectedAgentProjectId;
    }

    if ($selectedAgentProject) {
        $selectedAgentProjectLpUrl = function_exists('buildAgentProjectLpUrl')
            ? buildAgentProjectLpUrl((string)$currentAgent['agent_code'], $selectedAgentProject)
            : '/a/' . rawurlencode((string)$currentAgent['agent_code']) . '?project=' . rawurlencode((string)($selectedAgentProject['slug'] ?? ''));
    } else {
        $selectedAgentProjectLpUrl = '/a/' . rawurlencode((string)$currentAgent['agent_code']);
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle ?? 'マイページ') ?> | 戦国経済圏 <?= h($agentRoleLabel) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@400;700;900&family=Noto+Sans+JP:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { width: 100%; overflow-x: hidden; }
:root {
  --black:     #0A0805;
  --ink:       #13100D;
  --sidebar:   #0F0D0A;
  --gold:      #C9A84C;
  --gold-lt:   #E2C87A;
  --gold-dk:   #8B6914;
  --crimson:   #8B1A1A;
  --cream:     #F5F0E8;
  --paper:     #E8E0CC;
  --border:    rgba(201,168,76,.18);
  --text-muted:rgba(245,240,232,.45);
  --active-bg: rgba(201,168,76,.12);
}
/* ライトモード */
[data-theme="light"] {
  --black:      #f5f0e8;
  --ink:        #ffffff;
  --sidebar:    #faf7f2;
  --paper:      #2a1f14;
  --cream:      #2a1f14;
  --border:     rgba(139,105,20,.2);
  --text-muted: rgba(42,31,20,.4);
  --active-bg:  rgba(201,168,76,.15);
}
[data-theme="light"] body { background: #f5f0e8; color: #2a1f14; }
[data-theme="light"] .sidebar { background: #faf7f2; border-right-color: rgba(139,105,20,.2); }
[data-theme="light"] .sidebar-logo .brand { color: var(--gold-dk); }
[data-theme="light"] .sidebar-logo .sub { color: rgba(42,31,20,.4); }
[data-theme="light"] .sidebar-agent .name { color: var(--gold-dk); }
[data-theme="light"] .sidebar-agent .code { color: rgba(42,31,20,.45); }
[data-theme="light"] nav a { color: rgba(42,31,20,.55); }
[data-theme="light"] nav a:hover { background: rgba(201,168,76,.08); color: #2a1f14; }
[data-theme="light"] nav a.active { background: rgba(201,168,76,.15); color: var(--gold-dk); border-left-color: var(--gold); }
[data-theme="light"] .sidebar-footer a { color: rgba(42,31,20,.45); }
[data-theme="light"] .main-wrap { background: #f5f0e8; }
[data-theme="light"] .topbar { background: #fff; border-bottom-color: rgba(139,105,20,.2); }
[data-theme="light"] .page-title { color: #2a1f14; }
[data-theme="light"] .topbar-right a { color: var(--gold-dk); }
[data-theme="light"] .content { background: #f5f0e8; }
[data-theme="light"] .card { background: #fff; border-color: rgba(139,105,20,.2); }
[data-theme="light"] .card-title { color: #2a1f14; border-bottom-color: rgba(139,105,20,.15); }
[data-theme="light"] label { color: var(--gold-dk); }
[data-theme="light"] input, [data-theme="light"] select, [data-theme="light"] textarea {
  background: #f9f7f2; border-color: rgba(139,105,20,.25); color: #2a1f14;
}
[data-theme="light"] input:focus, [data-theme="light"] textarea:focus { border-color: var(--gold); background: #fff; }
[data-theme="light"] table th { background: rgba(201,168,76,.08); color: var(--gold-dk); }
[data-theme="light"] table td { color: #2a1f14; border-color: rgba(42,31,20,.1); }
[data-theme="light"] .badge-new { background: rgba(201,168,76,.15); color: var(--gold-dk); }
[data-theme="light"] .badge-contacted { background: rgba(45,106,79,.1); color: #1a6b47; }
[data-theme="light"] .badge-closed { background: rgba(42,31,20,.07); color: rgba(42,31,20,.45); }
[data-theme="light"] .alert-success { background: rgba(45,106,79,.08); border-color: rgba(45,106,79,.3); color: #1a6b47; }
[data-theme="light"] .alert-error { background: rgba(139,26,26,.07); border-color: rgba(139,26,26,.3); color: #8b1a1a; }
[data-theme="light"] .btn-outline { border-color: rgba(139,105,20,.3); color: #2a1f14; }
[data-theme="light"] .btn-outline:hover { border-color: var(--gold); background: rgba(201,168,76,.08); }
[data-theme="light"] pre { background: #f0ebe0; border-color: rgba(139,105,20,.2); color: #2a1f14; }
html, body { height: 100%; }
body {
  font-family: 'Noto Sans JP', sans-serif;
  background: var(--black);
  color: var(--paper);
  display: flex;
  min-height: 100vh;
  font-size: 14px;
  width: 100%;
  overflow-x: hidden;
}

/* ===== サイドバー ===== */
.sidebar {
  width: 220px;
  min-height: 100vh;
  height: 100vh;
  height: 100dvh;
  max-height: 100dvh;
  background: var(--sidebar);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  position: fixed;
  top: 0; left: 0; bottom: 0;
  overflow-y: auto;
  overflow-x: hidden;
  -webkit-overflow-scrolling: touch;
  overscroll-behavior: contain;
}

.sidebar-logo {
  padding: 1.25rem 1.25rem 1rem;
  border-bottom: 1px solid var(--border);
}
.sidebar-logo .brand {
  font-family: 'Noto Serif JP', serif;
  font-size: .85rem;
  font-weight: 900;
  color: var(--gold-lt);
  letter-spacing: .05em;
  text-decoration: none;
  display: block;
}
.sidebar-logo .sub {
  font-size: .68rem;
  color: var(--text-muted);
  margin-top: .2rem;
  letter-spacing: .05em;
}

.sidebar-agent {
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--border);
  font-size: .78rem;
}
.sidebar-agent .name { color: var(--gold-lt); font-weight: 700; }
.sidebar-agent .code { color: var(--text-muted); font-size: .72rem; margin-top: .15rem; }
.project-switcher {
  padding: .8rem 1.25rem .9rem;
  border-bottom: 1px solid var(--border);
}
.project-switcher label {
  display: block;
  color: var(--text-muted);
  font-size: .68rem;
  letter-spacing: .08em;
}
.project-switcher select {
  width: 100%;
  margin-top: .4rem;
  padding: .42rem .55rem;
  border: 1px solid var(--border);
  border-radius: 4px;
  background: var(--ink);
  color: var(--paper);
  font-size: .78rem;
}
.project-switcher .project-lp-link {
  display: block;
  margin-top: .45rem;
  color: var(--gold);
  font-size: .72rem;
  text-decoration: none;
  word-break: break-all;
}
.project-switcher .project-lp-link:hover { color: var(--gold-lt); }

nav { flex: 1 0 auto; padding: .5rem 0; }
nav a {
  display: flex;
  align-items: center;
  gap: .7rem;
  padding: .65rem 1.25rem;
  color: var(--text-muted);
  text-decoration: none;
  font-size: .82rem;
  border-left: 3px solid transparent;
  transition: background .15s, color .15s;
}
nav a:hover { background: rgba(201,168,76,.06); color: var(--paper); }
nav a.active { background: var(--active-bg); color: var(--gold); border-left-color: var(--gold); }
.nav-icon { width: 18px; text-align: center; }

.sidebar-footer {
  flex-shrink: 0;
  padding: 1rem 1.25rem;
  border-top: 1px solid var(--border);
}
.sidebar-footer a {
  font-size: .75rem;
  color: var(--text-muted);
  text-decoration: none;
}
.sidebar-footer a:hover { color: var(--paper); }

/* ===== メインコンテンツ ===== */
.main-wrap {
  margin-left: 220px;
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  min-width: 0;
}

.topbar {
  background: var(--ink);
  border-bottom: 1px solid var(--border);
  padding: .85rem 1.75rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-width: 0;
}
.page-title {
  font-family: 'Noto Serif JP', serif;
  font-size: 1rem;
  font-weight: 700;
  color: var(--paper);
  min-width: 0;
  overflow-wrap: anywhere;
}
.topbar-right { font-size: .78rem; color: var(--text-muted); }

.content { padding: 1.75rem; flex: 1; min-width: 0; max-width: 100%; }

/* ===== カード ===== */
.card {
  background: var(--ink);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 1.5rem;
  margin-bottom: 1.25rem;
  max-width: 100%;
  overflow-wrap: anywhere;
}
.card-title {
  font-family: 'Noto Serif JP', serif;
  font-size: .9rem;
  font-weight: 700;
  color: var(--gold-lt);
  margin-bottom: 1.1rem;
  padding-bottom: .65rem;
  border-bottom: 1px solid var(--border);
}

/* ===== フォーム ===== */
.form-group { margin-bottom: 1.1rem; }
label {
  display: block;
  font-size: .75rem;
  letter-spacing: .08em;
  color: var(--gold);
  margin-bottom: .4rem;
}
input[type="text"],input[type="email"],input[type="tel"],
input[type="url"],input[type="password"],textarea,select {
  width: 100%;
  padding: .7rem .9rem;
  background: rgba(255,255,255,.05);
  border: 1px solid var(--border);
  border-radius: 3px;
  color: var(--cream);
  font-family: inherit;
  font-size: .88rem;
  transition: border-color .2s;
}
input:focus,textarea:focus,select:focus {
  outline: none;
  border-color: var(--gold);
  background: rgba(255,255,255,.07);
}
textarea { resize: vertical; min-height: 100px; }

/* ===== ボタン ===== */
.btn {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  padding: .6rem 1.2rem;
  border-radius: 3px;
  font-family: inherit;
  font-size: .82rem;
  font-weight: 700;
  cursor: pointer;
  border: none;
  text-decoration: none;
  transition: opacity .2s, transform .15s;
}
.btn:hover { opacity: .88; transform: translateY(-1px); }
.btn-gold { background: linear-gradient(135deg,var(--gold),var(--gold-lt)); color: var(--ink); }
.btn-outline { background: transparent; border: 1px solid var(--border); color: var(--paper); }
.btn-outline:hover { border-color: var(--gold); }
.btn-danger { background: var(--crimson); color: #fff; }
.btn-danger:hover { background: #a92323; }

/* ===== テーブル ===== */
table { width: 100%; border-collapse: collapse; }
th, td { padding: .7rem .9rem; text-align: left; border-bottom: 1px solid var(--border); font-size: .82rem; }
th { color: var(--gold); font-size: .72rem; letter-spacing: .08em; background: rgba(201,168,76,.04); }

/* ===== アラート ===== */
.alert { padding: .8rem 1rem; border-radius: 3px; font-size: .85rem; margin-bottom: 1rem; }
.alert-success { background: rgba(94,203,155,.1); border: 1px solid rgba(94,203,155,.35); color: #5ecb9b; }
.alert-error   { background: rgba(139,26,26,.18); border: 1px solid rgba(178,34,34,.4);   color: #e08080; }

/* ===== バッジ ===== */
.badge { display: inline-block; padding: .2rem .6rem; border-radius: 2px; font-size: .72rem; font-weight: 700; }
.badge-new       { background: rgba(201,168,76,.2); color: var(--gold); }
.badge-contacted { background: rgba(94,203,155,.15); color: #5ecb9b; }
.badge-closed    { background: rgba(245,240,232,.06); color: var(--text-muted); }

/* ===== チェックボックス ===== */
.form-check { display: flex; align-items: flex-start; gap: .6rem; cursor: pointer; }
.form-check input[type="checkbox"] { width: auto; margin-top: .15rem; accent-color: var(--gold); }

/* ===== レスポンシブ ===== */
@media (max-width: 768px) {
  .sidebar { display: flex; }
  .main-wrap { margin-left: 0; }
}

/* ===== モバイル対応（⑨） ===== */
@media (max-width: 768px) {
    .sidebar {
        display: flex !important;
        position: fixed;
        left: -240px;
        top: 0; bottom: 0;
        z-index: 1000;
        transition: left .25s ease;
        width: 220px;
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
    .overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.5);
        z-index: 999;
    }
    .overlay.open { display: block; }
    .main-wrap { margin-left: 0 !important; }
    .topbar { padding: .65rem .85rem; gap: .6rem; align-items: flex-start; }
    .topbar > div { min-width: 0; }
    .page-title { font-size: .92rem; line-height: 1.35; }
    .topbar-right { gap: .5rem !important; font-size: .72rem; flex-wrap: wrap; justify-content: flex-end; }
    .content { padding: 1rem; }
    .menu-btn {
        display: flex !important;
        width: 34px; height: 34px;
        background: none;
        border: 1px solid var(--border);
        border-radius: 3px;
        color: var(--paper);
        font-size: 1.1rem;
        cursor: pointer;
        align-items: center;
        justify-content: center;
    }
    table { font-size: .78rem; }
    th, td { padding: .5rem .6rem; }
    .card { padding: 1rem; overflow-x: auto; }
    .card table { min-width: 680px; }
    .btn { white-space: normal; text-align: center; justify-content: center; }
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
    pre { font-size: .78rem !important; }
}
@media (max-width: 520px) {
    .content { padding: .75rem; }
    .card { padding: .85rem; margin-bottom: 1rem; }
    .card table { min-width: 620px; }
    .btn { width: 100%; }
    table .btn { width: auto; }
    .topbar-right { display: none !important; }
}
@media (min-width: 769px) {
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


/* ライトモード body直接指定 */
body[data-theme="light"] {
    background: #f5f0e8 !important;
    color: #2a1f14 !important;
}
body[data-theme="light"] .sidebar    { background: #faf7f2 !important; border-right-color: rgba(139,105,20,.2) !important; }
body[data-theme="light"] .sidebar-logo .brand { color: #8B6914 !important; }
body[data-theme="light"] .sidebar-logo .sub   { color: rgba(42,31,20,.4) !important; }
body[data-theme="light"] .sidebar-agent .name { color: #8B6914 !important; }
body[data-theme="light"] .sidebar-agent .code { color: rgba(42,31,20,.45) !important; }
body[data-theme="light"] nav a       { color: rgba(42,31,20,.55) !important; }
body[data-theme="light"] nav a.active{ color: #8B6914 !important; background: rgba(201,168,76,.15) !important; }
body[data-theme="light"] .topbar     { background: #ffffff !important; border-bottom-color: rgba(139,105,20,.2) !important; }
body[data-theme="light"] .page-title { color: #2a1f14 !important; }
body[data-theme="light"] .content    { background: #f5f0e8 !important; }
body[data-theme="light"] .card       { background: #ffffff !important; border-color: rgba(139,105,20,.2) !important; }
body[data-theme="light"] .card-title { color: #2a1f14 !important; border-bottom-color: rgba(139,105,20,.15) !important; }
body[data-theme="light"] label       { color: #8B6914 !important; }
body[data-theme="light"] input, body[data-theme="light"] select, body[data-theme="light"] textarea {
    background: #f9f7f2 !important; border-color: rgba(139,105,20,.25) !important; color: #2a1f14 !important;
}
body[data-theme="light"] table th    { color: #8B6914 !important; background: rgba(201,168,76,.08) !important; }
body[data-theme="light"] table td    { color: #2a1f14 !important; border-color: rgba(42,31,20,.1) !important; }
body[data-theme="light"] .badge-new  { background: rgba(201,168,76,.15) !important; color: #8B6914 !important; }
body[data-theme="light"] pre         { background: #f0ebe0 !important; color: #2a1f14 !important; }
body[data-theme="light"] .sidebar-footer a { color: rgba(42,31,20,.45) !important; }
body[data-theme="light"] .btn-outline { border-color: rgba(139,105,20,.3) !important; color: #2a1f14 !important; }
body[data-theme="light"] .btn-danger { background: #b02a2a !important; color: #fff !important; }

</style>
<script>/**
 * テーマ切替（ダーク/ライト）
 * 全ページで共通利用
 */
(function() {
    const STORAGE_KEY = 'sengoku_theme';

    // 保存済みテーマまたはシステム設定を適用
    function getTheme() {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved) return saved;
        return window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        document.body && document.body.setAttribute('data-theme', theme);
        localStorage.setItem(STORAGE_KEY, theme);
        // ボタンアイコン更新
        document.querySelectorAll('.theme-toggle').forEach(btn => {
            btn.textContent = theme === 'dark' ? '☀️' : '🌙';
            btn.title = theme === 'dark' ? 'ライトモードに切替' : 'ダークモードに切替';
        });
    }

    function toggleTheme() {
        const current = document.documentElement.getAttribute('data-theme') || 'dark';
        applyTheme(current === 'dark' ? 'light' : 'dark');
    }

    // 初期適用（フラッシュ防止のため即時実行）
    applyTheme(getTheme());

    // DOM読み込み後にボタンにイベント設定
    document.addEventListener('DOMContentLoaded', function() {
        applyTheme(getTheme()); // 再適用
        document.querySelectorAll('.theme-toggle').forEach(btn => {
            btn.addEventListener('click', toggleTheme);
        });
    });

    window.toggleTheme = toggleTheme;
})();
</script>
</head>
<body>

<aside class="sidebar" id="agentSidebar">
  <div class="sidebar-logo">
    <a href="/agent/dashboard.php" class="brand">⚔ 戦国経済圏</a>
    <p class="sub"><?= h($agentPortalLabel) ?></p>
  </div>
  <?php if (!empty($currentAgent)): ?>
  <div class="sidebar-agent">
    <p class="name"><?= h($currentAgent['person_name']) ?></p>
    <p class="code"><?= h($currentAgent['agent_name']) ?></p>
  </div>
  <?php endif; ?>
  <?php if (!empty($currentAgent) && !empty($agentProjectOptions)): ?>
  <div class="project-switcher">
    <form method="get" action="<?= h(parse_url($_SERVER['REQUEST_URI'] ?? '/agent/dashboard.php', PHP_URL_PATH) ?: '/agent/dashboard.php') ?>">
      <label for="agentProjectSwitch">プロジェクト切替</label>
      <select id="agentProjectSwitch" name="switch_project_id" onchange="this.form.submit()">
        <?php foreach ($agentProjectOptions as $projectOption): ?>
        <?php $projectOptionId = (int)($projectOption['id'] ?? 0); ?>
        <option value="<?= $projectOptionId ?>" <?= $projectOptionId === $selectedAgentProjectId ? 'selected' : '' ?>>
          <?= h($projectOption['name'] ?? $projectOption['slug'] ?? 'プロジェクト') ?>
        </option>
        <?php endforeach; ?>
      </select>
      <?php foreach ($_GET as $key => $value): ?>
        <?php if ($key === 'switch_project_id' || is_array($value)) { continue; } ?>
        <input type="hidden" name="<?= h((string)$key) ?>" value="<?= h((string)$value) ?>">
      <?php endforeach; ?>
    </form>
    <?php if ($selectedAgentProjectLpUrl): ?>
    <a class="project-lp-link" href="<?= h($selectedAgentProjectLpUrl) ?>" target="_blank">選択中LPを開く ↗</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <nav>
    <?php
    function agentNavLink(string $href, string $icon, string $label): void {
        $current = basename($_SERVER['PHP_SELF']);
        $path    = parse_url($href, PHP_URL_PATH) ?: $href;
        $active  = ($current === basename($path)) ? 'active' : '';
        echo "<a href=\"$href\" class=\"$active\"><span class=\"nav-icon\">$icon</span>$label</a>";
    }
    agentNavLink('/agent/dashboard.php', '📊', 'ダッシュボード');
    agentNavLink('/agent/reports.php', '📈', '活動レポート');
    if (!empty($currentAgent) && (int)($currentAgent['level'] ?? 1) >= 2) {
        agentNavLink('/agent/downline_activity.php', '📊', '配下活動');
    }

    if (!empty($currentAgent)) {
        $myLv  = (int)($currentAgent['level'] ?? 1);
        $lbls  = getLevelLabels();

        echo '<div style="padding:.55rem 1.25rem .25rem;font-size:.68rem;color:var(--text-muted);letter-spacing:.08em;">告知・招待機能</div>';
        agentNavLink('/agent/settings.php',  '⚙️',  'LP・通知設定');
        agentNavLink('/agent/influencer_profile.php', '🪪', 'プロフィール設定');
        if ($myLv >= 2) {
            agentNavLink('/agent/recruitment_links.php', '🔗', '招待URL管理');
            agentNavLink('/agent/followups.php', '📝', 'フォロー管理');
        }

        // level3（エージェント）：エージェントメニュー + ディレクターメニュー
        if ($myLv >= 3) {
            echo '<div style="padding:.55rem 1.25rem .25rem;font-size:.68rem;color:var(--text-muted);letter-spacing:.08em;">エージェントメニュー</div>';
            agentNavLink('/agent/sub_agents.php?mode=directors', '👥', h($lbls[2] ?? 'ディレクター') . '管理');
            $promoCnt = getPendingPromotionCount($currentAgent['id']);
            $promoLbl = '昇格申請管理' . ($promoCnt > 0 ? ' <span style="background:#8b1a1a;color:#fff;font-size:.65rem;padding:.1rem .4rem;border-radius:9px;">' . $promoCnt . '</span>' : '');
            $cur2 = basename($_SERVER['PHP_SELF']);
            $ac2  = ($cur2 === 'promotion_requests.php') ? 'active' : '';
            echo '<a href="/agent/promotion_requests.php" class="' . $ac2 . '"><span class="nav-icon">⬆️</span>' . $promoLbl . '</a>';
            echo '<div style="padding:.55rem 1.25rem .25rem;font-size:.68rem;color:var(--text-muted);letter-spacing:.08em;">ディレクターメニュー</div>';
            agentNavLink('/agent/sub_agents.php?mode=advisors', '👥', h($lbls[1] ?? 'アドバイザー') . '管理');
        }

        // level2（ディレクター）：傘下アドバイザー管理 + 昇格申請フォーム
        if ($myLv === 2) {
            echo '<div style="padding:.55rem 1.25rem .25rem;font-size:.68rem;color:var(--text-muted);letter-spacing:.08em;">ディレクターメニュー</div>';
            agentNavLink('/agent/sub_agents.php?mode=advisors', '👥', '傘下' . h($lbls[1]) . '管理');
            if (!empty($currentAgent['parent_id'])) {
                agentNavLink('/agent/request_promotion.php', '⬆️', h($lbls[3] ?? 'エージェント') . 'へ昇格申請');
            }
        }

        // level1（アドバイザー）：ディレクターへ昇格申請
        if ($myLv === 1 && !empty($currentAgent['parent_id'])) {
            agentNavLink('/agent/request_promotion.php', '⬆️', h($lbls[2] ?? 'ディレクター') . 'へ昇格申請');
        }
    }

    // 未対応件数バッジ
    if (!empty($currentAgent)) {
        $db2 = getDB();
        $leadAgentIds = [(int)$currentAgent['id']];
        if ((int)($currentAgent['level'] ?? 1) >= 2) {
            foreach (getAllDescendants((int)$currentAgent['id']) as $descendant) {
                $leadAgentIds[] = (int)$descendant['id'];
            }
        }
        $leadAgentIds = array_values(array_unique($leadAgentIds));
        $leadPlaceholders = implode(',', array_fill(0, count($leadAgentIds), '?'));
        $newCount = $db2->prepare("SELECT COUNT(*) FROM leads WHERE agent_id IN ($leadPlaceholders) AND status='new'");
        $newCount->execute($leadAgentIds);
        $newCount = (int)$newCount->fetchColumn();
        $badgeHtml = $newCount > 0
            ? ' <span style="background:var(--crimson,#8b1a1a);color:#fff;font-size:.65rem;font-weight:700;padding:.1rem .45rem;border-radius:9px;margin-left:auto;">' . $newCount . '</span>'
            : '';
        $cur = basename($_SERVER['PHP_SELF']);
        $active = ($cur === 'leads.php') ? ' active' : '';
        echo '<a href="/agent/leads.php" class="' . $active . '"><span class="nav-icon">📩</span>問い合わせ一覧' . $badgeHtml . '</a>';
    } else {
        agentNavLink('/agent/leads.php', '📩', '問い合わせ一覧');
    }

    agentNavLink('/agent/materials.php', '📦', '紹介素材');
    ?>
  </nav>
  <div class="sidebar-footer">
    <a href="/manual" target="_blank" style="display:block;margin-bottom:.4rem;">📖 使い方マニュアル</a>
    <a href="/agent/logout.php">ログアウト</a>
  </div>
</aside>

<div class="main-wrap">
  <!-- モバイルオーバーレイ -->
  <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

  <div class="topbar">
    <div style="display:flex;align-items:center;gap:.65rem;">
        <button class="menu-btn" type="button" aria-label="メニューを開く" aria-controls="agentSidebar" aria-expanded="false">☰</button>
        <p class="page-title"><?= h($pageTitle ?? '') ?></p>
    </div>
    <div class="topbar-right" style="display:flex;align-items:center;gap:.75rem;">
        <button class="theme-toggle"
            style="background:none;border:1px solid var(--border);border-radius:3px;
                   padding:.3rem .6rem;font-size:1rem;cursor:pointer;color:var(--paper);
                   transition:border-color .2s;"
            title="テーマ切替">☀️</button>
      <?php if (!empty($currentAgent)): ?>
      <?php $topLpUrl = $selectedAgentProjectLpUrl ?: ('/a/' . rawurlencode((string)$currentAgent['agent_code'])); ?>
      LP: <a href="<?= h($topLpUrl) ?>" target="_blank"
             style="color:var(--gold);text-decoration:none;"><?= h($selectedAgentProject['name'] ?? 'LP') ?> ↗</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="content">
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('overlay');
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
        button.setAttribute('aria-label', 'パスワードを表示');
        button.addEventListener('click', function() {
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            button.innerHTML = visible ? eyeOffIcon : eyeIcon;
            button.setAttribute('aria-label', visible ? 'パスワードを非表示' : 'パスワードを表示');
        });
        wrapper.appendChild(button);
    });

    function toggleSidebar() {
        const willOpen = !sidebar.classList.contains('open');
        sidebar.classList.toggle('open', willOpen);
        overlay.classList.toggle('open', willOpen);
        menuBtn?.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        document.body.style.overflow = willOpen ? 'hidden' : '';
    }
    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('open');
        menuBtn?.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }
    menuBtn?.addEventListener('click', toggleSidebar);
    overlay?.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });
    document.querySelectorAll('.sidebar nav a').forEach(a => {
        a.addEventListener('click', () => { if (window.innerWidth <= 768) closeSidebar(); });
    });
    window.toggleSidebar = toggleSidebar;
    window.closeSidebar  = closeSidebar;
});
</script>

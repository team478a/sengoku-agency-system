/**
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

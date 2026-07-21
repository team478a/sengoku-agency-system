<?php
$pageTitle = 'システムアップデート';
require_once __DIR__ . '/header.php';

$BASE_DIR = dirname(__DIR__);
$MIGRATIONS_DIR = $BASE_DIR . '/config/migrations';
$BACKUP_DIR = $BASE_DIR . '/backups';
$SYSTEM_VERSION = trim((string)(@file_get_contents($BASE_DIR . '/VERSION') ?: '1.0.0'));

if (!is_dir($BACKUP_DIR)) {
    @mkdir($BACKUP_DIR, 0755, true);
    @file_put_contents($BACKUP_DIR . '/.htaccess', "deny from all\n");
}

$csrf = getCsrfToken();
$db = getDB();
$message = '';
$msgType = 'success';
$log = [];

function updaterAppliedMigrations(PDO $db): array {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(50) PRIMARY KEY,
            description TEXT,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return $db->query("SELECT version FROM schema_migrations ORDER BY version")
            ->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Exception $e) {
        return [];
    }
}

function updaterPendingMigrations(PDO $db, string $dir): array {
    $applied = updaterAppliedMigrations($db);
    $files = glob($dir . '/*.sql') ?: [];
    $pending = [];
    foreach ($files as $file) {
        $version = basename($file, '.sql');
        if (!in_array($version, $applied, true)) {
            $pending[] = ['version' => $version, 'file' => $file];
        }
    }
    usort($pending, function($a, $b) {
        return version_compare($a['version'], $b['version']);
    });
    return $pending;
}

function updaterSplitSql(string $sql): array {
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $sql = preg_replace('/^\s*#.*$/m', '', $sql);
    $statements = [];
    $buffer = '';
    $quote = null;
    $escape = false;
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $buffer .= $char;

        if ($escape) {
            $escape = false;
            continue;
        }
        if ($char === '\\') {
            $escape = true;
            continue;
        }
        if (($char === "'" || $char === '"') && $quote === null) {
            $quote = $char;
            continue;
        }
        if ($char === $quote) {
            $quote = null;
            continue;
        }
        if ($char === ';' && $quote === null) {
            $statement = trim(substr($buffer, 0, -1));
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
        }
    }

    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }
    return $statements;
}

function updaterRunSqlFile(PDO $db, string $path): int {
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('SQLファイルを読み込めません: ' . basename($path));
    }

    $count = 0;
    foreach (updaterSplitSql($sql) as $statement) {
        try {
            $db->exec($statement);
            $count++;
        } catch (PDOException $e) {
            $message = strtolower($e->getMessage());
            $ignore = [
                'duplicate column',
                'duplicate key',
                'already exists',
                'check that column/key exists',
            ];
            $canIgnore = false;
            foreach ($ignore as $needle) {
                if (strpos($message, $needle) !== false) {
                    $canIgnore = true;
                    break;
                }
            }
            if (!$canIgnore) {
                throw $e;
            }
        }
    }
    return $count;
}

function updaterNormalizeZipPath(string $name): string {
    $name = str_replace('\\', '/', $name);
    $name = ltrim($name, '/');
    while (strpos($name, './') === 0) {
        $name = substr($name, 2);
    }
    if (strpos($name, 'public/') === 0) {
        $name = substr($name, 7);
    }
    return $name;
}

function updaterIsProtectedPath(string $name): bool {
    $name = updaterNormalizeZipPath($name);
    if ($name === '' || substr($name, -1) === '/') {
        return true;
    }
    $protected = [
        'config/database.php',
        'config/installed.lock',
        'uploads/',
        'backups/',
        'error_log',
    ];
    foreach ($protected as $path) {
        if (substr($path, -1) === '/') {
            if (strpos($name, $path) === 0) {
                return true;
            }
        } elseif ($name === $path) {
            return true;
        }
    }
    return false;
}

function updaterMakeBackup(string $baseDir, string $backupDir): string {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchiveが利用できません。');
    }
    $backupPath = $backupDir . '/backup_' . date('Ymd_His') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('バックアップを作成できません。');
    }

    $baseReal = realpath($baseDir);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $file) {
        $path = $file->getPathname();
        $relative = str_replace('\\', '/', substr($path, strlen($baseReal) + 1));
        if (updaterIsProtectedPath($relative) || strpos($relative, '.git/') === 0) {
            continue;
        }
        if ($file->isDir()) {
            $zip->addEmptyDir($relative);
        } else {
            $zip->addFile($path, $relative);
        }
    }
    $zip->close();
    return $backupPath;
}

function updaterInstallZip(string $zipPath, string $baseDir, array &$log): void {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchiveが利用できません。');
    }

    $baseReal = realpath($baseDir);
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('ZIPファイルを開けません。');
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $rawName = $zip->getNameIndex($i);
        $name = updaterNormalizeZipPath($rawName);
        if ($name === '' || strpos($name, '..') !== false || updaterIsProtectedPath($name)) {
            $log[] = 'スキップ: ' . $rawName;
            continue;
        }

        $destination = $baseReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
        $destinationDir = dirname($destination);
        if (!is_dir($destinationDir) && !mkdir($destinationDir, 0755, true)) {
            throw new RuntimeException('ディレクトリを作成できません: ' . $name);
        }
        $dirReal = realpath($destinationDir);
        if ($dirReal === false || strpos($dirReal, $baseReal) !== 0) {
            $log[] = '安全でないパスをスキップ: ' . $rawName;
            continue;
        }

        if (substr($rawName, -1) === '/') {
            if (!is_dir($destination)) {
                mkdir($destination, 0755, true);
            }
            continue;
        }

        $contents = $zip->getFromIndex($i);
        if ($contents === false) {
            throw new RuntimeException('ZIP内のファイルを読み込めません: ' . $rawName);
        }
        file_put_contents($destination, $contents);
        $log[] = '更新: ' . $name;
    }
    $zip->close();
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrfToken($_POST['csrf_token'] ?? '');
        $action = $_POST['action'] ?? '';

        if ($action === 'migrate') {
            $pending = updaterPendingMigrations($db, $MIGRATIONS_DIR);
            foreach ($pending as $migration) {
                $count = updaterRunSqlFile($db, $migration['file']);
                $log[] = $migration['version'] . ': ' . $count . '件実行';
            }
            $message = $pending ? 'DBマイグレーションを適用しました。' : '未適用のマイグレーションはありません。';
        } elseif ($action === 'file_update') {
            if (empty($_FILES['update_zip']['tmp_name']) || !is_uploaded_file($_FILES['update_zip']['tmp_name'])) {
                throw new RuntimeException('更新ZIPファイルを選択してください。');
            }
            updaterMakeBackup($BASE_DIR, $BACKUP_DIR);
            updaterInstallZip($_FILES['update_zip']['tmp_name'], $BASE_DIR, $log);

            $pending = updaterPendingMigrations($db, $MIGRATIONS_DIR);
            foreach ($pending as $migration) {
                $count = updaterRunSqlFile($db, $migration['file']);
                $log[] = 'マイグレーション ' . $migration['version'] . ': ' . $count . '件実行';
            }
            $message = '更新が完了しました。';
        } elseif ($action === 'delete_backup') {
            $file = basename((string)($_POST['backup'] ?? ''));
            $path = $BACKUP_DIR . '/' . $file;
            if ($file !== '' && is_file($path) && preg_match('/^backup_\d{8}_\d{6}\.zip$/', $file)) {
                unlink($path);
                $message = 'バックアップを削除しました。';
            } else {
                throw new RuntimeException('不正なバックアップファイルです。');
            }
        }
    }
} catch (Throwable $e) {
    $message = $e->getMessage();
    $msgType = 'error';
}

$pendingMigrations = updaterPendingMigrations($db, $MIGRATIONS_DIR);
$appliedCount = count(updaterAppliedMigrations($db));
$backupFiles = glob($BACKUP_DIR . '/backup_*.zip') ?: [];
rsort($backupFiles);

$envCheck = [
    'BASE_DIR' => $BASE_DIR,
    'BASE_DIR 存在' => is_dir($BASE_DIR) ? 'OK' : 'NG',
    'BASE_DIR 書込' => is_writable($BASE_DIR) ? 'OK' : 'NG',
    'admin 書込' => is_writable(__DIR__) ? 'OK' : 'NG',
    'migrations 存在' => is_dir($MIGRATIONS_DIR) ? 'OK (' . count(glob($MIGRATIONS_DIR . '/*.sql') ?: []) . '件)' : 'NG',
    'upload_max' => ini_get('upload_max_filesize'),
    'post_max' => ini_get('post_max_size'),
    'ZipArchive' => class_exists('ZipArchive') ? 'OK' : 'NG',
];
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>">
        <?= h($message) ?>
    </div>
<?php endif; ?>

<div class="card">
    <h3>環境チェック</h3>
    <table>
        <?php foreach ($envCheck as $key => $value): ?>
            <tr>
                <th><?= h($key) ?></th>
                <td><?= h((string)$value) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">現在のバージョン</div>
        <div class="stat-value"><?= h($SYSTEM_VERSION) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">適用済みMigration</div>
        <div class="stat-value"><?= h((string)$appliedCount) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">未適用Migration</div>
        <div class="stat-value"><?= h((string)count($pendingMigrations)) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">バックアップ数</div>
        <div class="stat-value"><?= h((string)count($backupFiles)) ?></div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <h3>ファイル更新（ZIP）</h3>
        <p>ZIPをアップロードするとシステムファイルを上書き更新します。更新前に自動バックアップを作成します。</p>
        <p><strong>保護対象:</strong> config/database.php, config/installed.lock, uploads/, backups/</p>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="file_update">
            <div class="form-group">
                <label>更新ZIPファイル</label>
                <input type="file" name="update_zip" accept=".zip" required>
            </div>
            <button type="submit" class="btn btn-primary" onclick="return confirm('このZIPを適用しますか？');">更新を適用</button>
        </form>
    </div>

    <div class="card">
        <h3>DBマイグレーション</h3>
        <?php if ($pendingMigrations): ?>
            <ul>
                <?php foreach ($pendingMigrations as $migration): ?>
                    <li><?= h($migration['version']) ?></li>
                <?php endforeach; ?>
            </ul>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="migrate">
                <button type="submit" class="btn btn-primary" onclick="return confirm('未適用のDBマイグレーションを適用しますか？');">マイグレーションを適用</button>
            </form>
        <?php else: ?>
            <p>すべてのマイグレーションは適用済みです。</p>
        <?php endif; ?>
    </div>
</div>

<?php if ($log): ?>
    <div class="card">
        <h3>更新ログ</h3>
        <pre style="white-space:pre-wrap;max-height:320px;overflow:auto;"><?= h(implode("\n", $log)) ?></pre>
    </div>
<?php endif; ?>

<div class="card">
    <h3>バックアップ</h3>
    <?php if (!$backupFiles): ?>
        <p>バックアップファイルはありません。</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ファイル</th>
                    <th>サイズ</th>
                    <th>作成日時</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($backupFiles as $file): ?>
                    <tr>
                        <td><?= h(basename($file)) ?></td>
                        <td><?= h(number_format((float)filesize($file) / 1024 / 1024, 2)) ?> MB</td>
                        <td><?= h(date('Y-m-d H:i:s', filemtime($file))) ?></td>
                        <td>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="delete_backup">
                                <input type="hidden" name="backup" value="<?= h(basename($file)) ?>">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('このバックアップを削除しますか？');">削除</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

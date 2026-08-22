<?php

declare(strict_types=1);

require __DIR__ . '/../includes/shared_bootstrap.php';

use SenNoKuni\Agency\RecruitmentLinkCsvExportService;
use SenNoKuni\Agency\SubAgentCsvExportService;
use SenNoKuni\Audit\LoginLogCsvExportService;
use SenNoKuni\Lead\LeadCsvExportService;
use SenNoKuni\Reporting\TemplateReportCsvExportService;

$dsn = getenv('CSV_CONTRACT_DSN') ?: '';
$user = getenv('CSV_CONTRACT_USER') ?: '';
$pass = getenv('CSV_CONTRACT_PASS') ?: '';

if ($dsn === '') {
    echo "CSV contract tests skipped: set CSV_CONTRACT_DSN, CSV_CONTRACT_USER, and CSV_CONTRACT_PASS.\n";
    exit(0);
}

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    createSchema($db);
    seedFixtures($db);

    assertLeadCsvContracts($db);
    assertSubAgentCsvContract($db);
    assertRecruitmentLinkCsvContract($db);
    assertTemplateReportCsvContract($db);
    assertLoginLogCsvContract($db);

    echo "CSV contract tests passed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "CSV contract tests failed: " . $e->getMessage() . "\n");
    exit(1);
}

function createSchema(PDO $db): void
{
    $statements = [
        "CREATE TEMPORARY TABLE agents (
            id INT PRIMARY KEY,
            agent_code VARCHAR(64) NOT NULL,
            agent_name VARCHAR(255) NOT NULL,
            person_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(64) NOT NULL,
            parent_id INT NULL,
            level INT NOT NULL,
            status VARCHAR(32) NOT NULL,
            created_at DATETIME NOT NULL,
            default_template_id INT NULL
        )",
        "CREATE TEMPORARY TABLE admins (
            id INT PRIMARY KEY,
            username VARCHAR(255) NOT NULL,
            display_name VARCHAR(255) NOT NULL,
            role VARCHAR(64) NOT NULL
        )",
        "CREATE TEMPORARY TABLE access_logs (
            id INT PRIMARY KEY,
            agent_id INT NOT NULL,
            template_id INT NULL,
            type VARCHAR(32) NOT NULL,
            created_at DATETIME NOT NULL
        )",
        "CREATE TEMPORARY TABLE leads (
            id INT PRIMARY KEY,
            agent_id INT NOT NULL,
            template_id INT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(64) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(32) NOT NULL,
            next_action_at DATETIME NULL,
            internal_note TEXT NULL,
            created_at DATETIME NOT NULL
        )",
        "CREATE TEMPORARY TABLE recruitment_links (
            id INT PRIMARY KEY,
            agent_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            token VARCHAR(255) NOT NULL,
            target_level INT NOT NULL,
            position_type VARCHAR(64) NULL,
            position_label VARCHAR(255) NULL,
            click_count INT NOT NULL DEFAULT 0,
            status VARCHAR(32) NOT NULL,
            expires_at DATETIME NULL,
            created_at DATETIME NOT NULL
        )",
        "CREATE TEMPORARY TABLE applicants (
            id INT PRIMARY KEY,
            recruitment_link_id INT NOT NULL,
            status VARCHAR(32) NOT NULL
        )",
        "CREATE TEMPORARY TABLE lp_templates (
            id INT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            html_file VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL
        )",
        "CREATE TEMPORARY TABLE login_logs (
            id INT PRIMARY KEY,
            user_type VARCHAR(32) NOT NULL,
            user_id INT NULL,
            email VARCHAR(255) NOT NULL,
            success TINYINT NOT NULL,
            ip_hash VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL
        )",
    ];

    foreach ($statements as $statement) {
        $db->exec($statement);
    }
}

function seedFixtures(PDO $db): void
{
    $db->exec("
        INSERT INTO lp_templates (id, name, slug, html_file, sort_order) VALUES
        (1, 'Template One', 'template-one', 'template-one.php', 1),
        (2, 'Template Two', 'template-two', 'template-two.php', 2)
    ");

    $db->exec("
        INSERT INTO agents (id, agent_code, agent_name, person_name, email, phone, parent_id, level, status, created_at, default_template_id) VALUES
        (1, 'agent_root', 'Root Agent', 'Root Person', 'root@example.com', '000', NULL, 3, 'active', '2026-07-01 00:00:00', 1),
        (2, 'dir_001', 'Director One', 'Director Person', 'dir@example.com', '111', 1, 2, 'active', '2026-07-02 00:00:00', 1),
        (3, 'adv_001', 'Advisor One', 'Advisor Person', 'adv@example.com', '222', 2, 1, 'active', '2026-07-03 00:00:00', 2)
    ");

    $db->exec("
        INSERT INTO admins (id, username, display_name, role) VALUES
        (1, 'admin01', 'Admin One', 'super_admin')
    ");

    $db->exec("
        INSERT INTO access_logs (id, agent_id, template_id, type, created_at) VALUES
        (1, 3, 2, 'pv', '2026-07-10 10:00:00'),
        (2, 3, 2, 'pv', '2026-07-10 10:05:00'),
        (3, 3, 2, 'line_click', '2026-07-10 10:10:00')
    ");

    $db->exec("
        INSERT INTO leads (id, agent_id, template_id, name, email, phone, message, status, next_action_at, internal_note, created_at) VALUES
        (1, 3, 2, 'Lead One', 'lead@example.com', '090', 'hello', 'new', '2026-07-20 00:00:00', 'note', '2026-07-10 11:00:00')
    ");

    $db->exec("
        INSERT INTO recruitment_links (id, agent_id, name, token, target_level, position_type, position_label, click_count, status, expires_at, created_at) VALUES
        (1, 2, 'Advisor Invite', 'token123', 1, 'influencer', 'Influencer', 7, 'active', '2026-12-31 23:59:59', '2026-07-11 00:00:00')
    ");

    $db->exec("
        INSERT INTO applicants (id, recruitment_link_id, status) VALUES
        (1, 1, 'pending'),
        (2, 1, 'approved')
    ");

    $db->exec("
        INSERT INTO login_logs (id, user_type, user_id, email, success, ip_hash, created_at) VALUES
        (1, 'agent', 3, 'adv@example.com', 1, 'hash-agent', '2026-07-12 12:00:00'),
        (2, 'admin', 1, 'admin@example.com', 0, 'hash-admin', '2026-07-12 13:00:00')
    ");
}

function assertLeadCsvContracts(PDO $db): void
{
    $service = new LeadCsvExportService($db);
    $labels = ['new' => '未対応', 'prospect' => '見込み', 'won' => '成約'];

    $adminRows = $service->adminRows(['status' => 'new', 'agent_id' => 3, 'q' => 'Lead'], $labels);
    assertCountValue(1, $adminRows, 'admin lead row count');
    assertSameValue('Advisor One', $adminRows[0][1], 'admin lead source name');
    assertSameValue('未対応', $adminRows[0][7], 'admin lead status label');
    assertSameValue('note', $adminRows[0][9], 'admin lead internal note');

    $agentRows = $service->agentRows([3], 'new', $labels);
    assertCountValue(1, $agentRows, 'agent lead row count');
    assertSameValue('adv_001', $agentRows[0][2], 'agent lead source code');
}

function assertSubAgentCsvContract(PDO $db): void
{
    $service = new SubAgentCsvExportService($db);
    $rows = $service->rows(2, 2, 'advisors', [2, 3], [1 => 'アドバイザー', 2 => 'ディレクター', 3 => 'エージェント']);

    assertCountValue(1, $rows, 'sub-agent row count');
    assertSameValue('アドバイザー', $rows[0][0], 'sub-agent level label');
    assertSameValue(2, $rows[0][7], 'sub-agent pv count');
    assertSameValue(1, $rows[0][8], 'sub-agent lead count');
}

function assertRecruitmentLinkCsvContract(PDO $db): void
{
    $service = new RecruitmentLinkCsvExportService($db);
    $rows = $service->rows(
        2,
        'https://sengoku-ai.example',
        [1 => 'アドバイザー', 2 => 'ディレクター', 3 => 'エージェント'],
        static fn(mixed $type, mixed $label): string => (string)($label ?: $type)
    );

    assertCountValue(1, $rows, 'recruitment-link row count');
    assertSameValue('Advisor Invite', $rows[0][0], 'recruitment-link name');
    assertSameValue('https://sengoku-ai.example/join/token123', $rows[0][2], 'recruitment-link url');
    assertSameValue(1, $rows[0][5], 'recruitment-link pending count');
    assertSameValue(1, $rows[0][6], 'recruitment-link approved count');
}

function assertTemplateReportCsvContract(PDO $db): void
{
    $service = new TemplateReportCsvExportService($db);
    $rows = $service->rows(null);

    assertCountValue(2, $rows, 'template report row count');
    assertSameValue('Template One', $rows[0][0], 'template report first name');
    assertSameValue(2, $rows[1][4], 'template report pv count');
    assertSameValue(1, $rows[1][5], 'template report line count');
    assertSameValue(1, $rows[1][6], 'template report lead count');
    assertSameValue('50%', $rows[1][10], 'template report cv rate');
}

function assertLoginLogCsvContract(PDO $db): void
{
    $service = new LoginLogCsvExportService($db);

    $agentRows = $service->rows(['user_type' => 'agent', 'result' => 'success', 'q' => 'Advisor', 'from' => '2026-07-12', 'to' => '2026-07-12']);
    assertCountValue(1, $agentRows, 'agent login-log row count');
    assertSameValue('代理店', $agentRows[0][1], 'agent login-log type');
    assertSameValue('Advisor One', $agentRows[0][2], 'agent login-log display name');
    assertSameValue('成功', $agentRows[0][5], 'agent login-log result');

    $adminRows = $service->rows(['user_type' => 'admin', 'result' => 'failed']);
    assertCountValue(1, $adminRows, 'admin login-log row count');
    assertSameValue('管理者', $adminRows[0][1], 'admin login-log type');
    assertSameValue('失敗', $adminRows[0][5], 'admin login-log result');
}

function assertSameValue(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($label . ' expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

/**
 * @param array<mixed> $actual
 */
function assertCountValue(int $expected, array $actual, string $label): void
{
    $count = count($actual);
    if ($expected !== $count) {
        throw new RuntimeException($label . ' expected ' . $expected . ', got ' . $count);
    }
}

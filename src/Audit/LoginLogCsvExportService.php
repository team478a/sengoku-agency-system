<?php

declare(strict_types=1);

namespace SenNoKuni\Audit;

use PDO;

final class LoginLogCsvExportService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<int, mixed>>
     */
    public function rows(array $filters): array
    {
        $userType = (string)($filters['user_type'] ?? '');
        $result = (string)($filters['result'] ?? '');
        $search = (string)($filters['q'] ?? '');
        $from = (string)($filters['from'] ?? '');
        $to = (string)($filters['to'] ?? '');

        $wheres = [];
        $params = [];

        if (in_array($userType, ['admin', 'agent'], true)) {
            $wheres[] = 'l.user_type=?';
            $params[] = $userType;
        }
        if ($result === 'success') {
            $wheres[] = 'l.success=1';
        } elseif ($result === 'failed') {
            $wheres[] = 'l.success=0';
        }
        if ($search !== '') {
            $wheres[] = '(l.email LIKE ? OR a.agent_name LIKE ? OR a.person_name LIKE ? OR a.agent_code LIKE ? OR ad.username LIKE ? OR ad.display_name LIKE ?)';
            $keyword = '%' . $search . '%';
            array_push($params, $keyword, $keyword, $keyword, $keyword, $keyword, $keyword);
        }
        if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $wheres[] = 'l.created_at >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $wheres[] = 'l.created_at <= ?';
            $params[] = $to . ' 23:59:59';
        }

        $where = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';
        $stmt = $this->db->prepare("
            SELECT l.*, a.agent_name, a.person_name, a.agent_code, a.level AS agent_level,
                   ad.username AS admin_username, ad.display_name AS admin_display_name, ad.role AS admin_role
            FROM login_logs l
            LEFT JOIN agents a ON l.user_type='agent' AND l.user_id=a.id
            LEFT JOIN admins ad ON l.user_type='admin' AND l.user_id=ad.id
            $where
            ORDER BY l.created_at DESC
        ");
        $stmt->execute($params);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $log) {
            $displayName = $log['user_type'] === 'admin'
                ? (($log['admin_display_name'] ?? '') ?: ($log['admin_username'] ?? ''))
                : (($log['agent_name'] ?? '') ?: ($log['person_name'] ?? ''));

            $rows[] = [
                $log['created_at'] ?? '',
                $log['user_type'] === 'admin' ? '管理者' : '代理店',
                $displayName,
                $log['email'] ?? '',
                $log['user_type'] === 'admin' ? ($log['admin_role'] ?? '') : ($log['agent_code'] ?? ''),
                !empty($log['success']) ? '成功' : '失敗',
                $log['ip_hash'] ?? '',
            ];
        }

        return $rows;
    }
}

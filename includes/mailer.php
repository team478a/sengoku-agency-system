<?php
/**
 * Resend APIを使ったメール送信クラス
 */
class Mailer {

    private string $apiKey;
    private string $from;
    private string $fromName;

    public function __construct() {
        $db = getDB();
        $settings = [];
        $rows = $db->query("SELECT key_name, value FROM system_settings")->fetchAll();
        foreach ($rows as $row) {
            $settings[$row['key_name']] = $row['value'];
        }
        $this->apiKey = $settings['resend_api_key'] ?? '';
        $this->from = $settings['mail_from'] ?? '';
        $this->fromName = $settings['mail_from_name'] ?? '戦国経済圏';
    }

    public function send(string $to, string $subject, string $html, string $text = ''): bool {
        if (empty($this->apiKey) || empty($this->from)) {
            error_log('Mailer: APIキーまたは送信元メールが未設定です。');
            return false;
        }

        $payload = [
            'from' => $this->fromName . ' <' . $this->from . '>',
            'to' => [$to],
            'subject' => $subject,
            'html' => $html,
        ];
        if ($text !== '') {
            $payload['text'] = $text;
        }

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('Mailer curl error: ' . $error);
            return false;
        }

        if (!in_array($httpCode, [200, 202], true)) {
            error_log('Mailer API error: HTTP ' . $httpCode . ' / ' . $response);
            return false;
        }

        return true;
    }

    public function sendApplicationNotice(array $applicant, string $adminEmail): bool {
        $targetLabel = $this->applicantTargetLabel($applicant);
        $adminUrl = $this->getSiteUrl() . '/admin/applicants.php';
        $subject = '【戦国経済圏】新規' . $targetLabel . '申請が届きました';

        $html = $this->wrapHtml($subject, '
            <p>新しい' . h($targetLabel) . '申請が届きました。管理画面から内容を確認してください。</p>
            ' . $this->infoTable([
                '会社名・屋号' => $applicant['company_name'] ?? '',
                '担当者名' => $applicant['person_name'] ?? '',
                'メール' => $applicant['email'] ?? '',
                '電話' => ($applicant['phone'] ?? '') ?: '未入力',
                '申請区分' => $targetLabel,
                'メッセージ' => ($applicant['message'] ?? '') ?: '未入力',
            ]) . '
            <div style="text-align:center;margin:2rem 0;">
                <a href="' . h($adminUrl) . '" style="display:inline-block;padding:.8rem 2rem;background:#C9A84C;color:#13100D;font-weight:700;border-radius:4px;text-decoration:none;">管理画面で確認する</a>
            </div>
        ');

        return $this->send($adminEmail, $subject, $html);
    }

    public function sendApprovalNotice(array $agent, string $setupUrl): bool {
        $siteUrl = $this->getSiteUrl();
        $lpUrl = $siteUrl . '/a/' . ($agent['agent_code'] ?? '');
        $mypageUrl = $siteUrl . '/agent/login.php';
        $manualUrl = $siteUrl . '/manual';
        $roleLabel = $this->agentRoleLabel($agent);

        $subject = $this->getTpl('mail_tpl_approval_subject', '【戦国経済圏】' . $roleLabel . 'として承認されました');
        $body = $this->getTpl('mail_tpl_approval_body', '');
        if ($body === '') {
            $body = "{person_name} 様\n\n"
                  . "申請が承認され、{role_label}として登録されました。\n"
                  . "以下のURLからパスワードを設定してログインしてください。\n\n"
                  . "初回設定URL：{setup_url}\n"
                  . "LP URL：{lp_url}\n"
                  . "マイページ：{mypage_url}\n";
        }

        $vars = [
            '{person_name}' => $agent['person_name'] ?? '',
            '{agent_name}' => $agent['agent_name'] ?? '',
            '{agent_code}' => $agent['agent_code'] ?? '',
            '{role_label}' => $roleLabel,
            '{setup_url}' => $setupUrl,
            '{lp_url}' => $lpUrl,
            '{mypage_url}' => $mypageUrl,
            '{manual_url}' => $manualUrl,
        ];
        $subject = str_replace(array_keys($vars), array_values($vars), $subject);
        $body = str_replace(array_keys($vars), array_values($vars), $body);
        $bodyHtml = $this->linkifyUrl(nl2br(h($body)), $setupUrl);

        $html = $this->wrapHtml($subject,
            '<p style="white-space:pre-line;line-height:1.9;">' . $bodyHtml . '</p>
            <div style="text-align:center;margin:2rem 0;">
                <a href="' . h($setupUrl) . '" style="display:inline-block;padding:.85rem 2rem;background:#C9A84C;color:#13100D;font-weight:700;border-radius:4px;text-decoration:none;">パスワードを設定してログイン →</a>
            </div>'
        );

        return $this->send($agent['email'], $subject, $html);
    }

    public function sendRejectionNotice(array $applicant): bool {
        $targetLabel = $this->applicantTargetLabel($applicant);
        $subject = $this->getTpl('mail_tpl_rejection_subject', '【戦国経済圏】' . $targetLabel . '申請について');
        $body = $this->getTpl('mail_tpl_rejection_body', '');
        if ($body === '') {
            $body = "{person_name} 様\n\n"
                  . "このたびは{role_label}へお申し込みいただき、ありがとうございました。\n"
                  . "確認の結果、今回は申請を見送らせていただくことになりました。";
        }
        $vars = [
            '{person_name}' => $applicant['person_name'] ?? '',
            '{role_label}' => $targetLabel,
        ];
        $subject = str_replace(array_keys($vars), array_values($vars), $subject);
        $body = str_replace(array_keys($vars), array_values($vars), $body);
        $html = $this->wrapHtml($subject, '<p style="white-space:pre-line;line-height:1.9;">' . nl2br(h($body)) . '</p>');
        return $this->send($applicant['email'], $subject, $html);
    }

    public function sendPromotionRequestNotice(array $applicant, array $approver, string $message): bool {
        $siteUrl = $this->getSiteUrl();
        $mypageUrl = $siteUrl . '/agent/promotion_requests.php';
        $labels = function_exists('getLevelLabels') ? getLevelLabels() : [1 => 'アドバイザー', 2 => 'ディレクター', 3 => 'エージェント'];

        $subject = $this->getTpl('mail_tpl_promo_request_subject', '【戦国経済圏】昇格申請が届きました');
        $body = $this->getTpl('mail_tpl_promo_request_body', '');
        if ($body === '') {
            $body = "{person_name} さんから昇格申請が届きました。\n\nメッセージ：\n{message}\n\n{mypage_url}";
        }
        $vars = [
            '{person_name}' => $applicant['person_name'] ?? '',
            '{agent_code}' => $applicant['agent_code'] ?? '',
            '{message}' => $message ?: '（メッセージなし）',
            '{mypage_url}' => $mypageUrl,
            '{label_level1}' => $labels[1] ?? 'アドバイザー',
            '{label_level2}' => $labels[2] ?? 'ディレクター',
            '{label_level3}' => $labels[3] ?? 'エージェント',
        ];
        $body = str_replace(array_keys($vars), array_values($vars), $body);
        $subject = str_replace(array_keys($vars), array_values($vars), $subject);
        $html = $this->wrapHtml($subject,
            '<p style="white-space:pre-line;line-height:1.9;">' . nl2br(h($body)) . '</p>
            <div style="text-align:center;margin:2rem 0;">
                <a href="' . h($mypageUrl) . '" style="display:inline-block;padding:.85rem 2rem;background:#C9A84C;color:#13100D;font-weight:700;border-radius:4px;text-decoration:none;">申請を確認する →</a>
            </div>'
        );
        return $this->send($approver['email'], $subject, $html);
    }

    public function sendPromotionNotice(array $agent): bool {
        $siteUrl = $this->getSiteUrl();
        $lpUrl = $siteUrl . '/a/' . ($agent['agent_code'] ?? '');
        $mypageUrl = $siteUrl . '/agent/login.php';
        $labels = function_exists('getLevelLabels') ? getLevelLabels() : [1 => 'アドバイザー', 2 => 'ディレクター', 3 => 'エージェント'];
        $roleLabel = $this->agentRoleLabel($agent);

        $subject = $this->getTpl('mail_tpl_promotion_subject', '【戦国経済圏】' . $roleLabel . 'に昇格しました');
        $body = $this->getTpl('mail_tpl_promotion_body', '');
        if ($body === '') {
            $body = "{person_name} 様\n\nあなたの区分が{role_label}に変更されました。\n\nLP URL：{lp_url}\nマイページ：{mypage_url}";
        }
        $vars = [
            '{person_name}' => $agent['person_name'] ?? '',
            '{agent_code}' => $agent['agent_code'] ?? '',
            '{role_label}' => $roleLabel,
            '{lp_url}' => $lpUrl,
            '{mypage_url}' => $mypageUrl,
            '{label_level1}' => $labels[1] ?? 'アドバイザー',
            '{label_level2}' => $labels[2] ?? 'ディレクター',
            '{label_level3}' => $labels[3] ?? 'エージェント',
        ];
        $body = str_replace(array_keys($vars), array_values($vars), $body);
        $subject = str_replace(array_keys($vars), array_values($vars), $subject);
        $html = $this->wrapHtml($subject,
            '<p style="white-space:pre-line;line-height:1.9;">' . nl2br(h($body)) . '</p>
            <div style="text-align:center;margin:2rem 0;">
                <a href="' . h($mypageUrl) . '" style="display:inline-block;padding:.85rem 2rem;background:#C9A84C;color:#13100D;font-weight:700;border-radius:4px;text-decoration:none;">マイページにログイン →</a>
            </div>'
        );
        return $this->send($agent['email'], $subject, $html);
    }

    public function sendRoleChangeNotice(array $agent, string $setupUrl = ''): bool {
        $siteUrl = $this->getSiteUrl();
        $lpUrl = $siteUrl . '/a/' . ($agent['agent_code'] ?? '');
        $mypageUrl = $siteUrl . '/agent/login.php';
        $roleLabel = $this->agentRoleLabel($agent);

        $subject = '【戦国経済圏】権限が変更されました';
        $body = ($agent['person_name'] ?? '') . " 様\n\n"
              . "管理者により、あなたの区分が「{$roleLabel}」に変更されました。\n\n"
              . "LP URL：{$lpUrl}\n"
              . "マイページ：{$mypageUrl}\n";
        if ($setupUrl !== '') {
            $body .= "\nパスワードの設定・再設定が必要な場合は、以下のURLから設定してください。\n{$setupUrl}\n";
        }

        $bodyHtml = nl2br(h($body));
        if ($setupUrl !== '') {
            $bodyHtml = $this->linkifyUrl($bodyHtml, $setupUrl);
        }

        $buttonUrl = $setupUrl !== '' ? $setupUrl : $mypageUrl;
        $buttonText = $setupUrl !== '' ? 'パスワードを設定する →' : 'マイページにログイン →';
        $html = $this->wrapHtml($subject,
            '<p style="white-space:pre-line;line-height:1.9;">' . $bodyHtml . '</p>
            <div style="text-align:center;margin:2rem 0;">
                <a href="' . h($buttonUrl) . '" style="display:inline-block;padding:.85rem 2rem;background:#C9A84C;color:#13100D;font-weight:700;border-radius:4px;text-decoration:none;">' . h($buttonText) . '</a>
            </div>'
        );
        return $this->send($agent['email'], $subject, $html);
    }

    public function sendPromotionRecommendNotice(array $applicant, array $approver, string $comment): bool {
        $siteUrl = $this->getSiteUrl();
        $adminUrl = $siteUrl . '/admin/promotion_requests.php';

        try {
            $db = getDB();
            $rows = $db->query("SELECT key_name, value FROM system_settings WHERE key_name IN ('admin_email','label_level2','label_level3')")->fetchAll();
            $cfg = [];
            foreach ($rows as $r) $cfg[$r['key_name']] = $r['value'];
        } catch (Exception $e) {
            $cfg = [];
        }

        $adminEmail = $cfg['admin_email'] ?? '';
        if (!$adminEmail) return false;

        $label2 = $cfg['label_level2'] ?? 'ディレクター';
        $label3 = $cfg['label_level3'] ?? 'エージェント';
        $subject = '【戦国経済圏】昇格推薦が届きました（要承認）';
        $html = $this->wrapHtml($subject, '
            <p style="margin-bottom:1.5rem;">' . h($approver['person_name'] ?? '') . ' エージェントから昇格推薦が届きました。管理画面で最終承認を行ってください。</p>
            ' . $this->infoTable([
                '申請者' => ($applicant['person_name'] ?? '') . '（' . ($applicant['agent_name'] ?? '') . '）',
                '現在の区分' => $label2 . ' → ' . $label3,
                '推薦者' => ($approver['person_name'] ?? '') . '（' . ($approver['agent_name'] ?? '') . '）',
                '推薦コメント' => $comment ?: '（なし）',
            ]) . '
            <div style="text-align:center;margin:2rem 0;">
                <a href="' . h($adminUrl) . '" style="display:inline-block;padding:.85rem 2rem;background:#C9A84C;color:#13100D;font-weight:700;border-radius:4px;text-decoration:none;">管理画面で承認する →</a>
            </div>
        ');
        return $this->send($adminEmail, $subject, $html);
    }

    private function agentRoleLabel(array $agent): string {
        $level = (int)($agent['level'] ?? 1);
        if ($level === 1 && function_exists('getAdvisorPositionLabel')) {
            return getAdvisorPositionLabel($agent['position_type'] ?? null, $agent['position_label'] ?? null);
        }
        $labels = function_exists('getLevelLabels') ? getLevelLabels() : [1 => 'アドバイザー', 2 => 'ディレクター', 3 => 'エージェント'];
        return $labels[$level] ?? 'メンバー';
    }

    private function applicantTargetLabel(array $applicant): string {
        $level = (int)($applicant['target_level'] ?? 1);
        if ($level === 1 && function_exists('getAdvisorPositionLabel')) {
            return getAdvisorPositionLabel($applicant['position_type'] ?? null, $applicant['position_label'] ?? null);
        }
        $labels = function_exists('getLevelLabels') ? getLevelLabels() : [1 => 'アドバイザー', 2 => 'ディレクター', 3 => 'エージェント'];
        return $labels[$level] ?? 'アドバイザー';
    }

    private function infoTable(array $rows): string {
        $html = '<table style="width:100%;border-collapse:collapse;margin:1.5rem 0;">';
        foreach ($rows as $label => $value) {
            $html .= '<tr><td style="padding:.6rem 1rem;border-bottom:1px solid #e5e7eb;color:#6b7280;width:130px;vertical-align:top;">' . h($label) . '</td>'
                  . '<td style="padding:.6rem 1rem;border-bottom:1px solid #e5e7eb;font-weight:600;white-space:pre-line;">' . nl2br(h((string)$value)) . '</td></tr>';
        }
        $html .= '</table>';
        return $html;
    }

    private function linkifyUrl(string $html, string $url): string {
        return preg_replace(
            '/(' . preg_quote(h($url), '/') . ')/',
            '<a href="' . h($url) . '" style="color:#C9A84C;word-break:break-all;">$1</a>',
            $html
        );
    }

    private function getTpl(string $key, string $default): string {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT value FROM system_settings WHERE key_name = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            return ($row && $row['value'] !== '') ? $row['value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }

    private function wrapHtml(string $title, string $body): string {
        return '<!DOCTYPE html>
<html lang="ja">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f5f0e8;font-family:\'Hiragino Sans\',\'Noto Sans JP\',sans-serif;font-size:14px;color:#1a1410;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f0e8;padding:2rem 1rem;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
    <tr><td style="background:linear-gradient(135deg,#13100D,#1a1510);padding:1.5rem 2rem;text-align:center;">
        <p style="font-family:serif;font-size:1.1rem;font-weight:700;color:#E2C87A;letter-spacing:.1em;margin:0;">戦国経済圏</p>
    </td></tr>
    <tr><td style="padding:2rem;">
        <h1 style="font-size:1.1rem;font-weight:700;color:#13100D;margin:0 0 1.5rem;padding-bottom:1rem;border-bottom:2px solid #C9A84C;">' . h($title) . '</h1>'
        . $body .
    '</td></tr>
    <tr><td style="background:#f9f7f3;padding:1rem 2rem;text-align:center;font-size:.75rem;color:#9ca3af;">
        &copy; 戦国経済圏 &nbsp;|&nbsp; このメールに心当たりがない場合は破棄してください。
    </td></tr>
</table>
</td></tr>
</table>
</body></html>';
    }

    private function getSiteUrl(): string {
        $db = getDB();
        $row = $db->query("SELECT value FROM system_settings WHERE key_name='site_url'")->fetch();
        $url = $row['value'] ?? '';
        if (empty($url)) {
            $url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
        }
        return rtrim($url, '/');
    }
}

<?php

declare(strict_types=1);

namespace SenNoKuni\Shared\Auth;

use Closure;
use PDO;
use Throwable;

final class ApiKeyAuthenticator
{
    /**
     * @param Closure(string, string=): string $settingReader
     * @param Closure(string, string): bool $columnChecker
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly Closure $settingReader,
        private readonly Closure $columnChecker,
    ) {
    }

    /**
     * @param array<string, mixed> $headers
     * @param array<string, mixed> $server
     * @param array<string, mixed> $query
     */
    public function extractRequestKey(array $headers, array $server, array $query = []): string
    {
        $apiKey = $headers['x-api-key']
            ?? $headers['X-API-Key']
            ?? $server['HTTP_X_API_KEY']
            ?? '';
        if (trim((string)$apiKey) !== '') {
            return trim((string)$apiKey);
        }

        $auth = $headers['Authorization']
            ?? $headers['authorization']
            ?? $server['HTTP_AUTHORIZATION']
            ?? '';
        if (preg_match('/Bearer\s+(.+)/i', (string)$auth, $matches)) {
            return trim($matches[1]);
        }

        return trim((string)($server['HTTP_X_API_TOKEN'] ?? ($query['token'] ?? '')));
    }

    public function hasConfiguredKey(): bool
    {
        if (trim(($this->settingReader)('external_api_token', '')) !== '') {
            return true;
        }

        try {
            if (!$this->hasPartnerKeyColumn()) {
                return false;
            }
            $count = (int)$this->pdo->query("
                SELECT COUNT(*)
                FROM external_partner_sites
                WHERE status='active' AND COALESCE(inbound_api_key, '') <> ''
            ")->fetchColumn();
            return $count > 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function authenticate(string $requestKey, string $remoteIp = '', bool $enforcePartnerRestrictions = false): ApiAuthenticationResult
    {
        if ($requestKey === '') {
            return ApiAuthenticationResult::failure('API_KEY_REQUIRED', 'x-api-key or Authorization: Bearer header is required.', 401);
        }

        $legacyKey = trim(($this->settingReader)('external_api_token', ''));
        if ($legacyKey !== '' && hash_equals($legacyKey, $requestKey)) {
            return ApiAuthenticationResult::legacy();
        }

        try {
            $partner = $this->partnerByKey($requestKey);
            if ($partner === null) {
                return ApiAuthenticationResult::failure('INVALID_API_KEY', 'API key is invalid.', 401);
            }

            if ($enforcePartnerRestrictions) {
                $expiry = trim((string)($partner['api_key_expires_at'] ?? ''));
                if ($expiry !== '' && strtotime($expiry) <= time()) {
                    return ApiAuthenticationResult::failure('API_KEY_EXPIRED', 'API key is expired.', 401);
                }

                $allowlist = trim((string)($partner['inbound_ip_allowlist'] ?? ''));
                if ($allowlist !== '' && !(new ApiIpRestriction())->isAllowed($remoteIp, $allowlist)) {
                    return ApiAuthenticationResult::failure('IP_NOT_ALLOWED', 'This IP address is not allowed for the API key.', 403);
                }
            }

            return ApiAuthenticationResult::partner($partner);
        } catch (Throwable) {
            return ApiAuthenticationResult::failure('AUTH_CHECK_FAILED', 'Failed to verify API key.', 500);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function partnerByKey(string $requestKey): ?array
    {
        if ($requestKey === '' || !$this->hasPartnerKeyColumn()) {
            return null;
        }

        $stmt = $this->pdo->query("
            SELECT *
            FROM external_partner_sites
            WHERE status='active' AND COALESCE(inbound_api_key, '') <> ''
            ORDER BY id ASC
        ");

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $partner) {
            if (hash_equals((string)($partner['inbound_api_key'] ?? ''), $requestKey)) {
                return $partner;
            }
        }

        return null;
    }

    private function hasPartnerKeyColumn(): bool
    {
        return ($this->columnChecker)('external_partner_sites', 'inbound_api_key');
    }
}

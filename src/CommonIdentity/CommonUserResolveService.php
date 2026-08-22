<?php

declare(strict_types=1);

namespace SenNoKuni\CommonIdentity;

final class CommonUserResolveService
{
    public function __construct(
        private readonly CommonUserInputNormalizer $normalizer,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $auth
     */
    public function inputSystemKey(array $data, array $auth): string
    {
        return $this->normalizer->normalize($data, (string)($auth['site_key'] ?? ''))->systemKey;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $auth
     * @return array<string, mixed>
     */
    public function resolve(array $data, array $auth, ?int $agentId): array
    {
        $db = \getDB();
        $input = $this->normalizer->normalize($data, (string)($auth['site_key'] ?? ''));
        $systemKey = $input->systemKey;
        $externalUserId = $input->externalUserId;
        $commonUserId = $input->commonUserId;
        $created = false;
        $matchedBy = null;
        $unverifiedIdentityCandidateCount = 0;

        if ($commonUserId !== '') {
            $stmt = $db->prepare("SELECT common_user_id FROM common_users WHERE common_user_id=? LIMIT 1");
            $stmt->execute([$commonUserId]);
            if ($stmt->fetchColumn()) {
                $matchedBy = 'common_user_id';
            }
        }

        if ($matchedBy === null && $systemKey !== '' && $externalUserId !== '') {
            $link = \findSystemAccountLink($systemKey, $externalUserId);
            if ($link) {
                $commonUserId = (string)$link['common_user_id'];
                $matchedBy = 'system_account_link';
            } else {
                $mapping = \findCommonUserMapping($systemKey, $externalUserId);
                if ($mapping) {
                    $commonUserId = (string)$mapping['common_user_id'];
                    $matchedBy = 'service_user_mapping';
                }
            }
        }

        $identityChecks = $input->identityChecks;
        foreach ($identityChecks as [$type, $value, $provider]) {
            if ($matchedBy !== null || $value === '') {
                continue;
            }
            $identity = \findCommonUserByIdentity($type, $value, $provider);
            if ($identity && !empty($identity['common_user_id'])) {
                $commonUserId = (string)$identity['common_user_id'];
                $matchedBy = 'identity:' . $type;
            } elseif (\getSystemSettingValue('common_hub_verified_identity_only', '1') === '1') {
                $unverifiedIdentity = \findCommonUserByIdentity($type, $value, $provider, false);
                if ($unverifiedIdentity && empty($unverifiedIdentity['verified'])) {
                    $unverifiedIdentityCandidateCount++;
                }
            }
        }

        $createIfMissing = array_key_exists('create_if_missing', $data) ? !empty($data['create_if_missing']) : true;
        if ($matchedBy === null && !$createIfMissing) {
            \apiV2Error('COMMON_USER_NOT_FOUND', 'No matching common user was found.', 404);
        }

        $txStarted = false;
        try {
            if (!$db->inTransaction()) {
                $db->beginTransaction();
                $txStarted = true;
            }

            if ($matchedBy === null) {
                $commonUserId = \ensureCommonUser($commonUserId ?: null, $data);
                $created = true;
                $matchedBy = 'created';
            } else {
                \ensureCommonUser($commonUserId, $data);
            }

            \updateCommonUserHubFields($commonUserId, [
                'acquisition_channel' => $data['acquisition_channel'] ?? null,
                'acquisition_source' => $data['acquisition_source'] ?? $systemKey,
                'campaign_id' => $data['campaign_id'] ?? null,
                'registration_referrer_agent_id' => $data['registration_referrer_agent_id'] ?? $agentId,
                'assigned_agent_id' => $data['assigned_agent_id'] ?? null,
                'agent_link_status' => $data['agent_link_status'] ?? null,
                'management_status' => $data['management_status'] ?? null,
                'first_touch_at' => $data['first_touch_at'] ?? null,
                'last_touch_at' => $data['last_touch_at'] ?? null,
                'metadata_json' => is_array($data['metadata'] ?? null) ? $data['metadata'] : null,
            ]);

            if ($systemKey !== '' && $externalUserId !== '') {
                \saveSystemAccountLink(array_merge($data, [
                    'common_user_id' => $commonUserId,
                    'system_key' => $systemKey,
                    'external_user_id' => $externalUserId,
                    'agent_id' => $agentId,
                ]));
            } else {
                $this->saveIdentityChecks($identityChecks, $commonUserId, $systemKey, $externalUserId, $data);
            }

            if ($txStarted) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($txStarted && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        $profile = \loadCommonUserHubProfile($commonUserId);
        $identityMatchStatus = $unverifiedIdentityCandidateCount > 0 && $matchedBy === 'created' ? 'unverified_candidate_not_auto_merged' : 'ok';
        $contractFields = \commonUserResolutionContractFields($profile ?: [], $identityMatchStatus, $unverifiedIdentityCandidateCount);

        return array_merge([
            'ok' => true,
            'common_user_id' => $commonUserId,
            'created' => $created,
            'matched_by' => $matchedBy,
            'identity_match_status' => $identityMatchStatus,
            'unverified_identity_candidates_count' => $unverifiedIdentityCandidateCount,
            'common_user' => $profile['common_user'] ?? null,
            'system_links' => $profile['system_links'] ?? [],
            'identities' => $profile['identities'] ?? [],
            'agency_relations' => $profile['agency_relations'] ?? [],
        ], $contractFields);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $auth
     * @return array<string, mixed>
     */
    public function saveSystemLink(string $commonUserId, array $data, array $auth, ?int $agentId): array
    {
        if ($commonUserId === '') {
            \apiV2Error('VALIDATION_ERROR', 'common_user_id is required.', 422);
        }

        $systemKey = $this->inputSystemKey($data, $auth);
        $externalUserId = trim((string)($data['external_user_id'] ?? $data['service_user_id'] ?? ''));
        if ($systemKey === '' || $externalUserId === '') {
            \apiV2Error('VALIDATION_ERROR', 'system_key and external_user_id are required.', 422);
        }

        $link = \saveSystemAccountLink(array_merge($data, [
            'common_user_id' => $commonUserId,
            'system_key' => $systemKey,
            'external_user_id' => $externalUserId,
            'agent_id' => $agentId,
        ]));
        $profile = \loadCommonUserHubProfile($commonUserId);

        return [
            'ok' => true,
            'common_user_id' => $commonUserId,
            'system_link' => $link,
            'common_user' => $profile['common_user'] ?? null,
            'system_links' => $profile['system_links'] ?? [],
            'identities' => $profile['identities'] ?? [],
        ];
    }

    /**
     * @param list<array{0: string, 1: string, 2: string}> $identityChecks
     * @param array<string, mixed> $data
     */
    private function saveIdentityChecks(array $identityChecks, string $commonUserId, string $systemKey, string $externalUserId, array $data): void
    {
        foreach ($identityChecks as [$type, $value, $provider]) {
            if ($value === '') {
                continue;
            }

            \saveUserIdentity([
                'common_user_id' => $commonUserId,
                'identity_type' => $type,
                'provider' => $provider,
                'identity_value' => $value,
                'verified' => in_array($type, ['line'], true) || !empty($data[$type . '_verified']),
                'source_system_key' => $systemKey ?: null,
                'source_external_user_id' => $externalUserId ?: null,
            ]);
        }
    }
}

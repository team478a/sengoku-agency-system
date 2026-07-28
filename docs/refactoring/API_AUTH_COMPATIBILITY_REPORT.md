# API Auth Compatibility Report

Date: 2026-07-29

Branch: `feature/v3.6.82-modular-monolith-foundation`

## Summary

API authentication has been centralized through `SenNoKuni\Shared\Auth\ApiKeyAuthenticator` while preserving existing API clients.

Supported key sources:

- `x-api-key`
- `X-API-Key`
- `Authorization: Bearer <token>`
- `X-API-Token`
- `?token=` for the legacy hierarchy API compatibility path

Supported key types:

- Legacy system key stored as `system_settings.external_api_token`.
- Partner-specific inbound key stored on `external_partner_sites.inbound_api_key`.

## Endpoints Using Common Auth

`/api/hierarchy.php`

- Uses `ApiKeyAuthenticator`.
- Accepts legacy key and partner-specific inbound keys.
- Preserves `format=tree|flat`, `root_code`, `include_contact=1`, `include_sso=1`, and inactive-filter behavior.
- Existing URL is unchanged.

`/api/integrations/agencies`

- Uses `ApiKeyAuthenticator`.
- Accepts legacy key and partner-specific inbound keys.
- Applies partner scope checks when `external_partner_sites.inbound_scopes` exists.
- Existing URL is unchanged.

`/api/v2/*`

- Uses `ApiKeyAuthenticator`.
- Accepts legacy key and partner-specific inbound keys.
- Supports scope checks through `ApiScopeAuthorizer`.
- Supports partner expiry and IP allow-list checks when available.

## Scope Compatibility

Scope enforcement is backward compatible:

- If the partner site row is not present, legacy key behavior is preserved.
- If `external_partner_sites.inbound_scopes` does not exist, scope checks are skipped.
- If a partner key is present and scopes are configured, the endpoint requires the matching scope.

Known write scope:

- `agencies:write` for agency registration/update integration.

## IP And Expiry Compatibility

Partner restrictions are only enforced by callers that request strict partner restriction checks.

Supported fields:

- `api_key_expires_at`
- `inbound_ip_allowlist`

If these columns do not exist or are empty, legacy behavior continues.

## Breaking Changes

None intended.

No API URL, header name, response envelope, or legacy key setting was removed.

## Operational Guidance

For new external systems:

1. Create a partner record from the External API integration screen.
2. Generate an inbound API key for the partner.
3. Give that key to the external system when it calls `sengoku-ai.com`.
4. Store the external system's receiving API key separately when `sengoku-ai.com` sends events outward.

This keeps inbound and outbound keys separate per connected site.

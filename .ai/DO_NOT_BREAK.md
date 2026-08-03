# Do Not Break

The following surfaces are compatibility-critical and must be treated as protected unless a task explicitly authorizes a breaking change.

## Authentication and authorization

- Existing administrator and agency login flows
- API key and Bearer authentication compatibility
- API scope enforcement
- IP restriction behavior where configured
- Existing session and cookie behavior

## Identity and agency data

- Existing common-user records and external-ID mappings
- Agency hierarchy and parent-child relationships
- Existing referral attribution and referral tokens
- Sales, closing and reward ownership records
- Historical reward and activity records

## External interfaces

- Existing API response shapes used by connected systems
- Webhook authentication and signature behavior
- Integration outbox retry and dead-letter recovery
- Purchase-to-agency provisioning flow
- CSV export column order and contract

## User-facing surfaces

- Administrator screens
- Agency dashboards and reports
- Landing-page and referral links
- Existing URLs used by external systems or operators

## Change restrictions

- Do not write directly to production data.
- Do not apply production migrations automatically.
- Do not rename or remove public endpoints without a compatibility layer.
- Do not replace established business rules with inferred behavior.
- Do not bypass failing tests to obtain a green build.
- Do not combine unrelated refactoring with a functional change.
- Do not commit credentials, database URLs, API keys or production exports.

When a protected behavior must change, document the migration path, rollback procedure, affected consumers and required human approval.
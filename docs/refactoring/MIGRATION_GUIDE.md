# Migration Guide

## v3.6.86

No database migration is required.

The Outbox and operations admin screens now load shared admin helper classes through `includes/shared_bootstrap.php`.

Existing page URLs, form action names, and query parameters remain unchanged.

## v3.6.85

No database migration is required.

Common user and referral internals now load the following classes through `includes/shared_bootstrap.php`:

- `SenNoKuni\CommonIdentity\CommonUserInputNormalizer`
- `SenNoKuni\Referral\ReferralTokenResolver`
- `SenNoKuni\Referral\TouchpointFingerprint`

Existing API URLs and legacy helper names remain unchanged.

## v3.6.84

No database migration is required.

Outbox internals now load `src/Integration/Outbox` classes through `includes/shared_bootstrap.php`.

The following existing functions remain available as compatibility wrappers:

- `retryDueIntegrationOutboxEvents`
- `retryIntegrationOutboxEventRow`
- `recoverStaleIntegrationOutboxClaims`
- `resetIntegrationOutboxEventForRetry`
- `moveIntegrationOutboxEventToDlq`

## v3.6.83

No database migration is required.

The following APIs now load `includes/shared_bootstrap.php` and route authentication through shared classes:

- `api/v2/bootstrap.php`
- `api/hierarchy.php`
- `api/integrations/agencies/index.php`

Rollback is file-only if authentication regressions are found.

## v3.6.82

No database migration is required.

This phase adds development and module foundation files only. Production behavior is unchanged unless future phases wire legacy entrypoints to `src/` services.

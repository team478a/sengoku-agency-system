# Implementation Status

## v3.6.85 CommonIdentity / Referral Foundation

Status: in progress

Implemented in this phase:

- Added `src/CommonIdentity` classes:
  - `CommonUserInput`
  - `CommonUserInputNormalizer`
- Added `src/Referral` classes:
  - `ReferralTokenResolver`
  - `TouchpointFingerprint`
- Routed current common user API input normalization through `CommonUserInputNormalizer`.
- Routed current referral API common user input normalization through `CommonUserInputNormalizer`.
- Routed referral token resolution and touchpoint IP/user-agent hashing through `src/Referral` classes.

Runtime behavior changed: limited to internal delegation

Database changed: no

Existing API URLs changed: no

## v3.6.84 Integration / Outbox Foundation

Status: in progress

Implemented in this phase:

- Added `src/Integration/Outbox` classes:
  - `RetryPolicy`
  - `OutboxClaimService`
  - `OutboxRepository`
  - `DeadLetterService`
- Kept existing compatibility functions and routed them through the new services:
  - `integrationOutboxSupportsClaims`
  - `getIntegrationOutboxClaimTimeoutSeconds`
  - `recoverStaleIntegrationOutboxClaims`
  - `claimIntegrationOutboxEventById`
  - `claimDueIntegrationOutboxEvents`
  - `updateIntegrationOutboxEventAfterAttempt`
  - `resetIntegrationOutboxEventForRetry`
  - `moveIntegrationOutboxEventToDlq`

Runtime behavior changed: limited to Outbox internal delegation

Database changed: no

Existing admin/cron/API entrypoints changed: no

## v3.6.83 Shared Foundation

Status: in progress

Implemented in this phase:

- Added shared API authentication and scope authorization classes.
- Added shared IP restriction, schema checker, API exception payload, HTTP client, logger, and clock classes.
- Kept existing public functions as compatibility wrappers:
  - `apiV2Authenticate`
  - `apiV2RequireScope`
  - `apiTokenIsValid`
  - `agencyApiKeyIsValid`
  - `agencyApiPartnerByKey`
  - `agencyApiRequireScope`
- Added `includes/shared_bootstrap.php` so production can load `src/` classes even without Composer autoload.

Runtime behavior changed: limited to shared authentication internals

Database changed: no

Existing API URLs changed: no

## v3.6.82 Modular Monolith Foundation

Status: in progress

Implemented in this phase:

- Added Composer project metadata and PSR-4 autoload mapping for `SenNoKuni\\`.
- Added PHPUnit, PHPStan, and syntax lint configuration.
- Added GitHub Actions CI for lint, tests, and static analysis.
- Added initial characterization tests for legacy entrypoints and function names.
- Added small Shared module foundation classes under `src/Shared`.

Runtime behavior changed: no

Database changed: no

Existing API contracts changed: no

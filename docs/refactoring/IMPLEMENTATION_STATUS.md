# Implementation Status

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

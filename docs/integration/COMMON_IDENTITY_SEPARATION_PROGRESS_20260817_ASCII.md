# Common Identity Separation Progress 2026-08-17

This note records the current implementation progress for the common identity separation work.

## Completed

- Moved common user resolve logic from `api/common-users/index.php` into `src/CommonIdentity/CommonUserResolveService.php`.
- Kept the public API route and response shape unchanged.
- Moved `agency_customer_relations` read/write SQL into `src/CommonIdentity/AgencyCustomerRelationRepository.php`.
- Updated `includes/shared_bootstrap.php` so both new CommonIdentity classes are loaded by the shared bootstrap.
- Added characterization tests for the common identity entrypoints and new service files.

## Files Changed

- `api/common-users/index.php`
- `includes/functions.php`
- `includes/shared_bootstrap.php`
- `src/CommonIdentity/CommonUserResolveService.php`
- `src/CommonIdentity/AgencyCustomerRelationRepository.php`
- `tests/Characterization/CommonIdentityFoundationTest.php`
- `docs/integration/COMMON_IDENTITY_SEPARATION_PROGRESS_20260817_ASCII.md`

## Public Behavior

- No public API URL changes.
- No request or response format changes.
- No database schema changes.
- No SSO behavior changes.
- `/api/common-users/resolve` remains the official common user resolve endpoint.

## Verification

- PHP syntax check passed for the changed PHP files.
- Shared bootstrap can load both new CommonIdentity classes.
- PHPUnit was not executed because `vendor/bin/phpunit` is not present in this source folder.

## Remaining Cleanup

- `includes/functions.php` still contains legacy code around some older helper areas. It should be cleaned carefully in a later cleanup pass because the file contains existing mojibake strings and broad edits are risky.
- Next phase should continue separating agency relation reassignment and common user profile screens from legacy helper functions.

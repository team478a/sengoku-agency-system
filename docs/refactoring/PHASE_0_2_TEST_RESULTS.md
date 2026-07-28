# Phase 0-2 Test Results

Date: 2026-07-29

Branch: `feature/v3.6.82-modular-monolith-foundation`

## Local Checks

Completed:

- `php scripts/lint-php.php`: passed
- `php -l` checks for touched and newly added PHP files in recent phases: passed
- `git diff --check`: passed in recent implementation phases

The most recent detailed test log is maintained in:

- `docs/refactoring/TEST_RESULTS.md`

## Characterization Coverage

Current characterization tests cover the modular foundation areas below:

- Shared auth, JSON request, and JSON response helpers.
- Legacy public helper surface.
- Integration outbox foundation.
- Common identity input normalization.
- Referral token and touchpoint helpers.
- Admin presentation helpers.
- Activity summary, trend, and ranking helpers.
- Landing page URL, SEO, template repository, and renderer behavior.
- Notification template/channel foundation.

## CI Configuration

GitHub Actions workflow:

- `.github/workflows/ci.yml`

Configured jobs:

- Composer validation.
- Composer dependency install.
- PHP syntax lint.
- PHPUnit.
- MariaDB-backed CSV contract tests.
- PHPStan.

## Not Run Locally

These checks were not run in the current Windows local environment:

- `composer validate --strict`
- `composer install`
- `composer test`
- `composer analyse`
- GitHub Actions log inspection

Reason:

- Local PHP lacks the OpenSSL extension required by Composer.
- Local PHP does not expose PDO database drivers needed for DB-backed tests.
- GitHub CLI is installed but not authenticated in this environment.

## Required Remote Verification

After opening the Draft PR, verify:

- GitHub Actions completes successfully.
- MariaDB service starts in CI.
- `composer test:csv-contract` completes against the CI MariaDB service.
- Existing admin, agent, LP, and API smoke tests pass on staging after ZIP update.

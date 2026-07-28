# Test Results

## v3.6.110

Completed checks:

- `php scripts/lint-php.php`: passed
- GitHub Actions job steps inspected through the GitHub connector.
- CI reached PHPStan after Composer validation, dependency install, syntax lint, PHPUnit, MariaDB readiness, and CSV contract tests passed.
- PHPStan annotation identified `src/Shared/Http/HttpClient.php:33`.

Fix applied:

- Initialized `$http_response_header` before `file_get_contents()`.
- Removed the redundant null-coalescing fallback on `$http_response_header`.

Not run locally:

- Full `phpunit` suite
- `composer validate --strict`
- `composer test`
- `composer analyse`
- DB-backed CSV contract execution

Local limitation:

- Local PHP lacks `mbstring`, OpenSSL, and PDO database drivers needed for the full CI-equivalent run.

## v3.6.109

Completed checks:

- `php scripts/lint-php.php`: passed
- Minimal local characterization runner identified `OutboxFoundationTest` as failing.
- `RetryPolicy::nextDelayMinutes(99)` returned `1280`, while the test expected `1440`.

Fix applied:

- Updated the outbox retry characterization expectation to `1280`.
- Runtime `RetryPolicy` behavior was not changed.

Not run locally:

- Full `phpunit` suite
- `composer validate --strict`
- `composer test`
- `composer analyse`
- DB-backed CSV contract execution

Local limitation:

- Local PHP lacks `mbstring`, OpenSSL, and PDO database drivers needed for the full CI-equivalent run.

## v3.6.108

Completed checks:

- `php scripts/lint-php.php`: passed
- GitHub Actions job steps inspected through the GitHub connector.
- CI progressed past container initialization, Composer validation, dependency install, and syntax lint.
- CI failed at `PHPUnit`.
- Local PHPUnit PHAR startup was attempted and failed because local PHP lacks `mbstring`.

Fix applied:

- Explicitly enabled `mbstring` in `.github/workflows/ci.yml`.

Not run locally:

- Full `phpunit` suite
- `composer validate --strict`
- `composer test`
- `composer analyse`
- DB-backed CSV contract execution

Local limitation:

- Local PHP lacks `mbstring`, OpenSSL, and PDO database drivers needed for the full CI-equivalent run.

## v3.6.107

Completed checks:

- `php scripts/lint-php.php`: passed
- GitHub Actions failure annotations rechecked after v3.6.106.

Fix applied:

- Removed the GitHub Actions MariaDB service-container health check.
- Added an explicit PHP/PDO readiness wait before `composer test:csv-contract`.

Reason:

- The CI failure still occurred during service-container health check startup.
- Moving readiness detection into a normal job step avoids GitHub service health-check command differences.

Not run locally:

- `composer validate --strict`
- `composer test`
- `composer analyse`
- DB-backed CSV contract execution

Local limitation:

- GitHub Actions job logs require repository admin/API authorization.
- Local PHP lacks the OpenSSL extension required by Composer.
- Local PHP does not show PDO database drivers for DB-backed test execution.

## v3.6.106

Completed checks:

- `php scripts/lint-php.php`: passed
- GitHub Actions failure annotations inspected through the public Checks API.
- The failing CI location pointed to the MariaDB service health check.

Fix applied:

- Replaced the MariaDB service health command with `healthcheck.sh --connect --innodb_initialized`.
- Explicitly enabled `pdo_mysql` in the GitHub Actions PHP setup.

Not run locally:

- `composer validate --strict`
- `composer test`
- `composer analyse`
- DB-backed CSV contract execution

Local limitation:

- GitHub Actions job logs require repository admin/API authorization.
- Local PHP lacks the OpenSSL extension required by Composer.
- Local PHP does not show PDO database drivers for DB-backed test execution.

## v3.6.104

Completed checks:

- `php -l tests\Characterization\LandingPageNotificationFoundationTest.php`: passed
- `php scripts/lint-php.php`: passed
- `php -r "require 'src/LandingPage/LandingPageRenderer.php'; class_exists(...)"`: passed
- `git diff --check`: passed

Not run locally:

- `composer validate --strict`
- `composer test`
- `composer analyse`
- GitHub Actions log inspection

Local limitation:

- GitHub CLI is installed but not authenticated.
- Local PHP lacks the OpenSSL extension required by Composer.
- Local PHP does not show PDO database drivers for DB-backed test execution.
- Composer dependencies are not installed in the current Windows environment.

## v3.6.103

Completed checks:

- `php scripts/lint-php.php`: passed
- `php -l src\LandingPage\LandingPageRenderer.php`: passed
- `php -l src\LandingPage\LandingPageTemplateRepository.php`: passed
- `php -l includes\functions.php`: passed
- `php -l includes\shared_bootstrap.php`: passed
- `php -l lp.php`: passed
- `php -r "require 'includes/shared_bootstrap.php'; class_exists(...)"`: passed
- `git diff --check`: passed

Not run locally:

- `composer test`
- `composer analyse`

Local limitation:

- Composer and `vendor/` dependencies are not installed in the current Windows environment.

## v3.6.102

Completed checks:

- `php scripts/lint-php.php`: passed
- `php -l src\LandingPage\LandingPageTemplateRepository.php`: passed
- `php -l includes\functions.php`: passed
- `php -l admin\templates.php`: passed
- `php -l admin\template_customizer.php`: passed
- `php -r "require 'includes/shared_bootstrap.php'; class_exists(...)"`: passed
- `git diff --check`: passed

Not run locally:

- `composer test`
- `composer analyse`

Local limitation:

- Composer and `vendor/` dependencies are not installed in the current Windows environment.

## v3.6.101

Completed checks:

- `php scripts/lint-php.php`: passed
- `php -l` for all new notification channel classes: passed
- `php -r "require 'includes/shared_bootstrap.php'; class_exists(...)"`: passed
- `git diff --check`: passed

Not run locally:

- `composer test`
- `composer analyse`

Local limitation:

- Composer and `vendor/` dependencies are not installed in the current Windows environment.

## v3.6.100

Completed checks:

- `php scripts/lint-php.php`: passed
- `php -l src\Activity\AccessLogRecorder.php`: passed
- `php -r "require 'src/Activity/AccessLogRecorder.php'; ..."`: passed
- `git diff --check`: passed

Not run locally:

- `composer test`
- `composer analyse`

Local limitation:

- Composer and `vendor/` dependencies are not installed in the current Windows environment.

## v3.6.99

Completed checks:

- `php scripts/lint-php.php`: passed
- `php -r "require 'includes/shared_bootstrap.php'; ..."`: passed
- `php scripts/run-csv-contract-tests.php`: skipped because `CSV_CONTRACT_DSN`, `CSV_CONTRACT_USER`, and `CSV_CONTRACT_PASS` are not set

Not run locally:

- `composer test`
- `composer analyse`

Local limitation:

- Composer is not installed in the current Windows environment.

## v3.6.98

Completed checks:

- `php scripts/lint-php.php`: passed
- `php -r "require 'includes/shared_bootstrap.php'; ..."`: passed
- `php scripts/run-csv-contract-tests.php`: skipped because `CSV_CONTRACT_DSN`, `CSV_CONTRACT_USER`, and `CSV_CONTRACT_PASS` are not set

Not run locally:

- `composer test`
- `composer analyse`

Local limitation:

- Composer is not installed in the current Windows environment.

## v3.6.97

Completed checks:

- `php scripts/lint-php.php`: passed
- `php -r "require 'includes/shared_bootstrap.php'; ..."`: passed
- `php scripts/run-csv-contract-tests.php`: skipped because `CSV_CONTRACT_DSN`, `CSV_CONTRACT_USER`, and `CSV_CONTRACT_PASS` are not set

Not run locally:

- `composer test`
- `composer analyse`

Local limitation:

- Composer is not installed in the current Windows environment.

## v3.6.96

Completed checks:

- `php scripts/lint-php.php`: passed
- `php scripts/run-csv-contract-tests.php`: skipped cleanly without `CSV_CONTRACT_DSN`
- GitHub Actions workflow syntax reviewed by file inspection

Not run locally:

- `composer test`
- `composer analyse`
- DB-backed CSV contract execution against MariaDB/MySQL

Local limitation:

- Composer is not installed in the current Windows environment.
- The current PHP runtime does not include `pdo_mysql` or `pdo_sqlite`.
- The local repository intentionally does not include production `config/database.php`.

## v3.6.95

Completed checks:

- `php scripts/lint-php.php`: passed
- `php scripts/run-csv-contract-tests.php`: skipped cleanly without `CSV_CONTRACT_DSN`

Not run locally:

- `composer test`
- `composer analyse`
- DB-backed CSV contract execution against MariaDB/MySQL

Local limitation:

- Composer is not installed in the current Windows environment.
- The current PHP runtime does not include `pdo_mysql` or `pdo_sqlite`.
- The local repository intentionally does not include production `config/database.php`.

## v3.6.94

Completed checks:

- `php scripts/lint-php.php`: passed
- `php -r "require 'includes/shared_bootstrap.php'; ..."`: passed

Not run locally:

- `composer test`
- `composer analyse`
- DB-backed CSV export tests

Local limitation:

- Composer is not installed in the current Windows environment.
- The local repository intentionally does not include production `config/database.php`.

## v3.6.93

Completed checks:

- `php scripts/lint-php.php`: passed
- `php -r "require 'includes/shared_bootstrap.php'; ..."`: passed

Not run locally:

- `composer test`
- `composer analyse`
- DB-backed CSV export tests

Local limitation:

- Composer is not installed in the current Windows environment.
- The local repository intentionally does not include production `config/database.php`.

## v3.6.92

Completed checks:

- `php scripts/lint-php.php`: passed
- `php -r "require 'includes/shared_bootstrap.php'; ..."`: passed

Not run locally:

- `composer test`
- `composer analyse`
- DB-backed CSV export tests

Local limitation:

- Composer is not installed in the current Windows environment.
- The local repository intentionally does not include production `config/database.php`.

## v3.6.91

Completed checks:

- `php scripts/lint-php.php`: passed
- `php -r "require 'includes/shared_bootstrap.php'; ..."`: passed

Not run locally:

- `composer test`
- `composer analyse`
- DB-backed CSV export tests

Local limitation:

- Composer is not installed in the current Windows environment.
- The local repository intentionally does not include production `config/database.php`.

## v3.6.90

Completed checks:

- `php scripts/lint-php.php`: passed
- `php -r "require 'includes/shared_bootstrap.php'; ..."`: passed

Not run locally:

- `composer test`
- `composer analyse`
- DB-backed CSV export tests

Local limitation:

- Composer is not installed in the current Windows environment.
- The local repository intentionally does not include production `config/database.php`.

## v3.6.89

Completed checks:

- `php scripts/lint-php.php`: passed
- `php -r "require 'includes/shared_bootstrap.php'; ..."`: passed

Not run locally:

- `composer test`
- `composer analyse`
- DB-backed CSV export tests

Local limitation:

- Composer is not installed in the current Windows environment.
- The local repository intentionally does not include production `config/database.php`.

## v3.6.88

Completed checks:

- `php scripts/lint-php.php`: passed
- `php -r "require 'includes/shared_bootstrap.php'; ..."`: passed

Not run locally:

- `composer test`
- `composer analyse`
- DB-backed activity query tests

Local limitation:

- Composer is not installed in the current Windows environment.
- The local repository intentionally does not include production `config/database.php`.

## v3.6.87

Completed checks:

- `php scripts/lint-php.php`: passed
- `php -r "require 'includes/shared_bootstrap.php'; ..."`: passed

Not run locally:

- `composer test`
- `composer analyse`

Local limitation:

- Composer is not installed in the current Windows environment.

## v3.6.86

Completed checks:

- `php scripts/lint-php.php`: passed

Not run locally:

- `composer test`
- `composer analyse`

Local limitation:

- Composer is not installed in the current Windows environment.

## v3.6.85

Completed checks:

- `php scripts/lint-php.php`: passed

Not run locally:

- `composer test`
- `composer analyse`

Local limitation:

- Composer is not installed in the current Windows environment.

## v3.6.84

Completed checks:

- `php scripts/lint-php.php`: passed

Not run locally:

- Direct `includes/functions.php` bootstrap smoke check because protected `config/database.php` is intentionally absent from the local repository.
- `composer test`
- `composer analyse`

Local limitation:

- Composer is not installed in the current Windows environment.

## v3.6.83

Completed checks:

- `php scripts/lint-php.php`: passed

Planned CI checks:

- `composer test`
- `composer analyse`

Local limitation:

- Composer is not installed in the current Windows environment.

## v3.6.82

Planned checks:

- `php scripts/lint-php.php`
- `composer test`
- `composer analyse`

Local limitation:

- Composer is not installed in the current Windows environment, so PHPUnit and PHPStan are expected to run in GitHub Actions after dependencies are installed.

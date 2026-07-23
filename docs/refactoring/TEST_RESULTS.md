# Test Results

## v3.6.82

Planned checks:

- `php scripts/lint-php.php`
- `composer test`
- `composer analyse`

Local limitation:

- Composer is not installed in the current Windows environment, so PHPUnit and PHPStan are expected to run in GitHub Actions after dependencies are installed.


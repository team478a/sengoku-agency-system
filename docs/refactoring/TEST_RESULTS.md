# Test Results

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

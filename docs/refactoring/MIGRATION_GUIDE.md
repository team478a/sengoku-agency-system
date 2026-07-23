# Migration Guide

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

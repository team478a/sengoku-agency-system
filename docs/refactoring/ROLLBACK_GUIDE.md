# Rollback Guide

## v3.6.82

Rollback is file-only:

1. Revert the v3.6.82 commit.
2. Remove generated `vendor/`, `.phpunit.cache/`, and `.phpstan-cache/` if they exist.
3. No database rollback is required.


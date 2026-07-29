# Purchase Provisioning Readiness

## Source Instruction

`AGENCY_SYSTEM_PURCHASE_PROVISIONING_IMPLEMENTATION_INSTRUCTIONS.md`

## Current Status

Purchase Provisioning implementation is not started yet because the instruction explicitly requires the modular monolith branch to be completed, reviewed, merged into `main`, and followed by a new implementation branch.

Current branch:

```text
feature/v3.6.82-modular-monolith-foundation
```

Required implementation branch after merge:

```text
feature/purchase-provisioning-foundation
```

## Completed Prerequisite Cleanup

- Removed request-time DDL from `api/integrations/agencies/index.php`.
- Replaced API-side schema mutation with a `DB_MIGRATION_REQUIRED` response when required columns are missing.
- Separated `external_id` lookup from legacy `agency_id` / `agent_code` lookup.
- Added characterization coverage to prevent reintroducing request-time DDL and combined `external_id OR agent_code` lookup.

## Still Required Before Purchase Provisioning Work

- Confirm the modular monolith branch CI is fully green.
- Generate and commit `composer.lock`.
- Merge `feature/v3.6.82-modular-monolith-foundation` into `main`.
- Create `feature/purchase-provisioning-foundation` from updated `main`.
- Implement PR-A1 through PR-A7 as separate, reviewable changes:
  - PR-A1 customer accounts and access grants
  - PR-A2 customer entitlements
  - PR-A3 purchase provisioning API
  - PR-A4 market-to-agency SSO
  - PR-A5 provisioning admin operations
  - PR-A6 purchase provisioning revoke
  - PR-A7 purchase provisioning E2E tests

## Local Verification

- `php -l api/integrations/agencies/index.php`: passed
- `php -l tests/Characterization/LegacySurfaceTest.php`: passed
- `php scripts/lint-php.php`: passed
- `git diff --check`: passed

## Local Limitations

- Local Composer is not installed.
- Local PHP lacks `mbstring`, OpenSSL, and PDO database drivers needed for full CI-equivalent execution.

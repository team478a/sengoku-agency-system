# Project Context

## Repository

- Repository: `team478a/sengoku-agency-system`
- Pilot base branch: `feature/v3.6.82-modular-monolith-foundation`
- Runtime: PHP 8.2
- Architecture direction: modular monolith with PSR-4 namespace `SenNoKuni\\`
- Quality tools: PHPUnit 11, PHPStan 1.12, custom PHP lint and CSV contract tests

## Purpose

This system is the central agency hub for the Sen-no-Kuni ecosystem. It manages common identity references, agency relationships, referral attribution, hierarchy, sales and closing responsibility, reward-related records, and external-system integration.

## Core identifiers

- `common_user_id`: ecosystem-wide user identifier
- `agency_id`: agency identifier
- `referral_token`: referral attribution token
- External system internal IDs: mappings to market, wallet, passport, art-school and other systems

## Current development direction

The active modular-monolith branch extracts legacy procedural responsibilities into `src/` services while preserving existing screens, APIs and integrations. AI-assisted development must prioritize compatibility and incremental replacement over broad rewrites.

## Required context before work

Before proposing or changing code, read:

1. `.ai/BUSINESS_RULES.md`
2. `.ai/DO_NOT_BREAK.md`
3. `.ai/DEVELOPMENT_WORKFLOW.md`
4. `docs/refactoring/IMPLEMENTATION_STATUS.md`
5. `docs/refactoring/IMPLEMENTATION_HISTORY.md`
6. `docs/refactoring/MODULE_DEPENDENCY_MAP.md`
7. `docs/integration/PURCHASE_PROVISIONING_READINESS.md`

## Standard validation commands

```bash
composer install
composer lint
composer analyse
composer test
composer test:csv-contract
```

Do not report completion unless applicable commands have been executed or the inability to run them is explicitly documented.
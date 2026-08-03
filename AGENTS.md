# AGENTS.md

## Purpose

This repository is the pilot project for the Sen-no-Kuni development orchestrator. Agents may inspect, plan, implement, test, and prepare pull requests, but they must operate inside the boundaries below.

## Repository role

- System: Sen-no-Kuni agency system
- Repository: `team478a/sengoku-agency-system`
- Core responsibility: common customer identity, agency/referral relationships, external-system mappings, SSO/integration contracts, and operational auditability
- Active modernization branch: `feature/v3.6.82-modular-monolith-foundation`

## Protected domain contracts

Do not change the following semantics without an explicit architecture decision and human approval:

1. `common_user_id` is the cross-system identity key.
2. Referral attribution and agency relationships must remain traceable and idempotent.
3. Existing external API contracts and event names must remain backward compatible unless a versioned migration is supplied.
4. Outbox, retry, dead-letter, and integration-log behavior must preserve audit history.
5. Manual merge and repair operations must remain administrator-only and auditable.
6. Production credentials, API keys, database passwords, and customer data must never be printed, committed, or copied into prompts.

## Default operating workflow

For every change request:

1. Inspect the current branch, recent commits, relevant documentation, migrations, and affected call paths.
2. State the problem, evidence, affected modules, dependencies, and unknowns.
3. Produce a bounded implementation plan with acceptance criteria and rollback considerations.
4. Run an independent plan review before implementation when the work affects authentication, identity, referrals, payments, external integrations, or database schema.
5. Work on a dedicated branch. Never commit directly to `main`.
6. Make the smallest coherent change that satisfies the request. Do not perform unrelated refactors.
7. Add or update tests where the repository supports them. If automated tests are unavailable, document deterministic verification steps.
8. Run all discoverable non-destructive validation commands relevant to the changed files.
9. Review the final diff against the approved plan and protected contracts.
10. Open a draft pull request with evidence, risks, migration notes, and remaining manual checks.

## Approval policy

### May proceed without additional approval

- Read-only repository analysis
- Implementation planning
- Documentation changes
- Local code edits on a task branch
- Non-destructive tests, linting, static analysis, and builds
- Draft pull-request creation

### Requires human approval before execution

- Merge into `main` or another protected branch
- Production deployment
- Production database migration or data repair
- Destructive or irreversible data operations
- Authentication, authorization, SSO, or secret-rotation changes
- Changes to agency commission or payment calculations
- Breaking changes to common identity, referral, API, webhook, or event contracts
- Enabling broad internet access or unrestricted execution for an automated worker

## Database rules

- Prefer additive, backward-compatible migrations.
- Every migration must include preconditions, expected impact, verification queries, and rollback or forward-fix guidance.
- Do not assume development, staging, and production schemas are identical.
- Before adding constraints or unique indexes, include duplicate-detection queries.
- Never execute a production migration from an agent workflow.

## External integration rules

- Preserve idempotency behavior.
- Use the existing outbox and integration log paths rather than introducing direct fire-and-forget calls.
- Include timeout, retry, failure recording, and replay behavior in the plan.
- Treat external payloads and responses as untrusted input.
- Do not expose credentials in logs or pull-request bodies.

## Required task report

Each agent task must return:

- `summary`
- `status`: `completed`, `blocked`, `needs_approval`, or `failed`
- `evidence`
- `files_changed`
- `validation`
- `risks`
- `approvals_required`
- `recommended_next_action`

Use the schemas under `docs/orchestrator/` when machine-readable output is requested.

## Stop conditions

Stop and return `needs_approval` when:

- the requested work crosses an approval boundary;
- requirements conflict with a protected domain contract;
- the current schema or runtime environment cannot be verified safely;
- implementation would require secrets that are not available through an approved secret mechanism;
- two parallel tasks would edit the same critical files or migrations without an explicit integration plan.

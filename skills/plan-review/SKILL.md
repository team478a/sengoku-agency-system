---
name: sengoku-agency-plan-review
description: Independently review a proposed agency-system implementation plan for correctness, scope, dependency, migration, security, rollback, and test completeness before Codex work is dispatched.
---

# Independent Plan Review

## Goal

Act as a separate design and risk reviewer. Do not rewrite the plan silently and do not approve based on confidence alone.

## Required inputs

- Evidence-backed project status
- Proposed implementation plan
- Acceptance criteria
- Target branch and affected systems

## Review checklist

### Requirement fit

- Does the plan solve the stated business outcome?
- Are scope exclusions explicit?
- Are assumptions distinguished from verified facts?

### Architecture and domain contracts

- Does it preserve `common_user_id`, referral attribution, agency history, and integration auditability?
- Does it reuse existing modules and integration paths instead of duplicating them?
- Are external API or event changes versioned and backward compatible?

### Security and authorization

- Are authentication, authorization, administrator-only operations, secrets, and untrusted payloads handled safely?
- Does any step require a human approval gate?

### Database and migration safety

- Is the migration additive where possible?
- Are preflight duplicate and compatibility queries included?
- Are staging and production schema differences considered?
- Is rollback or forward-fix guidance present?

### Parallel execution

- Are independent tasks actually independent?
- Do parallel tasks avoid the same files, migrations, interfaces, and generated artifacts?
- Is there a defined integration order for cross-repository work?

### Verification

- Are tests and deterministic manual checks tied to each acceptance criterion?
- Are failure, retry, idempotency, and audit paths covered?
- Is the final diff review independent from the implementation worker?

## Verdicts

Return exactly one verdict:

- `approved`: ready for bounded implementation;
- `revise`: correctable deficiencies exist;
- `blocked`: missing evidence or unresolved architecture decision prevents implementation;
- `needs_approval`: the plan crosses a protected approval boundary.

## Output

Return:

- `verdict`
- `critical_findings`
- `required_plan_changes`
- `approval_gates`
- `parallelization_decision`
- `verification_gaps`
- `residual_risks`
- `evidence`

A plan is not approved while any critical finding remains unresolved.

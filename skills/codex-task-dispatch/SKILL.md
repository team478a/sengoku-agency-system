---
name: sengoku-agency-codex-task-dispatch
description: Convert an approved agency-system plan into bounded, machine-readable Codex implementation tasks with safe parallelization, validation requirements, and approval boundaries.
---

# Codex Task Dispatch

## Goal

Turn an independently approved plan into one or more implementation tasks that Codex can execute without broadening scope.

## Preconditions

- `AGENTS.md` and `PROJECT.yaml` have been read.
- Project status evidence is current.
- The plan-review verdict is `approved`.
- Any required human approval has already been recorded.

## Task design rules

1. Each task must have one measurable outcome.
2. Include the repository, base branch, proposed work branch, and affected system.
3. State in-scope and out-of-scope work explicitly.
4. List protected contracts that must remain unchanged.
5. Include acceptance criteria and validation commands or validation-discovery instructions.
6. Require the worker to report changed files, tests, risks, blockers, and approvals.
7. Do not include secrets, credentials, customer data, or production access instructions.
8. Do not authorize merge or production deployment.
9. Prefer draft pull requests.
10. Require a separate final diff review.

## Parallelization rules

Parallelize only when tasks:

- do not edit the same files or migrations;
- do not redefine the same API, schema, or event contract;
- can be validated independently;
- have an explicit integration order when one consumes another's output.

For this pilot repository, dispatch no more than two implementation tasks concurrently.

## Required task envelope

Each dispatched task must contain:

- `task_id`
- `title`
- `repository`
- `base_branch`
- `work_branch`
- `goal`
- `business_context`
- `evidence`
- `in_scope`
- `out_of_scope`
- `protected_contracts`
- `implementation_steps`
- `acceptance_criteria`
- `validation`
- `approval_boundaries`
- `dependencies`
- `expected_output_schema`

Use `docs/orchestrator/task-request.schema.json` for machine-readable dispatch.

## Codex execution guidance

For automated CLI or CI execution:

- use a read-only run for analysis and planning;
- use workspace-write only for an approved implementation task;
- request structured final output using the repository task-result schema;
- capture the full event stream separately from the final result;
- treat any request for unrestricted access, secrets, production access, merge, or deployment as `needs_approval`.

## Completion

A task is complete only when:

- acceptance criteria are addressed;
- validation results are reported;
- the final diff matches the approved scope;
- residual risks and manual checks are explicit;
- a draft pull request or reviewable patch is available.

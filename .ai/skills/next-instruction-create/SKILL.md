# Skill: next-instruction-create

## Purpose

Create a precise implementation instruction for Codex, Claude Code or another developer from verified repository state.

## Prerequisites

1. Run `project-context-load`.
2. Run `progress-check` or obtain equivalent current evidence.

## Procedure

1. Select one high-priority, reviewable objective.
2. State the repository, base branch and starting commit.
3. Explain why the task is next and what risk it addresses.
4. Define in-scope and out-of-scope work.
5. List relevant files and modules without forbidding necessary adjacent changes.
6. State applicable business rules and protected behavior.
7. Define implementation requirements in execution order.
8. Define required tests, including normal, error, duplicate/retry and authorization cases where relevant.
9. Define documentation updates.
10. Define completion criteria and required evidence.
11. Separate AI/developer work from human-only actions.

## Required instruction structure

1. Title
2. Objective
3. Repository state
4. Background and verified findings
5. Scope
6. Out of scope
7. Functional requirements
8. Technical requirements
9. Business rules
10. Do-not-break requirements
11. Test requirements
12. Validation commands
13. Documentation updates
14. Deliverables
15. Definition of done
16. Human actions and decisions

## Task sizing rules

- Prefer one objective per PR.
- Split database, API, UI and production rollout when they cannot be reviewed safely together.
- Do not mix broad refactoring with business behavior changes.
- Keep follow-up tasks explicitly listed rather than silently expanding scope.

## Mandatory validation commands

Include applicable commands from:

```bash
composer lint
composer analyse
composer test
composer test:csv-contract
```

If a command cannot run in the target environment, require the implementer to record the reason and the substitute evidence.

## Prohibited instructions

- Do not direct changes to production databases or secrets.
- Do not instruct direct pushes to `main`.
- Do not allow tests to be skipped without an explicit blocker.
- Do not declare completion without evidence.
- Do not let AI invent business rules or silently change external contracts.
- Do not authorize merge, deployment or production migration without human approval.
# AI-Assisted Development Workflow

## Standard sequence

1. Run `project-context-load`.
2. Confirm repository, branch and latest commit.
3. Read current implementation status and recent history.
4. Run `progress-check` before selecting work.
5. Limit one change set to one reviewable objective.
6. Implement on a non-default branch.
7. Run applicable validation commands.
8. Update status/history documentation.
9. Run `next-instruction-create` for the following task.
10. Open a draft pull request for human review.

## Definition of done

A task is complete only when:

- The requested behavior is implemented.
- Existing protected behavior remains compatible.
- Relevant tests were added or updated.
- Lint, static analysis, tests and build checks pass where applicable.
- Database impact and rollback requirements are documented.
- `docs/refactoring/IMPLEMENTATION_STATUS.md` is updated when project status changes.
- `docs/refactoring/IMPLEMENTATION_HISTORY.md` records material work.
- Remaining manual checks are explicitly listed.

## Reporting format

Every development report must separate:

- Verified facts
- Inferences
- Completed work
- Remaining work
- Detected risks
- Automated checks
- Manual checks
- Human decisions required
- Recommended next task

## Branch and PR rules

- Never commit directly to `main`.
- Use a focused branch name.
- Default to a draft PR.
- Keep unrelated changes out of the PR.
- Do not mark a PR ready or merge it without human approval.

## Environment safety

- Use local/test databases for automated verification.
- Treat staging changes as approval-required unless explicitly authorized.
- Production access, deployment, migrations and secret changes always require human approval.
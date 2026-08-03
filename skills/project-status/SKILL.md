---
name: sengoku-agency-project-status
description: Inspect the Sen-no-Kuni agency-system repository and produce an evidence-backed implementation status, risk summary, and next-action recommendation. Use before planning or dispatching development work.
---

# Project Status Analysis

## Goal

Create a current, evidence-backed snapshot of this repository before any implementation plan is approved.

## Required inputs

- Requested business outcome
- Target branch or pull request, when supplied
- Relevant linked systems, when known

## Workflow

1. Read `AGENTS.md` and `PROJECT.yaml` first.
2. Resolve the actual target branch. Do not assume the default branch is the active integration branch.
3. Inspect:
   - recent commits;
   - open pull requests and their review state;
   - changed and uncommitted work when local context exists;
   - relevant architecture and implementation-status documents;
   - migrations and schema assumptions;
   - CI/check status when available;
   - affected API, identity, referral, SSO, outbox, and integration paths.
4. Trace the requested outcome through existing code and documents.
5. Separate confirmed facts from inferences and unresolved questions.
6. Identify dependencies on other repositories or environment changes.
7. Recommend the smallest next executable unit of work.

## Evidence rules

- Cite file paths, symbols, commits, pull requests, migrations, or test results.
- Do not claim a feature is complete from a document alone when code or validation evidence is available.
- Do not infer production schema state from repository migrations.
- Mark stale or conflicting documents explicitly.

## Output

Return these sections:

1. `request_understood`
2. `target_branch`
3. `current_state`
4. `confirmed_implemented`
5. `missing_or_unverified`
6. `dependencies`
7. `risks`
8. `recommended_next_action`
9. `approval_required`
10. `evidence`

Use `docs/orchestrator/task-result.schema.json` when machine-readable output is requested.

## Stop conditions

Return `blocked` or `needs_approval` when the target branch cannot be resolved, required repository evidence is inaccessible, or the requested outcome would cross a protected contract without an architecture decision.

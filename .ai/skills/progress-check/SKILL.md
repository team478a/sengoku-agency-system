# Skill: progress-check

## Purpose

Determine the current implementation state from repository evidence and produce a consistent next-action report.

## Prerequisite

Run `project-context-load` first.

## Procedure

1. Confirm branch and latest commit.
2. Compare the target branch with its intended base branch.
3. Review recent commits, open pull requests and related issues when available.
4. Read current implementation status and history documents.
5. Inspect changed modules and public interfaces.
6. Check the existence and relevance of tests for changed behavior.
7. Inspect CI configuration and available run results.
8. Run or verify the following when an execution environment is available:
   - `composer lint`
   - `composer analyse`
   - `composer test`
   - `composer test:csv-contract`
9. Classify features as complete, partial, unverified or not implemented.
10. Identify database, integration and manual UI checks still required.
11. Recommend one next reviewable development task.

## Required checks

- Common identity compatibility
- Agency hierarchy protection
- Referral attribution behavior
- Authentication and authorization
- External API and webhook compatibility
- Integration outbox recovery
- CSV contract compatibility
- Test coverage for changed behavior
- Documentation freshness

## Required output

1. Executive summary
2. Examined repository state
3. Completed work
4. Partially completed work
5. Remaining implementation
6. Detected problems and risks
7. Automated validation results
8. Manual validation still required
9. Human decisions required
10. Recommended next task

## Evidence rules

- Mark a statement as verified only when supported by code, tests, CI or current documentation.
- Label assumptions and inferences explicitly.
- Do not describe a feature as complete solely because a status file says it is complete.
- Do not describe tests as passing unless results were actually observed.

## Prohibited actions

- Do not change implementation while performing a progress check.
- Do not update status documents with unsupported claims.
- Do not ignore failing or unavailable validation.
- Do not merge or deploy as part of this skill.
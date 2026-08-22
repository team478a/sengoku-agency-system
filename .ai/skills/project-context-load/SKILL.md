# Skill: project-context-load

## Purpose

Load the authoritative project context before analysis, planning or code changes.

## Inputs

- Repository name
- Target branch
- Latest commit or pull request when supplied
- Requested task

## Procedure

1. Confirm the repository and target branch.
2. Record the latest commit under examination.
3. Read `.ai/PROJECT_CONTEXT.md`.
4. Read `.ai/BUSINESS_RULES.md`.
5. Read `.ai/DO_NOT_BREAK.md`.
6. Read `.ai/DEVELOPMENT_WORKFLOW.md`.
7. Read the latest implementation status, history, dependency map and known integration-readiness documents.
8. Inspect the files directly related to the requested task.
9. State any conflict between documentation and current code.
10. Produce a compact context summary before planning changes.

## Required output

- Repository and branch
- Latest examined commit
- Technical stack
- Relevant modules
- Applicable business rules
- Protected behavior
- Current known status
- Uncertainties or stale documentation
- Safe scope for the requested task

## Failure conditions

Stop and report a blocker when:

- The repository or branch cannot be resolved.
- Required context documents are missing and the business rule cannot be verified elsewhere.
- The requested operation would require production credentials or an unauthorized breaking change.

## Prohibited actions

- Do not modify code during context loading.
- Do not treat documentation as current without comparing it with the branch.
- Do not infer reward, hierarchy or identity behavior without evidence.
- Do not expose secrets or personal data in the output.
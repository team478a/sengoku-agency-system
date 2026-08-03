# Development Orchestrator Phase 1 Pilot

Created: 2026-08-04  
Pilot repository: `team478a/sengoku-agency-system`  
Pilot integration branch: `feature/v3.6.82-modular-monolith-foundation`

## 1. Objective

Build a single command channel where the owner states the desired business outcome and a manager agent performs the following workflow:

1. understand the request;
2. identify the affected project or projects;
3. inspect the current repository state;
4. create an implementation plan;
5. obtain an independent plan review;
6. split approved work into bounded Codex tasks;
7. execute safe tasks in parallel;
8. validate and independently review the resulting diffs;
9. create draft pull requests;
10. return one consolidated report and request human approval only at protected gates.

The owner should not need to coordinate separate project chats or manually rewrite instructions for each coding agent.

## 2. Phase 1 decision

Use a manager-style multi-agent design.

- The **Development Commander** owns the conversation and final answer.
- Specialist agents are invoked as bounded tools.
- Specialists do not independently change scope or communicate final decisions to the owner.
- Human approval is retained for merge, production deployment, production database work, destructive operations, authentication/SSO changes, identity-contract changes, referral-contract changes, and payment or commission changes.

This repository is the first pilot because it already contains the cross-system identity, referral, integration-log, outbox, retry, and operational repair responsibilities that the future orchestrator must protect.

## 3. Phase 1 scope

### Included

- Repository operating contract (`AGENTS.md`)
- Machine-readable project manifest (`PROJECT.yaml`)
- Reusable skills for status inspection, plan review, and Codex task dispatch
- Stable task request and task result schemas
- Approval and stop-condition definitions
- Pilot workflow and acceptance criteria
- Draft-PR-only delivery model

### Not included in this documentation pull request

- OpenAI API calls
- Persistent orchestrator service
- Automatic Codex execution
- GitHub webhook handling
- Queue infrastructure
- Secret provisioning
- Production access
- Automatic merge or deployment

## 4. Target production architecture

```text
Owner / Single Chat
        |
        v
Development Commander
        |
        +--> Project Registry
        +--> Repository Status Agent
        +--> Planning Agent
        +--> Independent Plan Review Agent
        +--> Approval Policy Engine
        +--> Codex Task Dispatcher
        |       +--> Worker A: repository/project 1
        |       +--> Worker B: repository/project 2
        |       +--> Worker C: tests/integration review
        +--> Independent Diff / QA Agent
        +--> GitHub Adapter
        +--> Consolidated Result Reporter
```

### Recommended service boundary

Create a dedicated repository after this pilot is approved:

`team478a/sengoku-development-orchestrator`

Recommended production stack:

- TypeScript / Node.js
- OpenAI Agents SDK for manager and specialists
- Codex SDK or `codex exec` for coding workers
- NestJS or a small Fastify service for the API
- PostgreSQL / Supabase for task, approval, and audit state
- Redis and BullMQ for worker queues
- GitHub App for repository, branch, pull-request, and webhook operations
- Docker-isolated worker runtime on DigitalOcean or AWS
- Vercel only for the operator UI, not for long-running coding workers

## 5. Agent responsibilities

### 5.1 Development Commander

Responsibilities:

- Receive the owner's request.
- Identify the business outcome and target project set.
- Select only the required specialist agents.
- Maintain task state and dependencies.
- Enforce approval policy.
- Consolidate evidence and results.
- Prevent duplicate or conflicting work.

The commander must not directly improvise implementation when repository evidence is missing. It should call the status agent first.

### 5.2 Project Router

Responsibilities:

- Match the request against the project registry.
- Identify one or more repositories.
- Identify cross-project dependencies.
- Return confidence and routing evidence.

Routing should use project manifests rather than repository names alone.

### 5.3 Repository Status Agent

Implemented in this pilot as:

`skills/project-status/SKILL.md`

Responsibilities:

- Resolve the active branch.
- Inspect recent commits, pull requests, documentation, migrations, CI, and affected code paths.
- Separate confirmed facts from assumptions.
- Identify missing implementation and environmental unknowns.
- Recommend the smallest executable next action.

### 5.4 Planning Agent

Responsibilities:

- Convert the requested outcome and status evidence into a bounded plan.
- Define scope, dependencies, acceptance criteria, validation, rollback, and approval gates.
- Create separate work units only when they are independently implementable.

The first MVP may use the commander to create the plan. A separate planning specialist can be introduced after evaluations show a measurable benefit.

### 5.5 Independent Plan Review Agent

Implemented in this pilot as:

`skills/plan-review/SKILL.md`

Responsibilities:

- Challenge assumptions and architecture choices.
- Verify protected domain contracts.
- Review database and integration safety.
- Validate the proposed parallelization.
- Return `approved`, `revise`, `blocked`, or `needs_approval`.

No critical implementation task may be dispatched before an `approved` verdict.

### 5.6 Codex Task Dispatcher

Implemented in this pilot as:

`skills/codex-task-dispatch/SKILL.md`

Responsibilities:

- Convert the approved plan into structured task envelopes.
- Assign base branch, work branch, scope, protected contracts, acceptance criteria, and validation.
- Enforce a maximum concurrency limit.
- Require a structured result.
- Prohibit merge and production actions.

### 5.7 Codex Implementation Worker

Responsibilities:

- Read repository instructions and the task envelope.
- Inspect only enough additional context to implement the bounded task.
- Create a dedicated branch or isolated worktree.
- Make the smallest coherent code change.
- Run discoverable validation.
- Return a structured result and draft PR or patch.

The worker is not authorized to broaden scope, rotate secrets, deploy, merge, or execute production changes.

### 5.8 Independent Diff and QA Agent

Responsibilities:

- Compare the final diff with the approved plan.
- Verify tests and acceptance criteria.
- Check for contract, security, migration, concurrency, and regression risks.
- Decide whether the draft PR is ready for human review.

The implementation worker must not be the sole reviewer of its own change.

## 6. Workflow state machine

```text
received
  -> routed
  -> status_collected
  -> planned
  -> plan_review
       -> revision_required -> planned
       -> blocked
       -> approval_required
       -> approved
  -> dispatched
  -> implementing
  -> validating
  -> diff_review
       -> revision_required -> implementing
       -> blocked
       -> approval_required
       -> ready_for_pr
  -> draft_pr_created
  -> awaiting_human_decision
  -> merged | rejected | cancelled
```

No automated transition may move directly from `draft_pr_created` to `merged` in Phase 1.

## 7. Parallel execution policy

Parallel execution is allowed only when:

- tasks use different repositories or isolated worktrees;
- tasks do not edit the same files or migrations;
- tasks do not define competing versions of the same API, event, schema, or interface;
- each task has independent acceptance criteria;
- integration order is explicit;
- the commander can cancel or pause downstream work when an upstream task fails.

Pilot limit for this repository: two implementation workers.

Cross-project example:

```text
Task A: agency-system API contract
Task B: market-side API client
Task C: integration test
```

Tasks A and B may run in parallel only after the shared contract is approved and frozen. Task C begins after both produce reviewable outputs.

## 8. Approval policy

### Automatically allowed

- Read-only inspection
- Planning and plan review
- Documentation changes
- Local implementation on an isolated branch
- Non-destructive validation
- Draft pull-request creation

### Human approval required

- Protected-branch merge
- Production deployment
- Production migration or data repair
- Destructive operation
- Secret creation, replacement, or rotation
- Authentication, authorization, or SSO behavior change
- `common_user_id` contract change
- Referral-attribution or agency-history contract change
- Payment or commission calculation change
- Breaking external API, webhook, or event change
- Worker permission escalation

## 9. Orchestrator data model

The dedicated orchestrator should minimally store:

### `projects`

- `id`
- `name`
- `repository`
- `default_branch`
- `integration_branch`
- `manifest_version`
- `criticality`
- `enabled`

### `tasks`

- `id`
- `parent_task_id`
- `project_id`
- `type`
- `status`
- `goal`
- `request_json`
- `result_json`
- `created_by`
- `created_at`
- `updated_at`

### `task_dependencies`

- `task_id`
- `depends_on_task_id`
- `dependency_type`

### `approvals`

- `id`
- `task_id`
- `gate_type`
- `status`
- `requested_reason`
- `approved_by`
- `approved_at`

### `agent_runs`

- `id`
- `task_id`
- `agent_role`
- `provider`
- `model`
- `status`
- `trace_id`
- `started_at`
- `completed_at`

### `artifacts`

- `id`
- `task_id`
- `kind`
- `repository`
- `branch`
- `commit_sha`
- `pull_request_number`
- `uri`

### `audit_events`

- `id`
- `task_id`
- `actor_type`
- `actor_id`
- `event_type`
- `payload_json`
- `created_at`

## 10. Minimal API contract

### Create task

`POST /api/tasks`

Input:

- owner request
- optional project hint
- optional priority
- optional deadline

Output:

- task ID
- routed project candidates
- initial status

### Read task

`GET /api/tasks/{taskId}`

Returns:

- current state
- agent activity
- evidence
- approvals
- artifacts
- next action

### Approve gate

`POST /api/tasks/{taskId}/approvals/{gateType}`

Requires an authenticated human actor and an audit record.

### Cancel task

`POST /api/tasks/{taskId}/cancel`

Cancellation must propagate to queued workers and prevent downstream dispatch.

### Event stream

`GET /api/tasks/{taskId}/events`

Use server-sent events or WebSocket streaming for operator progress. Persistent state remains in PostgreSQL.

## 11. Codex execution contract

The dispatcher produces a document that validates against:

`docs/orchestrator/task-request.schema.json`

The worker result validates against:

`docs/orchestrator/task-result.schema.json`

Recommended execution behavior:

- analysis tasks: read-only sandbox;
- implementation tasks: workspace-write sandbox;
- unrestricted execution: prohibited by default;
- final response: JSON Schema constrained;
- full event stream: retained for audit and debugging;
- final diff: reviewed by a different agent role;
- GitHub write permissions: isolated from model credentials where practical.

## 12. Security controls

1. Store secrets in a managed secret store, never in task prompts or repository files.
2. Give workers the least repository and runtime permissions required.
3. Separate code-generation credentials from GitHub write credentials.
4. Run repository-controlled code before exposing model/API credentials where possible.
5. Use isolated containers or worktrees per task.
6. Disable production network and database access in Phase 1.
7. Validate all agent-produced task and result payloads against schemas.
8. Preserve traces, tool calls, approvals, branch names, commits, and PR numbers in the audit log.
9. Add concurrency locks for repository, branch, migration namespace, and critical file sets.
10. Require explicit expiry and ownership for every task lock.

## 13. Phase 1 implementation sequence

### Step 1: repository readiness — completed in this pilot branch

- Add `AGENTS.md`.
- Add `PROJECT.yaml`.
- Add status, plan-review, and task-dispatch skills.
- Add task request/result schemas.

### Step 2: create the dedicated orchestrator repository

Create `team478a/sengoku-development-orchestrator` with:

```text
apps/
  api/
  operator-ui/
  worker/
packages/
  agent-runtime/
  project-registry/
  github-adapter/
  codex-adapter/
  approval-policy/
  task-contracts/
docs/
  architecture/
  operations/
```

### Step 3: single-project read-only prototype

- Register only this agency-system project.
- Accept a natural-language request.
- Run routing and repository-status analysis.
- Produce a reviewed plan.
- Do not implement code yet.
- Persist task state, evidence, traces, and review verdict.

### Step 4: bounded implementation prototype

- Add one workspace-write Codex worker.
- Create a task branch.
- Implement documentation or a low-risk test-only task.
- Validate structured output.
- Open a draft PR.
- Stop before merge.

### Step 5: two-worker parallel pilot

- Select two non-overlapping low-risk tasks.
- Verify lock acquisition and conflict prevention.
- Run independent diff review.
- Consolidate results into one owner-facing report.

### Step 6: add second repository

Recommended next project after the pilot:

- Sengoku Market or OVEW Wallet, because both depend on common identity and referral contracts.

## 14. Phase 1 acceptance criteria

The pilot is successful when all of the following are demonstrated:

1. The owner submits one natural-language request.
2. The system identifies the correct project.
3. Status analysis cites current repository evidence.
4. A plan is produced with scope, acceptance criteria, validation, and approval gates.
5. An independent reviewer approves or rejects the plan with reasons.
6. The dispatcher produces a schema-valid task.
7. A Codex worker performs a bounded low-risk change in an isolated branch.
8. Validation results are captured in a schema-valid result.
9. A different role reviews the final diff.
10. A draft PR is created.
11. The system stops before merge.
12. The owner receives one consolidated status and approval request.

## 15. Evaluation cases

Before expanding beyond the pilot, test at least:

- a read-only status request;
- a safe documentation change;
- a request with missing evidence;
- a request that requires a database migration;
- a request that attempts to change `common_user_id` semantics;
- a request that requires production access;
- two tasks that can run in parallel;
- two tasks that conflict on the same migration or file;
- a worker result with failed validation;
- a plan-review rejection and revision loop.

## 16. Immediate next action

After this documentation pull request is reviewed, create the dedicated orchestrator repository and implement the single-project read-only prototype. Before writing or running OpenAI API-backed code, complete secure API credential selection and storage through the approved credential workflow.

## 17. Reference documentation

- OpenAI Agents SDK overview: https://developers.openai.com/api/docs/guides/agents
- Orchestration and handoffs: https://developers.openai.com/api/docs/guides/agents/orchestration
- Guardrails and human review: https://developers.openai.com/api/docs/guides/agents/guardrails-approvals
- Sandbox agents: https://developers.openai.com/api/docs/guides/agents/sandboxes
- Codex SDK: https://learn.chatgpt.com/docs/codex-sdk
- Codex non-interactive mode: https://learn.chatgpt.com/docs/non-interactive-mode
- Build skills: https://learn.chatgpt.com/docs/build-skills

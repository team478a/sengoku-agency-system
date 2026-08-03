# Sengoku Development Orchestrator — Phase 1

A read-only multi-agent command center pilot. One request is routed to registered projects, current GitHub evidence is collected, specialist agents create status assessments and an implementation plan, and an independent reviewer checks the plan before the workflow stops for human approval.

## Phase 1 boundaries

- Reads repository metadata and selected policy files.
- Uses the OpenAI Agents SDK for routing, analysis, planning, and independent review.
- Persists task state and audit events in SQLite.
- Never writes code, runs Codex, merges, deploys, migrates a database, or modifies secrets.

## Required environment

Copy `.env.example` to `.env.local` and set:

- `OPENAI_API_KEY`: required for live task analysis.
- `GITHUB_TOKEN`: recommended for private repositories and rate limits. Use read-only repository permissions.

Never commit `.env.local` or any secret value.

## Local setup

```bash
uv sync --extra dev
uv run python main.py --smoke
uv run pytest
uv run python main.py --serve
```

Health check:

```bash
curl http://127.0.0.1:8421/health
```

Create a task:

```bash
curl -X POST http://127.0.0.1:8421/v1/tasks \
  -H 'Content-Type: application/json' \
  -d '{
    "goal": "代理店システムの現在の実装を確認し、次の安全な作業計画を作成してください",
    "project_ids": ["sengoku-agency-system"],
    "execution_mode": "analyze_only"
  }'
```

## Architecture

The workflow deliberately mixes model-based and code-based orchestration:

- Router agent: chooses registered projects.
- GitHub evidence collector: deterministic read-only tool.
- Status analyst agents: run per project and may execute concurrently.
- Planner agent: creates dependency-aware steps and parallel groups.
- Independent reviewer agent: approves, revises, or blocks.
- Policy layer: always requires human approval in Phase 1.

## Extraction to a dedicated repository

The entire `orchestrator/` directory is self-contained. When `team478a/sengoku-development-orchestrator` is created, move this directory to that repository root and adjust the workflow working directory.

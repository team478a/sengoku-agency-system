from __future__ import annotations

import argparse
import json
import os
from functools import lru_cache

import uvicorn
from fastapi import FastAPI, HTTPException

from app.config import get_settings
from app.github_client import GitHubReadClient
from app.models import TaskRequest, TaskResult
from app.pipeline import OrchestratorService
from app.registry import load_registry
from app.store import TaskStore

app = FastAPI(title="Sengoku Development Orchestrator", version="0.1.0")


@lru_cache
def get_service() -> OrchestratorService:
    settings = get_settings()
    registry = load_registry(settings.project_registry_path)
    return OrchestratorService(
        registry=registry,
        github=GitHubReadClient(settings.github_api_url, settings.github_token),
        store=TaskStore(settings.orchestrator_db_path),
        model=settings.openai_model,
    )


@app.get("/health")
def health() -> dict[str, object]:
    settings = get_settings()
    return {
        "status": "ok",
        "mode": "read_only",
        "openai_configured": bool(settings.openai_api_key),
        "github_token_configured": bool(settings.github_token),
    }


@app.post("/v1/tasks", response_model=TaskResult)
async def create_task(request: TaskRequest) -> TaskResult:
    settings = get_settings()
    if not settings.openai_api_key:
        raise HTTPException(status_code=503, detail="OPENAI_API_KEY is not configured")
    try:
        return await get_service().analyze(request)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
    except Exception as exc:
        raise HTTPException(status_code=502, detail=f"Orchestrator analysis failed: {exc}") from exc


@app.get("/v1/tasks/{task_id}")
def get_task(task_id: str) -> dict[str, object]:
    task = get_service().store.get_task(task_id)
    if task is None:
        raise HTTPException(status_code=404, detail="Task not found")
    return task


def smoke() -> int:
    settings = get_settings()
    registry = load_registry(settings.project_registry_path)
    store = TaskStore(settings.orchestrator_db_path)
    payload = {
        "status": "ok",
        "projects": [project.id for project in registry.projects],
        "database": str(store.path),
        "openai_configured": bool(settings.openai_api_key),
    }
    print(json.dumps(payload, ensure_ascii=False))
    return 0


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--serve", action="store_true")
    parser.add_argument("--smoke", action="store_true")
    args = parser.parse_args()

    if args.smoke:
        return smoke()
    if args.serve or os.getenv("PORT"):
        settings = get_settings()
        uvicorn.run("main:app", host="0.0.0.0", port=settings.port, reload=False)
        return 0
    parser.print_help()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

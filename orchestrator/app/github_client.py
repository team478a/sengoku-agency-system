from __future__ import annotations

import base64
from typing import Any

import httpx

from .models import ProjectSpec, RepositoryEvidence


class GitHubReadClient:
    def __init__(self, api_url: str, token: str | None = None) -> None:
        self.api_url = api_url.rstrip("/")
        self.headers = {
            "Accept": "application/vnd.github+json",
            "X-GitHub-Api-Version": "2022-11-28",
            "User-Agent": "sengoku-development-orchestrator/0.1",
        }
        if token:
            self.headers["Authorization"] = f"Bearer {token}"

    async def _get(self, path: str, params: dict[str, str] | None = None) -> Any:
        async with httpx.AsyncClient(
            base_url=self.api_url,
            headers=self.headers,
            timeout=20.0,
        ) as client:
            response = await client.get(path, params=params)
            response.raise_for_status()
            return response.json()

    async def _read_text_file(self, repository: str, path: str, ref: str) -> str | None:
        try:
            payload = await self._get(
                f"/repos/{repository}/contents/{path}",
                params={"ref": ref},
            )
        except httpx.HTTPStatusError as exc:
            if exc.response.status_code == 404:
                return None
            raise

        if payload.get("encoding") != "base64" or "content" not in payload:
            return None
        return base64.b64decode(payload["content"]).decode("utf-8", errors="replace")

    async def inspect_project(self, project: ProjectSpec) -> RepositoryEvidence:
        repository = await self._get(f"/repos/{project.repository}")
        inspected_ref = project.working_branch or project.default_branch
        commit = await self._get(f"/repos/{project.repository}/commits/{inspected_ref}")
        pulls = await self._get(
            f"/repos/{project.repository}/pulls",
            params={"state": "open", "per_page": "20"},
        )
        agents_md = await self._read_text_file(project.repository, "AGENTS.md", inspected_ref)
        project_yaml = await self._read_text_file(project.repository, "PROJECT.yaml", inspected_ref)

        warnings: list[str] = []
        if not agents_md:
            warnings.append("AGENTS.md was not found on the inspected ref")
        if not project_yaml:
            warnings.append("PROJECT.yaml was not found on the inspected ref")

        return RepositoryEvidence(
            project_id=project.id,
            repository=project.repository,
            inspected_ref=inspected_ref,
            default_branch=repository["default_branch"],
            latest_commit_sha=commit["sha"],
            latest_commit_message=commit["commit"]["message"].splitlines()[0],
            open_pull_requests=[
                {
                    "number": pull["number"],
                    "title": pull["title"],
                    "draft": pull.get("draft", False),
                    "head": pull["head"]["ref"],
                    "base": pull["base"]["ref"],
                }
                for pull in pulls
            ],
            agents_md=agents_md,
            project_yaml=project_yaml,
            warnings=warnings,
        )

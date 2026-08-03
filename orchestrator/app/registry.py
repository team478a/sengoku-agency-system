from __future__ import annotations

import json
from pathlib import Path

from pydantic import BaseModel

from .models import ProjectSpec


class ProjectRegistry(BaseModel):
    projects: list[ProjectSpec]

    def by_id(self) -> dict[str, ProjectSpec]:
        return {project.id: project for project in self.projects}


def load_registry(path: Path) -> ProjectRegistry:
    payload = json.loads(path.read_text(encoding="utf-8"))
    return ProjectRegistry.model_validate(payload)

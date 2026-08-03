from __future__ import annotations

import asyncio
import json
from uuid import uuid4

from agents import Runner

from .agents import (
    build_planner_agent,
    build_reviewer_agent,
    build_router_agent,
    build_status_agent,
)
from .github_client import GitHubReadClient
from .models import (
    ImplementationPlan,
    PlanReview,
    RepositoryEvidence,
    RoutingDecision,
    StatusAssessment,
    TaskRequest,
    TaskResult,
)
from .policy import approval_reasons
from .registry import ProjectRegistry
from .store import TaskStore


class OrchestratorService:
    def __init__(
        self,
        registry: ProjectRegistry,
        github: GitHubReadClient,
        store: TaskStore,
        model: str | None = None,
    ) -> None:
        self.registry = registry
        self.github = github
        self.store = store
        self.router_agent = build_router_agent(model)
        self.status_agent = build_status_agent(model)
        self.planner_agent = build_planner_agent(model)
        self.reviewer_agent = build_reviewer_agent(model)

    async def _route(self, request: TaskRequest) -> RoutingDecision:
        if request.project_ids:
            return RoutingDecision(
                project_ids=request.project_ids,
                rationale="Project scope was explicitly supplied by the user.",
                requires_cross_project_coordination=len(request.project_ids) > 1,
            )
        prompt = json.dumps(
            {
                "request": request.model_dump(),
                "project_registry": [project.model_dump() for project in self.registry.projects],
            },
            ensure_ascii=False,
        )
        result = await Runner.run(self.router_agent, prompt)
        return result.final_output

    def _validate_projects(self, routing: RoutingDecision) -> list:
        projects_by_id = self.registry.by_id()
        unknown = [project_id for project_id in routing.project_ids if project_id not in projects_by_id]
        if unknown:
            raise ValueError(f"Router selected unknown project IDs: {unknown}")
        if not routing.project_ids:
            raise ValueError("No target project could be determined")
        return [projects_by_id[project_id] for project_id in routing.project_ids]

    async def _assess(self, evidence: RepositoryEvidence) -> StatusAssessment:
        result = await Runner.run(
            self.status_agent,
            json.dumps(evidence.model_dump(), ensure_ascii=False),
        )
        assessment: StatusAssessment = result.final_output
        if assessment.project_id != evidence.project_id:
            assessment = assessment.model_copy(update={"project_id": evidence.project_id})
        return assessment

    async def _plan(
        self,
        request: TaskRequest,
        assessments: list[StatusAssessment],
        revision_feedback: PlanReview | None = None,
    ) -> ImplementationPlan:
        payload = {
            "request": request.model_dump(),
            "assessments": [assessment.model_dump() for assessment in assessments],
            "revision_feedback": revision_feedback.model_dump() if revision_feedback else None,
        }
        result = await Runner.run(self.planner_agent, json.dumps(payload, ensure_ascii=False))
        return result.final_output

    async def _review(
        self,
        request: TaskRequest,
        evidence: list[RepositoryEvidence],
        plan: ImplementationPlan,
    ) -> PlanReview:
        payload = {
            "request": request.model_dump(),
            "evidence": [item.model_dump() for item in evidence],
            "plan": plan.model_dump(),
        }
        result = await Runner.run(self.reviewer_agent, json.dumps(payload, ensure_ascii=False))
        return result.final_output

    async def analyze(self, request: TaskRequest) -> TaskResult:
        task_id = str(uuid4())
        self.store.create_task(task_id, request.model_dump())
        self.store.update_task(task_id, "analyzing")

        try:
            routing = await self._route(request)
            projects = self._validate_projects(routing)
            self.store.add_event(task_id, "routing_completed", routing.model_dump())

            evidence = await asyncio.gather(
                *(self.github.inspect_project(project) for project in projects)
            )
            self.store.add_event(
                task_id,
                "repository_evidence_collected",
                {"project_ids": [item.project_id for item in evidence]},
            )

            assessments = await asyncio.gather(*(self._assess(item) for item in evidence))
            plan = await self._plan(request, list(assessments))
            review = await self._review(request, list(evidence), plan)

            if review.verdict == "revise":
                revised_plan = await self._plan(request, list(assessments), review)
                revised_review = await self._review(request, list(evidence), revised_plan)
                plan, review = revised_plan, revised_review

            reasons = approval_reasons(plan, review)
            state = "blocked" if review.verdict == "block" else "awaiting_approval"
            result = TaskResult(
                task_id=task_id,
                state=state,
                routing=routing,
                evidence=list(evidence),
                assessments=list(assessments),
                plan=plan,
                review=review,
                approval_required=True,
                approval_reasons=reasons,
            )
            self.store.update_task(task_id, state, result.model_dump(mode="json"))
            self.store.add_event(task_id, "analysis_completed", {"state": state})
            return result
        except Exception as exc:
            self.store.update_task(task_id, "failed")
            self.store.add_event(task_id, "analysis_failed", {"error": str(exc)})
            raise

from __future__ import annotations

from enum import StrEnum
from typing import Any, Literal

from pydantic import BaseModel, Field


class RiskLevel(StrEnum):
    LOW = "low"
    MEDIUM = "medium"
    HIGH = "high"
    CRITICAL = "critical"


class ProjectSpec(BaseModel):
    id: str
    name: str
    repository: str
    default_branch: str = "main"
    working_branch: str | None = None
    criticality: RiskLevel = RiskLevel.MEDIUM
    protected_contracts: list[str] = Field(default_factory=list)
    approval_required_for: list[str] = Field(default_factory=list)


class TaskRequest(BaseModel):
    goal: str = Field(min_length=3, max_length=10_000)
    project_ids: list[str] = Field(default_factory=list)
    constraints: list[str] = Field(default_factory=list)
    acceptance_criteria: list[str] = Field(default_factory=list)
    execution_mode: Literal["analyze_only", "plan_only"] = "analyze_only"


class RoutingDecision(BaseModel):
    project_ids: list[str]
    rationale: str
    requires_cross_project_coordination: bool = False
    missing_information: list[str] = Field(default_factory=list)


class RepositoryEvidence(BaseModel):
    project_id: str
    repository: str
    inspected_ref: str
    default_branch: str
    latest_commit_sha: str
    latest_commit_message: str
    open_pull_requests: list[dict[str, Any]] = Field(default_factory=list)
    agents_md: str | None = None
    project_yaml: str | None = None
    warnings: list[str] = Field(default_factory=list)


class StatusAssessment(BaseModel):
    project_id: str
    summary: str
    confirmed_facts: list[str]
    risks: list[str] = Field(default_factory=list)
    blockers: list[str] = Field(default_factory=list)
    recommended_next_actions: list[str] = Field(default_factory=list)


class PlanStep(BaseModel):
    id: str
    title: str
    project_id: str
    objective: str
    dependencies: list[str] = Field(default_factory=list)
    expected_files_or_components: list[str] = Field(default_factory=list)
    verification: list[str] = Field(default_factory=list)
    risk_level: RiskLevel = RiskLevel.MEDIUM
    approval_gate: bool = False
    parallel_group: str | None = None


class ImplementationPlan(BaseModel):
    objective: str
    assumptions: list[str] = Field(default_factory=list)
    steps: list[PlanStep]
    rollback_strategy: list[str] = Field(default_factory=list)
    completion_criteria: list[str] = Field(default_factory=list)


class PlanReview(BaseModel):
    verdict: Literal["approve", "revise", "block"]
    summary: str
    findings: list[str] = Field(default_factory=list)
    required_changes: list[str] = Field(default_factory=list)
    risk_level: RiskLevel = RiskLevel.MEDIUM


class TaskResult(BaseModel):
    task_id: str
    state: Literal[
        "received",
        "analyzing",
        "awaiting_approval",
        "blocked",
        "failed",
    ]
    routing: RoutingDecision
    evidence: list[RepositoryEvidence]
    assessments: list[StatusAssessment]
    plan: ImplementationPlan
    review: PlanReview
    approval_required: bool
    approval_reasons: list[str] = Field(default_factory=list)

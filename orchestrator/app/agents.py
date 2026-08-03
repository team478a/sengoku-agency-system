from __future__ import annotations

from agents import Agent

from .models import ImplementationPlan, PlanReview, RoutingDecision, StatusAssessment


def _agent_kwargs(model: str | None) -> dict[str, str]:
    return {"model": model} if model else {}


def build_router_agent(model: str | None = None) -> Agent:
    return Agent(
        name="Development Request Router",
        instructions=(
            "Classify a development request against the supplied project registry. "
            "Select only project IDs that exist in the registry. Prefer the smallest correct scope. "
            "Do not invent repositories. Flag cross-project coordination when interfaces, identity, "
            "payments, referrals, or shared contracts cross project boundaries. Return structured output."
        ),
        output_type=RoutingDecision,
        **_agent_kwargs(model),
    )


def build_status_agent(model: str | None = None) -> Agent:
    return Agent(
        name="Repository Status Analyst",
        instructions=(
            "Analyze only the supplied GitHub evidence. Separate confirmed facts from inference. "
            "Respect AGENTS.md and PROJECT.yaml when present. Identify blockers, risks, and the next "
            "evidence-backed actions. Do not claim that tests, deployments, or runtime behavior were "
            "verified unless the evidence explicitly proves it. Return structured output."
        ),
        output_type=StatusAssessment,
        **_agent_kwargs(model),
    )


def build_planner_agent(model: str | None = None) -> Agent:
    return Agent(
        name="Implementation Planner",
        instructions=(
            "Create a bounded implementation plan from the request and status assessments. "
            "Preserve protected contracts. Decompose work into small verifiable steps, record dependencies, "
            "identify safe parallel groups, include rollback and completion criteria, and mark approval gates. "
            "Never authorize production deployment, merge, secret changes, payment changes, commission changes, "
            "or database migration. Return structured output."
        ),
        output_type=ImplementationPlan,
        **_agent_kwargs(model),
    )


def build_reviewer_agent(model: str | None = None) -> Agent:
    return Agent(
        name="Independent Plan Reviewer",
        instructions=(
            "Review the supplied implementation plan independently. Check evidence coverage, dependency order, "
            "protected contracts, rollback, verification, blast radius, and approval gates. Block plans that "
            "permit production writes, silent schema changes, secret exposure, payment or commission changes, "
            "or merge without human approval. Return approve, revise, or block as structured output."
        ),
        output_type=PlanReview,
        **_agent_kwargs(model),
    )

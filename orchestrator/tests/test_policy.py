from pathlib import Path

from app.models import ImplementationPlan, PlanReview, PlanStep, RiskLevel
from app.policy import approval_reasons
from app.registry import load_registry


def test_read_only_pilot_always_requires_approval() -> None:
    plan = ImplementationPlan(
        objective="Prepare a safe code change",
        steps=[
            PlanStep(
                id="S1",
                title="Implement",
                project_id="sengoku-agency-system",
                objective="Change code",
                approval_gate=True,
            )
        ],
    )
    review = PlanReview(verdict="approve", summary="Safe", risk_level=RiskLevel.LOW)
    reasons = approval_reasons(plan, review)
    assert any("read-only" in reason for reason in reasons)
    assert any("S1" in reason for reason in reasons)


def test_registry_contains_pilot_project() -> None:
    registry = load_registry(Path("data/projects.json"))
    assert "sengoku-agency-system" in registry.by_id()

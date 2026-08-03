from __future__ import annotations

from .models import ImplementationPlan, PlanReview, RiskLevel


ALWAYS_APPROVAL_TERMS = {
    "code_write",
    "merge",
    "production_deploy",
    "database_migration",
    "secret_change",
    "payment_change",
    "commission_change",
}


def approval_reasons(plan: ImplementationPlan, review: PlanReview) -> list[str]:
    reasons: list[str] = ["The Phase 1 pilot is read-only and cannot execute implementation tasks."]
    if review.verdict != "approve":
        reasons.append(f"Independent review verdict is {review.verdict}.")
    if review.risk_level in {RiskLevel.HIGH, RiskLevel.CRITICAL}:
        reasons.append(f"Independent review risk is {review.risk_level}.")
    for step in plan.steps:
        if step.approval_gate:
            reasons.append(f"Step {step.id} declares a human approval gate.")
        if step.risk_level in {RiskLevel.HIGH, RiskLevel.CRITICAL}:
            reasons.append(f"Step {step.id} risk is {step.risk_level}.")
    return list(dict.fromkeys(reasons))

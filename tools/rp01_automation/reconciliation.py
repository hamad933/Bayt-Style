from __future__ import annotations
from dataclasses import dataclass
from typing import Iterable, Mapping

APPLIED = "APPLIED"
NOT_APPLIED = "NOT_APPLIED"
UNKNOWN_PRIOR_WRITE_OUTCOME = "UNKNOWN_PRIOR_WRITE_OUTCOME"
RECONCILIATION_REQUIRED = "RECONCILIATION_REQUIRED"


@dataclass(frozen=True)
class ReconciliationResult:
    classification: str
    reason: str


def reconcile_create_session(*, target_request_id: str, observed_sessions: Iterable[Mapping[str, object]]) -> ReconciliationResult:
    matches = []
    for session in observed_sessions:
        title = str(session.get("title") or "")
        if f"request:{target_request_id}" in title:
            matches.append(session)
    if len(matches) == 1:
        return ReconciliationResult(APPLIED, "one authoritative session carries the target request marker")
    if len(matches) > 1:
        return ReconciliationResult(UNKNOWN_PRIOR_WRITE_OUTCOME, "multiple sessions carry the target request marker")
    return ReconciliationResult(RECONCILIATION_REQUIRED, "absence alone is insufficient proof that a create write was not applied")


def reconcile_activity_effect(*, marker: str, activities: Iterable[Mapping[str, object]]) -> ReconciliationResult:
    count = sum(marker in str(item) for item in activities)
    if count == 1:
        return ReconciliationResult(APPLIED, "one authoritative activity contains the effect marker")
    if count > 1:
        return ReconciliationResult(UNKNOWN_PRIOR_WRITE_OUTCOME, "multiple authoritative activities contain the effect marker")
    return ReconciliationResult(RECONCILIATION_REQUIRED, "no effect marker observed; provider state needs bounded follow-up before NOT_APPLIED")

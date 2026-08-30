from __future__ import annotations

import re
from dataclasses import dataclass
from typing import Any, Mapping

SCHEMA_VERSION = "rp01.automation.request/v1"
PROJECT_ID = "RP01"
REPOSITORY = "hamad933/Bayt-Style"
SHA40 = re.compile(r"^[0-9a-f]{40}$")
SAFE_ID = re.compile(r"^[A-Za-z0-9][A-Za-z0-9._:/-]{0,127}$")

READ_ACTIONS = frozenset({"list_sources", "list_sessions", "get_session", "list_activities"})
MUTATION_ACTIONS = frozenset({"create_session", "send_message", "approve_plan"})
RECONCILIATION_ACTIONS = frozenset({"reconcile_create_session", "reconcile_send_message", "reconcile_approve_plan"})
ACTIONS = READ_ACTIONS | MUTATION_ACTIONS | RECONCILIATION_ACTIONS

COMMON_REQUIRED = {
    "schema_version", "request_id", "project_id", "controller_id", "logical_task_id",
    "action", "repository",
}
COMMON_OPTIONAL = {
    "write_domain", "starting_branch", "expected_sha", "session_id",
    "expected_session_state", "expected_session_update_time", "instruction_ref",
    "instruction_digest", "authority_ref", "authority_event", "target_request_id",
    "target_intent_identity", "prompt", "require_plan_approval",
}

ACTION_REQUIRED = {
    "list_sources": set(),
    "list_sessions": set(),
    "get_session": {"session_id"},
    "list_activities": {"session_id"},
    "create_session": {"write_domain", "starting_branch", "expected_sha", "prompt"},
    "send_message": {"write_domain", "session_id", "expected_session_state", "expected_session_update_time", "prompt"},
    "approve_plan": {"write_domain", "session_id", "expected_session_state", "expected_session_update_time"},
    "reconcile_create_session": {"write_domain", "target_request_id", "target_intent_identity"},
    "reconcile_send_message": {"write_domain", "session_id", "target_request_id", "target_intent_identity"},
    "reconcile_approve_plan": {"write_domain", "session_id", "target_request_id", "target_intent_identity"},
}


@dataclass(frozen=True)
class ValidatedRequest:
    data: dict[str, Any]

    @property
    def action(self) -> str:
        return self.data["action"]

    @property
    def is_mutation(self) -> bool:
        return self.action in MUTATION_ACTIONS

    @property
    def is_reconciliation(self) -> bool:
        return self.action in RECONCILIATION_ACTIONS


class RequestValidationError(ValueError):
    pass


def _require_safe_id(name: str, value: Any) -> None:
    if not isinstance(value, str) or not SAFE_ID.fullmatch(value):
        raise RequestValidationError(f"{name} is not a valid bounded identifier")


def validate_request(payload: Mapping[str, Any]) -> ValidatedRequest:
    if not isinstance(payload, Mapping):
        raise RequestValidationError("request must be an object")
    data = dict(payload)
    missing = COMMON_REQUIRED - data.keys()
    if missing:
        raise RequestValidationError(f"missing required keys: {','.join(sorted(missing))}")

    action = data.get("action")
    if action not in ACTIONS:
        raise RequestValidationError("unsupported action")

    allowed = COMMON_REQUIRED | COMMON_OPTIONAL
    unknown = set(data) - allowed
    if unknown:
        raise RequestValidationError(f"unknown keys: {','.join(sorted(unknown))}")

    action_missing = ACTION_REQUIRED[action] - data.keys()
    if action_missing:
        raise RequestValidationError(f"missing action keys: {','.join(sorted(action_missing))}")

    if data["schema_version"] != SCHEMA_VERSION:
        raise RequestValidationError("schema_version mismatch")
    if data["project_id"] != PROJECT_ID:
        raise RequestValidationError("project_id mismatch")
    if data["repository"] != REPOSITORY:
        raise RequestValidationError("repository mismatch")

    for key in ("request_id", "controller_id", "logical_task_id"):
        _require_safe_id(key, data[key])
    for key in ("write_domain", "session_id", "target_request_id"):
        if key in data and data[key] not in ("", None):
            _require_safe_id(key, data[key])

    if "expected_sha" in data and data["expected_sha"] not in ("", None):
        if not isinstance(data["expected_sha"], str) or not SHA40.fullmatch(data["expected_sha"]):
            raise RequestValidationError("expected_sha must be lowercase 40-character hex")

    if "instruction_ref" in data and data["instruction_ref"] not in ("", None):
        if not isinstance(data["instruction_ref"], str) or not re.fullmatch(r"drive:[A-Za-z0-9_-]{10,}", data["instruction_ref"]):
            raise RequestValidationError("instruction_ref must be an opaque drive:<file_id> reference")

    if "instruction_digest" in data and data["instruction_digest"] not in ("", None):
        if not isinstance(data["instruction_digest"], str) or not re.fullmatch(r"[0-9a-f]{64}", data["instruction_digest"]):
            raise RequestValidationError("instruction_digest must be sha256 hex")

    if action == "create_session" and data.get("require_plan_approval") is not True:
        raise RequestValidationError("create_session requires require_plan_approval=true")

    if action in MUTATION_ACTIONS and not data.get("authority_ref"):
        raise RequestValidationError("mutation requires authority_ref")

    return ValidatedRequest(data)

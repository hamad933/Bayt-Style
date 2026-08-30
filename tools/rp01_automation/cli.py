from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path
from typing import Any

from .evidence import write_json
from .identity import effect_key, intent_identity, request_key
from .jules import JulesClient, JulesError
from .schema import READ_ACTIONS, RequestValidationError, validate_request


def _load_request(path: str) -> dict:
    return json.loads(Path(path).read_text(encoding="utf-8"))


def _pick(obj: dict[str, Any], keys: tuple[str, ...]) -> dict[str, Any]:
    return {key: obj[key] for key in keys if key in obj}


def _provider_summary(action: str, provider: dict[str, Any]) -> dict[str, Any]:
    """Return evidence-safe provider metadata; never persist prompts/agent message text."""
    session_keys = ("name", "id", "state", "createTime", "updateTime")
    activity_keys = ("name", "id", "originator", "createTime")
    if action == "list_sources":
        sources = []
        for source in provider.get("sources", []) or []:
            github_repo = source.get("githubRepo") or {}
            sources.append({
                **_pick(source, ("name", "id")),
                "githubRepo": _pick(github_repo, ("owner", "repo", "isPrivate", "defaultBranch")),
            })
        return {"sources": sources, "nextPageTokenPresent": bool(provider.get("nextPageToken"))}
    if action == "list_sessions":
        return {
            "sessions": [_pick(item, session_keys) for item in provider.get("sessions", []) or []],
            "nextPageTokenPresent": bool(provider.get("nextPageToken")),
        }
    if action == "get_session":
        return _pick(provider, session_keys + ("sourceContext",))
    if action == "list_activities":
        activities = []
        for item in provider.get("activities", []) or []:
            summary = _pick(item, activity_keys)
            summary["activityKinds"] = sorted(
                key
                for key in item
                if key not in {"name", "id", "originator", "description", "createTime"} and item.get(key) is not None
            )
            activities.append(summary)
        return {"activities": activities, "nextPageTokenPresent": bool(provider.get("nextPageToken"))}
    raise RequestValidationError("unsupported read action")


def cmd_validate(args: argparse.Namespace) -> int:
    request = validate_request(_load_request(args.request))
    result = {
        "classification": "PASS",
        "request_id": request.data["request_id"],
        "action": request.action,
        "request_key": request_key(request.data["request_id"]),
        "intent_identity": intent_identity(request.data),
        "effect_key": None if not request.data.get("write_domain") else effect_key("RP01", request.data["write_domain"]),
        "operation_kind": "READ" if request.action in READ_ACTIONS else ("RECONCILIATION" if request.is_reconciliation else "MUTATION"),
    }
    write_json(args.output, result)
    print(json.dumps(result, sort_keys=True))
    return 0


def cmd_inspect(args: argparse.Namespace) -> int:
    request = validate_request(_load_request(args.request))
    if request.action not in READ_ACTIONS:
        raise RequestValidationError("inspect command accepts read actions only")
    client = JulesClient.from_environment()
    if request.action == "list_sources":
        provider = client.list_sources()
    elif request.action == "list_sessions":
        provider = client.list_sessions()
    elif request.action == "get_session":
        provider = client.get_session(request.data["session_id"])
    else:
        provider = client.list_activities(request.data["session_id"])
    envelope = {
        "schema_version": "rp01.automation.evidence/v1",
        "project_id": "RP01",
        "request_id": request.data["request_id"],
        "action": request.action,
        "provider_mutation_performed": False,
        "provider_summary": _provider_summary(request.action, provider),
        "raw_prompt_or_agent_text_persisted": False,
        "classification": "PASS",
    }
    write_json(args.output, envelope)
    print(f"RP01_AUTOMATION_INSPECT=PASS request={request.data['request_id']}")
    return 0


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(prog="rp01-automation")
    sub = parser.add_subparsers(dest="command", required=True)
    validate = sub.add_parser("validate")
    validate.add_argument("--request", required=True)
    validate.add_argument("--output", required=True)
    validate.set_defaults(func=cmd_validate)
    inspect = sub.add_parser("inspect")
    inspect.add_argument("--request", required=True)
    inspect.add_argument("--output", required=True)
    inspect.set_defaults(func=cmd_inspect)
    return parser


def main() -> int:
    try:
        args = build_parser().parse_args()
        return int(args.func(args))
    except (RequestValidationError, JulesError, json.JSONDecodeError, OSError) as exc:
        print(f"RP01_AUTOMATION_ERROR={type(exc).__name__}:{exc}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())

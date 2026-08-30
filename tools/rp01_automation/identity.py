from __future__ import annotations
import hashlib
import json
from typing import Any, Mapping


def canonical_json(data: Mapping[str, Any]) -> bytes:
    return (json.dumps(dict(data), sort_keys=True, separators=(",", ":"), ensure_ascii=False) + "\n").encode("utf-8")


def sha256_hex(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def intent_identity(data: Mapping[str, Any]) -> str:
    return sha256_hex(canonical_json(data))


def request_key(request_id: str) -> str:
    return "req-" + sha256_hex(request_id.encode("utf-8"))[:24]


def effect_key(project_id: str, write_domain: str) -> str:
    raw = f"{project_id}\0{write_domain}".encode("utf-8")
    return "effect-" + sha256_hex(raw)[:24]

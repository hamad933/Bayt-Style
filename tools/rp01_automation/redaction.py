from __future__ import annotations
import re
from typing import Any

SECRET_KEYS = re.compile(r"(api[_-]?key|token|authorization|secret|password|cookie)", re.I)
GOOG_API_KEY = re.compile(r"\bAIza[0-9A-Za-z_-]{20,}\b")


def redact(value: Any) -> Any:
    if isinstance(value, dict):
        return {k: ("[REDACTED]" if SECRET_KEYS.search(str(k)) else redact(v)) for k, v in value.items()}
    if isinstance(value, list):
        return [redact(v) for v in value]
    if isinstance(value, tuple):
        return [redact(v) for v in value]
    if isinstance(value, str):
        return GOOG_API_KEY.sub("[REDACTED]", value)
    return value

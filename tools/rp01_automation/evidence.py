from __future__ import annotations
import json
from pathlib import Path
from typing import Any
from .redaction import redact


def write_json(path: str | Path, payload: Any) -> Path:
    target = Path(path)
    target.parent.mkdir(parents=True, exist_ok=True)
    safe = redact(payload)
    target.write_text(json.dumps(safe, sort_keys=True, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    return target

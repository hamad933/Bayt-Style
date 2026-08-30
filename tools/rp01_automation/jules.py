from __future__ import annotations
import json
import os
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass
from typing import Any

DEFAULT_BASE = "https://jules.googleapis.com/v1alpha"


class JulesError(RuntimeError):
    pass


@dataclass
class JulesClient:
    api_key: str
    base_url: str = DEFAULT_BASE
    timeout_seconds: float = 20.0

    @classmethod
    def from_environment(cls) -> "JulesClient":
        key = os.environ.get("JULES_API_KEY", "")
        if not key:
            raise JulesError("JULES_API_KEY is not provisioned")
        return cls(api_key=key, base_url=os.environ.get("JULES_API_BASE", DEFAULT_BASE))

    def _request(self, method: str, path: str, body: dict[str, Any] | None = None) -> Any:
        url = self.base_url.rstrip("/") + "/" + path.lstrip("/")
        data = None if body is None else json.dumps(body, separators=(",", ":")).encode("utf-8")
        req = urllib.request.Request(url, data=data, method=method)
        req.add_header("X-Goog-Api-Key", self.api_key)
        req.add_header("Accept", "application/json")
        if data is not None:
            req.add_header("Content-Type", "application/json")
        try:
            with urllib.request.urlopen(req, timeout=self.timeout_seconds) as response:
                raw = response.read()
                return {} if not raw else json.loads(raw.decode("utf-8"))
        except urllib.error.HTTPError as exc:
            raise JulesError(f"Jules HTTP {exc.code} for {method} {path}") from None
        except urllib.error.URLError as exc:
            raise JulesError(f"Jules transport failure for {method} {path}: {type(exc.reason).__name__}") from None

    def list_sources(self, page_size: int = 100) -> dict[str, Any]:
        return self._request("GET", f"sources?pageSize={int(page_size)}")

    def list_sessions(self, page_size: int = 100, page_token: str | None = None) -> dict[str, Any]:
        query = {"pageSize": str(int(page_size))}
        if page_token:
            query["pageToken"] = page_token
        return self._request("GET", "sessions?" + urllib.parse.urlencode(query))

    def get_session(self, session_id: str) -> dict[str, Any]:
        return self._request("GET", f"sessions/{urllib.parse.quote(session_id, safe='')}")

    def list_activities(self, session_id: str, page_size: int = 100) -> dict[str, Any]:
        sid = urllib.parse.quote(session_id, safe="")
        return self._request("GET", f"sessions/{sid}/activities?pageSize={int(page_size)}")

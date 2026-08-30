from __future__ import annotations

import os
import tempfile
import unittest

from tools.rp01_automation.cli import _provider_summary
from tools.rp01_automation.evidence import write_json
from tools.rp01_automation.identity import intent_identity
from tools.rp01_automation.reconciliation import APPLIED, RECONCILIATION_REQUIRED, UNKNOWN_PRIOR_WRITE_OUTCOME, reconcile_activity_effect, reconcile_create_session
from tools.rp01_automation.redaction import redact
from tools.rp01_automation.schema import RequestValidationError, validate_request


def base(action="list_sessions"):
    return {
        "schema_version": "rp01.automation.request/v1",
        "request_id": "req-001",
        "project_id": "RP01",
        "controller_id": "CENTRAL",
        "logical_task_id": "task-001",
        "action": action,
        "repository": "hamad933/Bayt-Style",
    }


class SchemaTests(unittest.TestCase):
    def test_valid_read(self):
        self.assertEqual(validate_request(base()).action, "list_sessions")

    def test_unknown_key_fails_closed(self):
        req = base()
        req["surprise"] = True
        with self.assertRaises(RequestValidationError):
            validate_request(req)

    def test_read_action_rejects_mutation_only_fields(self):
        req = base()
        req["prompt"] = "must not be accepted on read"
        with self.assertRaises(RequestValidationError):
            validate_request(req)

    def test_wrong_controller_fails_closed(self):
        req = base()
        req["controller_id"] = "A"
        with self.assertRaises(RequestValidationError):
            validate_request(req)

    def test_wrong_repository_fails_closed(self):
        req = base()
        req["repository"] = "other/repo"
        with self.assertRaises(RequestValidationError):
            validate_request(req)

    def test_create_requires_exact_sha_plan_approval_and_authority(self):
        req = base("create_session")
        req.update({
            "write_domain": "automation-test",
            "starting_branch": "work/example",
            "expected_sha": "a" * 40,
            "prompt": "bounded test",
            "authority_ref": "drive:authority-ref",
            "require_plan_approval": True,
        })
        self.assertTrue(validate_request(req).is_mutation)
        req["require_plan_approval"] = False
        with self.assertRaises(RequestValidationError):
            validate_request(req)

    def test_changed_replay_changes_intent(self):
        one = base()
        two = dict(one)
        two["logical_task_id"] = "task-002"
        self.assertNotEqual(intent_identity(one), intent_identity(two))

    def test_provider_summary_omits_prompt_title_and_activity_description(self):
        session = _provider_summary("get_session", {"id": "s1", "state": "COMPLETED", "prompt": "secret instruction", "title": "secret title"})
        self.assertNotIn("prompt", session)
        self.assertNotIn("title", session)
        activities = _provider_summary("list_activities", {"activities": [{"id": "a1", "description": "secret output", "agentMessaged": {"agentMessage": "secret"}}]})
        self.assertNotIn("description", activities["activities"][0])
        self.assertIn("agentMessaged", activities["activities"][0]["activityKinds"])


class RedactionTests(unittest.TestCase):
    def test_secret_fields_and_google_keys_are_redacted(self):
        safe = redact({"JULES_API_KEY": "should-not-appear", "message": "AIza" + "x" * 24})
        self.assertEqual(safe["JULES_API_KEY"], "[REDACTED]")
        self.assertNotIn("AIza", safe["message"])

    def test_evidence_writer_redacts(self):
        with tempfile.TemporaryDirectory() as d:
            path = write_json(os.path.join(d, "evidence.json"), {"token": "secret"})
            self.assertNotIn("secret", path.read_text())


class ReconciliationTests(unittest.TestCase):
    def test_unique_create_marker_is_applied(self):
        self.assertEqual(reconcile_create_session(target_request_id="abc", observed_sessions=[{"title": "RP01 request:abc task:x"}]).classification, APPLIED)

    def test_duplicate_create_marker_is_unknown(self):
        self.assertEqual(reconcile_create_session(target_request_id="abc", observed_sessions=[{"title": "request:abc"}, {"title": "request:abc"}]).classification, UNKNOWN_PRIOR_WRITE_OUTCOME)

    def test_missing_marker_does_not_claim_not_applied(self):
        self.assertEqual(reconcile_create_session(target_request_id="abc", observed_sessions=[]).classification, RECONCILIATION_REQUIRED)

    def test_activity_reconciliation(self):
        self.assertEqual(reconcile_activity_effect(marker="effect:1", activities=[{"description": "effect:1"}]).classification, APPLIED)


if __name__ == "__main__":
    unittest.main()

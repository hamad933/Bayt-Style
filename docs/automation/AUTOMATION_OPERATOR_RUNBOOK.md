# RP01 Automation Operator Runbook

Foundation checks:

```bash
bash tools/rp01_automation/setup.sh
python -m compileall -q tools/rp01_automation tests/Automation
python -m unittest discover -s tests/Automation -p "test_*.py" -v
```

After accepted integration on `main` and `JULES_API_KEY` provisioning, dispatch `RP01 Automation Shadow Inspect` only with a strict `list_sources`, `list_sessions`, `get_session`, or `list_activities` request. The artifact is evidence only and performs no Jules/Drive mutation.

Stop mutation progression on stale authority, missing secret, schema failure, provider ambiguity, unknown prior write outcome, conflicting writer, or remote-ref drift. Never retry an uncertain external write.

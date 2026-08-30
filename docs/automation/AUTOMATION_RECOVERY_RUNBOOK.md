# RP01 Automation Recovery Runbook

Current safe fallback is shadow/read-only mode.

For any uncertain mutation: record `UNKNOWN_PRIOR_WRITE_OUTCOME`; do not repeat the write; re-read exact Jules session/activity and GitHub remote ref; classify `APPLIED`, `RECONCILIATION_REQUIRED`, or `UNKNOWN_PRIOR_WRITE_OUTCOME`; retry only after authoritative proof of `NOT_APPLIED`.

Future mutation activation requires a repository-controlled kill switch defaulting disabled. Provider outage must not change product truth, accepted Git refs, or Drive state.

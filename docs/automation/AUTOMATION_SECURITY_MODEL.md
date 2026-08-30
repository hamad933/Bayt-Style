# RP01 Automation Security Model

Protected assets: `JULES_API_KEY`, GitHub credentials, RP01 refs/candidate integrity, Controller authority, Jules task/session identity, and evidence integrity.

Controls: Repository Secret only for Jules key; no secret in Drive/issues/inputs/artifacts; owner + `main` gate for live read workflow; read-only GitHub permissions; strict action-specific schema; stable request + intent identities; separate effect identity; exact SHA/session/update-time preconditions before future writes; no blind retry; request JSON never becomes shell commands; future publication rechecks patch/path digests; evidence never equals acceptance.

Threat classes covered by design include API-key exfiltration, token misuse, malicious issue/request payload, prompt injection through untrusted content, replay/collision, stale SHA race, session replacement collision, path-scope escape, artifact tampering, forged authority references, malicious executor output, CI leakage, overbroad permissions, and shell injection.

Mutation remains disabled in this foundation candidate.

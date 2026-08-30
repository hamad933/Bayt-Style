# RP01 Automation Status

- AUTOMATION STATUS: Foundation candidate implemented; live mutation disabled.
- ARCHITECTURE: project-local strict request/evidence layer with Jules adapter.
- CURRENT AUTOMATION BEFORE CANDIDATE: Product CI + Governance only.
- CURRENT JULES INTEGRATION BEFORE CANDIDATE: none found in repository.
- DRIVE MODEL: Controller-mediated; no direct Actions -> Drive credentials.
- SECRET CONTRACT: `JULES_API_KEY` Repository Secret; provisioning state must be verified separately.
- SETUP: `tools/rp01_automation/setup.sh`.
- SHADOW READ: candidate workflow exists; live verification requires accepted main integration + secret.
- MUTATION: not activated.
- PUBLICATION: architecture/lessons defined; implementation deferred until mutation/reconciliation gates pass.
- HOURLY AUTOMATION: not safe to activate yet.
- STOP GATE: `FOUNDATION_CANDIDATE_REVIEW_AND_CI__THEN_SECRET_AND_SHADOW_READ_GATE`.

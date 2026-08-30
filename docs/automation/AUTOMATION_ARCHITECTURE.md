# RP01 Automation Architecture

Status: FOUNDATION CANDIDATE — no live mutation enabled.

RP01 project authority remains in governed Drive. GitHub remains technical truth for repository state. This project-local control plane is an execution/evidence mechanism, not a second Current State and not a runtime dependency on UES or CEP.

Flow: `Controller -> strict request -> read/mutation gate -> Jules adapter -> evidence -> Controller review -> bounded candidate publication -> governed Control Event`.

The foundation implements strict schemas, stable request/effect identities, secret redaction, read-only Jules inspection, conservative reconciliation primitives, tests, and a shadow-inspection workflow. Mutation and trusted publication remain fail-closed until later reviewed stages.

Identities: `request_id` is exact dispatch identity; `logical_task_id` survives provider sessions; `write_domain` is collision/effect domain; `intent_identity` is a canonical request SHA-256; request and effect keys are deliberately separate.

Rollout: Foundation -> Shadow Read -> Read Canary -> Mutation Canary -> Reconciliation Canary -> Publication Canary -> Controller Integration -> Drive Contract -> Hourly Control. No later stage is implied by candidate push or printed success.

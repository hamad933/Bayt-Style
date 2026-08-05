# RP01 — Bayt-Style

Repository: `hamad933/Bayt-Style`  
Project: `RP01`  
Governance bootstrap workstream: `RP01-WS-REPO-GOV-BOOTSTRAP`

## Current repository state

This repository contains a repository-native governance baseline only. No product implementation, Stage E/F work, UI prototype, commerce feature, or unverified Stage A–D artifact is represented as present or complete.

The repository does not declare `RP1-PX-G01` passed or failed and does not resolve `MAP-046` or `MAP-047`.

## Governance reading order

1. `README.md` — repository identity, current non-product state, and stop condition.
2. `AGENTS.md` — binding executor instructions and scope boundaries.
3. `CONTRIBUTING.md` — initial-commit exception, branch, pull-request, and verification rules.
4. `docs/GOVERNANCE.md` — evidence, handoff, authorization, and enforcement model.
5. `docs/EXECUTION_HANDOFF.md` — current bootstrap evidence and known limitations.
6. `.github/pull_request_template.md` — required review evidence for every proposed change.

## Change control

The empty-repository bootstrap required one exceptional owner-authorized root commit to establish `main`. Its exact SHA is recorded in `docs/EXECUTION_HANDOFF.md`. After that root commit, repository changes must be proposed from a branch through a pull request. Review readiness never implies merge or release authorization.

## Stop state

`GOVERNANCE_BOOTSTRAP_READY_FOR_PRIMARY_REVIEW`

This stop state means the governance proposal is ready for primary review only. It does not authorize merge, release, product implementation, Stage E/F work, or any product gate decision.

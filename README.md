# RP01 — Bayt-Style

Repository: `hamad933/Bayt-Style`
Project: `RP01`
Governance status: `DURABLE_GOVERNANCE_ACTIVE`

## Repository state

The repository-native governance baseline is active. The earlier governance-only bootstrap is complete and remains historical context in Git history and PR #1.

This repository is now governed so that real product implementation may occur **only** through an explicit owner/Controller-authorized workstream that satisfies the repository execution, branch, pull-request, verification, evidence, and stop-gate rules.

Governance enablement does **not** claim that product implementation already exists, that any product surface has been implemented in this repository, or that release, deployment, or publication is authorized.

## Authority boundaries

- **Google Drive** owns governed current state, owner decisions, workstream authorization, gates, and accepted evidence.
- **GitHub** owns repository code, branches, commits, pull requests, diffs, CI results, and technical artifacts.
- Direct-source access is required. Do not substitute copied or manually transferred sources when authoritative direct access is available.

## Governance and execution reading order

Read these repository sources in order before writing:

1. `README.md`
2. `AGENTS.md`
3. `CONTRIBUTING.md`
4. `docs/GOVERNANCE.md`
5. `docs/EXECUTION_HANDOFF.md`
6. `.github/pull_request_template.md`
7. `.github/workflows/governance.yml`
8. `.github/CODEOWNERS`

Task-specific owner/Controller authority must also be read from the current Google Drive control state before repository mutation.

## Durable execution model

Every repository change must:

- belong to an explicit authorized workstream;
- verify the exact repository, base, and authorized branch before writing;
- stay inside stated scope and exclusions;
- use branch-based work and a pull request;
- run tests/checks appropriate to the change and report observed results;
- preserve reproducible evidence, including exact commits and complete changed paths;
- stop at the named workstream gate;
- avoid self-approval;
- treat merge, release, deployment, and publication as separate authorities.

Product implementation is permitted only when the current workstream explicitly authorizes it. Governance-only workstreams must not introduce product code.

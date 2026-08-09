# AGENTS.md

## Purpose and authority

This file is the repository-wide execution contract for humans and automated agents working in `hamad933/Bayt-Style`. Its rules apply to every path unless a reviewed, more-specific `AGENTS.md` exists below a subdirectory.

When instructions conflict, use this precedence:

1. explicit current owner/Controller authorization for the workstream;
2. repository `AGENTS.md`;
3. `CONTRIBUTING.md`;
4. `docs/GOVERNANCE.md`;
5. the pull-request template.

Surface unresolved conflicts instead of silently broadening scope.

Governance marker: `AUTHORIZED_WORKSTREAM_REQUIRED`
Governance marker: `NO_SELF_APPROVAL`

## Mandatory reading order

Before changing the repository, read:

1. `README.md`
2. `AGENTS.md`
3. `CONTRIBUTING.md`
4. `docs/GOVERNANCE.md`
5. `docs/EXECUTION_HANDOFF.md`
6. `.github/pull_request_template.md`
7. `.github/workflows/governance.yml`
8. `.github/CODEOWNERS`

Also read the current Google Drive control-state and workstream authority required by the task.

## Authorization conditions for product implementation

Product implementation is allowed only when **all** of the following are true:

- an explicit owner/Controller-authorized workstream exists;
- repository governance is effective for that workstream;
- the exact repository and required baseline are verified before writing;
- the authorized branch is used and unexpected divergence is absent or explicitly resolved;
- scope, exclusions, expected evidence, and the stop gate are explicit;
- authoritative Google Drive and GitHub sources are read directly;
- implementation remains inside the authorized product scope;
- tests/checks appropriate to the change are executed and observed results are reported;
- the executor stops at the named gate and does not self-approve;
- merge, release, deployment, and publication are performed only under separate explicit authority.

If a workstream does not explicitly authorize product implementation, product code is out of scope.

## Execution rules

- Confirm project, workstream, repository, baseline, branch, in-scope items, exclusions, evidence requirements, and stop gate before writing.
- Inspect current repository state before mutation. Do not assume a branch, artifact, gate result, or implementation exists.
- Use direct authoritative sources. Google Drive governs current state, owner decisions, gates, and accepted evidence; GitHub governs repository-native execution evidence.
- Make the smallest repository-native change that satisfies the authorized workstream.
- Preserve explicit unknowns as unknowns. Do not invent requirements, product decisions, approvals, evidence, test results, provenance, or operational claims.
- Do not expand scope into adjacent surfaces, features, infrastructure, or cleanup work unless authorization explicitly includes them.
- Use branches and pull requests for workstream changes. Do not push authorized work directly to `main`.
- Keep evidence reproducible: identify exact base/head commits, all changed paths, checks performed, observed results, limitations, and non-claims.
- Do not introduce secrets, credentials, personal data, production data, or unverifiable third-party material.
- Stop when the authorized stop gate is reached.

## Review and progression rules

Review readiness is not merge authorization. Passing CI is not owner acceptance. A pull-request approval does not by itself authorize release or deployment.

Executors must not:

- self-approve their own workstream;
- merge unless the current authority explicitly permits merge;
- infer a later workstream from a completed earlier gate;
- claim release, deployment, publication, or production readiness without explicit authority.

## Evidence and handoff minimum

Every execution handoff must include:

- project and workstream;
- repository;
- exact base;
- branch;
- exact head commit;
- pull request;
- complete changed-path list;
- tests/checks and observed results;
- artifacts/evidence references when applicable;
- product-code and dependency/configuration declarations;
- known limitations and non-claims;
- exact stop state;
- reviewer entry point.

Use `docs/EXECUTION_HANDOFF.md` as the durable handoff contract.

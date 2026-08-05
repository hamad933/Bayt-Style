# Repository Governance

## Scope

This document defines the minimum repository-native governance baseline for:

- Project: `RP01`
- Workstream: `RP01-WS-REPO-GOV-BOOTSTRAP`
- Repository: `hamad933/Bayt-Style`
- Starting baseline: empty repository with no commits or branch refs

It governs execution and review only. It does not define product requirements or establish product readiness.

## Governance reading order

1. `README.md`
2. `AGENTS.md`
3. `CONTRIBUTING.md`
4. `docs/GOVERNANCE.md`
5. `docs/EXECUTION_HANDOFF.md`
6. `.github/pull_request_template.md`

The earlier document controls when rules conflict, subject to an explicit, current owner instruction that stays within the authorized task.

## Authorization model

- The repository owner authorizes workstreams and exceptional actions.
- Executors may implement only the stated workstream scope and must stop at its named gate.
- Reviewers evaluate evidence and may approve, request changes, or reject the proposal.
- Merge, release, deployment, publication, and stage progression require separate explicit authorization.
- Absence of a response, passing automation, or review readiness is not authorization.

## Branch and pull-request control

The first root commit of a truly empty repository may be made directly to `main` only under the exceptional procedure in `CONTRIBUTING.md`. Once `main` exists, changes must be proposed from a branch through a pull request.

The intended protection model for `main` is:

- pull request required;
- at least one authorized review required;
- required governance check passing;
- unresolved conversations blocking merge;
- no force pushes or branch deletion;
- owner review for governance-control changes.

Repository settings are not modified by this workstream. Until branch protection is configured, these controls are policy-backed and partially enforced by `CODEOWNERS`, the pull-request template, and the governance workflow.

## Evidence standard

Evidence must be factual, reproducible, and tied to exact repository objects. The minimum evidence package is:

- exact initial commit SHA;
- exact proposal head SHA, recorded in the pull request or executor handoff;
- complete changed-path list;
- repository governance reading order;
- verification results for required files and tracked-path allowlisting;
- explicit no-product-code finding;
- known limitations and unresolved dependencies;
- exact stop state and permitted next action.

Do not describe planned checks as completed checks. Do not claim a status that depends on an unobserved workflow result.

## Execution Handoff rules

A handoff must be short enough to review but complete enough to reproduce the executor’s state. It must separate:

- **Observed facts** — repository state, commits, paths, checks, and results.
- **Non-claims** — outcomes deliberately not asserted.
- **Limitations** — controls not configured or evidence not available.
- **Stop state** — the exact gate reached.
- **Next authorized action** — review only, unless a later instruction says otherwise.

The committed handoff may refer to the pull-request description for self-referential values that cannot be embedded in their own commit, such as the proposal commit’s SHA.

## Implementation prohibitions

This bootstrap must not introduce or imply:

- product implementation or generated application scaffolding;
- Stage E/F work;
- UI prototypes or commerce features;
- unverified Stage A–D artifacts;
- resolution of `MAP-046` or `MAP-047`;
- a pass/fail declaration for `RP1-PX-G01`;
- merge or release authorization.

A future proposal that changes these prohibitions requires explicit owner authorization, its own scoped branch and pull request, and review against then-current governance.

## Proportionate enforcement

The bootstrap workflow validates required files, identity and stop-state markers, and a governance-only tracked-path allowlist. `CODEOWNERS` identifies the repository owner for review routing. These are safeguards, not substitutes for repository settings or human review.

## Stop gate

`GOVERNANCE_BOOTSTRAP_READY_FOR_PRIMARY_REVIEW`

At this gate, the only authorized next action is primary review of the governance proposal. No merge or release decision is made here.

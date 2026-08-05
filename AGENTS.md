# AGENTS.md

## Purpose and authority

This file is the repository-wide execution contract for humans and automated agents working in `hamad933/Bayt-Style`. Its rules apply to every path unless a future, reviewed, more-specific `AGENTS.md` is added below a subdirectory. No nested instructions exist at this bootstrap state.

When instructions conflict, use this precedence: explicit owner instruction for the current authorized task, repository `AGENTS.md`, `CONTRIBUTING.md`, `docs/GOVERNANCE.md`, then the pull-request template. An executor must surface unresolved conflicts instead of silently selecting a broader scope.

## Mandatory reading order

Read, in order, before changing the repository:

1. `README.md`
2. `AGENTS.md`
3. `CONTRIBUTING.md`
4. `docs/GOVERNANCE.md`
5. `docs/EXECUTION_HANDOFF.md`
6. `.github/pull_request_template.md`

## Execution rules

- Confirm the authorized project, workstream, repository, baseline, in-scope items, exclusions, evidence requirements, and stop gate before writing.
- Inspect the current repository state before mutation. Do not assume a branch, artifact, gate result, or implementation exists.
- Make the smallest repository-native change that satisfies the authorized workstream.
- Preserve explicit unknowns as unknowns. Do not invent product state, requirements, approvals, evidence, or provenance.
- Use branches and pull requests after the single empty-repository root-commit exception documented in `CONTRIBUTING.md`.
- Keep evidence reproducible: identify exact commits, all changed paths, checks performed, results, limitations, and the final stop state.
- Stop when the authorized stop gate is reached. Review readiness is not permission to merge, release, or begin a later stage.

## Implementation prohibitions for this bootstrap

Until separately authorized through reviewed repository governance, do not introduce:

- product source code, generated application scaffolding, runtime configuration, dependencies, lockfiles, build systems, tests for product behavior, fixtures, or deployment definitions;
- Stage E/F work, UI prototypes, commerce features, or product-facing assets;
- copied or reconstructed Stage A–D artifacts whose provenance has not been verified;
- changes purporting to resolve `MAP-046` or `MAP-047`;
- a declaration that `RP1-PX-G01` passed or failed;
- merge, release, deployment, publication, or rollout authorization.

Governance automation, repository metadata, Markdown policy files, and review templates are not product implementation when limited to this workstream.

## Evidence and handoff minimum

Every execution handoff must contain:

- project and workstream identifiers;
- base and head refs or exact commit SHAs when available;
- a complete changed-path list;
- verification commands or checks and their observed results;
- an explicit statement about whether product code was introduced;
- known limitations, unresolved dependencies, and non-claims;
- the exact stop state reached and the next authorized reviewer action.

## Required stop state

For `RP01-WS-REPO-GOV-BOOTSTRAP`, stop at:

`GOVERNANCE_BOOTSTRAP_READY_FOR_PRIMARY_REVIEW`

Do not merge the pull request or authorize a release as part of this workstream.

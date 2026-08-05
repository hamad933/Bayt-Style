# Contributing to RP01

This repository is in a governance-only bootstrap state. Contributions must preserve the explicit absence of product implementation unless a later, separately authorized workstream changes that boundary.

## 1. Exceptional owner-authorized initial commit

The only permitted direct-to-default-branch exception is the first commit of a repository that has no commits and no branch refs.

The procedure is:

1. Confirm the repository is genuinely empty and record the observation.
2. Confirm the actor is the repository owner or has explicit owner authorization.
3. Create one minimal root commit whose sole purpose is to establish the default branch and identify the project without claiming product state.
4. Record the exact root commit SHA in the execution evidence.
5. Introduce the complete governance proposal on a branch and submit it through a pull request.
6. Do not use this exception again after the default branch exists.

The root commit must not contain product code, dependencies, generated scaffolding, imported artifacts, gate decisions, merge authorization, or release authorization.

## 2. Branch policy

- `main` is the review target and intended protected branch.
- After the exceptional root commit, do not push workstream changes directly to `main`.
- Use a narrowly named branch such as `rp01/<workstream-or-purpose>`.
- Keep one authorized workstream per branch unless the owner explicitly approves a combined scope.
- Rebase or update only when necessary to preserve a reviewable history; never force-push over reviewed evidence without disclosing it.
- Delete or retain branches only after an authorized merge/closure decision. This bootstrap does not make that decision.

Recommended branch for this workstream: `rp01/repo-governance-bootstrap`.

## 3. Pull-request policy

Every pull request must:

- identify the project, workstream, baseline, in-scope work, exclusions, and requested stop gate;
- provide a complete changed-path list and exact base/head references;
- explain verification performed and attach or link reproducible evidence;
- state explicitly whether product code, dependencies, generated artifacts, or product configuration were introduced;
- list known limitations, unresolved dependencies, and non-claims;
- remain unmerged until the authorized reviewer gives an explicit merge decision.

A green check, approval, resolved conversation, or “ready for review” state is evidence for review only. None of these independently authorizes merge, release, deployment, or progression to another stage.

## 4. Commit policy

- Use concise, scoped commit messages that describe the repository change, not an unverified product outcome.
- Keep commits reviewable and avoid unrelated formatting or generated churn.
- Do not rewrite a published commit that is cited as evidence without recording the replacement SHA and reason.
- Never include secrets, credentials, personal data, production data, or unverifiable third-party materials.

## 5. Evidence and Execution Handoff

Before handoff, verify the proposed tree and provide:

1. exact initial commit SHA;
2. exact proposal head SHA;
3. complete changed-path list;
4. governance reading order;
5. check results demonstrating that no product code was introduced;
6. lightweight execution handoff with known limitations and non-claims;
7. exact stop state.

Use `docs/EXECUTION_HANDOFF.md` as the minimum structure. The pull-request description may supplement it with values that cannot be embedded in the commit that creates them, such as that commit’s own SHA or the pull-request number.

## 6. Verification expectations

For the governance bootstrap, verification must at minimum confirm:

- all required governance files exist and are non-empty;
- tracked paths are limited to the documented governance allowlist;
- `README.md` identifies `RP01` without asserting product implementation;
- the stop state is exactly `GOVERNANCE_BOOTSTRAP_READY_FOR_PRIMARY_REVIEW`;
- no Stage E/F, UI, commerce, imported A–D artifact, `MAP-046`/`MAP-047` resolution, or `RP1-PX-G01` decision was introduced.

The bootstrap workflow under `.github/workflows/governance.yml` performs a proportionate subset of these checks. Human review remains required.

## 7. Stop rule

Stop at `GOVERNANCE_BOOTSTRAP_READY_FOR_PRIMARY_REVIEW`.

Do not merge, release, deploy, or begin product implementation under this workstream.

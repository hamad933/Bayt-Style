# Contributing to RP01

All repository changes must be tied to an explicit authorized workstream and proposed through a reviewable branch and pull request.

## 1. Workstream and branch policy

Before writing:

- identify the project and workstream;
- verify the exact repository and base commit;
- verify the authorized branch and its divergence from the base;
- confirm in-scope paths, exclusions, required checks, evidence, and stop gate.

Use one narrowly scoped branch per authorized workstream unless the owner/Controller explicitly authorizes a combined scope. Do not push workstream changes directly to `main`.

Never force-push over reviewed evidence without explicit authorization and a recorded reason.

## 2. Pull-request policy

Every pull request must identify:

- project and workstream;
- authorization reference;
- exact base and head;
- change classification;
- scope and exclusions;
- complete changed-path list;
- tests/checks appropriate to the change;
- observed results;
- product-code introduction declaration;
- dependency/configuration introduction declaration;
- known limitations and non-claims;
- evidence links or artifact references;
- required stop gate;
- reviewer entry point.

A green check, approval, resolved conversation, or “ready for review” state is evidence for review only. None of these independently authorizes merge, release, deployment, publication, or another workstream.

## 3. Product implementation contributions

Product code, dependencies, lockfiles, runtime configuration, product tests, UI assets, backend/API code, or deployment definitions may be introduced only when the current owner/Controller-authorized workstream explicitly permits them.

When product implementation is authorized:

- keep the implementation inside the authorized scope;
- use the accepted product and design authority named by the workstream;
- add or update tests proportionate to the change;
- report actual test results rather than planned checks;
- do not invent product decisions or evidence;
- do not mix unrelated governance or cleanup changes unless explicitly authorized.

A governance-only workstream must remain governance-only.

## 4. Commit policy

- Use concise, scoped commit messages describing repository changes.
- Keep commits reviewable and avoid unrelated formatting or generated churn.
- Do not rewrite a published commit cited as evidence without recording the replacement SHA and reason.
- Never commit secrets, credentials, personal data, production data, or unverified private material.
- Keep privacy-sensitive and proprietary evidence in its authoritative system unless repository storage is explicitly authorized.

## 5. Verification expectations

Verification must be appropriate to the change. At minimum:

- confirm only authorized paths changed;
- run syntax, lint, build, test, or policy checks that are relevant and available;
- record the exact observed result of each check;
- use `git diff --check` or an equivalent whitespace/error check;
- inspect the final diff for unrelated content, secrets, and scope expansion;
- confirm the stop gate and reviewer entry point.

Do not describe an unavailable or pending check as passed.

## 6. Execution Handoff

Use `docs/EXECUTION_HANDOFF.md` as the minimum reusable handoff contract. The pull-request description may carry values that become known only after commit/PR creation, including the final head SHA, PR number, CI state, and review URL.

## 7. Historical root-commit exception

The original empty-repository bootstrap used a one-time owner-authorized root commit to establish `main`. That exception is historical and non-controlling. It is not a standing permission for direct changes to `main`.

## 8. Merge, release, and deployment

Contributors and executors must not self-authorize merge, release, deployment, or publication. Those actions require the explicit authority applicable to the current workstream and lifecycle state.

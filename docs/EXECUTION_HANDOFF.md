# Execution Handoff Contract

Use this document as the durable minimum contract for repository execution handoffs. Historical bootstrap evidence remains available in Git history and PR #1 and is not duplicated here.

## Identification

- Project:
- Workstream:
- Change classification: `GOVERNANCE_CHANGE` / `PRODUCT_IMPLEMENTATION`
- Repository:
- Base branch/ref:
- Exact base SHA:
- Execution branch:
- Exact head SHA:
- Pull request number/URL:
- Required stop gate:

## Authorization and scope

- Owner/Controller authorization reference:
- In scope:
- Explicit exclusions:
- Authoritative Google Drive sources:
- Authoritative GitHub sources:

## Complete changed-path list

List every changed repository path, one per line.

## Tests and checks

For each test/check, record:

- command or check;
- why it is applicable;
- observed result;
- unavailable/pending status when not observed.

Do not infer a pass from an unobserved check.

## Observed results

Record only facts observed from repository state, local verification, GitHub, CI, or authoritative project evidence.

## Product/dependency declarations

- Product code introduced: `YES` / `NO`
- Dependencies or lockfiles introduced: `YES` / `NO`
- Runtime/application configuration introduced: `YES` / `NO`
- Deployment configuration introduced: `YES` / `NO`

If any answer is `YES`, identify the authorizing scope and changed paths.

## Artifacts and evidence references

Include links or identifiers for relevant:

- commits;
- pull request;
- CI runs/checks;
- test reports;
- build artifacts;
- accepted external evidence where the workstream requires it.

## Limitations

State actual constraints, unavailable checks, environment gaps, pending CI, or repository-setting limitations.

## Non-claims

State important outcomes that are deliberately not claimed, including merge/release/deployment or product acceptance when they are outside the workstream.

## Stop state

- Exact stop gate reached:
- Actions deliberately not performed:
- Current CI state:
- Current PR state:

## Reviewer entry point

Provide the exact PR, compare URL, commit, or evidence location the authorized reviewer should inspect next.

The executor does not self-approve. Merge, release, deployment, publication, and later workstreams require their own authority.

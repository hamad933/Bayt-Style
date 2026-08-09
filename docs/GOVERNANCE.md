# Repository Governance

## Scope

This document defines durable repository-native governance for:

- Project: `RP01`
- Repository: `hamad933/Bayt-Style`

It governs how authorized work is executed, evidenced, reviewed, and stopped. It does not itself authorize a product feature, workstream, merge, release, deployment, or publication.

Governance marker: `DIRECT_SOURCE_AUTHORITY`
Governance marker: `SEPARATE_RELEASE_AUTHORITY`

## 1. Repository-stable governance

Repository-stable governance defines the controls that apply across workstreams:

- direct authoritative source access;
- explicit scope and exclusions;
- exact baseline and branch verification;
- branch-based execution and pull-request review;
- reproducible evidence;
- tests/checks appropriate to the change;
- no invented facts, approvals, or product decisions;
- no unrelated scope expansion;
- stop-gate discipline;
- no self-approval;
- separate merge/release/deployment authority.

Governance changes are themselves governed changes and require an authorized workstream, branch, pull request, evidence, and review.

## 2. Task and workstream authorization

Repository governance does not create execution authority by itself.

Before writing, an executor must have an explicit current owner/Controller-authorized workstream that states or resolves:

- project and repository;
- baseline;
- branch;
- in-scope work;
- exclusions;
- source authority;
- required verification and evidence;
- stop gate.

If required authority is missing, contradictory, or stale, execution stops rather than expanding scope by inference.

## 3. Product implementation

Product implementation may occur only under a workstream that explicitly authorizes product code.

That authorization may permit application source, dependencies, lockfiles, runtime configuration, product tests, UI/assets, backend/API code, or deployment configuration only to the extent stated by the workstream.

The existence of durable governance does not mean product implementation already exists. Product scope and acceptance must come from current project authority, not from repository governance text.

## 4. Accepted evidence

Evidence must be factual, reproducible, and tied to authoritative repository or control objects.

Minimum repository evidence includes:

- exact base and head commits;
- branch and pull request;
- complete changed-path list;
- tests/checks performed;
- observed results;
- CI state when available;
- artifacts/evidence references;
- limitations and non-claims;
- exact stop state.

Do not report planned, unavailable, or pending checks as passed.

Accepted project evidence and owner decisions are governed in Google Drive. GitHub records technical implementation evidence such as commits, diffs, PRs, and CI.

## 5. Release and deployment authority

Merge, release, deployment, publication, rollout, and production-readiness decisions are separate from implementation and review readiness.

A passing workflow or approved PR is not sufficient by itself. The applicable owner/Controller authority must explicitly permit the action.

## Source authority

### Google Drive

Google Drive is authoritative for:

- governed current state;
- owner decisions;
- workstream authorization;
- lifecycle gates;
- accepted evidence and continuity.

### GitHub

GitHub is authoritative for:

- repository files and code;
- branches and commits;
- pull requests and diffs;
- CI/check results;
- technical implementation history and repository artifacts.

Use direct source access when available. Do not substitute copied files or conversation memory for authoritative project state.

## Branch and pull-request control

`main` is the review/merge target. Workstream changes are made on an authorized branch and proposed through a pull request.

A reviewable PR must contain or link:

- authorization;
- exact base/head;
- scope and exclusions;
- changed paths;
- verification and observed results;
- declarations about product code and dependencies/configuration;
- limitations and non-claims;
- stop gate and reviewer entry point.

Force-push, merge, release, deployment, and publication follow their own explicit authorization requirements.

## Review ownership and enforcement

`.github/CODEOWNERS` routes repository ownership for review. `.github/workflows/governance.yml` performs proportionate governance-integrity checks.

Automation is a safeguard, not a substitute for human review or owner authority. Product-specific CI should be added by a later authorized product implementation workstream once a technology stack exists.

## Historical bootstrap context

The repository began with a governance-only bootstrap. That bootstrap completed and its evidence remains in Git history and PR #1. Its temporary implementation restrictions and bootstrap stop state are no longer controlling rules.

## Stop-gate discipline

Every workstream must name its own stop gate. Executors stop at that gate and return evidence for review.

Review readiness never authorizes a later workstream, merge, release, deployment, or publication by implication.

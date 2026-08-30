# RP01 Automation Lessons Ledger

## UES durable observer envelope
SOURCE_SYSTEM: UES. SOURCE_REF: `main@2c20f9d36b685dbb3a5f3ba2860f2108074960e4`. PROBLEM_SOLVED: provider observer failure must preserve evidence without freezing unrelated lanes. FAILURE_MODE: timeout/failure loses evidence or causes global wait. MECHANISM: inner budget, durable receipt before enforced failure, authority-neutral fallback. WHY_IT_WORKS: evidence survives and independent work continues. PORTABLE_TO_THIS_PROJECT: yes. PROJECT_SPECIFIC_PARTS: UES portfolio matrix/state store not copied. SECURITY_IMPLICATION: fail-closed/no provider mutation. RECOMMENDED_ADOPTION: adopted for shadow design. REJECTED_ALTERNATIVES: global lock; prose success as proof.

## CEP request/effect separation
SOURCE_SYSTEM: CEP Gateway v2.2. SOURCE_REF: `main@4d6b61d6b59635c50af976d4ea4a3b9969914bd2`. PROBLEM_SOLVED: duplicate request vs conflicting effect. FAILURE_MODE: duplicate execution or global serialization. MECHANISM: separate request/effect keys, preflight intent, one provider write, post-proof, reconciliation. WHY_IT_WORKS: controls conflicts while preserving parallelism. PORTABLE_TO_THIS_PROJECT: yes. PROJECT_SPECIFIC_PARTS: CEP lanes/controllers/source IDs not copied. SECURITY_IMPLICATION: no blind retry. RECOMMENDED_ADOPTION: identities now; mutation in separate reviewed wave. REJECTED_ALTERNATIVES: one global lock.

## CEP trusted publication
SOURCE_SYSTEM: CEP Gateway v2.3. SOURCE_REF: `.github/workflows/cep-jules-v2-publication.yml@4d6b61d6b59635c50af976d4ea4a3b9969914bd2`. PROBLEM_SOLVED: provider effect is not trusted remote candidate. FAILURE_MODE: stale/unreviewed patch push. MECHANISM: exact session/update/base/patch/path identity + non-force push + remote SHA readback. WHY_IT_WORKS: publication binds exact reviewed effect to exact remote state. PORTABLE_TO_THIS_PROJECT: yes after mutation/reconciliation. PROJECT_SPECIFIC_PARTS: CEP issue/lane IDs not copied. SECURITY_IMPLICATION: publication != acceptance/merge/release. RECOMMENDED_ADOPTION: Publication Canary wave. REJECTED_ALTERNATIVES: executor push as acceptance.

## Jules volatility
SOURCE_SYSTEM: Jules official API docs. SOURCE_REF: REST `v1alpha`, verified 2026-08-30. FAILURE_MODE: stale copied provider assumptions. MECHANISM: provider adapter + source discovery + explicit plan approval. PORTABLE_TO_THIS_PROJECT: yes. SECURITY_IMPLICATION: credentials isolated and mutations explicit. RECOMMENDED_ADOPTION: adopted. REJECTED_ALTERNATIVES: copied CEP source name or assuming stable alpha semantics.

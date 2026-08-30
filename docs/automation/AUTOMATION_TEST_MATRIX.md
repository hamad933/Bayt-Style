# RP01 Automation Test Matrix

Implemented: valid strict read; unknown-key rejection; wrong-repository rejection; create mutation requires exact SHA, authority reference and plan approval; changed intent identity; secret/API-key redaction; evidence redaction; conservative create/activity reconciliation; compile gate; literal-secret scan.

Required before Mutation Canary: durable exact/changed/concurrent replay tests; same-effect serialization and independent-effect parallelism; stale SHA/session/update-time rejection; lost-response reconciliation; provider timeout/rate-limit classification; kill-switch and untrusted-actor tests.

Required before Publication Canary: patch/path digest mismatch; remote drift; ambiguous push readback; duplicate publication; exact resulting tree/path verification.

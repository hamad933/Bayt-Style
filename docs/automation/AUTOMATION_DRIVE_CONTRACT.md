# RP01 Automation Drive Contract

Drive remains governed control-plane truth; GitHub automation must not create a competing Current State.

Controller reads governed RP01 authority -> dispatches bounded automation request -> automation emits machine-readable evidence -> Controller independently reviews exact Jules/GitHub post-state -> Drive changes only for a meaningful Control Event.

Governed instructions should use opaque `drive:<file_id>` references plus exact content digests where feasible. Direct GitHub Actions -> Drive writes are not authorized by this foundation and require a separate credential/threat-model decision.

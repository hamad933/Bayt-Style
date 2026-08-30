# RP01 Jules Setup

Current Jules API is treated as experimental `v1alpha`; provider paths stay behind the adapter and are reverified when material.

Owner-only provisioning: connect `hamad933/Bayt-Style` to Jules in the Jules web app; create an API key; add it to this repository as Repository Secret `JULES_API_KEY`; never put the value in Drive, issues, workflow inputs, artifacts, or source.

Discover the RP01 Jules source identity through the `sources` endpoint; never copy CEP source identity. Future create-session mutation must set `requirePlanApproval=true`. Automatic PR creation is not the trusted default path.

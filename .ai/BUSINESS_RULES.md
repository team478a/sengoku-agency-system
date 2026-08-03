# Business Rules

## Identity and linkage

1. `common_user_id`, `agency_id` and `referral_token` have different responsibilities and must not be substituted for one another.
2. A user may have internal IDs in multiple external systems, but those mappings must resolve to one common identity record.
3. Referral attribution must be traceable to its source and acquisition time.
4. Existing agency linkage must not be silently overwritten by a later visit or weaker attribution signal.
5. Duplicate registration and conflicting linkage must be detected and surfaced for review.

## Agency roles

Keep the following roles conceptually distinct even when one agency performs multiple roles:

- Referring agency
- Sales owner
- Closing owner
- Reward recipient
- Operational owner

## Hierarchy

1. Existing parent-child agency relationships are business-critical.
2. Hierarchy changes require explicit authorization and an audit trail.
3. Queries and exports must respect the requesting user's visibility scope.
4. Circular hierarchy relationships must never be created.

## Rewards and purchase linkage

1. A successful purchase event does not automatically prove reward eligibility.
2. Reward eligibility must be determined from the product/event rule in effect at the relevant time.
3. AI Art School tuition requires agency linkage but is not an agency-reward-eligible payment.
4. Purchase, attribution, provisioning and reward events must be independently traceable.
5. Replayed webhooks or jobs must not create duplicate outcomes.

## External integration

1. External requests require authentication, authorization scope and input validation.
2. Webhook/event processing must be idempotent.
3. Failed outbound events must remain recoverable through the integration outbox/dead-letter process.
4. Secret values, API keys and personal data must not be written to ordinary application logs.
5. Integration failures must not silently leave purchase and entitlement state inconsistent.
# Module Dependency Map

## Current Foundation

```text
legacy entrypoints
  -> includes/functions.php
  -> config/database.php

src/Shared
  -> Auth
  -> Config
  -> Database
  -> Http
  -> Log
  -> Time

src/Integration/Outbox
  -> Shared/Database through PDO
  -> RetryPolicy
  -> OutboxClaimService
  -> OutboxRepository
  -> DeadLetterService

src/CommonIdentity
  -> CommonUserInput
  -> CommonUserInputNormalizer

src/Referral
  -> ReferralTokenResolver
  -> TouchpointFingerprint

api/v2/bootstrap.php
  -> includes/shared_bootstrap.php
  -> src/Shared/Auth

api/hierarchy.php
  -> includes/shared_bootstrap.php
  -> src/Shared/Auth

api/integrations/agencies/index.php
  -> includes/shared_bootstrap.php
  -> src/Shared/Auth

api/common-users/index.php
  -> includes/shared_bootstrap.php
  -> src/CommonIdentity

api/referrals/index.php
  -> includes/shared_bootstrap.php
  -> src/CommonIdentity
  -> src/Referral

includes/functions.php Outbox compatibility wrappers
  -> includes/shared_bootstrap.php
  -> src/Integration/Outbox

includes/functions.php referral compatibility wrappers
  -> includes/shared_bootstrap.php
  -> src/Referral
```

## Rule

New business logic should be placed under `src/` and legacy functions should remain as compatibility wrappers until a phase explicitly migrates their internals.

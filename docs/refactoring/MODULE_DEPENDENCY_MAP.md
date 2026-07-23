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

api/v2/bootstrap.php
  -> includes/shared_bootstrap.php
  -> src/Shared/Auth

api/hierarchy.php
  -> includes/shared_bootstrap.php
  -> src/Shared/Auth

api/integrations/agencies/index.php
  -> includes/shared_bootstrap.php
  -> src/Shared/Auth

includes/functions.php Outbox compatibility wrappers
  -> includes/shared_bootstrap.php
  -> src/Integration/Outbox
```

## Rule

New business logic should be placed under `src/` and legacy functions should remain as compatibility wrappers until a phase explicitly migrates their internals.

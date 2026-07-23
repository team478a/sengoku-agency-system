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

src/Admin
  -> AdminDateFormatter
  -> AdminTextFormatter
  -> AdminBadgeRenderer
  -> OperationsQueryService

src/LandingPage
  -> LandingPageUrlBuilder
  -> LandingPageText
  -> ResponsiveImageBuilder

src/Notification
  -> TemplateVariableReplacer

src/Activity
  -> ActivityQueryService

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

includes/functions.php LP compatibility wrappers
  -> includes/shared_bootstrap.php
  -> src/LandingPage

includes/mailer.php template replacement
  -> includes/shared_bootstrap.php
  -> src/Notification

admin/agent_activity.php
  -> includes/shared_bootstrap.php
  -> src/Activity

agent/downline_activity.php
  -> includes/shared_bootstrap.php
  -> src/Activity

admin/export_csv.php activity export
  -> includes/shared_bootstrap.php
  -> src/Activity

agent/export_csv.php activity export
  -> includes/shared_bootstrap.php
  -> src/Activity

admin/integration_outbox.php
  -> includes/shared_bootstrap.php
  -> src/Admin

admin/operations.php
  -> includes/shared_bootstrap.php
  -> src/Admin
```

## Rule

New business logic should be placed under `src/` and legacy functions should remain as compatibility wrappers until a phase explicitly migrates their internals.

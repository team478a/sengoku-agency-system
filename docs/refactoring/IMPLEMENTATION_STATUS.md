# Implementation Status

## v3.6.94 Audit CSV Foundation

Status: completed

Implemented in this phase:

- Added `src/Audit` class:
  - `LoginLogCsvExportService`
- Routed `admin/export_csv.php?type=login_logs` row generation through `LoginLogCsvExportService`.

Runtime behavior changed: limited to internal query delegation

Database changed: no

Existing CSV URLs and CSV column order changed: no

Remaining CSV foundation work:

- Add DB-backed CSV contract tests once local test database configuration is available.

## v3.6.93 Reporting CSV Foundation

Status: completed

Implemented in this phase:

- Added `src/Reporting` class:
  - `TemplateReportCsvExportService`
- Routed `admin/export_csv.php?type=template_reports` row generation through `TemplateReportCsvExportService`.

Runtime behavior changed: limited to internal query delegation

Database changed: no

Existing CSV URLs and CSV column order changed: no

Remaining CSV foundation work:

- Add DB-backed CSV contract tests once local test database configuration is available.

## v3.6.92 Agency Recruitment Link CSV Foundation

Status: completed

Implemented in this phase:

- Added `src/Agency` class:
  - `RecruitmentLinkCsvExportService`
- Routed `agent/export_csv.php?type=recruitment_links` row generation through `RecruitmentLinkCsvExportService`.

Runtime behavior changed: limited to internal query delegation

Database changed: no

Existing CSV URLs and CSV column order changed: no

Remaining CSV foundation work:

- Add DB-backed CSV contract tests once local test database configuration is available.

## v3.6.91 Agency CSV Foundation

Status: completed

Implemented in this phase:

- Added `src/Agency` class:
  - `SubAgentCsvExportService`
- Routed `agent/export_csv.php?type=sub_agents` row generation through `SubAgentCsvExportService`.

Runtime behavior changed: limited to internal query delegation

Database changed: no

Existing CSV URLs and CSV column order changed: no

Remaining CSV foundation work:

- Extract template report CSV exports into a Reporting export service.
- Add DB-backed CSV contract tests once local test database configuration is available.

## v3.6.90 Lead CSV Foundation

Status: completed

Implemented in this phase:

- Added `src/Lead` class:
  - `LeadCsvExportService`
- Routed `admin/export_csv.php?type=leads` row generation through `LeadCsvExportService`.
- Routed `agent/export_csv.php?type=leads` row generation through `LeadCsvExportService`.

Runtime behavior changed: limited to internal query delegation

Database changed: no

Existing CSV URLs and CSV column order changed: no

Remaining CSV foundation work:

- Extract template report CSV exports into a Reporting export service.
- Add DB-backed CSV contract tests once local test database configuration is available.

## v3.6.89 Activity CSV Foundation

Status: completed

Implemented in this phase:

- Added all-row export support to `ActivityQueryService`.
- Routed `admin/export_csv.php?type=agent_activity` through `ActivityQueryService`.
- Routed `agent/export_csv.php?type=downline_activity` through `ActivityQueryService`.

Runtime behavior changed: limited to internal query delegation

Database changed: no

Existing CSV URLs and CSV column order changed: no

Remaining CSV foundation work:

- Extract sub-agent CSV exports into an Agency export service.
- Add DB-backed CSV contract tests once local test database configuration is available.

## v3.6.88 Activity Foundation

Status: in progress

Implemented in this phase:

- Added `src/Activity` class:
  - `ActivityQueryService`
- Routed `admin/agent_activity.php` activity aggregation through `ActivityQueryService`.
- Routed `agent/downline_activity.php` downline activity aggregation through `ActivityQueryService`.

Runtime behavior changed: limited to internal query delegation

Database changed: no

Existing page URLs, filters, sort options, CSV links, and display labels changed: no

Remaining Phase 7 work:

- Extract activity dashboard cards into presentation helpers.
- Add dedicated activity trend and ranking query services.
- Add DB-backed integration tests once local test database configuration is available.

## v3.6.87 LandingPage / Notification Foundation

Status: in progress

Implemented in this phase:

- Added `src/LandingPage` classes:
  - `LandingPageUrlBuilder`
  - `LandingPageText`
  - `ResponsiveImageBuilder`
- Added `src/Notification` classes:
  - `TemplateVariableReplacer`
- Routed existing LP URL helpers, query parameter handling, absolute URL handling, plain text normalization, and responsive image HTML through `src/LandingPage`.
- Routed existing mail template variable replacement through `src/Notification`.

Runtime behavior changed: limited to internal delegation

Database changed: no

Existing LP URLs, template tags, and mail variable names changed: no

Remaining Phase 6 work:

- Extract full LP rendering flow into application services.
- Add dedicated SEO metadata builder.
- Add notification channel strategy classes.
- Move template storage/query logic into repositories.
- Extract access logging into a dedicated service.

## v3.6.86 Admin Foundation

Status: in progress

Implemented in this phase:

- Added `src/Admin` classes:
  - `AdminDateFormatter`
  - `AdminTextFormatter`
  - `AdminBadgeRenderer`
  - `OperationsQueryService`
- Routed `admin/integration_outbox.php` display helpers through shared admin classes.
- Routed `admin/operations.php` safe query helpers and display helpers through shared admin classes.

Runtime behavior changed: limited to internal delegation

Database changed: no

Existing admin page URLs changed: no

Remaining Phase 5 work:

- Move full POST handling into controller/application services.
- Move full dashboard SQL into query services.
- Extract templates for the target admin screens.

## v3.6.85 CommonIdentity / Referral Foundation

Status: in progress

Implemented in this phase:

- Added `src/CommonIdentity` classes:
  - `CommonUserInput`
  - `CommonUserInputNormalizer`
- Added `src/Referral` classes:
  - `ReferralTokenResolver`
  - `TouchpointFingerprint`
- Routed current common user API input normalization through `CommonUserInputNormalizer`.
- Routed current referral API common user input normalization through `CommonUserInputNormalizer`.
- Routed referral token resolution and touchpoint IP/user-agent hashing through `src/Referral` classes.

Runtime behavior changed: limited to internal delegation

Database changed: no

Existing API URLs changed: no

## v3.6.84 Integration / Outbox Foundation

Status: in progress

Implemented in this phase:

- Added `src/Integration/Outbox` classes:
  - `RetryPolicy`
  - `OutboxClaimService`
  - `OutboxRepository`
  - `DeadLetterService`
- Kept existing compatibility functions and routed them through the new services:
  - `integrationOutboxSupportsClaims`
  - `getIntegrationOutboxClaimTimeoutSeconds`
  - `recoverStaleIntegrationOutboxClaims`
  - `claimIntegrationOutboxEventById`
  - `claimDueIntegrationOutboxEvents`
  - `updateIntegrationOutboxEventAfterAttempt`
  - `resetIntegrationOutboxEventForRetry`
  - `moveIntegrationOutboxEventToDlq`

Runtime behavior changed: limited to Outbox internal delegation

Database changed: no

Existing admin/cron/API entrypoints changed: no

## v3.6.83 Shared Foundation

Status: in progress

Implemented in this phase:

- Added shared API authentication and scope authorization classes.
- Added shared IP restriction, schema checker, API exception payload, HTTP client, logger, and clock classes.
- Kept existing public functions as compatibility wrappers:
  - `apiV2Authenticate`
  - `apiV2RequireScope`
  - `apiTokenIsValid`
  - `agencyApiKeyIsValid`
  - `agencyApiPartnerByKey`
  - `agencyApiRequireScope`
- Added `includes/shared_bootstrap.php` so production can load `src/` classes even without Composer autoload.

Runtime behavior changed: limited to shared authentication internals

Database changed: no

Existing API URLs changed: no

## v3.6.82 Modular Monolith Foundation

Status: in progress

Implemented in this phase:

- Added Composer project metadata and PSR-4 autoload mapping for `SenNoKuni\\`.
- Added PHPUnit, PHPStan, and syntax lint configuration.
- Added GitHub Actions CI for lint, tests, and static analysis.
- Added initial characterization tests for legacy entrypoints and function names.
- Added small Shared module foundation classes under `src/Shared`.

Runtime behavior changed: no

Database changed: no

Existing API contracts changed: no

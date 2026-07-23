# Migration Guide

## v3.6.95

No database migration is required.

CSV contract tests can be run against a MariaDB/MySQL test database by setting:

- `CSV_CONTRACT_DSN`
- `CSV_CONTRACT_USER`
- `CSV_CONTRACT_PASS`

Then run:

```bash
composer test:csv-contract
```

The test runner creates connection-local temporary tables only.

## v3.6.94

No database migration is required.

Login-log CSV exports now use `SenNoKuni\Audit\LoginLogCsvExportService` through `includes/shared_bootstrap.php`.

Existing CSV URL and CSV column order remain unchanged:

- `admin/export_csv.php?type=login_logs`

## v3.6.93

No database migration is required.

Template report CSV exports now use `SenNoKuni\Reporting\TemplateReportCsvExportService` through `includes/shared_bootstrap.php`.

Existing CSV URL and CSV column order remain unchanged:

- `admin/export_csv.php?type=template_reports`

## v3.6.92

No database migration is required.

Recruitment-link CSV exports now use `SenNoKuni\Agency\RecruitmentLinkCsvExportService` through `includes/shared_bootstrap.php`.

Existing CSV URL and CSV column order remain unchanged:

- `agent/export_csv.php?type=recruitment_links`

## v3.6.91

No database migration is required.

Sub-agent CSV exports now use `SenNoKuni\Agency\SubAgentCsvExportService` through `includes/shared_bootstrap.php`.

Existing CSV URL and CSV column order remain unchanged:

- `agent/export_csv.php?type=sub_agents`

## v3.6.90

No database migration is required.

Lead CSV exports now use `SenNoKuni\Lead\LeadCsvExportService` through `includes/shared_bootstrap.php`.

Existing CSV URLs and CSV column order remain unchanged.

## v3.6.89

No database migration is required.

Activity CSV exports now use `SenNoKuni\Activity\ActivityQueryService` through `includes/shared_bootstrap.php`.

Existing CSV URLs and CSV column order remain unchanged.

## v3.6.88

No database migration is required.

Activity aggregation internals now load the following class through `includes/shared_bootstrap.php`:

- `SenNoKuni\Activity\ActivityQueryService`

Existing activity page URLs, filters, sort options, and CSV links remain unchanged.

## v3.6.87

No database migration is required.

Landing page and notification internals now load the following classes through `includes/shared_bootstrap.php`:

- `SenNoKuni\LandingPage\LandingPageUrlBuilder`
- `SenNoKuni\LandingPage\LandingPageText`
- `SenNoKuni\LandingPage\ResponsiveImageBuilder`
- `SenNoKuni\Notification\TemplateVariableReplacer`

Existing LP URLs, LP template tags, mail templates, and mail variable names remain unchanged.

## v3.6.86

No database migration is required.

The Outbox and operations admin screens now load shared admin helper classes through `includes/shared_bootstrap.php`.

Existing page URLs, form action names, and query parameters remain unchanged.

## v3.6.85

No database migration is required.

Common user and referral internals now load the following classes through `includes/shared_bootstrap.php`:

- `SenNoKuni\CommonIdentity\CommonUserInputNormalizer`
- `SenNoKuni\Referral\ReferralTokenResolver`
- `SenNoKuni\Referral\TouchpointFingerprint`

Existing API URLs and legacy helper names remain unchanged.

## v3.6.84

No database migration is required.

Outbox internals now load `src/Integration/Outbox` classes through `includes/shared_bootstrap.php`.

The following existing functions remain available as compatibility wrappers:

- `retryDueIntegrationOutboxEvents`
- `retryIntegrationOutboxEventRow`
- `recoverStaleIntegrationOutboxClaims`
- `resetIntegrationOutboxEventForRetry`
- `moveIntegrationOutboxEventToDlq`

## v3.6.83

No database migration is required.

The following APIs now load `includes/shared_bootstrap.php` and route authentication through shared classes:

- `api/v2/bootstrap.php`
- `api/hierarchy.php`
- `api/integrations/agencies/index.php`

Rollback is file-only if authentication regressions are found.

## v3.6.82

No database migration is required.

This phase adds development and module foundation files only. Production behavior is unchanged unless future phases wire legacy entrypoints to `src/` services.

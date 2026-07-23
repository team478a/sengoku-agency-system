# Implementation History

## 2026-07-24 Phase 11

- Added `RecruitmentLinkCsvExportService` for shared recruitment-link CSV row generation.
- Routed `agent/export_csv.php?type=recruitment_links` through the service without changing CSV URL, filename, or column order.
- Kept database schema unchanged.

## 2026-07-24 Phase 10

- Added `SubAgentCsvExportService` for shared sub-agent CSV row generation.
- Routed `agent/export_csv.php?type=sub_agents` through the service without changing CSV URL, filename, or column order.
- Kept database schema unchanged.

## 2026-07-24 Phase 9

- Added `LeadCsvExportService` for shared lead CSV row generation.
- Routed admin and agent lead CSV exports through the service without changing CSV URLs, filenames, or column order.
- Kept database schema unchanged.

## 2026-07-24 Phase 8

- Routed activity CSV exports through the shared Activity query service.
- Preserved existing CSV endpoint URLs and column order.
- Kept database schema unchanged.

## 2026-07-24 Phase 7

- Added Activity foundation class for shared agent/downline activity aggregation.
- Preserved existing admin and agent activity page URLs, filters, sort options, pagination, and output fields.
- Kept database schema unchanged.

## 2026-07-24 Phase 6

- Added LandingPage foundation classes for LP URL building, query parameter handling, plain-text normalization, and responsive image HTML.
- Added Notification foundation class for mail/template variable replacement.
- Preserved existing LP helper function names, LP URL shape, template tags, and mail variable names while routing internals through `src/`.
- Kept database schema unchanged.

## 2026-07-24 Phase 5

- Added Admin foundation classes for shared formatting, badge rendering, and operations query access.
- Preserved existing admin page function names while routing internals through `src/Admin`.
- Kept page URLs, POST action names, and database schema unchanged.

## 2026-07-24 Phase 4

- Added CommonIdentity module classes for common user API input normalization.
- Added Referral module classes for referral token resolution and touchpoint fingerprinting.
- Preserved current common user, referral, and legacy function contracts while moving reusable logic into `src/`.
- Kept database schema unchanged.

## 2026-07-24 Phase 3

- Added Outbox module classes for claim locking, retry policy, retry state updates, and DLQ operations.
- Preserved legacy function names so existing admin and cron screens continue to call the same functions.
- Kept database schema unchanged.

## 2026-07-24 Phase 2

- Added shared API authentication and authorization modules.
- Routed duplicate legacy API authentication helpers through shared classes while preserving function names.
- Added common Shared classes requested by the modular monolith refactoring instructions.

## 2026-07-24

- Started Phase 0 and Phase 1 from `MODULAR_MONOLITH_REFACTORING_INSTRUCTIONS.md`.
- Created the modular-monolith foundation branch.
- Added development tooling without changing production PHP entrypoints.

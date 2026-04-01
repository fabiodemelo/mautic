# MauticSyncData — Technical Architecture Plan

## 1. Architecture Overview

A Mautic 5.x plugin that syncs SendGrid's 7 suppression types into Mautic's DNC system (or designated segments), with a dashboard for analytics and a cron command for automated sync.

**Namespace:** `MauticPlugin\MauticSyncDataBundle`
**Directory:** `plugins/MauticSyncDataBundle/`
**Compatibility:** Mautic >=5.0, PHP >=8.1, Symfony 6.x

---

## 2. Tech Stack

| Layer | Technology |
|-------|-----------|
| Platform | Mautic >=5.0 (Symfony 6.x) |
| Language | PHP >=8.1 |
| Plugin Framework | IntegrationsBundle |
| Templates | Twig (extends `MauticCoreBundle:Default:content.html.twig`) |
| Charts | Chart.js 2.9.4 (native, bundled with Mautic) |
| HTTP Client | Symfony HttpClient |
| ORM | Doctrine (MySQL/MariaDB) |
| Background Jobs | Cron-based console command |
| Testing | PHPUnit |

---

## 3. Data Models

### 3.1 Entity: `SyncLog`

**Table:** `plugin_syncdata_log`
**Purpose:** Record of each sync run for history and audit.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | INT (PK, AUTO) | No | Primary key |
| `sync_type` | VARCHAR(20) | No | `incremental`, `full`, `manual` |
| `started_at` | DATETIME | No | Sync start time |
| `completed_at` | DATETIME | Yes | Sync end time |
| `status` | VARCHAR(20) | No | `running`, `success`, `partial`, `failed` |
| `records_fetched` | INT | No | Total records from SendGrid (default 0) |
| `records_added` | INT | No | New DNC/segment entries created (default 0) |
| `records_skipped` | INT | No | Already in DNC (default 0) |
| `records_unmatched` | INT | No | No matching Mautic contact (default 0) |
| `error_message` | TEXT | Yes | Error details if failed |
| `suppression_breakdown` | JSON | Yes | `{"bounces": 12, "spam": 3, ...}` |
| `created_at` | DATETIME | No | Record creation timestamp |

**Indexes:**
- `idx_status` on `status`
- `idx_started_at` on `started_at`

---

### 3.2 Entity: `Suppression`

**Table:** `plugin_syncdata_suppressions`
**Purpose:** Cache of every synced suppression for dashboard queries and deduplication.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | INT (PK, AUTO) | No | Primary key |
| `email` | VARCHAR(255) | No | Suppressed email address |
| `suppression_type` | VARCHAR(30) | No | `bounce`, `spam_report`, `block`, `invalid_email`, `global_unsubscribe`, `group_unsubscribe` |
| `sendgrid_reason` | TEXT | Yes | Original reason from SendGrid |
| `sendgrid_status` | VARCHAR(50) | Yes | Bounce/block status code |
| `sendgrid_created_at` | DATETIME | No | When SendGrid recorded it |
| `sendgrid_group_id` | INT | Yes | Unsubscribe group ID |
| `sendgrid_group_name` | VARCHAR(100) | Yes | Unsubscribe group name |
| `mautic_contact_id` | INT | Yes | Matched Mautic contact ID |
| `action_taken` | VARCHAR(20) | No | `dnc`, `segment`, `unmatched` |
| `synced_at` | DATETIME | No | When this record was synced |
| `created_at` | DATETIME | No | Record creation timestamp |

**Indexes:**
- `idx_email` on `email`
- `idx_type` on `suppression_type`
- `idx_synced_at` on `synced_at`
- `idx_contact` on `mautic_contact_id`
- `UNIQUE idx_email_type_date` on (`email`, `suppression_type`, `sendgrid_created_at`)

---

### 3.3 Settings Storage (No Custom Table)

| Setting | Storage | Method |
|---------|---------|--------|
| API Key | IntegrationsBundle API keys | `getApiKeys()['api_key']` |
| Enabled suppression types | Feature settings | `getFeatureSettings()['enabled_types']` |
| Sync interval (minutes) | Feature settings | `getFeatureSettings()['sync_interval']` |
| Initial sync range (days) | Feature settings | `getFeatureSettings()['initial_sync_range']` |
| Suppression action mode (per type) | Feature settings | `getFeatureSettings()['action_modes']` |
| Target segment ID (per type) | Feature settings | `getFeatureSettings()['target_segments']` |
| Notification email | Feature settings | `getFeatureSettings()['notification_email']` |
| Spike threshold | Feature settings | `getFeatureSettings()['spike_threshold']` |
| Last sync timestamp (per type) | Derived | Query latest `SyncLog` with `status=success` |

---

## 4. API Endpoints (Plugin Routes)

All routes require authentication (Mautic admin session).

### 4.1 Dashboard

| Method | Route | Controller | Permission | Description |
|--------|-------|------------|------------|-------------|
| GET | `/s/plugins/syncdata/dashboard` | `DashboardController::indexAction` | `syncdata:view` | Main dashboard page |
| GET | `/s/plugins/syncdata/dashboard/stats` | `DashboardController::statsAction` | `syncdata:view` | AJAX: summary card data |
| GET | `/s/plugins/syncdata/dashboard/chart/{type}` | `DashboardController::chartDataAction` | `syncdata:view` | AJAX: chart data (breakdown/trend) |
| GET | `/s/plugins/syncdata/dashboard/suppressions` | `DashboardController::suppressionsAction` | `syncdata:view` | AJAX: paginated suppressions table |
| GET | `/s/plugins/syncdata/dashboard/history` | `DashboardController::historyAction` | `syncdata:view` | AJAX: sync history log |
| GET | `/s/plugins/syncdata/dashboard/export` | `DashboardController::exportAction` | `syncdata:manage` | CSV export of suppressions |

### 4.2 Sync

| Method | Route | Controller | Permission | Description |
|--------|-------|------------|------------|-------------|
| POST | `/s/plugins/syncdata/sync/run` | `SyncController::runAction` | `syncdata:manage` | Trigger manual sync (AJAX) |
| GET | `/s/plugins/syncdata/sync/status/{logId}` | `SyncController::statusAction` | `syncdata:view` | Check sync progress (AJAX) |

### 4.3 Settings

| Method | Route | Controller | Permission | Description |
|--------|-------|------------|------------|-------------|
| GET | `/s/plugins/syncdata/settings` | `SettingsController::indexAction` | `syncdata:manage` | Settings page |
| POST | `/s/plugins/syncdata/settings/save` | `SettingsController::saveAction` | `syncdata:manage` | Save settings |
| POST | `/s/plugins/syncdata/settings/test` | `SettingsController::testConnectionAction` | `syncdata:manage` | Test SendGrid API connection (AJAX) |

---

## 5. Console Command

### `mautic:syncdata:sync`

```
Usage:
  mautic:syncdata:sync [options]

Options:
  --type=TYPE           Sync type: incremental (default), full
  --suppression=TYPE    Specific suppression type to sync (default: all enabled)
  --limit=N             Max records per suppression type (default: no limit)
  --dry-run             Show what would be synced without making changes
```

**Cron setup:**
```bash
# Recommended: run every 15 minutes
*/15 * * * * php /path/to/mautic/bin/console mautic:syncdata:sync --type=incremental
```

---

## 6. Service Layer

### 6.1 `SendGridApiClient`

HTTP client for SendGrid v3 API.

```php
class SendGridApiClient
{
    // Dependencies: HttpClientInterface, LoggerInterface

    public function testConnection(): array;
    // Returns: ['success' => bool, 'account' => string, 'error' => string|null]

    public function getBounces(int $startTime = 0, int $limit = 500, int $offset = 0): array;
    public function getSpamReports(int $startTime = 0, int $limit = 500, int $offset = 0): array;
    public function getBlocks(int $startTime = 0, int $limit = 500, int $offset = 0): array;
    public function getInvalidEmails(int $startTime = 0, int $limit = 500, int $offset = 0): array;
    public function getGlobalUnsubscribes(int $startTime = 0, int $limit = 500, int $offset = 0): array;
    public function getGroupUnsubscribes(int $groupId = null): array;
    public function getUnsubscribeGroups(): array;

    // Internal
    private function request(string $method, string $endpoint, array $query = []): array;
    private function checkRateLimit(array $headers): void;
}
```

**SendGrid API Endpoint Mapping:**

| Suppression Type | Endpoint | Pagination | Supports `start_time` |
|-----------------|----------|------------|----------------------|
| Bounces | `GET /v3/suppression/bounces` | offset+limit | Yes |
| Spam Reports | `GET /v3/suppression/spam_reports` | offset+limit | Yes |
| Blocks | `GET /v3/suppression/blocks` | offset+limit | Yes |
| Invalid Emails | `GET /v3/suppression/invalid_emails` | offset+limit | Yes |
| Global Unsubscribes | `GET /v3/suppression/unsubscribes` | offset+limit | Yes |
| Group Unsubscribes | `GET /v3/asm/suppressions/{group_id}` | None (full list) | No |
| Unsubscribe Groups | `GET /v3/asm/groups` | None | No |

---

### 6.2 `SuppressionFetcher`

Fetches all enabled suppression types with pagination.

```php
class SuppressionFetcher
{
    // Dependencies: SendGridApiClient, LoggerInterface

    public function fetchAll(array $enabledTypes, int $startTime = 0): array;
    // Returns: ['bounces' => [...], 'spam_reports' => [...], ...]

    public function fetchByType(string $type, int $startTime = 0): array;
    // Returns: array of normalized suppression records

    private function normalize(string $type, array $rawRecord): array;
    // Normalizes different SendGrid response formats to a common structure:
    // ['email', 'type', 'reason', 'status', 'created_at', 'group_id', 'group_name']

    private function paginateFetch(string $type, int $startTime): array;
    // Handles offset-based pagination (500 per page)
}
```

**Normalization Map:**

| SendGrid Field | Normalized Field | Notes |
|---------------|-----------------|-------|
| `email` | `email` | All types |
| `reason` | `reason` | Bounces, blocks |
| `status` | `status` | Bounces, blocks (e.g., `4.0.0`, `5.1.1`) |
| `created` (unix timestamp) | `created_at` (DateTime) | All types |
| `ip` | (discarded) | Not needed |

---

### 6.3 `DncMapper`

Maps SendGrid suppression types to Mautic DNC reasons.

```php
class DncMapper
{
    public function getDncReason(string $suppressionType): int;
    // bounce        → DoNotContact::BOUNCED (2)
    // spam_report   → DoNotContact::UNSUBSCRIBED (1)
    // block         → DoNotContact::MANUAL (3)
    // invalid_email → DoNotContact::BOUNCED (2)
    // global_unsubscribe → DoNotContact::UNSUBSCRIBED (1)
    // group_unsubscribe  → DoNotContact::UNSUBSCRIBED (1)

    public function buildComment(string $type, ?string $reason, ?string $status): string;
    // Returns: "[SendGrid Sync] Bounce: 550 5.1.1 User unknown"
}
```

---

### 6.4 `ContactResolver`

Looks up Mautic contacts by email.

```php
class ContactResolver
{
    // Dependencies: LeadModel

    public function resolveByEmail(string $email): ?Lead;
    // Returns Lead entity or null

    public function resolveByEmails(array $emails): array;
    // Batch lookup. Returns: ['email@example.com' => Lead|null, ...]
    // Uses single query: SELECT * FROM leads WHERE email IN (...)
}
```

---

### 6.5 `SyncEngine`

Orchestrates the full sync process.

```php
class SyncEngine
{
    // Dependencies: SuppressionFetcher, ContactResolver, DncMapper,
    //               DoNotContact model, SegmentModel, SyncLogRepository,
    //               SuppressionRepository, LoggerInterface

    public function sync(string $syncType = 'incremental', ?string $suppressionType = null, bool $dryRun = false): SyncLog;
    // Main entry point. Returns completed SyncLog entity.
    //
    // Flow:
    // 1. Create SyncLog (status=running)
    // 2. Determine startTime (incremental: last success timestamp, full: 0)
    // 3. Fetch suppressions via SuppressionFetcher
    // 4. Batch resolve contacts via ContactResolver
    // 5. For each suppression:
    //    a. Check if already in suppressions table (dedup by unique index)
    //    b. If contact found:
    //       - Check action_mode config for this type
    //       - If 'dnc': add to DNC via DoNotContact model
    //       - If 'segment': add to configured segment via SegmentModel
    //    c. If contact not found: record as unmatched
    //    d. Persist Suppression entity
    // 6. Flush in batches of 100
    // 7. Update SyncLog with counts and status
    // 8. Return SyncLog

    public function getLastSyncTimestamp(string $suppressionType): int;
    // Queries latest successful SyncLog for this type

    private function processBatch(array $suppressions, array $contactMap, array $actionModes): array;
    // Returns: ['added' => N, 'skipped' => N, 'unmatched' => N]
}
```

---

### 6.6 `StatsCalculator`

Generates dashboard statistics.

```php
class StatsCalculator
{
    // Dependencies: SuppressionRepository, SyncLogRepository

    public function getSummaryCards(): array;
    // Returns:
    // [
    //   'total_synced'       => int,
    //   'new_24h'            => int,
    //   'new_7d'             => int,
    //   'new_30d'            => int,
    //   'contacts_protected' => int,  (distinct mautic_contact_id where action_taken != 'unmatched')
    //   'last_sync'          => ['time' => DateTime, 'status' => string]
    // ]

    public function getBreakdownData(): array;
    // Returns: ['labels' => [...], 'data' => [...], 'colors' => [...]]
    // For donut chart by suppression_type

    public function getTrendData(string $period = '30d', ?string $type = null): array;
    // Returns: ['labels' => ['2026-03-01', ...], 'datasets' => [...]]
    // For time-series line chart, grouped by day

    public function getRecentSuppressions(int $page = 1, int $limit = 25, array $filters = []): array;
    // Returns: ['items' => [...], 'total' => int, 'page' => int, 'pages' => int]
    // Filters: type, email (LIKE), dateFrom, dateTo

    public function getSyncHistory(int $page = 1, int $limit = 20): array;
    // Returns: ['items' => [...], 'total' => int]
}
```

---

### 6.7 `NotificationService`

Email alerts on failures or spikes.

```php
class NotificationService
{
    // Dependencies: MailHelper, LoggerInterface

    public function sendSyncFailureNotification(SyncLog $log, string $recipientEmail): void;
    public function sendSpikeAlert(string $type, int $count, int $threshold, string $recipientEmail): void;
    public function checkForSpike(array $breakdown, int $threshold): ?string;
    // Returns suppression type name if spike detected, null otherwise
}
```

---

## 7. Integration Class

```php
class SyncDataIntegration extends AbstractIntegration
{
    const NAME = 'SyncData';

    public function getName(): string;           // 'SyncData'
    public function getDisplayName(): string;     // 'SendGrid Suppression Sync'
    public function getIcon(): string;            // path to icon

    public function getRequiredKeyFields(): array;
    // ['api_key' => 'mautic.syncdata.config.api_key']

    public function getAuthenticationType(): string;
    // 'api_key'
}
```

---

## 8. Permissions

```php
class SyncDataPermissions extends AbstractPermissions
{
    // Defines three permission levels:

    // syncdata:view   — View dashboard (read-only)
    // syncdata:manage — Manual sync, export, settings
    // syncdata:admin  — Full access (includes view + manage)
}
```

---

## 9. Screen Inventory

### 9.1 Dashboard Page (`/s/plugins/syncdata/dashboard`)

| Section | UI Elements | Data Source |
|---------|------------|-------------|
| Header | Page title, "Run Sync" button, last sync status badge | `StatsCalculator::getSummaryCards()` |
| Summary Cards | 4 cards: Total Synced, New (24h/7d/30d toggle), Contacts Protected, Last Sync | `StatsCalculator::getSummaryCards()` |
| Breakdown Chart | Donut chart by suppression type | `StatsCalculator::getBreakdownData()` |
| Trend Chart | Line chart over time, filterable by type and period | `StatsCalculator::getTrendData()` |
| Suppressions Table | Paginated table with search/filter, export CSV button | `StatsCalculator::getRecentSuppressions()` |
| Sync History | Paginated table of sync runs with status indicators | `StatsCalculator::getSyncHistory()` |

**Layout:** Extends Mautic admin layout. Cards in top row (4 columns). Charts side-by-side (50/50). Tables full-width below.

### 9.2 Settings Page (`/s/plugins/syncdata/settings`)

| Section | UI Elements |
|---------|------------|
| API Connection | API key input (password field, masked), Test Connection button |
| Suppression Types | Checkbox toggles for each of 7 types |
| Action Mode | Per-type dropdown: "Add to DNC" or "Add to Segment" + segment selector |
| Sync Schedule | Dropdown: 5min, 15min, 30min, 1hr, 6hr, 12hr, 24hr |
| Initial Range | Dropdown: 7 days, 30 days, 90 days, All time |
| Notifications | Email input for alerts, spike threshold input |

### 9.3 Navigation

```
Mautic Admin Sidebar
└── Plugins (existing)
    └── SendGrid Sync (new menu item)
        ├── Dashboard (default)
        └── Settings
```

---

## 10. File Structure

```
plugins/MauticSyncDataBundle/
├── MauticSyncDataBundle.php              # Bundle class
├── composer.json                              # Package metadata
│
├── Assets/
│   ├── css/
│   │   └── syncdata.css                  # Plugin-specific styles
│   └── js/
│       └── dashboard.js                       # Chart rendering & AJAX interactions
│
├── Config/
│   └── config.php                             # Routes, services, menu, permissions
│
├── Command/
│   └── SyncCommand.php                        # mautic:syncdata:sync console command
│
├── Controller/
│   ├── DashboardController.php               # Dashboard page + AJAX endpoints
│   ├── SettingsController.php                # Settings page + test connection
│   └── SyncController.php                    # Manual sync trigger (AJAX)
│
├── Entity/
│   ├── SyncLog.php                           # Sync history entity
│   ├── SyncLogRepository.php                 # Sync log queries
│   ├── Suppression.php                       # Cached suppression entity
│   └── SuppressionRepository.php             # Suppression queries (stats, filters)
│
├── Integration/
│   └── SyncDataIntegration.php           # IntegrationsBundle integration class
│
├── Security/
│   └── Permissions/
│       └── SyncDataPermissions.php       # view, manage, admin permissions
│
├── Service/
│   ├── SendGridApiClient.php                 # HTTP client for SendGrid v3 API
│   ├── SuppressionFetcher.php                # Fetch + normalize + paginate
│   ├── DncMapper.php                         # SendGrid type → Mautic DNC reason
│   ├── ContactResolver.php                   # Lookup contacts by email
│   ├── SyncEngine.php                        # Orchestrator
│   ├── StatsCalculator.php                   # Dashboard statistics
│   └── NotificationService.php              # Email alerts
│
├── Migrations/
│   ├── Version20260330001.php                # Create plugin_syncdata_log
│   └── Version20260330002.php                # Create plugin_syncdata_suppressions
│
├── Translations/
│   └── en_US/
│       ├── messages.ini                      # UI labels, titles, descriptions
│       └── flashes.ini                       # Flash/toast messages
│
├── Views/
│   ├── Dashboard/
│   │   └── index.html.twig                   # Main dashboard (cards, charts, tables)
│   └── Settings/
│       └── index.html.twig                   # Settings form
│
└── Tests/
    └── Unit/
        ├── Service/
        │   ├── DncMapperTest.php
        │   ├── SuppressionFetcherTest.php
        │   └── SyncEngineTest.php
        └── Entity/
            └── SuppressionTest.php
```

---

## 11. Config/config.php Structure

```php
<?php

return [
    'name'        => 'SendGrid Suppression Sync',
    'description' => 'Sync SendGrid suppressions to Mautic DNC or segments',
    'version'     => '1.0.0',
    'author'      => 'Fabio de Melo',

    'routes' => [
        'main' => [
            'mautic_syncdata_dashboard' => [
                'path'       => '/plugins/syncdata/dashboard',
                'controller' => 'MauticPlugin\MauticSyncDataBundle\Controller\DashboardController::indexAction',
            ],
            'mautic_syncdata_dashboard_stats' => [
                'path'       => '/plugins/syncdata/dashboard/stats',
                'controller' => 'MauticPlugin\MauticSyncDataBundle\Controller\DashboardController::statsAction',
            ],
            'mautic_syncdata_dashboard_chart' => [
                'path'       => '/plugins/syncdata/dashboard/chart/{type}',
                'controller' => 'MauticPlugin\MauticSyncDataBundle\Controller\DashboardController::chartDataAction',
            ],
            'mautic_syncdata_dashboard_suppressions' => [
                'path'       => '/plugins/syncdata/dashboard/suppressions',
                'controller' => 'MauticPlugin\MauticSyncDataBundle\Controller\DashboardController::suppressionsAction',
            ],
            'mautic_syncdata_dashboard_history' => [
                'path'       => '/plugins/syncdata/dashboard/history',
                'controller' => 'MauticPlugin\MauticSyncDataBundle\Controller\DashboardController::historyAction',
            ],
            'mautic_syncdata_dashboard_export' => [
                'path'       => '/plugins/syncdata/dashboard/export',
                'controller' => 'MauticPlugin\MauticSyncDataBundle\Controller\DashboardController::exportAction',
            ],
            'mautic_syncdata_sync_run' => [
                'path'       => '/plugins/syncdata/sync/run',
                'controller' => 'MauticPlugin\MauticSyncDataBundle\Controller\SyncController::runAction',
                'method'     => 'POST',
            ],
            'mautic_syncdata_sync_status' => [
                'path'       => '/plugins/syncdata/sync/status/{logId}',
                'controller' => 'MauticPlugin\MauticSyncDataBundle\Controller\SyncController::statusAction',
            ],
            'mautic_syncdata_settings' => [
                'path'       => '/plugins/syncdata/settings',
                'controller' => 'MauticPlugin\MauticSyncDataBundle\Controller\SettingsController::indexAction',
            ],
            'mautic_syncdata_settings_save' => [
                'path'       => '/plugins/syncdata/settings/save',
                'controller' => 'MauticPlugin\MauticSyncDataBundle\Controller\SettingsController::saveAction',
                'method'     => 'POST',
            ],
            'mautic_syncdata_settings_test' => [
                'path'       => '/plugins/syncdata/settings/test',
                'controller' => 'MauticPlugin\MauticSyncDataBundle\Controller\SettingsController::testConnectionAction',
                'method'     => 'POST',
            ],
        ],
    ],

    'menu' => [
        'main' => [
            'mautic.syncdata.menu.root' => [
                'id'        => 'mautic_syncdata_root',
                'iconClass' => 'ri-mail-check-line',
                'priority'  => 60,
                'children'  => [
                    'mautic.syncdata.menu.dashboard' => [
                        'route' => 'mautic_syncdata_dashboard',
                    ],
                    'mautic.syncdata.menu.settings' => [
                        'route'  => 'mautic_syncdata_settings',
                        'access' => 'syncdata:manage',
                    ],
                ],
            ],
        ],
    ],

    'services' => [
        'integrations' => [
            'mautic.integration.syncdata' => [
                'class' => \MauticPlugin\MauticSyncDataBundle\Integration\SyncDataIntegration::class,
                'tags'  => [['name' => 'mautic.integration', 'alias' => 'SyncData']],
            ],
        ],
        'others' => [
            // Services defined here — see Section 6
        ],
        'commands' => [
            'mautic.syncdata.command.sync' => [
                'class'     => \MauticPlugin\MauticSyncDataBundle\Command\SyncCommand::class,
                'tags'      => [['name' => 'console.command']],
            ],
        ],
    ],
];
```

---

## 12. Dependency Graph

```
SyncCommand
    └── SyncEngine
            ├── SuppressionFetcher
            │       └── SendGridApiClient
            │               └── Integration (API key)
            ├── ContactResolver
            │       └── LeadModel (Mautic)
            ├── DncMapper
            ├── DoNotContact model (Mautic)
            ├── SegmentModel (Mautic — for segment action mode)
            ├── SuppressionRepository
            ├── SyncLogRepository
            └── NotificationService
                    └── MailHelper (Mautic)

DashboardController
    └── StatsCalculator
            ├── SuppressionRepository
            └── SyncLogRepository

SettingsController
    └── SendGridApiClient (for test connection)
```

---

## 13. Implementation Order

### Phase 1: Foundation
1. Bundle class, `composer.json`, `Config/config.php` (skeleton)
2. `SyncDataIntegration` class
3. `SyncLog` entity + migration
4. `Suppression` entity + migration
5. `SyncDataPermissions`
6. Menu registration

### Phase 2: Sync Engine
7. `SendGridApiClient` (all 7 endpoints + pagination + rate-limit check)
8. `SuppressionFetcher` (normalize + paginate all types)
9. `DncMapper`
10. `ContactResolver` (single + batch lookup)
11. `SyncEngine` (full orchestration with DNC + segment action modes)
12. `SyncCommand` (console command with options)
13. Translation files (`messages.ini`, `flashes.ini`)

### Phase 3: Dashboard
14. `StatsCalculator` (all 5 methods)
15. `DashboardController` (page + all AJAX endpoints + CSV export)
16. `Views/Dashboard/index.html.twig` (cards, charts, tables)
17. `Assets/js/dashboard.js` (Chart.js rendering, AJAX, filters)
18. `Assets/css/syncdata.css`

### Phase 4: Settings & Notifications
19. `SettingsController` (form, save, test connection)
20. `Views/Settings/index.html.twig`
21. `NotificationService` (failure + spike alerts)

### Phase 5: Testing
22. `DncMapperTest`
23. `SuppressionFetcherTest`
24. `SyncEngineTest`
25. `SuppressionTest` (entity)

---

## 14. Key Design Decisions

| Decision | Rationale |
|----------|-----------|
| Use IntegrationsBundle (not raw PluginBundle) | Modern Mautic 5.x pattern; built-in encryption, config forms |
| No custom settings table | IntegrationsBundle handles settings natively; less schema to maintain |
| Suppression cache table | Enables dashboard queries without re-hitting SendGrid API |
| Batch contact resolution | Single `WHERE email IN (...)` query instead of N queries |
| Flush in batches of 100 | Prevents memory exhaustion on large syncs |
| Action mode per type (DNC vs segment) | Gives admins flexibility — review before DNC for non-obvious types like blocks |
| Derive last_sync_timestamp from SyncLog | No need for separate state storage; SyncLog is the source of truth |
| Chart.js via Mautic native | No extra JS dependencies; consistent with Mautic admin UI |

---

*Architecture Version: 1.0*
*Created: 2026-03-30*
*Status: AWAITING APPROVAL*

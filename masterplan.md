# MauticSyncData Plugin — Product Masterplan

## 1. Product Overview

**Plugin Name:** MauticSyncData (Mautic SendGrid Suppression Sync)
**Tagline:** "Protect your sender reputation — automatically sync SendGrid suppressions to Mautic's Do Not Contact list."

### The Problem
When using Mautic with SendGrid as the email transport, SendGrid maintains its own suppression lists (bounces, spam reports, blocks, invalid emails, unsubscribes). But Mautic doesn't know about them. This means:
- Mautic keeps trying to send emails to addresses that bounced, were blocked, or reported spam
- This destroys sender reputation and deliverability
- Users must manually cross-reference lists — tedious and error-prone
- No visibility into suppression trends or email hygiene health

### The Solution
A native Mautic plugin that:
1. Connects to SendGrid's v3 API
2. Automatically syncs all 7 suppression types to Mautic's DNC (Do Not Contact) system
3. Provides a rich dashboard with charts and metrics showing suppression trends
4. Runs on a configurable schedule (cron-based)
5. Prevents Mautic from ever attempting to send to suppressed addresses

### Target Audience
- **Primary:** Mautic users who use SendGrid as their email transport (SMTP or API)
- **Secondary:** Marketing agencies managing multiple Mautic instances
- **Tertiary:** Email deliverability consultants

### Revenue Model
- **Free tier:** Basic sync (bounces + global unsubscribes only, manual sync)
- **Pro tier ($49-99 one-time or $9-19/mo):** All 7 suppression types, auto-sync, dashboard, reports, webhook support, multi-account

---

## 2. Core Features (MVP)

### F1: SendGrid API Connection
- Secure API key storage (encrypted in database)
- Connection test button
- Support for multiple SendGrid accounts (future)

### F2: Suppression Sync Engine
Sync all 7 SendGrid suppression types:

| # | SendGrid Suppression | API Endpoint | Mautic DNC Reason | Priority |
|---|---------------------|--------------|-------------------|----------|
| 1 | **Bounces** | `GET /v3/suppression/bounces` | Bounced (reason 2) | MVP |
| 2 | **Spam Reports** | `GET /v3/suppression/spam_reports` | Unsubscribed (reason 1) | MVP |
| 3 | **Blocks** | `GET /v3/suppression/blocks` | Manual (reason 3) | MVP |
| 4 | **Invalid Emails** | `GET /v3/suppression/invalid_emails` | Bounced (reason 2) | MVP |
| 5 | **Global Unsubscribes** | `GET /v3/suppression/unsubscribes` | Unsubscribed (reason 1) | MVP |
| 6 | **Group Unsubscribes** | `GET /v3/asm/suppressions` | Unsubscribed (reason 1) | MVP |
| 7 | **Unsubscribe Groups** | `GET /v3/asm/groups` | Config/mapping only | MVP |

### F3: Sync Modes
- **Manual Sync:** One-click sync from the plugin dashboard
- **Scheduled Sync:** Cron-based automatic sync (configurable interval: 5min, 15min, 30min, 1hr, 6hr, 12hr, 24hr)
- **Incremental Sync:** Only fetch new suppressions since last sync (using `start_time` parameter)
- **Full Sync:** Complete re-sync of all suppressions (on-demand)

### F4: Dashboard & Analytics
A dedicated plugin page with:

#### 4a. Summary Cards (Top Row)
- Total Suppressions Synced (all time)
- New Suppressions (last 24h / 7d / 30d)
- Contacts Protected (emails prevented)
- Last Sync Time + Status

#### 4b. Suppression Breakdown Chart (Donut/Pie)
- Visual breakdown by type: Bounces, Spam, Blocks, Invalid, Unsubscribes
- Color-coded with counts and percentages

#### 4c. Trend Line Chart (Time Series)
- Suppressions over time (daily/weekly/monthly)
- Filterable by suppression type
- Shows sync health — are bounces increasing? Is there a spam spike?

#### 4d. Recent Suppressions Table
- Paginated table of recent synced suppressions
- Columns: Email, Type, Reason, SendGrid Date, Synced Date, Status
- Search/filter by email, type, date range
- Export to CSV

#### 4e. Sync History Log
- Table showing each sync run: timestamp, duration, records fetched, records added, errors
- Status indicator (success/partial/failed)

### F5: Sync Configuration
- Toggle each suppression type on/off
- Set sync interval
- Set how far back to sync on first run (7d, 30d, 90d, all time)
- Map SendGrid unsubscribe groups to Mautic segments/categories
- Choose DNC channel (email by default)
- **Suppression action mode:** Admin chooses per suppression type whether to:
  - Add matched contacts to DNC (default), OR
  - Add matched contacts to a specified Mautic segment (e.g., "SendGrid Bounced")
  - This allows non-destructive workflows where contacts are segmented for review before DNC

### F6: Notifications & Alerts
- Email notification when sync fails
- Alert when suppression spike detected (e.g., >50 bounces in 1 hour)
- In-app notification badge on the plugin icon

---

## 3. Extended Features (Post-MVP / Pro)

### F7: Bidirectional Sync
- Push Mautic DNC contacts back to SendGrid's suppression list
- Prevents SendGrid from attempting delivery even if contacted through other channels

### F8: Webhook Receiver
- Real-time sync via SendGrid Event Webhook
- Instant DNC updates without polling
- Events: `bounce`, `dropped`, `spamreport`, `unsubscribe`, `group_unsubscribe`, `group_resubscribe`

### F9: Multi-Account Support
- Connect multiple SendGrid accounts
- Per-account sync settings
- Useful for agencies managing multiple brands

### F10: Segment Integration
- Auto-create/update Mautic segments based on suppression type
- Example: "SendGrid Bounced" segment, "Spam Reporters" segment
- Enables targeted re-engagement or cleanup campaigns

### F11: Email Hygiene Score
- Per-contact score based on suppression history
- Weighted scoring: spam report = -100, bounce = -50, block = -25
- Visible on contact detail page

### F12: Deliverability Report
- Weekly/monthly email report with:
  - Suppression summary
  - Trend analysis
  - Recommendations (e.g., "Bounces increased 40% — review your list acquisition")
- Exportable PDF

### F13: Audit Log
- Detailed log of every DNC change made by the plugin
- Who/what triggered it, original SendGrid data, Mautic contact ID
- Compliance-friendly (GDPR, CAN-SPAM)

---

## 4. User Roles & Permissions

| Role | Permissions |
|------|------------|
| **Admin** | Full access: configure API keys, sync settings, view dashboard, manual sync, export |
| **Manager** | View dashboard, view sync history, export data, trigger manual sync |
| **Viewer** | View dashboard only (read-only) |

Plugin uses Mautic's built-in permission system (`PluginBundle` security).

---

## 5. Core User Flows

### Flow 1: Initial Setup
1. Admin installs plugin (copy to `plugins/` or install via Marketplace)
2. Navigate to Settings > Plugins > MauticSyncData
3. Click "Configure"
4. Enter SendGrid API Key
5. Click "Test Connection" — shows success/fail with account info
6. Select which suppression types to sync
7. Set sync interval
8. Click "Save & Run First Sync"
9. Dashboard populates with initial data

### Flow 2: Automatic Sync (Background)
1. Cron fires `mautic:syncdata:sync` command
2. Plugin fetches suppressions since last sync timestamp
3. For each suppressed email:
   a. Look up contact in Mautic by email
   b. If found → based on admin config: add to DNC with appropriate reason + comment, OR add to a designated Mautic segment
   c. If not found → log as "unmatched" (no contact creation — log only)
4. Update sync history log
5. Update dashboard metrics
6. If errors → send notification

### Flow 3: Viewing Dashboard
1. User navigates to Plugin > SendGrid Sync dashboard
2. Sees summary cards (total synced, new today, protected, last sync)
3. Views suppression breakdown chart
4. Views trend chart (filters by type/date)
5. Browses recent suppressions table
6. Exports data to CSV if needed

### Flow 4: Investigating a Contact
1. User views a contact's profile in Mautic
2. Sees DNC badge with "SendGrid Bounce" or "SendGrid Spam Report"
3. Clicks to see details: original SendGrid reason, date, bounce code
4. Can manually remove DNC if needed (with audit log entry)

### Flow 5: Handling a Spike
1. Plugin detects >X suppressions in Y time period
2. Sends alert email to admin
3. Admin opens dashboard, sees spike on trend chart
4. Filters recent suppressions to identify the campaign/source
5. Takes corrective action (pause campaign, clean list, etc.)

---

## 6. Tech Stack

| Layer | Technology | Rationale |
|-------|-----------|-----------|
| **Platform** | Mautic >=5.0 (Symfony 6.x) | Target platform (5.x only, no legacy support) |
| **Language** | PHP >=8.1 | Mautic 5.x requirement |
| **Plugin Type** | Mautic IntegrationsBundle | Modern integration framework |
| **Templates** | Twig | Required for Mautic 5.x (no PHP templates) |
| **Frontend Charts** | Chart.js 2.9.4 (bundled with Mautic) | Native — no extra dependencies needed |
| **HTTP Client** | Symfony HttpClient / Guzzle | SendGrid API communication |
| **Database** | Doctrine ORM (MySQL/MariaDB) | Mautic's ORM layer |
| **Queue** | Mautic's command scheduler (cron) | Background sync |
| **Testing** | PHPUnit | Mautic's test framework |
| **Code Quality** | PHP CS Fixer, PHPStan | Marketplace best practices |

---

## 7. Third-Party Integrations

| Service | Purpose | API |
|---------|---------|-----|
| **SendGrid** | Suppression data source | v3 REST API (Bearer token auth) |
| **Mautic DNC** | Suppression data target | Internal Mautic API (DoNotContact model) |
| **Mautic Segments** | Auto-segment creation (Pro) | Internal Mautic API |

### SendGrid API Rate Limits
- 600 requests/minute across all endpoints
- Pagination: max 500 items per request
- Basic rate-limit awareness (check `X-RateLimit-Remaining` header, pause if near limit) — no complex backoff needed since typical sync volumes are well within limits

---

## 8. Security Considerations

### API Key Storage
- SendGrid API key stored via IntegrationsBundle's native `getApiKeys()` (encrypted at rest automatically)
- Never exposed in logs, UI (masked), or exports
- Key permissions: only `Suppressions Read` scope needed (principle of least privilege)

### Data Protection
- Plugin processes email addresses (PII) — must handle per GDPR
- Audit log for all DNC changes (who, when, why)
- No suppression data sent to external services (stays in Mautic DB)
- CSRF protection on all forms
- Input validation on all configuration fields

### Authentication
- Plugin settings protected by Mautic's role-based permission system
- API key validation before save
- Rate limiting on test connection button (prevent abuse)

---

## 9. Development Phases & Milestones

### Phase 1: Foundation (Week 1-2)
- [ ] Plugin scaffolding (bundle structure, config.php, composer.json)
- [ ] Integration class with ConfigForm (API key input)
- [ ] SendGrid API client service (authenticated HTTP client)
- [ ] Connection test functionality
- [ ] Basic plugin settings page
- [ ] Version compatibility: Mautic >=5.0, PHP >=8.1

### Phase 2: Sync Engine (Week 2-3)
- [ ] Suppression fetcher service (all 7 types)
- [ ] Pagination handling (500 items/page)
- [ ] Rate limiting with backoff
- [ ] Incremental sync logic (start_time tracking)
- [ ] DNC mapper (SendGrid type → Mautic DNC reason)
- [ ] Contact lookup by email
- [ ] DNC writer service
- [ ] Sync history entity and repository
- [ ] Console command: `mautic:syncdata:sync`

### Phase 3: Dashboard UI (Week 3-4)
- [ ] Dashboard controller and route
- [ ] Summary cards (total, new, protected, last sync)
- [ ] Suppression breakdown chart (donut)
- [ ] Trend chart (time series)
- [ ] Recent suppressions table (paginated, searchable)
- [ ] Sync history log table
- [ ] Manual sync button (AJAX)
- [ ] CSS/styling (matches Mautic admin theme)

### Phase 4: Configuration & Notifications (Week 4-5)
- [ ] Per-type toggle settings
- [ ] Sync interval configuration
- [ ] Initial sync range setting
- [ ] Unsubscribe group mapping UI
- [ ] Email notifications on failure
- [ ] Spike detection alerts
- [ ] Contact detail integration (DNC badge with SendGrid info)

### Phase 5: Testing & Polish (Week 5-6)
- [ ] Unit tests (sync engine, DNC mapper, API client)
- [ ] Integration tests (full sync flow with mocked API)
- [ ] Edge cases (no contacts match, API errors, rate limits)
- [ ] Error handling hardening
- [ ] Logging (Monolog integration)
- [ ] Documentation (README, setup guide, screenshots)
- [ ] Marketplace submission prep (composer.json, versioning)

### Phase 6: Pro Features (Post-Launch)
- [ ] Webhook receiver for real-time sync
- [ ] Bidirectional sync (Mautic → SendGrid)
- [ ] Multi-account support
- [ ] Segment auto-creation
- [ ] Email hygiene score
- [ ] Deliverability report (PDF)
- [ ] License key validation system

---

## 10. Success Metrics

| Metric | Target |
|--------|--------|
| First sync completion time (10k contacts) | < 2 minutes |
| Incremental sync time | < 30 seconds |
| Dashboard page load | < 1.5 seconds |
| Zero missed suppressions | 100% sync accuracy |
| Marketplace rating | 4.5+ stars |
| Support tickets per 100 installs | < 5 |

---

## 11. Competitive Advantage

| Feature | No-Code Tools (Zapier/Make) | This Plugin |
|---------|---------------------------|-------------|
| Native Mautic integration | No (external) | Yes |
| All 7 suppression types | Partial | Full |
| Dashboard & analytics | No | Yes |
| One-time cost | $20-50/month recurring | One-time purchase |
| Setup time | 30-60 min | 5 min |
| Real-time webhook sync | Complex setup | Built-in (Pro) |
| No external dependencies | Requires 3rd party | Self-contained |

---

## 12. File Structure

```
plugins/MauticSyncDataBundle/
├── MauticSyncDataBundle.php          # Bundle registration
├── composer.json                          # Package metadata & dependencies
├── README.md                              # Documentation
├── LICENSE                                # License file
│
├── Assets/
│   ├── css/
│   │   └── syncdata.css              # Plugin styles
│   └── js/
│       └── dashboard.js                   # Dashboard charts & interactions (uses Mautic's native Chart.js)
│
├── Config/
│   └── config.php                         # Service definitions, routes, menu
│
├── Controller/
│   ├── DashboardController.php           # Dashboard page
│   ├── SettingsController.php            # Configuration page
│   └── SyncController.php                # Manual sync actions (AJAX)
│
├── Entity/
│   ├── SyncLog.php                       # Sync history record
│   ├── SyncLogRepository.php             # Sync log queries
│   ├── Suppression.php                   # Cached suppression record
│   └── SuppressionRepository.php         # Suppression queries
│
├── EventListener/
│   ├── ConfigSubscriber.php              # Plugin config events
│   ├── ContactSubscriber.php             # Contact detail page integration
│   └── MenuSubscriber.php                # Navigation menu items
│
├── Form/
│   ├── Type/
│   │   ├── ConfigAuthType.php            # API key configuration form
│   │   ├── ConfigFeaturesType.php        # Feature toggles form
│   │   └── SyncSettingsType.php          # Sync configuration form
│
├── Integration/
│   ├── SyncDataIntegration.php       # Main integration class
│   ├── Configuration.php                 # Integration configuration
│   └── Support/
│       └── ConfigSupport.php             # Config form handling
│
├── Command/
│   └── SyncCommand.php                   # mautic:syncdata:sync CLI command
│
├── Service/
│   ├── SendGridApiClient.php             # HTTP client for SendGrid API
│   ├── SuppressionFetcher.php            # Fetch suppressions by type
│   ├── DncMapper.php                     # Map SendGrid types → Mautic DNC
│   ├── SyncEngine.php                    # Orchestrates the sync process
│   ├── ContactResolver.php              # Lookup Mautic contacts by email
│   ├── NotificationService.php           # Email alerts & notifications
│   └── StatsCalculator.php              # Dashboard statistics
│
├── Migrations/
│   ├── Version20260330_001.php           # Create sync_log table
│   └── Version20260330_002.php           # Create suppression_cache table
│
├── Translations/
│   └── en_US/
│       ├── messages.ini                  # UI strings
│       └── flashes.ini                   # Flash messages
│
├── Views/
│   ├── Dashboard/
│   │   └── index.html.twig              # Main dashboard page
│   ├── Settings/
│   │   └── config.html.twig             # Configuration page
│   └── Contact/
│       └── suppression_tab.html.twig    # Contact detail tab
│
├── Security/
│   └── Permissions/
│       └── SyncDataPermissions.php   # Role-based permissions
│
└── Tests/
    ├── Unit/
    │   ├── Service/
    │   │   ├── DncMapperTest.php
    │   │   ├── SuppressionFetcherTest.php
    │   │   └── SyncEngineTest.php
    │   └── Entity/
    │       └── SyncLogTest.php
    └── Functional/
        └── SyncFlowTest.php
```

---

## 13. Database Schema

### Table: `plugin_syncdata_log`
| Column | Type | Description |
|--------|------|-------------|
| id | INT (PK, AI) | Primary key |
| sync_type | VARCHAR(50) | 'incremental', 'full', 'manual' |
| started_at | DATETIME | Sync start time |
| completed_at | DATETIME | Sync end time |
| status | VARCHAR(20) | 'success', 'partial', 'failed' |
| records_fetched | INT | Total records from SendGrid |
| records_added | INT | New DNC entries created |
| records_skipped | INT | Already existed in DNC |
| records_unmatched | INT | No matching Mautic contact |
| error_message | TEXT | Error details if failed |
| suppression_breakdown | JSON | `{"bounces": 12, "spam": 3, ...}` |
| created_at | DATETIME | Record creation timestamp |

### Table: `plugin_syncdata_suppressions`
| Column | Type | Description |
|--------|------|-------------|
| id | INT (PK, AI) | Primary key |
| email | VARCHAR(255) | Suppressed email address |
| suppression_type | VARCHAR(50) | bounce, spam, block, invalid, global_unsub, group_unsub |
| sendgrid_reason | TEXT | Original reason from SendGrid |
| sendgrid_status | VARCHAR(50) | Bounce/block status code |
| sendgrid_created | DATETIME | When SendGrid recorded it |
| sendgrid_group_id | INT | Unsubscribe group ID (nullable) |
| sendgrid_group_name | VARCHAR(100) | Unsubscribe group name (nullable) |
| mautic_contact_id | INT | Matched Mautic contact ID (nullable) |
| dnc_applied | BOOLEAN | Whether DNC was successfully applied |
| synced_at | DATETIME | When this record was synced |
| created_at | DATETIME | Record creation timestamp |
| updated_at | DATETIME | Last update timestamp |

**Indexes:**
- `idx_email` on `email`
- `idx_type` on `suppression_type`
- `idx_synced` on `synced_at`
- `idx_contact` on `mautic_contact_id`
- `UNIQUE idx_email_type` on (`email`, `suppression_type`, `sendgrid_created`)

### Settings Storage (No Custom Table)

Settings are stored via Mautic's IntegrationsBundle native mechanism:
- **API keys** → `Integration::getApiKeys()` (encrypted automatically)
- **Feature toggles & sync config** → `Integration::getFeatureSettings()`

Only sync-specific runtime state needs custom storage:
- `last_sync_timestamp` (per suppression type) → stored in `plugin_syncdata_log` (derived from latest successful log entry)
- No custom settings table needed — reduces schema footprint

---

## 14. Additional Ideas Worth Considering

### Idea 1: "Clean My List" Action
- Bulk action in Mautic contact list: "Check against SendGrid"
- Select contacts → checks each against SendGrid suppression API
- Shows which are suppressed and offers to DNC them

### Idea 2: Pre-Send Check
- Before Mautic sends a campaign email, plugin checks if the recipient is on any SendGrid suppression list
- Prevents the send even before it reaches SendGrid
- Saves API calls and improves send speed

### Idea 3: Suppression Export to Other ESPs
- Export the aggregated suppression list in formats compatible with other ESPs
- Useful for users migrating from SendGrid or using multiple ESPs

### Idea 4: Integration with Mautic's Built-in Reports
- Add custom report types to Mautic's reporting engine
- Suppressions by date, by type, by campaign, by segment
- Reuses Mautic's report builder UI

### Idea 5: White-Label Support
- Allow agencies to rebrand the plugin
- Custom colors, logo, name
- Premium add-on for agency tier

---

*Masterplan Version: 1.1*
*Created: 2026-03-30*
*Updated: 2026-03-30*
*Status: APPROVED*

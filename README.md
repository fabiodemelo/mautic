# SyncData for Mautic — v2.0

**Sync every SendGrid suppression — bounces, spam reports, blocks, invalid emails, global unsubscribes, and group unsubscribes — into Mautic's Do Not Contact list (or any segment you choose), automatically and on a schedule.**

Protect your sender reputation by guaranteeing Mautic never sends to addresses that SendGrid has already suppressed, while keeping every action visible in a real-time dashboard.

> **Support:** [support@demelos.com](mailto:support@demelos.com)
> **Repository:** https://github.com/fabiodemelo/mautic
> **License:** GPL-3.0-or-later

---

## What This Plugin Does

When you use SendGrid as your Mautic email transport, SendGrid maintains its **own** suppression lists (bounces, spam reports, blocks, invalid emails, unsubscribes). Mautic doesn't know about them, which means:

- Mautic keeps trying to send to addresses SendGrid has permanently rejected
- Your sender reputation degrades silently
- Bounce rates climb and deliverability drops
- You can't see, in one place, who has been suppressed and why

**SyncData closes the loop.** It pulls every SendGrid suppression on a schedule, matches each address to the corresponding Mautic contact, and either adds them to Mautic's DNC list or moves them to a segment of your choice — with full visibility through a dedicated dashboard.

---

## Features

### Sync engine
- **All 7 SendGrid suppression types** synced in a single run:
  - Bounces · Spam Reports · Blocks · Invalid Emails · Global Unsubscribes · Group Unsubscribes · Unsubscribe Group mapping
- **Per-type action mode**: send each type to **DNC** or to a **specific Mautic segment**
- **Incremental sync**: only fetches suppressions added since the last successful run
- **Full sync**: optional re-pull of historical data within a configurable window
- **Configurable initial sync range**: 7 / 30 / 90 days, or **All time**
- **Max Records Per Sync** cap so first-time imports of huge lists can be processed in chunks
- **Automatic re-linking**: previously UNMATCHED suppressions get linked the moment the matching contact is created in Mautic
- **Deduplication** via a unique constraint (email + type + source date) — re-runs are safe
- **Memory-safe batching** in groups of 100 with automatic Doctrine entity-manager flush + clear
- **Dry-run mode** to preview what would happen before changing any data

### Dashboard
- **4 summary cards**: Total Synced · New (24h / 7d / 30d) · Contacts Protected · Last Sync timestamp with status colour
- **Donut chart**: suppression breakdown by type
- **Trend line chart**: suppressions over time, filterable by period (7/30/90 days) and by suppression type
- **Searchable, paginated suppressions table** with type filter, email search, contact link, action badge
- **Sync history log** showing every run with status, duration, fetched/added/skipped/unmatched counts and any errors
- **CSV export** of all suppressions, respecting the active filters
- **Run Sync Now** button with full-screen overlay, animated four-dot spinner, and live elapsed timer

### Settings
- **API key field with masked preview** — you can confirm the key is set without revealing it
- **Color-coded status panel** for the API connection:
  - 🟢 Green when the key is saved
  - 🟢 Brighter green after a successful Test Connection
  - 🟡 Amber when no key is configured
  - 🔴 Red when Test Connection fails
- **Test Connection** button that hits SendGrid `/v3/user/profile` and reports the connected account
- **Sync Interval** picker (5min → 24hr) for cron alignment
- **Notification email** for sync failures
- **Spike alert threshold** that emails you when a sync ingests more than N suppressions of any one type

### Security & operations
- **Encrypted API key storage** using Mautic's IntegrationsBundle EncryptionService
- **CSRF-protected forms** and AJAX endpoints
- **Role-based permissions**: separate `dashboard:view` and `settings:edit` permissions
- **Console command** for cron jobs and manual ops: `mautic:syncdata:sync`
- **Spike detection + email alerts** for unusual suppression volume
- **Rate-limit aware** API client that pauses when SendGrid's `X-RateLimit-Remaining` header drops below 10

---

## Requirements

| Requirement | Version |
|-------------|---------|
| Mautic | >= 5.0 |
| PHP | >= 8.1 |
| MySQL / MariaDB | per Mautic 5 requirements |
| SendGrid API Key | v3 with **Suppressions Read** permission |

---

## Installation

### Step 1 — Clone into the Mautic plugins folder

```bash
cd /path/to/mautic/plugins/
git clone https://github.com/fabiodemelo/mautic.git MauticSyncDataBundle
```

> The directory must be named exactly **`MauticSyncDataBundle`** — Mautic discovers plugins by folder name.

### Step 2 — Clear cache and install

Run these from the **Mautic root** (the directory that contains `bin/console`):

```bash
cd /path/to/mautic/
rm -rf var/cache/*
php bin/console cache:clear
php bin/console mautic:plugins:reload
```

You should see the plugin counted in the `mautic:plugins:reload` output. The two database tables (`plugin_syncdata_log`, `plugin_syncdata_suppressions`) are created automatically by the bundled migrations.

### Step 3 — Verify

1. Log into Mautic
2. Open **Settings → Plugins** and confirm **SyncData** appears with the SyncData icon
3. Open the sidebar — you'll see a new **SyncData** menu with **Dashboard** and **Settings** entries

---

## Configuration

### 1. Generate a SendGrid API key

1. Go to [SendGrid → Settings → API Keys](https://app.sendgrid.com/settings/api_keys)
2. Click **Create API Key**
3. Choose **Restricted Access**
4. Enable **Suppressions → Read** (Read-only is sufficient — the plugin never writes back to SendGrid)
5. Click **Create & Copy** the key

### 2. Connect it to Mautic

1. In Mautic, go to **SyncData → Settings**
2. Paste the API key into the **SyncData API Key** field
3. Click **Test Connection** — the panel turns bright green and shows your SendGrid account name
4. Scroll to the bottom and click **Save Settings**
5. After save, you'll see a green status badge **Key Saved** plus a masked preview like `SG.zeSNlWa2••••••••••••` so you always know the key is on file

### 3. Configure the per-type action

For each of the six suppression types, decide:

| Action | When to choose |
|--------|----------------|
| **Add to DNC** | You want Mautic to immediately stop sending to these addresses (recommended for bounces, invalid emails) |
| **Add to Segment** | You want a human to review them first — the contact is moved to the segment you pick |

| Suppression Type | Default DNC Reason | Typical use |
|-----------------|-------------------|-------------|
| Bounce | Bounced | Hard/soft bounces |
| Spam Report | Unsubscribed | Recipients who hit "report spam" |
| Block | Manual | Temporary delivery blocks |
| Invalid Email | Bounced | Malformed or non-existent addresses |
| Global Unsubscribe | Unsubscribed | Globally unsubscribed recipients |
| Group Unsubscribe | Unsubscribed | Unsubscribed from a SendGrid group |

### 4. Choose schedule and limits

| Setting | Options | Default | What it does |
|---------|---------|---------|--------------|
| Sync Interval | 5 min / 15 min / 30 min / 1 hr / 6 hr / 12 hr / 24 hr | 15 min | Set your cron to match this cadence |
| Initial Sync Range | 7 / 30 / 90 days, All time | 30 days | How far back to look on the very first sync (or any `--type=full` run) |
| Max Records Per Sync | 0 = unlimited, otherwise N | 0 | Caps each run — useful when first importing a list of 50k+ suppressions |
| Notification Email | Any email | (empty) | Where failure and spike alerts are sent |
| Spike Threshold | Positive integer | 50 | Alert if any single type ingests more than this in one run |

Click **Save Settings**.

### 5. Set up the cron job

Add this to your crontab (`crontab -e`) so syncs run automatically:

```cron
# Incremental sync every 15 minutes — match your Sync Interval setting
*/15 * * * * php /path/to/mautic/bin/console mautic:syncdata:sync --type=incremental >/dev/null 2>&1

# Optional: weekly full re-sync on Sunday 2 AM (catches anything that slipped through)
0 2 * * 0 php /path/to/mautic/bin/console mautic:syncdata:sync --type=full >/dev/null 2>&1
```

---

## Usage

### Dashboard

Open **SyncData → Dashboard** to see:

- **4 cards** at the top: Total Synced · New (24h/7d/30d) · Contacts Protected · Last Sync timestamp
- **Suppression Breakdown** donut chart — instant view of which suppression type dominates
- **Suppression Trends** line chart — filter by period and by type
- **Recent Suppressions** table — search by email, filter by type, paginate, click an email to jump to the contact
- **Sync History** table — every run with start time, duration, counts and any error
- **Run Sync Now** button — triggers a synchronous sync with a full-screen progress overlay

### CSV export

Click **Export CSV** to download every synced suppression. Filters in the search row are honored.

### Console commands

```bash
# Incremental sync (default — only suppressions added since the last successful run)
php bin/console mautic:syncdata:sync

# Full re-sync within the configured Initial Sync Range
php bin/console mautic:syncdata:sync --type=full

# Sync only one suppression type
php bin/console mautic:syncdata:sync --suppression=bounce

# Dry run — show what would happen, change nothing
php bin/console mautic:syncdata:sync --dry-run

# Combine flags
php bin/console mautic:syncdata:sync --type=full --suppression=spam_report --dry-run
```

**Valid `--suppression` values:**
`bounce` · `spam_report` · `block` · `invalid_email` · `global_unsubscribe` · `group_unsubscribe`

---

## How matching works

For every suppression coming back from SendGrid, the plugin:

1. **Looks up the contact** by email (case-insensitive, batched with `IN (...)` for speed)
2. If a contact exists → applies the configured action (DNC or Segment)
3. If no contact exists → marks the suppression as **UNMATCHED** but still records it for reference
4. On every subsequent sync, the plugin **re-checks all UNMATCHED rows** (up to 500 per run) and links them as soon as the matching contact gets created in Mautic

This means you never lose suppression data, even if SendGrid knows about an address before Mautic does.

---

## Database

The plugin creates two tables (auto-managed via Doctrine migrations):

| Table | Purpose |
|-------|---------|
| `plugin_syncdata_log` | Every sync run with status, duration, counts, errors, breakdown JSON |
| `plugin_syncdata_suppressions` | One row per (email + type + source date) with the action taken and the linked Mautic contact id |

Indexes are defined on `email`, `suppression_type`, `synced_at`, `mautic_contact_id`, and a **unique** constraint on `(email, suppression_type, source_created_at)` enforces deduplication.

---

## Permissions

The plugin registers two permission groups under Mautic's role system:

| Permission | Grants |
|-----------|--------|
| `plugin:syncdata:dashboard:view` | View the dashboard, suppressions table, sync history (read-only) |
| `plugin:syncdata:settings:edit` | Change settings, run manual sync, export CSV |

Configure per-role under **Settings → Roles**.

---

## Plugin Architecture

```
plugins/MauticSyncDataBundle/
├── MauticSyncDataBundle.php       # Bundle registration
├── composer.json                  # Package metadata
├── README.md                      # This file
├── ARCHITECTURE.md                # Technical reference
├── masterplan.md                  # Product vision
│
├── Assets/
│   ├── css/syncdata.css           # Dashboard + settings styles
│   ├── js/dashboard.js            # Charts, AJAX, sync overlay
│   └── img/syncdata.jpg           # Plugin logo shown in Settings → Plugins
│
├── Command/
│   └── SyncCommand.php            # `mautic:syncdata:sync` console command
│
├── Config/
│   └── config.php                 # Routes, services, menu, version
│
├── Controller/
│   ├── DashboardController.php    # Dashboard page + AJAX endpoints
│   ├── SettingsController.php     # Settings page, save, test connection
│   └── SyncController.php         # Manual sync trigger and status
│
├── Entity/
│   ├── Suppression.php            # Cached suppression entity
│   ├── SuppressionRepository.php  # Stats, search, dedup, find-unmatched
│   ├── SyncLog.php                # Sync run entity
│   └── SyncLogRepository.php      # History queries
│
├── Form/Type/
│   ├── ConfigAuthType.php         # API key form
│   └── ConfigFeaturesType.php     # Feature settings form
│
├── Integration/
│   └── SyncDataIntegration.php    # IntegrationsBundle integration class
│
├── Migrations/
│   ├── Version20260330001.php     # plugin_syncdata_log table
│   └── Version20260330002.php     # plugin_syncdata_suppressions table
│
├── Resources/
│   └── views/                     # Twig templates (Dashboard, Settings)
│
├── Security/
│   └── Permissions/
│       └── SyncDataPermissions.php
│
├── Service/
│   ├── SyncDataApiClient.php      # Guzzle client for SendGrid v3 API
│   ├── SuppressionFetcher.php     # Per-type fetch + pagination + normalization
│   ├── ContactResolver.php        # Batch email → Lead lookup
│   ├── DncMapper.php              # Suppression type → DNC reason mapping
│   ├── SyncEngine.php             # Orchestrates the whole sync flow
│   ├── StatsCalculator.php        # Dashboard stats and chart data
│   └── NotificationService.php    # Failure + spike emails
│
├── Tests/Unit/                    # PHPUnit tests
└── Translations/en_US/            # English strings
```

### Service dependency graph

```
SyncCommand ─┐
SyncController ─┼─► SyncEngine ─┬─► SuppressionFetcher ─► SyncDataApiClient ─► (SendGrid API)
                │                ├─► ContactResolver ────► EntityManager (Lead)
                │                ├─► DncMapper
                │                ├─► DoNotContact (Mautic) — for DNC mode
                │                ├─► ListModel (Mautic)    — for Segment mode
                │                └─► NotificationService ──► MailHelper

DashboardController ─► StatsCalculator ─► Suppression / SyncLog repositories
SettingsController  ─► IntegrationsHelper, EncryptionService, ListModel
```

---

## Updating

```bash
cd /path/to/mautic/plugins/MauticSyncDataBundle/
git pull

cd /path/to/mautic/
rm -rf var/cache/*
php bin/console cache:clear
php bin/console mautic:plugins:reload
```

CSS and JS are cache-busted automatically by the templates, so the browser pulls the new versions on next reload.

---

## Troubleshooting

### "SyncData API key is not configured or could not be decrypted"
Open **SyncData → Settings**, paste your SendGrid key into the input field, and click **Save Settings**. (This typically happens after upgrading from a pre-2.0 build that stored keys unencrypted.)

### "Connection failed: 403 Forbidden"
Your API key doesn't have the right scope. Generate a new SendGrid key with **Suppressions → Read** enabled and re-save.

### Sync runs but only finds a few records
1. Check **Initial Sync Range** — the default 30 days excludes older suppressions. Set to **All time** and run `--type=full` once.
2. Check **Max Records Per Sync** — if it's set to a small number, the run stops there. Set to `0` for unlimited.
3. Make sure the suppression types you care about are **enabled** on the Settings page.

### Sync log shows `records_fetched=0` for several days
First confirm whether SendGrid actually has new suppressions — the plugin only reports what the provider returns. Quick direct API check from the Mautic root:

```bash
APIKEY="SG.xxxxxxxxxxxxxxxxxxxx"   # the same key you saved in Settings
SINCE=$(date -d '-30 days' +%s)
for ep in bounces blocks spam_reports invalid_emails; do
  printf "%-16s " "$ep"
  curl -s -o /dev/null -w "http=%{http_code}\n" \
    -H "Authorization: Bearer $APIKEY" \
    "https://api.sendgrid.com/v3/suppression/$ep?start_time=$SINCE&limit=500"
done
```

- All `http=200` and JSON `[]` → SendGrid genuinely has nothing new. Plugin is correct, no fix needed.
- Any `http=403` → API key lost the `suppressions.*.read` scope. Regenerate.
- One endpoint returns rows but the plugin still reports 0 → open an issue with the response body.

Also confirm the **cron** is actually running. `crontab -l | grep syncdata` should show your scheduled entry. If empty, scheduled syncs aren't happening at all — manual runs from the dashboard still work.

### DNC reason mapping reference (v2.3.1+)
| Suppression type | `lead_donotcontact.reason` |
|---|---|
| bounce / block / spam_report / invalid_email | **2** (BOUNCED) |
| global_unsubscribe / group_unsubscribe | **1** (UNSUBSCRIBED) |

If a contact already has an UNSUBSCRIBED row for the email channel, Mautic refuses to overwrite it with BOUNCED. The plugin marks those `action_taken='dnc_exists'` (counts toward `records_skipped` in the sync log) — this is expected, not a bug.

### Dashboard or Settings shows "Uh oh! I think I broke it…"
Check the Mautic log for the real error:
```bash
tail -50 /path/to/mautic/var/logs/mautic_prod-$(date +%Y-%m-%d).php
```
Then email the relevant lines to **support@demelos.com**.

### Action Taken says "UNMATCHED" for everything
Those email addresses don't exist as Mautic contacts. The plugin still records them, and the next sync will auto-link any that get added to Mautic later. If the contacts *do* exist, double-check the email addresses in **Contacts** for typos or extra whitespace.

### Browser shows the old UI after an update
Hard-refresh with `Ctrl+Shift+R` (Windows / Linux) or `Cmd+Shift+R` (macOS). The plugin includes per-minute cache-busting on its CSS/JS but a stuck cache can still happen.

### High memory usage during a huge first import
Set **Max Records Per Sync** to e.g. `5000`, run a full sync, then run incremental syncs on a tighter cron until the backlog clears.

---

## Uninstallation

1. Disable the plugin under **Settings → Plugins** (toggle off and save)
2. Remove the plugin directory and clear cache:
   ```bash
   cd /path/to/mautic/
   rm -rf plugins/MauticSyncDataBundle/
   rm -rf var/cache/*
   php bin/console cache:clear
   ```
3. (Optional) Drop the plugin database tables:
   ```sql
   DROP TABLE IF EXISTS plugin_syncdata_suppressions;
   DROP TABLE IF EXISTS plugin_syncdata_log;
   ```
4. (Optional) Remove the integration row:
   ```sql
   DELETE FROM plugin_integration_settings WHERE name = 'SyncData';
   ```

---

## Changelog

### v2.3.1 — Map all deliverability events to BOUNCED
- DncMapper updated: spam_report and block now map to
  `DoNotContact::BOUNCED` (was UNSUBSCRIBED and MANUAL respectively).
  All deliverability problems now share the same DNC reason so
  SendGrid's bounce/block/spam/invalid signals don't pollute the
  user-driven UNSUBSCRIBED list.
- Final mapping: bounce / block / spam_report / invalid_email →
  BOUNCED (2); global_unsubscribe / group_unsubscribe → UNSUBSCRIBED (1).

### v2.3.0 — Honest DNC status reporting
- Captures `addDncForContact` return value instead of assuming success.
  Previously every contact got `action_taken='dnc'` even when Mautic
  silently refused to write the DNC row (most often because the
  contact already had an UNSUBSCRIBED entry which the BOUNCED code
  path will not override).
- Two new action states: **`dnc_exists`** (Mautic refused — contact
  already protected) and **`dnc_failed`** (write threw or returned
  unexpected). Honest counts now reflect what really happened.
- Try/catch around the DNC call so a single bad row never aborts a
  whole sync. Failures land in `monolog` and the SyncLog row gets
  `markPartial()` with up to 20 sample errors.
- Re-link pass uses the same return-value handling.

### v2.2.0 — Mautic 7 compatibility
- **Mautic 7.0+ support.** Plugin migrations rewritten to extend
  `Mautic\IntegrationsBundle\Migration\AbstractMigration` instead of
  Doctrine's `AbstractMigration` (Mautic 7 changed the constructor
  signature, breaking the old base class).
- Bumped minimum PHP requirement to **8.2** to match Mautic 7
- Existing tables are preserved — `isApplicable()` returns false when
  the table already exists, so upgraders aren't disrupted

### v2.1.0
- Trend chart now plots by **provider creation date** (`source_created_at`) instead of import date, so a one-time backfill no longer collapses to a single spike
- Trend chart fills missing days with zero so the line is continuous
- Refreshed donut palette to a maximally distinct rainbow (red / orange / yellow / green / blue / purple) — adjacent slices never blur together
- "Run Sync Now" button is now wide, green, and prominent with hover lift and disabled state
- Documentation refresh

### v2.0.0
- Mautic 5.x compatible (Symfony 6, PHP 8.1+)
- Encrypted API key storage via IntegrationsBundle EncryptionService
- New full-screen sync overlay with animated spinner and elapsed timer
- New **Max Records Per Sync** setting for chunked imports
- New **API key masked preview** and **color-coded status panel** (Saved / Verified / Missing / Invalid)
- Auto re-link of previously UNMATCHED suppressions on every sync
- Cache-busted CSS/JS so updates show up immediately
- Fully refactored to follow the Mautic 5 controller pattern
- Plugin renamed throughout — display name, services, entities, and translations now use **SyncData**

### v1.0.0
- Initial release

---

## Support

Questions, bug reports, or feature requests:

- **Email:** [support@demelos.com](mailto:support@demelos.com)
- **Issues:** https://github.com/fabiodemelo/mautic/issues

When reporting a bug, please include:
1. Mautic version (`php bin/console --version`)
2. PHP version (`php -v`)
3. The plugin version (from `composer.json` or `Config/config.php`)
4. The relevant lines from `var/logs/mautic_prod-YYYY-MM-DD.php`

---

## License

GPL-3.0-or-later

## Author

**Fabio de Melo** · [support@demelos.com](mailto:support@demelos.com)

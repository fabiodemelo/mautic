# MauticSyncData Plugin

**Sync suppressions (bounces, spam reports, blocks, invalid emails, unsubscribes) to Mautic's Do Not Contact list or segments — automatically.**

Protect your sender reputation by ensuring Mautic never sends to addresses that SendGrid has already suppressed.

---

## Features

- **All 7 SendGrid suppression types**: Bounces, Spam Reports, Blocks, Invalid Emails, Global Unsubscribes, Group Unsubscribes, and Unsubscribe Group mapping
- **Flexible action modes**: Per suppression type, choose to add contacts to DNC *or* to a Mautic segment for review
- **Automated sync**: Cron-based incremental sync with configurable intervals (5min to 24hr)
- **Rich dashboard**: Summary cards, donut breakdown chart, trend line chart, searchable/filterable suppressions table, sync history log
- **CSV export**: Export all synced suppressions
- **Spike detection**: Email alerts when suppression counts exceed a configurable threshold
- **Dry-run mode**: Preview what would be synced without making changes
- **Deduplication**: Unique constraint prevents duplicate suppression records
- **Batch processing**: Memory-efficient processing in batches of 100

---

## Requirements

| Requirement | Version |
|-------------|---------|
| Mautic | >= 5.0 |
| PHP | >= 8.1 |
| SyncData API Key | v3 with **Suppressions Read** permission |

---

## Installation

### Option 1: Manual Installation

1. **Clone** the repository directly into your Mautic plugins directory:

   ```bash
   cd /path/to/mautic/plugins/
   git clone https://github.com/fabiodemelo/mautic.git MauticSyncDataBundle
   ```

2. **Clear the Mautic cache and install the plugin:**

   ```bash
   cd /path/to/mautic/
   php bin/console cache:clear
   php bin/console mautic:plugins:reload
   ```

   > **Important:** Always run `cache:clear` and `mautic:plugins:reload` from the **Mautic root directory** (where `bin/console` lives), not from inside the plugins folder.

3. **Verify** the plugin appears in **Settings > Plugins > SyncData**.

### Option 2: Composer (when published to Packagist)

```bash
cd /path/to/mautic/
composer require mauticplugin/syncdata-bundle
php bin/console cache:clear
php bin/console mautic:plugins:reload
```

---

## Configuration

### 1. Add Your SyncData API Key

1. Navigate to **SyncData > Settings** in the Mautic admin sidebar
2. Enter your SyncData API Key (requires `Suppressions Read` scope)
3. Click **Test Connection** to verify
4. Click **Save Settings**

> **How to create a SyncData API key:**
> Go to [SendGrid > Settings > API Keys](https://app.sendgrid.com/settings/api_keys) → Create API Key → Select "Restricted Access" → Enable **Suppressions > Read** → Create & Copy.

### 2. Configure Suppression Types

For each of the 6 suppression types, you can:

- **Enable/disable** the type for syncing
- **Choose action mode**:
  - **Add to DNC** (default) — Adds the contact to Mautic's Do Not Contact list with the appropriate reason
  - **Add to Segment** — Adds the contact to a specified Mautic segment for review before taking action

| Suppression Type | Default DNC Reason | Use Case |
|-----------------|-------------------|----------|
| Bounce | Bounced | Hard/soft bounces from the provider |
| Spam Report | Unsubscribed | Recipients who reported spam |
| Block | Manual | Temporarily blocked sends |
| Invalid Email | Bounced | Invalid email addresses |
| Global Unsubscribe | Unsubscribed | Globally unsubscribed recipients |
| Group Unsubscribe | Unsubscribed | Unsubscribed from specific groups |

### 3. Set Sync Schedule

| Setting | Options | Default |
|---------|---------|---------|
| Sync Interval | 5min, 15min, 30min, 1hr, 6hr, 12hr, 24hr | 15 minutes |
| Initial Sync Range | 7 days, 30 days, 90 days, All time | 30 days |
| Notification Email | Any valid email | (empty) |
| Spike Threshold | Any positive integer | 50 |

### 4. Set Up the Cron Job

Add this to your server's crontab:

```bash
# Run every 15 minutes (match your configured interval)
*/15 * * * * php /path/to/mautic/bin/console mautic:syncdata:sync --type=incremental

# Or for a full re-sync (e.g., once per week)
0 2 * * 0 php /path/to/mautic/bin/console mautic:syncdata:sync --type=full
```

---

## Usage

### Dashboard

Navigate to **SyncData > Dashboard** to see:

- **Summary cards**: Total synced, new suppressions (24h/7d/30d), contacts protected, last sync status
- **Breakdown chart**: Donut chart showing suppression distribution by type
- **Trend chart**: Line chart showing suppressions over time (filterable by period and type)
- **Suppressions table**: Searchable, filterable, paginated table of all synced suppressions
- **Sync history**: Log of all sync runs with status, duration, and record counts

### Manual Sync

Click **Run Sync Now** on the dashboard to trigger an immediate sync.

### CSV Export

Click **Export CSV** on the dashboard to download all suppressions (respects active filters).

### Console Commands

```bash
# Incremental sync (default — only new since last sync)
php bin/console mautic:syncdata:sync

# Full sync (re-sync everything within configured range)
php bin/console mautic:syncdata:sync --type=full

# Sync only bounces
php bin/console mautic:syncdata:sync --suppression=bounce

# Dry run (preview without changes)
php bin/console mautic:syncdata:sync --dry-run

# Combine options
php bin/console mautic:syncdata:sync --type=full --suppression=spam_report --dry-run
```

**Suppression type values for `--suppression`:**
`bounce`, `spam_report`, `block`, `invalid_email`, `global_unsubscribe`, `group_unsubscribe`

---

## Database Tables

The plugin creates two tables:

| Table | Purpose |
|-------|---------|
| `plugin_syncdata_log` | Sync run history (timestamp, status, counts, errors) |
| `plugin_syncdata_suppressions` | Cache of all synced suppressions (email, type, reason, action taken) |

Tables are created automatically via Doctrine migrations when the plugin is installed.

---

## Permissions

The plugin uses Mautic's role-based permission system:

| Permission | Access |
|-----------|--------|
| `syncdata:dashboard:view` | View the dashboard (read-only) |
| `syncdata:settings:manage` | Change settings, trigger manual sync, export data |

Configure in **Settings > Roles**.

---

## Architecture

```
plugins/MauticSyncDataBundle/
├── Assets/css/js/              # Dashboard styles and Chart.js interactions
├── Command/                    # mautic:syncdata:sync console command
├── Config/config.php           # Routes, services, menu registration
├── Controller/                 # Dashboard, Settings, Sync controllers
├── Entity/                     # SyncLog + Suppression entities & repositories
├── Form/Type/                  # Config form types (auth + features)
├── Integration/                # IntegrationsBundle integration class
├── Migrations/                 # Database schema migrations
├── Security/Permissions/       # Role-based permissions
├── Service/                    # Core services (API client, sync engine, etc.)
├── Tests/Unit/                 # PHPUnit tests
├── Translations/en_US/         # English translations
└── Views/                      # Twig templates (dashboard + settings)
```

### Service Dependency Graph

```
SyncCommand → SyncEngine
                ├── SuppressionFetcher → SyncDataApiClient
                ├── ContactResolver → LeadModel
                ├── DncMapper
                ├── DoNotContactModel (Mautic)
                ├── SegmentModel (Mautic)
                └── NotificationService → MailHelper
```

---

## Troubleshooting

### "SyncData API key is not configured"
Go to **SyncData > Settings**, enter your API key, and click **Save**.

### "Connection failed: 403 Forbidden"
Your API key doesn't have the required permissions. Create a new key with **Suppressions Read** access.

### Sync runs but finds 0 records
- Check your **Initial Sync Range** — if set to 7 days and no suppressions occurred in the last 7 days, nothing will sync
- Verify the suppression types are **enabled** in Settings
- Try a **full sync**: `php bin/console mautic:syncdata:sync --type=full`

### High memory usage during sync
The plugin processes in batches of 100 and clears Doctrine's identity map between batches. If you have >100k suppressions, consider running the initial full sync during off-peak hours.

---

## Updating

To update the plugin to the latest version:

```bash
cd /path/to/mautic/plugins/MauticSyncDataBundle/
git pull
cd /path/to/mautic/
php bin/console cache:clear
php bin/console mautic:plugins:reload
```

---

## Uninstallation

1. Disable the plugin in **Settings > Plugins**
2. Remove the plugin directory and clear cache:
   ```bash
   cd /path/to/mautic/
   rm -rf plugins/MauticSyncDataBundle/
   php bin/console cache:clear
   ```
4. The database tables (`plugin_syncdata_log`, `plugin_syncdata_suppressions`) will remain. To remove them manually:
   ```sql
   DROP TABLE IF EXISTS plugin_syncdata_suppressions;
   DROP TABLE IF EXISTS plugin_syncdata_log;
   ```

---

## License

GPL-3.0-or-later

---

## Author

Fabio de Melo

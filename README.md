# WhatConverts API Metrics

WordPress plugin to display lead metrics from the WhatConverts API.

## Installation

1. Create a zip of the plugin:
   ```bash
   make zip
   ```

2. In WordPress admin: **Plugins → Add New → Upload Plugin**

3. Upload the zip and activate

4. Go to **Settings → WhatConverts API** and enter your API credentials

## Configuration

Get your API credentials from WhatConverts:
**Tracking → Integrations → API Keys**

Use a **Master Account** API key if you need to query multiple sub-accounts.

## Shortcodes

### Basic Usage (all accounts)

```
[wc_qualified_leads]
[wc_closed_leads]
[wc_annual_value]
[wc_total_leads]
[wc_last_updated]
```

### Per-Account Usage

```
[wc_qualified_leads account_id="102204"]
[wc_closed_leads account_id="102204"]
[wc_annual_value account_id="102204"]
```

Find the `account_id` in WhatConverts under **Accounts → Account ID** column.

## Using with Elementor

1. Add a **Shortcode** widget
2. Enter the shortcode: `[wc_qualified_leads account_id="102204"]`
3. Preview/publish the page

## Caching

Data is cached for 1 hour per account. Clear cache at **Settings → WhatConverts → Clear Cache**.

## Development

Requires PHP 8.0+

```bash
# Install dependencies
composer install

# Run tests
make test

# Create deployment zip
make zip
```

## Lead Status Mapping

| Metric | Counts statuses |
|--------|-----------------|
| Qualified Leads | `qualified`, `quotable` |
| Closed Leads | `closed`, `converted` |
| Annual Value | Sum of `sales_value` (or `quote_value`) for closed leads |

## Updating the Plugin

1. Bump version in `whatconverts-metrics.php`
2. Run `make test`
3. Run `make zip`
4. In WordPress: Deactivate → Delete → Upload new zip → Activate

Settings persist across updates.

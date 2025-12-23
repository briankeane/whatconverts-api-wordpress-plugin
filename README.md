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
[wc_annual_sales_value]
[wc_annual_quote_value]
[wc_total_leads]
[wc_last_updated]
```

### Per-Account Usage

```
[wc_qualified_leads account_id="102204"]
[wc_closed_leads account_id="102204"]
[wc_annual_sales_value account_id="102204"]
[wc_annual_quote_value account_id="102204"]
```

Find the `account_id` in WhatConverts under **Accounts → Account ID** column.

### Date Range

By default, all shortcodes show **all-time** data. Use the `months` parameter to filter:

```
[wc_annual_quote_value]                 → all time (default)
[wc_annual_quote_value months="1"]      → last 1 month
[wc_annual_quote_value months="3"]      → last 3 months
[wc_annual_quote_value months="6"]      → last 6 months
[wc_annual_quote_value months="12"]     → last 12 months
```

Combine with account_id:
```
[wc_annual_quote_value account_id="102204" months="3"]
```

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

## Metric Definitions

| Shortcode | What it shows |
|-----------|---------------|
| `wc_qualified_leads` | Count of leads where `quotable` = "Yes" |
| `wc_closed_leads` | Count of leads with `sales_value` > 0 |
| `wc_annual_sales_value` | Sum of all `sales_value` (closed revenue) |
| `wc_annual_quote_value` | Sum of all `quote_value` (potential revenue) |
| `wc_total_leads` | Total lead count (last 12 months) |

## Updating the Plugin

1. Bump version in `whatconverts-metrics.php`
2. Run `make test`
3. Run `make zip`
4. In WordPress: Deactivate → Delete → Upload new zip → Activate

Settings persist across updates.

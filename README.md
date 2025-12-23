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
[wc_sales_value]
[wc_quote_value]
[wc_total_leads]
[wc_last_updated]
```

### Per-Account Usage

```
[wc_qualified_leads account_id="102204"]
[wc_closed_leads account_id="102204"]
[wc_sales_value account_id="102204"]
[wc_quote_value account_id="102204"]
```

Find the `account_id` in WhatConverts under **Accounts → Account ID** column.

### Date Range

By default, all shortcodes show the **last 12 months** of data. Use the `months` parameter to adjust:

```
[wc_quote_value]                 → last 12 months (default)
[wc_quote_value months="3"]      → last 3 months
[wc_quote_value months="6"]      → last 6 months
[wc_quote_value months="all"]    → all time (fetched in yearly chunks)
```

Combine with account_id:
```
[wc_quote_value account_id="102204" months="3"]
```

## Using with Elementor

1. Add a **Shortcode** widget
2. Enter the shortcode: `[wc_qualified_leads account_id="102204"]`
3. Preview/publish the page

## Caching

- Data is cached per account and date range. Default TTL is 60 minutes and can be configured under **Settings → WhatConverts API → Cache Length (minutes)** (minimum 5 minutes).
- A cron job prewarms the cache hourly (`wcm_prewarm_cache`), so front-end/admin requests should not need to fetch live data. It prewarms existing cache keys and any targets returned by the `wcm_prewarm_targets` filter.
- Clear cache at **Settings → WhatConverts API → Clear Cache**.

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
| `wc_sales_value` | Sum of all `sales_value` (closed revenue) |
| `wc_quote_value` | Sum of all `quote_value` (potential revenue) |
| `wc_total_leads` | Total lead count |

## Debugging

### Debug Attribute

Add `debug="true"` to any shortcode to log debug info to the browser console (admins only):

```
[wc_sales_value account_id="102204" debug="true"]
```

Open browser DevTools (F12) → Console to see:
- Cache hit/miss status
- Number of API requests made
- All calculated metrics
- Account ID and date range used

### Debug Shortcode

Use `[wc_debug]` for a full debug summary:

```
[wc_debug account_id="102204"]
```

Shows:
- API request count
- All calculated metrics with explanations
- How to verify the numbers in WhatConverts UI

### Server Logging

When `WP_DEBUG` is enabled, API requests are logged to `wp-content/debug.log`:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## API Limits

- Max 25 API requests per page load (failsafe)
- 2500 leads fetched per request (API maximum)
- Rate limit retries with exponential backoff

## Updating the Plugin

1. Bump version in `whatconverts-metrics.php`
2. Run `make test`
3. Run `make zip`
4. In WordPress: Deactivate → Delete → Upload new zip → Activate

Settings persist across updates.

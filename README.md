# Redirection Params & Logging Enhancer

An extension for the [Redirection plugin](https://redirection.me/) that automatically preserves query parameters from incoming requests during redirects and ensures the complete target URL (including parameters) is logged.

## Overview

This plugin enhances the Redirection plugin by:

1. **Parameter Preservation**: Automatically merges query parameters from the incoming request URL into the target URL when performing redirects
2. **Complete Logging**: Ensures that the full redirect target URL—including all query parameters—is recorded in the Redirection logs

This is particularly useful when you need to track visitor parameters (such as UTM parameters, affiliate codes, or custom tracking IDs) through your redirect chain while maintaining clean redirect rules.

## Requirements

- **WordPress 5.0+** or higher
- **Redirection plugin** (required dependency)

## Features

### Intelligent Parameter Merging

The plugin intelligently merges query parameters without overwriting existing target parameters:
- Parameters from the source URL are added to the target URL
- Target URL parameters always take precedence (are not overwritten)
- Handles array-style parameters correctly
- Preserves URL fragments (anchors)

### Automatic Request Parameter Detection

The plugin automatically detects and uses the incoming request URL to extract parameters:
- Uses the Redirection plugin's `Redirection_Request` class when available
- Falls back to `$_SERVER['REQUEST_URI']` as a fallback
- Properly sanitizes all input

### Enhanced Logging

All redirects are logged with the complete target URL including merged parameters, providing accurate traffic tracking and analytics.

## How It Works

### Query Parameter Merging Algorithm

The `rparams_merge_queries()` function:

1. Parses both the target and source URLs into components
2. Extracts query parameters from both URLs
3. Merges parameters from the source URL into the target, but **only if the target doesn't already have that parameter**
4. Reconstructs the full URL with the merged query string
5. Handles relative URLs gracefully

**Example:**

```
Target URL: https://example.com/page?campaign=spring
Source URL: https://old.example.com/signup?utm_source=google&campaign=fall

Result: https://example.com/page?campaign=spring&utm_source=google
```

Notice how `campaign=spring` from the target is preserved, while `utm_source=google` from the source is added.

### Integration Points

The plugin hooks into two key filters provided by the Redirection plugin:

1. **`redirection_url_target`** - Merges parameters into the target URL before the redirect occurs
2. **`redirection_log_data`** - Ensures the complete URL with parameters is logged

## Installation

1. Download or clone this plugin into your WordPress `wp-content/plugins/` directory
2. Ensure the Redirection plugin is installed and activated
3. Activate the "Redirection Params & Logging Enhancer" plugin from the WordPress admin

## Admin Notices

If the Redirection plugin is not active, administrators will see a warning notice in the WordPress admin prompting them to activate or install it.

## Version

**Version:** 0.1.0

## Author

Created by Copilot with direction from ChangeMakers Digital

## Plugin URI

https://cmtrk.ca/

# Review Helper for WooCommerce

![Version](https://img.shields.io/badge/version-1.1.0-2563eb)
![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759b)
![WooCommerce](https://img.shields.io/badge/WooCommerce-7.0%2B-7f54b3)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-16a34a)

A polished admin toolkit for generating, scheduling, and managing sample WooCommerce product reviews from one dashboard.

Review Helper for WooCommerce is built for development, staging, QA, and demo stores where you need realistic review data quickly without turning review setup into a manual chore.

## Highlights

- Generate reviews from the single product edit screen.
- Run bulk review generation across all products or selected product IDs.
- Schedule drip-style review generation with WP-Cron instead of posting everything at once.
- Manage reviews from one interface with filtering, editing, approving, deleting, and bulk removal.
- Save defaults for names, comments, ratings, content style, approval status, and date range.
- Import and export plugin settings for faster setup across multiple sites.
- Use schedule templates, recurring jobs, history tracking, pause or resume controls, and manual run tools.

## Feature Snapshot

| Area | Included |
| --- | --- |
| Dashboard | Overview cards, last generation stats, quick actions |
| Single Product | Generate reviews directly inside the WooCommerce product editor |
| Bulk Generate | All products or specific IDs, skip generated products, undo last bulk batch |
| Schedule Generation | One-time or recurring jobs, review spacing, pause/resume, run now, history |
| Review Manager | Filter by product/rating/source, edit, approve/unapprove, delete, bulk remove |
| Settings | Reviewer names, custom comments, rating defaults, review status, date range, exclusions |
| Portability | Settings import/export and schedule template tools |
| Cleanup | Optional uninstall cleanup for plugin data |
| i18n | Translation-ready with `.pot` file included |

## Scheduling Tools

The Schedule Generation area is designed for stores that want reviews to appear gradually over time instead of all in one burst.

- One-time or recurring schedules
- Daily, weekly, and custom-day recurrences
- Minutes-between-reviews spacing
- Pause and resume controls
- Manual "run now" trigger
- Schedule history log
- Schedule templates
- Schedule import/export support
- Timezone-aware admin display

Note: scheduled jobs use WordPress WP-Cron, so exact execution time can depend on normal site traffic or cron triggering.

## Review Controls

- Approved or pending review generation mode
- Configurable date range for generated review dates
- Exclude specific product IDs
- Exclude specific category IDs
- Custom reviewer name pool
- Custom comment pool with `{product_name}` placeholder support
- Short, medium, and long content style options
- Generated-review tracking for safer cleanup and filtering

## Who This Is For

- WooCommerce developers building demo stores
- agencies preparing client previews
- QA or staging teams that need realistic product feedback data
- site owners who want a faster way to populate non-production catalog reviews

## Requirements

- WordPress 5.8 or higher
- WooCommerce 7.0 or higher
- PHP 7.4 or higher

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate **Review Helper for WooCommerce** from the WordPress plugins screen.
3. Make sure WooCommerce is active.
4. Open **Review Helper** from the WordPress admin menu.

## Quick Start

1. Open **Review Helper > Settings** and set your defaults.
2. Add reviewer names and optional custom comment lines.
3. Choose whether reviews should be approved immediately or saved as pending.
4. Generate reviews from a single product or use the bulk tools.
5. Use **Schedule Generation** if you want reviews to appear over time.
6. Open **Manage Reviews** to filter, edit, approve, or remove generated reviews.

## Data Notes

- The plugin uses standard WordPress comments for WooCommerce product reviews.
- Plugin settings are stored in the WordPress options table.
- A generated-review marker is stored so plugin-created reviews can be filtered and cleaned up more safely.
- No custom database tables are required.

## Included Files

```text
review-helper-for-woocommerce/
|-- admin/
|-- assets/
|-- build/
|-- includes/
|-- languages/
|-- index.php
|-- readme.txt
|-- review-helper-for-woocommerce.php
`-- uninstall.php
```

## Changelog

### 1.1.0

- Added settings import and export tools
- Added approved and pending review generation modes
- Added configurable review date range controls
- Added bulk exclusion rules for selected products and categories
- Improved generated review handling and admin workflow

### 1.0.0

- Initial release

## License

Licensed under the GPL-2.0-or-later license.

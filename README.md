# Aircraft Builders Log

This plugin allows you to track time spent on an aircraft build project - or really *anything* - which makes WordPress a more suitable tool to use for your builders log. You assign an amount of time to each post, and the times can be aggregated by category and displayed on your site.

## Version 2.0.0 - Updated for Modern WordPress

This version has been updated to work with the latest versions of WordPress (5.0 and above). Updates include:

- Gutenberg block editor compatibility
- Improved security with better data sanitization and validation
- Updated code to use modern WordPress standards
- Fixed deprecated jQuery methods
- Added text domain for improved internationalization support

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher

## Setup Instructions

1. Install this plugin
2. Activate the plugin, if you didn't already
3. Show times on your site using one of these methods:
   - **Widget**: Go to the "Appearance" menu and choose "Widgets". Find and add the "Aircraft Build Time" widget
   - **Block Editor**: Use the Aircraft Builders Log block (if using Gutenberg editor)

## Usage

1. Add or edit a post, and look for the "Airplane Section" part of the form. Add a new one such as Fuselage, Empennage, Engine, etc.
2. Enter the time spent on this part of the build. You can use hours, minutes, or both (e.g. 1 hr + 30 minutes is the same as 90 minutes).
3. Optionally, drag the "Airplane Section" or "Time Spent" boxes to your preferred place on the Posts edit page
4. Save your post, and your times have been recorded.

## For Developers

If you need to customize the plugin functionality, you can hook into the following filters and actions:

- Various WordPress standard hooks for widgets and taxonomies
- Term metadata for storing build times

## Changelog

### 2.0.0 (2025-06-05)
- Updated for WordPress 6.4+ compatibility
- Added Gutenberg block editor support
- Fixed deprecated jQuery code (replaced .live() method with .on() method)
- Improved AJAX security with better nonces and validation
- Added proper text domain for translations
- Enhanced code structure and maintainability

### 20161117 (Original)
- Initial release

## Credits

- Original Author: Mark A. Stratman
- Plugin URI: http://zenith.stratman.pw/builders-log-wp-plugin/

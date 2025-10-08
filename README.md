# wp-optimal-state
=== WP Optimal State ===
Developer: The Spiritual Seek - https://spiritualseek.com/wp-optimal-state-wordpress-plugin/
Tags: optimization, database, performance, speed, maintenance, cleanup, optimize
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.0.7
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Advanced WordPress optimization and cleaning plugin. Clean database, optimize tables, remove unused data, and keep your site lightning fast.


== Description ==

WP Optimal State is a comprehensive database optimization and maintenance plugin designed to keep your WordPress website running at peak performance. Over time, WordPress databases accumulate unnecessary data such as post revisions, spam comments, orphaned metadata, and expired transients. This bloat can slow down your site and increase hosting costs.

WP Optimal State provides an intuitive interface to safely identify, review, and remove this unnecessary data while optimizing your database tables for maximum efficiency. Version 1.0.5 introduces an advanced automated cleaning scheduler, enabling you to set up fully automated database maintenance with customizable intervals. The update also features a more reliable database restoration process with enhanced error handling and an improved, clearer user interface for better user experience.

= Key Features =

* **Integrated Backup Manager*: Create, manage, restore, and delete database backups (max 10 retained)
* **Enhanced Table Repair*: Improved analysis script with detailed error reporting
* **One-Click Optimization**: Perform all safe optimizations with a single click
* **Automated Cleaning Scheduler**: Set up fully automated maintenance that runs on your preferred schedule
* **Database Statistics**: Get detailed insights into your database size and contents
* **Smart Cleanup**: Remove post revisions, auto-drafts, spam comments, trashed items, and more
* **Autoload Optimization**: Identify and optimize large autoloaded options
* **Safe Operations**: Clear warnings for potentially unsafe operations
* **Modern Interface**: Beautiful, intuitive design with smooth animations
* **Toast Notifications**: Real-time feedback for all operations
* **Custom Modals**: Professional confirmation dialogs instead of browser alerts
* **Backup Reminders**: Always reminded to backup before cleanup operations

= Why Use WP Optimal State? =

* **Improve Site Speed**: Reduce database bloat and improve query performance
* **Save Storage Space**: Remove unnecessary data accumulating in your database
* **Easy to Use**: Clean, intuitive interface with clear statistics and controls
* **Safe & Reliable**: Built with safety in mind - always warns before risky operations
* **Regular Maintenance**: Keep your database in optimal state with scheduled cleanups


== Installation ==

1. Upload the `wp-optimal-state` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **Optimal State** in the admin menu to start optimizing

= Automatic Installation =

1. Go to **Plugins > Add New** in your WordPress admin
2. Search for "WP Optimal State"
3. Click "Install Now" and then "Activate"


== Frequently Asked Questions ==

= Is this plugin safe to use? =
Yes! WP Optimal State includes clear warnings for any operations that might affect your content. Always backup your database before performing cleanup operations.

= What does the plugin clean? =
* Post revisions and auto-drafts
* Spam and trashed comments
* Orphaned post metadata and comment metadata
* Orphaned user metadata
* Expired and all transients
* Duplicate metadata entries
* Orphaned term relationships
* Pingbacks and trackbacks
* Unapproved comments

= Will this affect my posts and pages? =
The plugin only removes unnecessary data like revisions and auto-drafts. Your published content remains completely safe.

= How often should I run optimizations? =
For most sites, running optimizations once a month is sufficient. High-traffic sites might benefit from weekly optimizations.

= Can I schedule automatic cleanups? =
Yes! The plugin supports scheduled cleanups for routine maintenance.

= How do I restore a backup? =
Select the backup in the management section and confirm the restore action; the current database will be overwritten.

= What's new in the interface? =
Version 1.0.5 features a completely redesigned interface with modern gradients, smooth animations, toast notifications for real-time feedback, and custom modal dialogs instead of browser alerts.


== Changelog ==

= 1.0.7 (Current) =
- New Setting: Customizable backup limits
- Improved optimization history with additional information
- Transient expiration logic fixed for reliable rate limiting
- Added the database creation date to the statistics section

= 1.0.6 =
- Fixed SQL injection vulnerabilities in table operations
- Enhanced file download security with protection headers
- Improved memory management for large operations
- Better batch processing for large backups
- Automatic backup directory protection (.htaccess + index.php)
- Better error handling for rate-limited operations

= 1.0.6 (Current) =
- Fixed SQL injection vulnerabilities in table operations
- Enhanced file download security with protection headers
- Improved memory management for large operations
- Better batch processing for large backups
- Automatic backup directory protection (.htaccess + index.php)
- Better error handling for rate-limited operations

= 1.0.5 =
- Added advanced automated cleaning scheduler with customizable intervals
- Implemented more reliable database restoration process with enhanced error handling
- Completely redesigned user interface for better clarity and usability
- Added automatic backup creation before scheduled cleanups
- Enhanced activity logging for automated operations
- Improved status indicators and monitoring

= 1.0.4 =
* **Integrated Backup Manager**: Added full database backup functionality for creation, restoration, download, and deletion
* **Enhanced Table Repair**: Improved analysis and repair script with better error handling and user confirmations
* **User Feedback Improvements**: Added custom modals and toast notifications for operations
* **Security and Compatibility**: Updated for WordPress 6.9; minor bug fixes

= 1.0.3 =
* **Major UI/UX Overhaul**: Complete redesign with modern, polished interface
* **Enhanced Security**: Comprehensive security improvements including:
  - Proper nonce verification on all AJAX handlers
  - Enhanced capability checks with proper error messages
  - Input sanitization using WordPress functions (sanitize_text_field, absint)
  - Output escaping (esc_html, esc_attr, esc_url, wp_json_encode)
  - Prepared SQL statements with $wpdb->prepare()
  - Whitelist validation for item types
  - Protection against SQL injection
* **Code Efficiency**: 
  - Centralized AJAX handler registration
  - Proper error handling with try-catch blocks
  - Settings sanitization callbacks
  - Reduced code duplication
  - Better function organization
  - Text domain loading for internationalization
* **Visual Enhancements**:
  - Modern gradient backgrounds and buttons
  - Smooth hover effects and transitions
  - Custom toast notification system
  - Professional modal dialogs (no more browser alerts!)
  - Loading spinner animations
  - Card hover effects with elevation
  - Better color contrast and typography
  - Enhanced responsive design
* **User Experience**:
  - Toast notifications for all actions
  - Custom confirmation modals with detailed information
  - Better error messages and feedback
  - Improved loading states
  - Success animations
  - Fade-in effects for dynamic content
  - Processing lock to prevent double-clicks
  - Warning icons for unsafe operations
* **Accessibility**:
  - ARIA-compliant focus outlines
  - High contrast mode support
  - Reduced motion support for users with vestibular disorders
  - Semantic HTML structure
  - Keyboard navigation improvements
* **Additional Features**:
  - Print-friendly styles
  - Enhanced mobile-responsive layout
  - Better data validation
  - Improved uninstall script with multisite support
  - Debug mode logging
* **Bug Fixes**:
  - Fixed edge cases in duplicate detection
  - Improved error handling for failed database operations
  - Better handling of empty results

= 1.0.2 =
* Improved security with enhanced nonce verification
* Better error handling for database operations
* Updated translation files
* Fixed minor CSS issues

= 1.0.1 =
* Added scheduled cleanup functionality
* Improved database size calculation
* Enhanced mobile responsiveness
* Fixed duplicate metadata detection

= 1.0.0 =
* Initial release
* Core optimization features
* Database statistics
* One-click optimization


== Upgrade Notice ==

= 1.0.7 =
Minor update including bug fixes and some new additional features (customizable backup limits and richer logs).

= 1.0.6 =
This update includes several bug fixes and improvements to security and efficiency. It does not introduce any new features.

= 1.0.5 =
This release implements a powerful automated backup and cleanup feature running at custom intervals. For ultimate reliability, every cleaning operation is preceded by a full database backup.

= 1.0.4 =
This update introduces integrated database backups and improved table repair capabilities. Backup your site before upgrading. Review new features in the dashboard.

= 1.0.3 =
Major update with complete UI redesign, enterprise-level security enhancements, and improved user experience. Highly recommended for all users. Features modern interface with toast notifications, custom modals, and smooth animations.

= 1.0.2 =
Security release - recommended for all users. Includes enhanced protection and bug fixes.

= 1.0.1 =
Adds scheduled cleanup feature and improved mobile experience.


== Support ==

For support, feature requests, or bug reports, please visit: https://spiritualseek.com/wp-optimal-state-wordpress-plugin/

Email: info@spiritualseek.com


== Privacy Policy ==

WP Optimal State does not collect any user data or transmit any information to external servers. All operations are performed locally on your WordPress installation.


== Technical Details ==

= Minimum Requirements =
* WordPress 5.0 or higher
* PHP 7.4 or higher
* MySQL 5.6 or higher

= Recommended Environment =
* WordPress 6.0 or higher
* PHP 8.0 or higher
* MySQL 8.0 or MariaDB 10.3

= Security Features =
* Nonce verification on all AJAX requests
* Capability checks (manage_options required)
* SQL injection protection via prepared statements
* XSS protection via output escaping
* CSRF protection
* Input validation and sanitization


== Credits ==

Developed by Luke Garrison at The Spiritual Seek for the WordPress community.
Special thanks to all our beta testers and contributors who helped make this plugin better.


== Developer Notes ==

= Code Quality =
* Follows WordPress Coding Standards
* PSR-4 autoloading compatible structure
* Comprehensive inline documentation
* Modular and extensible architecture

= Hooks & Filters =
* `wp_opt_state_before_cleanup_complete` - Action hook before cleanup completes
* `wp_opt_state_after_uninstall` - Action hook after plugin uninstallation

= Browser Compatibility =
* Chrome 90+
* Firefox 88+
* Safari 14+
* Edge 90+
* Opera 76+

= Performance =
* Lightweight footprint (~150KB total)
* Only loads on admin pages where needed
* Optimized database queries
* Efficient AJAX operations


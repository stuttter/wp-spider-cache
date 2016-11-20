=== WP Spider Cache ===
Contributors: johnjamesjacoby, stuttter
Tags: cache, object, output, admin, memcache, memcached
Requires at least: 4.7
Tested up to: 4.7
Stable tag: 4.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=9Q4F4EL5YJ62J

== Description ==

WP Spider Cache is your friendly neighborhood caching solution for WordPress. It uses Memcached to store both objects & page output.

It's heroic like Batcache & Super Cache, but younger, humbler, and a born web-slinger.

It comes with:

* Support for registering multiple cache servers
* An administration interface for viewing cache data
* Ability to flush specific keys & groups
* Drop-in plugins for `object-cache.php` & `advanced-cache.php`

= Also checkout =

Admin:

* [WP Admin Menu Plus](https://wordpress.org/plugins/wp-admin-menu-plus/ "Additional styling for WordPress administration menus.")
* [WP Chosen](https://wordpress.org/plugins/wp-chosen/ "Make long, unwieldy select boxes much more user-friendly.")
* [WP Comment Humility](https://wordpress.org/plugins/wp-comment-humility/ "Move the "Comments" menu underneath 'Posts'.")
* [WP Pretty Filters](https://wordpress.org/plugins/wp-pretty-filters/ "Make all filters match the Media & Attachments interface.")
* [WP Reset Filters](https://wordpress.org/plugins/wp-reset-filters/ "Adds a "Reset" button to all admin area filters.")

Events:

* [WP Event Calendar](https://wordpress.org/plugins/wp-event-calendar/ "The best way to manage events in WordPress.")
* [WP Event Venues](https://wordpress.org/plugins/wp-event-venues/ "Add reusable venues to WP Event Calendar.")

Media:

* [WP Media Categories](https://wordpress.org/plugins/wp-media-categories/ "Add categories to media & attachments.")

Multisite:

* [WP Blog Meta](https://wordpress.org/plugins/wp-blog-meta/ "A global, joinable meta-data table for WordPress Multisite.")
* [WP Multi Network](https://wordpress.org/plugins/wp-multi-network/ "Create many networks of many sites with any domains.")
* [WP Site Aliases](https://wordpress.org/plugins/wp-site-aliases/ "Create many networks of many sites with any domains.")

Posts:

* [WP Article Order](https://wordpress.org/plugins/wp-article-order/ "Move articles to the end of post titles.")

System:

* [LudicrousDB](https://wordpress.org/plugins/ludicrousdb/ "Minifies & concatenates enqueued scripts & styles.")
* [WP Enqueue Masher](https://wordpress.org/plugins/wp-enqueue-masher/ "Minifies & concatenates enqueued scripts & styles.")
* [WP Spider Cache](https://wordpress.org/plugins/wp-spider-cache/ "Your friendly neighborhood caching solution for WordPress.")

Terms:

* [WP Term Authors](https://wordpress.org/plugins/wp-term-authors/ "Authors for categories, tags, and other taxonomy terms.")
* [WP Term Colors](https://wordpress.org/plugins/wp-term-colors/ "Pretty colors for categories, tags, and other taxonomy terms.")
* [WP Term Families](https://wordpress.org/plugins/wp-term-families/ "Associate taxonomy terms with other taxonomy terms.")
* [WP Term Icons](https://wordpress.org/plugins/wp-term-icons/ "Pretty icons for categories, tags, and other taxonomy terms.")
* [WP Term Images](https://wordpress.org/plugins/wp-term-images/ "Pretty images for categories, tags, and other taxonomy terms.")
* [WP Term Locks](https://wordpress.org/plugins/wp-term-locks/ "Protect categories, tags, and other taxonomy terms from being edited or deleted.")
* [WP Term Order](https://wordpress.org/plugins/wp-term-order/ "Sort taxonomy terms, your way.")
* [WP Term Visibility](https://wordpress.org/plugins/wp-term-visibility/ "Visibilities for categories, tags, and other taxonomy terms.")

Users:

* [WP User Activity](https://wordpress.org/plugins/wp-user-activity/ "The best way to log activity in WordPress.")
* [WP User Alerts](https://wordpress.org/plugins/wp-user-alerts/ "Send alerts to users when new posts are published.")
* [WP User Avatars](https://wordpress.org/plugins/wp-user-avatars/ "Allow users to upload avatars or choose them from your media library.")
* [WP User Groups](https://wordpress.org/plugins/wp-user-groups/ "Group users together with taxonomies & terms.")
* [WP User Parents](https://wordpress.org/plugins/wp-user-parents/ "A user hierarchy for WordPress user accounts.")
* [WP User Parents](https://wordpress.org/plugins/wp-user-preferences/ "Cascading user options with intelligent defaults.")
* [WP User Profiles](https://wordpress.org/plugins/wp-user-profiles/ "A sophisticated way to edit users in WordPress.")
* [WP User Signups](https://wordpress.org/plugins/wp-user-signups/ "An interface for managing multisite user signups.")
* [WP User Tagline](https://wordpress.org/plugins/wp-user-tagline/ "Allow users to give themselves unique taglines.")
* [WP User Title](https://wordpress.org/plugins/wp-user-title/ "Allow users to give themselves unique titles.")

= Credits =

This plugin is largely inspired by:

* Memcached
* Batcache
* Super Cache
* Johnny Cache

== Screenshots ==

1. Admin UI
2. Servers
3. Data View

== Installation ==

* Download and install using the built in WordPress plugin installer.
* Copy contents of `drop-ins` to your `wp-content` directory
* Activate in the "Plugins" area of your admin by clicking the "Activate" link.
* No further setup or configuration is necessary.

== Frequently Asked Questions ==

= Does this work with on single-site, multi-site, and multi-network installations? =

Yes. Yes. Yes.

= Does this work with BuddyPress, bbPress, and GlotPress? =

Yes. Yes. Yes.

= What other plugins has this been tested with? =

* EasyDigitalDownloads
* Jetpack
* Keyring
* Stuttter plugins
* User Switching
* WooCommerce

= Where can I get support? =

This plugin is free for anyone to use.

[Community support](https://wordpress.org/support/plugin/wp-spider-cache) is provided for free by existing users.
[Priority support](https://chat.flox.io/support/channels/wp-spider-cache) is available to paying customers & volunteer contributors.

If you require immediate assistance, please consider a paid support subscription.

= Where can I find documentation? =

http://github.com/stuttter/wp-spider-cache

== Changelog ==

= [4.1.0] - 2016-11-20 =
* General code improvements

= [4.0.0] - 2016-10-24 =
* Add support for global LudicrousDB cache group

= [3.4.0] - 2016-10-18 =
* Fix key & group deletion from UI
* Move BuddyPress cache-groups to root site of network

= [3.3.0] - 2016-10-07 =
* Prevent fatal errors on WordPress 4.7
* Bump minimum WordPress version to 4.7

= [3.2.0] - 2016-09-22 =
* Prevent fatal errors if packaged drop-ins are not used
* Prevent fatal errors if supported back-ends are not installed

= [3.1.0] - 2016-09-08 =
* Add extended global cache groups

= [3.0.3] - 2016-08-22 =
* Yield to XDebug if enabled
* Improve output of pretty var_dump

= [3.0.2] - 2016-08-22 =
* Use correct callback functions

= [3.0.1] - 2016-08-21 =
* Fix bug relating to Thickbox refresh

= [3.0.0] - 2016-08-21 =
* Improved cache view using Thickbox

= [2.2.1] - 2016-07-29 =
* Asset bump

= [2.2.0] - 2016-07-29 =
* Refactoring throughout
* Adding caps
* Cache exempt cookie

= [2.1.1] - 2016-02-15 =
* Sanity checks for Memcached & drop-ins

= [2.1.0] - 2016-02-15 =
* Refactor drop-ins
* More accurate debug times
* More protective method scopes
* Better output cache encapsulation
* Rename a few old functions

= [2.0.0] - 2016-02-15 =
* Initial release

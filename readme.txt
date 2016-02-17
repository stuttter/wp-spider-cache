=== WP Spider Cache ===
Contributors: johnjamesjacoby, stuttter
Tags: cache, object, output, admin, memcache, memcached
Requires at least: 4.4
Tested up to: 4.4
Stable tag: 2.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=9Q4F4EL5YJ62J

== Description ==

WP Spider Cache is your friendly neighborhood caching solution for WordPress. It uses Memcached to store both objects & page output.

If you are familiar with Batcache and WP Super Cache, you'll be right at home here.

It comes with:  

*   Support for registering multiple Memcached servers
*   An administration UI for viewing cache data
*   Ability to flush specific keys & groups
*   Drop-ins for `object-cache.php` and `advanced-cache.php`

= Also checkout =

* [WP Chosen](https://wordpress.org/plugins/wp-chosen/ "Make long, unwieldy select boxes much more user-friendly.")
* [WP Pretty Filters](https://wordpress.org/plugins/wp-pretty-filters/ "Makes post filters better match what's already in Media & Attachments.")
* [WP Event Calendar](https://wordpress.org/plugins/wp-event-calendar/ "The best way to manage events in WordPress.")
* [WP Media Categories](https://wordpress.org/plugins/wp-media-categories/ "Add categories to media & attachments.")
* [WP Term Meta](https://wordpress.org/plugins/wp-term-meta/ "Metadata, for taxonomy terms.")
* [WP Term Order](https://wordpress.org/plugins/wp-term-order/ "Sort taxonomy terms, your way.")
* [WP Term Authors](https://wordpress.org/plugins/wp-term-authors/ "Authors for categories, tags, and other taxonomy terms.")
* [WP Term Colors](https://wordpress.org/plugins/wp-term-colors/ "Pretty colors for categories, tags, and other taxonomy terms.")
* [WP Term Icons](https://wordpress.org/plugins/wp-term-icons/ "Pretty icons for categories, tags, and other taxonomy terms.")
* [WP Term Visibility](https://wordpress.org/plugins/wp-term-visibility/ "Visibilities for categories, tags, and other taxonomy terms.")
* [WP User Groups](https://wordpress.org/plugins/wp-user-groups/ "Group users together with taxonomies & terms.")
* [WP User Activity](https://wordpress.org/plugins/wp-user-activity/ "The best way to log activity in WordPress.")
* [WP User Avatars](https://wordpress.org/plugins/wp-user-avatars/ "Allow users to upload avatars or choose them from your media library.")

= Credits =

This plugin is largely inspired by:

*   Memcached
*   Batcache
*   Super Cache
*   Johnny Cache

== Screenshots ==

1. Admin UI
2. Servers
3. Data View

== Installation ==

* Download and install using the built in WordPress plugin installer.
* Optionally copy contents of `drop-ins` to your `wp-content` directory
* Optionally activate in the "Plugins" area of your admin by clicking the "Activate" link.
* No further setup or configuration is necessary.

== Frequently Asked Questions ==

= Does this work with on single-site, multi-site, and multi-network installations? =

Yes. Yes. Yes.

= Does this work with BuddyPress, bbPress, and GlotPress? =

Yes. Yes. Yes.

= What other plugins has this been tested with? =

* EasyDigitalDownloads
* WooCommerce
* Jetpack
* All Stuttter plugins
* Keyring

= Where can I get support? =

The WordPress support forums: https://wordpress.org/support/plugin/wp-spider-cache.

= Where can I find documentation? =

http://github.com/stuttter/wp-spider-cache

== Changelog ==

= 2.1.1 =
* Saniity checks for Memcached & drop-ins

= 2.1.0 =
* Refactor drop-ins
* More accurate debug times
* More protective method scopes
* Better output cache encapsulation
* Rename a few old functions

= 2.0.0 =
* Initial release

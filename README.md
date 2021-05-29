# WP Spider Cache

WP Spider Cache is your friendly neighborhood caching solution for WordPress. It uses Memcached to store both objects & page output.

If you are familiar with Batcache and WP Super Cache, you'll be right at home here.

It comes with:
* Support for registering multiple Memcached backend servers
* An administration interface for viewing cache data
* Ability to flush specific keys & groups
* Drop-in plugins for `object-cache.php` & `advanced-cache.php`

# Installation

* Download and install using the built in WordPress plugin installer.
* Copy contents of `drop-ins` to your `wp-content` directory
* Activate in the "Plugins" area of your `wp-admin` by clicking the "Activate" link.
* Consider sponsoring future development by clicking "Sponsor".
* No further setup or configuration is necessary.

# FAQ

### Does this work with on single-site, multi-site, and multi-network installations?

Yes. Yes. Yes.

### Does this work with BuddyPress, bbPress, and GlotPress?

Yes. Yes. Yes.

### What other plugins has this been tested with?

* EasyDigitalDownloads
* WooCommerce
* Jetpack
* All Stuttter plugins
* Keyring
* User Switching

### Credits

This plugin is largely inspired by:

* Memcached
* Batcache
* Super Cache
* Johnny Cache

### Where can I get support?

This plugin is free for anyone to use.

* Community: https://wordpress.org/support/plugin/wp-spider-cache
* Development: https://github.com/stuttter/wp-spider-cache/discussions

If you require immediate assistance, please consider a paid support subscription.

### Contributing

Please [open a new issue](/pull/new/master) to discuss whether the feature is a good fit for the project. Once you've decided to work on a pull request, please follow the [WordPress Coding Standards](http://make.wordpress.org/core/handbook/coding-standards/).

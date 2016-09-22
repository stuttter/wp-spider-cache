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

* [Community support](https://wordpress.org/support/plugin/wp-spider-cache) is provided for free by existing users.
* [Priority support](https://chat.flox.io/support/channels/wp-spider-cache) is available to paying customers & volunteer contributors.

If you require immediate assistance, please consider a paid support subscription.

### How can I help?

Please [open a new issue](/pull/new/master) to discuss whether the feature is a good fit for the project. Once you've decided to work on a pull request, please include [functional tests](https://wp-cli.org/docs/pull-requests/#functional-tests) and follow the [WordPress Coding Standards](http://make.wordpress.org/core/handbook/coding-standards/).

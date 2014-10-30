# === bSocial comments ===

Contributors: methnen, borkweb, camwyn, misterbisson

Tags: bSuite, bSocial, comments, featured comments, flags, faves, comment flagging, comment favoriting

Requires at least: 4.0

Tested up to: 4.0

Stable tag: trunk

Featured, faved, and flaged comments.

## == Description ==

1. This section needs development.
1. Adds featured comments selected by editors
1. Adds ability for users to flag comments
1. Adds ability for users to fave comments
1. Adds support for registering new comment types and statuses, similar to WP core's `register_post_type()`, see the section on registering custom comment types

### = History =

Featured comments were originally introduced in bSocial (<a href="https://wordpress.org/plugins/bsocial/">plugin repo</a>, <a href="https://github.com/misterbisson/bsocial">github</a>), but have moved here. New bSocial commenting features will be developed in this plugin.

### = In the WordPress.org plugin repo =

Eventually here: https://wordpress.org/plugins/bsocial-comments/

### = Fork me! =

This plugin is on Github: https://github.com/misterbisson/bsocial-comments

### = Build status =

[Master build status at Travis-CI](https://travis-ci.org/misterbisson/bsocial-comments): [![Build Status](https://travis-ci.org/misterbisson/bsocial-comments.svg?branch=master)](https://travis-ci.org/misterbisson/bsocial-comments)

## == Installation ==

1. Place the plugin folder in your `wp-content/plugins/` directory and activate it.
1. Use the default options, or filter `go_config` to return the options you want (when the second arg = `bsocial-comments`).
1. Have fun, feature some comments!

## == Screenshots ==

1. bSocial includes the JS SDKs you need to add social buttons for Twitter and Facebook, as seen on <a href="http://musictotakeyourclothesoffto.com/blog/lets-get-it-on/">Tease.FM</a>.
2. Easily add many Facebook widgets.
3. Incorporate conversations in Twitter and Facebook into your WordPress-native comment stream.
4. The <a href="http://developers.facebook.com/tools/debug/og/object?q=http%3A%2F%2Fgigaom.com%2Feurope%2Fstudents-force-facebook-to-cough-up-more-user-data%2F">Open Graph metadata</a> improves sharing and discoverability of your site.

## == Registering custom comment types ==

Custom comment types

```php
$args = array(
	'labels' => array(
		'name'          => 'Faves',
		'singular_name' => 'Fave',
		'edit_item'     => 'Edit Fave',
		'update_item'   => 'Update Fave',
		'view_item'     => 'View Fave',
		'all_items'     => 'All Faves',
	),
	'description'   => 'Comment faves',
	'public'        => TRUE,
	'show_ui'       => TRUE,
	'admin_actions' => array( 'trash', 'untrash', 'delete' ),
	'statuses'      => array(
		'feedback',
		'trash',
	),
);

bsocial_comments()->register()->comment_type( 'fave', $args );
```

Custom comment statuses

```php
$args = array(
	'label'             => 'Feedback',
	'label_count'       => _n_noop( 'Feedback <span class="count">(%s)</span>', 'Feedback <span class="count">(%s)</span>' ),
	'status_links_show' => TRUE,
	'include_in_all'    => FALSE,
);

bsocial_comments()->register()->comment_status( 'feedback', $args );
```

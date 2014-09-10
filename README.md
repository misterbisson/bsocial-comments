bsocial-comments
================

The featured comments from [bSocial](https://github.com/misterbisson/bsocial) are moved here and we're adding other goodness.

## Installation
1. Install and activate the plugin.
2. Use the default options, or filter `go_config` to return the options you want (when the second arg = `bsocial-comments`).
3. Have fun, feature some comments!

## Usage
### Registering comment types and statuses with WordPress

```php
$args = array(
	'label'             => 'Feedback',
	'label_count'       => _n_noop( 'Feedback <span class="count">(%s)</span>', 'Feedback <span class="count">(%s)</span>' ),
	'status_links_show' => TRUE,
	'include_in_all'    => FALSE,
);

bsocial_comments()->register()->comment_status( 'feedback', $args );

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

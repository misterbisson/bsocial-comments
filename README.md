bsocial-comments
================

## Registering comment types and statuses with WordPress

```php
$args = array(
	'label'                  => 'Feedback',
	'label_count'            => _n_noop('Feedback <span class="count">(%s)</span>', 'Feedback <span class="count">(%s)</span>'),
	'show_in_admin_all_list' => TRUE,
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
	'admin_actions' => array( 'trash' ),
	'statuses'      => array(
		'feedback',
		'trash',
	),
);

bsocial_comments()->register()->comment_type( 'fave', $args );
```

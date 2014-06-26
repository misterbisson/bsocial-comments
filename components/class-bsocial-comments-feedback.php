<?php

class bSocial_Comments_Feedback
{
	public $id_base = 'bsocial-comments-feedback';

	public function __construct()
	{
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
	} // end __construct

	/**
	 * Register our custom comment types and status with WordPress
	 */
	public function init()
	{
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

		$args = array(
			'labels' => array(
				'name'          => 'Flags',
				'singular_name' => 'Flag',
				'edit_item'     => 'Edit Flag',
				'update_item'   => 'Update Flag',
				'view_item'     => 'View Flag',
				'all_items'     => 'All Flags',
			),
			'description'   => 'Comment flags',
			'public'        => TRUE,
			'show_ui'       => TRUE,
			'admin_actions' => array( 'trash' ),
			'statuses'      => array(
				'feedback',
				'trash',
			),
		);

		bsocial_comments()->register()->comment_type( 'flags', $args );
	} // END init
}// END bSocial_Comments_Feedback
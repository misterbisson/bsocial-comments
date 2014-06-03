<?php
class bSocial_Comments_Featured_Admin extends bSocial_Comments_Featured
{
	public function __construct()
	{
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'wp_ajax_bsocial_feature_comment', array( $this, 'ajax_feature_comment' ) );

		add_filter( 'comment_row_actions', array( $this, 'comment_row_actions' ), 10, 2 );

		/*
		@TODO:
		+ add metaboxes to the custom post type that show the comment text and connect to both the post and comment
		+ delete the comment meta when the post is deleted
		+ add a metabox to the comment that connects to the featured comment post
		+ add a metabox to the regular post that shows featured comments on the post
		*/
	} // END __construct

	/**
	 * Enqueu admin JS
	 */
	public function admin_enqueue_scripts()
	{
		wp_register_script( $this->id_base, plugins_url( '/js/bsocial-comments-featured.js', __FILE__ ), array( 'jquery' ), NULL, TRUE );

		$valid_bases = array(
			'comment',
			'edit-comments',
		);

		if ( ! in_array( get_current_screen()->base, $valid_bases ) )
		{
			return;
		} // END if

		wp_enqueue_script( $this->id_base );
	} // END admin_enqueue_scripts

	public function comment_row_actions( $actions, $comment )
	{
		// check permissions against the parent post
		if ( ! current_user_can( 'edit_post', $comment->comment_post_ID ) )
		{
			return $actions;
		}

		// Get feature/unfeature link for the comment
		$actions['feature-comment hide-if-no-js'] = $this->get_feature_comment_link( $comment->comment_ID );

		return $actions;
	} // END comment_row_actions

	public function metabox( $post )
	{
	} // END metabox

	public function register_metaboxes()
	{
		// add metaboxes
		add_meta_box( $id_base, 'Featured Comment', array( $this, 'metabox' ), $this->post_type_name, 'normal', 'high' );
	} // END register_metaboxes
} // END bSocial_Comments_Featured class
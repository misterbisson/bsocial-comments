<?php

class bSocial_Comments_Featured_Admin extends bSocial_Comments_Featured
{
	public $post_id;

	public function __construct()
	{
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2 );
		add_action( 'pre_get_comments', array( $this, 'pre_get_comments' ) );

		add_filter( 'comment_row_actions', array( $this, 'comment_row_actions' ), 10, 2 );
		add_filter( 'comment_status_links', array( $this, 'comment_status_links_add' ), 10, 2 );
	} // END __construct

	/**
	 * Enqueue admin JS
	 */
	public function admin_enqueue_scripts( $hook )
	{
		$script_config = apply_filters( 'go_config', array( 'version' => bsocial_comments()->version ), 'go-script-version' );

		wp_register_script( $this->id_base . '-admin', plugins_url( '/js/bsocial-comments-featured-admin.js', __FILE__ ), array( 'jquery' ), $script_config['version'], TRUE );
		wp_enqueue_style( $this->id_base . '-admin', plugins_url( '/css/bsocial-comments-featured-admin.css', __FILE__ ), array(), $script_config['version'] );

		//pass the hook to the javascript for comparison
		wp_localize_script( $this->id_base . '-admin', 'bsocial_comments_featured_admin', array( 'hook' => $hook ) );

		$valid_bases = array(
			'comment',
			'edit-comments',
		);

		if (
			   ! in_array( get_current_screen()->base, $valid_bases )
			&& ( ! isset( get_current_screen()->post_type ) && ! post_type_supports( get_current_screen()->post_type, 'comments' ) )
		)
		{
			return;
		} // END if

		wp_enqueue_script( $this->id_base . '-admin' );
	} // END admin_enqueue_scripts

	/**
	 * Filters comment_row_actions hook and returns a feature/unfeature link for each comment
	 *
	 * @param $actions (array) Array of action links for the given comment
	 * @param $comment (object) WordPress comment object
	 */
	public function comment_row_actions( $actions, $comment )
	{
		// check permissions against the parent post
		if (
			! current_user_can( 'edit_post', $comment->comment_post_ID )
			|| ( '' != $comment->comment_type && 'comment' != $comment->comment_type )
		)
		{
			return $actions;
		}

		$new_actions = array();

		// Get feature/unfeature link for the comment
		foreach ( $actions as $action => $action_link )
		{
			$new_actions[ $action ] = $action_link;

			if ( 'unapprove' == $action )
			{
				switch ( $comment->comment_approved )
				{
					case '1':
					case '0':
					case 'approve':
					case 'approved':
					case 'hold':
					case 'unapprove':
					case 'unapproved':
						// Get feature/unfeature link for the comment only where we want it
						$new_actions['feature-comment'] = $this->get_feature_link( $comment->comment_ID );
						break;
					default:
						//for trash and spam, $comment->comment_approved returns the text label
						break;
				}//end switch
			} // END if
		} // END foreach

		return $new_actions;
	} // END comment_row_actions

	/**
	 * Add metaboxes
	 */
	public function add_meta_boxes( $post_type, $post )
	{
		if (
			   'comment' == $post_type
			&& $this->post_id = $this->get_comment_meta( $post->comment_ID )
		)
		{
			// Kind of sucky given the limited nature of this metabox but comment metaboxes only work when the context is normal (i.e. 'side' doesn't work)
			add_meta_box( $this->id_base, 'Featured Comment Post', array( $this, 'featured_comment_post_metabox' ), 'comment', 'normal', 'high' );
		} // END if

		add_meta_box( $this->id_base, 'Featured Comment', array( $this, 'featured_comment_metabox' ), $this->post_type_name, 'normal', 'high' );

		if (
			   $post_type != $this->post_type_name
			&& post_type_supports( $post_type, 'comments' )
			&& $this->featured_comments = $this->get_featured_comment_posts( $post->ID )
		)
		{
			add_meta_box( $this->id_base, 'Featured Comments', array( $this, 'featured_comments_metabox' ), $post_type, 'normal', 'high' );
		} // END if
	} // END add_meta_boxes

	/**
	 * Display the post associated with a Featured Comment
	 */
	public function featured_comment_post_metabox( $post )
	{
		$post = get_post( $this->post_id );

		require __DIR__ . '/templates/featured-comment-post.php';
	} // END featured_comment_post_metabox

	/**
	 * Display the comment related to a Featured Comment post
	 */
	public function featured_comment_metabox( $post )
	{
		$comment = get_comment( $this->get_post_meta( $post->ID ) );

		require __DIR__ . '/templates/featured-comment.php';
	} // END featured_comment_metabox

	/**
	 * Display featured comments for a post
	 */
	public function featured_comments_metabox( $post )
	{
		require __DIR__ . '/templates/featured-comments.php';
	} // END featured_comments_metabox

	/**
	 * Hook to the pre_get_comments action and adjust the active query to handle our sudo status
	 */
	public function pre_get_comments( $query )
	{
		if ( ! isset( $_GET['comment_status'] ) || 'featured' != $_GET['comment_status'] )
		{
			return;
		} // END if

		$query->query_vars['meta_query'] = array(
			array(
				'key'     => $this->meta_key . '-post_id',
			),
		);

		$query->meta_query = new WP_Meta_Query();
		$query->meta_query->parse_query_vars( $query->query_vars );
	} // END pre_get_comments

	/**
	 * Filters comment_status_links to include additional sudo comment status for filtering by comments that have been featured
	 *
	 * @param $status_links (array) Array of status links for use in the edit-comments admin panel
	 */
	public function comment_status_links_add( $status_links )
	{
		$status_links['featured'] = bsocial_comments()->register->get_status_link( 'featured', _n_noop( 'Featured', 'Featured' ), $_GET );

		return $status_links;
	} // END comment_status_links_add
}// END bSocial_Comments_Featured class

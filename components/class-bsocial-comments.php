<?php

class bSocial_Comments
{
	public $id_base = 'bsocial-comments';
	public $featuredcomments = NULL;

	private $options = NULL;

	public function __construct()
	{
		add_action( 'init', array( $this, 'init' ), 1 );
		add_action( 'wp_ajax_bsocial_approve_comment', array( $this, 'ajax_approve_comment' ) );

		add_action( 'delete_comment', array( $this, 'comment_id_by_meta_delete_cache' ) );
	} // END __construct

	public function init()
	{
		if ( $this->options()->featuredcomments->enable )
		{
			$this->featured_comments();
		} // END if
	} // END init

	/**
	 * featured comments object accessor
	 */
	public function featured_comments()
	{
		if ( ! $this->featuredcomments )
		{
			require_once __DIR__ . '/class-bsocial-comments-featured.php';
			$this->featuredcomments = new bSocial_Comments_Featured();
		} // END if

		return $this->featuredcomments;
	} // END function_name

	/**
	 * plugin options getter
	 */
	public function options()
	{
		if ( ! $this->options )
		{
			$this->options = (object) apply_filters(
				'go_config',
				wp_parse_args( (array) $this->default_options() ),
				$this->id_base
			);
		}

		return $this->options;
	} // END options

	/**
	 * default options
	 */
	public function default_options()
	{
		return array(
			'featuredcomments' => (object) array(
				'enable'           => 1,
				'use_commentdate'  => 1,
				'add_to_waterfall' => 1,
				'word_limit'       => 50,
			),
		);
	} // END default_options

	/**
	 * this method uses a custom SQL query because it's way more performant
	 * than the SQL from WP's core WP_Comment_Query class.
	 *
	 * The main problem: joins on tables with BLOB or TEXT columns _always_
	 * go to temp tables on disk. See http://dev.mysql.com/doc/refman/5.5/en/internal-temporary-tables.html
	 */
	public function comment_id_by_meta( $metavalue, $metakey )
	{
		global $wpdb;

		if ( ! $comment_id = wp_cache_get( (string) $metakey .':'. (string) $metavalue, 'comment_id_by_meta' ) )
		{
			$comment_id = $wpdb->get_var( $wpdb->prepare( 'SELECT comment_id FROM ' . $wpdb->commentmeta . ' WHERE meta_key = %s AND meta_value = %s', $metakey, $metavalue ) );
			wp_cache_set( (string) $metakey .':'. (string) $metavalue, $comment_id, 'comment_id_by_meta' );
		}

		return $comment_id;
	} // END comment_id_by_meta

	/**
	 * Updates the cache used by comment_id_by_meta
	 */
	public function comment_id_by_meta_update_cache( $comment_id, $metavalue, $metakey )
	{
		if ( 0 < $comment_id )
		{
			return;
		}

		if ( ! $metavalue || ! $metakey )
		{
			return;
		}

		wp_cache_set( (string) $metakey .':'. (string) $metavalue, (int) $comment_id, 'comment_id_by_meta' );
	} // END comment_id_by_meta_update_cache

	/**
	 * deletes the cached used by comment_id_by_meta
	 */
	public function comment_id_by_meta_delete_cache( $comment_id )
	{
		foreach ( (array) get_metadata( 'comment', $comment_id ) as $metakey => $metavalues )
		{
			foreach ( $metavalues as $metavalue )
			{
				wp_cache_delete( (string) $metakey .':'. (string) $metavalue, 'comment_id_by_meta' );
			}
		}
	} // END comment_id_by_meta_delete_cache

	/**
	 * Return a nonced URL to approve/unapprove a comment
	 */
	public function get_approve_url( $comment_id )
	{
		if ( ! $comment = get_comment( $comment_id ) )
		{
			return;
		} // END if

		$arguments = array(
			'action'        => 'bsocial_approve_comment',
			'comment_id'    => absint( $comment->comment_ID ),
			'bsocial-nonce' => wp_create_nonce( 'bsocial-approve-comment' ),
		);

		// If the comment is already featured then this URL should unfeature the comment
		if ( 1 == $comment->comment_approved )
		{
			$arguments['direction'] = 'unapprove';
		} // END if
		else
		{
			$arguments['direction'] = 'approve';
		} // END else

		// Checking is_admin lets us avoid cross domain JS issues because on VIP the admin panel and the site itself have different domains
		return add_query_arg( $arguments, is_admin() ? admin_url( 'admin-ajax.php' ) : site_url( 'wp-admin/admin-ajax.php' ) );
	} // END get_approve_url

	/**
	 * Returns a approve/unapprove link for a comment
	 */
	public function get_approve_link( $comment_id )
	{
		if ( ! $comment = get_comment( $comment_id ) )
		{
			return;
		} // END if

		// If the comment is already approved then this URL should unapprove the comment
		if ( 1 == $comment->comment_approved )
		{
			$text  = 'Unapprove';
			$class = 'approved-comment';
		} // END if
		else
		{
			$text  = 'Approve';
			$class = 'unapproved-comment';
		} // END else

		$classes = 'approve-comment ' . $class;

		$url = $this->get_approve_url( $comment->comment_ID );

		return '<a href="' . $url . '" title="' . $text . '" class="' . $classes . '">' . $text . '</a>';
	} // END get_approve_link

	/**
	 * approve/unapprove a comment via an admin-ajax.php endpoint
	 */
	public function ajax_approve_comment()
	{
		$comment_id = absint( $_GET['comment_id'] );

		if ( ! current_user_can( 'moderate_comments' ) )
		{
			return FALSE;
		}

		if ( ! check_ajax_referer( 'bsocial-approve-comment', 'bsocial-nonce' ) )
		{
			return FALSE;
		} // END if

		if ( $comment = get_comment( $comment_id ) )
		{
			if ( 'approve' == $_GET['direction'] )
			{
				$comment = array(
					'comment_ID'       => $comment->comment_ID,
					'comment_approved' => 0,
				);

				wp_update_comment( $comment );
			}
			else
			{
				$comment = array(
					'comment_ID'       => $comment->comment_ID,
					'comment_approved' => 1,
				);

				wp_update_comment( $comment );
			}

			echo $this->get_approve_link( $comment->comment_ID );
		} // END if

		die;
	} // END ajax_approve_comment
} // END bSocial_Comments

function bsocial_comments()
{
	global $bsocial_comments;

	if( ! $bsocial_comments )
	{
		$bsocial_comments = new bSocial_Comments();
	}

	return $bsocial_comments;
} // END bsocial_comments

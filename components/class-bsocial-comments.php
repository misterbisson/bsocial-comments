<?php

class bSocial_Comments
{
	public $id_base = 'bsocial-comments';
	public $featuredcomments = NULL;
	public $version = '1.0';

	private $options = NULL;

	public function __construct()
	{
		add_action( 'init', array( $this, 'init' ), 1 );
		add_action( 'wp_ajax_bsocial_comment_status', array( $this, 'ajax_comment_status' ) );

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
	 * Return a nonced URL to approve/unapprove/spam/unspam/trash/untrash a comment
	 *
	 * @param $comment_id int The comment_id of the comment you want the URL to affect
	 * @param $type string The type of action you want the URL to apply to the comment: approve/spam/trash
	 */
	public function get_status_url( $comment_id, $type )
	{
		if ( ! $comment = get_comment( $comment_id ) )
		{
			return FALSE;
		} // END if

		if ( ! in_array( $type, array( 'approve', 'spam', 'trash' ) ) )
		{
			return FALSE;
		} // END if

		$arguments = array(
			'action'        => 'bsocial_comment_status',
			'comment_id'    => absint( $comment->comment_ID ),
			'bsocial-nonce' => wp_create_nonce( 'bsocial-comment-status' ),
		);

		switch ( wp_get_comment_status( $comment->comment_ID ) ) {
			case 'approved':
				$arguments['direction'] = 'unapprove';
				break;
			case 'unapproved':
				$arguments['direction'] = 'approve';
				break;
			case 'spam':
				$arguments['direction'] = 'unspam';
				break;
			case 'trash':
				$arguments['direction'] = 'untrash';
				break;
			default:
				// There's no 'unspammed' or 'untrashed' so we'll deal with those only when asked
				if ( 'spam' == $type )
				{
					$arguments['direction'] = 'spam';
				} // END if
				elseif ( 'trash' == $type )
				{
					$arguments['direction'] = 'trash';
				} // END else
				break;
		} // END switch

		// Checking is_admin lets us avoid cross domain JS issues because on VIP the admin panel and the site itself have different domains
		return add_query_arg( $arguments, is_admin() ? admin_url( 'admin-ajax.php' ) : site_url( 'wp-admin/admin-ajax.php' ) );
	} // END get_status_url

	/**
	 * Returns a approve/unapprove/spam/unspam/trash/untrash link for a comment
	 *
	 * @param $comment_id int The comment_id of the comment you want the URL to affect
	 * @param $type string The type of action you want the link to apply to the comment: approve/spam/trash
	 */
	public function get_status_link( $comment_id, $type )
	{
		if ( ! $comment = get_comment( $comment_id ) )
		{
			return FALSE;
		} // END if

		if ( ! in_array( $type, array( 'approve', 'spam', 'trash' ) ) )
		{
			return FALSE;
		} // END if

		switch ( wp_get_comment_status( $comment->comment_ID ) ) {
			case 'approved':
				$text  = 'Unapprove';
				$class = 'approved-comment';
				break;
			case 'unapproved':
				$text  = 'Approve';
				$class = 'unapproved-comment';
				break;
			case 'spam':
				$text  = 'Unspam';
				$class = 'spammed-comment';
				break;
			case 'trash':
				$text  = 'Untrash';
				$class = 'trashed-comment';
				break;
			default:
				// There's no 'unspammed' or 'untrashed' so we'll deal with those only when asked
				if ( 'spam' == $type )
				{
					$text  = 'Spam';
					$class = 'unspamed-comment';
				} // END if
				elseif ( 'trash' == $type )
				{
					$text  = 'Trash';
					$class = 'untrashed-comment';
				} // END else
				break;
		} // END switch

		$url = $this->get_status_url( $comment->comment_ID, $type );

		return '<a href="' . $url . '" title="' . $text . '" class="' . $classes . '">' . $text . '</a>';
	} // END get_status_link

	/**
	 * approve/unapprove/spam/unspam a comment via an admin-ajax.php endpoint
	 */
	public function ajax_comment_status()
	{
		$comment_id = absint( $_GET['comment_id'] );
		$direction  = $_GET['direction'];

		if ( ! current_user_can( 'moderate_comments' ) )
		{
			return FALSE;
		} // END if

		if ( ! check_ajax_referer( 'bsocial-comment-status', 'bsocial-nonce' ) )
		{
			return FALSE;
		} // END if

		$allowed_directions = array(
			'approve',
			'unapprove',
			'spam',
			'unspam',
			'trash',
			'untrash',
		);

		if ( ! in_array( $direct, $allowed_directions ) )
		{
			return FALSE;
		} // END if

		if ( $comment = get_comment( $comment_id ) )
		{
			$data = array();

			switch ( $direction ) {
				case 'approve' :
					$data = array(
						'success' => wp_set_comment_status( $comment->comment_ID, 'approve' ),
						'link'    => $this->get_status_link( $comment->comment_ID, 'approve' ),
					);
					break;
				case 'unapprove' :
					$data = array(
						'success' => wp_set_comment_status( $comment->comment_ID, 'hold' ),
						'link'    => $this->get_status_link( $comment->comment_ID, 'approve' ),
					);
					break;
				case 'spam' :
					$data = array(
						'success' => wp_spam_comment( $comment->comment_ID ),
						'link'    => $this->get_status_link( $comment->comment_ID, 'spam' ),
					);
					break;
				case 'unspam' :
					$data = array(
						'success' => wp_unspam_comment( $comment->comment_ID ),
						'link'    => $this->get_status_link( $comment->comment_ID, 'spam' ),
					);
				case 'trash' :
					$data = array(
						'success' => wp_trash_comment( $comment->comment_ID ),
						'link'    => $this->get_status_link( $comment->comment_ID, 'trash' ),
					);
				case 'untrash' :
					$data = array(
						'success' => wp_untrash_comment( $comment->comment_ID ),
						'link'    => $this->get_status_link( $comment->comment_ID, 'trash' ),
					);
					break;
			} // END switch

			wp_send_json( $data );
		} // END if

		die;
	} // END ajax_comment_status
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

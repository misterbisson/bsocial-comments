<?php

class bSocial_Comments
{
	public $id_base = 'bsocial-comments';
	public $featuredcomments = NULL;
	public $register = NULL;
	public $version = '1.0';

	private $options = NULL;

	public function __construct()
	{
		add_action( 'init', array( $this, 'init' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
		add_action( 'wp_ajax_bsocial_comment_status', array( $this, 'ajax_comment_status' ) );
		add_action( 'wp_ajax_bsocial_comment_favorite_comment', array( $this, 'ajax_favorite_comment' ) );
		add_action( 'wp_ajax_bsocial_comment_flag_comment', array( $this, 'ajax_flag_comment' ) );
		add_action( 'wp_ajax_bsocial_comment_states_for_user', array( $this, 'ajax_states_for_user' ) );

		add_action( 'delete_comment', array( $this, 'comment_id_by_meta_delete_cache' ) );

		add_action( 'bsocial_comments_manage_links', array( $this, 'bsocial_comments_manage_links' ) );
		add_action( 'bsocial_comments_feedback_links', array( $this, 'bsocial_comments_feedback_links' ) );
	} // END __construct

	public function init()
	{
		if ( $this->options()->featuredcomments->enable )
		{
			$this->featured_comments();
		} // END if

		if ( $this->options()->register->enable )
		{
			$this->register();
		} // END if
	} // END init

	/**
	 * hooked to the wp_enqueue_scripts action
	 */
	public function wp_enqueue_scripts()
	{
		$script_config = apply_filters( 'go_config', array( 'version' => 1 ), 'go-script-version' );

		wp_register_script(
			'bsocial-comments',
			plugins_url( 'js/bsocial-comments.js', __FILE__ ),
			array( 'jquery' ),
			$script_config['version'],
			TRUE
		);

		wp_register_script(
			'bsocial-comments-moderation',
			plugins_url( 'js/bsocial-comments-moderation.js', __FILE__ ),
			array( 'jquery' ),
			$script_config['version'],
			TRUE
		);

		$data = array(
			'nonce' => wp_create_nonce( 'bsocial-nonce' ),
			'endpoint' => admin_url( 'admin-ajax.php' ),
		);

		wp_localize_script( 'bsocial-comments', 'bsocial_comments', $data );
		wp_enqueue_script( 'bsocial-comments' );
		wp_enqueue_script( 'bsocial-comments-moderation' );
	}//end wp_enqueue_scripts

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
	} // END featured_comments

	/**
	 * featured comments object accessor
	 */
	public function register()
	{
		if ( ! $this->register )
		{
			require_once __DIR__ . '/class-bsocial-comments-register.php';
			$this->register = new bSocial_Comments_Register();
		} // END if

		return $this->register;
	} // END featured_comments

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
				'enable'           => TRUE,
				'use_commentdate'  => TRUE,
				'add_to_waterfall' => TRUE,
				'has_archive'      => FALSE,
				'rewrite_slug'     => 'talkbox',
				'word_limit'       => 50,
			),
			'register' => (object) array(
				'enable'      => TRUE,
				'filter_text' => FALSE,
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
	 * gives a count for the number of times a comment has been favorited
	 */
	public function comment_favorited_count( $comment_id )
	{
		// @TODO: logic to count the comments that have been favorited for the provided comment id
		return 0;
	}//end comment_favorited_count

	/**
	 * gives a count for the number of times a comment has been flagged
	 */
	public function comment_flagged_count( $comment_id )
	{
		// @TODO: logic to count the comments that have been flagged for the provided comment id
		return 0;
	}//end comment_flagged_count

	/**
	 * returns a link for favoriting a comment
	 */
	public function favorite_comment_link( $comment_id )
	{
		if ( ! $comment = get_comment( $comment_id ) )
		{
			return FALSE;
		} // END if

		$args = array(
			'action' => 'bsocial_comments_favorite_comment',
			'comment_id' => $comment_id,
			'bsocial-nonce' => wp_create_nonce( 'bsocial-comment-favorite' ),
		);

		return add_query_arg( $args, is_admin() ? admin_url( 'admin-ajax.php' ) : site_url( 'wp-admin/admin-ajax.php' ) );
	}//end favorite_comment_link

	/**
	 * returns a link for flag a comment
	 */
	public function flag_comment_link( $comment_id )
	{
		if ( ! $comment = get_comment( $comment_id ) )
		{
			return FALSE;
		} // END if

		$args = array(
			'action' => 'bsocial_comments_flag_comment',
			'comment_id' => $comment_id,
			'bsocial-nonce' => wp_create_nonce( 'bsocial-comment-flag' ),
		);

		return add_query_arg( $args, is_admin() ? admin_url( 'admin-ajax.php' ) : site_url( 'wp-admin/admin-ajax.php' ) );
	}//end flag_comment_link

	/**
	 * handles ajax requests to favorite/unfavorite a comment
	 */
	public function ajax_favorite_comment()
	{
		$comment_id = absint( $_GET['comment_id'] );

		if ( ! check_ajax_referer( 'bsocial-comment-flag', 'bsocial-nonce' ) )
		{
			return wp_send_json_error();
		} // END if

		if ( ! $comment = get_comment( $comment_id ) )
		{
			return wp_send_json_error();
		} // END if

		// @TODO: insert logic to favorite/unfavorite a comment for an oauth'd user

		$data = array(
			// if the comment has been favorited, this should be set to 'favorited'.  Otherwise: 'unfavorited'
			'state' => 'favorited',
		);

		wp_send_json_success( $data );
		die;
	}//end ajax_favorite_comment

	/**
	 * handles ajax requests to flag/unflag a comment
	 */
	public function ajax_flag_comment()
	{
		$comment_id = absint( $_GET['comment_id'] );

		if ( ! check_ajax_referer( 'bsocial-comment-flag', 'bsocial-nonce' ) )
		{
			return wp_send_json_error();
		} // END if

		if ( ! $comment = get_comment( $comment_id ) )
		{
			return wp_send_json_error();
		} // END if

		// @TODO: insert logic to flag/unflag a comment for an oauth'd user

		$data = array(
			// if the comment has been flagged, this should be set to 'flagged'.  Otherwise: 'unflagged'
			'state' => 'flagged',
		);

		wp_send_json_success( $data );
		die;
	}//end ajax_flag_comment

	/**
	 * handles ajax requests to get the states of all comments on post for the oauth'd user
	 */
	public function ajax_states_for_user()
	{
		$post_id = absint( $_GET['post_id'] );
		$user = absint( $_GET['user'] );

		if ( ! check_ajax_referer( 'bsocial-nonce', 'nonce' ) )
		{
			return wp_send_json_error();
		} // END if

		if ( ! $post = get_post( $post_id ) )
		{
			return wp_send_json_error();
		} // END if

		// @TODO find the comment (flag and favorite) states for comments.
		/*
		$args = array(
			'post_id' => $post_id,
			'author_email' => '?????',
			'status' => '?????',
		);
		get_comments( $args );
		// massage into an array somehow
		 */

		$data = array();

		wp_send_json_success( $data );
		die;
	}//end ajax_states_for_user

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
			'direction'     => NULL,
		);

		$status = wp_get_comment_status( $comment->comment_ID );

		if ( 'approve' == $type )
		{
			if ( 'approved' == $status )
			{
				$arguments['direction'] = 'unapprove';
			}//end if
			else
			{
				$arguments['direction'] = 'approve';
			}//end else
		}//end if
		elseif ( 'spam' == $type )
		{
			if ( 'spam' == $status )
			{
				$arguments['direction'] = 'unspam';
			}//end if
			else
			{
				$arguments['direction'] = 'spam';
			}//end else
		}//end elseif
		elseif ( 'trash' == $type )
		{
			if ( 'trash' == $status )
			{
				$arguments['direction'] = 'untrash';
			}//end if
			else
			{
				$arguments['direction'] = 'trash';
			}//end else
		}//end elseif

		if ( ! $arguments['direction'] )
		{
			return;
		}//end if

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

		$text = NULL;
		$class = NULL;

		$status = wp_get_comment_status( $comment->comment_ID );

		if ( 'approve' == $type )
		{
			if ( 'approved' == $status )
			{
				$text  = 'Unapprove';
				$class = 'approved-comment';
			}//end if
			else
			{
				$text  = 'Approve';
				$class = 'unapproved-comment';
			}//end else
		}//end if
		elseif ( 'spam' == $type )
		{
			if ( 'spam' == $status )
			{
				$text  = 'Unspam';
				$class = 'spammed-comment';
			}//end if
			else
			{
				$text  = 'Spam';
				$class = 'unspamed-comment';
			}//end else
		}//end elseif
		elseif ( 'trash' == $type )
		{
			if ( 'trash' == $status )
			{
				$text  = 'Untrash';
				$class = 'trashed-comment';
			}//end if
			else
			{
				$text  = 'Trash';
				$class = 'untrashed-comment';
			}//end else
		}//end elseif

		if ( ! $text )
		{
			return;
		}//end if

		$url = $this->get_status_url( $comment->comment_ID, $type );

		return '<a href="' . $url . '" title="' . $text . '" class="' . $class . '">' . $text . '</a>';
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
			return wp_send_json_error();
		} // END if

		if ( ! check_ajax_referer( 'bsocial-comment-status', 'bsocial-nonce' ) )
		{
			return wp_send_json_error();
		} // END if

		$allowed_directions = array(
			'approve',
			'unapprove',
			'spam',
			'unspam',
			'trash',
			'untrash',
		);

		if ( ! in_array( $direction, $allowed_directions ) )
		{
			return wp_send_json_error();
		} // END if

		if ( ! ( $comment = get_comment( $comment_id ) ) )
		{
			return wp_send_json_error();
		}//end if

		$data = array();

		switch ( $direction )
		{
			case 'approve' :
				$data = array(
					'success' => wp_set_comment_status( $comment->comment_ID, 'approve' ),
					'link'    => $this->get_status_link( $comment->comment_ID, 'approve' ),
					'state'   => 'approved',
				);
				break;
			case 'unapprove' :
				$data = array(
					'success' => wp_set_comment_status( $comment->comment_ID, 'hold' ),
					'link'    => $this->get_status_link( $comment->comment_ID, 'approve' ),
					'state'   => 'unapproved',
				);
				break;
			case 'spam' :
				$data = array(
					'success' => wp_spam_comment( $comment->comment_ID ),
					'link'    => $this->get_status_link( $comment->comment_ID, 'spam' ),
					'state'   => 'spammed',
				);
				break;
			case 'unspam' :
				$data = array(
					'success' => wp_unspam_comment( $comment->comment_ID ),
					'link'    => $this->get_status_link( $comment->comment_ID, 'spam' ),
					'state'   => 'unspammed',
				);
				break;
			case 'trash' :
				$data = array(
					'success' => wp_trash_comment( $comment->comment_ID ),
					'link'    => $this->get_status_link( $comment->comment_ID, 'trash' ),
					'state'   => 'trashed',
				);
				break;
			case 'untrash' :
				$data = array(
					'success' => wp_untrash_comment( $comment->comment_ID ),
					'link'    => $this->get_status_link( $comment->comment_ID, 'trash' ),
					'state'   => 'untrashed',
				);
				break;
		} // END switch

		wp_send_json( $data );

		die;
	} // END ajax_comment_status

	/**
	 * hooked to bsocial_comments_manage_links outputs manage UI for a comment
	 */
	public function manage_links( $comment )
	{
		?>
		<li class="trash-link"><?php echo $this->get_status_link( $comment->comment_ID, 'trash' ); ?></li>
		<li class="spam-link"><?php echo $this->get_status_link( $comment->comment_ID, 'spam' ); ?></li>
		<li class="approve-link"><?php echo $this->get_status_link( $comment->comment_ID, 'approve' ); ?></li>
		<?php
	}//end manage_links

	/**
	 * hooked to bsocial_comments_feedback_links outputs feedback UI for a comment
	 */
	public function feedback_links( $comment )
	{
		$favorited_count = $this->comment_favorited_count( $comment->comment_ID );
		?>
		<span class="comment-like"><a href="<?php echo esc_url( $this->favorite_comment_link( $comment->comment_ID ) ); ?>" class="goicon icon-star"></a><span class="like-count" data-count="<?php echo absint( $favorited_count ); ?>"><?php echo absint( $favorited_count ); ?></span></span>
		<span class="comment-flag"><a href="<?php echo esc_url( $this->flag_comment_link( $comment->comment_ID ) ); ?>" class="goicon icon-x"></a></span>
		<?php
	}//end feedback_links
}// END bSocial_Comments

function bsocial_comments()
{
	global $bsocial_comments;

	if ( ! $bsocial_comments )
	{
		$bsocial_comments = new bSocial_Comments();
	}//end if

	return $bsocial_comments;
} // END bsocial_comments

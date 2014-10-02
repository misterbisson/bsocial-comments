<?php

class bSocial_Comments
{
	public $id_base = 'bsocial-comments';
	public $featured_comments = NULL;
	public $register = NULL;
	public $feedback = NULL;
	public $version = '1.0';

	private $options = NULL;

	public function __construct()
	{
		add_action( 'init', array( $this, 'init' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
		add_action( 'wp_ajax_bsocial_comments_status', array( $this, 'ajax_comment_status' ) );
		add_action( 'delete_comment', array( $this, 'comment_id_by_meta_delete_cache' ) );

		add_action( 'bsocial_comments_manage_links', array( $this, 'manage_links' ) );
	} // END __construct

	public function init()
	{
		if ( $this->options()->featured_comments->enable )
		{
			$this->featured_comments();
		} // END if

		if ( $this->options()->register->enable )
		{
			$this->register();
		} // END if

		if ( $this->options()->feedback->enable )
		{
			$this->feedback();
		} // END if
	} // END init

	/**
	 * hooked to the wp_enqueue_scripts action
	 */
	public function wp_enqueue_scripts()
	{
		$script_config = apply_filters( 'go_config', array( 'version' => $this->version ), 'go-script-version' );

		wp_register_style(
			'bsocial-comments',
			plugins_url( 'css/bsocial-comments.css', __FILE__ ),
			array(),
			$script_config['version']
		);

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

		if ( ! is_single() )
		{
			return;
		}//end if

		$data = array(
			'nonce' => wp_create_nonce( 'bsocial-comment-feedback' ),
			'endpoint' => is_admin() ? admin_url( 'admin-ajax.php' ) : home_url( 'wp-admin/admin-ajax.php' ),
			'logged_in_as' => get_current_user_id(),
		);

		wp_localize_script( 'bsocial-comments', 'bsocial_comments', $data );
		wp_enqueue_script( 'bsocial-comments' );

		if ( current_user_can( 'edit_post', get_queried_object_id() ) )
		{
			wp_enqueue_script( 'bsocial-comments-moderation' );
		}//end if

		wp_enqueue_style( 'bsocial-comments' );
	}//end wp_enqueue_scripts

	/**
	 * featured comments object accessor
	 */
	public function featured_comments()
	{
		if ( ! $this->featured_comments )
		{
			require_once __DIR__ . '/class-bsocial-comments-featured.php';
			$this->featured_comments = new bSocial_Comments_Featured();
		} // END if

		return $this->featured_comments;
	} // END featured_comments

	/**
	 * Comment type/status registration object accessor
	 */
	public function register()
	{
		if ( ! $this->register )
		{
			require_once __DIR__ . '/class-bsocial-comments-register.php';
			$this->register = new bSocial_Comments_Register();
		} // END if

		return $this->register;
	} // END register

	/**
	 * Comment feedback object accessor
	 */
	public function feedback()
	{
		if ( ! $this->feedback )
		{
			require_once __DIR__ . '/class-bsocial-comments-feedback.php';
			$this->feedback = new bSocial_Comments_Feedback();
		} // END if

		return $this->feedback;
	} // END feedback

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
			'featured_comments' => (object) array(
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
			'feedback' => (object) array(
				'enable'      => TRUE,
			),
			'reasons' => array(
				'spam' => array(
					'reason' => 'Spam',
					'display-text' => 'Spam',
				),
				'personal-attack' => array(
					'reason' => 'Personal attack',
					'display-text' => 'Personal attack',
				),
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
			'action'        => 'bsocial_comments_status',
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
		return add_query_arg( $arguments, is_admin() ? admin_url( 'admin-ajax.php' ) : home_url( 'wp-admin/admin-ajax.php' ) );
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

		return '<a href="' . /* @INSANE */ esc_url( $url ) . '" title="' . $text . '" class="' . $class . '">' . $text . '</a>';
	} // END get_status_link

	/**
	 * approve/unapprove/spam/unspam a comment via an admin-ajax.php endpoint
	 */
	public function ajax_comment_status()
	{
		$comment_id = absint( $_GET['comment_id'] );
		$direction  = $_GET['direction'];

		if ( ! current_user_can( 'edit_comment', $comment_id ) )
		{
			$current_user = wp_get_current_user();
			$slog_data    = array( 'comment_id' => $comment_id, 'user_id' => $current_user->ID, 'display_name' => $current_user->display_name );
			do_action( 'go_slog', 'bsocial-comments-status-edit', 'Current user can not edit comment.', $slog_data );
			return wp_send_json_error();
		} // END if

		if ( ! check_ajax_referer( 'bsocial-comment-status', 'bsocial-nonce' ) )
		{
			$slog_data = array( 'nonce' => $_GET['bsocial-nonce'], 'expected-nonce' => wp_create_nonce( 'bsocial-comment-status' ) );
			do_action( 'go_slog', 'bsocial-comments-status-nonce', 'Nonce check failed.', $slog_data );
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
			$slog_data = array( 'direction' => $direction );
			do_action( 'go_slog', 'bsocial-comments-status-direction', 'Invalid direction.', $slog_data );
			return wp_send_json_error();
		} // END if

		if ( ! ( $comment = get_comment( $comment_id ) ) )
		{
			$slog_data = array( 'comment_id' => $comment_id );
			do_action( 'go_slog', 'bsocial-comments-status-comment', 'Comment does not exist.', $slog_data );
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
		<li class="approve-link"><?php echo wp_kses_post( $this->get_status_link( $comment->comment_ID, 'approve' ) ); ?></li>
		<li class="feature-link"><?php echo wp_kses_post( $this->featured_comments()->get_feature_link( $comment->comment_ID ) ); ?></li>
		<li class="edit-link">
			<a class="comment-edit-link" href="<?php echo esc_url( get_edit_comment_link( $comment->comment_ID ) ); ?>">Edit</a>
		</li>
		<li class="spam-link"><?php echo wp_kses_post( $this->get_status_link( $comment->comment_ID, 'spam' ) ); ?></li>
		<li class="trash-link"><?php echo wp_kses_post( $this->get_status_link( $comment->comment_ID, 'trash' ) ); ?></li>
		<?php
	}//end manage_links
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

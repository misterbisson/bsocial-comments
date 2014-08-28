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
		add_action( 'bsocial_comments_feedback_links', array( $this, 'feedback_links' ) );
		add_action( 'bsocial_comments_feedback_info', array( $this, 'feedback_info' ) );
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
		$script_config = apply_filters( 'go_config', array( 'version' => 1 ), 'go-script-version' );

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

		$data = array(
			'nonce' => wp_create_nonce( 'bsocial-comment-feedback' ),
			'endpoint' => admin_url( 'admin-ajax.php' ),
			'logged_in_as' => get_current_user_id(),
		);

		wp_localize_script( 'bsocial-comments', 'bsocial_comments', $data );
		wp_enqueue_script( 'bsocial-comments' );
		wp_enqueue_script( 'bsocial-comments-moderation' );
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

		return '<a href="' . /* @INSANE */ esc_url( $url ) . '" title="' . $text . '" class="' . $class . '">' . $text . '</a>';
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
		<li class="approve-link"><?php echo $this->get_status_link( $comment->comment_ID, 'approve' ); ?></li>
		<li class="spam-link"><?php echo $this->get_status_link( $comment->comment_ID, 'spam' ); ?></li>
		<li class="trash-link"><?php echo $this->get_status_link( $comment->comment_ID, 'trash' ); ?></li>
		<?php
	}//end manage_links

	/**
	 * hooked to bsocial_comments_feedback_links outputs feedback UI for a comment
	 */
	public function feedback_links( $comment )
	{
		$favorited_count = $this->feedback()->comment_fave_count( $comment->comment_ID );
		$flagged_count = $this->feedback()->comment_flag_count( $comment->comment_ID );
		?>
		<span class="comment-fave">
			<a href="<?php echo esc_url( $this->feedback()->get_comment_feedback_url( $comment->comment_ID, 'fave' ) ); ?>" class="goicon icon-fave" title="Fave this comment"></a>
			<span class="fave-count" data-count="<?php echo absint( $favorited_count ); ?>"><?php echo absint( $favorited_count ); ?></span>
		</span>
		<span class="comment-flag">
			<a href="<?php echo esc_url( $this->feedback()->get_comment_feedback_url( $comment->comment_ID, 'flag' ) ); ?>" class="goicon icon-flag" title="Flag this comment"></a>
			<?php
			if ( current_user_can( 'edit_comment', $comment->comment_ID ) )
			{
				?>
				<span class="flag-count" data-count="<?php echo absint( $flagged_count ); ?>"><?php echo absint( $flagged_count ); ?></span>
				<?php
			}//end if
			?>
		</span>
		<?php
	}//end feedback_links

	/**
	 * hooked to bsocial_comments_feedback_info outputs feedback UI for a comment
	 */
	public function feedback_info( $comment )
	{
		$message_logged_out = '<p>Sign in to %1$s this comment</p>';
		$message_logged_in = '<h2>Reason for flagging this comment:</h2>';

		$reasons = array(
			'spam' => array(
				'reason' => 'Spam',
				'display-text' => 'Spam',
			),
			'personal-attack' => array(
				'reason' => 'Personal attack',
				'display-text' => 'Personal attack',
			),
		);

		$reasons = apply_filters( 'bsocial_comments_feedback_flag_reasons', $reasons );

		$message_fave_logged_out = apply_filters(
			'bsocial_comments_feedback_fave_logged_out_message',
			sprintf(
				$message_logged_out,
				'fave',
				wp_login_url( get_permalink() )
			),
			$comment
		);

		$message_flag_logged_out = apply_filters(
			'bsocial_comments_feedback_flag_logged_out_message',
			sprintf(
				$message_logged_out,
				'flag',
				wp_login_url( get_permalink() )
			),
			$comment
		);

		$message_flag_logged_in = apply_filters(
			'bsocial_comments_feedback_flag_logged_in_message',
			sprintf(
				$message_logged_in,
				'flag'
			),
			$comment
		);
		?>
		<div class="feedback-box">
			<section class="fave fave-logged-out">
				<?php
				// this will need to be sanitized up stream as we must be able to support HTML in here
				echo $message_fave_logged_out;
				?>
			</section>
			<section class="flag flag-logged-out">
				<?php
				// this will need to be sanitized up stream as we must be able to support HTML in here
				echo $message_flag_logged_out;
				?>
			</section>
			<section class="flag flag-logged-in">
				<form class="<?php echo esc_attr( implode( ' ', apply_filters( 'bsocial_comments_feedback_form_classes', array() ) ) ); ?>">
					<?php
					// this will need to be sanitized up stream as we must be able to support HTML in here
					echo $message_flag_logged_in;
					?>
					<p>
						<?php
						foreach ( $reasons as $reason_id => $reason )
						{
							$id = "comment-{$comment->comment_ID}-reason-" . sanitize_key( $reason_id );
							$name = preg_replace( '/_reason_.+$/', '_reason', str_replace( '-', '_', $id ) );
							?>
							<label for="<?php echo esc_attr( $id ); ?>">
								<input type="radio" class="go-radio reason" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $reason['reason'] ); ?>">
								<span>
									<?php
									// using wp_kses_post because we wish to support HTML here
									echo wp_kses_post( $reason['display-text'] );
									?>
								</span>
							</label>
							<?php
						}//end foreach

						$id = "comment-{$comment->comment_ID}-reason-other";
						$name = preg_replace( '/_reason_.+$/', '_reason', str_replace( '-', '_', $id ) );

						$description_id = "comment-{$comment->comment_ID}-reason-description";
						$description_name = str_replace( '-', '_', $description_id );
						?>
						<label for="<?php echo esc_attr( $id ); ?>">
							<input type="radio" class="go-radio reason" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" value="Other">
							<span>Other (please describe):</span>
						</label>
					</p>
					<p>
						<textarea class="reason-description" name="<?php echo esc_attr( $description_name ); ?>" id="<?php echo esc_attr( $description_id ); ?>"></textarea>
						<span class="required">Describe your reason for flagging this comment.</span>
					</p>
					<p>
						<a href="<?php echo esc_url( bsocial_comments()->feedback()->get_comment_feedback_url( $comment->ID, 'flag', FALSE, array( 'direction' => 'flag' ) ) ); ?>" class="button primary comment-flag-confirm">Flag</a>
						<button class="button link cancel">Cancel</button>
					</p>
				</form>
			</section>
		</div>
		<?php
	}//end feedback_info
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

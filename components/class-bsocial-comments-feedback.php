<?php

class bSocial_Comments_Feedback
{
	public $id_base = 'bsocial-comments-feedback';
	public $admin = FALSE;
	public $current_feedback = array();

	/**
	 * constructor!
	 */
	public function __construct()
	{
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'wp_ajax_bsocial_comments_comment_feedback', array( $this, 'ajax_comment_feedback' ) );
		add_action( 'wp_ajax_nopriv_bsocial_comments_comment_feedback', array( $this, 'ajax_comment_feedback' ) );
		add_action( 'wp_ajax_bsocial_comments_feedback_states_for_user', array( $this, 'ajax_states_for_user' ) );
		add_action( 'wp_ajax_nopriv_bsocial_comments_feedback_states_for_user', array( $this, 'ajax_states_for_user' ) );
		add_action( 'delete_comment', array( $this, 'delete_comment' ) );
		add_action( 'deleted_comment', array( $this, 'deleted_comment' ) );
		add_action( 'bsocial_comments_feedback_links', array( $this, 'feedback_links' ) );
		add_action( 'bsocial_comments_feedback_info', array( $this, 'feedback_info' ), 10, 2 );

		// Count handling for fave/flag changes via non update_comment_feedback methods
		add_action( 'delete_comment', array( $this, 'pre_handle_feedback_changes' ) );
		add_action( 'trash_comment', array( $this, 'pre_handle_feedback_changes' ) );
		add_action( 'untrash_comment', array( $this, 'pre_handle_feedback_changes' ) );
		add_action( 'deleted_comment', array( $this, 'handle_feedback_changes' ) );
		add_action( 'trashed_comment', array( $this, 'handle_feedback_changes' ) );
		add_action( 'untrashed_comment', array( $this, 'handle_feedback_changes' ) );

		// this should be the first filter that returns comment feedback
		add_filter( 'bsocial_comments_feedback_get_comment_feedback', array( $this, 'get_comment_feedback' ), 1, 4 );
	} // end __construct

	/**
	 * Activate admin functionality
	 */
	public function admin_init()
	{
		$this->admin();
	}// END admin_init

	/**
	 * Admin object accessor
	 */
	public function admin()
	{
		if ( ! $this->admin )
		{
			require_once __DIR__ . '/class-bsocial-comments-feedback-admin.php';
			$this->admin = new bSocial_Comments_Feedback_Admin;
		}

		return $this->admin;
	}// END admin

	/**
	 * Register our custom comment types and status with WordPress
	 */
	public function init()
	{
		$args = array(
			'label'             => 'Feedback',
			'label_count'       => _n_noop( 'Feedback <span class="count">(%s)</span>', 'Feedback <span class="count">(%s)</span>' ),
			'status_links_show' => FALSE,
			'include_in_all'    => TRUE,
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
			'admin_actions' => array( 'trash', 'untrash', 'delete'  ),
			'statuses'      => array(
				'feedback',
				'trash',
			),
		);

		bsocial_comments()->register()->comment_type( 'flag', $args );
	} // END init

	/**
	 * handles ajax requests for comment feedback
	 */
	public function ajax_comment_feedback()
	{
		$comment_id = absint( $_GET['comment_id'] );
		$direction  = $_GET['direction'];

		if ( ! check_ajax_referer( 'bsocial-comment-feedback', 'bsocial-nonce', FALSE ) )
		{
			$slog_data = array( 'nonce' => $_GET['bsocial-nonce'], 'expected-nonce' => wp_create_nonce( 'bsocial-comment-feedback' ) );
			do_action( 'go_slog', 'bsocial-comments-feedback-nonce', 'Nonce check failed.', $slog_data );
			wp_send_json_error();
		} // END if

		if ( ! $comment = get_comment( $comment_id ) )
		{
			$slog_data = array( 'comment_id' => $comment_id );
			do_action( 'go_slog', 'bsocial-comments-feedback-comment', 'Comment does not exist.', $slog_data );
			wp_send_json_error();
		} // END if

		$valid_directions = array(
			'fave',
			'unfave',
			'flag',
			'unflag',
		);

		$inverse_directions = array(
			'fave'   => 'unfave',
			'unfave' => 'fave',
			'flag'   => 'unflag',
			'unflag' => 'flag',
		);

		if ( ! in_array( $direction, $valid_directions ) )
		{
			$slog_data = array( 'direction' => $direction );
			do_action( 'go_slog', 'bsocial-comments-feedback-direction', 'Invalid direction.', $slog_data );
			wp_send_json_error();
		} // END if

		if ( ! $_GET['post_id'] )
		{
			$slog_data = array( 'comment_id' => $comment_id );
			do_action( 'go_slog', 'bsocial-comments-feedback-post-id', 'No post_id given.', $slog_data );
			wp_send_json_error();
		} // END if

		if ( ! $post = get_post( absint( $_GET['post_id'] ) ) )
		{
			$slog_data = array( 'comment_id' => $comment_id, 'post_id' => $_GET['post_id'] );
			do_action( 'go_slog', 'bsocial-comments-feedback-post', 'Post could not be found.', $slog_data );
			wp_send_json_error();
		} // END if

		$success = $this->update_comment_feedback( $post->ID, $comment->comment_ID, $direction, $_GET );

		$data = array(
			'direction' => $direction,
			'state'     => $success ? $direction : $inverse_directions[ $direction ],
		);

		if ( $success )
		{
			wp_send_json_success( $data );
		}//end if

		wp_send_json_error();
	}//end ajax_comment_feedback

	/**
	 * Adds or removes a feedback comment to/from the given comment_id
	 *
	 * @param int $post_id Post ID the comment that is being (un)faved/flagged is from
	 * @param int $comment_id Comment ID that is being (un)faved/flagged
	 * @param string $direction Direction of the feedback: fave, unfave, flag, unflag
	 * @param array $args Additional arguments
	 */
	public function update_comment_feedback( $post_id, $comment_id, $direction, $args = array() )
	{
		if ( ! $comment = get_comment( $comment_id ) )
		{
			return FALSE;
		} // END if

		if ( ! $post = get_post( $post_id ) )
		{
			return FALSE;
		} // END if

		$directions_to_types = array(
			'fave'   => 'fave',
			'unfave' => 'fave',
			'flag'   => 'flag',
			'unflag' => 'flag',
		);

		if ( ! isset( $directions_to_types[ $direction ] ) )
		{
			return FALSE;
		} // END if

		$type = $directions_to_types[ $direction ];

		$user_id = ! empty( $args['user']['user_id'] ) ? absint( $args['user']['user_id'] ) : NULL;
		$user_id = ! empty( $args['user_id'] ) && is_numeric( $args['user_id'] ) ? absint( $args['user_id'] ) : NULL;

		if ( $user_id && $user = get_user_by( 'id', $user_id ) )
		{
			$comment_author = $user->display_name;
			$comment_author_email = $user->user_email;
		}//end if
		elseif ( ! empty( $args['user']['comment_author'] ) && ! empty( $args['user']['comment_author_email'] ) )
		{
			$comment_author = sanitize_text_field( $_GET['user']['comment_author'] );
			$comment_author_email = sanitize_email( $_GET['user']['comment_author_email'] );
		}//end elseif
		else
		{
			return FALSE;
		}//end else

		// if a user_id was passed, make sure it is the current user
		if ( $user_id && $user_id != get_current_user_id() )
		{
			return FALSE;
		}//end if

		$success = FALSE;

		if ( 0 != strncmp( 'un', $direction, 2 ) )
		{
			$content = empty( $args['flag_type'] ) ? $type : $args['flag_type'];

			if ( ! empty( $args['flag_text'] ) )
			{
				$content .= ': ' . sanitize_text_field( $args['flag_text'] );
			}//end if

			$comment = array(
					'comment_post_ID'      => $post_id,
					'comment_author'       => $comment_author,
					'comment_author_email' => $comment_author_email,
					'comment_content'      => $content,
					'comment_type'         => $type,
					'comment_parent'       => $comment_id,
					'user_id'              => $user_id ?: 0,
					'comment_date'         => current_time( 'mysql' ),
					'comment_approved'     => 'feedback',
			);

			$success = FALSE;

			if ( ! $this->get_feedback_id( $comment_id, $type, $user_id ?: $comment_author_email ) )
			{
				$success = wp_insert_comment( $comment );
			} // END if

			// Send email notification to moderator/author if appropriate
			// @TODO Move this and related code to the register class and have notify be a custom comment type param
			if ( $success && get_option( 'comments_notify' ) && 'flag' == $type )
			{
				$this->send_email_notifications( $success );
			} // END if
		} // END if
		else
		{
			if ( $feedback_id = $this->get_feedback_id( $comment_id, $type, $user_id ?: $comment_author_email ) )
			{
				$success = wp_delete_comment( $feedback_id, TRUE );
			} // END if
		} // END else

		// This keeps our feedback count meta up to date
		$this->update_feedback_counts( $comment_id, $type );

		return $success;
	} // END update_comment_feedback

	/**
	 * handles ajax requests to get the states of all comments on post for the oauth'd user
	 */
	public function ajax_states_for_user()
	{
		$post_id = absint( $_GET['post_id'] );

		if ( ! check_ajax_referer( 'bsocial-comment-feedback', 'nonce', FALSE ) )
		{
			wp_send_json_error();
		} // END if

		if ( ! $post = get_post( $post_id ) )
		{
			wp_send_json_error();
		} // END if

		$user_id = ! empty( $_GET['user']['user_id'] ) ? absint( $_GET['user']['user_id'] ) : null;

		if ( $user_id && $user = get_user_by( 'id', $user_id ) )
		{
			$user = $user_id;
		}//end if
		elseif ( ! empty( $_GET['user']['comment_author_email'] ) )
		{
			$user = sanitize_email( $_GET['user']['comment_author_email'] );
		}//end elseif
		else
		{
			wp_send_json_error();
		}//end else

		$data = $this->get_post_comment_states( $post_id, $user );

		wp_send_json_success( $data );
	}//end ajax_states_for_user

	/**
	 * Returns an array of a posts comment states for the given user
	 */
	public function get_post_comment_states( $post_id, $user )
	{
		global $wpdb;

		$sql = 'SELECT comment_parent AS comment_id,
				comment_type AS type
				FROM ' . $wpdb->comments . '
				WHERE comment_post_ID = %d
				AND comment_approved = %s';

		// See what kind of user value we are dealing with
		if ( is_numeric( $user ) )
		{
			$sql .= ' AND user_id = %d';
		} // END if
		else
		{
			// If we weren't given a user_id then we assume it's an author email
			$sql .= ' AND comment_author_email = %s';
		} // END else

		$feedback = $wpdb->get_results( $wpdb->prepare( $sql, $post_id, 'feedback', $user ) );

		$types_to_states = array(
			'fave' => 'faved',
			'flag' => 'flagged',
		);

		$comment_states = array();

		foreach ( $feedback as $row )
		{
			$state = $types_to_states[ $row->type ];
			$comment_states[ $row->comment_id ][ $state ] = $state;
		} // END foreach

		return $comment_states;
	} // END get_post_comment_states

	/**
	 * Returns the id of a users feedback for a specified comment and type
	 */
	public function get_feedback_id( $comment_id, $type, $user )
	{
		if ( 'fave' != $type && 'flag' != $type )
		{
			return FALSE;
		} // END if

		global $wpdb;

		$sql = 'SELECT comment_ID AS ID
				FROM ' . $wpdb->comments . '
				WHERE comment_parent = %d
				AND comment_type = %s
				AND comment_approved = %s';

		// See what kind of user value we are dealing with
		if ( is_numeric( $user ) )
		{
			$sql .= ' AND user_id = %d';
		} // END if
		else
		{
			// If we weren't given a user_id then we assume it's an author email
			$sql .= ' AND comment_author_email = %s';
		} // END else

		$feedback = $wpdb->get_row( $wpdb->prepare( $sql, $comment_id, $type, 'feedback', $user ) );

		return $feedback->ID ?: FALSE;
	} // END get_feedback_id

	/**
	 * Returns the comment state for a given user and state type
	 */
	public function get_comment_state( $comment_id, $type, $user )
	{
		if ( 'fave' != $type && 'flag' != $type )
		{
			return FALSE;
		} // END if

		global $wpdb;

		$sql = 'SELECT COUNT(*) AS count
				FROM ' . $wpdb->comments . '
				WHERE comment_parent = %d
				AND comment_type = %s
				AND comment_approved = %s';

		// See what kind of user value we are dealing with
		if ( is_numeric( $user ) )
		{
			$sql .= ' AND user_id = %d';
		} // END if
		else
		{
			// If we weren't given a user_id then we assume it's an author email
			$sql .= ' AND comment_author_email = %s';
		} // END else

		$count = $wpdb->get_row( $wpdb->prepare( $sql, $comment_id, $type, 'feedback', $user ) );

		if ( 'fave' == $type )
		{
			$state = 0 < $count->count ? 'faved' : 'unfaved';
		} // END if
		else
		{
			$state = 0 < $count->count ? 'flagged' : 'unflagged';
		} // END else

		return $state;
	} // END get_comment_state

	/**
	 * Returns the comment state for a state type
	 */
	public function get_comment_feedback( $unused_feedback, $comment_id, $type, $user = NULL )
	{
		if ( 'fave' != $type && 'flag' != $type )
		{
			return FALSE;
		} // END if

		global $wpdb;

		$args = array(
			$comment_id,
			$type,
			'feedback',
		);

		$sql = 'SELECT *
				FROM ' . $wpdb->comments . '
				WHERE comment_parent = %d
				AND comment_type = %s
				AND comment_approved = %s';

		// See what kind of user value we are dealing with
		if ( NULL != $user )
		{
			if ( is_numeric( $user ) )
			{
				$sql .= ' AND user_id = %d';
			} // END if
			else
			{
				// If we weren't given a user_id then we assume it's an author email
				$sql .= ' AND comment_author_email = %s';
			} // END else

			$args[] = $user;
		}//end if

		return $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
	} // END get_comment_feedback

	/**
	 * gives a count for the number of times a comment has been favorited
	 */
	public function get_comment_fave_count( $comment_id )
	{
		if ( ! $comment = get_comment( $comment_id ) )
		{
			return NULL;
		} // END if

		if ( ! $count = get_comment_meta( $comment_id, $this->id_base . '-faves', TRUE ) )
		{
			$count = $this->update_feedback_counts( $comment_id, 'faves' );
		} // END if

		return $count;
	}//end get_comment_fave_count

	/**
	 * gives a count for the number of times a comment has been flagged
	 */
	public function get_comment_flag_count( $comment_id )
	{
		if ( ! $comment = get_comment( $comment_id ) )
		{
			return NULL;
		} // END if

		if ( ! $count = get_comment_meta( $comment_id, $this->id_base . '-flags', TRUE ) )
		{
			$count = $this->update_feedback_counts( $comment_id, 'flags' );
		} // END if

		return $count;
	}//end get_comment_flag_count

	public function update_feedback_counts( $comment_id, $type )
	{
		if ( ! $comment = get_comment( $comment_id ) )
		{
			return NULL;
		} // END if

		// Auto pluralize types that aren't already plural to avoid issues
		$type = 's' != substr( $type, -1 ) ? $type . 's' : $type;

		if ( 'faves' != $type && 'flags' != $type )
		{
			return NULL;
		} // END if

		global $wpdb;

		$sql = 'SELECT COUNT(*) AS count
				FROM ' . $wpdb->comments . '
				WHERE comment_parent = %d
				AND comment_type = %s
				AND comment_approved = %s';

		$query = $wpdb->get_row( $wpdb->prepare( $sql, $comment_id, substr( $type, 0, -1 ), 'feedback' ) );
		$count = absint( $query->count );
		update_comment_meta( $comment_id, $this->id_base . '-' . $type, $count );

		return $count;
	} // END update_feedback_counts

	/**
	 * Hooks to a variety of actions (trash_comment, untrash_comment, delete_comment) and saves a feedback's parent ID for use in handle_feedback_changes
	 */
	public function pre_handle_feedback_changes( $comment_id )
	{
		if ( ! $feedback = get_comment( $comment_id ) )
		{
			return;
		} // END if

		if ( 'fave' != $feedback->comment_type && 'flag' != $feedback->comment_type )
		{
			return;
		} // END if

		// Record the feedback's comment_parent for use later
		$this->current_feedback['feedback_changes'][ $comment_id ] = (object) array( 'comment_type' => $feedback->comment_type, 'comment_parent' => $feedback->comment_parent );
	} // END pre_handle_feedback_changes

	/**
	 * Hooks to a variety of actions (trashed_comment, untrashed_comment, deleted_comment) and updates feedback counts appropriately
	 */
	public function handle_feedback_changes( $comment_id )
	{
		// Check if we've got any info about this id
		if ( ! isset( $this->current_feedback['feedback_changes'][ $comment_id ] ) )
		{
			return;
		} // END if

		$feedback = $this->current_feedback['feedback_changes'][ $comment_id ];

		$this->update_feedback_counts( $feedback->comment_parent, $feedback->comment_type );
	} // END handle_feedback_status_changes

	/**
	 * returns a link for favoriting a comment
	 */
	public function get_comment_feedback_url( $comment_id, $type, $user_id = FALSE, $args = array() )
	{
		if ( ! $comment = get_comment( $comment_id ) )
		{
			return FALSE;
		} // END if

		$defaults = array(
			'action'        => 'bsocial_comments_comment_feedback',
			'comment_id'    => $comment_id,
			'bsocial-nonce' => wp_create_nonce( 'bsocial-comment-feedback' ),
		);

		$args = wp_parse_args( $args, $defaults );

		// Filter should allow scripts to get the user id from some other method when one wasn't specified
		// Like maybe a site where there's an alternate method of authentication for comments? Perhaps?
		$user_id = $user_id && is_numeric( $user_id ) ? $user_id : apply_filters( 'bsocial_comments_feedback_url_user', get_current_user_id() );

		// Pass the user to the ajax endpoint as well
		$args['user_id'] = urlencode( $user_id );

		// Pass post_id the post this comment is for
		$args['post_id'] = $comment->comment_post_ID;

		if ( empty( $args['direction'] ) )
		{
			$state = $this->get_comment_state( $comment_id, $type, $user_id );
			if ( 'fave' == $type )
			{
				if ( 'faved' == $state )
				{
					$args['direction'] = 'unfave';
				}//end if
				else
				{
					$args['direction'] = 'fave';
				}//end else
			}//end if
			elseif ( 'flag' == $type )
			{
				if ( 'flagged' == $state )
				{
					$args['direction'] = 'unflag';
				}//end if
				else
				{
					$args['direction'] = 'flag';
				}//end else
			}//end elseif
		}//end if

		if ( ! $args['direction'] )
		{
			return;
		}//end if

		return add_query_arg( $args, is_admin() ? admin_url( 'admin-ajax.php' ) : home_url( 'wp-admin/admin-ajax.php' ) );
	}//end get_comment_feedback_url

	/**
	 * Hook to delete_comment action and if a comment has feedback we store it so we can remove it later in deleted_comment
	 *
	 * @param $comment_id (int) The id of the comment
	 */
	public function delete_comment( $comment_id )
	{
		global $wpdb;

		if ( ! $comment = get_comment( $comment_id ) )
		{
			return;
		} // END if

		// Make sure we're dealing with a comment that can have feedback
		if ( '' != $comment->comment_type && 'comment' != $comment->comment_type )
		{
			return;
		} // END if

		$sql = 'SELECT comment_ID
				FROM ' . $wpdb->comments . '
				WHERE comment_parent = %d
				AND ( comment_type = %s
				OR comment_type = %s )';

		// If there was any feedback we want to store it so deleted_comment can delete it AFTER the parent comment has successful gone away
		if ( $feedback = $wpdb->get_results( $wpdb->prepare( $sql, $comment_id, 'fave', 'flag' ) ) )
		{
			$this->current_feedback['feedback_children'][ $comment_id ] = $feedback;
		} // END if
	} // END delete_comment

	/**
	 * Hook to deleted_comment action and if the comment has feedback children delete them as well
	 *
	 * @param $comment_id (int) The id of the comment
	 */
	public function deleted_comment( $comment_id )
	{
		// Check if there are any feedback children for this comment and if not we can stop
		if ( ! isset( $this->current_feedback['feedback_children'][ $comment_id ] ) )
		{
			return;
		} // END if

		// Remove oursevles so we don't infinite loop
		remove_action( 'deleted_comment', array( $this, 'deleted_comment' ) );

		foreach ( $this->current_feedback['feedback_children'][ $comment_id ] as $comment )
		{
			wp_delete_comment( $comment->comment_ID, TRUE );
		} // END foreach

		unset( $this->current_feedback['feedback_children'][ $comment_id ] );

		// Add ourselves back in
		add_action( 'deleted_comment', array( $this, 'deleted_comment' ) );
	} // END deleted_comment

	/**
	 * hooked to bsocial_comments_feedback_links outputs feedback UI for a comment
	 */
	public function feedback_links( $comment )
	{
		$favorited_count = $this->get_comment_fave_count( $comment->comment_ID );
		$flagged_count = $this->get_comment_flag_count( $comment->comment_ID );
		?>
		<span class="comment-fave">
			<a href="<?php echo esc_url( $this->get_comment_feedback_url( $comment->comment_ID, 'fave' ) ); ?>" class="goicon icon-fave" title="Fave this comment">
				<span class="fave-count" data-count="<?php echo absint( $favorited_count ); ?>"><?php echo absint( $favorited_count ); ?></span>
			</a>
		</span>
		<span class="comment-flag">
			<a href="<?php echo esc_url( $this->get_comment_feedback_url( $comment->comment_ID, 'flag' ) ); ?>" class="goicon icon-flag" title="Flag this comment">
				<?php
				if ( current_user_can( 'edit_comment', $comment->comment_ID ) )
				{
					?>
					<span class="flag-count" data-count="<?php echo absint( $flagged_count ); ?>"><?php echo absint( $flagged_count ); ?></span>
					<?php
				}//end if
				?>
			</a>
		</span>
		<?php
	}//end feedback_links

	/**
	 * hooked to bsocial_comments_feedback_info outputs feedback UI for a comment
	 */
	public function feedback_info( $comment, $args )
	{
		$message_logged_out = '<p>Sign in to %1$s this comment</p>';
		$message_logged_in = '<header>Reason for flagging this comment:</header>';

		$reasons = bsocial_comments()->options()->reasons;

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

		// Make sure IDs are always unique
		$id_slug = $comment->comment_ID;

		if ( isset( $args['featured-comments'] ) && $args['featured-comments'] )
		{
			$id_slug .= '-featured';
		}//end if
		?>
		<div class="feedback-box">
			<section class="fave fave-logged-out">
				<?php
				// this will need to be sanitized up stream as we must be able to support HTML in here
				echo wp_kses_post( $message_fave_logged_out );
				?>
			</section>
			<section class="flag flag-logged-out">
				<?php
				// this will need to be sanitized up stream as we must be able to support HTML in here
				echo wp_kses_post( $message_flag_logged_out );
				?>
			</section>
			<section class="flag flag-logged-in">
				<form class="<?php echo esc_attr( implode( ' ', apply_filters( 'bsocial_comments_feedback_form_classes', array() ) ) ); ?>" action="<?php echo esc_url( $this->get_comment_feedback_url( $comment->comment_ID, 'flag', FALSE, array( 'direction' => 'flag' ) ) ); ?>">
					<?php
					// this will need to be sanitized up stream as we must be able to support HTML in here
					echo wp_kses_post( $message_flag_logged_in );
					?>
					<p>
						<?php
						foreach ( $reasons as $reason_id => $reason )
						{
							$id = "comment-{$id_slug}-reason-" . sanitize_key( $reason_id );
							$name = preg_replace( '/_reason_.+$/', '_reason', str_replace( '-', '_', $id ) );
							?>
							<label for="<?php echo esc_attr( $id ); ?>">
							<input
								type="radio"
								class="go-radio reason"
								name="<?php echo esc_attr( $name ); ?>"
								id="<?php echo esc_attr( $id ); ?>"
								value="<?php echo esc_attr( $reason['reason'] ); ?>"
								data-reason-type="<?php echo esc_attr( $reason_id ); ?>"
							>
								<span><?php /* using wp_kses_post because we wish to support HTML here */ echo wp_kses_post( $reason['display-text'] ); ?></span>
							</label>
							<?php
						}//end foreach

						$id = "comment-{$id_slug}-reason-other";
						$name = preg_replace( '/_reason_.+$/', '_reason', str_replace( '-', '_', $id ) );

						$description_id = "comment-{$id_slug}-reason-description";
						$description_name = str_replace( '-', '_', $description_id );
						?>
						<label for="<?php echo esc_attr( $id ); ?>">
							<input type="radio" class="go-radio reason" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" value="Other" data-reason-type="other">
							<span>Other</span>
						</label>
					</p>
					<p class="other-describe">
						<textarea placeholder="Please describe" class="reason-description" name="<?php echo esc_attr( $description_name ); ?>" id="<?php echo esc_attr( $description_id ); ?>"></textarea>
						<span class="required">Describe your reason for flagging this comment.</span>
					</p>
					<p>
						<button class="button primary comment-flag-confirm" disabled="true">Flag</button>
						<button class="button link cancel">Cancel</button>
					</p>
				</form>
			</section>
		</div>
		<?php
	}//end feedback_info

	/**
	 * Send email notification to authors and moderator for a flag
	 * @TODO Move this and related code to the register class and have notify be a custom comment type param
	 *
	 * @param $feedback_id (int) The id of the feedback comment
	 */
	public function send_email_notifications( $feedback_id )
	{
		$feedback = get_comment( $feedback_id );
		$post     = get_post( $feedback->comment_post_ID );

		if ( 'flag' != $feedback->comment_type )
		{
			return FALSE;
		} // END if

		if ( function_exists( 'get_coauthors' ) )
		{
			$authors = get_coauthors( $post->ID );
		} // END if
		else
		{
			$authors = array( get_user_by( 'id', $post->post_author ) );
		} // END else

		// Loop through authors and send a notice if appropriate
		foreach ( $authors as $author )
		{
			// The comment was left by the author
			if ( $feedback->user_id == $author->ID )
			{
				continue;
			} // END if

			// The author is messing with a comment on their own post
			if ( $author->ID == get_current_user_id() )
			{
				continue;
			} // END if

			// There's no email to send the comment to
			if ( '' == $author->user_email )
			{
				continue;
			}

			// The user can't edit the comment
			if ( ! user_can( $author->ID, 'edit_comment', $feedback->comment_parent ) )
			{
				continue;
			} // END if

			// We passed the checks lets send this thing
			$this->send_flag_email( $feedback_id, $author->user_email );
		} // END foreach

		// Send an email to the moderator email address as well if appropriate
		if ( get_option( 'moderation_notify' ) )
		{
			$this->send_flag_email( $feedback_id, get_option( 'admin_email' ) );
		} // END if
	} // END send_email_notifications

	/**
	 * Send email notification to authors and moderator for a flag
	 * @TODO Move this and related code to the register class and have notify be a custom comment type param
	 *
	 * @param $feedback_id (int) The id of the feedback comment
	 * @param $email (string) The email address the flag email will be sent to
	 */
	public function send_flag_email( $feedback_id, $email )
	{
		$feedback = get_comment( $feedback_id );
		$post     = get_post( $feedback->comment_post_ID );

		if ( 'flag' != $feedback->comment_type )
		{
			return FALSE;
		} // END if

		// This header stuff is heavily borrowed from the core WP comment alert stuff
		$wp_email = 'wordpress@' . preg_replace('#^www\.#', '', strtolower( $_SERVER['SERVER_NAME'] ) );
		$from     = 'From: "' . wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ) . '" <' . $wp_email . '>';
		$headers  = $from . "\n" . 'Content-Type: text/plain; charset="' . get_option('blog_charset') . '"' . "\n";

		// Email subject
		$subject = 'A comment has been flagged and is waiting your moderation on ' . esc_html( get_site_url() );

		// Email message
		ob_start();
		require __DIR__ . '/templates/flag-notification-email.php';
		$message = ob_get_contents();
		ob_end_clean();

		return wp_mail( $email, $subject, $message, $headers );
	} // END send_email_notifications
}// END bSocial_Comments_Feedback

<?php

class bSocial_Comments_Feedback
{
	public $id_base = 'bsocial-comments-feedback';
	public $admin = FALSE;
	public $current_feedback = array();

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
		add_action( 'comment_delete_fave', array( $this, 'comment_delete_fave_flag' ), 10, 2 );
		add_action( 'comment_delete_flag', array( $this, 'comment_delete_fave_flag' ), 10, 2 );

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
			'label_count'       => _n_noop('Feedback <span class="count">(%s)</span>', 'Feedback <span class="count">(%s)</span>'),
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
			return wp_send_json_error();
		} // END if

		if ( ! $comment = get_comment( $comment_id ) )
		{
			wp_send_json_error();
			die;
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
			wp_send_json_error();
			die;
		} // END if

		if ( ! $_GET['post_id'] )
		{
			wp_send_json_error();
			die;
		} // END if

		if ( ! $post = get_post( absint( $_GET['post_id'] ) ) )
		{
			wp_send_json_error();
			die;
		} // END if

		$success = $this->update_comment_feedback( $post->ID, $comment->comment_ID, $direction, $_GET );

		$data = array(
			'direction' => $direction,
			'state'     => $success ? $direction : $inverse_directions[ $direction ],
		);

		if ( $success ) {
			wp_send_json_success( $data );
			die;
		}//end if

		wp_send_json_error();
		die;
	}//end ajax_comment_feedback

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

		if ( $user_id && $user = get_user_by( 'id', $user_id ) )
		{
			$comment_author = $user->display_name;
			$comment_author_email = $user->user_email;
		}//end if
		elseif ( ! empty( $args['user']['comment_author'] ) && ! empty( $args['user']['comment_author_email'] ) )
		{
			$comment_author = $_GET['user']['comment_author'];
			$comment_author_email = $_GET['user']['comment_author_email'];
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

		$sucess = FALSE;

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

			$sucess = wp_insert_comment( $comment );

			// Send email notification to mederator/author if appropriate
			if ( $sucess && get_option( 'comments_notify' ) && 'flag' == $type )
			{
				$this->send_email_notifications( $sucess );
			} // END if
		} // END if
		else
		{
			if ( $feedback_id = $this->get_feedback_id( $comment_id, $type, $comment_author_email ) )
			{
				$sucess = wp_delete_comment( $feedback_id, TRUE );
			} // END if
		} // END else

		// This keeps our feedback count meta up to date
		$this->update_feedback_counts( $comment_id, $type );

		return $sucess;
	} // END update_comment_feedback

	/**
	 * handles ajax requests to get the states of all comments on post for the oauth'd user
	 */
	public function ajax_states_for_user()
	{
		$post_id = absint( $_GET['post_id'] );

		if ( ! check_ajax_referer( 'bsocial-comment-feedback', 'nonce', FALSE ) )
		{
			return wp_send_json_error();
		} // END if

		if ( ! $post = get_post( $post_id ) )
		{
			return wp_send_json_error();
		} // END if

		$user_id = ! empty( $_GET['user']['user_id'] ) ? absint( $_GET['user']['user_id'] ) : null;

		if ( $user_id && $user = get_user_by( 'id', $user_id ) )
		{
			$user = $user_id;
		}//end if
		elseif ( ! empty( $_GET['user']['comment_author_email'] ) )
		{
			$user = $_GET['user']['comment_author_email'];
		}//end elseif
		else
		{
			return wp_send_json_error();
		}//end else

		$data = $this->get_post_comment_states( $post_id, $user );

		if ( empty( $data ) )
		{
			wp_send_json_error();
		} // END if

		wp_send_json_success( $data );
		die;
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

		if ( ! $count = get_comment_meta( $comment_id, $this->id_base . '-flags', TRUE ) )
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
	 * returns a link for favoriting a comment
	 */
	public function get_comment_feedback_url( $comment_id, $type, $user = FALSE, $args = array() )
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
		$user  = $user ? $user : apply_filters( 'bsocial_comments_feedback_url_user', get_current_user_id() );

		// Pass the user to the ajax endpoint as well
		$args['user'] = urlencode( $user );

		// Pass post_id the post this comment is for
		$args['post_id'] = $comment->comment_post_ID;

		if ( empty( $args['direction'] ) )
		{
			$state = $this->get_comment_state( $comment_id, $type, $user );
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

		return add_query_arg( $args, is_admin() ? admin_url( 'admin-ajax.php' ) : site_url( 'wp-admin/admin-ajax.php' ) );
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
		if ( '' != $comment->comment_type AND 'comment' != $comment->comment_type )
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
			$this->current_feedback[ $comment_id ] = $feedback;
		} // END if
	} // END delete_comment

	/**
	 * Hook to deleted_comment action and if the comment has feedback children delete them as well
	 *
	 * @param $comment_id (int) The id of the comment
	 */
	public function deleted_comment( $comment_id )
	{
		if ( ! isset( $this->current_feedback[ $comment_id ] ) )
		{
			return;
		} // END if

		// Remove oursevles so we don't infinite loop
		remove_action( 'deleted_comment', array( $this, 'deleted_comment' ) );

		foreach ( $this->current_feedback[ $comment_id ] as $comment )
		{
			wp_delete_comment( $comment->comment_ID, TRUE );
		} // END foreach

		unset( $this->current_feedback[ $comment_id ] );

		// Add ourselves back in
		add_action( 'deleted_comment', array( $this, 'deleted_comment' ) );
	} // END deleted_comment

	/**
	 * Hook to comment_delete_fave and comment_delete_flag actions and update counts
	 *
	 * @param $comment_id (int) The id of the comment
	 */
	public function comment_delete_fave_flag( $unused_comment_id, $comment )
	{
		if ( 'fave' == $comment->comment_type )
		{
			$this->update_feedback_counts( $comment->comment_parent, 'faves' );
		} // END if

		if ( 'flag' == $comment->comment_type )
		{
			$this->update_feedback_counts( $comment->comment_parent, 'flags' );
		} // END if
	} // END transition_comment_status

	/**
	 * Send email notification to authors and moderator for a flag
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
	} // END send_email_notifications

	/**
	 * Send email notification to authors and moderator for a flag
	 *
	 * @param $feedback_id (int) The id of the feedback comment
	 * @param $email (string) The email address the flag email will be sent to
	 */
	public function send_flag_email( $feedback_id, $email )
	{
		$feedback = get_comment( $feedback_id );
		$post     = get_post( $comment->comment_post_ID );

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
		?>
<?php echo esc_html( $subject ); ?>


<?php echo esc_html( $feedback->comment_author ); ?> flagged a comment on the post "<?php echo get_the_title( $post->ID ); ?>"

Reason:
<?php echo esc_html( $feedback->comment_content ); ?>


Flagged comment:
<?php echo wp_kses( get_comment_text( $feedback->comment_parent ) ); ?>


View flagged comment:
<?php echo esc_url_raw( get_comment_link( $feedback->comment_parent ) ); ?>


Trash it: <?php echo esc_url_raw( bsocial_comments()->get_status_url( $feedback->comment_parent, 'trash' ) ); ?>

Spam it: <?php echo esc_url_raw( bsocial_comments()->get_status_url( $feedback->comment_parent, 'spam' ) ); ?>


More info on <?php echo esc_html( $feedback->comment_author ); ?>

IP: <?php echo esc_html( $feedback->comment_author_IP ); ?>, <?php echo esc_html( gethostbyaddr( $feedback->comment_author_IP ) ); ?>
		<?php
		$message = ob_get_contents();
		ob_end_clean();

		if ( 'jamie@gigaom.com' != $email )
		{
			return;
		} // END if

		return wp_mail( $email, $subject, $message, $headers );
	} // END send_email_notifications
}// END bSocial_Comments_Feedback

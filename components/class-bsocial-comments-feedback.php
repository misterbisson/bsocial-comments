<?php

class bSocial_Comments_Feedback
{
	public $id_base = 'bsocial-comments-feedback';
	public $admin = FALSE;

	public function __construct()
	{
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'wp_ajax_bsocial_comments_comment_feedback', array( $this, 'ajax_comment_feedback' ) );
		add_action( 'wp_ajax_nopriv_bsocial_comments_comment_feedback', array( $this, 'ajax_comment_feedback' ) );
		add_action( 'wp_ajax_bsocial_comments_feedback_states_for_user', array( $this, 'ajax_states_for_user' ) );
	} // end __construct

	/**
	 * Activate admin functionality
	 */
	public function admin_init()
	{
		// @TODO I'm imagining some way of seeing the counts for faves/flags other stuff for the admin
		//$this->admin();
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

		$user_id = ! empty( $args['user']['user_id'] ) ? absint( $args['user']['user_id'] ) : null;

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
			$comment = array(
					'comment_post_ID'      => $post_id,
					'comment_author'       => $comment_author,
					'comment_author_email' => $comment_author_email,
					'comment_content'      => empty( $args['flag_type'] ) ? $type : $args['flag_type'],
					'comment_type'         => $type,
					'comment_parent'       => $comment_id,
					'user_id'              => $user_id ?: 0,
					'comment_date'         => current_time( 'mysql' ),
					'comment_approved'     => 'feedback',
			);

			$sucess = wp_new_comment( $comment );
		} // END if
		else
		{
			if ( $feedback_id = $this->get_feedback_id( $comment_id, $type, $comment_author_email ) )
			{
				$sucess = wp_delete_comment( $feedback->ID, TRUE );
			} // END if
		} // END else

		return $sucess;
	} // END update_comment_feedback

	/**
	 * handles ajax requests to get the states of all comments on post for the oauth'd user
	 */
	public function ajax_states_for_user()
	{
		$post_id = absint( $_GET['post_id'] );
		$user    = $_GET['user'];

		if ( ! check_ajax_referer( 'bsocial-comment-feedback', 'bsocial-nonce', FALSE ) )
		{
			//return wp_send_json_error();
		} // END if

		if ( ! $post = get_post( $post_id ) )
		{
			return wp_send_json_error();
		} // END if

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
		global $wpdb;

		if ( 'fave' != $type && 'flag' != $type )
		{
			return FALSE;
		} // END if

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
			$state = isset( $count->count ) ? 'faved' : 'unfaved';
		} // END if
		else
		{
			$state = isset( $count->count ) ? 'flagged' : 'unflagged';
		} // END else

		return $state;
	} // END get_comment_state

	/**
	 * gives a count for the number of times a comment has been favorited
	 */
	public function comment_fave_count( $comment_id )
	{
		global $wpdb;

		$sql = 'SELECT COUNT(*) AS count
				FROM ' . $wpdb->comments . '
				WHERE comment_parent = %d
				AND comment_type = %s
				AND comment_approved = %s';

		$count = $wpdb->get_row( $wpdb->prepare( $sql, $comment_id, 'fave', 'feedback' ) );

		if ( isset( $count->count ) )
		{
			return absint( $count->count );
		} // END if

		return FALSE;
	}//end comment_fave_count

	/**
	 * gives a count for the number of times a comment has been flagged
	 */
	public function comment_flag_count( $comment_id )
	{
		global $wpdb;

		$sql = 'SELECT COUNT(*) AS count
				FROM ' . $wpdb->comments . '
				WHERE comment_parent = %d
				AND comment_type = %s
				AND comment_approved = %s';

		$count = $wpdb->get_row( $wpdb->prepare( $sql, $comment_id, 'flag', 'feedback' ) );

		if ( isset( $count->count ) )
		{
			return absint( $count->count );
		} // END if

		return FALSE;
	}//end comment_flag_count

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
	 * hooked to bsocial_comments_feedback_links outputs feedback UI for a comment
	 */
	public function feedback_links( $comment )
	{
		$fave_count = $this->comment_fave_count( $comment->comment_ID );
		?>
		<span class="comment-like"><a href="<?php echo esc_url( $this->get_comment_feedback_url( $comment->comment_ID, 'fave' ) ); ?>" class="goicon icon-star"></a><span class="like-count" data-count="<?php echo absint( $fave_count ); ?>"><?php echo absint( $fave_count ); ?></span></span>
		<span class="comment-flag"><a href="<?php echo esc_url( $this->get_comment_feedback_url( $comment->comment_ID, 'flag' ) ); ?>" class="goicon icon-x"></a></span>
		<?php
	}//end feedback_links
}// END bSocial_Comments_Feedback

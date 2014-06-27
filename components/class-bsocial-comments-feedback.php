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
		$user       = $_GET['user'];
		$direction  = $_GET['direction'];

		if ( ! check_ajax_referer( 'bsocial-comment-feedback', 'bsocial-nonce' ) )
		{
			return wp_send_json_error();
		} // END if

		if ( ! $comment = get_comment( $comment_id ) )
		{
			return wp_send_json_error();
		} // END if

		$valid_directions = array(
			'fave',
			'unfave',
			'flag',
			'unflag',
		);

		if ( ! in_array( $direction, $valid_directions ) )
		{
			return wp_send_json_error();
		} // END if

		$success = $this->update_comment_feedback( $comment->comment_ID, $direction, $user );

		$data = array(
			'success'   => $success,
			'direction' => $direction,
			'state'     => $state,
		);

		wp_send_json_success( $data );
		die;
	}//end ajax_comment_feedback

	public function update_comment_feedback( $comment_id, $direction, $user )
	{
		if ( ! $comment = get_comment( $comment_id ) )
		{
			return FALSE;
		} // END if

		$directions_to_types = array(
			'fave'   => 'fave',
			'unfave' => 'fave',
			'flag'   => 'flag',
			'unflag' => 'flag',
		);

		if ( ! ( $valid_directions[ $direction ] ) )
		{
			return FALSE;
		} // END if

		$type = $valid_directions[ $direction ];

		if ( 0 == strncmp( 'un', $direction, 2 ) )
		{
			// @TODO flesh this out... which of these matter?
			// Can we jsut set the items we care about?
			$comment = array(
			    'comment_author'       => 'admin',
			    'comment_author_email' => 'admin@admin.com',
			    'comment_author_url'   => 'http://',
			    'comment_content'      => $type,
			    'comment_type'         => $type,
			    'comment_parent'       => $comment_id,
			    'user_id'              => 1,
			    'comment_author_IP'    => '127.0.0.1',
			    'comment_agent'        => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.10) Gecko/2009042316 Firefox/3.0.10 (.NET CLR 3.5.30729)',
			    'comment_date'         => current_time('mysql'),
			    'comment_approved'     => 'feedback',
			);

			$sucess = wp_new_comment( $comment );
		} // END if
		else
		{
			// @TODO get existing feedback comment id somehow
			$feedback_id = '';
			$sucess = wp_insert_comment( $feedback_id, TRUE );
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
	 * Returns the comment state for a given user and state type
	 */
	public function get_comment_state( $comment_id, $type, $user )
	{
		if ( 'fave' != $type && 'flag' != $type )
		{
			return FALSE;
		} // END if

		$sql = 'SELECT COUNT(*) AS count
				FROM ' . $wpdb->comments . '
				WHERE comment_parent = %d
				AND comment_type = %s
				AND comment_status = %s';

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

		if ( 'fave' == $type )
		{
			$count = $wpdb->get_row( $wpdb->prepare( $sql, $comment_id, 'fave', 'feedback', $user ) );
			$state = isset( $count->count ) ? 'faved' : 'unfaved';
		} // END if
		else
		{
			$count = $wpdb->get_row( $wpdb->prepare( $sql, $comment_id, 'flag', 'feedback', $user ) );
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
				AND comment_status = %s';

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
				AND comment_status = %s';

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
	public function get_comment_feedback_url( $comment_id, $type, $user = FALSE )
	{
		if ( ! $comment = get_comment( $comment_id ) )
		{
			return FALSE;
		} // END if

		$args = array(
			'action'        => 'bsocial_comments_comment_feedback',
			'comment_id'    => $comment_id,
			'bsocial-nonce' => wp_create_nonce( 'bsocial-comment-feedback' ),
		);

		// Filter should allow scripts to get the user id from some other method when one wasn't specified
		// Like maybe a site where there's an alternate method of authentication for comments? Perhaps?
		$user  = $user ? $user : apply_filters( 'bsocial_comments_feedback_url_user', get_current_user_id() );
		$state = $this->get_comment_state( $comment_id, $type, $user );

		// Pass the user to the ajax endpoint as well
		$args['user'] = urlencode( $user );

		if ( 'fave' == $type )
		{
			if ( 'faved' == $state )
			{
				$arguments['direction'] = 'unfave';
			}//end if
			else
			{
				$arguments['direction'] = 'fave';
			}//end else
		}//end if
		elseif ( 'flag' == $type )
		{
			if ( 'flagged' == $state )
			{
				$arguments['direction'] = 'unflag';
			}//end if
			else
			{
				$arguments['direction'] = 'flag';
			}//end else
		}//end elseif

		if ( ! $arguments['direction'] )
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
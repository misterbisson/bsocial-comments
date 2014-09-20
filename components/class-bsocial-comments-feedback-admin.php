<?php

class bSocial_Comments_Feedback_Admin extends bSocial_Comments_Feedback
{
	private $dependencies = array(
		'go-ui' => 'https://github.com/GigaOM/go-ui',
	);
	private $missing_dependencies = array();

	public function __construct()
	{
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2 );
		add_action( 'pre_get_comments', array( $this, 'pre_get_comments' ) );

		add_filter( 'manage_edit-comments_columns', array( $this, 'comments_columns' ) );
		add_filter( 'manage_comments_custom_column', array( $this, 'manage_comments_custom_column' ), 10, 2 );
		add_filter( 'manage_edit-comments_sortable_columns', array( $this, 'manage_edit_comments_sortable_columns' ) );
		add_filter( 'comment_status_links', array( $this, 'comment_status_links_add' ), 10, 2 );
	} // end __construct

	/**
	 * Hook to admin_enqueue_scripts
	 */
	public function admin_enqueue_scripts( $current_page )
	{
		$script_config = apply_filters( 'go_config', array( 'version' => bsocial_comments()->version ), 'go-script-version' );

		$this->check_dependencies();

		if ( function_exists( 'go_ui' ) )
		{
			go_ui();
		} // END if

		wp_enqueue_style( $this->id_base . '-admin', plugins_url( '/css/bsocial-comments-feedback-admin.css', __FILE__ ), array( 'fontawesome' ), $script_config['version'] );
		wp_register_script( $this->id_base . '-admin', plugins_url( '/js/bsocial-comments-feedback-admin.js', __FILE__ ), array( 'jquery' ), $script_config['version'], TRUE );

		// Only enqueue script when on a comment edit page where it's needed
		if ( 'comment.php' == $current_page || 'edit-comments.php' == $current_page )
		{
			wp_enqueue_script( $this->id_base . '-admin' );
		} // END if
	} // END admin_enqueue_scripts

	/**
	 * check plugin dependencies
	 */
	public function check_dependencies()
	{
		foreach ( $this->dependencies as $dependency => $url )
		{
			if ( function_exists( str_replace( '-', '_', $dependency ) ) )
			{
				continue;
			}//end if

			$this->missing_dependencies[ $dependency ] = $url;
		}//end foreach

		if ( $this->missing_dependencies )
		{
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		}//end if
	}//end check_dependencies

	/**
	 * hooked to the admin_notices action to inject a message if depenencies are not activated
	 */
	public function admin_notices()
	{
		?>
		<div class="error">
			<p>
				You must <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">activate</a> the following plugins before using <code>bsocial-comments</code>'s to it's fullest potential:
			</p>
			<ul>
				<?php
				foreach ( $this->missing_dependencies as $dependency => $url )
				{
					?>
					<li><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $dependency ); ?></a></li>
					<?php
				}//end foreach
				?>
			</ul>
		</div>
		<?php
	}//end admin_notices

	/**
	 * Add metaboxes
	 */
	public function add_meta_boxes( $post_type, $post )
	{
		if ( 'comment' != $post_type )
		{
			return;
		} // END if

		add_meta_box( $this->id_base . '-faves', 'Comment Faves', array( $this, 'faves_meta_box' ), 'comment', 'normal', 'high' );
		add_meta_box( $this->id_base . '-flags', 'Comment Flags', array( $this, 'flags_meta_box' ), 'comment', 'normal', 'high' );
	} // END add_meta_boxes

	/**
	 * Render the comment faves metabox
	 */
	public function faves_meta_box( $comment )
	{
		require_once __DIR__ . '/class-bsocial-comments-feedback-table.php';

		$go_list_table = new bSocial_Comments_Feedback_Table();

		$go_list_table->current_comment = $comment;
		$go_list_table->type = 'fave';

		$go_list_table->prepare_items();
		$go_list_table->custom_display();
	} // END faves_meta_box

	/**
	 * Render the comment flags metabox
	 */
	public function flags_meta_box( $comment )
	{
		require_once __DIR__ . '/class-bsocial-comments-feedback-table.php';

		$go_list_table = new bSocial_Comments_Feedback_Table();

		$go_list_table->current_comment = $comment;
		$go_list_table->type = 'flag';

		$go_list_table->prepare_items();
		$go_list_table->custom_display();
	} // END flags_meta_box

	/**
	 * Hook to the pre_get_comments action and adjust the active query to handle our sudo statuses and feedback sorting
	 */
	public function pre_get_comments( $query )
	{
		$current_screen = get_current_screen();

		if ( ! is_admin() || 'edit-comments' != $current_screen->base )
		{
			return;
		} // END if

		$reparse_meta = FALSE;

		if (
			isset( $_GET['comment_status'] )
			&& ( 'faved' == $_GET['comment_status'] || 'flagged' == $_GET['comment_status'] )
		)
		{
			$reparse_meta = TRUE;
			$query = $this->handle_sudo_statuses( $query );
		} // END if

		if (
			isset( $_GET['orderby'] )
			&& ( 'faves' == $_GET['orderby'] || 'flags' == $_GET['orderby'] )
		)
		{
			$reparse_meta = TRUE;
			$query = $this->handle_feedback_sorting( $query );
		} // END if

		if ( ! $reparse_meta )
		{
			return;
		} // END if

		$query->meta_query = new WP_Meta_Query();
		$query->meta_query->parse_query_vars( $query->query_vars );
	} // END pre_get_comments

	/**
	 * Add necessary query values for the sudo statuses
	 */
	public function handle_sudo_statuses( $query )
	{
		$type = 'faved' == $_GET['comment_status'] ? 'faves' : 'flags';

		$query->query_vars['meta_query'] = array(
			array(
				'key'     => $this->id_base . '-' . $type,
				'value'   => 0,
				'compare' => '!=',
			),
		);

		return $query;
	} // END handle_sudo_statuses

	/**
	 * Add necessary query values for the feedback sorting
	 */
	public function handle_feedback_sorting( $query )
	{
		$type = 'faves' == $_GET['orderby'] ? 'faves' : 'flags';

		$query->query_vars['meta_key'] = $this->id_base . '-' . $type;
		$query->query_vars['orderby']  = 'meta_value_num';

		return $query;
	} // END handle_feedback_sorting

	/**
	 * Hook to manage_edit-comments_columns filter and add columns for flags and faves
	 *
	 * @param $columns (array) array of column slugs and names
	 */
	public function comments_columns( $columns )
	{
		// Rebuild the array so we can make sure Faves/Flags columns show right after the comment content
		$new_columns = array();

		foreach ( $columns as $column => $column_name )
		{
			$new_columns[ $column ] = $column_name;

			if ( 'comment' == $column )
			{
				$new_columns['faves'] = 'Faves';
				$new_columns['flags'] = 'Flags';
			} // END if
		} // END foreach

		return $new_columns;
	} // END comments_columns

	/**
	 * Hook to the manage_comments_custom_column filter and echo appropriate content for the column
	 *
	 * @param $column (string) slug of the column being rendered
	 * @param $comment_id (int) WP comment_id value for the comment being viewed
	 */
	public function manage_comments_custom_column( $column, $comment_id )
	{
		if ( 'faves' != $column && 'flags' != $column )
		{
			return;
		} // END if

		switch ( $column )
		{
			case 'faves':
				$this->faves_column( $comment_id );
				break;

			case 'flags':
				$this->flags_column( $comment_id );
				break;
		} // END switch
	} // END manage_comments_custom_column

	/**
	 * Render and echo fave column value for a given comment
	 *
	 * @param $comment_id (int) WP comment_id value for the comment being viewed
	 */
	public function faves_column( $comment_id )
	{
		if ( ! $comment = get_comment( $comment_id ) )
		{
			return;
		} // END if

		if ( 'fave' == $comment->comment_type )
		{
			echo $this->get_parent_link( $comment->comment_parent );
		} // END if
		elseif ( '' == $comment->comment_type || 'comment' == $comment->comment_type )
		{
			$count      = $this->get_comment_fave_count( $comment_id );
			$count_link = '<a href="' . esc_url( get_edit_comment_link( $comment_id ) ) . '" title="Edit comment"><i class="fa fa-thumbs-up"></i> ' . absint( $count ) . '</a>';

			echo 0 == $count ? '<span class="zero">' . wp_kses_post( $count_link ) . '</span>' : '<span class="faves">' . wp_kses_post( $count_link ) . '</span>';
		} // END elseif
	} // END faves_column

	/**
	 * Render and echo flag column value for a given comment
	 *
	 * @param $comment_id (int) WP comment_id value for the comment being viewed
	 */
	public function flags_column( $comment_id )
	{
		if ( ! $comment = get_comment( $comment_id ) )
		{
			return;
		} // END if

		if ( 'flag' == $comment->comment_type )
		{
			echo $this->get_parent_link( $comment->comment_parent );
		} // END if
		elseif ( '' == $comment->comment_type || 'comment' == $comment->comment_type )
		{
			$count      = $this->get_comment_flag_count( $comment_id );
			$count_link = '<a href="' . esc_url( get_edit_comment_link( $comment_id ) ) . '" title="Edit comment"><i class="fa fa-flag"></i> ' . absint( $count ) . '</a>';

			echo 0 == $count ? '<span class="zero">' . wp_kses_post( $count_link ) . '</span>' : '<span class="flags">' . wp_kses_post( $count_link ) . '</span>';
		} // END elseif
	} // END flags_column

	/**
	 * Echos a comment edit link for a flag/fave parent
	 *
	 * @param $parent_id (int) WP parent_id value the link should be created for
	 */
	public function get_parent_link( $parent_id )
	{
		$url = add_query_arg( array( 'action' => 'editcomment', 'c' => absint( $parent_id ) ), admin_url( 'comment.php' ) );
		echo '<a href="' . esc_url( $url ) . '" title="Edit parent comment"><i class="fa fa-comment"></i> </a>';
	} // END get_parent_link

	/**
	 * Hook to the manage_edit-comments_sortable_columns filter and add faves/flags to the list of sortable columns
	 *
	 * @param $parent_id (int) WP parent_id value the link should be created for
	 */
	public function manage_edit_comments_sortable_columns( $sortable_columns )
	{
		$sortable_columns['faves'] = 'faves';
		$sortable_columns['flags'] = 'flags';

		return $sortable_columns;
	} // END manage_edit_comments_sortable_columns

	/**
	 * Filters comment_status_links to include additional sudo comment statuses for filtering by comments that are flagged or faved
	 *
	 * @param $status_links (array) Array of status links for use in the edit-comments admin panel
	 */
	public function comment_status_links_add( $status_links )
	{
		$status_links['faved']  = bsocial_comments()->register->get_status_link( 'faved', _n_noop( 'Faved', 'Faved' ), $_GET );
		$status_links['flaged'] = bsocial_comments()->register->get_status_link( 'flagged', _n_noop( 'Flagged', 'Flagged' ), $_GET );

		return $status_links;
	} // END comment_status_links_add
}// END bSocial_Comments_Feedback_Admin

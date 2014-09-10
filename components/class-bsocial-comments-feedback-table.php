<?php

class bSocial_Comments_Feedback_Table extends WP_List_Table
{
	public $current_comment = '';
	public $type = 'fave';
	public $user_can;

	/**
	 * constructor!
	 */
	public function __construct()
	{
		//Set parent defaults
		parent::__construct(
			array(
				'singular' => 'bsocial-comments-feedback',  //singular name of the listed records
				'plural'   => 'bsocial-comments-feedback', //plural name of the listed records
				'ajax'     => FALSE,             //does this table support ajax?
			)
		);
	} // END __construct

	/**
	 * Custom column to render comment authors
	 *
	 * @param Object $comment Current comment
	 */
	public function column_author( $comment )
	{
		?>
		<strong><?php comment_author(); ?></strong><br />
		<?php
		$author_url = get_comment_author_url();

		if ( preg_match( '|^https?://$|', $author_url ) )
		{
			$author_url = '';
		} // END if

		$author_url_display = preg_replace( '|https?://(www\.)?|i', '', $author_url );

		if ( strlen( $author_url_display ) > 50 )
		{
			$author_url_display = substr( $author_url_display, 0, 49 ) . '&hellip;';
		} // END if

		if ( ! empty( $author_url ) )
		{
			?>
			<a href="<?php echo esc_url( $author_url ); ?>" title="<?php echo esc_url( $author_url ); ?>"><?php esc_html( $author_url_display ); ?></a>
			<?php
		} // END if

		if ( $this->user_can )
		{
			if ( !empty( $comment->comment_author_email ) )
			{
				comment_author_email_link();
			} // END if

			$args = array(
				's'    => get_comment_author_IP(),
				'mode' => 'detail',
			);
			?>
			<br />
			<a href="<?php echo esc_url( add_query_arg( $args, admin_url( 'edit-comments.php' ) ) ); ?>"><?php echo esc_html( get_comment_author_IP() ); ?></a>
			<?php
		} // END if
	} // END column_default

	/**
	 * Custom column to output info on the comment the feedback is attached to
	 *
	 * @param Object $comment Current comment
	 */
	public function column_comment( $comment )
	{
		global $comment_status;
		$post = get_post();

		// Feedback comments aren't displayed on the front end individually but as counts on the actual comments so we link to the parent
		$comment_url = esc_url( get_comment_link( $comment->comment_parent ) );

		if ( $this->user_can )
		{
			$del_nonce = esc_html( '_wpnonce=' . wp_create_nonce( "delete-comment_$comment->comment_ID" ) );
			$approve_nonce = esc_html( '_wpnonce=' . wp_create_nonce( "approve-comment_$comment->comment_ID" ) );

			$url = "comment.php?c=$comment->comment_ID";

			$trash_url = esc_url( $url . "&action=trashcomment&$del_nonce" );
		} // END if

		echo '<div class="submitted-on">';

		/* translators: 2: comment date, 3: comment time */
		printf( __( 'Submitted on <a href="%1$s">%2$s at %3$s</a>' ), $comment_url,
			/* translators: comment date format. See http://php.net/date */
			get_comment_date( __( 'Y/m/d' ) ),
			get_comment_date( get_option( 'time_format' ) )
		);

		echo '</div>';

		comment_text();

		if ( $this->user_can )
		{
			// There's only one valid action for feedback comments
			?>
			<div class="row-actions">
				<span class="trash">
					<a href="<?php echo esc_url( $trash_url ); ?>" class="delete vim-d vim-destructive" title="Move this comment to the trash">Trash</a>
				</span>
			</div>
			<?php
		}
	} // END column_default

	/**
	 * Returns an associative array of columns
	 */
	public function get_columns()
	{
		$columns = array(
			'author'  => 'Author',
			'comment' => 'Comment',
		);

		return $columns;
	} // END get_columns

	/**
	 * This echos a single item (from the items property) to the page.
	 *
	 * @param Object $comment Current comment
	 */
	public function single_row( $comment )
	{
		// Sort of goofy but this is how WP does it so we might as well copy
		if ( get_option( 'show_avatars' ) )
		{
			add_filter( 'comment_author', 'floated_admin_avatar' );
		}

		// Prep some stuff so the functions have what they need
		$GLOBALS['comment'] = $comment;
		$this->user_can = current_user_can( 'edit_comment', $comment->comment_ID );

		static $row_class = 'insane';
		$row_class = ( 'insane' == $row_class ) ? 'alternate' : 'insane';

		echo '<tr id="' . esc_attr( $this->type ) . '-feedback-' . absint( $comment->comment_ID ) . '" class="' . esc_attr( $row_class ) . '">';
		echo $this->single_row_columns( $comment );
		echo '</tr>';

		// Set the comment global back to the current comment
		$GLOBALS['comment'] = $this->current_comment;
	} // END single_row

	/**
	 * prepares the comments for rendering
	 */
	public function prepare_items()
	{
		// Set columns
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = array();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Get the feedback
		$args = array(
			'parent' => $this->current_comment->comment_ID,
			'type'   => $this->type,
			'status' => 'feedback',
			'number' => 0,
		);

		$this->items = get_comments( $args );
	} // END prepare_items

	/**
	 * Returns a list of css classes to be attached to the table element.
	 */
	public function get_table_classes()
	{
		$classes = parent::get_table_classes();
		$classes[] = 'comments-box';
		$classes[] = $this->type;
		return $classes;
	}

	/**
	 * Outputs the list table the way we want for feedback info!
	 */
	public function custom_display()
	{
		?>
		<table class="<?php echo esc_attr( implode( ' ', $this->get_table_classes() ) ); ?>">
			<?php
			if ( 0 == count( $this->items ) )
			{
				?>
				<tbody class="none">
					<tr>
						<td>
							There are currently no <?php echo esc_html( $this->type ); ?>s for this comment.
						</td>
					</tr>
				</tbody>
				<?php
			} // END if
			else
			{
				?>
				<tbody>
					<?php $this->display_rows_or_placeholder(); ?>
				</tbody>
				<?php
			} // END else
			?>
		</table>
		<?php
	} // END custom_display
}// END bSocial_Comments_Feedback_Table

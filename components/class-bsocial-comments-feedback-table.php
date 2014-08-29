<?php

class bSocial_Comments_Feedback_Table extends WP_List_Table
{
	public $current_comment = '';
	public $type = 'fave';
	public $user_can;

	public function __construct()
	{
		//Set parent defaults
		parent::__construct(
			array(
				'singular' => 'embedded-chart',  //singular name of the listed records
				'plural'   => 'embedded-charts', //plural name of the listed records
				'ajax'     => FALSE,             //does this table support ajax?
			)
		);
	} // END __construct

	public function column_author( $comment )
	{
		global $comment_status;

		$author_url = get_comment_author_url();

		if ( 'http://' == $author_url )
		{
			$author_url = '';
		}

		$author_url_display = preg_replace( '|http://(www\.)?|i', '', $author_url );

		if ( strlen( $author_url_display ) > 50 )
		{
			$author_url_display = substr( $author_url_display, 0, 49 ) . '&hellip;';
		}

		echo "<strong>"; comment_author(); echo '</strong><br />';

		if ( !empty( $author_url ) )
		{
			echo "<a title='$author_url' href='$author_url'>$author_url_display</a><br />";
		}

		if ( $this->user_can )
		{
			if ( !empty( $comment->comment_author_email ) )
			{
				comment_author_email_link();
				echo '<br />';
			}
			echo '<a href="edit-comments.php?s=';
			comment_author_IP();
			echo '&amp;mode=detail';
			if ( 'spam' == $comment_status )
				echo '&amp;comment_status=spam';
			echo '">';
			comment_author_IP();
			echo '</a>';
		}
	} // END column_default

	public function column_comment( $comment )
	{
		global $comment_status;
		$post = get_post();

		$user_can = $this->user_can;

		$comment_url = esc_url( get_comment_link( $comment->comment_ID ) );
		$the_comment_status = wp_get_comment_status( $comment->comment_ID );

		if ( $user_can ) {
			$del_nonce = esc_html( '_wpnonce=' . wp_create_nonce( "delete-comment_$comment->comment_ID" ) );
			$approve_nonce = esc_html( '_wpnonce=' . wp_create_nonce( "approve-comment_$comment->comment_ID" ) );

			$url = "comment.php?c=$comment->comment_ID";

			$approve_url = esc_url( $url . "&action=approvecomment&$approve_nonce" );
			$unapprove_url = esc_url( $url . "&action=unapprovecomment&$approve_nonce" );
			$spam_url = esc_url( $url . "&action=spamcomment&$del_nonce" );
			$unspam_url = esc_url( $url . "&action=unspamcomment&$del_nonce" );
			$trash_url = esc_url( $url . "&action=trashcomment&$del_nonce" );
			$untrash_url = esc_url( $url . "&action=untrashcomment&$del_nonce" );
			$delete_url = esc_url( $url . "&action=deletecomment&$del_nonce" );
		}

		echo '<div class="submitted-on">';
		/* translators: 2: comment date, 3: comment time */
		printf( __( 'Submitted on <a href="%1$s">%2$s at %3$s</a>' ), $comment_url,
			/* translators: comment date format. See http://php.net/date */
			get_comment_date( __( 'Y/m/d' ) ),
			get_comment_date( get_option( 'time_format' ) )
		);

		if ( $comment->comment_parent ) {
			$parent = get_comment( $comment->comment_parent );
			$parent_link = esc_url( get_comment_link( $comment->comment_parent ) );
			$name = get_comment_author( $parent->comment_ID );
			printf( ' | '.__( 'In reply to <a href="%1$s">%2$s</a>.' ), $parent_link, $name );
		}

		echo '</div>';
		comment_text();
		if ( $user_can ) { ?>
		<div id="inline-<?php echo $comment->comment_ID; ?>" class="hidden">
		<textarea class="comment" rows="1" cols="1"><?php
			/** This filter is documented in wp-admin/includes/comment.php */
			echo esc_textarea( apply_filters( 'comment_edit_pre', $comment->comment_content ) );
		?></textarea>
		<div class="author-email"><?php echo esc_attr( $comment->comment_author_email ); ?></div>
		<div class="author"><?php echo esc_attr( $comment->comment_author ); ?></div>
		<div class="author-url"><?php echo esc_attr( $comment->comment_author_url ); ?></div>
		<div class="comment_status"><?php echo $comment->comment_approved; ?></div>
		</div>
		<?php
		}

		if ( $user_can ) {
			// preorder it: Approve | Reply | Quick Edit | Edit | Spam | Trash
			$actions = array(
				'approve' => '', 'unapprove' => '',
				'reply' => '',
				'quickedit' => '',
				'edit' => '',
				'spam' => '', 'unspam' => '',
				'trash' => '', 'untrash' => '', 'delete' => ''
			);

			if ( $comment_status && 'all' != $comment_status ) { // not looking at all comments
				if ( 'approved' == $the_comment_status )
					$actions['unapprove'] = "<a href='$unapprove_url' data-wp-lists='delete:the-comment-list:comment-$comment->comment_ID:e7e7d3:action=dim-comment&amp;new=unapproved' class='vim-u vim-destructive' title='" . esc_attr__( 'Unapprove this comment' ) . "'>" . __( 'Unapprove' ) . '</a>';
				else if ( 'unapproved' == $the_comment_status )
					$actions['approve'] = "<a href='$approve_url' data-wp-lists='delete:the-comment-list:comment-$comment->comment_ID:e7e7d3:action=dim-comment&amp;new=approved' class='vim-a vim-destructive' title='" . esc_attr__( 'Approve this comment' ) . "'>" . __( 'Approve' ) . '</a>';
			} else {
				$actions['approve'] = "<a href='$approve_url' data-wp-lists='dim:the-comment-list:comment-$comment->comment_ID:unapproved:e7e7d3:e7e7d3:new=approved' class='vim-a' title='" . esc_attr__( 'Approve this comment' ) . "'>" . __( 'Approve' ) . '</a>';
				$actions['unapprove'] = "<a href='$unapprove_url' data-wp-lists='dim:the-comment-list:comment-$comment->comment_ID:unapproved:e7e7d3:e7e7d3:new=unapproved' class='vim-u' title='" . esc_attr__( 'Unapprove this comment' ) . "'>" . __( 'Unapprove' ) . '</a>';
			}

			if ( 'spam' != $the_comment_status && 'trash' != $the_comment_status ) {
				$actions['spam'] = "<a href='$spam_url' data-wp-lists='delete:the-comment-list:comment-$comment->comment_ID::spam=1' class='vim-s vim-destructive' title='" . esc_attr__( 'Mark this comment as spam' ) . "'>" . /* translators: mark as spam link */ _x( 'Spam', 'verb' ) . '</a>';
			} elseif ( 'spam' == $the_comment_status ) {
				$actions['unspam'] = "<a href='$unspam_url' data-wp-lists='delete:the-comment-list:comment-$comment->comment_ID:66cc66:unspam=1' class='vim-z vim-destructive'>" . _x( 'Not Spam', 'comment' ) . '</a>';
			} elseif ( 'trash' == $the_comment_status ) {
				$actions['untrash'] = "<a href='$untrash_url' data-wp-lists='delete:the-comment-list:comment-$comment->comment_ID:66cc66:untrash=1' class='vim-z vim-destructive'>" . __( 'Restore' ) . '</a>';
			}

			if ( 'spam' == $the_comment_status || 'trash' == $the_comment_status || !EMPTY_TRASH_DAYS ) {
				$actions['delete'] = "<a href='$delete_url' data-wp-lists='delete:the-comment-list:comment-$comment->comment_ID::delete=1' class='delete vim-d vim-destructive'>" . __( 'Delete Permanently' ) . '</a>';
			} else {
				$actions['trash'] = "<a href='$trash_url' data-wp-lists='delete:the-comment-list:comment-$comment->comment_ID::trash=1' class='delete vim-d vim-destructive' title='" . esc_attr__( 'Move this comment to the trash' ) . "'>" . _x( 'Trash', 'verb' ) . '</a>';
			}

			if ( 'spam' != $the_comment_status && 'trash' != $the_comment_status ) {
				$actions['edit'] = "<a href='comment.php?action=editcomment&amp;c={$comment->comment_ID}' title='" . esc_attr__( 'Edit comment' ) . "'>". __( 'Edit' ) . '</a>';
				$actions['quickedit'] = '<a onclick="window.commentReply && commentReply.open( \''.$comment->comment_ID.'\',\''.$post->ID.'\',\'edit\' );return false;" class="vim-q" title="'.esc_attr__( 'Quick Edit' ).'" href="#">' . __( 'Quick&nbsp;Edit' ) . '</a>';
				$actions['reply'] = '<a onclick="window.commentReply && commentReply.open( \''.$comment->comment_ID.'\',\''.$post->ID.'\' );return false;" class="vim-r" title="'.esc_attr__( 'Reply to this comment' ).'" href="#">' . __( 'Reply' ) . '</a>';
			}

			/** This filter is documented in wp-admin/includes/dashboard.php */
			$actions = apply_filters( 'comment_row_actions', array_filter( $actions ), $comment );

			$i = 0;
			echo '<div class="row-actions">';
			foreach ( $actions as $action => $link ) {
				++$i;
				( ( ( 'approve' == $action || 'unapprove' == $action ) && 2 === $i ) || 1 === $i ) ? $sep = '' : $sep = ' | ';

				// Reply and quickedit need a hide-if-no-js span when not added with ajax
				if ( ( 'reply' == $action || 'quickedit' == $action ) && ! defined('DOING_AJAX') )
					$action .= ' hide-if-no-js';
				elseif ( ( $action == 'untrash' && $the_comment_status == 'trash' ) || ( $action == 'unspam' && $the_comment_status == 'spam' ) ) {
					if ( '1' == get_comment_meta( $comment->comment_ID, '_wp_trash_meta_status', true ) )
						$action .= ' approve';
					else
						$action .= ' unapprove';
				}

				echo "<span class='$action'>$sep$link</span>";
			}
			echo '</div>';
		}
	} // END column_default

	public function get_columns()
	{
		$columns = array(
			'author'  => 'Author',
			'comment' => 'Comment',
		);

		return $columns;
	} // END get_columns

	public function single_row( $comment )
	{
		// Prep some stuff so the functions have what they need
		$GLOBALS['comment'] = $comment;
		$this->user_can = current_user_can( 'edit_comment', $comment->comment_ID );
		
		static $row_class = '';
		$row_class = ( '' == $row_class ) ? ' class="alternate"' : '';

		echo '<tr id="' . $this->type . '-feedback-' . absint( $comment->comment_ID ) . '"' . $row_class . '>';
		echo $this->single_row_columns( $comment );
		echo '</tr>';

		// Set the comment global back to the current comment
		$GLOBALS['comment'] = $this->current_comment;
	} // END single_row

	public function display_tablenav( $which )
	{
		if ( 'bottom' == $which )
		{
			return;
		} // END if

		$add_chart_url = wp_nonce_url( admin_url( 'admin-ajax.php?action=go_datamodule_add_chart&parent_id=' . absint( $this->current_post->ID ) ), 'go_datamodule_add_chart' );

		$count = count( $this->items );
		$text  = ( 1 == $count ) ? $count . ' chart associated with this post.' : $count . ' charts associated with this post.';
		?>
		<div class="tablenav <?php echo esc_attr( $which ); ?>" style="min-height: 43px;">
			<div class="alignleft"><p><?php echo $text; ?></p></div>
			<div class="alignright">
				<p><a href="<?php echo $add_chart_url; ?>" title="Add Chart" class="button add-chart" target="_blank">Add Chart</a></p>
			</div>
			<br class="clear" />
		</div>
		<?php
	} // END display_tablenav

	public function prepare_items()
	{
		global $wpdb;

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

	public function get_table_classes()
	{
		$classes = parent::get_table_classes();
		$classes[] = 'comments-box';
		$classes[] = 'bsocial-comments-feedback-list';
		$classes[] = $type;
		return $classes;
	}

	public function custom_display()
	{
		?>
		<table class="<?php echo esc_attr( implode( ' ', $this->get_table_classes() ) ); ?>">
			<?php
			if ( 0 == count( $this->items ) )
			{
				?>
				<tbody id="the-feedback-list none">
					There are currently no <?php echo esc_html( $type ); ?>s for this comment.
				</tbody>
				<?php
			} // END if
			else
			{
				?>
				<tbody id="the-feedback-list">
					<?php $this->display_rows_or_placeholder(); ?>
				</tbody>
				<?php
			} // END else
			?>
		</table>
		<?php
	} // END custom_display
}// END bSocial_Comments_Feedback_Table
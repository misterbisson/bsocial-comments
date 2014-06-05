<?php
foreach ( $this->featured_comments as $comment_post )
{
	?>
	<div class="featured-comment">
		<?php echo get_comment_text( $comment_post->comment->comment_ID ); ?>
		 - <a href="<?php echo get_edit_comment_link( $comment_post->comment->comment_ID ); ?>" title="Edit">Edit</a>
		 | <a href="<?php echo get_comment_link( $comment_post->comment->comment_ID ); ?>" title="View">View</a>
	</div>
	<?php
} // END foreach

<?php
foreach ( $this->featured_comments as $comment_post )
{
	?>
	<div class="featured-comment" data-comment-id="<?php echo absint( $comment_post->comment->comment_ID ); ?>">
		<?php echo $this->get_featured_comment_text( $comment_post->comment->comment_ID ); ?>
		 - <a href="<?php echo get_edit_comment_link( $comment_post->comment->comment_ID ); ?>" title="Edit">Edit</a>
		 | <a href="<?php echo get_comment_link( $comment_post->comment->comment_ID ); ?>" title="View">View</a>
		 | <?php echo $this->get_feature_link( $comment_post->comment->comment_ID ); ?>
	</div>
	<?php
} // END foreach

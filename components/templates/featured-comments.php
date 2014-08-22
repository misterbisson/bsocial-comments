<?php
foreach ( $this->featured_comments as $comment_post )
{
	// VIP is caching the earlier query which means comments that are unfeatured might still show up here for quite awhile
	// This is only a partial fix but it's an easy one
	if ( ! $this->is_featured( $comment_post->comment->comment_ID ) )
	{
		continue;
	} // END if
	?>
	<div class="featured-comment" data-comment-id="<?php echo absint( $comment_post->comment->comment_ID ); ?>">
		<?php echo $this->get_featured_comment_text( $comment_post->comment->comment_ID ); ?>
		 - <a href="<?php echo get_edit_comment_link( $comment_post->comment->comment_ID ); ?>" title="Edit">Edit</a>
		 | <a href="<?php echo get_comment_link( $comment_post->comment->comment_ID ); ?>" title="View">View</a>
		 | <?php echo $this->get_feature_link( $comment_post->comment->comment_ID ); ?>
	</div>
	<?php
} // END foreach

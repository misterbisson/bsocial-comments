<div id="bsocial-comments-featured-meta-box">
	<?php echo get_the_title( $post->ID ); ?>
	 - <a href="<?php echo get_edit_post_link( $post->ID ); ?>" title="Edit">Edit</a>
</div>
<?php
do_action( 'bsocial_comments_featured_comment_meta_box', $post );

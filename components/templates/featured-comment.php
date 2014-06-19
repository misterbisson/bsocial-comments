<?php comment_text( $comment->comment_ID ); ?>
<a href="<?php echo get_edit_comment_link( $comment->comment_ID ); ?>" title="Edit">Edit</a>
 | <a href="<?php echo get_comment_link( $comment->comment_ID ); ?>" title="View">View</a>
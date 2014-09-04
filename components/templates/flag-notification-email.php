<?php echo esc_html( $subject ); ?>


<?php echo esc_html( $feedback->comment_author ); ?> flagged a comment on the post "<?php echo get_the_title( $post->ID ); ?>"

Reason:
<?php echo esc_html( $feedback->comment_content ); ?>


Flagged comment:
<?php echo wp_kses( get_comment_text( $feedback->comment_parent ) ); ?>


View flagged comment:
<?php echo esc_url_raw( get_comment_link( $feedback->comment_parent ) ); ?>


Trash it: <?php echo esc_url_raw( bsocial_comments()->get_status_url( $feedback->comment_parent, 'trash' ) ); ?>

Spam it: <?php echo esc_url_raw( bsocial_comments()->get_status_url( $feedback->comment_parent, 'spam' ) ); ?>


More info on <?php echo esc_html( $feedback->comment_author ); ?>

IP: <?php echo esc_html( $feedback->comment_author_IP ); ?>, <?php echo esc_html( gethostbyaddr( $feedback->comment_author_IP ) ); ?>
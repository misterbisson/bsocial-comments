<?php echo esc_html( $subject ); ?>


<?php echo esc_html( $feedback->comment_author ); ?> flagged a comment on the post "<?php echo get_the_title( $post->ID ); ?>"

Reason:
<?php echo esc_html( $feedback->comment_content ); ?>


Flagged comment:
<?php echo wp_kses( get_comment_text( $feedback->comment_parent ), wp_kses_allowed_html() ); ?>


View flagged comment:
<?php echo esc_url_raw( get_comment_link( $feedback->comment_parent ) );
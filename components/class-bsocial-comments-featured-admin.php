<?php
class bSocial_Comments_Featured_Admin extends bSocial_Comments_Featured
{
	public function __construct()
	{
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'wp_ajax_bsocial_feature_comment', array( $this, 'ajax' ) );
		
		add_filter( 'quicktags_settings', array( $this, 'quicktags_settings' ) );
		add_filter( 'comment_row_actions', array( $this, 'comment_row_actions' ), 10, 2 );

		/*
		@TODO:
		+ add metaboxes to the custom post type that show the comment text and connect to both the post and comment
		+ delete the comment meta when the post is deleted
		+ add a metabox to the comment that connects to the featured comment post
		+ add a metabox to the regular post that shows featured comments on the post
		*/
	} // END __construct

	public function admin_enqueue_scripts()
	{
		
	} // END admin_enqueue_scripts

	public function quicktags_settings( $settings )
	{
		switch( $settings['id'] )
		{
			case 'content':
				if ( get_current_screen()->id !== 'comment' )
				{
					return $settings;
				}
				// no break, so it continues to the case below
			case 'replycontent':
				$settings['buttons'] .= ',featuredcomment';
				// enqueue some JS to handle these buttons, yes?
				// similar to AddQuicktagsAndFunctions() in http://plugins.trac.wordpress.org/browser/vipers-video-quicktags/trunk/vipers-video-quicktags.php#L556
				// and now that I've read further, I don't think messing with the button list does anything.
				break;
		} // END switch

		return $settings;
	} // END quicktags_settings

	public function comment_row_actions( $actions, $comment )
	{
		// check permissions against the parent post
		if ( ! current_user_can( 'edit_post', $comment->comment_post_ID ) )
		{
			return $actions;
		}

		// Get feature/unfeature link for the comment
		$actions['feature-comment hide-if-no-js'] = $this->get_feature_comment_link( $comment->comment_ID, 'feature-comment-needs-refresh' );

		// enqueue some JS once
		if ( ! $this->enqueued_admin_js )
		{
			add_action( 'admin_print_footer_scripts', array( $this, 'footer_js' ) );
			$this->enqueued_admin_js = TRUE;
		}

		return $actions;
	} // END comment_row_actions

	public function footer_js()
	{
		// this JS code originated by Mark Jaquith, http://coveredwebservices.com/ , for Gigaom, http://gigaom.com/

		?>
		<script type="text/javascript">
			var bsocial_featuredcomment_nonce = '<?php echo wp_create_nonce( 'bsocial-featuredcomment-save' ); ?>';
			function cwsFeatComLoad() {
				jQuery('#replyrow a.save').click(function() { cwsFeatComLoadLoop( jQuery('#comment_ID').val() ); });
				jQuery('a.feature-comment').removeClass('feature-comment-needs-refresh');
				jQuery('a.feature-comment').click( function(){
					var cmt = jQuery(this);
					var comment_id = cmt.attr('id').replace( /feature-comment-/, '' );
					var ajaxURL = '<?php echo js_escape( admin_url( 'admin-ajax.php' ) ); ?>';
					if ( cmt.hasClass('featured-comment') ) {
						cmt.fadeOut();
						jQuery.post(ajaxURL, {
							action:"bsocial_feature_comment",
							direction:"unfeature",
							comment_id: comment_id,
							cookie: encodeURIComponent(document.cookie),
							_bsocial_featuredcomment_nonce: bsocial_featuredcomment_nonce
						}, function(str){
							cmt.text("Feature").addClass('unfeatured-comment').removeClass('featured-comment').fadeIn();
						});
					} else {
						cmt.fadeOut();
						jQuery.post(ajaxURL, {
							action:"bsocial_feature_comment",
							direction:"feature",
							comment_id: comment_id,
							cookie: encodeURIComponent(document.cookie),
							_bsocial_featuredcomment_nonce: bsocial_featuredcomment_nonce
						}, function(str){
							cmt.text("Unfeature").addClass('featured-comment').removeClass('unfeatured-comment').fadeIn();
						});
					}
					return false;
				});
			}
			function cwsFeatComLoadLoop(comment_id) {
				if ( jQuery( '#comment-' + comment_id + ' a.feature-comment-needs-refresh').text() ) {
					cwsFeatComLoad();
				} else {
					setTimeout("cwsFeatComLoadLoop(" + comment_id + ")", 100);
				}
			}

		jQuery( document ).ready( function(){
			cwsFeatComLoad();
		});
		</script>
		<?php
	} // END footer_js

	public function metabox( $post )
	{
	} // END metabox

	public function register_metaboxes()
	{
		// add metaboxes
		add_meta_box( $id_base, 'Featured Comment', array( $this, 'metabox' ), $this->post_type_name, 'normal', 'high' );
	} // END register_metaboxes

	public function ajax()
	{
		$comment_id = intval( $_REQUEST['comment_id'] );

		if ( ! current_user_can( 'moderate_comments' ) )
		{
			return FALSE;
		}

		if ( ! check_ajax_referer( 'bsocial-featuredcomment-save', '_bsocial_featuredcomment_nonce' ) )
		{
			return;
		} // END if

		if ( get_comment( $comment_id ) )
		{
			if ( 'feature' == $_POST['direction'] )
			{
				$this->feature_comment( $comment_id );
			}
			else
			{
				$this->unfeature_comment( $comment_id );
			}
		} // END if

		die;
	} // END ajax
} // END bSocial_Comments_Featured class
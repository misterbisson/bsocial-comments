var bsocial_comments_featured = {};

(function($) {
	// Watch for people hitting the authenticate button, if necessary this submits the form instead of allowing the click to go through
	bsocial_comments_featured.watch_authentication_buttons = function() {
		$(document).on( 'click', '.' + go_local_keyring.slug + '-form .button', function( e ){
			var form = $(this).closest( '.' + go_local_keyring.slug + '-form' );

			// We only need to submit the form if there's a password field
			if ( $( 'input', form ).length ) {
				e.preventDefault();
				$( form ).submit();
			}
		});
	};

	// Some users will hit return in the password field instead of the button, this handles them as well
	bsocial_comments_featured.watch_authentication_forms = function() {
		$(document).on( 'submit', '.' + go_local_keyring.slug + '-form', function( e ){
			$(this).attr( 'action', $(this).find( '.button' ).attr( 'href' ) );
		});
	};
})(jQuery);

jQuery(function($) {
	bsocial_comments_featured.init();
});
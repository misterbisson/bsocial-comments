var bsocial_comments_featured = {};

(function($) {
	// Start things up...
	bsocial_comments_featured.init = function() {
		// Add a featured comment button to quicktags
		QTags.addButton( 'bsocial-featured-comment', 'featured comment', '[featured_comment]', '[/featured_comment]', 'f', 'Feature specific portion of a comment' );

		bsocial_comments_featured.watch_links();
	};

	// Watch for clicks to the Feature/Unfeature links
	bsocial_comments_featured.watch_links = function() {
		$( document ).on( 'click', 'a.feature-comment', function( event ){
			event.preventDefault();

			var feature_link = $(this);
			var comment_id   = feature_link.closest( 'tr' ).find( '.check-column input' ).attr( 'value' );
			var comment_tr   = $( '#comment-' + comment_id );

			// Fade out the the comment to show something's happening
			comment_tr.fadeOut( 'slow' );

			// Call the AJAX endpoint and try to feature/unfeature the comment
			var request = $.ajax({
				url:      feature_link.attr( 'href' ),
				cache:    false,
				dataType: 'html',
			});

			// On error we just show the comment again
			request.error( function() {
				bsocial_comments_featured.error( comment_tr );
			});

			// On success we do some stuff
			request.success( function( new_link ) {
				if ( ! new_link ) {
					bsocial_comments_featured.error( comment_tr );
					return;
				}

				// Update link
				feature_link.replaceWith( new_link );

				// Fade the comment back in and show success
				bsocial_comments_featured.success( comment_tr );
			});
		});
	};

	// Animate comment back in with failure colors
	bsocial_comments_featured.error = function( comment ) {
		comment.css('background-color', '#ffffe0' );
		comment.fadeIn( 'slow' );
		comment.animate( { 'backgroundColor' : '' }, 400 );
	};

	// Animate comment back in with sucess colors
	bsocial_comments_featured.success = function( comment ) {
		comment.css('background-color', '#cceebb' );
		comment.fadeIn( 'slow' );
		comment.animate( { 'backgroundColor' : '' }, 400 );
	};
})(jQuery);

jQuery(function($) {
	bsocial_comments_featured.init();
});
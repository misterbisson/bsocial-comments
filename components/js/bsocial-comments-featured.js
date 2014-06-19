var bsocial_comments_featured = {};

(function($) {
	'use strict';

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

			if ( 0 != $( 'div#bsuite-fcomment.postbox ' ).length ) {
				bsocial_comments_featured.meta_box_link( $(this) );
			} else {
				bsocial_comments_featured.comments_panel_link( $(this) );
			}
		});
	};

	// Handle clicks to a meta box feature/unfeature link
	bsocial_comments_featured.meta_box_link = function( feature_link ) {
		var $comment_div = feature_link.closest( 'div.featured-comment' );
		var $comment_id  = $comment_div.data( 'comment-id' );

		// Show we're doing something
		feature_link.text( 'Unfeaturing...' );

		// Call the AJAX endpoint and try to feature/unfeature the comment
		var request = $.ajax({
			url:      feature_link.attr( 'href' ),
			cache:    false,
			dataType: 'html'
		});

		// On error we set things back
		request.error( function() {
			feature_link.text( 'Unfeature' );
		});

		// On success we do some stuff
		request.success( function( new_link ) {
			if ( ! new_link ) {
				feature_link.text( 'Unfeature' );
				return;
			}

			// Fade the comment back in and show success
			$comment_div.fadeOut( 'slow' );
		});
	};

	// Handle clicks to a comments panel feature/unfeature link
	bsocial_comments_featured.comments_panel_link = function( feature_link ) {
		var $comment_id   = feature_link.closest( 'tr' ).find( '.check-column input' ).attr( 'value' );
		var $comment_tr   = $( '#comment-' + $comment_id );

		// Fade out the the comment to show something's happening
		$comment_tr.fadeOut( 'slow' );

		// Call the AJAX endpoint and try to feature/unfeature the comment
		var request = $.ajax({
			url:      feature_link.attr( 'href' ),
			cache:    false,
			dataType: 'json'
		});

		// On error we just show the comment again
		request.error( function() {
			bsocial_comments_featured.error( $comment_tr );
		});

		// On success we do some stuff
		request.success( function( data ) {
			if ( ! 'link' in data ) {
				bsocial_comments_featured.error( $comment_tr );
				return;
			}

			// Update link
			feature_link.replaceWith( data.link );

			// Check for comment text
			if ( 'text' in data ) {
				// Remove the existing content
				$( $comment_tr ).find( '.column-comment p' ).remove();
				// Put the new comment text in
				$( $comment_tr ).find( '.column-comment textarea' ).val( data.text );
				$( $comment_tr ).find( '.column-comment .submitted-on' ).after( data.text_with_pees );
			}

			// Fade the comment back in and show success
			bsocial_comments_featured.success( $comment_tr );
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
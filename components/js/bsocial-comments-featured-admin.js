if ( 'undefined' === typeof bsocial_comments_featured_admin ) {
	var bsocial_comments_featured_admin = {
		hook: null
	};
}//end if

(function($) {
	'use strict';

	// Start things up...
	bsocial_comments_featured_admin.init = function() {
		// Add a featured comment button to quicktags when we're editing a comment
		if( 'comment.php' === bsocial_comments_featured_admin.hook || 'edit-comments.php' === bsocial_comments_featured_admin.hook ) {
			//we're on a comment-only page, we can add to all of them
			QTags.addButton( 'bsocial-featured-comment', 'featured comment', '[featured_comment]', '[/featured_comment]', 'f', 'Feature specific portion of a comment');
		} else {
			//we're not on a comment-only page, be picky where we put the button
			QTags.addButton( 'bsocial-featured-comment', 'featured comment', '[featured_comment]', '[/featured_comment]', 'f', 'Feature specific portion of a comment', 200, 'replycontent' );
		}

		bsocial_comments_featured_admin.watch_links();
	};

	// Watch for clicks to the Feature/Unfeature links
	bsocial_comments_featured_admin.watch_links = function() {
		$( document ).on( 'click', 'a.feature-comment', function( event ){
			event.preventDefault();

			// If someone is young and impatient like Jesus they might click on the link again causing duplicate featured comment posts
			$(this).on( 'click', function() { return false; } );

			if ( 0 !== $(this).closest( 'div#bsuite-fcomment.postbox' ).length ) {
				bsocial_comments_featured_admin.meta_box_link( $(this) );
			} else {
				bsocial_comments_featured_admin.comments_panel_link( $(this) );
			}
		});
	};

	// Handle clicks to a meta box feature/unfeature link
	bsocial_comments_featured_admin.meta_box_link = function( feature_link ) {
		var $comment_div = feature_link.closest( 'div.featured-comment' );

		// Show we're doing something
		feature_link.text( 'Unfeaturing...' );

		// Call the AJAX endpoint and try to feature/unfeature the comment
		var request = $.ajax({
			url:      feature_link.attr( 'href' ),
			cache:    false,
			dataType: 'html'
		});

		// On error we set things back
		request.fail( function() {
			feature_link.text( 'Unfeature' );
		});

		// On success we do some stuff
		request.done( function( new_link ) {
			if ( ! new_link ) {
				feature_link.text( 'Unfeature' );
				return;
			}

			// Fade the comment back in and show success
			$comment_div.fadeOut( 'slow' );
		});
	};

	// Handle clicks to a comments panel feature/unfeature link
	bsocial_comments_featured_admin.comments_panel_link = function( feature_link ) {
		var $comment_tr   = feature_link.closest( 'tr' );

		// Fade out the the comment to show something's happening
		$comment_tr.fadeOut( 'slow' );

		// Call the AJAX endpoint and try to feature/unfeature the comment
		var request = $.ajax({
			url:      feature_link.attr( 'href' ),
			cache:    false,
			dataType: 'json'
		});

		// On error we just show the comment again
		request.fail( function() {
			bsocial_comments_featured_admin.error( $comment_tr );
		});

		// On success we do some stuff
		request.done( function( data ) {
			if ( ! 'link' in data ) {
				bsocial_comments_featured_admin.error( $comment_tr );
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
			bsocial_comments_featured_admin.success( $comment_tr );

			// Mark comment as approved if needed
			if ( $( $comment_tr ).hasClass( 'unapproved' ) ) {
				$( $comment_tr ).removeClass( 'unapproved' );
				$( $comment_tr ).addClass( 'approved' );
			}
		});
	};

	// Animate comment back in with failure colors
	bsocial_comments_featured_admin.error = function( comment ) {
		comment.css('background-color', '#ffffe0' );
		comment.fadeIn( 'slow' );
		comment.animate( { 'backgroundColor' : '' }, 400 );
	};

	// Animate comment back in with sucess colors
	bsocial_comments_featured_admin.success = function( comment ) {
		comment.css('background-color', '#cceebb' );
		comment.fadeIn( 'slow' );
		comment.animate( { 'backgroundColor' : '' }, 400 );
	};

	$(function() {
		bsocial_comments_featured_admin.init();
	});
})(jQuery);

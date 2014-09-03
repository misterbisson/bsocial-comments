var bsocial_comments_feedback = {};

(function($) {
	'use strict';

	// Start things up...
	bsocial_comments_feedback.init = function() {
		$( document ).on( 'click', 'table.bsocial-comments-feedback .row-actions .trash a', bsocial_comments_feedback.trash_feedback );
	};

	// Trash feedback
	bsocial_comments_feedback.trash_feedback = function( event ) {
		event.preventDefault();

		bsocial_comments_feedback.$comment_tr = $( this ).closest('tr');
		bsocial_comments_feedback.$comment_id = bsocial_comments_feedback.$comment_tr.attr('id').substr( 14 );
		bsocial_comments_feedback.$type       = bsocial_comments_feedback.$comment_tr.attr('id').substr( 0, 4 );

		// Fade out the the comment to show something's happening
		bsocial_comments_feedback.$comment_tr.fadeOut( 'slow' );

		// Call the AJAX endpoint and try to feature/unfeature the comment
		var request = $.ajax({
			url:      $(this).attr('href'),
			cache:    false,
			dataType: 'html'
		});

		// On error indicate something didn't work
		request.error( function() {
			bsocial_comments_feedback.error();
		});

		// On success we do some stuff
		request.success( function( success ) {
			// Did the feedback actually get removed?
			if ( ! $( success ).find( '#moderated a' ).attr( 'href' ).search( 'action=untrash&ids=' + bsocial_comments_feedback.$comment_id ) ) {
				bsocial_comments_feedback.error();
			} else {
				bsocial_comments_feedback.$comment_tr.css( 'background-color', '#cceebb' );
				bsocial_comments_feedback.$comment_tr.html( '<td colspan="2">The ' + bsocial_comments_feedback.$type  + ' was moved to the trash.</td>');
				bsocial_comments_feedback.$comment_tr.fadeIn( 'slow' );
				bsocial_comments_feedback.$comment_tr.animate( { 'backgroundColor' : '' }, 400 );
			}
		});
	};

	// Fade comment back in with error colors
	bsocial_comments_feedback.error = function() {
		bsocial_comments_feedback.$comment_tr.css( 'background-color', '#ffffe0' );
		bsocial_comments_feedback.$comment_tr.fadeIn( 'slow' );
		bsocial_comments_feedback.$comment_tr.animate( { 'backgroundColor' : '' }, 400 );
	};
})(jQuery);

jQuery(function($) {
	bsocial_comments_feedback.init();
});
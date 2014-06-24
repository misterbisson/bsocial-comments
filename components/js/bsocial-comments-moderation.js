var bsocial_comments_mod = {
	event: {}
};

(function($) {
	'use strict';

	bsocial_comments_mod.init = function() {
		$( document ).on( 'click', '.comment-manage .approve-link a, .comment-manage .spam-link a, .comment-manage .trash-link a', this.event.click_manage );
	};

	bsocial_comments_mod.manage_ajax = function( $el ) {
		var $comment = $el.closest( '.div-comment' );
		var url = $el.attr( 'href' );
		var action = $el.html();

		var message = 'Are you sure you wish to ' + action + ' this comment by ' + $el.closest( '.div-comment' ).find( '.comment-info:first cite' ).text() + '?';

		if ( ! confirm( message ) ) {
			return;
		}

		$comment.block( {
			message: '<span class="go-alerts-block">Saving...</span>',
			css: {
				backgroundColor: 'transparent',
				border: 'none',
				color: '#2A2E33'
			},
			overlayCSS: {
				backgroundColor: '#F6F6F6',
				opacity: 0.85
			}
		} );

		var jqxhr = $.getJSON( url );

		jqxhr.done( function( data ) {
			$comment.unblock();

			if ( data.success ) {
				$el.replaceWith( data.link );
				var opposite_type = $el.data( 'type' );

				$comment.attr( 'data-state', data.state );
			}//end if
		} );
	};

	bsocial_comments_mod.event.click_manage = function( e ) {
		e.preventDefault();
		bsocial_comments_mod.manage_ajax( $( this ) );
	};

	$(function() {
		bsocial_comments_mod.init();
	});
})( jQuery );

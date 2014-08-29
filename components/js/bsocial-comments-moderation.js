var bsocial_comments_moderation = {
	event: {}
};

(function($) {
	'use strict';

	/**
	 * initialize moderation JS
	 */
	bsocial_comments_moderation.init = function() {
		$( document ).on( 'click', '.comment-manage li:not(.edit-link) a', this.event.click_manage );
	};

	/**
	 * ajax update comment status
	 */
	bsocial_comments_moderation.manage_ajax = function( $el ) {
		var $comment = $el.closest( '.div-comment' );
		var url = $el.attr( 'href' );
		var action = $el.html();

		var message = 'Are you sure you wish to ' + action + ' this comment by ' + $el.closest( '.div-comment' ).find( '.comment-info:first cite' ).text() + '?';

		if ( ! confirm( message ) ) {
			return;
		}

		if ( $comment.attr( 'id' ).match( /-featured$/ ) ) {
			$comment = $comment.add( '#' + $comment.attr( 'id' ).replace( '-featured', '' ) );
		} else {
			$comment = $comment.add( '#' + $comment.attr( 'id' ) + '-featured' );
		}//end else

		// block the comment until the state has changed
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

		// when we've received data back from our status update, replace links and update data attributes
		jqxhr.done( function( data ) {
			$comment.unblock();

			if ( data.success ) {
				var $li = $el.closest( 'li' );
				var $els = $comment.find( '.' + $li.attr( 'class' ) ).find( 'a' );

				$els.replaceWith( data.link );
				var opposite_type = $el.data( 'type' );

				if ( 'featured' === data.state ) {
					$comment.addClass( 'featured' );
				} else if ( 'unfeatured' === data.state ) {
					$comment.filter( '[id$="-featured"]' ).closest( '.comment' ).remove();
					$comment.removeClass( 'featured' );

					if ( ! $( '#comments-list-featured ol li' ).length ) {
						$( '#comments-list-featured' ).remove();
						$( 'h3.subheader.featured' ).remove();
					}//end if
				}//end else

				if ( 'spammed' === data.state || 'trashed' === data.state ) {
					$comment.slideUp( 'fast' );
				}//end if

				$comment.attr( 'data-state', data.state );
			}//end if
		} );
	};

	/**
	 * handle click events on status manipulation links on comments
	 */
	bsocial_comments_moderation.event.click_manage = function( e ) {
		e.preventDefault();
		bsocial_comments_moderation.manage_ajax( $( this ) );
	};

	/**
	 * run this file
	 */
	$(function() {
		bsocial_comments_moderation.init();
	});
})( jQuery );

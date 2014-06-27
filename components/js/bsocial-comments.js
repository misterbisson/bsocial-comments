if ( 'undefined' === typeof bsocial_comments ) {
	var bsocial_comments = {};
}//end if

if ( 'undefined' === typeof bsocial_comments.event ) {
	bsocial_comments.event = {};
}//end if

( function( $ ) {
	'use strict';

	/**
	 * initializes the bsocial_comments features
	 */
	bsocial_comments.init = function() {
		if ( ! $( '#comment_post_ID' ).length ) {
			return;
		}//end if

		this.authenticated = $( 'body' ).hasClass( 'logged-in' );
		this.post_id = $( '#comment_post_ID' ).val();

		$( document ).on( 'click', '.comment-fave a', this.event.fave_comment );
		$( document ).on( 'click', '.comment-flag a', this.event.flag_comment );
		$( document ).on( 'click', '.comment-flag-confirm', this.event.confirm_flag_comment );
	};

	/**
	 * handles the interaction with a comment fave link
	 */
	bsocial_comments.fave_comment = function( $link ) {
		var $comment = $link.closest( '.comment' );

		var args = this.generate_ajax_args( $comment, $link, 'fave' );

		if ( ! this.authenticated ) {
			$( document ).trigger( 'bsocial-comments-defer-action-for-auth', [ args, 'fave' ] );
			$comment.find( '.feedback-box:first' ).attr( 'data-type', 'fave-logged-out' ).slideDown( 'fast' );
			return;
		}//end if

		$.ajax( args );
	};

	/**
	 * handles the interaction with a comment flag link
	 */
	bsocial_comments.flag_comment = function( $link ) {
		var $comment = $link.closest( '.comment' );

		if ( ! this.authenticated ) {
			var args = this.generate_ajax_args( $comment, $link, 'flag' );
			$( document ).trigger( 'bsocial-comments-defer-action-for-auth', [ args, 'flag' ] );
			$comment.find( '.feedback-box:first' ).attr( 'data-type', 'flag-logged-out' ).slideDown( 'fast' );
			return;
		}//end if

		$comment.find( '.feedback-box:first' ).attr( 'data-type', 'flag-logged-in' ).slideDown( 'fast' );
	};

	/**
	 * handles the interaction with a comment flag confirmation link
	 */
	bsocial_comments.confirm_flag_comment = function( $link ) {
		var $comment = $link.closest( '.comment' );
		var args = this.generate_ajax_args( $comment, $link, 'flag' );

		if ( ! this.authenticated ) {
			return;
		}//end if

		$.ajax( args );
	};

	/**
	 * generates feedback ajax args
	 */
	bsocial_comments.generate_ajax_args = function( $comment, $link, type ) {
		var url = $link.attr( 'href' );

		var state_check = null;
		var type_inverse = null;

		if ( 'flag' === type ) {
			state_check = 'flagged';
			type_inverse = 'unflag';
		} else {
			state_check = 'faved';
			type_inverse = 'unfave';
		}//end else

		var has_state = state_check === $comment.data( 'comment-' + state_check );

		var args = {
			dataType: 'json',
			url: url,
			data: {
				action: 'bsocial_comments_comment_feedback',
				comment_id: $comment.closest( '.comment' ).data( 'comment-id' ),
				direction: has_state ? type_inverse : type
			},
			success: function( data ) {
				if ( ! data.success ) {
					return;
				}//end if

				$comment.closest( '.comment' ).attr( 'data-comment-' + state_check, state_check === data.state ? 'true' : 'false' );
			}
		};

		return args;
	};

	bsocial_comments.event.fave_comment = function( e ) {
		e.preventDefault();

		bsocial_comments.fave_comment( $( this ) );
	};

	bsocial_comments.event.flag_comment = function( e ) {
		e.preventDefault();

		bsocial_comments.flag_comment( $( this ) );
	};

	bsocial_comments.event.confirm_flag_comment = function( e ) {
		e.preventDefault();

		bsocial_comments.confirm_flag_comment( $( this ) );
	};

	$( function() {
		bsocial_comments.init();
	});
})( jQuery );

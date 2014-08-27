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

		this.states_requested = false;
		this.authenticated_request_filters = [];
		this.authenticated = $( 'body' ).hasClass( 'logged-in' );
		this.post_id = $( '#comment_post_ID' ).val();

		if ( this.authenticated ) {
			this.request_comment_states();
		}

		$( document ).on( 'click', '.comment-fave a', this.event.fave_comment );
		$( document ).on( 'click', '.comment-flag a', this.event.flag_comment );
		$( document ).on( 'click', '.comment-flag-confirm', this.event.confirm_flag_comment );
		$( document ).on( 'click', '.flag-logged-in .cancel', this.event.cancel_confirm_flag_comment );
	};

	/**
	 * retrieves comment states via Ajax
	 */
	bsocial_comments.request_comment_states = function() {
		// track that states have been requested
		this.states_requested = true;

		var args = {
			dataType: 'json',
			url: this.endpoint,
			data: {
				action: 'bsocial_comments_feedback_states_for_user',
				post_id: this.post_id,
				user: {
					user_id: this.logged_in_as
				},
				nonce: this.nonce
			},
			success: function( response ) {
				if ( ! response.success ) {
					return;
				}//end if

				bsocial_comments.parse_comment_states( response.data );
			}
		};

		this.authenticated_request( args );
	};

	/**
	 * parses comment state JSON and assigns classes to comments where appropriate
	 */
	bsocial_comments.parse_comment_states = function( states ) {
		for ( var comment_id in states ) {
			var $comment = $( '.comment[data-comment-id="' + comment_id + '"]' );

			if ( 'undefined' !== typeof states[ comment_id ].flagged && states[ comment_id ].flagged ) {
				$comment.attr( 'data-comment-flag', 'flag' );
			}//end if

			if ( 'undefined' !== typeof states[ comment_id ].faved && states[ comment_id ].faved ) {
				$comment.attr( 'data-comment-fave', 'fave' );
			}//end if
		}//end for
	};

	/**
	 * handles the interaction with a comment fave link
	 */
	bsocial_comments.fave_comment = function( $link ) {
		var $comment = $link.closest( '.comment' );

		var args = this.generate_ajax_args( $comment, $link, 'fave' );

		if ( ! this.authenticated ) {
			$( document ).trigger( 'bsocial-comments-defer-action-for-auth', [ args, 'fave' ] );
			$( document ).trigger( 'bsocial-comments-fave-not-authenticated', $comment );
			return;
		}//end if

		this.authenticated_request( args );

		// let's give immediate feedback so we don't have to wait for the ajax round-trip
		this.adjust_fave_count( $comment, 'fave' === $comment.attr( 'data-comment-fave' ) ? 'decrement' : 'increment' );
	};

	/**
	 * increment/decrement fave count
	 */
	bsocial_comments.adjust_fave_count = function( $comment, which ) {
		which = 'decrement' === which ? 'decrement' : 'increment';

		var $fave_count = $comment.find( '.fave-count:first' );
		var count = parseInt( $fave_count.attr( 'data-count' ), 10 );

		if ( 'decrement' === which && count > 0 ) {
			count--;
		} else {
			count++;
		}//end if

		$fave_count.html( count ).attr( 'data-count', count );

		if ( 'fave' === $comment.attr( 'data-comment-fave' ) ) {
			$comment.attr( 'data-comment-fave', 'unfave' );
		} else {
			$comment.attr( 'data-comment-fave', 'fave' );
		}//end else
	};

	/**
	 * handles the interaction with a comment flag link
	 */
	bsocial_comments.flag_comment = function( $link ) {
		var $comment = $link.closest( '.comment' );

		if ( ! this.authenticated ) {
			var args = this.generate_ajax_args( $comment, $link, 'flag' );
			$( document ).trigger( 'bsocial-comments-defer-action-for-auth', [ args, 'flag' ] );
			$( document ).trigger( 'bsocial-comments-flag-not-authenticated', $comment );
			return;
		}//end if
		else {
			if ( 'flag' !== $comment.attr( 'data-comment-flag' ) ) {
				$( document ).trigger( 'bsocial-comments-flag-is-authenticated', $comment );
			} else {
				this.confirm_flag_comment( $link );
			}//end else
		}//end else
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

		this.authenticated_request( args );

		// let's give immediate feedback so we don't have to wait for the ajax round-trip
		if ( 'flag' === $comment.attr( 'data-comment-flag' ) ) {
			$comment.attr( 'data-comment-flag', 'unflag' );
		} else {
			$comment.attr( 'data-comment-flag', 'flag' );
		}//end else

		$( document ).trigger( 'bsocial-comments-flag-confirmed', $comment );
	};

	/**
	 * generates feedback ajax args
	 */
	bsocial_comments.generate_ajax_args = function( $comment, $link, type ) {
		var url = $link.attr( 'href' );

		var type_inverse = null;

		if ( 'flag' === type ) {
			type_inverse = 'unflag';
		} else {
			type_inverse = 'unfave';
		}//end else

		var has_state = false;

		if ( 'undefined' !== typeof $comment.attr( 'comment-' + type ) ) {
			has_state = type === $comment.attr( 'comment-' + type );
		}//end else

		var args = {
			dataType: 'json',
			url: url,
			data: {
				action: 'bsocial_comments_comment_feedback',
				comment_id: $comment.closest( '.comment' ).data( 'comment-id' ),
				post_id: this.post_id,
				direction: has_state ? type_inverse : type,
				user: {}
			}
		};

		return args;
	};

	bsocial_comments.filter_authenticated_request_args = function( filter, priority ) {
		if ( 'undefined' === typeof this.authenticated_request_filters[ priority ] ) {
			this.authenticated_request_filters[ priority ] = [];
		}//end if

		this.authenticated_request_filters[ priority ].push( filter );
	};

	bsocial_comments.authenticated_request = function( args ) {
		// this.logged_in_as comes from wp_localize_script
		if ( this.logged_in_as ) {
			args.data.user = {
				user_id: parseInt( this.logged_in_as, 10 )
			};
		}//end else

		for ( var priority in this.authenticated_request_filters ) {
			for ( var i in this.authenticated_request_filters[ priority ] ) {
				args = this.authenticated_request_filters[ priority ][ i ]( args );
			}//end for
		}//end for

		$.ajax( args );
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

	bsocial_comments.event.cancel_confirm_flag_comment = function( e ) {
		e.preventDefault();

		$( this ).closest( '.feedback-box' ).slideUp( 'fast' );
	};

	$( function() {
		bsocial_comments.init();
	});
})( jQuery );

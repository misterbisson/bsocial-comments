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

		this.authenticated_request_filters = [];
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

		this.authenticated_request( args );
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

		this.authenticated_request( args );
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

		var has_state = type === $comment.data( 'comment-' + type );

		var args = {
			dataType: 'json',
			url: url,
			data: {
				action: 'bsocial_comments_comment_feedback',
				comment_id: $comment.closest( '.comment' ).data( 'comment-id' ),
				post_id: this.post_id,
				direction: has_state ? type_inverse : type
			},
			success: function( response ) {
				if ( ! response.success ) {
					return;
				}//end if

				$comment.attr( 'data-comment-' + type, response.data.state );

				if ( 'fave' === type ) {
					var $fave_count = $comment.find( '.fave-count:first' );
					var count = parseInt( $fave_count.data( 'count' ), 10 );

					if ( 'fave' === response.data.state ) {
						count++;
					} else if ( count > 0 ) {
						count--;
					}//end if

					$fave_count.html( count ).data( 'count', count ).attr( 'data-count', count );
				}//end if
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
			args.data.user_id = this.logged_in_as;
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

	$( function() {
		bsocial_comments.init();
	});
})( jQuery );

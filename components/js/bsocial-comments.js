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
		$( document ).on( 'submit', '.flag-logged-in form', this.event.confirm_flag_comment );
		$( document ).on( 'click', '.flag-logged-in .cancel', this.event.cancel_confirm_flag_comment );
		$( document ).on( 'change', '.reason', this.event.select_reason );
		$( document ).on( 'keyup', '.reason-description', this.event.select_reason );
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

			// if there is comment data for a comment that doesn't exist on the page, don't bother attempting to set the flag/fave states
			if ( ! $comment.length ) {
				continue;
			}//end if

			if ( 'undefined' !== typeof states[ comment_id ].flagged && states[ comment_id ].flagged ) {
				this.set_flag_state( comment_id, 'flag' );
			}//end if

			if ( 'undefined' !== typeof states[ comment_id ].faved && states[ comment_id ].faved ) {
				this.set_fave_state( comment_id, 'fave' );
			}//end if
		}//end for

		$( document ).trigger( 'bsocial-comments-parsed-comment-states' );
	};

	/**
	 * sets a comments fave state
	 */
	bsocial_comments.set_fave_state = function( comment_id, state ) {
		var $comment = $( '.comment[data-comment-id="' + comment_id + '"]' );

		if ( ! $comment.length ) {
			return;
		}//end if

		var $fave_link = $comment.find( ' > .div-comment .comment-fave a' );
		var href = $fave_link.attr( 'href' );

		$comment.attr( 'data-comment-fave', state );
		$comment.removeClass( 'faving' );

		if ( 'fave' === state ) {
			$fave_link.attr( 'title', 'Unfave this comment' );
			$fave_link.attr( 'href', href.replace( 'direction=fave', 'direction=unfave' ) );
		} else {
			$fave_link.attr( 'title', 'Fave this comment' );
			$fave_link.attr( 'href', href.replace( 'direction=unfave', 'direction=fave' ) );
		}//end else
	};

	/**
	 * sets a comments flag state
	 */
	bsocial_comments.set_flag_state = function( comment_id, state ) {
		var $comment = $( '.comment[data-comment-id="' + comment_id + '"]' );

		if ( ! $comment.length ) {
			return;
		}//end if

		var $flag_link = $comment.children( '.div-comment' ).find( '.comment-flag a' );
		var href = $flag_link.attr( 'href' );

		$comment.attr( 'data-comment-flag', state );
		$comment.removeClass( 'flagging' );

		if ( 'flag' === state ) {
			$flag_link.attr( 'title', 'Unflag this comment' );
			$flag_link.attr( 'href', href.replace( 'direction=flag', 'direction=unflag' ) );
		} else {
			$flag_link.attr( 'title', 'Flag this comment' );
			$flag_link.attr( 'href', href.replace( 'direction=unflag', 'direction=flag' ) );
		}//end else
	};

	/**
	 * handles the interaction with a comment fave link
	 */
	bsocial_comments.fave_comment = function( $link ) {
		var $comment = $link.closest( '.comment' );
		var $feedback_box = $comment.find( '.feedback-box .fave-logged-out' ).filter( ':visible' );

		if ( $feedback_box.length ) {
			$comment.removeClass( 'faving flagging' );
			$comment.find( '.feedback-box' ).slideUp( 'fast' );
			return;
		}//end if

		var args = this.generate_ajax_args( $comment, $link, 'fave' );

		if ( ! this.authenticated ) {
			$comment.addClass( 'faving' ).removeClass( 'flagging' );
			$comment.find( '.feedback-box:first' ).attr( 'data-type', 'fave-logged-out' ).slideDown( 'fast' );
			$( document ).trigger( 'bsocial-comments-defer-action-for-auth', [ args, 'fave' ] );
			$( document ).trigger( 'bsocial-comments-fave-not-authenticated', [ $comment ] );
			return;
		}//end if

		$comment.removeClass( 'faving flagging' );
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
			this.set_fave_state( $comment.data( 'comment-id' ), 'unfave' );
		} else {
			this.set_fave_state( $comment.data( 'comment-id' ), 'fave' );
		}//end else
	};

	/**
	 * handles the interaction with a comment flag link
	 */
	bsocial_comments.flag_comment = function( $link ) {
		var $comment = $link.closest( '.comment' );
		var $feedback_box = $comment.find( '.feedback-box' ).filter( ':visible' );

		if ( $feedback_box.find( '.flag-logged-in' ).filter( ':visible' ).length ) {
			$feedback_box.find( '.flag-logged-in .cancel' ).click();
			return;
		}//end if
		else if ( $feedback_box.find( '.flag-logged-out' ).filter( ':visible' ).length ) {
			$comment.removeClass( 'faving flagging' );
			$feedback_box.slideUp( 'fast' );
			return;
		}//end else if

		if ( ! this.authenticated ) {
			$comment.addClass( 'flagging' ).removeClass( 'faving' );
			$comment.find( '.feedback-box:first' ).attr( 'data-type', 'flag-logged-out' ).slideDown( 'fast' );
			var args = this.generate_ajax_args( $comment, $link, 'flag' );
			$( document ).trigger( 'bsocial-comments-defer-action-for-auth', [ args, 'flag' ] );
			$( document ).trigger( 'bsocial-comments-flag-not-authenticated', [ $comment ] );
			return;
		}//end if
		else {
			if ( 'flag' !== $comment.attr( 'data-comment-flag' ) ) {
				$comment.addClass( 'flagging' ).removeClass( 'faving' );
				$comment.find( '.feedback-box:first' ).attr( 'data-type', 'flag-logged-in' ).slideDown( 'fast' );
				$( document ).trigger( 'bsocial-comments-flag-is-authenticated', [ $comment ] );
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
		var $form = $comment.find( '> .feedback-box .flag-logged-in' );
		if( 'other' !== $form.find( '.reason:checked' ).data( 'reason-type' ) ) {
			//clear the textarea if we're not submitting "other"
			$form.find( 'textarea' ).val( '' );
		}//end if

		var args = this.generate_ajax_args( $comment, $link, 'flag' );

		if ( ! this.authenticated ) {
			return;
		}//end if

		// if we are flagging a comment AND it is the "Other" option, make sure a description has been entered
		if ( 'flag' !== $comment.attr( 'data-comment-flag' ) && $form.filter( '[data-selected-reason="other"]' ).length ) {
			if ( '' === $.trim( $form.find( '.reason-description' ).val() ) ) {
				$form.find( '.required' ).show();
				return;
			}//end if
		}//end if

		$comment.removeClass( 'faving flagging' );

		this.authenticated_request( args );

		$form.find( '.reason:checked' ).prop( false );
		$form.find( '.reason-description' ).val( '' );

		// let's give immediate feedback so we don't have to wait for the ajax round-trip
		$comment.find( '.feedback-box:first' ).attr( 'data-type', '' ).slideUp( 'fast' );
		if ( 'flag' === $comment.attr( 'data-comment-flag' ) ) {
			this.set_flag_state( $comment.attr( 'data-comment-id' ), 'unflag' );
		} else {
			this.set_flag_state( $comment.attr( 'data-comment-id' ), 'flag' );
		}//end else
		bsocial_comments.form_reset( $form );

		$( document ).trigger( 'bsocial-comments-flag-confirmed', [ $comment ] );
	};

	bsocial_comments.form_reset = function( $form ) {
		//reset the form ( deselect radio and clear textarea )
		$form.find( 'form' )[0].reset();
		//have to hide the text area - and that is based on the data-selected-reason atribute
		$form.find( 'form' ).attr( 'data-selected-reason', '' );
		//re-disable the button see https://github.com/GigaOM/gigaom/issues/5267
		$form.find( '.comment-flag-confirm' ).prop( { 'disabled': true } );
	};

	/**
	 * generates feedback ajax args
	 */
	bsocial_comments.generate_ajax_args = function( $comment, $trigger, type ) {
		if ( $trigger.is( 'button' ) ) {
			var url = $trigger.closest( 'form' ).attr( 'action' );
		} else {
			var url = $trigger.attr( 'href' );
		}

		var type_inverse = null;

		if ( 'flag' === type ) {
			type_inverse = 'unflag';
		} else {
			type_inverse = 'unfave';
		}//end else

		var has_state = false;

		if ( 'undefined' !== typeof $comment.attr( 'data-comment-' + type ) ) {
			has_state = type === $comment.attr( 'data-comment-' + type );
		}//end else

		var args = {
			dataType: 'json',
			url: url,
			data: {
				action: 'bsocial_comments_comment_feedback',
				comment_id: $comment.data( 'comment-id' ),
				post_id: this.post_id,
				direction: has_state ? type_inverse : type,
				user: {}
			}
		};

		if ( 'flag' === type && 'flag' === args.data.direction ) {
			args.data.flag_type = $comment.find( '> .feedback-box .reason:checked' ).val();
			args.data.flag_text = $comment.find( '> .feedback-box .reason-description' ).val();
		}//end if

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

	bsocial_comments.event.select_reason = function( e ) {
		e.preventDefault();
		var $el = $( this );

		var $flag_button = $el.closest( 'form' ).find( '.comment-flag-confirm' );

		if ( $el.is( ':radio' ) ) {
			if ( 'other' === $el.data( 'reason-type' ) && 0 >= $el.closest( 'form' ).find( '.reason-description' ).val().length ) {
				$flag_button.prop( { 'disabled': true } );
			}else {
				$flag_button.prop( { 'disabled': false } );
			}

			$el.closest( 'form' ).attr( 'data-selected-reason', $el.data( 'reason-type' ) );
		} else {
			//if they've typed in the reason textarea, let them pass
			if ( $.trim( $el.val() ).length ) {
				$flag_button.prop( { 'disabled': false } );
			} else {
				$flag_button.prop( { 'disabled': true } );
			}
		}
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

		var $el = $( this );
		var $form = $( this ).closest( '.feedback-box .flag-logged-in' );

		bsocial_comments.form_reset( $form );

		$el.closest( '.comment' ).removeClass( 'faving flagging' );
		$el.closest( '.feedback-box' ).slideUp( 'fast' );
	};

	$( function() {
		bsocial_comments.init();
	});
})( jQuery );

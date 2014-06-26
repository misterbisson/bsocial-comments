if ( 'undefined' === typeof bsocial_comments ) {
	var bsocial_comments = {};
}//end if

if ( 'undefined' === typeof bsocial_comments.event ) {
	bsocial_comments.event = {};
}//end if

( function( $ ) {
	'use strict';

	bsocial_comments.authenticated = false;
	bsocial_comments.delayed_action = {};

	/**
	 * initializes the bsocial_comments features
	 */
	bsocial_comments.init = function() {
		if ( ! $( '#comment_post_ID' ).length ) {
			return;
		}//end if

		this.states_requested = false;
		this.post_id = $( '#comment_post_ID' ).val();

		$( document ).on( 'go-auth-success', this.event.auth_success );

		$( document ).on( 'click', '.comment-like a', this.event.fave_comment );
		$( document ).on( 'click', '.comment-flag a', this.event.flag_comment );
		$( document ).on( 'click', '.comment-flag-confirm', this.event.confirm_flag_comment );
	};

	/**
	 * handles the interaction with a comment fave link
	 */
	bsocial_comments.fave_comment = function( $fave_link ) {
		var $comment = $fave_link.closest( '.comment' ).find( '.div-comment' );
		var url = $fave_link.attr( 'href' );

		var args = {
			dataType: 'json',
			url: url,
			data: {
				action: 'bsocial_comments_fave_comment',
				comment_id: $comment.closest( '.comment' ).data( 'comment-id' ),
				check_if: 'faved'
			},
			success: function( data ) {
				if ( ! data.success ) {
					return;
				}//end if

				$comment.closest( '.comment' ).attr( 'data-comment-faved', 'faved' == data.state ? 'true' : 'false' );
			}
		};

		if ( ! this.authenticated ) {
			bsocial_comments.delayed_action = args;
			this.auth_on_comment( $comment );
			return;
		}//end if
	};

	/**
	 * handles the interaction with a comment flag link
	 */
	bsocial_comments.flag_comment = function( $flag_link ) {
		var $comment = $flag_link.closest( '.comment' ).find( '.div-comment' );
		var url = $flag_link.attr( 'href' );

		if ( ! this.authenticated ) {
			this.auth_on_comment( $comment );
			return;
		}//end if

		var $confirm = $( '<div class="feedback-box" />' );

		$confirm.hide();
		$comment.after( $confirm );
		$confirm.slideDown( 'fast' );
	};

	/**
	 * handles the interaction with a comment flag confirmation link
	 */
	bsocial_comments.confirm_flag_comment = function( $confirm_link ) {
		var $comment = $confirm_link.closest( '.comment' ).find( '.div-comment' );
		var url = $confirm_link.attr( 'href' );

		var args = {
			dataType: 'json',
			url: url,
			data: {
				action: 'bsocial_comments_flag_comment',
				comment_id: $comment.closest( '.comment' ).data( 'comment-id' ),
				check_if: 'flagged'
			},
			success: function( data ) {
				if ( ! data.success ) {
					return;
				}//end if

				$comment.closest( '.comment' ).attr( 'data-comment-flagged', 'flagged' == data.state ? 'true' : 'false' );
			}
		};

		if ( ! this.authenticated ) {
			bsocial_comments.delayed_action = args;
			this.auth_on_comment( $comment );
			return;
		}//end if
	};

	/**
	 * slides down an auth box under a comment
	 */
	bsocial_comments.auth_on_comment = function( $comment ) {
		var $social = $('.credentials.credential-popup-logins:first').clone();

		var $auth = $( '<div class="feedback-box credential-social feedback-credentials" />' );

		$auth.append( $social );
		$auth.hide();
		$comment.after( $auth );
		$auth.slideDown( 'fast' );
	};

	/**
	 * parses comment state JSON and assigns classes to comments where appropriate
	 */
	bsocial_comments.parse_comment_states = function( states ) {
		for ( var i in states ) {
			if ( 'undefined' === typeof states[ i ].comment_id ) {
				continue;
			}//end if

			var comment_id = states[ i ].comment_id;
			var $comment = $( '.comment[data-comment-id="' + comment_id + '"]' );

			if ( 'undefined' !== typeof states[ i ].flagged && states[ i ].flagged ) {
				$comment.attr( 'data-comment-flagged', 'true' );
			}//end if

			if ( 'undefined' !== typeof states[ i ].favorited && states[ i ].favorited ) {
				$comment.attr( 'data-comment-faved', 'true' );
				var $like_count = $comment.find( '.like-count:first' );

				var count = parseInt( $like_count.data( 'count' ), 10 ) + 1;

				$like_count.data( 'count', count );
				$like_count.html( count );
			}//end if
		}//end for
	};

	/**
	 * retrieves comment states via Ajax
	 */
	bsocial_comments.request_comment_states = function( user_data ) {
		this.states_requested = true;

		var jqxhr = $.getJSON(
			this.endpoint,
			{
				action: 'bsocial_comments_states_for_user',
				post_id: this.post_id,
				user: user_data.logged_in_as,
				nonce: this.nonce
			}
		);

		jqxhr.done( function( data ) {
			bsocial_comments.parse_comment_states( data );
		});
	};

	/**
	 * runs a stored ajax action
	 */
	bsocial_comments.run_delayed_action = function() {
		var comment_id = this.delayed_action.data.comment_id;
		var check_if = this.delayed_action.data.check_if;

		if ( 'true' == $( '#comment-' + comment_id ).data( 'comment-' + check_if ) ) {
			this.delayed_action = {};
			return;
		}//end if

		this.delayed_actions.user = go_remote_identity.logged_in_as;

		$.ajax( this.delayed_action );
	};

	/**
	 * event to handle the go-auth-success trigger
	 */
	bsocial_comments.event.auth_success = function( e, user_data ) {
		bsocial_comments.authenticated = true;
		bsocial_comments.request_comment_states( user_data );

		if ( 'undefined' === typeof bsocial_comments.delayed_action.url ) {
			return;
		}//end if

		bsocial_comments.run_delayed_action();
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

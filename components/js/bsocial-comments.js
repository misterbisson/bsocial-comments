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
		this.post_id = $( '#comment_post_ID' ).val();

		$( document ).on( 'go-auth-success', this.event.auth_success );
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
				$comment.addClass( 'flagged' );
			}//end if

			if ( 'undefined' !== typeof states[ i ].favorited && states[ i ].favorited ) {
				$comment.addClass( 'favorited' );
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
				action: 'bsocial_comment_states_for_user',
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
	 * event to handle the go-auth-success trigger
	 */
	bsocial_comments.event.auth_success = function( e, user_data ) {
		bsocial_comments.request_comment_states( user_data );
	};

	$( function() {
		bsocial_comments.init();
	});
})( jQuery );

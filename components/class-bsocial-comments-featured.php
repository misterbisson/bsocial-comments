<?php

class bSocial_Comments_Featured
{
	// don't mess with these
	public $id_base = 'bsuite-fcomment';
	public $post_type_name = 'bsuite-fcomment';
	public $meta_key = 'bsuite-fcomment';
	public $tag_regex = '/\[\/?featured_?comment\]/i'; // just match the single tag to make it easy to remove
	public $wrapper_regex = '/\[featured_?comment\](.*?)\[\/?featured_?comment\]/is'; // match the content inside the tags
	public $featured_comments = array();
	public $admin = FALSE;

	public function __construct()
	{
		add_action( 'init', array( $this, 'init' ), 11 );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'edit_comment', array( $this, 'edit_comment' ), 5 );
		add_action( 'delete_comment', array( $this, 'unfeature_comment' ) );
		add_action( 'wp_ajax_bsocial_feature_comment', array( $this, 'ajax_feature_comment' ) );

		add_filter( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
		add_filter( 'post_class', array( $this, 'filter_post_class' ) );
		add_filter( 'get_comment_text', array( $this, 'filter_get_comment_text' ) );
		add_filter( 'the_author', array( $this, 'filter_the_author' ) );
		add_filter( 'the_author_posts_link', array( $this, 'filter_the_author_posts_link' ) );
		add_filter( 'post_type_link', array( $this, 'post_type_link' ), 11, 2 );
		add_filter( 'bsocial_comments_is_featured', array( $this, 'bsocial_comments_is_featured' ), 10, 2 );
		add_filter( 'transition_comment_status', array( $this, 'transition_comment_status' ), 10, 3 );
	}// END __construct

	/**
	 * Activate featured comments
	 */
	public function init()
	{
		$this->register_post_type();
	}// END init

	/**
	 * Activate admin functionality
	 */
	public function admin_init()
	{
		$this->admin();
	}// END admin_init

	/**
	 * Admin object accessor
	 */
	public function admin()
	{
		if ( ! $this->admin )
		{
			require_once __DIR__ . '/class-bsocial-comments-featured-admin.php';
			$this->admin = new bSocial_Comments_Featured_Admin;
		}

		return $this->admin;
	}// END admin

	/**
	 * Register the featured comment post_type
	 */
	public function register_post_type()
	{
		// Only allow taxonomies that are associated with posts that can have comments
		$taxonomies = array();
		$post_types = get_post_types( array( 'public' => TRUE ), 'objects' );

		foreach ( $post_types as $post_type => $object )
		{
			if (
				 ! empty( $object->taxonomies )
				&& post_type_supports( $post_type, 'comments' )
			)
			{
				$taxonomies = array_merge( $taxonomies, $object->taxonomies );
			}// END if
		}// END foreach

		$post_type_config = array(
			'labels' => array(
				'name' => 'Featured Comments',
				'singular_name' => 'Featured Comment',
			),
			'supports' => array(
				'title',
				'author',
			),
			'register_meta_box_cb' => array( $this, 'register_metaboxes' ),
			'public' => TRUE,
			'show_in_menu' => FALSE,
			'has_archive' => bsocial_comments()->options()->featured_comments->has_archive,
			'rewrite' => array(
				'slug' => bsocial_comments()->options()->featured_comments->rewrite_slug,
				'with_front' => FALSE,
			),
			'taxonomies' => $taxonomies,
		);

		register_post_type( $this->post_type_name, $post_type_config );
	}// END register_post_type

	/**
	 * Include featured comments in the post_types if the config is set to do so
	 */
	public function pre_get_posts( $query )
	{
		if (
			   bsocial_comments()->options()->featured_comments->add_to_waterfall
			&& ! is_admin()
			&& $query->is_main_query()
		)
		{
			$post_types = array_merge(
				(array) $query->query_vars['post_type'],
				array( is_singular() && isset( $query->queried_object->post_type ) ? $query->queried_object->post_type : 'post' ),
				array( $this->post_type_name )
			);

			$query->set( 'post_type', $post_types );
		}// END if

		return $query;
	}// END pre_get_posts

	/**
	 * Return the link to the comment for featured commment posts
	 */
	public function post_type_link( $permalink, $post )
	{
		if ( $post->post_type == $this->post_type_name && ( $comment_id = $this->get_post_meta( $post->ID ) ) )
		{
			return get_comment_link( $comment_id );
		}

		return $permalink;
	}// END post_type_link

	/**
	 * Filter out the shortcode unless in the admin panel
	 */
	public function filter_get_comment_text( $content )
	{
		if ( is_admin() )
		{
			// Check if there's any shortcodes if so we'll wrap each enclosed paragraph so the featured portions are more visible
			if ( preg_match( $this->wrapper_regex, $content, $matches ) )
			{
				$chunks = explode( "\n\n", $matches[0] );

				foreach ( $chunks as $chunk )
				{
					$content = str_replace( $chunk, '<span class="bsocial-featured-comment">' . $chunk . '</span>', $content );
				}// END foreach
			}// END if

			return $content;
		}// END if
		else
		{
			return preg_replace( $this->tag_regex, '', $content );
		}//END else
	}// END filter_get_comment_text

	/**
	 * Add a class of post to featured comment posts
	 */
	public function filter_post_class( $classes )
	{
		if ( get_post( get_the_ID() )->post_type == $this->post_type_name )
		{
			$classes[] = 'post';
		}

		return $classes;
	}// END filter_post_class

	/**
	 * Show featured comment posts with the author of the comment instead of the originating post
	 */
	public function filter_the_author( $author_name )
	{
		if ( get_the_ID() && get_post( get_the_ID() )->post_type == $this->post_type_name )
		{
			return get_comment_author( $this->get_post_meta( $post->ID ) );
		}// END if
		else
		{
			return $author_name;
		}//END else
	}// END filter_the_author

	/**
	 * Prevent featured comments from having an author posts link
	 */
	public function filter_the_author_posts_link( $url )
	{
		if ( get_the_ID() && get_post( get_the_ID() )->post_type == $this->post_type_name )
		{
			return '';
		}// END if
		else
		{
			return $url;
		}//END else
	}// END filter_the_author_posts_link

	/**
	 * Returns any text between the [featured_comment]text here[/featured_comment] shortcodes
	 */
	public function get_featured_comment_text( $comment_id = FALSE )
	{
		remove_filter( 'get_comment_text', array( $this, 'filter_get_comment_text' ) );
		$text = $this->_get_featured_comment_text( get_comment_text( $comment_id ) );
		add_filter( 'get_comment_text', array( $this, 'filter_get_comment_text' ) );

		return $text;
	}// END get_featured_comment_text

	/**
	 * Checks content for the [featured_comment][/featured_comment] shortcode
	 */
	public function _get_featured_comment_text( $input )
	{
		$featured_text = $this->get_only_the_featured_comment_text( $input );

		$text = $featured_text ?: $input;

		return wp_trim_words( $text, bsocial_comments()->options()->featured_comments->word_limit, '&hellip;' );
	}// END _get_featured_comment_text

	/**
	 * Retrieves the content for the [featured_comment][/featured_comment] shortcode if there is any
	 */
	public function get_only_the_featured_comment_text( $input )
	{
		preg_match( $this->wrapper_regex, $input, $text );

		return empty( $text[1] ) ? FALSE : $text[1];
	}// END get_only_the_featured_comment_text

	/**
	 * Watches for comment edits and features the comment if either the comment_meta or content indicates it should be featured.
	 */
	public function edit_comment( $comment_id )
	{
		$comment = get_comment( $comment_id );

		// check if the featured tags exist in the comment content, permissions will be checked in the next function
		if (
			   $featured = $this->get_only_the_featured_comment_text( $comment->comment_content )
			|| $this->get_comment_meta( $comment->comment_ID )
		)
		{
			$this->feature_comment( $comment_id );
		}// END if
	}// END edit_comment

	/**
	 * Unfeatures a comment
	 */
	public function unfeature_comment( $comment_id )
	{
		$comment = get_comment( $comment_id );

		// check user permissions
		// @TODO: map a meta cap for this rather than extend the edit_post here
		if ( current_user_can( 'edit_post', $comment->comment_post_ID ) )
		{
			if ( $post_id = $this->get_comment_meta( $comment->comment_ID ) )
			{
				// turn off our transition_comment_status filter so we don't fire this off while updating the status
				remove_filter( 'transition_comment_status', array( $this, 'transition_comment_status' ), 10, 3 );

				// The comment might have a shortcode in it so we'll remove that since it could refeature the comment accidentally
				$cleaned_comment = array(
					'comment_ID'      => $comment->comment_ID,
					'comment_content' => preg_replace( $this->tag_regex, '', $comment->comment_content ),
					'comment_approved' => $comment->comment_approved,
				);

				// if comment_approved isn't provided with 'hold', the comment will be auto-transitioned to approved
				if ( '0' === (string) $cleaned_comment['comment_approved'] )
				{
					$cleaned_comment['comment_approved'] = 'hold';
				}//end if
				elseif ( '1' === (string) $cleaned_comment['comment_approved'] )
				{
					$cleaned_comment['comment_approved'] = 'approve';
				}//end elseif

				// we need to delete the comment meta BEFORE updating the comment because wp_update_comment fires off
				// the edit_comment action. We hook to edit_comment to check if this comment is featured (determined by
				// the contents of the comment OR wether or not it has comment meta.  Deleting the meta BEFORE updating
				// allows our check in edit_comment to succeed as expected.
				delete_comment_meta( $comment->comment_ID, $this->meta_key . '-post_id' );

				wp_update_comment( $cleaned_comment );

				$comment = get_comment( $comment_id );

				wp_delete_post( $post_id );

				// Clear out the get_featured_comment_posts cache for this post
				$this->delete_featured_comment_posts_cache( $comment->comment_post_ID );

				// let's turn this back on
				add_filter( 'transition_comment_status', array( $this, 'transition_comment_status' ), 10, 3 );
				return TRUE;
			}// END if
		}// END if
	}// END unfeature_comment

	/**
	 * Features a comment
	 */
	public function feature_comment( $comment_id )
	{
		$comment = get_comment( $comment_id );

		// check user permissions
		// @TODO: map a meta cap for this rather than extend the edit_post here
		if ( current_user_can( 'edit_post', $comment->comment_post_ID ) )
		{
			// Featuring a comment implicity approves it
			if ( 1 != $comment->comment_approved )
			{
				$comment->comment_approved = 1;
				wp_update_comment( (array) $comment );
			} // END if

			if ( $post_id = $this->get_comment_meta( $comment->comment_ID ) )
			{
				// Post already exists so we just make sure that we account for any shortcode use
				$featured = $this->_get_featured_comment_text( $comment->comment_content );
				$success  = wp_update_post( (object) array( 'ID' => $post_id, 'post_title' => $featured, 'post_content' => $featured ) );
			}//END if
			else
			{
				// Post doesn't exist yet so we create one
				$success = $this->create_post( $comment_id );
			}//END else

			// Clear out the get_featured_comment_posts cache for this post
			$this->delete_featured_comment_posts_cache( $comment->comment_post_ID );

			return $success;
		}// END if
	}// END feature_comment

	/**
	 * Creates the post for a featured comment when given a valid comment_id
	 */
	public function create_post( $comment_id )
	{
		$comment = get_comment( $comment_id );
		$parent = get_post( $comment->comment_post_ID );
		$featured = $this->_get_featured_comment_text( $comment->comment_content );
		// @TODO = wrap the content in a blockquote tag with the cite URL set to the comment permalink

		$post = array(
			'post_title' => $featured,
			'post_content' => $featured,
			'post_name' => sanitize_title( $featured ),
			'post_date' => bsocial_comments()->options()->featured_comments->use_commentdate ? $comment->comment_date : FALSE, // comment_date vs. the date the comment was featured
			'post_date_gmt' => bsocial_comments()->options()->featured_comments->use_commentdate ? $comment->comment_date_gmt : FALSE,
			'post_author' => $parent->post_author, // so permissions map the same as for the parent post
			'post_parent' => $parent->ID,
			'post_status' => $parent->post_status,
			'post_password' => $parent->post_password,
			'post_type' => $this->post_type_name,
		);

		$post_id = wp_insert_post( $post );

		// simple sanity check
		if ( ! is_numeric( $post_id ) )
		{
			return $post_id;
		}

		// save the meta
		update_post_meta( $post_id, $this->meta_key . '-comment_id', $comment->comment_ID );
		update_comment_meta( $comment->comment_ID, $this->meta_key . '-post_id', $post_id );

		// get all the terms on the parent post
		foreach ( (array) wp_get_object_terms( $parent->ID, get_object_taxonomies( $parent->post_type ) ) as $term )
		{
			$parent_terms[ $term->taxonomy ][] = $term->name;
		}//END foreach

		// set those terms on the comment
		foreach ( (array) $parent_terms as $tax => $terms )
		{
			wp_set_object_terms( $post_id, $terms, $tax, FALSE );
		}//END foreach

		return $post_id;
	}// END create_post

	/**
	 * Returns the matching post_id of a featuerd comment if it exists
	 */
	public function get_comment_meta( $comment_id )
	{
		return get_comment_meta( $comment_id, $this->meta_key . '-post_id', TRUE );
	}// END get_comment_meta

	/**
	 * Return featured comment post or FALSE
	 */
	public function is_featured( $comment_id )
	{
		if ( ! $post_id = $this->get_comment_meta( $comment_id ) )
		{
			return FALSE;
		}// END if

		return get_post( $post_id );
	}// END is_featured

	/**
	 * Filter bsocial_comments_is_featured hook and return TRUE/FALSE for given comment
	 */
	public function bsocial_comments_is_featured( $is_featured, $comment_id )
	{
		return $this->is_featured( $comment_id );
	} // END bsocial_comments_is_featured

	/**
	 * Watch for status transitions that should result in the comment being unfeatured
	 *
	 * @param string $new_status The new comment status
	 * @param string $old_status The old comment status
	 * @param object $comment WP comment object
	 */
	public function transition_comment_status( $new_status, $old_status, $comment )
	{
		if ( ! $this->is_featured( $comment->comment_ID ) )
		{
			return;
		} // END if

		// If the comment's new status is will make it invisible we unfeature it
		// Because orphaned featured comment posts are sad
		switch ( $new_status )
		{
			case 'unapproved':
			case 'spam':
			case 'trash':
			case 'hold':
			case 'delete':
				$this->unfeature_comment( $comment->comment_ID );
				break;
		} // END switch
	} // END transition_comment_status

	/**
	 * Returns the matching comment_id of the post if it exists
	 */
	public function get_post_meta( $post_id )
	{
		return get_post_meta( $post_id, $this->meta_key . '-comment_id', TRUE );
	}// END get_post_meta

	/**
	 * Feature/unfeature a comment via an admin-ajax.php endpoint
	 */
	public function ajax_feature_comment()
	{
		$comment_id = absint( $_GET['comment_id'] );

		if ( ! current_user_can( 'edit_comment', $comment_id ) )
		{
			$current_user = wp_get_current_user();
			$slog_data    = array( 'comment_id' => $comment_id, 'user_id' => $current_user->ID, 'display_name' => $current_user->display_name );
			do_action( 'go_slog', 'bsocial-comments-feature-edit', 'Current user can not edit comment.', $slog_data );
			return FALSE;
		}//END if

		if ( ! check_ajax_referer( 'bsocial-featuredcomment-save', 'bsocial-nonce', FALSE ) )
		{
			$slog_data = array( 'nonce' => $_GET['bsocial-nonce'], 'expected-nonce' => wp_create_nonce( 'bsocial-featuredcomment-save' ) );
			do_action( 'go_slog', 'bsocial-comments-feature-nonce', 'Nonce check failed.', $slog_data );
			return FALSE;
		}// END if

		if ( get_comment( $comment_id ) )
		{
			$state = $this->is_featured( $comment_id ) ? 'featured' : 'unfeatured';

			if ( 'feature' == $_GET['direction'] )
			{
				$success = $this->feature_comment( $comment_id );
				$state = 'featured';
			}//END if
			else
			{
				$success = $this->unfeature_comment( $comment_id );
				$state = 'unfeatured';
			}//END else

			$data = array(
				'success' => $success,
				'link' => $this->get_feature_link( $comment_id ),
				'state' => $state,
			);

			// We only need to return text if the comment was unfeatured
			if ( 'unfeature' == $_GET['direction'] )
			{
				$data['text'] = get_comment_text( $comment_id );
				// We apply the 'comment_text' filters here so the text comes back in the appropriate state for use in the comments panel
				$data['text_with_pees'] = apply_filters( 'comment_text', get_comment_text( $comment_id ), get_comment( $comment_id ), array() );
			}// END if

			wp_send_json( $data );
		}// END if

		die;
	}// END ajax_feature_comment

	/**
	 * Return a nonced URL to feature/unfeature a comment
	 */
	public function get_feature_url( $comment_id )
	{
		$arguments = array(
			'action'        => 'bsocial_feature_comment',
			'comment_id'    => absint( $comment_id ),
			'bsocial-nonce' => wp_create_nonce( 'bsocial-featuredcomment-save' ),
		);

		// If the comment is already featured then this URL should unfeature the comment
		if ( $this->get_comment_meta( $comment_id ) )
		{
			$arguments['direction'] = 'unfeature';
		}// END if
		else
		{
			$arguments['direction'] = 'feature';
		}// END else

		// Checking is_admin lets us avoid cross domain JS issues because on VIP the admin panel and the site itself have different domains
		return add_query_arg( $arguments, is_admin() ? admin_url( 'admin-ajax.php' ) : home_url( 'wp-admin/admin-ajax.php' ) );
	}// END get_feature_url

	/**
	 * Returns a feature/unfeature link for a comment
	 */
	public function get_feature_link( $comment_id, $additional_classes = '' )
	{
		// If the comment is already featured then this URL should unfeature the comment
		if ( $this->get_comment_meta( $comment_id ) )
		{
			$text  = 'Unfeature';
			$class = 'featured-comment';
		}// END if
		else
		{
			$text  = 'Feature';
			$class = 'unfeatured-comment';
		}// END else

		$classes = 'feature-comment ' . $class;
		$classes .= '' != $additional_classes ? ' ' . esc_attr( $additional_classes ) : '';

		$url = $this->get_feature_url( $comment_id );

		return '<a href="' . $url . '" title="' . $text . '" class="' . $classes . '">' . $text . '</a>';
	}// END get_feature_link

	/**
	 * Return all featured comment post and comment objects for a post
	 *
	 * @param $post_id int The post_id of the post you want featured comments for
	 * @param $args array get_post args you want to use
	 */
	public function get_featured_comment_posts( $post_id )
	{
		$args = array(
			'post_parent' => absint( $post_id ),
			'post_type'   => $this->post_type_name,
			'numberposts' => 50,
			// Just like comments we should default to
			'order'       => 'ASC',
		);

		// Check the cache to see if we've already done this recently
		if ( ! $comment_posts = wp_cache_get( $post_id, $this->id_base )  )
		{
			$comment_posts = get_posts( $args );

			// Get the comment objects for each comment post
			foreach ( $comment_posts as $key => $comment_post )
			{
				$comment_posts[ $key ]->comment = get_comment( $this->get_post_meta( $comment_post->ID ) );
			}// END foreach

			// Store everything for 24 hours
			wp_cache_set( $post_id, $comment_posts, $this->id_base, 86400 );
		}// END if

		return $comment_posts;
	}// END get_featured_comment_posts

	/**
	 * Delete the the featured comment posts cache for a given post_id
	 */
	public function delete_featured_comment_posts_cache( $post_id )
	{
		return wp_cache_delete( $post_id, $this->id_base );
	} // END delete_featured_comment_posts_cache
}// END bSocial_Comments_Featured

<?php
class bSocial_Comments_Featured
{
	// don't mess with these
	var $id_base = 'bsuite-fcomment';
	var $post_type_name = 'bsuite-fcomment';
	var $meta_key = 'bsuite-fcomment';
	var $tag_regex = '/\[\/?featured_?comment\]/i'; // just match the single tag to make it easy to remove
	var $wrapper_regex = '/\[featured_?comment\](.*?)\[\/?featured_?comment\]/i'; // match the content inside the tags
	var $enqueued_admin_js = FALSE;

	public function __construct()
	{
		add_action( 'init', array( $this, 'init' ), 11 );
		add_action( 'edit_comment', array( $this, 'edit_comment' ), 5 );
		add_action( 'delete_comment', array( $this, 'unfeature_comment' ) );

		add_filter( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
		add_filter( 'post_class', array( $this, 'filter_post_class' ) );
		add_filter( 'get_comment_text', array( $this, 'filter_get_comment_text' ) );
		add_filter( 'the_author', array( $this, 'filter_the_author' ) );
		add_filter( 'the_author_posts_link', array( $this, 'filter_the_author_posts_link' ) );
		add_filter( 'post_type_link', array( $this, 'post_type_link' ), 11, 2 );
	} // END __construct

	/**
	 * Activate featured comments
	 */
	public function init()
	{
		$this->register_post_type();

		if ( is_admin() )
		{
			$this->admin();
		} // END if
	} // END init

	/**
	 * Admin singleton
	 */
	public function admin()
	{
		if ( ! isset( $this->admin ) )
		{
			require_once __DIR__ . '/class-bsocial-comments-featured-admin.php';
			$this->admin = new bSocial_Comments_Featured_Admin;
		}

		return $this->admin;
	} // END admin

	public function register_post_type()
	{
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
			} // END if
		} // END foreach

		register_post_type( $this->post_type_name,
			array(
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
				'show_in_menu' => 'edit-comments.php',
				'has_archive' => 'talkbox',
				'rewrite' => array(
					'slug' => 'talkbox',
					'with_front' => FALSE,
				),
				'taxonomies' => $taxonomies,
			)
		);
	} // END register_post_type

	public function pre_get_posts( $query )
	{

		if ( bsocial_comments()->options()->featuredcomments->add_to_waterfall && ! is_admin() && $query->is_main_query() )
		{
			$post_types = array_merge(
				(array) $query->query_vars['post_type'],
				array( is_singular() && isset( $query->queried_object->post_type ) ? $query->queried_object->post_type : 'post' ),
				array( $this->post_type_name )
			);

			$query->set( 'post_type', $post_types );
		}

		return $query;
	} // END pre_get_posts

	public function post_type_link( $permalink, $post )
	{
		if ( $post->post_type == $this->post_type_name && ( $comment_id = get_post_meta( $post->ID, $this->meta_key .'-comment_id', TRUE ) ) )
		{
			return get_comment_link( $comment_id );
		}

		return $permalink;
	} // END post_type_link

	public function filter_get_comment_text( $content )
	{
		if ( is_admin() )
		{
			return $content;
		}
		else
		{
			return preg_replace( $this->tag_regex, '', $content );
		}
	} // END filter_get_comment_text

	public function filter_post_class( $classes )
	{
		if ( get_post( get_the_ID() )->post_type == $this->post_type_name )
		{
			$classes[] = 'post';
		}

		return $classes;
	} // END filter_post_class

	public function filter_the_author( $author_name )
	{
		if ( get_the_ID() && get_post( get_the_ID() )->post_type == $this->post_type_name )
		{
			return get_comment_author( get_post_meta( get_the_ID(), $this->meta_key .'-comment_id', TRUE ) );
		}
		else
		{
			return $author_name;
		}
	} // END filter_the_author

	public function filter_the_author_posts_link( $url )
	{
		if ( get_the_ID() && get_post( get_the_ID() )->post_type == $this->post_type_name )
		{
			return '';
		}
		else
		{
			return $url;
		}
	} // END filter_the_author_posts_link

	public function get_featured_comment_text( $comment_id = FALSE )
	{
		remove_filter( 'get_comment_text', array( $this, 'filter_get_comment_text' ) );
		$text = $this->_get_featured_comment_text( get_comment_text( $comment_id ) );
		add_filter( 'get_comment_text', array( $this, 'filter_get_comment_text' ) );

		return $text[1];
	} // END get_featured_comment_text

	public function _get_featured_comment_text( $input )
	{
		preg_match( $this->wrapper_regex, $input, $text );

		return empty( $text[1] ) ? $input : $text[1];
	} // END _get_featured_comment_text

	public function edit_comment( $comment_id )
	{
		$comment = get_comment( $comment_id );

		// check if the featured tags exist in the comment content, permissions will be checked in the next function
		if (
			$featured = $this->_get_featured_comment_text( $comment->comment_content ) ||
			$this->get_comment_meta( $comment->comment_ID )
		)
		{
			$this->feature_comment( $comment_id );
		}

	} // END edit_comment

	public function unfeature_comment( $comment_id )
	{
		$comment = get_comment( $comment_id );

		// check user permissions
		// @TODO: map a meta cap for this rather than extend the edit_post here
		if ( current_user_can( 'edit_post', $comment->comment_post_ID ) )
		{
			if ( $post_id = $this->get_comment_meta( $comment->comment_ID ) )
			{
				wp_delete_post( $post_id );
				delete_comment_meta( $comment->comment_ID, $this->meta_key .'-post-id' );
				return TRUE;
			}
		}
	} // END unfeature_comment

	public function feature_comment( $comment_id )
	{
		$comment = get_comment( $comment_id );

		// check user permissions
		// @TODO: map a meta cap for this rather than extend the edit_post here
		if ( current_user_can( 'edit_post', $comment->comment_post_ID ) )
		{
			if ( $post_id = $this->get_comment_meta( $comment->comment_ID ) )
			{
				return wp_update_post( (object) array( 'ID' => $post_id, 'post_content' => $featured ) ); // we have a post for this comment
			}
			else
			{
				return $this->create_post( $comment_id ); // create a new post for this comment
			}
		} // END if
	} // END feature_comment

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
			'post_date' => bsocial_comments()->options()->featuredcomments->use_commentdate ? $comment->comment_date : FALSE, // comment_date vs. the date the comment was featured
			'post_date_gmt' => bsocial_comments()->options()->featuredcomments->use_commentdate ? $comment->comment_date_gmt : FALSE,
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
		update_post_meta( $post_id, $this->meta_key .'-comment_id', $comment->comment_ID );
		update_comment_meta( $comment->comment_ID, $this->meta_key .'-post-id', $post_id );

		// get all the terms on the parent post
		foreach ( (array) wp_get_object_terms( $parent->ID, get_object_taxonomies( $parent->post_type ) ) as $term )
		{
			$parent_terms[ $term->taxonomy ][] = $term->name;
		}

		// set those terms on the comment
		foreach ( (array) $parent_terms as $tax => $terms )
		{
			wp_set_object_terms( $post_id, $terms, $tax, FALSE );
		}

		return $post_id;
	} // END create_post

	/**
	 * Returns the matching post_id of a featuerd comment if it exists
	 */
	public function get_comment_meta( $comment_id )
	{
		return get_comment_meta( $comment_id, $this->meta_key . '-post-id', TRUE );
	} // END get_comment_meta

	public function get_feature_comment_url( $comment_id )
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
		} // END if
		else
		{
			$arguments['direction'] = 'feature';
		} // END else

		return add_query_arg( $arguments, admin_url( 'admin-ajax.php' ) );
	} // END get_feature_comment_url

	/**
	 * Returns a feature/unfeature link for a comment
	 */
	public function get_feature_comment_link( $comment_id, $additional_classes = '' )
	{
		// If the comment is already featured then this URL should unfeature the comment
		if ( $this->get_comment_meta( $comment_id ) )
		{
			$text  = 'Unfeature';
			$class = 'featured-comment';
		} // END if
		else
		{
			$text  = 'Feature';
			$class = 'unfeatured-comment';
		} // END else

		$classes = 'feature-comment ' . $class;
		$classes .= '' != $additional_classes ? ' ' . esc_attr( $additional_classes ) : '';

		$url = $this->get_feature_comment_url( $comment_id );

		return '<a href="' . $url . '" title="' . $text . '" class="' . $classes . '">' . $text . '</a>';
	} // END get_feature_comment_link
} // END bSocial_Comments_Featured class
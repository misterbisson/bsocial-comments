<?php

class bSocial_Comments
{
	public $id_base = 'bsocial-comments';
	public $featuredcomments = NULL;

	private $options = NULL;

	public function __construct()
	{
		// activate components
		add_action( 'init', array( $this, 'init' ), 1 );

		// hooks for methods in this class
		add_action( 'delete_comment', array( $this, 'comment_id_by_meta_delete_cache' ) );
	} // END __construct

	public function init()
	{
		if ( $this->options()->featuredcomments->enable )
		{
			require_once __DIR__ . '/class-bsocial-comments-featured.php';
			$this->featuredcomments = new bSocial_Comments_Featured();
		} // END if
	} // END init

	/**
	 * object accessors
	 */
	public function admin()
	{
		if ( ! isset( $this->admin ) )
		{
			require_once __DIR__ . '/class-bsocial-admin.php';
			$this->admin = new bSocial_Admin;
		}

		return $this->admin;
	} // END admin

	/**
	 * plugin options getter
	 */
	public function options()
	{
		if ( ! $this->options )
		{
			$this->options = (object) apply_filters(
				'go_config',
				wp_parse_args( (array) $this->default_options() ),
				$this->id_base
			);
		}

		return $this->options;
	} // END options

	/**
	 * default options
	 */
	public function default_options()
	{
		return array(
			'featuredcomments' => (object) array(
				'enable'           => 1,
				'use_commentdate'  => 1,
				'add_to_waterfall' => 1,
			),
		);
	} // END default_options

	/**
	 * this method uses a custom SQL query because it's way more performant
	 * than the SQL from WP's core WP_Comment_Query class.
	 *
	 * The main problem: joins on tables with BLOB or TEXT columns _always_
	 * go to temp tables on disk. See http://dev.mysql.com/doc/refman/5.5/en/internal-temporary-tables.html
	 */
	public function comment_id_by_meta( $metavalue, $metakey )
	{
		global $wpdb;

		if ( ! $comment_id = wp_cache_get( (string) $metakey .':'. (string) $metavalue, 'comment_id_by_meta' ) )
		{
			$comment_id = $wpdb->get_var( $wpdb->prepare( 'SELECT comment_id FROM ' . $wpdb->commentmeta . ' WHERE meta_key = %s AND meta_value = %s', $metakey, $metavalue ) );
			wp_cache_set( (string) $metakey .':'. (string) $metavalue, $comment_id, 'comment_id_by_meta' );
		}

		return $comment_id;
	} // END comment_id_by_meta

	/**
	 * Updates the cached used by comment_id_by_meta
	 */
	public function comment_id_by_meta_update_cache( $comment_id, $metavalue, $metakey )
	{
		if ( 0 < $comment_id )
		{
			return;
		}

		if ( ( ! $metavalue ) || ( ! $metakey ) )
		{
			return;
		}

		wp_cache_set( (string) $metakey .':'. (string) $metavalue, (int) $comment_id, 'comment_id_by_meta' );
	} // END comment_id_by_meta_update_cache

	/**
	 * deletes the cached used by comment_id_by_meta
	 */
	public function comment_id_by_meta_delete_cache( $comment_id )
	{
		foreach ( (array) get_metadata( 'comment', $comment_id ) as $metakey => $metavalues )
		{
			foreach( $metavalues as $metavalue )
			{
				wp_cache_delete( (string) $metakey .':'. (string) $metavalue, 'comment_id_by_meta' );
			}
		}
	} // END comment_id_by_meta_delete_cache
} // END bSocial_Comments

function bsocial_comments()
{
	global $bsocial_comments;

	if( ! $bsocial_comments )
	{
		$bsocial_comments = new bSocial_Comments();
	}

	return $bsocial_comments;
} // END bsocial_comments

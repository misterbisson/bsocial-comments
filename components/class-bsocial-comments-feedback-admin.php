<?php

class bSocial_Comments_Feedback_Admin extends bSocial_Comments_Feedback
{
	public function __construct()
	{
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		add_filter( 'manage_edit-comments_columns', array( $this, 'comments_columns' ) );
		add_filter( 'manage_comments_custom_column', array( $this, 'manage_comments_custom_column' ), 10, 2 );
	} // end __construct

	/**
	 *
	 */
	public function admin_enqueue_scripts()
	{
		$version_config = apply_filters( 'go_config', array( 'version' => bsocial_comments()->version ), 'go-script-version' );

		wp_enqueue_style( $this->id_base, plugins_url( '/css/bsocial-comments-feedback.css', __FILE__ ), array(), $version_config['version'] );
	} // END admin_enqueue_scripts

	/**
	 * Hook to manage_edit-comments_columns filter and add columns for flags and faves
	 *
	 * @param $columns (array) array of column slugs and names
	 */
	public function comments_columns( $columns )
	{
		// Rebuild the array so we can make sure Faves/Flags columns show right after the comment content
		$new_columns = array();

		foreach ( $columns as $column => $column_name )
		{
			$new_columns[ $column ] = $column_name;

			if ( 'comment' == $column )
			{
				$new_columns['faves'] = 'Faves';
				$new_columns['flags'] = 'Flags';
			} // END if
		} // END foreach

		return $new_columns;
	} // END comments_columns

	public function manage_comments_custom_column( $column, $comment_id )
	{
		if ( 'faves' != $column && 'flags' != $column )
		{
			return;
		} // END if

		switch ( $column )
		{
			case 'faves':
				$this->faves_column( $comment_id );
				break;

			case 'flags':
				$this->flags_column( $comment_id );
				break;
		} // END switch
	} // END manage_comments_custom_column

	public function faves_column( $comment_id )
	{
		if ( ! $comment = get_comment( $comment_id ) )
		{
			return;
		} // END if

		if ( 'fave' == $comment->comment_type )
		{
			echo $this->get_parent_link( $comment->comment_parent );
		} // END if
		elseif ( '' == $comment->comment_type || 'comment' == $comment->comment_type )
		{
			$count = $this->get_comment_fave_count( $comment_id );
			echo 0 == $count ? $count : '<span class="faves">+ ' . $count . '</span>';
		} // END elseif
	} // END faves_column

	public function flags_column( $comment_id )
	{
		if ( ! $comment = get_comment( $comment_id ) )
		{
			return;
		} // END if

		if ( 'flag' == $comment->comment_type )
		{
			echo $this->get_parent_link( $comment->comment_parent );
		} // END if
		elseif ( '' == $comment->comment_type || 'comment' == $comment->comment_type )
		{
			$count = $this->get_comment_flag_count( $comment_id );
			echo 0 == $count ? $count : '<span class="flags">- ' . $count . '</span>';
		} // END elseif
	} // END flags_column

	public function get_parent_link( $parent_id )
	{
		$url = add_query_arg( array( 'action' => 'editcomment', 'c' => absint( $parent_id ) ), admin_url( 'comment.php' ) );
		echo '<a href="' . esc_url( $url ) . '" title="Edit parent comment">' . absint( $parent_id ) . '</a>';
	} // END get_parent_link
}// END bSocial_Comments_Feedback_Admin

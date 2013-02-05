<?php
/**
 * @package Tako Movable Comments
 * @author Ren Aysha
 * @version 1.0
 */
/*
Plugin Name: Tako Movable Comments
Version: 1.0
Plugin URI: http://www.renettarenula.github.com
Author: Ren Aysha
Author URI: http://twitter.com/RenettaRenula
Description: This plugin allows you to move comments from one post/page to another. Just click on edit comments and move 
your comments through the 'Move Comments with Tako' metabox.

Copyright (C) <2013> <Ren Aysha>

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

class Tako
{
	/*--------------------------------------------*
	 * Constructor
	 *--------------------------------------------*/

	/**
	 * Initializes the plugin by adding meta box, filters, and JS files.
	 */
	public function __construct() 
	{
		add_action( 'add_meta_boxes', array( &$this, 'tako_add_meta_box' ) );
		add_filter( 'comment_save_pre', array( &$this, 'tako_save_meta_box' ) );
		wp_enqueue_script('dropdown', '/wp-content/plugins/tako/js/dropdown.js');
	}

	/*--------------------------------------------*
	 * Core Functions
	 *---------------------------------------------*/

	/**
	 * This is needed in order to add a new meta box in the edit comment page
	 */
	public function tako_add_meta_box() 
	{
		add_meta_box( 
           	'tako_move_comments'
            ,__( 'Move Comments with Tako', 'tako_lang' )
            ,array( &$this, 'tako_meta_box' )
            ,'comment' 
            ,'normal'
            ,'high'
        );
	}
	/**
	 * The callback for the meta box in order to print the HTML form of the Meta Box
	 * @param array $comment 	Getting the comment information for the current comment
	 */
	public function tako_meta_box( $comment ) 
	{
		wp_nonce_field( plugin_basename( __FILE__ ), 'tako_nonce' );
		global $post;
		$args  = array( 'numberposts' => -1 );
		$posts = get_posts( $args );
		$pargs = array(
			'depth'    => 0,
			'child_of' => 0,
			'selected' => $comment->comment_post_ID,
			'echo'     => 1,
			'name'     => 'tako_page');
		$current_post = get_the_title( $comment->comment_post_ID );
		$post_types = get_post_types( '', 'names' );
	?>
		<p><?php _e( 'This comment currently belongs to a post called ', 'tako_lang') ?><em><strong><?php echo $current_post; ?></em></strong></p>
		<div id = "tako-post-types">
		<label for="post-type"><?php _e( 'Choose the post type that you want to move this comment to', 'tako_lang' ); ?></label>
		<select name="tako_post_type" id="tako_post_type">
			<?php foreach( $post_types as $post_type ) { ?>
				<option value="<? echo $post_type; ?>" <?php if ( get_post_type( $comment->comment_post_ID ) == $post_type ) echo 'selected'; ?>><?php echo $post_type; ?></option>
		  	<?php } ?>
		</select>
		</div>
		<br />
		<div id = "tako-post">
		<label for="post"><?php _e( 'Choose the post that you want to move this comment to', 'tako_lang')?></label>
		<select name="tako_post" id="tako_post">
		<?php foreach( $posts as $post ) : setup_postdata($post); ?>
			<option value="<? echo $post->ID; ?>" <?php if ( $post->ID == $comment->comment_post_ID ) echo 'selected'; ?>><?php the_title(); ?></option>
		<?php endforeach; ?>
		</select>
		</div>
		<br />
		<div id = "tako-pages">
		<label for="page"><?php _e( 'Choose the page that you want to move this comment to', 'tako_lang')?></label>
		<?php wp_dropdown_pages( $pargs ); ?>
		</div>
<?php
	}
	/**
	 * The method that is responsible in ensuring that the new comment is saved
	 * @param string $comment_content 	Getting the comment information for the current comment
	 */
	public function tako_save_meta_box( $comment_content ) 
	{
		if ( !wp_verify_nonce( $_POST['tako_nonce'], plugin_basename( __FILE__ ) ) )
			return;
		if ( !current_user_can( 'moderate_comments' ) )  {
			return;
		}
		global $wpdb, $comment;
		$post_type = (string) $_POST['tako_post_type'];
		$comment_post_ID = $post_type == 'post' ? (int) $_POST['tako_post'] : (int) $_POST['tako_page'];
		$comment_ID = (int) $_POST['comment_ID'];
		$comments_args = array( 'parent' => $comment_ID );
		$comments = get_comments( $comments_args );
		$comments_id = array();
		foreach( $comments as $comment ) {
			$comments_id[] = $comment->comment_ID;
		}
		$post = get_post( $comment_post_ID );
		// if the post ID doesn't exist
		if ( !$post )
			return $comment_content;
		$new = compact( 'comment_post_ID' );
		$curr = compact( 'comment_ID' );
		// if there are no nested comments
		if ( !$comments ) {
			$update = $wpdb->update( $wpdb->comments, $new, compact( 'comment_ID' ) );
		}
		else {
			$var = array_merge( $this->tako_get_subcomments( $comments_id ), compact( 'comment_ID' ) );
			$val = implode( ',', array_map( 'intval', $var ) );
			$wpdb->query( "UPDATE $wpdb->comments SET comment_post_ID = $comment_post_ID WHERE comment_ID IN ( $val )" );
		}
		wp_update_comment_count( $comment_post_ID );
		return $comment_content;	
	}	

	/**
	 * The method that is responsible for getting all the nested comments under one comment.
	 * This method will check if there are subcomments available under each subcomments.
	 * @param array $comments	This is an array of comments. These comments are subcomments of the comment that the user wants to move
	 */
	function tako_get_subcomments( $comments ) {
		global $wpdb;
		// implode the array; this is the current 'parent'
		$parents = implode( ',', $comments );
		// this will store all the subcomments
		$nested = array();
		do {
			// initializing the an array (or emptying the array)
			$subs = array();
			// get the subcomments under the parent
			$subcomments = $wpdb->get_results( "SELECT comment_ID FROM $wpdb->comments WHERE comment_parent IN ( $parents )" );
			// store the subcomment under $subs and $nested
			foreach( $subcomments as $subcomment ) {
				$subs[] = $subcomment->comment_ID;
				$nested[] = $subcomment->comment_ID;
			}
			// implode the array and assign it as parents
			$parents = implode( ',', $subs );
		// loop will stop once $subs is empty
		} while( !empty( $subs ) );
		// merge all the subcomments with the initial parent comments
		$merge = array_merge( $comments, $nested );
		return $merge;
	}
}
$tako = new Tako();
?>
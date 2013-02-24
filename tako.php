<?php
/**
 * @package Tako Movable Comments
 * @author Ren Aysha
 * @version 1.0.2
 */
/*
Plugin Name: Tako Movable Comments
Version: 1.0.2
Plugin URI: https://github.com/renettarenula/Tako/
Author: Ren Aysha
Author URI: http://twitter.com/RenettaRenula
Description: This plugin allows you to move comments from one post or page to another. You can also move comments across post types and custom post types. Just click on edit comments and move your comments through the <strong>Move Comments with Tako</strong> metabox.

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
		add_action( 'admin_enqueue_scripts', array( &$this, 'tako_load_scripts') );
		add_action( 'wp_ajax_tako_chosen_post_type', array( &$this, 'tako_chosen_post_type_callback' ) );
	}

	/**
	 * Enqueue the JS file and ensure that it only loads in the edit comment page
	 */
	public function tako_load_scripts( $hook ) {
		if ( $hook != 'comment.php' )
			return;
		wp_enqueue_script( 'tako_dropdown', plugins_url( 'js/tako-dropdown.js' , __FILE__ ) );
		wp_localize_script( 'tako_dropdown', 'tako_object', array( 'tako_ajax_nonce' => wp_create_nonce( 'tako-ajax-nonce' ) ) );
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
		<p><?php _e( 'This comment currently belongs to a post called ', 'tako_lang' ) ?><em><strong><?php echo $current_post; ?></em></strong></p>
		<div id="tako_current_comment" style="display:none;"><?php echo $comment->comment_post_ID ?></div>
		<div id = "tako-post-types">
		<label for="post-type"><?php _e( 'Choose the post type that you want to move this comment to', 'tako_lang' ); ?></label>
		<select name="tako_post_type" id="tako_post_type">
			<?php foreach( $post_types as $post_type ) { ?>
				<option value="<?php echo $post_type; ?>" <?php if ( get_post_type( $comment->comment_post_ID ) == $post_type ) echo 'selected'; ?>><?php echo $post_type; ?></option>
		  	<?php } ?>
		</select>
				<img src="<?php echo admin_url('/images/wpspin_light.gif'); ?>" class="waiting" id="tako_spinner" style="display:none;" />
		</div>
		<br />
		<div id="tako_dropdown_list"></div>
<?php
	}

	/**
	 * The method that is responsible in ensuring that the new comment is saved
	 * @param string $comment_content 	Getting the comment information for the current comment
	 */
	public function tako_save_meta_box( $comment_content ) 
	{
		$screen = get_current_screen();
		// For Quick Edit: if current screen is anything other than edit-comments (main page for editing comments), ignore nonce verification.
		if ( !wp_verify_nonce( $_POST['tako_nonce'], plugin_basename( __FILE__ ) ) && $screen->parent_base == 'edit-comments' )
			return;
		if ( !current_user_can( 'moderate_comments' ) )  {
			return;
		}
		global $wpdb, $comment;
		$comment_post_ID = (int) $_POST['tako_post'];
		$comment_ID = (int) $_POST['comment_ID'];
		if ( !$this->tako_post_exist( $comment_post_ID ) )
			return $comment_content;
		$new = compact( 'comment_post_ID' );
		// if there are no nested comments
		if ( !$this->tako_nested_comments_exist( $comment_ID ) ) {
			$update = $wpdb->update( $wpdb->comments, $new, compact( 'comment_ID' ) );
		}
		else {
			$var = array_merge( $this->tako_get_subcomments( $this->get_direct_subcomments( $comment_ID ) ), compact( 'comment_ID' ) );
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
	 * @return array
	 */
	public function tako_get_subcomments( $comments ) 
	{
		global $wpdb;
		// implode the array; this is the current 'parent'
		$parents = implode( ',', array_map( 'intval', $comments ) );
		$nested = array(); // this will store all the subcomments
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
			$parents = implode( ',', array_map( 'intval', $subs ) );
		// loop will stop once $subs is empty
		} while( !empty( $subs ) );
		// merge all the subcomments with the initial parent comments
		$merge = array_merge( $comments, $nested );
		return $merge;
	}

	/**
	 * This method is responsible in checking whether nested comments is available
	 * @param int $comment_ID Comment ID of the comment chosen to be moved
	 * @return object
	 */
	public function tako_nested_comments_exist( $comment_ID ) 
	{
		$comments_args = array( 'parent' => $comment_ID );
		$comments = get_comments( $comments_args );
		return $comments;
	}

	/**
	 * Get the post object that the user had chosen to move the comments to
	 * @param int $comment_post_ID	The post ID that the user wants to move the comments to
	 * @return object
	 */
	public function tako_post_exist( $comment_post_ID ) 
	{
		return get_post( $comment_post_ID );
	}

	/**
	 * Get the direct subcomments of the comment that is chosen to be moved
	 * @param int $comment_ID Comment ID of the comment chosen to be moved
	 * @return array
	 */
	public function get_direct_subcomments( $comment_ID ) 
	{
		$comments_args = array( 'parent' => $comment_ID );
		$comments = get_comments( $comments_args );
		$comments_id = array();
		foreach( $comments as $comment ) {
			$comments_id[] = $comment->comment_ID;
		}
		return $comments_id;
	}

	/*--------------------------------------------*
	 * Ajax Callback
	 *--------------------------------------------*/

	/**
	 * Ajax callback for checking which post type is chosen and it will
	 * print the dropdown list of all post title of the chosen post type.
	 */
	public function tako_chosen_post_type_callback() 
	{
		// check nonce
		if ( !isset( $_POST['tako_ajax_nonce'] ) || !wp_verify_nonce( $_POST['tako_ajax_nonce'], 'tako-ajax-nonce' ) )
			die( 'Permission Denied!' );
		global $wpdb;
		$post_type = $_POST['postype'];
		$post_id   = (int) $_POST['post_id'];
		$args  = array( 'numberposts' => -1, 'post_type' => $post_type );
		$posts = get_posts( $args );
	?>
		<label for="post"><?php _e( 'Choose the post title that you want to move this comment to', 'tako_lang')?></label>
		<select name="tako_post" id="tako_post">
		<?php foreach( $posts as $post ) : setup_postdata( $post ); ?>
			<option value="<?php echo $post->ID; ?>" <?php if ( $post->ID == $post_id ) echo 'selected'; ?>><?php echo $post->post_title ?></option>
		<?php endforeach; ?>

	<?php
        die();
	}
}
$tako = new Tako();
?>
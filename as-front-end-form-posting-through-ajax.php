<?php 
/*
* Plugin Name: As front-end-form-posting-through-ajax
*/

add_action('wp_enqueue_scripts', 'add_scripts_in_theme');
function add_scripts_in_theme() {
	wp_register_script('post-through-ajax', plugin_dir_url(__FILE__). 'as-front-end-form-posting-through-ajax.js', array(jquery), '', true  );
	wp_localize_script('post-through-ajax', 'as_params', array('as_ajax_url' => admin_url('admin-ajax.php')));
	wp_enqueue_script( 'post-through-ajax' );
}


add_shortcode('As_Front_End_Form', 'display_front_end_form');
function display_front_end_form() {
	?>
	<form name="upload_form" id="upload_form" method="POST" enctype="multipart/form-data">
		<input type="text" name="title" id="title">
		<input type="text" name="content" id="content">
		<input type="file" name="images[]" id="images" accept="image/*" multiple>
		<?php wp_nonce_field('image_upload', 'image_upload_nonce');?>
		<input type="submit" value="upload">
	</form>
	<span id="status"></span>
	<ul id="images_wrap">
		<!-- Images will be added here -->
	</ul>
	<?php
}

if ( ! function_exists( 'upload_user_file' ) ) :
	function upload_user_file( $file = array(), $title = false ) {
		require_once ABSPATH.'wp-admin/includes/admin.php';
		$file_return = wp_handle_upload($file, array('test_form' => false));
		if(isset($file_return['error']) || isset($file_return['upload_error_handler'])){
			return false;
		}else{
			$filename = $file_return['file'];
			$attachment = array(
				'post_mime_type' => $file_return['type'],
				'post_content' => '',
				'post_type' => 'attachment',
				'post_status' => 'inherit',
				'guid' => $file_return['url']
			);
			if($title){
				$attachment['post_title'] = $title;
			}

			// Add new post
			$currentUserID = get_current_user_id();	// If user not login then user Id will set to 0(int)
			$taxonomyId = 3;
			$newPost = array(
			'post_title'    => wp_strip_all_tags( $_POST['post-title'] ),
			'post_content'  => $_POST['post-content'],
			'post_type'		=> 'dummy',
			'post_status'   => 'publish',
			'post_author'   => $currentUserId,
			//'post_category' => array( 8,39 )
			);
			// Insert the post into the database
			$newPostId = wp_insert_post( $newPost );
			wp_set_post_terms( $newPostId, $taxonomyId, 'dummy_taxo' );
			/* Setup post meta data */
			$postMeta['dummy_meta_field'] = 'Dummy Post Meta';
			$asMetaKey = key($postMeta);
			$asMetaValue = $postMeta[$asMetaKey];
			add_post_meta($newPostId, $asMetaKey, $asMetaValue, true);
			/* Setup post meta data */
			//Add new post

			$attachment_id = wp_insert_attachment( $attachment, $filename, $newPostId );
			require_once(ABSPATH . 'wp-admin/includes/image.php');
			
			$attachment_data = wp_generate_attachment_metadata( $attachment_id, $filename );
			wp_update_attachment_metadata( $attachment_id, $attachment_data );
			if( 0 < intval( $attachment_id ) ) {
				return $attachment_id;
			}
		}
		return false;
	}
endif;


/**
 * Rearray $_FILES array for easy use
 *
 */
 
if ( ! function_exists( 'reArrayFiles' ) ) :
	function reArrayFiles(&$file_post) {
	    $file_ary = array();
	    $file_count = count($file_post['name']);
	    $file_keys = array_keys($file_post);
	    for ($i=0; $i<$file_count; $i++) {
	        foreach ($file_keys as $key) {
	            $file_ary[$i][$key] = $file_post[$key][$i];
	        }
	    }
	    return $file_ary;
	}
endif;


add_action( 'wp_ajax_upload_images', 'upload_images_callback' );
add_action( 'wp_ajax_nopriv_upload_images', 'upload_images_callback' );
if ( ! function_exists( 'upload_images_callback' ) ) :
	function upload_images_callback() {
		$data = array();
		
		$attachment_ids = array();
		if( isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], 'image_upload' ) ){
			$files = reArrayFiles($_FILES['files']);
			if ( empty($_FILES['files']) ) {
				$data['status'] = false;
				$data['message'] = __('Please select an image to upload!','twentysixteen');
			} elseif ( $files[0]['size'] > 5242880 ) { // Maximum image size is 5M
				$data['size'] = $files[0]['size'];
				$data['status'] = false;
				$data['message'] = __('Image is too large. It must be less than 2M!','twentysixteen');
			} else {
				$i = 0;
				$data['message'] = '';
				foreach( $files as $file ){
					if( is_array($file) ){
						$attachment_id = upload_user_file( $file, false );
						
						if ( is_numeric($attachment_id) ) {
							$img_thumb = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );
							$data['status'] = true;
							$data['message'] .= 
								'<li id="attachment-'.$attachment_id.'">
									<img src="'.$img_thumb[0].'" alt="" />
								</li>';
							$attachment_ids[] = $attachment_id;
							$data['msg'] = $_POST['post-title'];
						}
					}
					$i++;
				}
				if( ! $attachment_ids ){
					$data['status'] = false;
					$data['message'] = __('An error has occured. Your image was not added.','twentysixteen');
				}
			}
		} else {
			$data['status'] = false;
			$data['message'] = __('Nonce verify failed','twentysixteen');
		}
		echo json_encode($data);
		die();
	}
endif;
 ?>
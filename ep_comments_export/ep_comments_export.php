<?php
/*
Plugin Name: EP Comments Export
Description: Simple plugin to export comments and the comment meta data from your blog to a csv file that can be save on your local computer.
Version: 0.1.0
Author: Mattias Hedman, Earth People AB
*/

add_action('admin_init', 'Init::epce_add_to_post_table');

class Init {
	/**
	* Load the style and javascript files only in wp-admin
	*
	* @access public
	*/
	function epce_add_to_post_table() {
		global $wpdb; // Load wordpress database helper
		
		// Get all custom post types
		$post_types = $wpdb->get_results("SELECT post_type
			FROM wp_posts
			WHERE post_type != 'attachment'
			AND post_type != 'revision'
			AND post_type != 'nav_menu_item'
			AND post_type != 'mediapage'
			AND post_type != 'post'
			AND post_type != 'page'
			GROUP BY post_type",ARRAY_A
		);

		// Add export to user added custom post type
		foreach($post_types as $type) {
			add_filter('manage_edit-'.$type['post_type'].'_columns', 'Init::add_epce_column');
			add_action('manage_'.$type['post_type'].'posts_custom_column', 'epce::epce_column_content');
		}
		
		// Add export to default wordpress post type
		add_filter('manage_edit-post_columns', 'Init::add_epce_column');
		add_action('manage_posts_custom_column', 'epce::epce_column_content');
		
		// Add export to default wordpress page type
		add_filter('manage_edit-page_columns', 'Init::add_epce_column');
		add_action('manage_pages_custom_column', 'epce::epce_column_content');
		
		// Load JS and Style sheet
		add_action('admin_enqueue_scripts', 'Init::epce_script');
	}
	
	/**
	* Add a new custom column to the wp post list view
	*
	* @access public
	* @param $column - gets all the columns in the wp post view
	*/
	function add_epce_column($columns) {
		return array_merge($columns, array('epce_comment_export' => '<img src="'.plugins_url('export_deactive.png', __FILE__).'" alt="" />'));
	}
	
	/**
	* Load the style and javascript files only in wp-admin
	*
	* @access public
	*/
	function epce_script() {	
		wp_register_script('epce_js', plugins_url('script.js', __FILE__));
		wp_enqueue_script('epce_js');
		wp_register_style('epce_style', plugins_url('style.css', __FILE__));
		wp_enqueue_style('epce_style');
	}
}

class epce {
	/**
	* Adds content into our new custom column
	*
	* @access public
	* @param $column - gets all the columns in the wp post view
	*/
	function epce_column_content($column) {
		global $wpdb; // Load wordpress database helper
		global $post; // Load wordpress post helper
		
		switch($column) {
			case 'epce_comment_export': 			
				// If the GET value of epce is export, lets export
				if($_GET['epce'] == 'export') {
					
					// Run function that will create the CSV file for us
					$csv = epce::epce_create_csv($_GET['post'],$_GET['key']);
					
					// If the CSV file is created with success.
					if($csv['status'] == 'success' && $csv['id'] == $post->ID) {
						// Add a hidden post field with the url to the CSV
						echo '<input type="hidden" class="success" value="'.$csv['file'].'" name="file"/>';
					}
					// If the CSV file creation faled we give and error.
					elseif($csv['status'] == 'fail' && $csv['id'] == $post->ID) {
						echo '<input type="hidden" class="fail" value="For some reason the CSV file could not be created! If you keep getting this problem, please contact the plugin creators." name="fail"/>';
					}
				}
				
				$db_prefix = $wpdb->base_prefix; // Get used database prefix
				$db_pw = $wpdb->dbpassword; // get used database password. TThis is used as salt in the hash key.
				
				// Count the amount of comments
				$comments = count($wpdb->get_results("SELECT comment_id FROM ".$db_prefix."comments WHERE comment_post_ID = '$post->ID'"));
				
				// If there are comments to the post, display the active export icon
				if($comments > 0) {
					$key = md5(date('Y-m-d-H').$db_pw);
					if($_GET['post_type']) {
						echo '<a href="?post_type='.$_GET['post_type'].'&amp;epce=export&amp;post='.$post->ID.'&amp;key='.$key.'" id="epce_active"><!-- img src="'.plugins_url('export_active.png', __FILE__).'" alt="" / --></a>';
					} else {
						echo '<a href="?epce=export&amp;post='.$post->ID.'&amp;key='.$key.'" id="epce_active"><!-- img src="'.plugins_url('export_active.png', __FILE__).'" alt="" / --></a>';
					}
				}
				// If there are no comments, display an inactive icon
				else {
					echo '<img src="'.plugins_url('export_deactive.png', __FILE__).'" alt="" />';
				}
				
			break;
		}
	}
	
	/**
	* Create the CSV for download
	*
	* @access private
	* @param $post_id - The posts ID in database
	* @param $key - md5 and salted hash key
	*/
	private function epce_create_csv($post_id, $key) {
		global $wpdb; // Load wordpress database helper
		$db_prefix = $wpdb->base_prefix; // Get used database prefix
		$db_pw = $wpdb->dbpassword; // Get used database password. This is used as salt in the hash key.
		
		// Check if hash key is valid
		$check_key = md5(date('Y-m-d-H').$db_pw);
		if($_GET['key'] == $check_key) {
			$post_ID = $_GET['post'];
			
			// Get all comments and it's meta data from database
			$comments = $wpdb->get_results("
				SELECT wp_comments.*,
				GROUP_CONCAT(wp_commentmeta.meta_id SEPARATOR '|epce|') AS meta_id_array,
				GROUP_CONCAT(wp_commentmeta.comment_id SEPARATOR '|epce|') AS comment_id_array,
				GROUP_CONCAT(wp_commentmeta.meta_key SEPARATOR '|epce|') AS meta_key_array,
				GROUP_CONCAT(wp_commentmeta.meta_value SEPARATOR '|epce|') AS meta_value_array
				FROM wp_comments
				LEFT JOIN wp_commentmeta ON wp_comments.comment_ID = wp_commentmeta.comment_id
				WHERE wp_comments.comment_post_ID = '$post_ID'
				GROUP BY wp_comments.comment_ID",ARRAY_A
			);
			
			// Split up the comments meta data into new array, seperated from the comments array
			foreach($comments as $key => $comment) {
				if(!empty($comment['meta_key_array'])){
					$meta_id = explode('|epce|',$comment['meta_id_array']);
					$meta_comment_id = explode('|epce|',$comment['comment_id_array']);
					$meta_key = explode('|epce|',$comment['meta_key_array']);
					$meta_value = explode('|epce|',$comment['meta_value_array']);
					
					$i=0;
					foreach($meta_id as $key => $id) {
						$metadata[$comment['comment_ID']][$i]['meta_id'] = $id;
						$metadata[$comment['comment_ID']][$i]['meta_comment_id'] = $meta_comment_id[$key];
						$metadata[$comment['comment_ID']][$i]['meta_key'] = $meta_key[$key];
						$metadata[$comment['comment_ID']][$i]['meta_value'] = $meta_value[$key];
						$i++;
					}
				}
			}
			
			// Delete old meta data values from the comments array and add the new meta data array
			foreach($comments as $key => $comment) {
				unset($comments[$key]['meta_id_array']);
				unset($comments[$key]['comment_id_array']);
				unset($comments[$key]['meta_key_array']);
				unset($comments[$key]['meta_value_array']);

				if(!empty($metadata[$comments[$key]['comment_ID']])) {
					$i=0;
					foreach($metadata[$comments[$key]['comment_ID']] as $meta) {
						$comments[$key]['meta_id_'.$i] = $metadata[$comments[$key]['comment_ID']][$i]['meta_id'];
						$comments[$key]['meta_comment_id_'.$i] = $metadata[$comments[$key]['comment_ID']][$i]['meta_comment_id'];
						$comments[$key]['meta_key_'.$i] = $metadata[$comments[$key]['comment_ID']][$i]['meta_key'];
						$comments[$key]['meta_value_'.$i] = $metadata[$comments[$key]['comment_ID']][$i]['meta_value'];
						$i++;
					}
				}
			}
			

			$upload_dir = wp_upload_dir(); // Get upload path from Wordpress
			
			// Path and name for the CSV file
			$comments_csv = $upload_dir['basedir'].'/epce/comments-post'.$post_id.'-'.date('Ymd').'.csv';

			// If the EPCE folder don't exists, create it
			if(!file_exists($upload_dir['basedir'].'/epce')) {
				mkdir($upload_dir['basedir'].'/epce',0777);
			}

			// Create and write to the CSV file
			$csvfile = fopen($comments_csv,'w');
			foreach($comments as $comment) {
				fputcsv($csvfile,$comment);
			}
			fclose($csvfile);

			/*
			// Code to put the CSV in a ZIP file
			$zip = new ZipArchive;
			if ($zip->open($upload_dir['basedir'].'/epce/ep_comment_export-post'.$post_id.'-'.date('Ymd').'.zip',ZipArchive::CREATE) === TRUE) {
				$zip->addFile($comments_csv, 'comments-post'.$post_id.'-'.date('Ymd').'.csv');
				$zip->close();
			}
			*/
			
			// Return the result
			return array('status' => 'success', 'id' => $post_id, 'file' => $upload_dir['baseurl'].'/epce/comments-post'.$post_id.'-'.date('Ymd').'.csv');
		}
		// If the hash key is invalid, send error.
		else {
			return array('status' => 'fail', 'id' => $post_id);
		}
	}
}

?>
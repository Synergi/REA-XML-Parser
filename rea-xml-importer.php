<?php

/*
Plugin Name: Property Inserter & Updater
Plugin URI: http://telosbrand.com
Description: A plugin to insert, update properties from REAXML files. 
Version: 1.0
Original Author: Ben Dougherty
Original Author URI: http://www.devblog.com.au
Author: Sathesh Kumar
Author URI: http://telosbrand.com
Pre-requisites: 
	- Configure the $post_type variable if necessary.
	- Configure categories
	- Configure max.number of images to be imported
Configuration:
	- Go through all the variables within db_importer_load_properties() function and change them if necessary

*/
$realpath = realpath('./');			

define( 'PLUGINNAME_PATH', plugin_dir_path(__FILE__) );
define( 'ABSPATH ', $realpath );
define( 'MAX_IMAGES', '30' );

register_activation_hook(__FILE__, 'db_importer_activation');
register_deactivation_hook(__FILE__, 'db_importer_deactivation');
//add_action('db_importer_run_hourly', 'db_importer_process_properties');

$result = array();
$dfs_result = array();

 
/**
 * Schedules our events on plugin activation
 */
function db_importer_activation() {
  wp_schedule_event( current_time( 'timestamp' ), 'hourly', 'db_importer_run_hourly');
}
 
/**
 * Remove any scheduled events on plugin deactivation
 */
function db_importer_deactivation() {
  wp_clear_scheduled_hook('db_importer_run_hourly');
}
 
/**
 * Runs Hourly
 */
function db_importer_run_hourly() {
	db_importer_load_properties();
}


//add_action('after_setup_theme', 'db_importer_load_properties');


function db_importer_load_properties() {

  /* The location of our data directory */
  $xml_dir = PLUGINNAME_PATH."/data";
 
  /* Where our failed files will be moved */
  $failed_dir = PLUGINNAME_PATH."/data/failed";
 
  /* Where our successfully processed xml files will be moved */
  $processed_dir = PLUGINNAME_PATH."/data/processed";
 
  /* Files to exclude in our data folder */
  $excluded_data_files = array(".", "..", ".ftpquota", "processed", "failed");
 
  /* Residential Catgegory */
  $residential_cat_text = 'residential';
  $residential_cat = get_cat_ID( $residential_cat_text );
  
  /* Business Catgegory */
  $business_cat_text = 'business';
  $business_cat = get_cat_ID( $business_cat_text );
  
  /* Rental Catgegory */
  $rental_cat_text = 'rental';
  $rental_cat = get_cat_ID( $rental_cat_text );
  
  /* Commercial Catgegory */
  $commercial_cat_text = 'commercial';
  $commercial_cat = get_cat_ID( $commercial_cat_text );
  
  /* Commercial Land Catgegory */
  $commercialLand_cat_text = 'commercialLand';
  $commercialLand_cat = get_cat_ID( $commercialLand_cat_text );
  
  /* Holiday Rental Catgegory */
  $holidayRental_cat_text = 'holidayRental';
  $holidayRental_cat = get_cat_ID( $holidayRental_cat_text );
  
  /* Land Rental Catgegory */
  $land_cat_text = 'land';
  $land_cat = get_cat_ID( $land_cat_text );
  
  /* Rural Catgegory */
  $rural_cat_text = 'rural';
  $rural_cat = get_cat_ID( $rural_cat_text );
  
  /* My user ID */
  $user_id = 1;	
 
  /* Post Type */
  $post_type = "property";
 
  //Included our REA_XML class
  include(PLUGINNAME_PATH."/rea_xml/rea_xml.class.php");
  $rea = new REA_XML($debug=true); //create in debug mode
 
  //set some additional excluded files
  $excluded_files = array("processed", "failed", ".DS_Store", ".ftpquota");
 
  //parse the whole directory
  $properties = $rea->parse_directory($xml_dir, $processed_dir, $failed_dir, $excluded_files);

  //Insert all properties  
  if ( !empty( $properties[$rental_cat_text] ) ) process_properties($properties[$rental_cat_text], $rental_cat, $user_id, $post_type);
  if ( !empty( $properties[$residential_cat_text] ) ) process_properties($properties[$residential_cat_text], $residential_cat, $user_id, $post_type);
  if ( !empty( $properties[$business_cat_text] ) ) process_properties($properties[$business_cat_text], $business_cat, $user_id, $post_type);
  if ( !empty( $properties[$commercial_cat_text] ) ) process_properties($properties[$commercial_cat_text], $commercial_cat, $user_id, $post_type);
  if ( !empty( $properties[$commercialLand_cat_text] ) ) process_properties($properties[$commercialLand_cat_text], $commercialLand_cat, $user_id, $post_type);
  if ( !empty( $properties[$holidayRental_cat_text] ) ) process_properties($properties[$holidayRental_cat_text], $holidayRental_cat, $user_id, $post_type);
  if ( !empty( $properties[$land_cat_text] ) ) process_properties($properties[$land_cat_text], $land_cat, $user_id, $post_type);
  if ( !empty( $properties[$rural_cat_text] ) ) process_properties($properties[$rural_cat_text], $rural_cat, $user_id, $post_type);
  
}


/**
 * Based on each property status, this function will take care of insert & update functions
 *
 * @param		mixed			$properties
 * @param		string 			$category_id
 * @param		string	 		$user_id
 * @param		string			$post_type
 *
 * Returns NULL
 */
function process_properties ($properties, $category_id, $user_id, $post_type) {
  
  if($properties) {
  
    if(count($properties) > 0) {
    
      $count = 0;
      foreach($properties as $property) {
      	
      	$result_property_post_meta_uniqueID = get_property_post_meta_uniqueID ( $property['values']['uniqueID']['value'] );   // Get all the post meta
    	
      	switch ( $property['attributes']['status'] ) {
      	
			// The property has not yet been sold (or leased), and is still on offer. Should check if the property exists or new. Update or create new. 
      		case 'current': 
				
      			if ( !empty( $result_property_post_meta_uniqueID ) ) {

      				$postID = $result_property_post_meta_uniqueID[0]->post_id;
					$result_property_post_meta_postID = get_property_post_meta_postID ($postID);  // Get the original ModTime based on the postID
					
					$result_time_difference = check_property_time_difference ($result_property_post_meta_postID[0]->meta_value, $property['attributes']['modTime']);
					
      				if ( $result_time_difference ) {
						
						// update required
						$result_update = db_importer_update_property ($property, $category_id, $user_id, $post_type, $postID, 'publish');
						
						
						if ($result_update != false) {
							
						}
					}
					
      			} else {
      				$result_insert = db_importer_insert_property($property, $category_id, $user_id, $post_type);
      			}
      			
      			
      		break;
      		
      		
      		// The property has been sold. Should check if the property exists and update the status if it exists, if it doesn't exist, don't worry. 
      		case 'sold':

      			if ( !empty( $result_property_post_meta_uniqueID ) ) {

      				$postID = $result_property_post_meta_uniqueID[0]->post_id;
					$result_property_post_meta_postID = get_property_post_meta_postID ($postID);  // Get the original ModTime based on the postID
					
					$result_time_difference = check_property_time_difference ($result_property_post_meta_postID[0]->meta_value, $property['attributes']['modTime']);
					
      				if ( $result_time_difference ) {
						// update required
						$result_update = db_importer_update_property_sold ($property, $category_id, $user_id, $post_type, $postID, 'publish');
					}
      				
      			}
      			
      		break;
      		
      		
      		// The property is withdrawn from the market for whatever reason. Should check if the property exists and unpublish it. If it doesn't exist, don't worry.
      		case 'withdrawn':
      			
      			if ( !empty( $result_property_post_meta_uniqueID ) ) {

      				$postID = $result_property_post_meta_uniqueID[0]->post_id;
					$result_property_post_meta_postID = get_property_post_meta_postID ($postID);  // Get the original ModTime based on the postID
					
					$result_time_difference = check_property_time_difference ($result_property_post_meta_postID[0]->meta_value, $property['attributes']['modTime']);
					
      				if ( $result_time_difference ) {
						// update required
						$result_update = db_importer_update_property_status ($property, $category_id, $user_id, $post_type, $postID, 'draft');
					}
      				
      			}
      			
      		break;
      		
      		// This property is not currently on the market. Should check if the property exists and update the status if it exists, if it doesn't exist, don't worry.
      		case 'offmarket':
      			
      			if ( !empty( $result_property_post_meta_uniqueID ) ) {

      				$postID = $result_property_post_meta_uniqueID[0]->post_id;
					$result_property_post_meta_postID = get_property_post_meta_postID ($postID);  // Get the original ModTime based on the postID
					
					$result_time_difference = check_property_time_difference ($result_property_post_meta_postID[0]->meta_value, $property['attributes']['modTime']);
					
      				if ( $result_time_difference ) {
						// update required
						$result_update = db_importer_update_property_status ($property, $category_id, $user_id, $post_type, $postID, 'offmarket');
					}
      				
      			}
      			
      		break;
      		
      		// The property has been leased or rented. Should check if the property exists and update the status if it exists, if it doesn't exist, don't worry
      		case 'leased':
      			
      			if ( !empty( $result_property_post_meta_uniqueID ) ) {

      				$postID = $result_property_post_meta_uniqueID[0]->post_id;
					$result_property_post_meta_postID = get_property_post_meta_postID ($postID);  // Get the original ModTime based on the postID
					
					$result_time_difference = check_property_time_difference ($result_property_post_meta_postID[0]->meta_value, $property['attributes']['modTime']);
					
      				if ( $result_time_difference ) {
						// update required
						$result_update = db_importer_update_property_status ($property, $category_id, $user_id, $post_type, $postID, 'leased');
					}
      				
      			}
      		break;
      	}

	  }
	}
  }
}




/**
 * Inserts a property into the DB with it's meta information
 *
 * @param		mixed			$property
 * @param		string 			$category
 * @param		string	 		$user_id
 * @param		string			$post_type
 * @param		int 			$postID
 * @param		string	 		$status
 *
 * Returns boolean true or false based on update
 */
function db_importer_insert_property ($property, $category, $user_id, $post_type) {
  
  $result = true; //if properties were added from this file
  global $wp_taxonomies;
	
	if ($property) {

		// Match REA Agent Id with Wordpress userID
		$REA_Agent_ID = $property['values']['agentID']['value'];
		$result_property_user_meta_userID = get_property_user_meta_userID ($REA_Agent_ID);
		
		if ($result_property_user_meta_userID[0]->user_id != '') {
			$user_id = (int) $result_property_user_meta_userID[0]->user_id;
		}
		
        //setup post
        $new_post = array(
        	'post_title' => $property['values']['address']['value']['streetNumber']['value'].', '.$property['values']['address']['value']['street']['value'].', '.$property['values']['address']['value']['streetNumber']['value'].', '.strtolower($property['values']['address']['value']['suburb']['value']).', '.$property['values']['address']['value']['state']['value'].', '.$property['values']['address']['value']['postcode']['value'],
	        'post_content' => $property['values']['description']['value'],
    	    'post_status' => 'publish',
        	'post_date' => date('Y-m-d H:i:s'),
	        'post_author' => $user_id,
    	    'post_type' => $post_type,
     	 );
 	  	
      $post_id = wp_insert_post($new_post);	//insert post

      if($post_id != 0) {
      	
      	$result_post_cat = wp_set_post_categories ($post_id,array($category));  // This actually works but it doesn't update the custom taxonomy. Instead it updates the posts' category
      	
      	for ($i=0; $i <= MAX_IMAGES; $i++) {
      		
      		// Images
      		if ( isset ( $property['values']['images']['value']['images'][$i]['url'] ) && $i == 0) {      			
      			$result_insert = custom_wp_attach_external_image( $property['values']['images']['value']['images'][$i]['url'], $post_id, true );
      		} else if ( isset ( $property['values']['images']['value']['images'][$i]['url'] ) ) {
      			$result_insert = custom_wp_attach_external_image( $property['values']['images']['value']['images'][$i]['url'], $post_id, false );
      		}
      		
      		// FloorPlans
      		if ( isset ( $property['values']['objects']['value']['floorplan'][$i]['url'] ) ) {      			
      			$result_insert = custom_wp_attach_external_image( $property['values']['objects']['value']['floorplan'][$i]['url'], $post_id, false );
      		}
      	}
		
        // build meta keys and values from the property array 
		$result_dfs = dfs ($property);
		
		//add metadata
		foreach ( $result_dfs as $key => $value ) {
			add_post_meta($post_id, "_".$key, esc_attr( $value ) );
		}
		
		// insert user meta. user meta is inserted in to the post meta as well.
		if (isset( $property['values']['listingAgent']['value']['0']['telephone'] )) add_user_meta( $user_id, '_office', $property['values']['listingAgent']['value']['0']['telephone']);
		if (isset( $property['values']['listingAgent']['value']['0']['twitterURL'] )) add_user_meta( $user_id, '_twitterURL', $property['values']['listingAgent']['value']['0']['twitterURL']);
		if (isset( $property['values']['listingAgent']['value']['0']['facebookURL'] )) add_user_meta( $user_id, '_facebookURL', $property['values']['listingAgent']['value']['0']['facebookURL']);
		if (isset( $property['values']['listingAgent']['value']['0']['linkedInURL'] )) add_user_meta( $user_id, '_linkedInURL', $property['values']['listingAgent']['value']['0']['linkedInURL']);		
		
      } else {
        feedback("post was failed to add");
      }
  }
  else {
    feedback("No properties were selected");
    $result = false;
  }   
  return $result;
}


/**
 * @param		mixed			$property
 * @param		mixed 			$category
 * @param		string	 		$user_id
 * @param		string			$post_type
 * @param		int 			$postID
 * @param		string	 		$status
 *
 * Returns boolean true or false based on update
 */
function db_importer_update_property ($property, $category, $user_id, $post_type, $postID, $status) {

  $result = true;//if properties were added from this file
	
	if ($property) {
		
		// Match REA Agent Id with Wordpress userID
		$REA_Agent_ID = $property['values']['agentID']['value'];
		$result_property_user_meta_userID = get_property_user_meta_userID ($REA_Agent_ID);
		
		if ($result_property_user_meta_userID[0]->user_id != '') {
			$user_id = (int) $result_property_user_meta_userID[0]->user_id;
		}

        //setup post
        $update_post = array(
        'ID' => $postID,
        'post_title' => $property['values']['address']['value']['streetNumber']['value'].', '.$property['values']['address']['value']['street']['value'].', '.$property['values']['address']['value']['streetNumber']['value'].', '.strtolower( $property['values']['address']['value']['suburb']['value'] ).', '.$property['values']['address']['value']['state']['value'].', '.$property['values']['address']['value']['postcode']['value'],
        'post_content' => $property['values']['description']['value'],
        'post_status' => $status,
        'post_date' => date('Y-m-d H:i:s'),
        'post_author' => $user_id,
        'post_type' => $post_type,
        
     	 );
 
      	$post_id = wp_update_post($update_post);	//update post
      	if($post_id != 0) {
      	
			$result_post_cat = wp_set_post_categories ($post_id,array($category));  // This actually works but it doesn't update the custom taxonomy. Instead it updates the posts' category
			
			// Delete existing images based on the post ID
			$args = array(
				'post_type' => 'attachment',
				'numberposts' => MAX_IMAGES,
				'post_parent' => $post_id
			); 
			$attachments = get_posts($args);
			
			if ($attachments) {
				foreach ($attachments as $attachment) {
					$result_delete = wp_delete_attachment( $attachment->ID );
				}
			}
			
			// Insert images
			for ($i=0; $i <= MAX_IMAGES; $i++) {
			
				// Images
				if ( isset ( $property['values']['images']['value']['images'][$i]['url'] ) && $i == 0) {      			
					$result_insert = custom_wp_attach_external_image( $property['values']['images']['value']['images'][$i]['url'], $post_id, true );
				} else if ( isset ( $property['values']['images']['value']['images'][$i]['url'] ) ) {
					$result_insert = custom_wp_attach_external_image( $property['values']['images']['value']['images'][$i]['url'], $post_id, false );
				}

				// FloorPlans
				if ( isset ( $property['values']['objects']['value']['floorplan'][$i]['url'] ) ) {      			
					$result_insert = custom_wp_attach_external_image( $property['values']['objects']['value']['floorplan'][$i]['url'], $post_id, false );
				}

			}
			
			// build meta keys and values from the property array 
			$result_dfs = dfs ( $property );
			
			//add metadata
			foreach ( $result_dfs as $key => $value ) {
				update_post_meta ( $post_id, "_".$key, esc_attr ( $value ) );
			}
			
			feedback("updated property $title with post_id $post_id");
			
		} else {
	        feedback("post was failed to update");
	}
  }
  else {
    feedback("No properties were selected");
    
    $result = false;
    
  }   
  return $result;	

}


/**
 * Updates a single 'sold' property
 *   
 * @param		mixed			$property
 * @param		string 			$category
 * @param		string	 		$user_id
 * @param		string			$post_type
 * @param		int 			$postID
 * @param		string	 		$status
 *
 * Returns boolean true or false based on update
 */
function db_importer_update_property_sold ($property, $category, $user_id, $post_type, $postID, $status) {

  $result = true;//if properties were added from this file
	
	if ($property) {
		
		// Match REA Agent Id with Wordpress userID
		$REA_Agent_ID = $property['values']['agentID']['value'];
		$result_property_user_meta_userID = get_property_user_meta_userID ($REA_Agent_ID);
		
		if ($result_property_user_meta_userID[0]->user_id != '') {
			$user_id = (int) $result_property_user_meta_userID[0]->user_id;
		}
		
        //setup post
        $update_post = array(
        'ID' => $postID,
        'post_status' => $status,
        'post_date' => date('Y-m-d H:i:s'),
        'post_author' => $user_id,
        'post_type' => $post_type,
        
     	 );
 
      $post_id = wp_update_post($update_post);	//update post
      if($post_id != 0) {
      
      	$result_post_cat = wp_set_post_categories ($post_id,array($category));  // This actually works but it doesn't update the custom taxonomy. Instead it updates the posts' category
      
        // build meta keys and values from the property array 
		$result_dfs = dfs ( $property );
		
		//add metadata
		foreach ( $result_dfs as $key => $value ) {
			update_post_meta ( $post_id, "_".$key, esc_attr ( $value ) );
		}
 		
		feedback("updated property $title with post_id $post_id"); //feedback
		
      }
      
      else {
        feedback("post was failed to update");
      }
  }
  else {
  
    feedback("No properties were selected");
    $result = false;
    
  }   
  return $result;	

}
/**
 * Updates a single property status. Just status updates and nothing else!! 
 *   
 * @param		mixed			$property
 * @param		string 			$category
 * @param		string	 		$user_id
 * @param		string			$post_type
 * @param		int 			$postID
 * @param		string	 		$status
 *
 * Returns boolean true or false based on update
 */
function db_importer_update_property_status ($property, $category, $user_id, $post_type, $postID, $status) {

  $result = true;//if properties were added from this file
	
	if ($property) {
		
		// Match REA Agent Id with Wordpress userID
		$REA_Agent_ID = $property['values']['agentID']['value'];
		$result_property_user_meta_userID = get_property_user_meta_userID ($REA_Agent_ID);
		
		if ($result_property_user_meta_userID[0]->user_id != '') {
			$user_id = (int) $result_property_user_meta_userID[0]->user_id;
		}
		
        //setup post
        $update_post = array(
        'ID' => $postID,
        'post_status' => $status,
        'post_date' => date('Y-m-d H:i:s'),
        'post_author' => $user_id,
        'post_type' => $post_type,
        'post_category' => array($category)
        
     	 );
 
      $post_id = wp_update_post($update_post);	//update post
      if($post_id != 0) {
      
      	$result_post_cat = wp_set_post_categories ($post_id,array($category));  // This actually works but it doesn't update the custom taxonomy. Instead it updates the posts' category
      	
        //add metadata
        update_post_meta($post_id, "_attributes_modTime", esc_attr($property['attributes']['modTime']));
		update_post_meta($post_id, "_attributes_status", esc_attr($property['attributes']['status']));
 		
		feedback("updated property $title with post_id $post_id"); //feedback
		
      }
      
      else {
        feedback("post was failed to update");
      }
  }
  else {
    
    feedback("No properties were selected");
    $result = false;
    
  }   
  return $result;	

}

/**
 * Updates a single withdrawn property
 *   
 * @param		mixed			$property
 * @param		string 			$category
 * @param		string	 		$user_id
 * @param		string			$post_type
 * @param		int 			$postID
 * @param		string	 		$status
 *
 * Returns boolean true or false based on update
 */
function db_importer_update_property_withdrawn ($property, $category, $user_id, $post_type, $postID) {

  $result = true;//if properties were added from this file
	
	if ($property) {
		
		// Match REA Agent Id with Wordpress userID
		$REA_Agent_ID = $property['values']['agentID']['value'];
		$result_property_user_meta_userID = get_property_user_meta_userID ($REA_Agent_ID);
		
		if ($result_property_user_meta_userID[0]->user_id != '') {
			$user_id = (int) $result_property_user_meta_userID[0]->user_id;
		}
		
        //setup post
        $update_post = array (
        'ID' => $postID,
        'post_status' => 'draft',
        'post_date' => date('Y-m-d H:i:s'),
        'post_author' => $user_id,
        'post_type' => $post_type,
        
     	 );
 
      $post_id = wp_update_post($update_post);	//update post
      if($post_id != 0) {
      
      	$result_post_cat = wp_set_post_categories ($post_id,array($category));  // This actually works but it doesn't update the custom taxonomy. Instead it updates the posts' category
      	
        //add metadata
        update_post_meta($post_id, "_attributes_modTime", esc_attr($property['attributes']['modTime']));
		update_post_meta($post_id, "_attributes_status", esc_attr($property['attributes']['status']));
 		
		feedback("updated property $title with post_id $post_id"); //feedback
		
      }
      
      else {
        feedback("post was failed to add");
        //throw new Exception("Added $count properties");
      }
  }
  else {

    feedback("No properties were selected");
    $result = false;
    
  }   
  return $result;	

}


/**
 * Helper function to get post meta info from Wordpress database
 *
 * @param		int			$unique_id
 *
 * Returns property post meta info based on unique_id
 */
function get_property_post_meta_uniqueID ($unique_id) {
	
	global $wpdb;
    $results = array();
    $wpdb->query("
        SELECT post_id, meta_key, meta_value
        FROM $wpdb->postmeta
        where meta_key = '_values_uniqueID_value'
        AND meta_value = '$unique_id'
    ");
	
	return $wpdb->last_result;
}

/**
 * Helper function to get post meta info from Wordpress database
 *
 * @param		int			$postID
 *
 * Returns property post meta info based on postID
 */
function get_property_post_meta_postID ($postID) {
	
	global $wpdb;
    
    $results = array();
    $wpdb->query("
        SELECT meta_key, meta_value
        FROM $wpdb->postmeta
        where meta_key = '_attributes_modTime'
        AND post_id = '$postID'
    ");
    
    //foreach($wpdb->last_result as $key => $value) { $results[$value->meta_key] = $value->meta_value; }
    return $wpdb->last_result;
} 

/**
 * Helper function to get wordpress UserID with REA AGENT ID
 *
 * @param		string			$REA_Agent_ID
 *
 * Returns Wordpress userID that matches with REA Agent ID 
 */
function get_property_user_meta_userID ($REA_Agent_ID) {
	
	global $wpdb;
    
    $results = array();
    $wpdb->query("
        SELECT user_id 
        FROM wp_usermeta 
        WHERE meta_value = '$REA_Agent_ID'
    ");
    
    //foreach($wpdb->last_result as $key => $value) { $results[$value->meta_key] = $value->meta_value; }
    return $wpdb->last_result;
}


/**
 * Helper function to check time differences
 *
 * @param			String			$time1
 * @param			String			$time2
 *
 * Returns true or false based on time difference
 */
function check_property_time_difference ($time1, $time2) {

	$datetime = explode("-", $time1);
	$original_modTime = date( 'Y-m-d H:i:s', strtotime( $datetime[0].$datetime[1].$datetime[2]." ".$datetime[3] ) );

	$datetime = '';
	$datetime = explode("-", $time2);
	$difference_modTime = date( 'Y-m-d H:i:s', strtotime( $datetime[0].$datetime[1].$datetime[2]." ".$datetime[3] ) );
	
	if ( $original_modTime !== $difference_modTime ) { 
		return true; // yes, there is time difference
	} else {
		return false; // no, there isn't any time difference
	}
}



/**
 * Generic function to show a message to the user using WP's 
 * standard CSS classes to make use of the already-defined
 * message colour scheme.
 *
 * @param $message The message you want to tell the user.
 * @param $errormsg If true, the message is an error, so use 
 * the red message style. If false, the message is a status 
  * message, so use the yellow information message style.
 */
function feedback($message, $errormsg = false)
{
	if ($errormsg) {
		echo '<div id="message" class="error">';
	}
	else {
		echo '<div id="message" class="updated fade">';
	}

	echo "<p><strong>$message</strong></p></div>";
}  

/**
 * Helper function to convert the multi level array keys into single level underscore based keys with it's value
 *
 * @param			Mixed			$data
 * @param			String			$prefix
 *
 * Returns array in the format ( _firstlevel_secondlevel_thirdlevel_as_single_key => value )
 */ 
function dfs($data, $prefix = "") {
	
   global $dfs_result; 	
	
   if (is_array($data) && !empty($data)) {
      foreach ($data as $key => $value) {
        dfs($value, "{$prefix}_{$key}");
      }
   } else {
      $dfs_result[substr($prefix, 1)] = $data;
   }
   return $dfs_result;
}

/**
 * Helper function to insert images into Wordpress media filesystem
 *
 * @param			String			$url
 *
 * Returns the attachment URL
 *
 * @Deprecated
 */ 
function insert_images_into_media ($url) {
	
    $tmp = download_url( $url );
    $file_array = array(
        'name' => basename( $url ),
        'tmp_name' => $tmp
    );

    // Check for download errors
    if ( is_wp_error( $tmp ) ) {
        @unlink( $file_array[ 'tmp_name' ] );
        return $tmp;
    }

    $id = media_handle_sideload( $file_array, 0 );
    // Check for handle sideload errors.
    if ( is_wp_error( $id ) ) {
        @unlink( $file_array['tmp_name'] );
        return $id;
    }

    $attachment_url = wp_get_attachment_url( $id );
    
    return $attachment_url;
}


/**
 * Download an image from the specified URL and attach it to a post.
 * Modified version of core function media_sideload_image() in /wp-admin/includes/media.php  (which returns an html img tag instead of attachment ID)
 * Additional functionality: ability override actual filename, and to pass $post_data to override values in wp_insert_attachment (original only allowed $desc)
 *
 * @since 1.4 Somatic Framework
 *
 * @param string $url (required) The URL of the image to download
 * @param int $post_id (required) The post ID the media is to be associated with
 * @param bool $thumb (optional) Whether to make this attachment the Featured Image for the post (post_thumbnail)
 * @param string $filename (optional) Replacement filename for the URL filename (do not include extension)
 * @param array $post_data (optional) Array of key => values for wp_posts table (ex: 'post_title' => 'foobar', 'post_status' => 'draft')
 * @return int|object The ID of the attachment or a WP_Error on failure
 */
function custom_wp_attach_external_image( $url = null, $post_id = null, $thumb = null, $filename = null, $post_data = array() ) {
    if ( !$url || !$post_id ) return new WP_Error('missing', "Need a valid URL and post ID...");
    require_once( ABSPATH . 'wp-admin/includes/file.php' );
    // Download file to temp location, returns full server path to temp file, ex; /home/user/public_html/mysite/wp-content/26192277_640.tmp
    $tmp = download_url( $url );

    // If error storing temporarily, unlink
    if ( is_wp_error( $tmp ) ) {
        @unlink($file_array['tmp_name']);   // clean up
        $file_array['tmp_name'] = '';
        return $tmp; // output wp_error
    }

    preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $url, $matches);    // fix file filename for query strings
    $url_filename = basename($matches[0]);                                                  // extract filename from url for title
    $url_type = wp_check_filetype($url_filename);                                           // determine file type (ext and mime/type)

    // override filename if given, reconstruct server path
    if ( !empty( $filename ) ) {
        $filename = sanitize_file_name($filename);
        $tmppath = pathinfo( $tmp );                                                        // extract path parts
        $new = $tmppath['dirname'] . "/". $filename . "." . $tmppath['extension'];          // build new path
        rename($tmp, $new);                                                                 // renames temp file on server
        $tmp = $new;                                                                        // push new filename (in path) to be used in file array later
    }

    // assemble file data (should be built like $_FILES since wp_handle_sideload() will be using)
    $file_array['tmp_name'] = $tmp;                                                         // full server path to temp file

    if ( !empty( $filename ) ) {
        $file_array['name'] = $filename . "." . $url_type['ext'];                           // user given filename for title, add original URL extension
    } else {
        $file_array['name'] = $url_filename;                                                // just use original URL filename
    }

    // set additional wp_posts columns
    if ( empty( $post_data['post_title'] ) ) {
        $post_data['post_title'] = basename($url_filename, "." . $url_type['ext']);         // just use the original filename (no extension)
    }

    // make sure gets tied to parent
    if ( empty( $post_data['post_parent'] ) ) {
        $post_data['post_parent'] = $post_id;
    }

    // required libraries for media_handle_sideload
    require_once(ABSPATH . "wp-admin" . '/includes/media.php');
	require_once(ABSPATH . "wp-admin" . '/includes/file.php');
	require_once(ABSPATH . "wp-admin" . '/includes/image.php'); 

    // do the validation and storage stuff
    $att_id = media_handle_sideload( $file_array, $post_id, null, $post_data );             // $post_data can override the items saved to wp_posts table, like post_mime_type, guid, post_parent, post_title, post_content, post_status

    // If error storing permanently, unlink
    if ( is_wp_error($att_id) ) {
        @unlink($file_array['tmp_name']);   // clean up
        return $att_id; // output wp_error
    }

    // set as post thumbnail if desired
    if ($thumb) {
        set_post_thumbnail($post_id, $att_id);
    }

    return $att_id;
}


?>
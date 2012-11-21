<?php
/**
 *
 * Author: Sathesh Kumar
 * URL: http://www.Telosbrand.com, 
 * Original Author: Ben Dougherty
 * Original Author URLs:  http://www.devblog.com.au, 
 *	 					  http://www.mandurahweb.com.au
 *
 * Original Author Comments:
 * This code was written for a project by mandurahweb.com. Please give credit if you
 * use this code in any of your projects. You can see a write-up of this code been
 * used to create posts in WordPress here: http://www.devblog.com.au/rea-xml-parser-and-wordpress
 *
 * This code is licensed under with the GPL and may be used and distributed freely. 
 * You may fork the code make changes add extra features etc.
 *
 * Any changes to this code should be released to the open source community.
 *
 *
 * REA_XML allows you to easily retrieve an associative arary of properties
 * indexed by propertyList. Properties types as specified in the REAXML documentation
 * include:
 * 		residential
 *		rental
 *		land
 * 		rural
 *		commercial
 *		commercialLand
 *		business
 *
 * USAGE:
 * 		$rea = new REA_XML($debug=true); //uses default fields
 *		$properties = $rea->parse_dir($xml_file_dir, $processed_dir, $failed_dir, $excluded_files=array());
 * 
 * or 	$property = $rea->parse_file();
 *
 * For a full list of fields please see. http://reaxml.realestate.com.au/ and click 'Mandatory Fields'
 *
 * Modified author comments: 
 * 
 * Modifications to the original code: 
 * 	- Removed the fields variable. Made to read and include all the elements (fields) and atttributes from the XML file. Changes in main class & function 'parse_xml'. 
 *  - Added a function 'xml2array_parse' to convert simpleXMLElement(s) to Array
 */

class REA_XML {

	
	/* default files exluded when parsing a directory */
	private $default_excluded_files = array(".", "..");

	/* Keeps track of excluded files */
	private $excluded_files;

	function REA_XML($debug=true) {

		$this->debug = $debug; /* Set debug flag */
		
	}

	/*
	 * xml_string $xml_string
	 *
	 * Returns an associative array of properties keyed by property type
	 * for the XML string. XML string must be valid.
	 */
	function parse_xml($xml_string) {
	
		//feedback("parsing xml string");

		$properties = array();
		$properties_array = array();
		$xml = false;

		try {
			/* Create XML document. */

			/* Some of the xml files I receive were invalid. This could be due to a number
			 * of reasons. SimpleXMLElement still spits out some ugly errors even with the try
			 * catch so we supress them when not in debug mode
			 */
			 if($this->debug) {
				$xml = new SimpleXMLElement($xml_string);	 	
			 }	
			 else {
			 	@$xml = new SimpleXMLElement($xml_string);	
			 }		 
			
		}
		catch(Exception $e) {
			$this->feedback($e->getMessage());
		}

		/* Loaded the file */
		if($xml !== false) {

			/* Get property type */
			$property_root = $xml->xpath("/propertyList/*");
			if(isset($property_root[0])) {
				$property_type = $property_root[0]->getName();	
				
			}
			
			/* Some XML files don't even have a type and caused errors */
			if(!empty($property_type)) {
				/* Select the property type. */
				//$properties = $xml->xpath("/propertyList/$property_type");
				$properties = $xml->xpath("/propertyList/*");
			}
		}

		
		
		/* We have properties */
		if(is_array($properties) && count($properties) > 0) {
		
			/** 
			 * For every property, make it an array
			 * with Elements ( as Values ) and Attributes to be inserted into 
			 * Wordpress 'wp_postmeta' table
			 */			
			foreach($properties as $property) {
				
				$prop = array();//reset property
				$result_array = array();
				$result = array();
				$attr = array();
				$dfs_result = '';
				
				$single_property = xml2array_parse($property);
				
				$attr = $property->attributes();
				$status = $attr->status;
				$modTime = $attr->modTime;
				$property_type = $property->getName();
				
				$result_array['values'] = $single_property;
				$result_array['attributes']['status'] = (String) $status;
				$result_array['attributes']['modTime'] = (String) $modTime;
				$result_array['propertyType'] = (String) $property_type;
				
				$properties_array[$property_type][] = $result_array;
				
			}
		}
		//do_dump($properties_array);
		//var_dump ($properties_array);
 		return $properties_array;	
	}
	
	/**
	 * string $xml_file_dir
	 * string $processed_dir
	 * string $failed_dir
	 * string[] $excluded_files
	 *
	 * Returns an associative array of properties keyed by property type
	 */
	function parse_directory($xml_file_dir, $processed_dir=false, $failed_dir=false, $excluded_files=array()) {
		$properties = array();
		if(file_exists($xml_file_dir)) { 
			if($handle = opendir($xml_file_dir)) {
				
				//feedback("parsing directory $xml_file_dir");
				
				/* Merged default excluded files with user specified files */
				$this->excluded_files = array_merge($excluded_files, $this->default_excluded_files);

				/* Loop through all the files. */
				while(false !== ($xml_file = readdir($handle))) {

					/* Ensure it's not exlcuded. */
					if(!in_array($xml_file, $this->excluded_files)) {
						
						/* Get the full path */
						$xml_full_path = $xml_file_dir  . "/" . $xml_file;

						/* retrieve the properties from this file. */
						$prop = $this->parse_file($xml_full_path, $xml_file, $processed_dir, $failed_dir);

						if(is_array($prop) && count($prop) > 0) {

							/* We have to get the array key which is the property
							 * type so we can do a merge with $property[$property_type]
							 * otherwise our properties get overwritten when we try to merge
							 * properties of the same type which already exist.
							 */
							$array_key = array_keys($prop);
							
							for ( $i=0; $i<10; $i++ ) {
								
								if ( !empty ($array_key[$i]) ) {
									$property_type = $array_key[$i];
									
									if(!isset($properties[$property_type])) {
										//initialise
										$properties[$property_type] = array();
									}
		
									/* We need the array prop because it includes the property type */
									$properties[$property_type] = array_merge($prop[$property_type], $properties[$property_type]);
		
									//file loaded
									$file_loaded = true;
								}
							}
						}
						else {
							//feedback("no properties returned from file");
							throw new Exception("no properties returned from file");
						}						
					}
				}
				closedir($handle);
			}
			else {
				//feedback("Could not open directory");
				throw new Exception("Could not open directory");
			}	
		}
		else {
			throw new Exception("Directory $xml_file_dir could not be found");
		}
		//do_dump ($properties);
		return $properties;
	}

	/* Parse a REA XML File. */
	function parse_file($xml_full_path, $xml_file, $processed_dir=false, $failed_dir=false) {
		
		$properties = array();
		if(file_exists($xml_full_path)) {

			//feedback("parsing XML file $xml_file");

			/* Parse the XML file */
			$properties = $this->parse_xml(file_get_contents($xml_full_path));

			if(is_array($properties) && count($properties > 0)) {
				/* If a processed/removed directory was supplied then we move
				* the xml files accordingly after they've been processed
				*/
				if($processed_dir !== false) {
					if(file_exists($processed_dir)) {
						$this->xml_processed($xml_file, $xml_full_path, $processed_dir);		
					}
					else {
						//feedback("Processed dir: $processed_dir does not exist");
						throw new Exception("Processed dir: $processed_dir does not exist");
					}
					
				}		
			}
			else {
				if($failed_dir !== false) {
					if(file_exists($failed_dir)) {
						$this->xml_load_failed($xml_file, $xml_full_path, $failed_dir);	
					}
					else {
						feedback("Failed dir: $failed_dir does not exist");
						throw new Exception("Failed dir: $failed_dir does not exist");
					}
					
				}					
			}
		}
		else {
			throw new Exception("File could not be found");
		}

		return $properties;
	}

	/* Called if the xml file was processed */
	function xml_processed($xml_file, $xml_full_path, $processed_dir) {
		//do anything specific to xml_processed

		//move file
		$this->move_file($xml_file, $xml_full_path, $processed_dir);
	}

	/* Called if the xml file was not correctly processed */
	private function xml_load_failed($xml_file, $xml_full_path, $failed_dir) {
		//do anything specific to xml_failed

		//move file
		$this->move_file($xml_file, $xml_full_path, $failed_dir);
	}

	/* Moves a file to a new location */
	private function move_file($file, $file_full_path, $new_dir) {
		if(copy($file_full_path, $new_dir . "/$file")) {
			unlink($file_full_path);
		}
	}

	/* Reset excluded files */
	public function reset_excluded_files() {
		$this->excluded_files = $this->default_excluded_files;
	}

	/* Display Feedback if in debug mode */
	private function feedback($string) {
		if($this->debug) {
			print $string . "<br/>";
		}
	}
	
	
}


/**
 * Function to convert simpleXMLElement(s) into array for much easier usage while inserting or updating
 *
 * @param		string[]		$xml
 *
 * Returns the property in an array
 */
function xml2array_parse($xml){
	
	$return = array();

     foreach ($xml->children() as $parent => $child){
     	
     	foreach($xml->$parent->attributes() as $a => $b) {
     		// Different handling for listingAgents & businessCategory
     		if ($parent != 'listingAgent' && $parent != 'businessCategory') {
				$return["$parent"]['attributes'][$a] = (string) $b[0];
			}
			
		}
		
		$prop = array ();
		
		if ( $parent == 'images' ) { 
			
			// Multiple Images
			foreach($xml->images as $img) {
				$i=0;
				foreach($img as $value) {
					$attr = $value->attributes();
					if (isset($attr->id)) $prop[$i]['id'] = (String) $attr->id;
					if (isset($attr->url)) $prop[$i]['url'] = (String) $attr->url;
					if (isset($attr->format)) $prop[$i]['format'] = (String) $attr->format;
					if (isset($attr->modTime)) $prop[$i]['modTime'] = (String) $attr->modTime;
					$i++;
				}
			}

			$return["$parent"]['value']['images'] = $prop;
			
		} else if ( $parent == 'objects' ) {
			
			// Floor plans
			foreach($xml->objects as $img) {
				$i=0;
				if ($img->floorplan) {
					foreach($img as $value) {
						$attr = $value->attributes();
						if (isset($attr->id)) $prop[$i]['id'] = (String) $attr->id;
						if (isset($attr->url)) $prop[$i]['url'] = (String) $attr->url;
						if (isset($attr->format)) $prop[$i]['format'] = (String) $attr->format;
						if (isset($attr->modTime)) $prop[$i]['modTime'] = (String) $attr->modTime;
						$i++;
					}
				}
			}

			$return["$parent"]['value']['floorplan'] = $prop;
			
		} else if ($parent == 'listingAgent') {
			
			// Do nothing. Separate handling for listingAgents.
			
			
		} else if ($parent == 'inspectionTimes') {
			
			$i=0;
			
			// Multiple Inspection times
			foreach($xml->inspectionTimes as $inspection) {
				foreach ($inspection[$i] as $value) {
					if (isset($value)) $prop[$i]['inspection'] = (String) (String) $value;
					$i++;
				}
			}

			$return["$parent"]['value'] = $prop;
			
		} else if ($parent == 'businessCategory') {
			
			// Do nothing. Separate handling for businessCategory.
			
		} else {
		
	    	$return["$parent"]['value'] =  xml2array_parse($child)?xml2array_parse($child):"$child" ;
	    	
	    }	
        
     }
     
     // Listing Agents. Since there can be multiple Listing Agents on the same level, we'll have to process them separately
     $count_listing_agents = (int) count ($xml->children()->listingAgent);

     if ( $count_listing_agents >= 1) {
     	
		 foreach ($xml->children()->listingAgent as $agent) {
			
			$attr = $agent->attributes();
			$prop = array();
			$attributes = array();
				
			$attributes['id'] = (Int) $attr->id;
			if (isset($agent->agentID)) $prop['agentID'] = (String) $agent->agentID;
			if (isset($agent->name)) $prop['name'] = (String) $agent->name;
			if (isset($agent->telephone)) $prop['telephone'] = (String) $agent->telephone;
			if (isset($agent->email)) $prop['email'] = (String) $agent->email;
			if (isset($agent->twitterURL)) $prop['twitterURL'] = (String) $agent->twitterURL;
			if (isset($agent->facebookURL)) $prop['facebookURL'] = (String) $agent->facebookURL;
			if (isset($agent->linkedInURL)) $prop['linkedInURL'] = (String) $agent->linkedInURL;
			$return["listingAgent"]['value'][] = $prop;
			$return["listingAgent"]['attributes'][] = $attributes;
				
			$i++;
		 }
	}   
    
    // Business Category. Since there can be multiple business category on the same level, we'll have to process them separately
	$count_business_category = count ($xml->children()->businessCategory);
    if ( $count_business_category != 0 ) {
     
		 foreach ($xml->children()->businessCategory as $bizCat) {
			
			$attr = $bizCat->attributes();
			$prop = array();
			$attributes = array();
				
			$attributes['id'] = (Int) $attr->id;
			
			if (isset($bizCat->name)) $prop['name'] = (String) $bizCat->name;
			if (isset($bizCat->businessSubCategory->name)) $prop['businessSubCategoryName'] = (String) $bizCat->businessSubCategory->name;
			
			$return["businessCategory"]['value'][] = $prop;
			$return["businessCategory"]['attributes'][] = $attributes;
		
		 }
	}
    
    
     
     return $return;
} 
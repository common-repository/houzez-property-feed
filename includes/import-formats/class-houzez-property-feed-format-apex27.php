<?php
/**
 * Class for managing the import process of a Apex27 XML file
 *
 * @package WordPress
 */
if ( class_exists( 'Houzez_Property_Feed_Process' ) ) {

class Houzez_Property_Feed_Format_Apex27 extends Houzez_Property_Feed_Process {

	public function __construct( $instance_id = '', $import_id = '' )
	{
		$this->instance_id = $instance_id;
		$this->import_id = $import_id;

		if ( $this->instance_id != '' && isset($_GET['custom_property_import_cron']) )
	    {
	    	$current_user = wp_get_current_user();

	    	$this->log("Executed manually by " . ( ( isset($current_user->display_name) ) ? $current_user->display_name : '' ), '', 0, '', false );
	    }
	}

	public function parse()
	{
		$this->properties = array(); // Reset properties in the event we're importing multiple files

		$this->log("Parsing properties", '', 0, '', false);

		$import_settings = get_import_settings_from_id( $this->import_id );

		$contents = '';

		$response = wp_remote_get( $import_settings['xml_url'], array( 'timeout' => 360 ) );
		if ( !is_wp_error($response) && is_array( $response ) ) 
		{
			$contents = $response['body'];
		}
		else
		{
			$this->log_error( "Failed to obtain XML. Dump of response as follows: " . print_r($response, TRUE) );

        	return false;
		}

		$xml = simplexml_load_string($contents);

		if ($xml !== FALSE)
		{
			$statuses_to_exclude = apply_filters( 'houzez_property_feed_apex27_exclude_statuses', array('Pending', 'Let', 'Sold') );

			foreach ($xml->Listing as $property)
			{
                if ( !in_array((string)$property->Status, $statuses_to_exclude) )
				{
	                $this->properties[] = $property;
	            }
            } // end foreach property
        }
        else
        {
        	// Failed to parse XML
        	$this->log_error( 'Failed to parse XML file. Possibly invalid XML' );

        	return false;
        }

		if ( empty($this->properties) )
		{
			$this->log_error( 'No properties found. We\'re not going to continue as this could likely be wrong and all properties will get removed if we continue.' );

			return false;
		}

		return true;
	}

	public function import()
	{
		global $wpdb;

		$imported_ref_key = ( ( $this->import_id != '' ) ? '_imported_ref_' . $this->import_id : '_imported_ref' );
		$imported_ref_key = apply_filters( 'houzez_property_feed_property_imported_ref_key', $imported_ref_key, $this->import_id );

		$import_settings = get_import_settings_from_id( $this->import_id );

		$this->import_start();

		do_action( "houzez_property_feed_pre_import_properties", $this->properties, $this->import_id );
        do_action( "houzez_property_feed_pre_import_properties_apex27", $this->properties, $this->import_id );

        $this->properties = apply_filters( "houzez_property_feed_properties_due_import", $this->properties, $this->import_id );
        $this->properties = apply_filters( "houzez_property_feed_properties_due_import_apex27", $this->properties, $this->import_id );

        $limit = apply_filters( "houzez_property_feed_property_limit", 25 );
        $additional_message = '';
        if ( $limit !== false )
        {
        	$this->properties = array_slice( $this->properties, 0, $limit );
        	$additional_message = '. <a href="https://houzezpropertyfeed.com/#pricing" target="_blank">Upgrade to PRO</a> to import unlimited properties';
        }

		$this->log( 'Beginning to loop through ' . count($this->properties) . ' properties' . $additional_message );

		$start_at_property = get_option( 'houzez_property_feed_property_' . $this->import_id );

		$property_row = 1;
		foreach ( $this->properties as $property )
		{
			if ( !empty($start_at_property) )
			{
				// we need to start on a certain property
				if ( (string)$property->ID == $start_at_property )
				{
					// we found the property. We'll continue for this property onwards
					$this->log( 'Previous import failed to complete. Continuing from property ' . $property_row . ' with ID ' . (string)$property->ID );
					$start_at_property = false;
				}
				else
				{
					++$property_row;
					continue;
				}
			}

			update_option( 'houzez_property_feed_property_' . $this->import_id, (string)$property->ID, false );
			
			$this->log( 'Importing property ' . $property_row . ' with reference ' . (string)$property->ID, (string)$property->ID, 0, '', false );

			$this->ping(array('status' => 'importing', 'property' => $property_row, 'total' => count($this->properties)));

			$inserted_updated = false;

			$args = array(
	            'post_type' => 'property',
	            'posts_per_page' => 1,
	            'post_status' => 'any',
	            'meta_query' => array(
	            	array(
		            	'key' => $imported_ref_key,
		            	'value' => (string)$property->ID
		            )
	            )
	        );
	        $property_query = new WP_Query($args);

	        $display_address = (string)$property->DisplayAddress;

	        $post_content = ( isset($property->Description) && !empty((string)$property->Description) ) ? (string)$property->Description : '';
	        if ( empty($post_content) && isset($property->fullDescription) && !empty((string)$property->fullDescription) )
	        {
	        	$post_content = (string)$property->fullDescription;
	        }
	        
	        if ($property_query->have_posts())
	        {
	        	$this->log( 'This property has been imported before. Updating it', (string)$property->ID );

	        	// We've imported this property before
	            while ($property_query->have_posts())
	            {
	                $property_query->the_post();

	                $post_id = get_the_ID();

	                $my_post = array(
				    	'ID'          	 => $post_id,
				    	'post_title'     => wp_strip_all_tags( $display_address ),
				    	'post_excerpt'   => (string)$property->Summary,
				    	'post_content' 	 => $post_content,
				    	'post_status'    => 'publish',
				  	);

				 	// Update the post into the database
				    $post_id = wp_update_post( $my_post, true );

				    if ( is_wp_error( $post_id ) ) 
					{
						$this->log_error( 'Failed to update post. The error was as follows: ' . $post_id->get_error_message(), (string)$property->ID );
					}
					else
					{
						$inserted_updated = 'updated';
					}
	            }
	        }
	        else
	        {
	        	$this->log( 'This property hasn\'t been imported before. Inserting it', (string)$property->ID );

	        	// We've not imported this property before
				$postdata = array(
					'post_excerpt'   => (string)$property->Summary,
					'post_content' 	 => $post_content,
					'post_title'     => wp_strip_all_tags( $display_address ),
					'post_status'    => 'publish',
					'post_type'      => 'property',
					'comment_status' => 'closed',
				);

				$post_id = wp_insert_post( $postdata, true );

				if ( is_wp_error( $post_id ) ) 
				{
					$this->log_error( 'Failed to insert post. The error was as follows: ' . $post_id->get_error_message(), (string)$property->ID );
				}
				else
				{
					$inserted_updated = 'inserted';
				}
			}
			$property_query->reset_postdata();

			if ( $inserted_updated !== false )
			{
				// Inserted property ok. Continue

				if ( $inserted_updated == 'updated' )
				{
					// Get all meta data so we can compare before and after to see what's changed
					$metadata_before = get_metadata('post', $post_id, '', true);

					// Get all taxonomy/term data
					$taxonomy_terms_before = array();
					$taxonomy_names = get_post_taxonomies( $post_id );
					foreach ( $taxonomy_names as $taxonomy_name )
					{
						$taxonomy_terms_before[$taxonomy_name] = wp_get_post_terms( $post_id, $taxonomy_name, array('fields' => 'ids') );
					}
				}

				$this->log( 'Successfully ' . $inserted_updated . ' post', (string)$property->ID, $post_id );

				update_post_meta( $post_id, $imported_ref_key, (string)$property->ID );

				update_post_meta( $post_id, '_property_import_data', $property->asXML() );

				$department = 'residential-sales';
				if ( (string)$property->TransactionType == 'rent' || (string)$property->TransactionType == 'commercial_rent' )
				{
					$department = 'residential-lettings';
				}

				$poa = false;

				if ( $poa === true ) 
                {
                    update_post_meta( $post_id, 'fave_property_price', 'POA');
                    update_post_meta( $post_id, 'fave_property_price_postfix', '' );
                }
                else
                {
                	$price = round(preg_replace("/[^0-9.]/", '', (string)$property->Price));

                    update_post_meta( $post_id, 'fave_property_price_prefix', ( ( isset($property->PricePrefix) ) ? (string)$property->PricePrefix : '' ) );
                    update_post_meta( $post_id, 'fave_property_price', $price );
                    update_post_meta( $post_id, 'fave_property_price_postfix', '' );

                    if ( $department == 'residential-lettings' && isset($property->RentFrequency) )
                    {
                    	$rent_frequency = 'pcm';
						switch ((string)$property->RentFrequency)
						{
							case "W": { $rent_frequency = 'pw'; break; }
							case "Y": { $rent_frequency = 'pa'; break; }
						}

						update_post_meta( $post_id, 'fave_property_price_postfix', $rent_frequency );
                    }
                }

                update_post_meta( $post_id, 'fave_property_bedrooms', ( ( isset($property->Bedrooms) ) ? (string)$property->Bedrooms : '' ) );
	            update_post_meta( $post_id, 'fave_property_bathrooms', ( ( isset($property->Bathrooms) ) ? (string)$property->Bathrooms : '' ) );
	            update_post_meta( $post_id, 'fave_property_rooms', ( ( isset($property->Receptions) ) ? (string)$property->Receptions : '' ) );
	            update_post_meta( $post_id, 'fave_property_garage', '' ); // need to look at parking
	            update_post_meta( $post_id, 'fave_property_id', (string)$property->Reference );

	            $address_parts = array();
	            if ( isset($property->Address1) && (string)$property->Address1 != '' )
	            {
	                $address_parts[] = (string)$property->Address1;
	            }
	            if ( isset($property->Address2) && (string)$property->Address2 != '' )
	            {
	                $address_parts[] = (string)$property->Address2;
	            }
	            if ( isset($property->City) && (string)$property->City != '' )
	            {
	                $address_parts[] = (string)$property->City;
	            }
	            if ( isset($property->County) && (string)$property->County != '' )
	            {
	                $address_parts[] = (string)$property->County;
	            }
	            if ( isset($property->PostalCode) && (string)$property->PostalCode != '' )
	            {
	                $address_parts[] = (string)$property->PostalCode;
	            }

	            update_post_meta( $post_id, 'fave_property_map', '1' );
	            update_post_meta( $post_id, 'fave_property_map_address', implode(", ", $address_parts) );
	            $lat = '';
	            $lng = '';
	            if ( isset($property->Latitude) && !empty((string)$property->Latitude) )
	            {
	                update_post_meta( $post_id, 'houzez_geolocation_lat', (string)$property->Latitude );
	                $lat = (string)$property->Latitude;
	            }
	            if ( isset($property->Longitude) && !empty((string)$property->Longitude) )
	            {
	                update_post_meta( $post_id, 'houzez_geolocation_long', (string)$property->Longitude );
	                $lng = (string)$property->Longitude;
	            }
	            update_post_meta( $post_id, 'fave_property_location', $lat . "," . $lng . ",14" );
	            update_post_meta( $post_id, 'fave_property_country', 'GB' );
	            
	            $address_parts = array();
	            if ( isset($property->Address1) && (string)$property->Address1 != '' )
	            {
	                $address_parts[] = (string)$property->Address1;
	            }
	            update_post_meta( $post_id, 'fave_property_address', implode(", ", $address_parts) );
	            update_post_meta( $post_id, 'fave_property_zip', ( ( isset($property->PostalCode) ) ? (string)$property->PostalCode : '' ) );

	            update_post_meta( $post_id, 'fave_featured', ( ( isset($property->Featured) && (string)$property->Featured == '1' ) ? '1' : '0' ) );
	            update_post_meta( $post_id, 'fave_agent_display_option', ( isset($import_settings['agent_display_option']) ? $import_settings['agent_display_option'] : 'none' ) );

	            if ( 
	            	isset($import_settings['agent_display_option']) && 
	            	isset($import_settings['agent_display_option_rules']) && 
	            	is_array($import_settings['agent_display_option_rules']) && 
	            	!empty($import_settings['agent_display_option_rules']) 
	            )
	            {
		            switch ( $import_settings['agent_display_option'] )
		            {
		            	case "author_info":
		            	{
		            		foreach ( $import_settings['agent_display_option_rules'] as $rule )
		            		{
		            			$value_in_feed_to_check = '';
		            			switch ( $rule['field'] )
		            			{
		            				case "Branch Name":
		            				{
		            					$value_in_feed_to_check = isset($property->Branch->Name) ? (string)$property->Branch->Name : '';
		            					break;
		            				}
		            				default:
		            				{
		            					$value_in_feed_to_check = isset($property->{$rule['field']}) ? (string)$property->{$rule['field']} : '';
		            				}
		            			}

		            			if ( $value_in_feed_to_check == $rule['equal'] || $rule['equal'] == '*' )
		            			{
		            				// set post author
		            				$my_post = array(
								    	'ID'          	 => $post_id,
								    	'post_author'    => $rule['reult'],
								  	);

								 	// Update the post into the database
								    wp_update_post( $my_post, true );

		            				break; // Rule matched. Lets not do anymore
		            			}
		            		}
		            		break;
		            	}
		            	case "agent_info":
		            	{
		            		foreach ( $import_settings['agent_display_option_rules'] as $rule )
		            		{
		            			$value_in_feed_to_check = '';
		            			switch ( $rule['field'] )
		            			{
		            				case "Branch Name":
		            				{
		            					$value_in_feed_to_check = isset($property->Branch->Name) ? (string)$property->Branch->Name : '';
		            					break;
		            				}
		            				default:
		            				{
		            					$value_in_feed_to_check = isset($property->{$rule['field']}) ? (string)$property->{$rule['field']} : '';
		            				}
		            			}

		            			if ( $value_in_feed_to_check == $rule['equal'] || $rule['equal'] == '*' )
		            			{
		            				update_post_meta( $post_id, 'fave_agents', $rule['result'] );
		            				break; // Rule matched. Lets not do anymore
		            			}
		            		}
		            		break;
		            	}
		            	case "agency_info":
		            	{
		            		foreach ( $import_settings['agent_display_option_rules'] as $rule )
		            		{
		            			$value_in_feed_to_check = '';
		            			switch ( $rule['field'] )
		            			{
		            				case "Branch Name":
		            				{
		            					$value_in_feed_to_check = isset($property->Branch->Name) ? (string)$property->Branch->Name : '';
		            					break;
		            				}
		            				default:
		            				{
		            					$value_in_feed_to_check = isset($property->{$rule['field']}) ? (string)$property->{$rule['field']} : '';
		            				}
		            			}

		            			if ( $value_in_feed_to_check == $rule['equal'] || $rule['equal'] == '*' )
		            			{
		            				update_post_meta( $post_id, 'fave_property_agency', $rule['result'] );
		            				break; // Rule matched. Lets not do anymore
		            			}
		            		}
		            		break;
		            	}
		            }
	        	}
	        	
	            //turn bullets into property features
	            $feature_term_ids = array();
	            if ( isset($property->Features) && !empty($property->Features) )
				{
					foreach ( $property->Features->Feature as $feature )
					{
						$term = term_exists( trim((string)$feature), 'property_feature');
						if ( $term !== 0 && $term !== null && isset($term['term_id']) )
						{
							$feature_term_ids[] = (int)$term['term_id'];
						}
						else
						{
							$term = wp_insert_term( trim((string)$feature), 'property_feature' );
							if ( is_array($term) && isset($term['term_id']) )
							{
								$feature_term_ids[] = (int)$term['term_id'];
							}
						}
					}
					if ( !empty($feature_term_ids) )
					{
						wp_set_object_terms( $post_id, $feature_term_ids, "property_feature" );
					}
					else
					{
						wp_delete_object_term_relationships( $post_id, "property_feature" );
					}
				}

				update_post_meta( $post_id, 'fave_epc_current_rating', ( ( isset($property->EPC->EnergyEfficiencyCurrent) ) ? (string)$property->EPC->EnergyEfficiencyCurrent : '' ) );
				update_post_meta( $post_id, 'fave_epc_potential_rating', ( ( isset($property->EPC->EnergyEfficiencyPotential) ) ? (string)$property->EPC->EnergyEfficiencyPotential : '' ) );

				$mappings = ( isset($import_settings['mappings']) && is_array($import_settings['mappings']) && !empty($import_settings['mappings']) ) ? $import_settings['mappings'] : array();

				// status taxonomies
				$mapping_name = 'lettings_status';
				if ( $department == 'residential-sales' )
				{
					$mapping_name = 'sales_status';
				}

				$taxonomy_mappings = ( isset($mappings[$mapping_name]) && is_array($mappings[$mapping_name]) && !empty($mappings[$mapping_name]) ) ? $mappings[$mapping_name] : array();

				if ( isset($property->Status) && !empty((string)$property->Status) )
				{
					if ( isset($taxonomy_mappings[(string)$property->Status]) && !empty($taxonomy_mappings[(string)$property->Status]) )
					{
						wp_set_object_terms( $post_id, (int)$taxonomy_mappings[(string)$property->Status], "property_status" );
					}
					else
					{
						$this->log( 'Received status of ' . (string)$property->Status . ' that isn\'t mapped in the import settings', (string)$property->ID, $post_id );

						$import_settings = $this->add_missing_mapping( $mappings, $mapping_name, (string)$property->Status, $this->import_id );
					}
				}

				// property type taxonomies
				$taxonomy_mappings = ( isset($mappings['property_type']) && is_array($mappings['property_type']) && !empty($mappings['property_type']) ) ? $mappings['property_type'] : array();

				if ( isset($property->PropertyType) && !empty((string)$property->PropertyType) )
				{
					if ( isset($taxonomy_mappings[(string)$property->PropertyType]) && !empty($taxonomy_mappings[(string)$property->PropertyType]) )
					{
						wp_set_object_terms( $post_id, (int)$taxonomy_mappings[(string)$property->PropertyType], "property_type" );
					}
					else
					{
						$this->log( 'Received property type of ' . (string)$property->PropertyType . ' that isn\'t mapped in the import settings', (string)$property->ID, $post_id );

						$import_settings = $this->add_missing_mapping( $mappings, 'property_type', (string)$property->PropertyType, $this->import_id );
					}
				}

				// Location taxonomies
				$create_location_taxonomy_terms = isset( $import_settings['create_location_taxonomy_terms'] ) ? $import_settings['create_location_taxonomy_terms'] : false;

				$houzez_tax_settings = get_option('houzez_tax_settings', array() );
				
				$location_taxonomies = array();
				if ( !isset($houzez_tax_settings['property_city']) || ( isset($houzez_tax_settings['property_city']) && $houzez_tax_settings['property_city'] != 'disabled' ) )
				{
					$location_taxonomies[] = 'property_city';
				}
				if ( !isset($houzez_tax_settings['property_area']) || ( isset($houzez_tax_settings['property_area']) && $houzez_tax_settings['property_area'] != 'disabled' ) )
				{
					$location_taxonomies[] = 'property_area';
				}
				if ( !isset($houzez_tax_settings['property_state']) || ( isset($houzez_tax_settings['property_state']) && $houzez_tax_settings['property_state'] != 'disabled' ) )
				{
					$location_taxonomies[] = 'property_state';
				}

				foreach ( $location_taxonomies as $location_taxonomy )
				{
					$address_field_to_use = isset( $import_settings[$location_taxonomy . '_address_field'] ) ? $import_settings[$location_taxonomy . '_address_field'] : '';
					if ( !empty($address_field_to_use) )
					{
						$location_term_ids = array();
						if ( isset($property->{$address_field_to_use}) && !empty((string)$property->{$address_field_to_use}) )
		            	{
		            		$term = term_exists( trim((string)$property->{$address_field_to_use}), $location_taxonomy);
							if ( $term !== 0 && $term !== null && isset($term['term_id']) )
							{
								$location_term_ids[] = (int)$term['term_id'];
							}
							else
							{
								if ( $create_location_taxonomy_terms === true )
								{
									$term = wp_insert_term( trim((string)$property->{$address_field_to_use}), $location_taxonomy );
									if ( is_array($term) && isset($term['term_id']) )
									{
										$location_term_ids[] = (int)$term['term_id'];
									}
								}
							}
		            	}
		            	if ( !empty($location_term_ids) )
						{
							wp_set_object_terms( $post_id, $location_term_ids, $location_taxonomy );
						}
						else
						{
							wp_delete_object_term_relationships( $post_id, $location_taxonomy );
						}
					}
				}

				// Images
				if ( 
					apply_filters('houzez_property_feed_images_stored_as_urls', false, $post_id, $property, $this->import_id) === true ||
					apply_filters('houzez_property_feed_images_stored_as_urls_apex27', false, $post_id, $property, $this->import_id) === true
				)
				{
					$urls = array();

					if (isset($property->Images) && !empty($property->Images))
	                {
	                    foreach ($property->Images as $images)
	                    {
	                        if (!empty($images->Image))
	                        {
	                            foreach ($images->Image as $image)
	                            {
									if ( 
										substr( strtolower((string)$image->URL), 0, 2 ) == '//' || 
										substr( strtolower((string)$image->URL), 0, 4 ) == 'http'
									)
									{
										$urls[] = array(
											'url' => (string)$image->URL
										);
									}
								}
							}
						}
					}

					update_post_meta( $post_id, 'image_urls', $urls );
					update_post_meta( $post_id, 'images_stored_as_urls', true );

					$this->log( 'Imported ' . count($urls) . ' photo URLs', (string)$property->ID, $post_id );
				}
				else
				{
					$media_ids = array();
					$new = 0;
					$existing = 0;
					$deleted = 0;
					$image_i = 0;
					$previous_media_ids = get_post_meta( $post_id, 'fave_property_images' );

					$start_at_image_i = false;
					$previous_import_media_ids = get_option( 'houzez_property_feed_property_image_media_ids_' . $this->import_id );

					if ( !empty($previous_import_media_ids) )
					{
						// an import stopped previously whilst doing images. Check if it was this post
						$explode_previous_import_media_ids = explode("|", $previous_import_media_ids);
						if ( $explode_previous_import_media_ids[0] == $post_id )
						{
							// yes it was this property. now loop through the media already imported to ensure it's not imported again
							if ( isset($explode_previous_import_media_ids[1]) && !empty($explode_previous_import_media_ids[1]) )
							{
								$media_ids = explode(",", $explode_previous_import_media_ids[1]);
								$start_at_image_i = count($media_ids);

								$this->log( 'Imported ' . count($media_ids) . ' images before failing in the previous import. Continuing from here', (string)$property->ID, $post_id );
							}
						}
					}

					if (isset($property->Images) && !empty($property->Images))
	                {
	                    foreach ($property->Images as $images)
	                    {
	                        if (!empty($images->Image))
	                        {
	                            foreach ($images->Image as $image)
	                            {
									if ( 
										substr( strtolower((string)$image->URL), 0, 2 ) == '//' || 
										substr( strtolower((string)$image->URL), 0, 4 ) == 'http'
									)
									{
										if ( $start_at_image_i !== false )
										{
											// we need to start at a specific image
											if ( $image_i < $start_at_image_i )
											{
												++$existing;
												++$image_i;
												continue;
											}
										}

										// This is a URL
										$url = (string)$image->URL;
										$explode_url = explode("?", $url);
										$url = $explode_url[0];
										$description = (string)$image->Caption;

										$filename = basename( $url );

										// Check, based on the URL, whether we have previously imported this media
										$imported_previously = false;
										$imported_previously_id = '';
										if ( is_array($previous_media_ids) && !empty($previous_media_ids) )
										{
											foreach ( $previous_media_ids as $previous_media_id )
											{
												if ( 
													get_post_meta( $previous_media_id, '_imported_url', TRUE ) == $url
												)
												{
													$imported_previously = true;
													$imported_previously_id = $previous_media_id;
													break;
												}
											}
										}

										if ($imported_previously)
										{
											$media_ids[] = $imported_previously_id;

											if ( $description != '' )
											{
												$my_post = array(
											    	'ID'          	 => $imported_previously_id,
											    	'post_title'     => $description,
											    );

											 	// Update the post into the database
											    wp_update_post( $my_post );
											}

											if ( $image_i == 0 ) set_post_thumbnail( $post_id, $imported_previously_id );

											++$existing;

											++$image_i;

											update_option( 'houzez_property_feed_property_image_media_ids_' . $this->import_id, $post_id . '|' . implode(",", $media_ids), false );
										}
										else
										{
											$this->ping();

											$tmp = download_url( $url );

										    $file_array = array(
										        'name' => $filename,
										        'tmp_name' => $tmp
										    );

										    // Check for download errors
										    if ( is_wp_error( $tmp ) ) 
										    {
										        $this->log_error( 'An error occurred whilst importing ' . $url . '. The error was as follows: ' . $tmp->get_error_message(), (string)$property->ID, $post_id );
										    }
										    else
										    {
											    $id = media_handle_sideload( $file_array, $post_id, $description );

											    // Check for handle sideload errors.
											    if ( is_wp_error( $id ) ) 
											    {
											        @unlink( $file_array['tmp_name'] );
											        
											        $this->log_error( 'ERROR: An error occurred whilst importing ' . $url . '. The error was as follows: ' . $id->get_error_message(), (string)$property->ID, $post_id );
											    }
											    else
											    {
											    	$media_ids[] = $id;

											    	update_post_meta( $id, '_imported_url', $url);

											    	if ( $image_i == 0 ) set_post_thumbnail( $post_id, $id );

											    	++$new;

											    	++$image_i;

											    	update_option( 'houzez_property_feed_property_image_media_ids_' . $this->import_id, $post_id . '|' . implode(",", $media_ids), false );
											    }
											}
										}
									}
								}
							}
						}
					}
					if ( $media_ids != $previous_media_ids )
					{
						delete_post_meta( $post_id, 'fave_property_images' );
						foreach ( $media_ids as $media_id )
						{
							add_post_meta( $post_id, 'fave_property_images', $media_id );
						}
					}

					update_post_meta( $post_id, 'images_stored_as_urls', false );

					// Loop through $previous_media_ids, check each one exists in $media_ids, and if it doesn't then delete
					if ( is_array($previous_media_ids) && !empty($previous_media_ids) )
					{
						foreach ( $previous_media_ids as $previous_media_id )
						{
							if ( !in_array($previous_media_id, $media_ids) )
							{
								if ( wp_delete_attachment( $previous_media_id, TRUE ) !== FALSE )
								{
									++$deleted;
								}
							}
						}
					}

					$this->log( 'Imported ' . count($media_ids) . ' photos (' . $new . ' new, ' . $existing . ' existing, ' . $deleted . ' deleted)', (string)$property->ID, $post_id );

					update_option( 'houzez_property_feed_property_image_media_ids_' . $this->import_id, '', false );
				}

				// Floorplans
				$property_floorplans = array();

				if (isset($property->Floorplans) && !empty($property->Floorplans))
                {
                    foreach ($property->Floorplans as $floorplans)
                    {
                        if (!empty($floorplans->Floorplan))
                        {
                            foreach ($floorplans->Floorplan as $floorplan)
                            {
								if ( 
									substr( strtolower((string)$floorplan->URL), 0, 2 ) == '//' || 
									substr( strtolower((string)$floorplan->URL), 0, 4 ) == 'http'
								)
								{
									$url = (string)$floorplan->URL;
									$explode_url = explode("?", $url);
									$url = $explode_url[0];
									$description = ( isset($floorplan->Caption) && !empty((string)$floorplan->Caption) ) ? (string)$floorplan->Caption : __( 'Floorplan', 'houzezpropertyfeed' );

									$property_floorplans[] = array( 
										"fave_plan_title" => $description, 
										"fave_plan_image" => $url
									);
								}
							}
						}
					}
				}

				if ( !empty($property_floorplans) )
				{
	                update_post_meta( $post_id, 'floor_plans', $property_floorplans );
	                update_post_meta( $post_id, 'fave_floor_plans_enable', 'enable' );
	            }
	            else
	            {
	            	update_post_meta( $post_id, 'fave_floor_plans_enable', 'disable' );
	            }

				$this->log( 'Imported ' . count($floorplans) . ' floorplans', (string)$property->ID, $post_id );

				// Brochures and EPCs
				$media_ids = array();
				$new = 0;
				$existing = 0;
				$deleted = 0;
				$previous_media_ids = get_post_meta( $post_id, 'fave_attachments' );

				if (isset($property->Brochures) && !empty($property->Brochures))
                {
                    foreach ($property->Brochures as $brochures)
                    {
                        if (!empty($brochures->Brochure))
                        {
                            foreach ($brochures->Brochure as $brochure)
                            {
								if ( 
									substr( strtolower((string)$brochure->URL), 0, 2 ) == '//' || 
									substr( strtolower((string)$brochure->URL), 0, 4 ) == 'http'
								)
								{
									// This is a URL
									$url = (string)$brochure->URL;
									$description = (string)$brochure->Caption;
									
									$filename = basename( $url );

									// Check, based on the URL, whether we have previously imported this media
									$imported_previously = false;
									$imported_previously_id = '';
									if ( is_array($previous_media_ids) && !empty($previous_media_ids) )
									{
										foreach ( $previous_media_ids as $previous_media_id )
										{
											if ( 
												get_post_meta( $previous_media_id, '_imported_url', TRUE ) == $url
											)
											{
												$imported_previously = true;
												$imported_previously_id = $previous_media_id;
												break;
											}
										}
									}

									if ($imported_previously)
									{
										$media_ids[] = $imported_previously_id;

										if ( $description != '' )
										{
											$my_post = array(
										    	'ID'          	 => $imported_previously_id,
										    	'post_title'     => $description,
										    );

										 	// Update the post into the database
										    wp_update_post( $my_post );
										}

										++$existing;
									}
									else
									{
										$this->ping();
										
										$tmp = download_url( $url );

									    $file_array = array(
									        'name' => $filename,
									        'tmp_name' => $tmp
									    );

									    // Check for download errors
									    if ( is_wp_error( $tmp ) ) 
									    {
									        $this->log_error( 'An error occurred whilst importing ' . $url . '. The error was as follows: ' . $tmp->get_error_message(), (string)$property->ID, $post_id );
									    }
									    else
									    {
										    $id = media_handle_sideload( $file_array, $post_id, $description, array(
		                                        'post_title' => $description,
		                                        'post_excerpt' => $description
		                                    ) );

										    // Check for handle sideload errors.
										    if ( is_wp_error( $id ) ) 
										    {
										        @unlink( $file_array['tmp_name'] );
										        
										        $this->log_error( 'ERROR: An error occurred whilst importing ' . $url . '. The error was as follows: ' . $id->get_error_message(), (string)$property->ID, $post_id );
										    }
										    else
										    {
										    	$media_ids[] = $id;

										    	update_post_meta( $id, '_imported_url', $url);

										    	++$new;
										    }
										}
									}
								}
							}
						}
					}
				}

				if ( $media_ids != $previous_media_ids )
				{
					delete_post_meta( $post_id, 'fave_attachments' );
					foreach ( $media_ids as $media_id )
					{
						add_post_meta( $post_id, 'fave_attachments', $media_id );
					}
				}

				// Loop through $previous_media_ids, check each one exists in $media_ids, and if it doesn't then delete
				if ( is_array($previous_media_ids) && !empty($previous_media_ids) )
				{
					foreach ( $previous_media_ids as $previous_media_id )
					{
						if ( !in_array($previous_media_id, $media_ids) )
						{
							if ( wp_delete_attachment( $previous_media_id, TRUE ) !== FALSE )
							{
								++$deleted;
							}
						}
					}
				}

				$this->log( 'Imported ' . count($media_ids) . ' brochures (' . $new . ' new, ' . $existing . ' existing, ' . $deleted . ' deleted)', (string)$property->ID, $post_id );
				
				update_post_meta( $post_id, 'fave_video_url', '' );
				update_post_meta( $post_id, 'fave_virtual_tour', '' );

				if (isset($property->Videos) && !empty($property->Videos))
                {
                    foreach ($property->Videos as $virtualTours)
                    {
                        if (!empty($virtualTours->Video))
                        {
                            foreach ($virtualTours->Video as $virtualTour)
                            {
								if ( 
									substr( strtolower((string)$virtualTour->URL), 0, 2 ) == '//' || 
									substr( strtolower((string)$virtualTour->URL), 0, 4 ) == 'http'
								)
								{
									// This is a URL
									$url = trim((string)$virtualTour->URL);

									if ( strpos(strtolower($url), 'youtu') !== false || strpos(strtolower($url), 'vimeo') !== false )
									{
										update_post_meta( $post_id, 'fave_video_url', $url );
									}
									else
									{
										$iframe = '<iframe src="' . $url . '" style="border:0; height:360px; width:640px; max-width:100%" allowFullScreen="true"></iframe>';
										update_post_meta( $post_id, 'fave_virtual_tour', $iframe );
									}
								}
							}
						}
					}
				}

				do_action( "houzez_property_feed_property_imported", $post_id, $property, $this->import_id );
				do_action( "houzez_property_feed_property_imported_apex27", $post_id, $property, $this->import_id );

				$post = get_post( $post_id );
				do_action( "save_post_property", $post_id, $post, false );
				do_action( "save_post", $post_id, $post, false );

				if ( $inserted_updated == 'updated' )
				{
					$this->compare_meta_and_taxonomy_data( $post_id, (string)$property->ID, $metadata_before, $taxonomy_terms_before );
				}
			}

			++$property_row;

		} // end foreach property

		do_action( "houzez_property_feed_post_import_properties_apex27", $this->import_id );

		$this->import_end();
	}

	public function remove_old_properties()
	{
		global $wpdb, $post;

		if ( !empty($this->properties) )
		{
			$import_refs = array();
			foreach ($this->properties as $property)
			{
				$import_refs[] = (string)$property->ID;
			}

			$this->do_remove_old_properties( $import_refs );

			unset($import_refs);
		}
	}
}

}
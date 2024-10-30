<?php
/**
 * Class for managing the import process of a VaultEA JSON file
 *
 * @package WordPress
 */
if ( class_exists( 'Houzez_Property_Feed_Process' ) ) {

class Houzez_Property_Feed_Format_Vaultea extends Houzez_Property_Feed_Process {

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

		$requests = 0;
		$requests_per_chunk = apply_filters( 'houzez_property_feed_vaultea_requests_per_chunk', 10 );
		$pause_between_requests = apply_filters( 'houzez_property_feed_vaultea_pause_between_requests', 1 );

		// List endpoints for getting both sales and lettings properties
		$endpoints = array(
			array(
				'uri' => 'properties/residential/sale',
				'department' => 'residential-sales',
				'portalStatus' => array( 'listing', 'conditional' )
			),
			array(
				'uri' => 'properties/residential/lease',
				'department' => 'residential-lettings'
			),
			/*array(
				'uri' => 'properties/commercial/sale',
				'department' => 'commercial',
				'portalStatus' => array( 'listing', 'conditional' )
			),
			array(
				'uri' => 'properties/commercial/lease',
				'department' => 'commercial'
			),*/
			array(
				'uri' => 'properties/land/sale',
				'department' => 'residential-sales',
				'portalStatus' => array( 'listing', 'conditional' )
			),
		);

		$endpoints = apply_filters( 'houzez_property_feed_vaultea_endpoints', $endpoints );

		foreach ( $endpoints as $endpoint )
		{
			$current_page = 1;
			$more_properties = true;

			while ( $more_properties )
			{
				++$requests;
				if ( $requests % $requests_per_chunk ) { sleep($pause_between_requests); }

				$response = wp_remote_get( 'https://eu-west-1.api.vaultea.co.uk/api/v1.3/' . $endpoint['uri'] . '?' . ( isset($endpoint['portalStatus']) ? 'portalStatus=' . implode(",", $endpoint['portalStatus']) . '&' : '' ) . 'publishedOnPortals=' . $import_settings['portal'] . '&pagesize=50&page=' . $current_page, array( 'timeout' => 120, 'headers' => array(
					'accept' => 'application/json',
					'Content-Type' => 'application/json',
					'X-Api-Key' => $import_settings['api_key'],
					'Authorization' => 'Bearer ' . $import_settings['token'],
				) ) );

				if ( !is_wp_error($response) && is_array( $response ) )
				{
					$contents = $response['body'];

					$json = json_decode( $contents, TRUE );

					if ( $json !== FALSE && is_array($json) )
					{
						if ( isset($json['totalPages']) )
						{
							if ( $current_page >= $json['totalPages'] )
							{
								$more_properties = false;
							}
						}
						else
						{
							$more_properties = false;
						}

						$this->log("Parsing properties from " . $endpoint['uri'] . " on page " . $current_page);

						if ( !isset($json['items']) && isset($json['message']) && !empty($json['message']) )
						{
							$this->log_error( 'Response: ' . $json['message'] );
							return false;
						}

						$this->log("Found " . count($json['items']) . " properties in JSON from " . $endpoint['uri'] . " ready for parsing");

						foreach ($json['items'] as $property)
						{
							$property['department'] = $endpoint['department'];

							$property['features'] = array();
							$property['rooms'] = array();
							$property['custom'] = array();

							$explode_endpoint = explode("/", $endpoint['uri']);
							$salelease = $explode_endpoint[count($explode_endpoint)-1];
							$life_id = isset($property[$salelease . 'LifeId']) && !empty($property[$salelease . 'LifeId']) ? $property[$salelease . 'LifeId'] : '';

							// custom
							if ( apply_filters( 'houzez_property_feed_vaultea_custom', false ) === true )
							{
								++$requests;
								if ( $requests % $requests_per_chunk ) { sleep($pause_between_requests); }

								$custom_response = wp_remote_get( 'https://eu-west-1.api.vaultea.co.uk/api/v1.3/properties/residential/' . $salelease . '/' . $property['id'] . '/custom?pagesize=50', array( 'timeout' => 120, 'headers' => array(
									'accept' => 'application/json',
									'Content-Type' => 'application/json',
									'X-Api-Key' => $import_settings['api_key'],
									'Authorization' => 'Bearer ' . $import_settings['token'],
								) ) );

								if ( !is_wp_error($custom_response) && is_array( $custom_response ) )
								{
									$custom_contents = $custom_response['body'];

									$custom_json = json_decode( $custom_contents, TRUE );

									if ( $custom_json !== FALSE && is_array($custom_json) && is_array($custom_json['items']) )
									{
										$property['custom'] = $custom_json['items'];
									}
								}
							}
							// end custom

							if ( !empty($life_id) )
							{
								// rooms
								++$requests;
								if ( $requests % $requests_per_chunk ) { sleep($pause_between_requests); }

								$rooms_response = wp_remote_get( 'https://eu-west-1.api.vaultea.co.uk/api/v1.3/properties/' . $property['id'] . '/' . $salelease . '/' . $life_id . '/rooms', array( 'timeout' => 120, 'headers' => array(
									'accept' => 'application/json',
									'Content-Type' => 'application/json',
									'X-Api-Key' => $import_settings['api_key'],
									'Authorization' => 'Bearer ' . $import_settings['token'],
								) ) );

								if ( !is_wp_error($rooms_response) && is_array( $rooms_response ) )
								{
									$rooms_contents = $rooms_response['body'];

									$rooms_json = json_decode( $rooms_contents, TRUE );

									if ( $rooms_json !== FALSE && is_array($rooms_json) && is_array($rooms_json['items']) )
									{
										$property['rooms'] = $rooms_json['items'];
									}
								}
								// end rooms
							}

							++$requests;
							if ( $requests % $requests_per_chunk ) { sleep($pause_between_requests); }

							$features_response = wp_remote_get( 'https://eu-west-1.api.vaultea.co.uk/api/v1.3/' . $endpoint['uri'] . '/' . $property['id'] . '', array( 'timeout' => 120, 'headers' => array(
								'accept' => 'application/json',
								'Content-Type' => 'application/json',
								'X-Api-Key' => $import_settings['api_key'],
								'Authorization' => 'Bearer ' . $import_settings['token'],
							) ) );

							if ( !is_wp_error($features_response) && is_array( $features_response ) )
							{
								$features_contents = $features_response['body'];

								$features_json = json_decode( $features_contents, TRUE );

								if ( $features_json !== FALSE && is_array($features_json) && is_array($features_json['highlights']) )
								{
									$property['features'] = $features_json['highlights'];
								}
							}
							// end features

							// If lettings, ensure it doesn't exist in sales alredy
							if ( $endpoint['department'] == 'residential-lettings' )
							{
								foreach ( $this->properties as $existing_property )
								{
									if ( 
										$existing_property['department'] == 'residential-sales' && 
										$property['id'] == $existing_property['id'] 
									)
									{
										$property['id'] = $property['id'] . '-L';
									}
								}
							}

							$this->properties[] = $property;
						}

						++$current_page;
					}
					else
					{
						// Failed to parse JSON
						$this->log_error( 'Failed to parse JSON file for ' . $endpoint['uri'] . '. Possibly invalid JSON' );
						return false;
					}
				}
				else
				{
					$this->log_error( 'Failed to obtain JSON from ' . $endpoint . '. Dump of response as follows: ' . print_r($response, TRUE) );
					return false;
				}
			}
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
        do_action( "houzez_property_feed_pre_import_properties_vaultea", $this->properties, $this->import_id );

        $this->properties = apply_filters( "houzez_property_feed_properties_due_import", $this->properties, $this->import_id );
        $this->properties = apply_filters( "houzez_property_feed_properties_due_import_vaultea", $this->properties, $this->import_id );

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
				if ( $property['id'] == $start_at_property )
				{
					// we found the property. We'll continue for this property onwards
					$this->log( 'Previous import failed to complete. Continuing from property ' . $property_row . ' with ID ' . $property['id'] );
					$start_at_property = false;
				}
				else
				{
					++$property_row;
					continue;
				}
			}

			update_option( 'houzez_property_feed_property_' . $this->import_id, $property['id'], false );
			
			$this->log( 'Importing property ' . $property_row . ' with reference ' . $property['id'], $property['id'], 0, '', false );

			$this->ping(array('status' => 'importing', 'property' => $property_row, 'total' => count($this->properties)));

			$inserted_updated = false;

			$args = array(
	            'post_type' => 'property',
	            'posts_per_page' => 1,
	            'post_status' => 'any',
	            'meta_query' => array(
	            	array(
		            	'key' => $imported_ref_key,
		            	'value' => $property['id']
		            )
	            )
	        );
	        $property_query = new WP_Query($args);

	        $display_address = ( isset($property['address']['displayAddress']) && !empty($property['address']['displayAddress']) ) ? trim($property['address']['displayAddress']) : trim($property['displayAddress']);

            $post_content = str_replace(array("\r\n", "\n"), "", $property['description']);
            if ( isset($property['rooms']) && is_array($property['rooms']) && !empty($property['rooms']) )
            {
            	foreach ( $property['rooms'] as $room )
            	{
            		$room_content = ( isset($room['name']) && !empty($room['name']) ) ? '<strong>' . $room['name'] . '</strong>' : '';
					$room_content .= ( isset($room['formatted_dimensions']) && !empty($room['formatted_dimensions']) ) ? ' (' . $room['formatted_dimensions'] . ')' : '';
					if ( isset($room['description']) && !empty($room['description']) ) 
					{
						if ( !empty($room_content) ) { $room_content .= '<br>'; }
						$room_content .= $room['description'];
					}
					
					if ( !empty($room_content) )
					{
						$post_content .= '<p>' . $room_content . '</p>';
					}
            	}
            }
	        
	        if ($property_query->have_posts())
	        {
	        	$this->log( 'This property has been imported before. Updating it', $property['id'] );

	        	// We've imported this property before
	            while ($property_query->have_posts())
	            {
	                $property_query->the_post();

	                $post_id = get_the_ID();

	                $my_post = array(
				    	'ID'          	 => $post_id,
				    	'post_title'     => wp_strip_all_tags( $display_address ),
				    	'post_excerpt'   => $property['heading'],
				    	'post_content' 	 => $post_content,
				    	'post_status'    => 'publish',
				  	);

				 	// Update the post into the database
				    $post_id = wp_update_post( $my_post, true );

				    if ( is_wp_error( $post_id ) ) 
					{
						$this->log_error( 'Failed to update post. The error was as follows: ' . $post_id->get_error_message(), $property['id'] );
					}
					else
					{
						$inserted_updated = 'updated';
					}
	            }
	        }
	        else
	        {
	        	$this->log( 'This property hasn\'t been imported before. Inserting it', $property['id'] );

	        	// We've not imported this property before
				$postdata = array(
					'post_excerpt'   => $property['heading'],
					'post_content' 	 => $post_content,
					'post_title'     => wp_strip_all_tags( $display_address ),
					'post_status'    => 'publish',
					'post_type'      => 'property',
					'comment_status' => 'closed',
				);

				$post_id = wp_insert_post( $postdata, true );

				if ( is_wp_error( $post_id ) ) 
				{
					$this->log_error( 'Failed to insert post. The error was as follows: ' . $post_id->get_error_message(), $property['id'] );
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

				$this->log( 'Successfully ' . $inserted_updated . ' post', $property['id'], $post_id );

				update_post_meta( $post_id, $imported_ref_key, $property['id'] );

				update_post_meta( $post_id, '_property_import_data', json_encode($property, JSON_PRETTY_PRINT) );

				$department = $property['department'];

				$poa = false;
				if ( isset($property['priceOnApplication']) && $property['priceOnApplication'] == true )
				{
					$poa = true;
				}

				if ( $poa === true ) 
                {
                    update_post_meta( $post_id, 'fave_property_price', 'POA');
                    update_post_meta( $post_id, 'fave_property_price_postfix', '' );
                }
                else
                {
                	if ( $department == 'residential-sales' )
                	{
                		$price = '';
                		if ( isset($property['searchPrice']) && !empty($property['searchPrice']) )
                		{
	                		$price = round(preg_replace("/[^0-9.]/", '', $property['searchPrice']));
	                	}
	                    update_post_meta( $post_id, 'fave_property_price_prefix', ( isset($property['priceQualifier']['name']) ? $property['priceQualifier']['name'] : '' ) );
	                    update_post_meta( $post_id, 'fave_property_price', $price );
	                    update_post_meta( $post_id, 'fave_property_price_postfix', '' );
	                }
	                elseif ( $department == 'residential-lettings' )
	                {
	                	$price = '';
	                	if ( isset($property['searchPrice']) && !empty($property['searchPrice']) )
                		{
	                		$price = round(preg_replace("/[^0-9.]/", '', $property['searchPrice']));
	                	}
	                	update_post_meta( $post_id, 'fave_property_price_prefix', '' );
	                    update_post_meta( $post_id, 'fave_property_price', $price );
	                    update_post_meta( $post_id, 'fave_property_price_postfix', 'pcm' );
	                }
                }

                update_post_meta( $post_id, 'fave_property_bedrooms', ( ( isset($property['bed']) ) ? $property['bed'] : '' ) );
	            update_post_meta( $post_id, 'fave_property_bathrooms', ( ( isset($property['bath']) ) ? $property['bath'] : '' ) );
	            update_post_meta( $post_id, 'fave_property_rooms', ( ( isset($property['receptionRooms']) ) ? $property['receptionRooms'] : '' ) );

	            update_post_meta( $post_id, 'fave_property_garage', '' );
	            update_post_meta( $post_id, 'fave_property_id', ( ( isset($property['referenceID']) ) ? $property['referenceID'] : '' ) );

	            // Name number
				$address_parts = array();
				if ( isset($property['address']['royalMail']['buildingName']) ) { $address_parts[] = trim($property['address']['royalMail']['buildingName']); }
				if ( isset($property['address']['royalMail']['buildingNumber']) ) { $address_parts[] = trim($property['address']['royalMail']['buildingNumber']); }
				if ( isset($property['address']['royalMail']['subbuildingNumber']) ) { $address_parts[] = trim($property['address']['royalMail']['subbuildingNumber']); }
				if ( isset($property['address']['royalMail']['subbuildingName']) ) { $address_parts[] = trim($property['address']['royalMail']['subbuildingName']); }
				
				if ( isset($property['address']['unitNumber']) ) { $address_parts[] = trim($property['address']['unitNumber']); }
				if ( isset($property['address']['streetNumber']) ) { $address_parts[] = trim($property['address']['streetNumber']); }

				// Street
				if ( isset($property['address']['royalMail']['thoroughfare']) ) { $address_parts[] = trim($property['address']['royalMail']['thoroughfare']); }
				if ( isset($property['address']['royalMail']['thoroughfare2']) ) { $address_parts[] = trim($property['address']['royalMail']['thoroughfare2']); }
				
				if ( isset($property['address']['street']) ) { $address_parts[] = trim($property['address']['street']); }
								
				// Address 2
				if ( isset($property['address']['royalMail']['locality']) ) { $address_parts[] = trim($property['address']['royalMail']['locality']); }
				if ( isset($property['address']['royalMail']['locality2']) ) { $address_parts[] = trim($property['address']['royalMail']['locality2']); }

				if ( isset($property['address']['suburb']['name']) ) { $address_parts[] = trim($property['address']['suburb']['name']); }

				// Address 3
				if ( isset($property['address']['royalMail']['postTown']) ) { $address_parts[] = trim($property['address']['royalMail']['postTown']); }
				
				if ( isset($property['address']['suburb']['giDistrict']['name']) ) { $address_parts[] = trim($property['address']['suburb']['giDistrict']['name']); }

				// Address 4
				if ( isset($property['address']['state']['name']) ) { $address_parts[] = trim($property['address']['state']['name']); }

				// Postcode
				$postcode = ( ( isset($property['address']['royalMail']['postcode']) ) ? $property['address']['royalMail']['postcode'] : '' );
				if ( isset($property['address']['suburb']['postcode']) )
				{
					$postcode = $property['address']['suburb']['postcode'];
				}
				$address_parts[] = $postcode;

				$address_parts = array_filter($address_parts);

	            update_post_meta( $post_id, 'fave_property_map', '1' );
	            update_post_meta( $post_id, 'fave_property_map_address', implode(", ", $address_parts) );
	            $lat = '';
	            $lng = '';
	            if ( isset($property['geolocation']['latitude']) && isset($property['geolocation']['longitude']) && $property['geolocation']['latitude'] != '' && $property['geolocation']['longitude'] != '' && $property['geolocation']['latitude'] != '0' && $property['geolocation']['longitude'] != '0' )
				{
					$lat = $property['geolocation']['latitude'];
					$lng = $property['geolocation']['longitude'];

					update_post_meta( $post_id, 'houzez_geolocation_lat', $lat );
					update_post_meta( $post_id, 'houzez_geolocation_long', $lng );
				}
	            update_post_meta( $post_id, 'fave_property_location', $lat . "," . $lng . ",14" );

	            $country = 'GB';
				if ( isset($property['address']['country']['isocode']) && !empty($property['address']['country']['isocode']) )
				{
					$country = strtoupper($property['address']['country']['isocode']);
				}
	            update_post_meta( $post_id, 'fave_property_country', 'GB' );
	            
	            $address_parts = array();
	            if ( isset($property['address']['royalMail']['thoroughfare']) ) { $address_parts[] = trim($property['address']['royalMail']['thoroughfare']); }
				if ( isset($property['address']['royalMail']['thoroughfare2']) ) { $address_parts[] = trim($property['address']['royalMail']['thoroughfare2']); }
				
				if ( isset($property['address']['street']) ) { $address_parts[] = trim($property['address']['street']); }

	            update_post_meta( $post_id, 'fave_property_address', implode(", ", $address_parts) );
	            update_post_meta( $post_id, 'fave_property_zip', $postcode );

	            $featured = '0';
	            update_post_meta( $post_id, 'fave_featured', $featured );
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
		            				case "branch_name":
		            				{
		            					$value_in_feed_to_check = $property['branch']['name'];
		            					break;
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
		            				case "branch_name":
		            				{
		            					$value_in_feed_to_check = $property['branch']['name'];
		            					break;
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
		            				case "branch_name":
		            				{
		            					$value_in_feed_to_check = $property['branch']['name'];
		            					break;
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
	        	
	            // Turn bullets into property features
	            $feature_term_ids = array();
	            if ( isset($property['features']) && is_array($property['features']) && !empty($property['features']) )
				{
					foreach ( $property['features'] as $feature )
					{
						$term = term_exists( trim($feature), 'property_feature');
						if ( $term !== 0 && $term !== null && isset($term['term_id']) )
						{
							$feature_term_ids[] = (int)$term['term_id'];
						}
						else
						{
							$term = wp_insert_term( trim($feature), 'property_feature' );
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

				$mappings = ( isset($import_settings['mappings']) && is_array($import_settings['mappings']) && !empty($import_settings['mappings']) ) ? $import_settings['mappings'] : array();

				// status taxonomies
				$mapping_name = 'lettings_status';
				if ( $department == 'residential-sales' )
				{
					$mapping_name = 'sales_status';
				}

				$taxonomy_mappings = ( isset($mappings[$mapping_name]) && is_array($mappings[$mapping_name]) && !empty($mappings[$mapping_name]) ) ? $mappings[$mapping_name] : array();

				$status_field = str_replace('residential-', '', str_replace('sales', 'sale', $department));

				if ( isset($property['portalStatus']) && !empty($property['portalStatus']) )
				{
					if ( $property['portalStatus'] == 'management' && isset($property['currentTenancy']['letAgreed']) && $property['currentTenancy']['letAgreed'] == true )
					{
						$property['portalStatus'] = 'letAgreed';
					}
					if ( isset($taxonomy_mappings[$property['portalStatus']]) && !empty($taxonomy_mappings[$property['portalStatus']]) )
					{
						wp_set_object_terms( $post_id, (int)$taxonomy_mappings[$property['portalStatus']], "property_status" );
					}
					else
					{
						$this->log( 'Received status of ' . $property['attributes'][$status_field . '_status'] . ' that isn\'t mapped in the import settings', $property['id'], $post_id );

						$import_settings = $this->add_missing_mapping( $mappings, $mapping_name, $property['attributes'][$status_field . '_status'], $this->import_id );
					}
				}

				// property type taxonomies
				$taxonomy_mappings = ( isset($mappings['property_type']) && is_array($mappings['property_type']) && !empty($mappings['property_type']) ) ? $mappings['property_type'] : array();

				if ( isset($property['type']['name']) && !empty($property['type']['name']) )
				{
					if ( isset($taxonomy_mappings[$property['type']['name']]) && !empty($taxonomy_mappings[$property['type']['name']]) )
					{
						wp_set_object_terms( $post_id, (int)$taxonomy_mappings[$property['type']['name']], "property_type" );
					}
					else
					{
						$this->log( 'Received property type of ' . $property['type']['name'] . ' that isn\'t mapped in the import settings', $property['id'], $post_id );

						$import_settings = $this->add_missing_mapping( $mappings, 'property_type', $property['type']['name'], $this->import_id );
					}
				}

				// Location taxonomies
				/*$create_location_taxonomy_terms = isset( $import_settings['create_location_taxonomy_terms'] ) ? $import_settings['create_location_taxonomy_terms'] : false;

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
						if ( isset($property['address'][$address_field_to_use]) && !empty($property['address'][$address_field_to_use]) )
		            	{
		            		$term = term_exists( trim($property['address'][$address_field_to_use]), $location_taxonomy);
							if ( $term !== 0 && $term !== null && isset($term['term_id']) )
							{
								$location_term_ids[] = (int)$term['term_id'];
							}
							else
							{
								if ( $create_location_taxonomy_terms === true )
								{
									$term = wp_insert_term( trim($property['address'][$address_field_to_use]), $location_taxonomy );
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
				}*/

				// Images
				if ( 
					apply_filters('houzez_property_feed_images_stored_as_urls', false, $post_id, $property, $this->import_id) === true ||
					apply_filters('houzez_property_feed_images_stored_as_urls_vaultea', false, $post_id, $property, $this->import_id) === true
				)
				{
					$urls = array();

					if ( isset($property['photos']) && is_array($property['photos']) && !empty($property['photos']) )
					{
						foreach ( $property['photos'] as $image )
						{
							if ( 
								isset($image['url']) && $image['url'] != ''
								&&
								(
									substr( strtolower($image['url']), 0, 2 ) == '//' || 
									substr( strtolower($image['url']), 0, 4 ) == 'http'
								)
								&& 
								isset($image['type']) && strtolower($image['type']) == 'photograph'
								&& 
								isset($image['published']) && $image['published'] == true
							)
							{
								$urls[] = array(
									'url' => $url
								);
							}
						}
					}

					update_post_meta( $post_id, 'image_urls', $urls );
					update_post_meta( $post_id, 'images_stored_as_urls', true );

					$this->log( 'Imported ' . count($urls) . ' photo URLs', $property['id'], $post_id );
				}
				else
				{
					$media_ids = array();
					$new = 0;
					$existing = 0;
					$deleted = 0;
					$image_i = 0;
					$queued = 0;
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

								$this->log( 'Imported ' . count($media_ids) . ' images before failing in the previous import. Continuing from here', $property['id'], $post_id );
							}
						}
					}

					if ( isset($property['photos']) && is_array($property['photos']) && !empty($property['photos']) )
					{
						foreach ( $property['photos'] as $image )
						{
							if ( 
								isset($image['url']) && $image['url'] != ''
								&&
								(
									substr( strtolower($image['url']), 0, 2 ) == '//' || 
									substr( strtolower($image['url']), 0, 4 ) == 'http'
								)
								&& 
								isset($image['type']) && strtolower($image['type']) == 'photograph'
								&& 
								isset($image['published']) && $image['published'] == true
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
								$url = $image['url'];
								$description = ( isset($image['caption']) ? $image['caption'] : '' );
								$modified = $image['modified'];
							    
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
											&&
											(
												get_post_meta( $previous_media_id, '_modified', TRUE ) == '' 
												||
												(
													get_post_meta( $previous_media_id, '_modified', TRUE ) != '' &&
													get_post_meta( $previous_media_id, '_modified', TRUE ) == $modified
												)
											)
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
									if ( apply_filters( 'houzez_property_feed_import_media', true, $this->import_id, $post_id, $property['id'], $url, $url, $description, 'image', $image_i, $modified ) === true )
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
									        $this->log_error( 'An error occurred whilst importing ' . $url . '. The error was as follows: ' . $tmp->get_error_message(), $property['id'], $post_id );
									    }
									    else
									    {
										    $id = media_handle_sideload( $file_array, $post_id, $description );

										    // Check for handle sideload errors.
										    if ( is_wp_error( $id ) ) 
										    {
										        @unlink( $file_array['tmp_name'] );
										        
										        $this->log_error( 'ERROR: An error occurred whilst importing ' . $url . '. The error was as follows: ' . $id->get_error_message(), $property['id'], $post_id );
										    }
										    else
										    {
										    	$media_ids[] = $id;

										    	update_post_meta( $id, '_imported_url', $url);
										    	update_post_meta( $id, '_modified', $modified);

										    	if ( $image_i == 0 ) set_post_thumbnail( $post_id, $id );

										    	++$new;

										    	++$image_i;

										    	update_option( 'houzez_property_feed_property_image_media_ids_' . $this->import_id, $post_id . '|' . implode(",", $media_ids), false );
										    }
										}
									}
									else
									{
										++$queued;
										++$image_i;
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

					$this->log( 'Imported ' . count($media_ids) . ' photos (' . $new . ' new, ' . $existing . ' existing, ' . $deleted . ' deleted)', $property['id'], $post_id );
					if ( $queued > 0 ) 
					{
						$this->log( $queued . ' photos added to download queue', $property['id'], $post_id );
					}

					update_option( 'houzez_property_feed_property_image_media_ids_' . $this->import_id, '', false );
				}

				// Floorplans
				$floorplans = array();

				if ( isset($property['photos']) && is_array($property['photos']) && !empty($property['photos']) )
				{
					foreach ( $property['photos'] as $image )
					{
						if ( 
							isset($image['url']) && $image['url'] != ''
							&&
							(
								substr( strtolower($image['url']), 0, 2 ) == '//' || 
								substr( strtolower($image['url']), 0, 4 ) == 'http'
							)
							&& 
							isset($image['type']) && strtolower($image['type']) == 'floorplan'
							&& 
							isset($image['published']) && $image['published'] == true
						)
						{
							$description = __( 'Floorplan', 'houzezpropertyfeed' );

							$floorplans[] = array( 
								"fave_plan_title" => $description, 
								"fave_plan_image" => $image['url']
							);
						}
					}
				}

				if ( !empty($floorplans) )
				{
	                update_post_meta( $post_id, 'floor_plans', $floorplans );
	                update_post_meta( $post_id, 'fave_floor_plans_enable', 'enable' );
	            }
	            else
	            {
	            	update_post_meta( $post_id, 'fave_floor_plans_enable', 'disable' );
	            }

				$this->log( 'Imported ' . count($floorplans) . ' floorplans', $property['id'], $post_id );

				// Brochures and EPCs
				$media_ids = array();
				$new = 0;
				$existing = 0;
				$deleted = 0;
				$previous_media_ids = get_post_meta( $post_id, 'fave_attachments' );

				if ( isset($property['soiUrl']) && !empty($property['soiUrl']) )
				{
					if ( 
						substr( strtolower($property['soiUrl']), 0, 2 ) == '//' || 
						substr( strtolower($property['soiUrl']), 0, 4 ) == 'http'
					)
					{
						// This is a URL
						$url = $property['soiUrl'];
						$description = __( 'Brochure', 'houzezpropertyfeed' );
					    
						$filename = basename( $url );

						$max_length = 100; // Define a safe limit considering file system and other constraints
						    
					    if ( strlen($filename) > $max_length ) 
					    {
					    	$extension = pathinfo($filename, PATHINFO_EXTENSION);
					    	$name_without_extension = pathinfo($filename, PATHINFO_FILENAME);

					        $name_without_extension = substr($name_without_extension, 0, $max_length - strlen($extension) - 1);
					        $filename = $name_without_extension . '.' . $extension;
					    }

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
						        $this->log_error( 'An error occurred whilst importing ' . $url . '. The error was as follows: ' . $tmp->get_error_message(), $property['id'], $post_id );
						    }
						    else
						    {
							    $id = media_handle_sideload( $file_array, $post_id, $description, array(
                                    'post_title' => __( 'Brochure', 'houzezpropertyfeed' ),
                                    'post_excerpt' => $description
                                ) );

							    // Check for handle sideload errors.
							    if ( is_wp_error( $id ) ) 
							    {
							        @unlink( $file_array['tmp_name'] );
							        
							        $this->log_error( 'ERROR: An error occurred whilst importing ' . $url . '. The error was as follows: ' . $id->get_error_message(), $property['id'], $post_id );
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

				if ( isset($property['epcGraphUrl']) && !empty($property['epcGraphUrl']) )
				{
					if ( 
						substr( strtolower($property['epcGraphUrl']), 0, 2 ) == '//' || 
						substr( strtolower($property['epcGraphUrl']), 0, 4 ) == 'http'
					)
					{
						// This is a URL
						$url = $property['epcGraphUrl'];
						$description = __( 'EPC', 'houzezpropertyfeed' );
					    
						$filename = basename( $url );

						$max_length = 100; // Define a safe limit considering file system and other constraints
						    
					    if ( strlen($filename) > $max_length ) 
					    {
					    	$extension = pathinfo($filename, PATHINFO_EXTENSION);
					    	$name_without_extension = pathinfo($filename, PATHINFO_FILENAME);

					        $name_without_extension = substr($name_without_extension, 0, $max_length - strlen($extension) - 1);
					        $filename = $name_without_extension . '.' . $extension;
					    }

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
						        $this->log_error( 'An error occurred whilst importing ' . $url . '. The error was as follows: ' . $tmp->get_error_message(), $property['id'], $post_id );
						    }
						    else
						    {
							    $id = media_handle_sideload( $file_array, $post_id, $description, array(
                                    'post_title' => __( 'EPC', 'houzezpropertyfeed' ),
                                    'post_excerpt' => $description
                                ) );

							    // Check for handle sideload errors.
							    if ( is_wp_error( $id ) ) 
							    {
							        @unlink( $file_array['tmp_name'] );
							        
							        $this->log_error( 'ERROR: An error occurred whilst importing ' . $url . '. The error was as follows: ' . $id->get_error_message(), $property['id'], $post_id );
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

				$this->log( 'Imported ' . count($media_ids) . ' brochures (' . $new . ' new, ' . $existing . ' existing, ' . $deleted . ' deleted)', $property['id'], $post_id );
				
				$virtual_tours = array();
				if ( isset($property['externalLinks']) && is_array($property['externalLinks']) && !empty($property['externalLinks']) )
				{
					foreach ( $property['externalLinks'] as $external_link )
					{
						if ( 
							isset($external_link['url']) && $external_link['url'] != ''
							&&
							(
								substr( strtolower($external_link['url']), 0, 2 ) == '//' || 
								substr( strtolower($external_link['url']), 0, 4 ) == 'http'
							)
							&&
							isset($external_link['type']['name']) && strtolower($external_link['type']['name']) == 'virtual tour'
						)
						{
							// This is a URL
							$url = $external_link['url'];

							$virtual_tours[] = $url;
						}
					}
				}

				update_post_meta( $post_id, 'fave_video_url', '' );
				update_post_meta( $post_id, 'fave_virtual_tour', '' );

				if ( !empty($virtual_tours) )
				{
					foreach ( $virtual_tours as $virtual_tour )
					{
						if ( 
							$virtual_tour != ''
							&&
							(
								substr( strtolower($virtual_tour), 0, 2 ) == '//' || 
								substr( strtolower($virtual_tour), 0, 4 ) == 'http'
							)
						)
						{
							// This is a URL
							$url = $virtual_tour;

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

				do_action( "houzez_property_feed_property_imported", $post_id, $property, $this->import_id );
				do_action( "houzez_property_feed_property_imported_vaultea", $post_id, $property, $this->import_id );

				$post = get_post( $post_id );
				do_action( "save_post_property", $post_id, $post, false );
				do_action( "save_post", $post_id, $post, false );

				if ( $inserted_updated == 'updated' )
				{
					$this->compare_meta_and_taxonomy_data( $post_id, $property['id'], $metadata_before, $taxonomy_terms_before );
				}
			}

			++$property_row;

		} // end foreach property

		do_action( "houzez_property_feed_post_import_properties_vaultea", $this->import_id );

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
				$import_refs[] = $property['id'];
			}

			$this->do_remove_old_properties( $import_refs );

			unset($import_refs);
		}
	}
}

}
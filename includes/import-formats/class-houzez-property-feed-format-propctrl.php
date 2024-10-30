<?php
/**
 * Class for managing the import process of a PropCtrl JSON file
 *
 * @package WordPress
 */
if ( class_exists( 'Houzez_Property_Feed_Process' ) ) {

class Houzez_Property_Feed_Format_Propctrl extends Houzez_Property_Feed_Process {

	private $agency_cache = array();
	private $branch_cache = array();
	private $agent_cache = array();

	public function __construct( $instance_id = '', $import_id = '' )
	{
		$this->instance_id = $instance_id;
		$this->import_id = $import_id;

		if ( $this->instance_id != '' && isset($_GET['custom_property_import_cron']) )
	    {
	    	$current_user = wp_get_current_user();

	    	$this->log("Executed manually by " . ( ( isset($current_user->display_name) ) ? $current_user->display_name : '' ), '', 0, '', false );
	    }

	    add_action( "houzez_property_feed_property_removed", array( $this, 'send_put_request_to_withdraw' ), 10, 2 );

	}

	public function send_put_request_to_withdraw( $property_post_id, $import_id )
	{
		$import_settings = get_import_settings_from_id( $import_id );

		$imported_ref_key = ( ( $import_id != '' ) ? '_imported_ref_' . $import_id : '_imported_ref' );
		$imported_ref_key = apply_filters( 'houzez_property_feed_property_imported_ref_key', $imported_ref_key, $import_id );

		$crm_id = get_post_meta($property_post_id, $imported_ref_key, TRUE);

		// Send request back to PropCtrl containing post ID and URL etc
		$url = rtrim($import_settings['base_url'], '/') . '/listing/v1/listings/' . $crm_id;

		$headers = array(
			'Content-Type' => 'application/json',
			'Authorization' => 'Basic ' . base64_encode($import_settings['api_username'] . ':' . $import_settings['api_password']),
		);

		$body = array(
		    "listingNumber" => (string)get_post_meta($property_post_id, $imported_ref_key, TRUE),
		    "status" => "Withdrawn"
		);

		$this->log( 'Making PUT request to PropCtrl to ' . $url . ' with body: ' . json_encode($body), $crm_id, $property_post_id );

		$response = wp_remote_request(
			$url,
			array(
				'method' => 'PUT',
				'timeout' => 120,
				'headers' => $headers,
				'body'    => json_encode($body),
			)
		);

		if ( is_wp_error( $response ) )
		{
			$this->log_error( 'Response when updating status in PropCtrl: ' . $response->get_error_message(), $crm_id, $property_post_id );
		}
		elseif ( wp_remote_retrieve_response_code( $response ) != 200 )
		{
			$this->log_error( 'Response code received not 200 when updating status in PropCtrl: ' . print_r($response, TRUE), $crm_id, $property_post_id );
		}
		else
		{
			$this->log_error( 'Response from PropCtrl: ' . print_r($response, TRUE), $crm_id, $property_post_id );
		}
	}

	public function parse()
	{
		$this->properties = array(); // Reset properties in the event we're importing multiple files

		$this->log("Parsing properties", '', 0, '', false);

		$import_settings = get_import_settings_from_id( $this->import_id );

		$url = rtrim($import_settings['base_url'], '/') . '/listing/v1/listings/changes?fromDate=2020-01-01 00:00:00';

		$headers = array(
			'Authorization' => 'Basic ' . base64_encode($import_settings['api_username'] . ':' . $import_settings['api_password']),
		);

		$response = wp_remote_request(
			$url,
			array(
				'method' => 'GET',
				'timeout' => 120,
				'headers' => $headers
			)
		);

		if ( is_wp_error( $response ) )
		{
			$this->log_error( 'Response: ' . $response->get_error_message() );

			return false;
		}

		if ( wp_remote_retrieve_response_code($response) === 401 )
        {
            $this->log_error( wp_remote_retrieve_response_code($response) . ' response received when requesting properties. Error message: ' . wp_remote_retrieve_response_message($response) );
            return false;
        }

		$json = json_decode( $response['body'], TRUE );

		if ($json !== FALSE)
		{
			if ( isset($json['items']) )
			{
				$property_ids_in_batch = array();

				foreach ($json['items'] as $property)
				{
					if ( count($property_ids_in_batch) == 10 )
					{
						$this->get_properties_in_batch( $property_ids_in_batch );

						$property_ids_in_batch = array();
					}

					$property_ids_in_batch[] = $property['id'];
				}

				$this->get_properties_in_batch( $property_ids_in_batch );
			}
			else
			{
				$this->log_error( 'Parsed JSON but no properties found: ' . $response['body'] );

				return false;
			}
		}
		else
		{
			// Failed to parse JSON
			$this->log_error( 'Failed to parse JSON: ' . $response['body'] );

			return false;
		}

		if ( empty($this->properties) )
		{
			$this->log_error( 'No properties found. We\'re not going to continue as this could likely be wrong and all properties will get removed if we continue.' );

			return false;
		}

		return true;
	}

	private function get_properties_in_batch( $property_ids = array() )
	{
		if ( empty($property_ids) )
		{
			return false;
		}

		$import_settings = get_import_settings_from_id( $this->import_id );

		$url = rtrim($import_settings['base_url'], '/') . '/listing/v1/listings?';

		// Use array_map to prepend "listingIds=" to each ID
		$listing_ids = array_map(function($id) {
		    return "listingIds=" . $id;
		}, $property_ids);

		// Use implode to concatenate them with "&"
		$url .= implode("&", $listing_ids);

		$headers = array(
			'Authorization' => 'Basic ' . base64_encode($import_settings['api_username'] . ':' . $import_settings['api_password']),
		);

		$this->ping();

		$response = wp_remote_request(
			$url,
			array(
				'method' => 'GET',
				'timeout' => 120,
				'headers' => $headers
			)
		);

		if ( is_wp_error( $response ) )
		{
			$this->log_error( 'Response: ' . $response->get_error_message() );

			return false;
		}

		$json = json_decode( $response['body'], TRUE );

		if ($json !== FALSE)
		{
			if ( is_array($json) && !empty($json) )
			{
				foreach ( $json as $property )
				{
					if ( 
						isset($property['listingStatus']) &&
						(
							strtolower($property['listingStatus']) == 'cancelled' ||
							strtolower($property['listingStatus']) == 'withdrawn' ||
							strtolower($property['listingStatus']) == 'expired' ||
							strtolower($property['listingStatus']) == 'rented' ||
							strtolower($property['listingStatus']) == 'sold'
						)
					)
					{
						continue;
					}

					if ( isset($import_settings['agency_id']) && !empty($import_settings['agency_id']) )
					{
						$agency_ids_to_import = explode(",", $import_settings['agency_id']);
			        	$agency_ids_to_import = array_map('trim', $agency_ids_to_import);
			        	$agency_ids_to_import = array_filter($agency_ids_to_import);
			        	$agency_ids_to_import = array_unique($agency_ids_to_import);

			        	if (
			        		!isset($property['agencyId']) ||
			        		( 
			        			isset($property['agencyId']) && 
			        			!in_array($property['agencyId'], $agency_ids_to_import) 
			        		)
			        	)
			        	{
			        		continue;
			        	}
					}
					
					if ( isset($import_settings['branch_id']) && !empty($import_settings['branch_id']) )
					{
						$branch_ids_to_import = explode(",", $import_settings['branch_id']);
			        	$branch_ids_to_import = array_map('trim', $branch_ids_to_import);
			        	$branch_ids_to_import = array_filter($branch_ids_to_import);
			        	$branch_ids_to_import = array_unique($branch_ids_to_import);

			        	if (
			        		!isset($property['branchId']) ||
			        		( 
			        			isset($property['branchId']) && 
			        			!in_array($property['branchId'], $branch_ids_to_import) 
			        		)
			        	)
			        	{
			        		continue;
			        	}
					}

					list($suburb, $city, $province, $postcode, $country) = $this->get_suburb_info($property['suburbId']);

					$property['suburb'] = $suburb;
					$property['city'] = $city;
					$property['province'] = $province;
					$property['postcode'] = $postcode;
					$property['country'] = $country;

					$property['agencyDetails'] = array();
					$property['branchDetails'] = array();
					$property['agentDetails'] = array();

					if ( isset($property['agencyId']) && !empty($property['agencyId']) )
					{
						$agency = $this->get_agency_details($property['agencyId']);

						if ( $agency !== FALSE )
						{
							$property['agencyDetails'] = $agency;
						}
					}

					if ( isset($property['branchId']) && !empty($property['branchId']) )
					{
						$branch = $this->get_branch_details($property['branchId']);

						if ( $branch !== FALSE )
						{
							$property['branchDetails'] = $branch;
						}
					}

					if ( isset($property['agentIds']) && !empty($property['agentIds']) && is_array($property['agentIds']) )
					{
						foreach ( $property['agentIds'] as $agent_id )
						{
							$agent = $this->get_agent_details($agent_id);

							if ( $agent !== FALSE )
							{
								$property['agentDetails'][] = $agent;
							}
						}
					}

					$this->properties[] = $property;
					
				}
			}
			else
			{
				$this->log_error( 'Parsed JSON but it\'s empty: ' . $response['body'] );

				return false;
			}
		}
		else
		{
			// Failed to parse JSON
			$this->log_error( 'Failed to parse JSON: ' . $response['body'] );

			return false;
		}
	}

	private function get_agency_details( $agency_id )
	{
		if ( isset($this->agency_cache[$agency_id]) )
		{
			return $this->agency_cache[$agency_id];
		}

		$import_settings = get_import_settings_from_id( $this->import_id );

		$url = rtrim($import_settings['base_url'], '/') . '/listing/v1/agencies?agencyIds=' . $agency_id;

		$headers = array(
			'Authorization' => 'Basic ' . base64_encode($import_settings['api_username'] . ':' . $import_settings['api_password']),
		);

		$response = wp_remote_request(
			$url,
			array(
				'method' => 'GET',
				'timeout' => 120,
				'headers' => $headers
			)
		);

		if ( is_wp_error( $response ) )
		{
			$this->log_error( 'Agency Response: ' . $response->get_error_message() );

			return false;
		}

		$json = json_decode( $response['body'], TRUE );

		if ($json !== FALSE)
		{
			if ( is_array($json) && !empty($json) )
			{
				$this->agency_cache[$agency_id] = $json[0];
				return $json[0];
			}
			else
			{
				$this->log_error( 'Parsed agency but it\'s empty: ' . $response['body'] );

				return false;
			}
		}
		else
		{
			// Failed to parse JSON
			$this->log_error( 'Failed to parse agency JSON: ' . $response['body'] );

			return false;
		}

		return false;
	}

	private function get_branch_details( $branch_id )
	{
		if ( isset($this->branch_cache[$branch_id]) )
		{
			return $this->branch_cache[$branch_id];
		}

		$import_settings = get_import_settings_from_id( $this->import_id );

		$url = rtrim($import_settings['base_url'], '/') . '/listing/v1/branches?branchIds=' . $branch_id;

		$headers = array(
			'Authorization' => 'Basic ' . base64_encode($import_settings['api_username'] . ':' . $import_settings['api_password']),
		);

		$response = wp_remote_request(
			$url,
			array(
				'method' => 'GET',
				'timeout' => 120,
				'headers' => $headers
			)
		);

		if ( is_wp_error( $response ) )
		{
			$this->log_error( 'Branch Response: ' . $response->get_error_message() );

			return false;
		}

		$json = json_decode( $response['body'], TRUE );

		if ($json !== FALSE)
		{
			if ( is_array($json) && !empty($json) )
			{
				$this->branch_cache[$branch_id] = $json[0];
				return $json[0];
			}
			else
			{
				$this->log_error( 'Parsed branch JSON but it\'s empty: ' . $response['body'] );

				return false;
			}
		}
		else
		{
			// Failed to parse JSON
			$this->log_error( 'Failed to parse branch JSON: ' . $response['body'] );

			return false;
		}

		return false;
	}

	private function get_agent_details( $agent_id )
	{
		if ( isset($this->agent_cache[$agent_id]) )
		{
			return $this->agent_cache[$agent_id];
		}

		$import_settings = get_import_settings_from_id( $this->import_id );

		$url = rtrim($import_settings['base_url'], '/') . '/listing/v1/agents?agentIds=' . $agent_id;

		$headers = array(
			'Authorization' => 'Basic ' . base64_encode($import_settings['api_username'] . ':' . $import_settings['api_password']),
		);

		$response = wp_remote_request(
			$url,
			array(
				'method' => 'GET',
				'timeout' => 120,
				'headers' => $headers
			)
		);

		if ( is_wp_error( $response ) )
		{
			$this->log_error( 'Agent Response: ' . $response->get_error_message() );

			return false;
		}

		$json = json_decode( $response['body'], TRUE );

		if ($json !== FALSE)
		{
			if ( is_array($json) && !empty($json) )
			{
				$this->agent_cache[$agent_id] = $json[0];
				return $json[0];
			}
			else
			{
				$this->log_error( 'Parsed agent JSON but it\'s empty: ' . $response['body'] );

				return false;
			}
		}
		else
		{
			// Failed to parse JSON
			$this->log_error( 'Failed to parse agent JSON: ' . $response['body'] );

			return false;
		}

		return false;
	}

	private function get_suburb_info( $suburb_id )
	{
		// add some kind of caching
		$suburb = '';
		$city = '';
		$province = '';
		$postcode = '';
		$country = '';

		$import_settings = get_import_settings_from_id( $this->import_id );

		$url = rtrim($import_settings['base_url'], '/') . '/listing/v1/suburbs?suburbIds=' . $suburb_id;

		$headers = array(
			'Authorization' => 'Basic ' . base64_encode($import_settings['api_username'] . ':' . $import_settings['api_password']),
		);

		$response = wp_remote_request(
			$url,
			array(
				'method' => 'GET',
				'timeout' => 120,
				'headers' => $headers
			)
		);

		if ( is_wp_error( $response ) )
		{
			$this->log_error( 'Suburb Response: ' . $response->get_error_message() );

			return false;
		}

		$json = json_decode( $response['body'], TRUE );

		if ($json !== FALSE)
		{
			if ( is_array($json) && !empty($json) )
			{
				if ( isset($json[0]['suburbName']) ) { $suburb = $json[0]['suburbName']; }
				if ( isset($json[0]['city']) ) { $city = $json[0]['city']; }
				if ( isset($json[0]['province']) ) { $province = $json[0]['province']; }
				if ( isset($json[0]['postalCode']) ) { $postcode = $json[0]['postalCode']; }
				if ( isset($json[0]['country']) ) { $country = $json[0]['country']; }
			}
			else
			{
				$this->log_error( 'Parsed suburb JSON but it\'s empty: ' . $response['body'] );

				return false;
			}
		}
		else
		{
			// Failed to parse JSON
			$this->log_error( 'Failed to parse suburb JSON: ' . $response['body'] );

			return false;
		}

		return array($suburb, $city, $province, $postcode, $country);
	}

	public function import()
	{
		global $wpdb;

		$imported_ref_key = ( ( $this->import_id != '' ) ? '_imported_ref_' . $this->import_id : '_imported_ref' );
		$imported_ref_key = apply_filters( 'houzez_property_feed_property_imported_ref_key', $imported_ref_key, $this->import_id );

		$import_settings = get_import_settings_from_id( $this->import_id );

		$this->import_start();

		do_action( "houzez_property_feed_pre_import_properties", $this->properties, $this->import_id );
        do_action( "houzez_property_feed_pre_import_properties_propctrl", $this->properties, $this->import_id );

        $this->properties = apply_filters( "houzez_property_feed_properties_due_import", $this->properties, $this->import_id );
        $this->properties = apply_filters( "houzez_property_feed_properties_due_import_propctrl", $this->properties, $this->import_id );

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
				if ( $property['listingId'] == $start_at_property )
				{
					// we found the property. We'll continue for this property onwards
					$this->log( 'Previous import failed to complete. Continuing from property ' . $property_row . ' with ID ' . $property['listingId'] );
					$start_at_property = false;
				}
				else
				{
					++$property_row;
					continue;
				}
			}

			update_option( 'houzez_property_feed_property_' . $this->import_id, $property['listingId'], false );
			
			$this->log( 'Importing property ' . $property_row . ' with reference ' . $property['listingId'], $property['listingId'], 0, '', false );

			$this->ping(array('status' => 'importing', 'property' => $property_row, 'total' => count($this->properties)));

			$inserted_updated = false;

			$args = array(
	            'post_type' => 'property',
	            'posts_per_page' => 1,
	            'post_status' => 'any',
	            'meta_query' => array(
	            	array(
		            	'key' => $imported_ref_key,
		            	'value' => $property['listingId']
		            )
	            )
	        );
	        $property_query = new WP_Query($args);

	        $display_address = $property['marketingHeading'];

			$post_content = $property['marketingDescription'];
	        
	        if ($property_query->have_posts())
	        {
	        	$this->log( 'This property has been imported before. Updating it', $property['listingId'] );

	        	// We've imported this property before
	            while ($property_query->have_posts())
	            {
	                $property_query->the_post();

	                $post_id = get_the_ID();

	                $my_post = array(
				    	'ID'          	 => $post_id,
				    	'post_title'     => wp_strip_all_tags( $display_address ),
				    	'post_excerpt'   => $post_content,
				    	'post_content' 	 => $post_content,
				    	'post_status'    => 'publish',
				  	);

				 	// Update the post into the database
				    $post_id = wp_update_post( $my_post, true );

				    if ( is_wp_error( $post_id ) ) 
					{
						$this->log_error( 'Failed to update post. The error was as follows: ' . $post_id->get_error_message(), $property['listingId'] );
					}
					else
					{
						$inserted_updated = 'updated';
					}
	            }
	        }
	        else
	        {
	        	$this->log( 'This property hasn\'t been imported before. Inserting it', $property['listingId'] );

	        	// We've not imported this property before
				$postdata = array(
					'post_excerpt'   => $post_content,
					'post_content' 	 => $post_content,
					'post_title'     => wp_strip_all_tags( $display_address ),
					'post_status'    => 'publish',
					'post_type'      => 'property',
					'comment_status' => 'closed',
				);

				$post_id = wp_insert_post( $postdata, true );

				if ( is_wp_error( $post_id ) ) 
				{
					$this->log_error( 'Failed to insert post. The error was as follows: ' . $post_id->get_error_message(), $property['listingId'] );
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

				$this->log( 'Successfully ' . $inserted_updated . ' post', $property['listingId'], $post_id );

				update_post_meta( $post_id, $imported_ref_key, $property['listingId'] );

				update_post_meta( $post_id, '_property_import_data', json_encode($property, JSON_PRETTY_PRINT) );

				$department = ( strtolower($property['mandateType']) == 'sale' ? 'residential-sales' : 'residential-lettings' );

				$poa = false;
				if ( 
					isset($property['pricingOption']) && 
					$property['pricingOption'] == 'POA'
				)
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
                		if ( isset($property['listPrice']) && !empty($property['listPrice']) )
                		{
	                		$price = round(preg_replace("/[^0-9.]/", '', $property['listPrice']));
	                	}
	                    update_post_meta( $post_id, 'fave_property_price_prefix', '' );
	                    update_post_meta( $post_id, 'fave_property_price', $price );
	                    update_post_meta( $post_id, 'fave_property_price_postfix', '' );
	                }
	                elseif ( $department == 'residential-lettings' )
	                {
	                	$price = '';
	                	if ( isset($property['listPrice']) )
						{
							$price = preg_replace("/[^0-9.]/", '', $property['listPrice']);
						}

						$rent_frequency = 'pcm';
						switch ( $property['pricingOption'] )
						{
							case "PerDay": { $rent_frequency = 'pd'; break; }
							case "PerWeek": { $rent_frequency = 'pw'; break; }
							case "PerYear": { $rent_frequency = 'pa'; break; }
							case "PerMeterSquared": { $rent_frequency = 'per m²'; break; }
						}
	                	update_post_meta( $post_id, 'fave_property_price_prefix', '' );
	                    update_post_meta( $post_id, 'fave_property_price', $price );
	                    update_post_meta( $post_id, 'fave_property_price_postfix', $rent_frequency );
	                }
                }

                $bedrooms = 0;
                if ( isset($property['features']) && !empty($property['features']) )
				{
					foreach ( $property['features'] as $feature )
					{
						if ( isset($feature['type']) && strpos(strtolower($feature['type']), 'bedroom') !== FALSE )
						{
							++$bedrooms;
						}
					}
				}

                update_post_meta( $post_id, 'fave_property_bedrooms', $bedrooms );
	            update_post_meta( $post_id, 'fave_property_bathrooms', '' );
	            update_post_meta( $post_id, 'fave_property_rooms', '' );

	            $parking = array();
	            if ( isset($property['features']) && !empty($property['features']) )
				{
					foreach ( $property['features'] as $feature )
					{
						if ( isset($feature['type']) && $feature['type'] == 'Parking' )
						{
							$parking[] = $feature['description'];
						}
					}
				}
	            update_post_meta( $post_id, 'fave_property_garage', implode(", ", $parking) );
	            update_post_meta( $post_id, 'fave_property_id', isset($property['listingNumber']) ? $property['listingNumber'] : $property['listingId'] );

	            $address_parts = array();
	            if ( $property['suburb'] != '' )
	            {
	                $address_parts[] = $property['suburb'];
	            }
	            if ( $property['city'] != '' )
	            {
	                $address_parts[] = $property['city'];
	            }
	            if ( $property['province'] != '' )
	            {
	                $address_parts[] = $property['province'];
	            }
	            if ( $property['postcode'] != '' )
	            {
	                $address_parts[] = $property['postcode'];
	            }

	            update_post_meta( $post_id, 'fave_property_map', '1' );
	            update_post_meta( $post_id, 'fave_property_map_address', implode(", ", $address_parts) );
	            $lat = '';
	            $lng = '';
	            if ( isset($property['location']['latitude']) && !empty($property['location']['latitude']) )
	            {
	                update_post_meta( $post_id, 'houzez_geolocation_lat', $property['location']['latitude'] );
	                $lat = $property['location']['latitude'];
	            }
	            if ( isset($property['location']['longitude']) && !empty($property['location']['longitude']) )
	            {
	                update_post_meta( $post_id, 'houzez_geolocation_long', $property['location']['longitude'] );
	                $lng = $property['location']['longitude'];
	            }
	            update_post_meta( $post_id, 'fave_property_location', $lat . "," . $lng . ",14" );
	            update_post_meta( $post_id, 'fave_property_country', 'GB' );
	            
	            $address_parts = array();
	            /*if ( $property['suburb'] != '' )
	            {
	                $address_parts[] = $property['suburb'];
	            }
	            if ( $property['city'] != '' )
	            {
	                $address_parts[] = $property['city'];
	            }
	            if ( $property['province'] != '' )
	            {
	                $address_parts[] = $property['province'];
	            }
	            if ( $property['postcode'] != '' )
	            {
	                $address_parts[] = $property['postcode'];
	            }*/
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
		            				case "agencyId":
		            				{
		            					$value_in_feed_to_check = $property['agencyId'];
		            					break;
		            				}
		            				case "agencyName":
		            				{
		            					$value_in_feed_to_check = ( isset($property['agencyDetails']['name']) ? $property['agencyDetails']['name'] : '' );
		            					break;
		            				}
		            				case "branchId":
		            				{
		            					$value_in_feed_to_check = $property['branchId'];
		            					break;
		            				}
		            				case "branchName":
		            				{
		            					$value_in_feed_to_check = ( isset($property['branchDetails']['name']) ? $property['branchDetails']['name'] : '' );
		            					break;
		            				}
		            				case "agentId":
		            				{
		            					$value_in_feed_to_check = isset($property['agents'][0]) ? $property['agents'][0] : '';
		            					break;
		            				}
		            				case "agentName":
		            				{
		            					$value_in_feed_to_check = (isset($property['agentDetails'][0]['firstName']) && isset($property['agentDetails'][0]['lastName'])) ? $property['agentDetails'][0]['firstName'] . ' ' . $property['agentDetails'][0]['lastName'] : '';
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
		            				case "agencyId":
		            				{
		            					$value_in_feed_to_check = $property['agencyId'];
		            					break;
		            				}
		            				case "agencyName":
		            				{
		            					$value_in_feed_to_check = ( isset($property['agencyDetails']['name']) ? $property['agencyDetails']['name'] : '' );
		            					break;
		            				}
		            				case "branchId":
		            				{
		            					$value_in_feed_to_check = $property['branchId'];
		            					break;
		            				}
		            				case "branchName":
		            				{
		            					$value_in_feed_to_check = ( isset($property['branchDetails']['name']) ? $property['branchDetails']['name'] : '' );
		            					break;
		            				}
		            				case "agentId":
		            				{
		            					$value_in_feed_to_check = isset($property['agents'][0]) ? $property['agents'][0] : '';
		            					break;
		            				}
		            				case "agentName":
		            				{
		            					$value_in_feed_to_check = (isset($property['agentDetails'][0]['firstName']) && isset($property['agentDetails'][0]['lastName'])) ? $property['agentDetails'][0]['firstName'] . ' ' . $property['agentDetails'][0]['lastName'] : '';
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
		            				case "agencyId":
		            				{
		            					$value_in_feed_to_check = $property['agencyId'];
		            					break;
		            				}
		            				case "agencyName":
		            				{
		            					$value_in_feed_to_check = ( isset($property['agencyDetails']['name']) ? $property['agencyDetails']['name'] : '' );
		            					break;
		            				}
		            				case "branchId":
		            				{
		            					$value_in_feed_to_check = $property['branchId'];
		            					break;
		            				}
		            				case "branchName":
		            				{
		            					$value_in_feed_to_check = ( isset($property['branchDetails']['name']) ? $property['branchDetails']['name'] : '' );
		            					break;
		            				}
		            				case "agentId":
		            				{
		            					$value_in_feed_to_check = isset($property['agents'][0]) ? $property['agents'][0] : '';
		            					break;
		            				}
		            				case "agentName":
		            				{
		            					$value_in_feed_to_check = (isset($property['agentDetails'][0]['firstName']) && isset($property['agentDetails'][0]['lastName'])) ? $property['agentDetails'][0]['firstName'] . ' ' . $property['agentDetails'][0]['lastName'] : '';
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
	            /*$feature_term_ids = array();
	            if ( isset($property['featuresForPortals']) && is_array($property['featuresForPortals']) )
				{
					foreach ( $property['featuresForPortals'] as $feature )
					{
						$term = term_exists( trim($feature['name']), 'property_feature');
						if ( $term !== 0 && $term !== null && isset($term['term_id']) )
						{
							$feature_term_ids[] = (int)$term['term_id'];
						}
						else
						{
							$term = wp_insert_term( trim($feature['name']), 'property_feature' );
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
				}*/

				$mappings = ( isset($import_settings['mappings']) && is_array($import_settings['mappings']) && !empty($import_settings['mappings']) ) ? $import_settings['mappings'] : array();

				// status taxonomies
				$mapping_name = 'lettings_status';
				if ( $department == 'residential-sales' )
				{
					$mapping_name = 'sales_status';
				}

				$taxonomy_mappings = ( isset($mappings[$mapping_name]) && is_array($mappings[$mapping_name]) && !empty($mappings[$mapping_name]) ) ? $mappings[$mapping_name] : array();

				$status_field = str_replace('residential-', '', str_replace('sales', 'sale', $department));

				if ( isset($property[$status_field . '_status']) && !empty($property[$status_field . '_status']) )
				{
					if ( isset($taxonomy_mappings[$property[$status_field . '_status']]) && !empty($taxonomy_mappings[$property[$status_field . '_status']]) )
					{
						wp_set_object_terms( $post_id, (int)$taxonomy_mappings[$property[$status_field . '_status']], "property_status" );
					}
					else
					{
						$this->log( 'Received status of ' . $property[$status_field . '_status'] . ' that isn\'t mapped in the import settings', $property['listingId'], $post_id );

						$import_settings = $this->add_missing_mapping( $mappings, $mapping_name, $property[$status_field . '_status'], $this->import_id );
					}
				}

				// property type taxonomies
				$taxonomy_mappings = ( isset($mappings['property_type']) && is_array($mappings['property_type']) && !empty($mappings['property_type']) ) ? $mappings['property_type'] : array();

				if ( isset($property['property_type']) && !empty($property['property_type']) )
				{
					$type_mapped = false;

					if ( 
						isset($property['property_type']) && 
						$property['property_type'] != '' &&
						isset($property['property_style']) && 
						$property['property_style'] != ''
					)
					{
						if ( 
							isset($taxonomy_mappings[$property['property_type'] . ' - ' . $property['property_style']]) && 
							!empty($taxonomy_mappings[$property['property_type'] . ' - ' . $property['property_style']]) 
						)
						{
							wp_set_object_terms( $post_id, (int)$taxonomy_mappings[$property['property_type'] . ' - ' . $property['property_style']], "property_type" );
							$type_mapped = true;
						}
						else
						{
							$this->log( 'Received property type of ' . $property['property_type'] . ' - ' . $property['property_style'] . ' that isn\'t mapped in the import settings', $property['listingId'], $post_id );

							$import_settings = $this->add_missing_mapping( $mappings, 'property_type', $property['property_type'] . ' - ' . $property['property_style'], $this->import_id );
						}
					}

					if ( !$type_mapped )
					{
						if ( isset($taxonomy_mappings[$property['property_type']]) && !empty($taxonomy_mappings[$property['property_type']]) )
						{
							wp_set_object_terms( $post_id, (int)$taxonomy_mappings[$property['property_type']], "property_type" );
						}
						else
						{
							$this->log( 'Received property type of ' . $property['property_type'] . ' that isn\'t mapped in the import settings', $property['listingId'], $post_id );

							$import_settings = $this->add_missing_mapping( $mappings, 'property_type', $property['property_type'], $this->import_id );
						}
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
						if ( isset($property[$address_field_to_use]) && !empty($property[$address_field_to_use]) )
		            	{
		            		$term = term_exists( trim($property[$address_field_to_use]), $location_taxonomy);
							if ( $term !== 0 && $term !== null && isset($term['term_id']) )
							{
								$location_term_ids[] = (int)$term['term_id'];
							}
							else
							{
								if ( $create_location_taxonomy_terms === true )
								{
									$term = wp_insert_term( trim($property[$address_field_to_use]), $location_taxonomy );
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
					apply_filters('houzez_property_feed_images_stored_as_urls_propctrl', false, $post_id, $property, $this->import_id) === true
				)
				{
					$urls = array();

					if (isset($property['images']) && !empty($property['images']))
					{
						foreach ($property['images'] as $image)
						{
							$size = 'large'; // thumbnail, small, medium, large, hero, full
							$url = isset($image['urls'][$size]) ? $image['urls'][$size] : $image['url'];

							if ( 
								substr( strtolower($url), 0, 2 ) == '//' || 
								substr( strtolower($url), 0, 4 ) == 'http'
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

					$this->log( 'Imported ' . count($urls) . ' photo URLs', $property['listingId'], $post_id );
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

								$this->log( 'Imported ' . count($media_ids) . ' images before failing in the previous import. Continuing from here', $property['listingId'], $post_id );
							}
						}
					}

					if (isset($property['images']) && !empty($property['images']))
					{
						foreach ($property['images'] as $image)
						{
							$size = 'large'; // thumbnail, small, medium, large, hero, full
							$url = isset($image['urls'][$size]) ? $image['urls'][$size] : $image['url'];

							if ( 
								substr( strtolower($url), 0, 2 ) == '//' || 
								substr( strtolower($url), 0, 4 ) == 'http'
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
								$description = ( (isset($image['title'])) ? $image['title'] : '' );
							    
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
								        $this->log_error( 'An error occurred whilst importing ' . $url . '. The error was as follows: ' . $tmp->get_error_message(), $property['listingId'], $post_id );
								    }
								    else
								    {
									    $id = media_handle_sideload( $file_array, $post_id, $description );

									    // Check for handle sideload errors.
									    if ( is_wp_error( $id ) ) 
									    {
									        @unlink( $file_array['tmp_name'] );
									        
									        $this->log_error( 'ERROR: An error occurred whilst importing ' . $url . '. The error was as follows: ' . $id->get_error_message(), $property['listingId'], $post_id );
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
					if ( $media_ids != $previous_media_ids )
					{
						delete_post_meta( $post_id, 'fave_property_images' );
						foreach ( $media_ids as $media_id )
						{
							add_post_meta( $post_id, 'fave_property_images', $media_id );
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

					$this->log( 'Imported ' . count($media_ids) . ' photos (' . $new . ' new, ' . $existing . ' existing, ' . $deleted . ' deleted)', $property['listingId'], $post_id );

					update_option( 'houzez_property_feed_property_image_media_ids_' . $this->import_id, '', false );
				}

				// Floorplans
				$floorplans = array();

				if (isset($property['floorplans']) && !empty($property['floorplans']))
				{
					foreach ($property['floorplans'] as $floorplan)
					{
						if ( 
							isset($floorplan['url']) && $floorplan['url'] != ''
							&&
							(
								substr( strtolower($floorplan['url']), 0, 2 ) == '//' || 
								substr( strtolower($floorplan['url']), 0, 4 ) == 'http'
							)
						)
						{
							$description = ( ( isset($floorplan['title']) && !empty($floorplan['title']) ) ? $floorplan['title'] : __( 'Floorplan', 'houzezpropertyfeed' ) );

							$floorplans[] = array( 
								"fave_plan_title" => $description, 
								"fave_plan_image" => $floorplan['url']
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

				$this->log( 'Imported ' . count($floorplans) . ' floorplans', $property['listingId'], $post_id );

				// Brochures and EPCs
				$media_ids = array();
				$new = 0;
				$existing = 0;
				$deleted = 0;
				$previous_media_ids = get_post_meta( $post_id, 'fave_attachments' );

				if (isset($property['brochure']) && !empty($property['brochure']))
				{
					if ( 
						substr( strtolower($property['brochure']['url']), 0, 2 ) == '//' || 
						substr( strtolower($property['brochure']['url']), 0, 4 ) == 'http'
					)
					{
						// This is a URL
						$url = $property['brochure']['url'];
						$description = '';
					    
						$explode_url = explode("?", $url);
						$filename = basename( $explode_url[0] );

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
						        $this->log_error( 'An error occurred whilst importing ' . $url . '. The error was as follows: ' . $tmp->get_error_message(), $property['listingId'], $post_id );
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
							        
							        $this->log_error( 'ERROR: An error occurred whilst importing ' . $url . '. The error was as follows: ' . $id->get_error_message(), $property['listingId'], $post_id );
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

				if (isset($property['additionalMedia']) && !empty($property['additionalMedia']))
				{
					foreach ($property['additionalMedia'] as $brochure)
					{	
						if ( 
							substr( strtolower($brochure['url']), 0, 2 ) == '//' || 
							substr( strtolower($brochure['url']), 0, 4 ) == 'http'
						)
						{
							// This is a URL
							$url = $property['brochure']['url'];
							$description = ( (isset($brochure['title'])) ? $brochure['title'] : '' );
						    
							$explode_url = explode("?", $url);
							$filename = basename( $explode_url[0] );

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
							        $this->log_error( 'An error occurred whilst importing ' . $url . '. The error was as follows: ' . $tmp->get_error_message(), $property['listingId'], $post_id );
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
								        
								        $this->log_error( 'ERROR: An error occurred whilst importing ' . $url . '. The error was as follows: ' . $id->get_error_message(), $property['listingId'], $post_id );
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

				// No EPCs as I believe this gets sent as a URL to a webpage
				// They do provide EPC ratings that we could look to use in future

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

				$this->log( 'Imported ' . count($media_ids) . ' brochures (' . $new . ' new, ' . $existing . ' existing, ' . $deleted . ' deleted)', $property['listingId'], $post_id );
				
				$virtual_tours = array();
				if ( isset($property['details']['virtual_tour']) && !empty($property['details']['virtual_tour']) )
				{
					$virtual_tours[] = $property['details']['virtual_tour'];
				}
				if ( isset($property['property_urls']) && !empty($property['property_urls']) && is_array($property['property_urls']) )
				{
					foreach ( $property['property_urls'] as $property_url )
					{
						if ( 
							isset($property_url['media_type']) && 
							(
								strpos(strtolower($property_url['media_type']), 'virtual') !== FALSE ||
								strpos(strtolower($property_url['media_type']), 'video') !== FALSE ||
								strpos(strtolower($property_url['media_type']), 'tour') !== FALSE
							) &&
							isset($property_url['media_url']) && 
							!empty($property_url['media_url']) &&
							!in_array($property_url['media_url'], $virtual_tours)
						)
						{
							$virtual_tours[] = $property_url['media_url'];
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
				do_action( "houzez_property_feed_property_imported_propctrl", $post_id, $property, $this->import_id );

				$post = get_post( $post_id );
				do_action( "save_post_property", $post_id, $post, false );
				do_action( "save_post", $post_id, $post, false );

				// Send request back to PropCtrl containing post ID and URL etc
				$url = rtrim($import_settings['base_url'], '/') . '/listing/v1/listings/' . $property['listingId'];

				$headers = array(
					'Content-Type' => 'application/json',
					'Authorization' => 'Basic ' . base64_encode($import_settings['api_username'] . ':' . $import_settings['api_password']),
				);

				$body = array(
				    "listingNumber" => (string)$post_id,
				    "status" => "Active",
				    "listingUrl" => get_permalink($post_id)
				);

				$response = wp_remote_request(
					$url,
					array(
						'method' => 'PUT',
						'timeout' => 120,
						'headers' => $headers,
						'body'    => json_encode($body),
					)
				);

				if ( is_wp_error( $response ) )
				{
					$this->log_error( 'Response when updating status in PropCtrl: ' . $response->get_error_message(), $property['listingId'], $post_id );
				}

				if ( $inserted_updated == 'updated' )
				{
					$this->compare_meta_and_taxonomy_data( $post_id, $property['listingId'], $metadata_before, $taxonomy_terms_before );
				}
			}

			++$property_row;

		} // end foreach property

		do_action( "houzez_property_feed_post_import_properties_propctrl", $this->import_id );

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
				$import_refs[] = $property['listingId'];
			}

			$this->do_remove_old_properties( $import_refs );

			unset($import_refs);
		}
	}
}

}
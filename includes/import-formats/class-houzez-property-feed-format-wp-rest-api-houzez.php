<?php
/**
 * Class for managing the import process of another Houzez site using the WP REST API
 *
 * @package WordPress
 */
if ( class_exists( 'Houzez_Property_Feed_Process' ) ) {

class Houzez_Property_Feed_Format_Wp_Rest_Api_Houzez extends Houzez_Property_Feed_Process {

	/**
	 * @var array
	 */
	private $property_status;

	/**
	 * @var array
	 */
	private $property_type;

	/**
	 * @var array
	 */
	private $property_country;

	/**
	 * @var array
	 */
	private $property_state;

	/**
	 * @var array
	 */
	private $property_city;

	/**
	 * @var array
	 */
	private $property_area;

	/**
	 * @var array
	 */
	private $property_feature;

	/**
	 * @var array
	 */
	private $property_label;

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

	private function get_other_site_terms()
	{
		$this->get_other_site_property_statuses();
		$this->get_other_site_property_types();
		$this->get_other_site_property_countries();
		$this->get_other_site_property_states();
		$this->get_other_site_property_cities();
		$this->get_other_site_property_areas();
		$this->get_other_site_property_features();
		$this->get_other_site_property_labels();
	}

	private function get_other_site_property_statuses()
	{
		$this->log("Obtaining property statuses");

		$import_settings = get_import_settings_from_id( $this->import_id );

		$url = ( isset($import_settings['url']) && !empty($import_settings['url']) ) ? rtrim($import_settings['url'], '/') : '';
		$url .= '/wp-json/wp/v2/property_status?per_page=100';

		$url = apply_filters( 'houzez_property_feed_wp_rest_api_houzez_property_status_url', $url, $this->import_id );

		$response = wp_remote_request(
			$url,
			array(
				'method' => 'GET',
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $response ) )
		{
			$this->log_error( 'Response: ' . $response->get_error_message() );

			return false;
		}

		if ( wp_remote_retrieve_response_code( $response ) != 200 )
		{
			$this->log_error( 'Received an invalid response when requesting property statuses. If this taxonomy is disabled this error can be ignored. ' . print_r($response, true) );

			return false;
		}

		$json = json_decode( $response['body'], TRUE );

		if ($json !== FALSE)
		{
			foreach ( $json as $property_status )
			{
				$this->property_status[] = array(
					'id' => $property_status['id'],
					'name' => $property_status['name'],
				);
			}
		}
		else
		{
			// Failed to parse XML
			$this->log_error( 'Failed to parse property statuses JSON.' );

			return false;
		}
	}

	private function get_other_site_property_types()
	{
		$this->log("Obtaining property types");

		$import_settings = get_import_settings_from_id( $this->import_id );

		$url = ( isset($import_settings['url']) && !empty($import_settings['url']) ) ? rtrim($import_settings['url'], '/') : '';
		$url .= '/wp-json/wp/v2/property_type?per_page=100';

		$url = apply_filters( 'houzez_property_feed_wp_rest_api_houzez_property_type_url', $url, $this->import_id );

		$response = wp_remote_request(
			$url,
			array(
				'method' => 'GET',
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $response ) )
		{
			$this->log_error( 'Response: ' . $response->get_error_message() );

			return false;
		}

		if ( wp_remote_retrieve_response_code( $response ) != 200 )
		{
			$this->log_error( 'Received an invalid response when requesting property types. If this taxonomy is disabled this error can be ignored. ' . print_r($response, true) );

			return false;
		}

		$json = json_decode( $response['body'], TRUE );

		if ($json !== FALSE)
		{
			foreach ( $json as $property_type )
			{
				$this->property_type[] = array(
					'id' => $property_type['id'],
					'name' => $property_type['name'],
				);
			}
		}
		else
		{
			// Failed to parse XML
			$this->log_error( 'Failed to parse property types JSON.' );

			return false;
		}
	}

	private function get_other_site_property_countries()
	{
		$this->log("Obtaining property countries");

		$import_settings = get_import_settings_from_id( $this->import_id );

		$url = ( isset($import_settings['url']) && !empty($import_settings['url']) ) ? rtrim($import_settings['url'], '/') : '';
		$url .= '/wp-json/wp/v2/property_country?per_page=100';

		$url = apply_filters( 'houzez_property_feed_wp_rest_api_houzez_property_country_url', $url, $this->import_id );

		$response = wp_remote_request(
			$url,
			array(
				'method' => 'GET',
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $response ) )
		{
			$this->log_error( 'Response: ' . $response->get_error_message() );

			return false;
		}

		if ( wp_remote_retrieve_response_code( $response ) != 200 )
		{
			$this->log_error( 'Received an invalid response when requesting property countries. If this taxonomy is disabled this error can be ignored. ' . print_r($response, true) );

			return false;
		}

		$json = json_decode( $response['body'], TRUE );

		if ($json !== FALSE)
		{
			foreach ( $json as $property_country )
			{
				$this->property_country[] = array(
					'id' => $property_country['id'],
					'name' => $property_country['name'],
				);
			}
		}
		else
		{
			// Failed to parse XML
			$this->log_error( 'Failed to parse property countries JSON.' );

			return false;
		}
	}

	private function get_other_site_property_states()
	{
		$this->log("Obtaining property states");

		$import_settings = get_import_settings_from_id( $this->import_id );

		$url = ( isset($import_settings['url']) && !empty($import_settings['url']) ) ? rtrim($import_settings['url'], '/') : '';
		$url .= '/wp-json/wp/v2/property_state?per_page=100';

		$url = apply_filters( 'houzez_property_feed_wp_rest_api_houzez_property_state_url', $url, $this->import_id );

		$response = wp_remote_request(
			$url,
			array(
				'method' => 'GET',
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $response ) )
		{
			$this->log_error( 'Response: ' . $response->get_error_message() );

			return false;
		}

		if ( wp_remote_retrieve_response_code( $response ) != 200 )
		{
			$this->log_error( 'Received an invalid response when requesting property states. If this taxonomy is disabled this error can be ignored. ' . print_r($response, true) );

			return false;
		}

		$json = json_decode( $response['body'], TRUE );

		if ($json !== FALSE)
		{
			foreach ( $json as $property_state )
			{
				$this->state[] = array(
					'id' => $property_state['id'],
					'name' => $property_state['name'],
				);
			}
		}
		else
		{
			// Failed to parse XML
			$this->log_error( 'Failed to parse property states JSON.' );

			return false;
		}
	}

	private function get_other_site_property_cities()
	{
		$this->log("Obtaining property cities");

		$import_settings = get_import_settings_from_id( $this->import_id );

		$url = ( isset($import_settings['url']) && !empty($import_settings['url']) ) ? rtrim($import_settings['url'], '/') : '';
		$url .= '/wp-json/wp/v2/property_city?per_page=100';

		$url = apply_filters( 'houzez_property_feed_wp_rest_api_houzez_property_city_url', $url, $this->import_id );

		$response = wp_remote_request(
			$url,
			array(
				'method' => 'GET',
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $response ) )
		{
			$this->log_error( 'Response: ' . $response->get_error_message() );

			return false;
		}

		if ( wp_remote_retrieve_response_code( $response ) != 200 )
		{
			$this->log_error( 'Received an invalid response when requesting property cities. If this taxonomy is disabled this error can be ignored. ' . print_r($response, true) );

			return false;
		}

		$json = json_decode( $response['body'], TRUE );

		if ($json !== FALSE)
		{
			foreach ( $json as $property_city )
			{
				$this->property_city[] = array(
					'id' => $property_city['id'],
					'name' => $property_city['name'],
				);
			}
		}
		else
		{
			// Failed to parse XML
			$this->log_error( 'Failed to parse property cities JSON.' );

			return false;
		}
	}

	private function get_other_site_property_areas()
	{
		$this->log("Obtaining property areas");

		$import_settings = get_import_settings_from_id( $this->import_id );

		$url = ( isset($import_settings['url']) && !empty($import_settings['url']) ) ? rtrim($import_settings['url'], '/') : '';
		$url .= '/wp-json/wp/v2/property_area?per_page=100';

		$url = apply_filters( 'houzez_property_feed_wp_rest_api_houzez_property_area_url', $url, $this->import_id );

		$response = wp_remote_request(
			$url,
			array(
				'method' => 'GET',
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $response ) )
		{
			$this->log_error( 'Response: ' . $response->get_error_message() );

			return false;
		}

		if ( wp_remote_retrieve_response_code( $response ) != 200 )
		{
			$this->log_error( 'Received an invalid response when requesting property areas. If this taxonomy is disabled this error can be ignored. ' . print_r($response, true) );

			return false;
		}

		$json = json_decode( $response['body'], TRUE );

		if ($json !== FALSE)
		{
			foreach ( $json as $property_area )
			{
				$this->property_area[] = array(
					'id' => $property_area['id'],
					'name' => $property_area['name'],
				);
			}
		}
		else
		{
			// Failed to parse XML
			$this->log_error( 'Failed to parse property areas JSON.' );

			return false;
		}
	}

	private function get_other_site_property_features()
	{
		$this->log("Obtaining property features");

		$import_settings = get_import_settings_from_id( $this->import_id );

		$url = ( isset($import_settings['url']) && !empty($import_settings['url']) ) ? rtrim($import_settings['url'], '/') : '';
		$url .= '/wp-json/wp/v2/property_feature?per_page=100';

		$url = apply_filters( 'houzez_property_feed_wp_rest_api_houzez_property_feature_url', $url, $this->import_id );

		$response = wp_remote_request(
			$url,
			array(
				'method' => 'GET',
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $response ) )
		{
			$this->log_error( 'Response: ' . $response->get_error_message() );

			return false;
		}

		if ( wp_remote_retrieve_response_code( $response ) != 200 )
		{
			$this->log_error( 'Received an invalid response when requesting property features. If this taxonomy is disabled this error can be ignored. ' . print_r($response, true) );

			return false;
		}

		$json = json_decode( $response['body'], TRUE );

		if ($json !== FALSE)
		{
			foreach ( $json as $property_feature )
			{
				$this->property_feature[] = array(
					'id' => $property_feature['id'],
					'name' => $property_feature['name'],
				);
			}
		}
		else
		{
			// Failed to parse XML
			$this->log_error( 'Failed to parse property features JSON.' );

			return false;
		}
	}

	private function get_other_site_property_labels()
	{
		$this->log("Obtaining property labels");

		$import_settings = get_import_settings_from_id( $this->import_id );

		$url = ( isset($import_settings['url']) && !empty($import_settings['url']) ) ? rtrim($import_settings['url'], '/') : '';
		$url .= '/wp-json/wp/v2/property_label?per_page=100';

		$url = apply_filters( 'houzez_property_feed_wp_rest_api_houzez_property_label_url', $url, $this->import_id );

		$response = wp_remote_request(
			$url,
			array(
				'method' => 'GET',
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $response ) )
		{
			$this->log_error( 'Response: ' . $response->get_error_message() );

			return false;
		}

		if ( wp_remote_retrieve_response_code( $response ) != 200 )
		{
			$this->log_error( 'Received an invalid response when requesting property labels. If this taxonomy is disabled this error can be ignored. ' . print_r($response, true) );

			return false;
		}

		$json = json_decode( $response['body'], TRUE );

		if ($json !== FALSE)
		{
			foreach ( $json as $property_label )
			{
				$this->property_label[] = array(
					'id' => $property_label['id'],
					'name' => $property_label['name'],
				);
			}
		}
		else
		{
			// Failed to parse XML
			$this->log_error( 'Failed to parse property labels JSON.' );

			return false;
		}
	}

	public function parse()
	{
		$this->properties = array(); // Reset properties in the event we're importing multiple files

		$this->log("Parsing properties", '', 0, '', false);

		$import_settings = get_import_settings_from_id( $this->import_id );

		$current_page = 1;
		$more_properties = true;

		while ( $more_properties )
		{
			$this->log("Obtaining properties on page " . $current_page);

			$url = ( isset($import_settings['url']) && !empty($import_settings['url']) ) ? rtrim($import_settings['url'], '/') : '';
			$url .= '/wp-json/wp/v2/properties?per_page=100&page=' . $current_page;

			$url = apply_filters( 'houzez_property_feed_wp_rest_api_houzez_properties_url', $url, $this->import_id );

			$response = wp_remote_request(
				$url,
				array(
					'method' => 'GET',
					'timeout' => 120,
				)
			);

			if ( is_wp_error( $response ) )
			{
				$this->log_error( 'Response: ' . $response->get_error_message() );

				return false;
			}

			if ( wp_remote_retrieve_response_code( $response ) != 200 && wp_remote_retrieve_response_code( $response ) != 400 )
			{
				$this->log_error( 'Received an invalid response: ' . print_r($response, true) );

				return false;
			}

			if ( wp_remote_retrieve_response_code( $response ) == 400 )
			{
				// Hit the end of pages
				$more_properties = false;
			}
			else
			{
				$json = json_decode( $response['body'], TRUE );

				if ($json !== FALSE)
				{
					$this->log("Parsing properties on page " . $current_page);

					foreach ( $json as $property )
					{
						// get media attachments for this property
						$attachment_urls = array();

						if (isset($property['property_meta']['fave_property_images']) && !empty($property['property_meta']['fave_property_images']))
						{
							$url = ( isset($import_settings['url']) && !empty($import_settings['url']) ) ? rtrim($import_settings['url'], '/') : '';
							$url .= '/wp-json/wp/v2/media?per_page=100&include=' . implode(",", $property['property_meta']['fave_property_images']);

							$url = apply_filters( 'houzez_property_feed_wp_rest_api_houzez_media_url', $url, $this->import_id );

							$response = wp_remote_request(
								$url,
								array(
									'method' => 'GET',
									'timeout' => 120,
								)
							);

							if ( is_wp_error( $response ) )
							{
								$this->log_error( 'Response when requesting property media: ' . $response->get_error_message() );

								return false;
							}

							if ( wp_remote_retrieve_response_code( $response ) != 200 )
							{
								$this->log_error( 'Received an invalid response when requesting property media: ' . print_r($response, true) );

								return false;
							}

							$json = json_decode( $response['body'], TRUE );

							if ($json !== FALSE)
							{
								foreach ( $json as $attachment )
								{
									$attachment_urls[] = $attachment['source_url'];
								}
							}
						}

						$property['image_urls'] = $attachment_urls;

						// get media attachments for this property
						$attachment_urls = array();

						if (isset($property['property_meta']['fave_attachments']) && !empty($property['property_meta']['fave_attachments']))
						{
							$url = ( isset($import_settings['url']) && !empty($import_settings['url']) ) ? rtrim($import_settings['url'], '/') : '';
							$url .= '/wp-json/wp/v2/media?per_page=100&include=' . implode(",", $property['property_meta']['fave_attachments']);

							$url = apply_filters( 'houzez_property_feed_wp_rest_api_houzez_media_url', $url, $this->import_id );

							$response = wp_remote_request(
								$url,
								array(
									'method' => 'GET',
									'timeout' => 120,
								)
							);

							if ( is_wp_error( $response ) )
							{
								$this->log_error( 'Response when requesting property media: ' . $response->get_error_message() );

								return false;
							}

							if ( wp_remote_retrieve_response_code( $response ) != 200 )
							{
								$this->log_error( 'Received an invalid response when requesting property media: ' . print_r($response, true) );

								return false;
							}

							$json = json_decode( $response['body'], TRUE );

							if ($json !== FALSE)
							{
								foreach ( $json as $attachment )
								{
									$attachment_urls[] = $attachment['source_url'];
								}
							}
						}

						$property['attachment_urls'] = $attachment_urls;

						$this->properties[] = $property;
					}

					++$current_page;
				}
				else
				{
					// Failed to parse XML
					$this->log_error( 'Failed to parse JSON.' );

					return false;
				}
			}
		}

		if ( empty($this->properties) )
		{
			$this->log_error( 'No properties found. We\'re not going to continue as this could likely be wrong and all properties will get removed if we continue.' );

			return false;
		}

		$this->get_other_site_terms();

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
        do_action( "houzez_property_feed_pre_import_properties_wp_rest_api_houzez", $this->properties, $this->import_id );

        $this->properties = apply_filters( "houzez_property_feed_properties_due_import", $this->properties, $this->import_id );
        $this->properties = apply_filters( "houzez_property_feed_properties_due_import_wp_rest_api_houzez", $this->properties, $this->import_id );

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

	        $display_address = $property['title']['rendered'];

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
				    	'post_excerpt'   => $property['excerpt']['rendered'],
				    	'post_content' 	 => $property['content']['rendered'],
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
					'post_excerpt'   => $property['excerpt']['rendered'],
					'post_content' 	 => $property['content']['rendered'],
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

				// import all meta fields
				$meta_fields_to_ignore = array(
					'_property_import_data',
					'image_urls',
					'images_stored_as_urls',
					'_edit_lock',
					'_edit_last',
					'fave_agent_display_option',
					'fave_agents',
					'fave_property_agency',
					'fave_attachments',
					'fave_property_images',
					'_thumbnail_id'
				);

				$meta_fields_to_ignore = apply_filters( 'houzez_property_feed_property_wp_rest_api_houzez_meta_fields_to_ignore', $meta_fields_to_ignore, $this->import_id );

				if ( isset($property['property_meta']) && !empty($property['property_meta']) )
				{
					foreach ( $property['property_meta'] as $meta_key => $meta_value )
					{
						if ( !in_array($meta_key, $meta_fields_to_ignore) )
						{
							update_post_meta( $post_id, $meta_key, $meta_value[0] );
						}
					}
				}

	            /*update_post_meta( $post_id, 'fave_agent_display_option', ( isset($import_settings['agent_display_option']) ? $import_settings['agent_display_option'] : 'none' ) );

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
		            				case "branch_uuid":
		            				{
		            					$value_in_feed_to_check = $property['attributes']['branch_uuid'];
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
		            				case "branch_uuid":
		            				{
		            					$value_in_feed_to_check = $property['attributes']['branch_uuid'];
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
		            				case "branch_uuid":
		            				{
		            					$value_in_feed_to_check = $property['attributes']['branch_uuid'];
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
	        	}*/
	        	
				$taxonomies = array(
					'property_status',
					'property_type',
					'property_country',
					'property_state',
					'property_city',
					'property_area',
					'property_feature',
					'property_label',
				);

				foreach ( $taxonomies as $taxonomy )
				{
					if ( isset($property[$taxonomy]) && !empty($property[$taxonomy]) )
					{
						$term_ids = array();

						foreach ( $property[$taxonomy] as $property_property_type )
						{
							if ( !empty($this->{$taxonomy}) )
							{
								// get name of property type from $this->property_types based on ID
								foreach ( $this->{$taxonomy} as $property_type_bank ) 
								{
							        if ( $property_type_bank['id'] == $property_property_type ) 
							        {
							            // We have a name. Now see if the same taxonomy and term exists on this site
							            $term = get_term_by('name', $property_type_bank['name'], $taxonomy);
							            if ( $term !== FALSE && !empty($term) )
							            {
							            	$term_ids[] = (int)$term->term_id;
							            }
							        }
							    }
							}
						}

						wp_set_object_terms( $post_id, $term_ids, $taxonomy );
					}
				}

				// Images
				if ( 
					apply_filters('houzez_property_feed_images_stored_as_urls', false, $post_id, $property, $this->import_id) === true ||
					apply_filters('houzez_property_feed_images_stored_as_urls_wp_rest_api_houzez', false, $post_id, $property, $this->import_id) === true
				)
				{
					$urls = array();

					foreach ($property['image_urls'] as $url)
					{
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

					if (isset($property['image_urls']) && !empty($property['image_urls']))
					{
						foreach ($property['image_urls'] as $url)
						{
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
								$description = '';
							    
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

					update_option( 'houzez_property_feed_property_image_media_ids_' . $this->import_id, '', false );
				}

				// Brochures and EPCs
				$media_ids = array();
				$new = 0;
				$existing = 0;
				$deleted = 0;
				$previous_media_ids = get_post_meta( $post_id, 'fave_attachments' );

				if (isset($property['attachment_urls']) && !empty($property['attachment_urls']))
				{
					foreach ($property['attachment_urls'] as $url)
					{	
						if ( 
							substr( strtolower($url), 0, 2 ) == '//' || 
							substr( strtolower($url), 0, 4 ) == 'http'
						)
						{
							// This is a URL
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
							        $this->log_error( 'An error occurred whilst importing ' . $url . '. The error was as follows: ' . $tmp->get_error_message(), $property['id'], $post_id );
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

				$this->log( 'Imported ' . count($media_ids) . ' attachments (' . $new . ' new, ' . $existing . ' existing, ' . $deleted . ' deleted)', $property['id'], $post_id );
				

				do_action( "houzez_property_feed_property_imported", $post_id, $property, $this->import_id );
				do_action( "houzez_property_feed_property_imported_wp_rest_api_houzez", $post_id, $property, $this->import_id );

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

		do_action( "houzez_property_feed_post_import_properties_wp_rest_api_houzez", $this->import_id );

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
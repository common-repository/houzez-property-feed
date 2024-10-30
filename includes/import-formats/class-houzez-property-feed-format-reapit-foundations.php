<?php
/**
 * Class for managing the import process of a Reapit Foundations JSON file
 *
 * @package WordPress
 */
if ( class_exists( 'Houzez_Property_Feed_Process' ) ) {

class Houzez_Property_Feed_Format_Reapit_Foundations extends Houzez_Property_Feed_Process {

	private $token = false;

	public function __construct( $instance_id = '', $import_id = '' )
	{
		$this->instance_id = $instance_id;
		$this->import_id = $import_id;

		if ( $this->instance_id != '' && isset($_GET['custom_property_import_cron']) )
	    {
	    	$current_user = wp_get_current_user();

	    	$this->log("Executed manually by " . ( ( isset($current_user->display_name) ) ? $current_user->display_name : '' ), '', 0, '', false );
	    }

	    if ( !defined('ALLOW_UNFILTERED_UPLOADS') ) { define( 'ALLOW_UNFILTERED_UPLOADS', true ); }
	}

	private function get_token()
    {
        $client_id = apply_filters( 'houzezpropertyfeed_reapit_foundations_json_client_id', '45m5oderlfmrbum378s85gbql7' );
        $client_secret = apply_filters( 'houzezpropertyfeed_reapit_foundations_json_client_secret', '11t5p8oein6hath9at2lf0dqdurqpd7u0sb0u9rrlae70o9t9jqq' );

        $base64_secret = base64_encode( $client_id . ':' . $client_secret );

        $response = wp_remote_post(
            'https://connect.reapit.cloud/token',
            array(
                'method' => 'POST',
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . $base64_secret,
                ),
                'body' => array(
                    'client_id' => $client_id,
                    'grant_type' => 'client_credentials',
                ),
            )
        );

        if ( is_wp_error( $response ) )
        {
            $this->log_error( 'Failed to request token: ' . $response->get_error_message() );
            return false;
        }
        else
        {
            if ( wp_remote_retrieve_response_code($response) !== 200 )
            {
                $this->log_error( wp_remote_retrieve_response_code($response) . ' response received when requesting token. Error message: ' . wp_remote_retrieve_response_message($response) );
                return false;
            }
            else
            {
                $body = json_decode($response['body'], TRUE);

                if ( $body === null || $body === false )
                {
                    $this->log_error( 'Failed to decode token request body: ' . $response['body'] );
                    return false;
                }
                else
                {
                    if ( isset($body['access_token']) )
                    {
                        $this->token = $body['access_token'];

                        return true;
                    }
                    else
                    {
                        $this->log_error( 'Failed to get access_token part of response body: ' . $response['body'] );
                        return false;
                    }
                }
            }
        }
    }

    private function build_reapit_query_string( $url_parameters )
    {
        $url_string = http_build_query( $url_parameters );

        // Reapit can't handle the embed array as http_build_query builds it (&embed[0]=images&embed[1]=area etc)
        // So run a preg_replace to convert it to &embed=images&embed=area etc
        $url_string = preg_replace('/%5B(?:[0-9]|[1-9][0-9]+)%5D=/', '=', $url_string);

        return $url_string;
    }

	public function parse()
	{
		$this->properties = array(); // Reset properties in the event we're importing multiple files

		$this->log("Obtaining token", '', 0, '', false);

        $this->get_token();

        if ( $this->token === false )
        {
            return false;
        }

		$this->log("Parsing properties", '', 0, '', false);

		$import_settings = get_import_settings_from_id( $this->import_id );

        $imported_ref_key = '_imported_ref_' . $this->import_id;

		$property_ids = array();

        $embed = array();

        // only get negotiator embed once a day as pointless doing every time an import runs
        $last_negotiator_embed = get_option( 'houzez_property_feed_reapit_foundations_negotiator_get_' . $this->import_id, '' );
        if ( 
        	apply_filters( 'houzez_property_feed_reapit_foundations_json_negotiators_on_every_request', false ) === true || 
        	$last_negotiator_embed == '' || 
        	$last_negotiator_embed < strtotime('-24 hours') 
        )
        {
            $embed[] = 'negotiator';
        }
        $new_negotiator_embed = time();

        // only embed area if location taxonomy being used
        /*if ( taxonomy_exists('location') )
        {
            $args = array(
                'hide_empty' => false,
                'parent' => 0
            );
            $terms = get_terms( 'location', $args );

            if ( !empty( $terms ) && !is_wp_error( $terms ) )
            {
                $embed[] = 'area';
            }
        }*/

        $total_pages = 999;
        $page_size = 100;
        $page = 1;

        $import_sales_statuses = $import_settings['sale_statuses'];

        $import_lettings_statuses = $import_settings['letting_statuses'];

        $property_status_counts = array();

        $requests = array();

        if ( !empty($import_sales_statuses) )
        {
            $requests['sales'] = array(
                'pageSize' => $page_size,
                'pageNumber' => $page,
                'sellingStatus' => $import_sales_statuses,
                'internetAdvertising' => 'true',
                'fromArchive' => 'false',
                'isExternal' => 'false',
                'embed' => $embed
            );
        }
        if ( !empty($import_lettings_statuses) )
        {
            $requests['lettings'] = array(
                'pageSize' => $page_size,
                'pageNumber' => $page,
                'lettingStatus' => $import_lettings_statuses,
                'internetAdvertising' => 'true',
                'fromArchive' => 'false',
                'isExternal' => 'false',
                'embed' => $embed
            );
        }

        $requests = apply_filters( 'houzez_property_feed_reapit_foundations_json_properties_requests', $requests, $this->import_id);
        
        foreach ( $requests as $request_name => $url_parameters )
        {
            $page = 1;

            $url_parameters = apply_filters( 'houzez_property_feed_reapit_foundations_json_properties_url_parameters', $url_parameters, $this->import_id );

            $params_for_log = $url_parameters;
            unset($params_for_log['pageSize']);
            unset($params_for_log['pageNumber']);
            $this->log("Requesting " . $request_name . " properties with following params: " . $this->build_reapit_query_string( $params_for_log ));

            $url_string = $this->build_reapit_query_string( $url_parameters );

            while ( $page <= $total_pages )
            {
            	$this->ping();

                $response = wp_remote_get(
                    'https://platform.reapit.cloud/properties?' . $url_string,
                    array(
                        'headers' => array(
                            'Authorization' => 'Bearer ' . $this->token,
                            'reapit-customer' => $import_settings['customer_id'],
                            'api-version' => '2020-01-31',
                        ),
                        'timeout' => 120
                    )
                );

                if ( !is_wp_error( $response ) && is_array( $response ) && wp_remote_retrieve_response_code( $response ) === 200 && isset( $response['body'] ) )
                {
                    $body = $response['body']; // use the content

                    $json = json_decode( $body, TRUE );

                    if ( $json !== null && isset( $json['_embedded'] ) && is_array( $json['_embedded'] ) )
                    {
                        if ( $total_pages === 999 && isset( $json['totalPageCount'] ) )
                        {
                            $total_pages = $json['totalPageCount'];
                        }

                        $this->log("Parsing " . count($json['_embedded']) . " properties on page " . $page . ' / ' . $total_pages);

                        foreach ($json['_embedded'] as $property)
                        {
                            $property_id = $property['id'];

                            $ok_to_import = true;
                            $property_has_valid_status = false;

                            if ( $ok_to_import )
                            {
                                if ( isset( $property['marketingMode'] ) )
                                {
                                    switch ( $property['marketingMode'] )
                                    {
                                        case 'selling':
                                            if ( isset($property['selling']['status']) && !empty($import_sales_statuses) && in_array( $property['selling']['status'], $import_sales_statuses ) )
                                            {
                                                $property_has_valid_status = true;
                                                if ( !isset($property_status_counts[$property['selling']['status']]) ) { $property_status_counts[$property['selling']['status']] = 0; }
                                                ++$property_status_counts[$property['selling']['status']];
                                            }
                                            break;
                                        case 'letting':
                                            if ( isset($property['letting']['status']) && !empty($import_lettings_statuses) && in_array( $property['letting']['status'], $import_lettings_statuses ) )
                                            {
                                                $property_has_valid_status = true;
                                                if ( !isset($property_status_counts[$property['letting']['status']]) ) { $property_status_counts[$property['letting']['status']] = 0; }
                                                ++$property_status_counts[$property['letting']['status']];
                                            }
                                            break;
                                        case 'sellingAndLetting':
                                            if ( isset($property['selling']['status']) && !empty($import_sales_statuses) && in_array( $property['selling']['status'], $import_sales_statuses ) )
                                            {
                                                $property_has_valid_status = true;
                                                if ( !isset($property_status_counts[$property['selling']['status']]) ) { $property_status_counts[$property['selling']['status']] = 0; }
                                                ++$property_status_counts[$property['selling']['status']];
                                            }

                                            if ( isset($property['letting']['status']) && !empty($import_lettings_statuses) && in_array( $property['letting']['status'], $import_lettings_statuses ) )
                                            {
                                                $property_has_valid_status = true;
                                                if ( !isset($property_status_counts[$property['letting']['status']]) ) { $property_status_counts[$property['letting']['status']] = 0; }
                                                ++$property_status_counts[$property['letting']['status']];
                                            }
                                            break;
                                    }
                                }

                                if ( $property_has_valid_status )
                                {
                                    if ( ( isset($import_settings['only_updated']) && $import_settings['only_updated'] == 'yes' ) || !isset($import_settings['only_updated']) )
                                    {
                                        $args = array(
                                            'post_type' => 'property',
                                            'posts_per_page' => 1,
                                            'post_status' => 'any',
                                            'fields' => 'ids',
                                            'meta_query' => array(
                                                array(
                                                    'key' => $imported_ref_key,
                                                    'value' => array( $property_id, $property_id . '-S', $property_id . '-L' ),
                                                    'compare' => 'IN'
                                                )
                                            )
                                        );
                                        $property_query = new WP_Query($args);

                                        if ($property_query->have_posts())
                                        {
                                            while ($property_query->have_posts())
                                            {
                                                $property_query->the_post();

                                                $reapit_eTag = $property['_eTag'];
                                                $previous_eTag = get_post_meta( get_the_ID(), '_reapit_foundations_json_eTag_' . $this->import_id, TRUE );

                                                if ($reapit_eTag == $previous_eTag)
                                                {
                                                    $ok_to_import = false;
                                                }
                                            }
                                        }
                                    }

                                    if ( $ok_to_import )
                                    {
                                        if ( $property['marketingMode'] == 'sellingAndLetting' /*&& !$this->is_commercial($property)*/ )
                                        {
                                            if ( isset($property['selling']['status']) && !empty($import_sales_statuses) && in_array( $property['selling']['status'], $import_sales_statuses ) )
                                            {
                                                $property['marketingMode'] = 'selling';
                                                $property['id'] = $property_id . '-S';

                                                $this->properties[] = $property;
                                            }

                                            if ( isset($property['letting']['status']) && !empty($import_lettings_statuses) && in_array( $property['letting']['status'], $import_lettings_statuses ) )
                                            {
                                                $property['marketingMode'] = 'letting';
                                                $property['id'] = $property_id . '-L';

                                                $this->properties[] = $property;
                                            }
                                        }
                                        else
                                        {
                                            $this->properties[] = $property;
                                        }

                                        $property_ids[] = $property_id;
                                    }
                                    else
                                    {
                                        // Property not been updated.
                                        // Lets create our own array so at least the property gets put into the $this->properties array and not removed
                                        if ( $property['marketingMode'] == 'sellingAndLetting' /*&& !$this->is_commercial($property)*/ )
                                        {
                                            if ( isset($property['selling']['status']) && in_array( $property['selling']['status'], $import_sales_statuses ) )
                                            {
                                                $property['fake'] = 'yes';
                                                $property['id'] = $property_id . '-S';
                                                $this->properties[] = $property;
                                            }

                                            if ( isset($property['letting']['status']) && in_array( $property['letting']['status'], $import_lettings_statuses ) )
                                            {
                                                $property['fake'] = 'yes';
                                                $property['id'] = $property_id . '-L';
                                                $this->properties[] = $property;
                                            }
                                        }
                                        else
                                        {
                                            $property['fake'] = 'yes';
                                            $property['id'] = $property_id;
                                            $this->properties[] = $property;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    else
                    {
                        // Failed to parse JSON
                        $this->log_error( 'Failed to parse JSON file: ' . print_r($body, TRUE) );
                        return false;
                    }
                }
                else
                {
                    // Request failed
                    $this->log_error( 'Request failed. Response: ' . print_r($response, TRUE) );
                    return false;
                }

                // Increment page number for while look and in URL string
                ++$page;
                $url_parameters['pageNumber'] = $page;
                $url_string = $this->build_reapit_query_string( $url_parameters );
            }
        }

        if ( !empty( $property_status_counts ) )
        {
            foreach ( $property_status_counts as $status => $count )
            {
                $this->log('Properties with status ' . $status . ': ' . $count);
            }
        }

        if ( !empty($property_ids) )
        {
            $this->log("Parsing images");

            $property_ids = array_unique($property_ids);

            // get images
            $per_chunk = 50;

            $property_id_chunks = array_chunk($property_ids, $per_chunk);

            foreach ( $property_id_chunks as $chunk_i => $property_id_chunk )
            {
                $total_pages = 999;
                $page_size = 100;
                $page = 1;

                $url_parameters = array(
                    'pageSize' => $page_size,
                    'pageNumber' => $page,
                    'propertyId' => $property_id_chunk
                );

                $url_parameters = apply_filters( 'houzez_property_feed_reapit_foundations_json_property_images_url_parameters', $url_parameters, $this->import_id );

                $url_string = $this->build_reapit_query_string( $url_parameters );

                while ( $page <= $total_pages )
                {
                	$this->ping();

                    $response = wp_remote_get(
                        'https://platform.reapit.cloud/propertyImages?' . $url_string,
                        array(
                            'headers' => array(
                                'Authorization' => 'Bearer ' . $this->token,
                                'reapit-customer' => $import_settings['customer_id'],
                                'api-version' => '2020-01-31',
                            ),
                            'timeout' => 120
                        )
                    );

                    //$this->log_api_request( 'propertyImages' );

                    if ( !is_wp_error( $response ) && is_array( $response ) && wp_remote_retrieve_response_code( $response ) === 200 && isset( $response['body'] ) )
                    {
                        $body = $response['body']; // use the content

                        $json = json_decode( $body, TRUE );

                        if ( $json !== null && isset( $json['_embedded'] ) && is_array( $json['_embedded'] ) )
                        {
                            if ( $total_pages === 999 && isset( $json['totalPageCount'] ) )
                            {
                                $total_pages = $json['totalPageCount'];
                            }

                            if ( $total_pages > 0 )
                            {
                                $this->log("Parsing images on page " . $page . ' of ' . $total_pages . ' in chunk ' . ($chunk_i + 1) . ' / ' . count($property_id_chunks));

                                foreach ($json['_embedded'] as $image)
                                {
                                    // add image to relevant property
                                    foreach ( $this->properties as $i => $property )
                                    {
                                        if ( 
                                            $property['id'] == $image['propertyId'] || 
                                            $property['id'] == $image['propertyId'] . '-S' || 
                                            $property['id'] == $image['propertyId'] . '-L' 
                                        )
                                        {
                                            if ( !isset($this->properties[$i]['_embedded']['images']) )
                                            {
                                                $this->properties[$i]['_embedded']['images'] = array();
                                            }
                                            $this->properties[$i]['_embedded']['images'][] = $image;
                                        }
                                    }
                                }
                            }
                        }
                        else
                        {
                            // Failed to parse JSON
                            $this->log_error( 'Failed to parse propertyImages JSON file: ' . print_r($body, TRUE) );
                            return false;
                        }
                    }
                    else
                    {
                        // Request failed
                        $this->log_error( 'propertyImages Request failed. Response: ' . print_r($response, TRUE) );
                        return false;
                    }
                    // Increment page number for while look and in URL string
                    ++$page;
                    $url_parameters['pageNumber'] = $page;
                    $url_string = $this->build_reapit_query_string( $url_parameters );
                }
            }

        }

        update_option( 'houzez_property_feed_reapit_foundations_negotiator_get_' . $this->import_id, $new_negotiator_embed );

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
        do_action( "houzez_property_feed_pre_import_properties_reapit_foundations", $this->properties, $this->import_id );

        $this->properties = apply_filters( "houzez_property_feed_properties_due_import", $this->properties, $this->import_id );
        $this->properties = apply_filters( "houzez_property_feed_properties_due_import_reapit_foundations", $this->properties, $this->import_id );

        $limit = apply_filters( "houzez_property_feed_property_limit", 25 );
        $additional_message = '';
        if ( $limit !== false )
        {
        	$this->properties = array_slice( $this->properties, 0, $limit );
        	$additional_message = '. <a href="https://houzezpropertyfeed.com/#pricing" target="_blank">Upgrade to PRO</a> to import unlimited properties';
        }

        $importing = 0;
        foreach ( $this->properties as $property )
        {
            if ( !isset($property['fake']) )
            {
                ++$importing;
            }
        }

		$this->log( 'Beginning to loop through ' . count($this->properties) . ' properties (importing ' . $importing . ')' . $additional_message );

		$start_at_property = get_option( 'houzez_property_feed_property_' . $this->import_id );

		$property_row = 1;
		foreach ( $this->properties as $property )
		{
            if ( ( isset($import_settings['only_updated']) && $import_settings['only_updated'] == 'yes' ) || !isset($import_settings['only_updated']) )
            {
                update_option( 'houzez_property_feed_property_' . $this->import_id, '', false );
            }
            else
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
            }

            if ( !isset($property['fake']) )
            {
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

    	        if ( !empty( $property['strapline'] ) )
                {
                    $display_address = $property['strapline'];
                }
                else
                {
                    $address_fields_array = array_filter(array(
                        $property['address']['line1'],
                        $property['address']['line2'],
                        $property['address']['line3'],
                        $property['address']['line4'],
                        $property['address']['postcode'],
                    ));

                    $display_address = implode(', ', $address_fields_array);
                }

    			$post_content = $property['longDescription'];
    	        
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
    				    	'post_excerpt'   => ( ( isset($property['description']) ) ? $property['description'] : '' ),
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
    					'post_excerpt'   => ( ( isset($property['description']) ) ? $property['description'] : '' ),
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

    				$department = $property['marketingMode'] != 'selling' ? 'residential-lettings' : 'residential-sales';

    				$poa = false;
    				if ( 
    					$department == 'residential-sales' &&
    					( isset( $property['selling']['qualifier'] ) && $property['selling']['qualifier'] == 'priceOnApplication' )
    				)
    				{
    					$poa = true;
    				}
    				if ( 
    					$department == 'residential-lettings' &&
    					( isset( $property['letting']['qualifier'] ) && $property['letting']['qualifier'] == 'rentOnApplication' )
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
                    		if ( isset( $property['selling']['price'] ) && !empty($property['selling']['price']) )
                    		{
    	                		$price = round(preg_replace("/[^0-9.]/", '', $property['selling']['price']));
    	                	}

                            $qualifier = ( isset($property['selling']['qualifier']) ? $property['selling']['qualifier'] : '' );

                            if ( !empty($qualifier) && strtoupper($qualifier) !== $qualifier )
                            {
                                // We have a camel case price qualifier
                                $qualifier = preg_replace('/(?<!^)([A-Z])/', ' $1', $qualifier);
                                $qualifier = ucwords($qualifier);
                            }

    	                    update_post_meta( $post_id, 'fave_property_price_prefix', $qualifier );
    	                    update_post_meta( $post_id, 'fave_property_price', $price );
    	                    update_post_meta( $post_id, 'fave_property_price_postfix', '' );
    	                }
    	                elseif ( $department == 'residential-lettings' )
    	                {
    	                	$price = '';
    	                	if ( isset($property['letting']['rent']) )
    						{
    							$price = preg_replace("/[^0-9.]/", '', $property['letting']['rent']);
    						}
    	                	update_post_meta( $post_id, 'fave_property_price_prefix', '' );
    	                    update_post_meta( $post_id, 'fave_property_price', $price );

    	                    $rent_frequency = 'pcm';
                            if ( isset( $property['letting']['rentFrequency'] ) )
                            {
                                switch ( $property['letting']['rentFrequency'] )
                                {
                                    case 'monthly': { $rent_frequency = 'pcm'; break; }
                                    case 'weekly': { $rent_frequency = 'pw'; break; }
                                    case 'yearly':
                                    case 'annually': { $rent_frequency = 'pa'; break; }
                                }
                            }
    	                    update_post_meta( $post_id, 'fave_property_price_postfix', $rent_frequency );
    	                }
                    }

                    update_post_meta( $post_id, 'fave_property_bedrooms', ( ( isset($property['bedrooms']) ) ? $property['bedrooms'] : '' ) );
    	            update_post_meta( $post_id, 'fave_property_bathrooms', ( ( isset($property['bathrooms']) ) ? $property['bathrooms'] : '' ) );
    	            update_post_meta( $post_id, 'fave_property_rooms', ( ( isset($property['receptions']) ) ? $property['receptions'] : '' ) );
    	            $parking = array();
    	            /*if ( isset($property['parkingSpaces']) && !empty($property['parkingSpaces']) )
    				{
    					foreach ( $property['parkingSpaces'] as $parking_space )
    					{
    						if ( isset($parking_space['parking_space_type']) )
    						{
    							$parking[] = $parking_space['parking_space_type'];
    						}
    					}
    				}*/
    	            update_post_meta( $post_id, 'fave_property_garage', implode(", ", $parking) );
    	            update_post_meta( $post_id, 'fave_property_id', ( ( isset( $property['alternateId'] ) && !empty( $property['alternateId'] ) ) ? $property['alternateId'] : $property['id'] ) );

    	            $address_parts = array();
    	            if ( isset($property['address']['line1']) && $property['address']['line1'] != '' )
    	            {
    	                $address_parts[] = $property['address']['line1'];
    	            }
    	            if ( isset($property['address']['line2']) && $property['address']['line2'] != '' )
    	            {
    	                $address_parts[] = $property['address']['line2'];
    	            }
    	            if ( isset($property['address']['line3']) && $property['address']['line3'] != '' )
    	            {
    	                $address_parts[] = $property['address']['line3'];
    	            }
    	            if ( isset($property['address']['line4']) && $property['address']['line4'] != '' )
    	            {
    	                $address_parts[] = $property['address']['line4'];
    	            }
    	            if ( isset($property['address']['postcode']) && $property['address']['postcode'] != '' )
    	            {
    	                $address_parts[] = $property['address']['postcode'];
    	            }

    	            update_post_meta( $post_id, 'fave_property_map', '1' );
    	            update_post_meta( $post_id, 'fave_property_map_address', implode(", ", $address_parts) );
    	            $lat = '';
    	            $lng = '';
    	            if ( isset($property['address']['geolocation']['latitude']) && !empty($property['address']['geolocation']['latitude']) )
    	            {
    	                update_post_meta( $post_id, 'houzez_geolocation_lat', $property['address']['geolocation']['latitude'] );
    	                $lat = $property['address']['geolocation']['latitude'];
    	            }
    	            if ( isset($property['address']['geolocation']['longitude']) && !empty($property['address']['geolocation']['longitude']) )
    	            {
    	                update_post_meta( $post_id, 'houzez_geolocation_long', $property['address']['geolocation']['longitude'] );
    	                $lng = $property['address']['geolocation']['longitude'];
    	            }
    	            update_post_meta( $post_id, 'fave_property_location', $lat . "," . $lng . ",14" );
    	            update_post_meta( $post_id, 'fave_property_country', 'GB' );
    	            
    	            $address_parts = array();
    	            if ( isset($property['address']['line1']) && $property['address']['line1'] != '' )
    	            {
    	                $address_parts[] = $property['address']['line1'];
    	            }
    	            update_post_meta( $post_id, 'fave_property_address', implode(", ", $address_parts) );
    	            update_post_meta( $post_id, 'fave_property_zip', ( ( isset($property['address']['postcode']) ) ? $property['address']['postcode'] : '' ) );

    	            update_post_meta( $post_id, 'fave_featured', 0 );
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
    	        	
    	            // Turn bullets into property features
    	            $feature_term_ids = array();
    	            if ( isset($property['summary']) && !empty($property['summary']) )
    				{
    					$features = explode("\r", $property['summary']);

    					if ( !empty($features) )
    					{
    						foreach ( $features as $feature )
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

    				$status_field = 'selling';
    				if ( $department == 'residential-lettings' ) { $status_field = 'letting'; }

    				if ( isset($property[$status_field]['status']) && !empty($property[$status_field]['status']) )
    				{
    					if ( isset($taxonomy_mappings[$property[$status_field]['status']]) && !empty($taxonomy_mappings[$property[$status_field]['status']]) )
    					{
    						wp_set_object_terms( $post_id, (int)$taxonomy_mappings[$property[$status_field]['status']], "property_status" );
    					}
    					else
    					{
    						$this->log( 'Received status of ' . $property[$status_field]['status'] . ' that isn\'t mapped in the import settings', $property['id'], $post_id );

    						$import_settings = $this->add_missing_mapping( $mappings, $mapping_name, $property[$status_field]['status'], $this->import_id );
    					}
    				}

    				// property type taxonomies
    				$taxonomy_mappings = ( isset($mappings['property_type']) && is_array($mappings['property_type']) && !empty($mappings['property_type']) ) ? $mappings['property_type'] : array();

    				$propertyTypeStyles = array();

                    if ( isset($property['type']) && is_array($property['type']) && !empty($property['type']) )
                    {
                        $propertyTypeStyle = $property['type'][0];

                        if ( isset($property['style']) && is_array($property['style']) && !empty($property['style']) )
                        {
                            $propertyTypeStyle .= ' - ' . $property['style'][0];
                        }

                        $propertyTypeStyles[] = $propertyTypeStyle;
                    }

                    if ( empty($propertyTypeStyles) )
                    {
                        if ( isset($property['unmappedAttributes']) && is_array($property['unmappedAttributes']) && !empty($property['unmappedAttributes']) )
                        {
                            foreach ( $property['unmappedAttributes'] as $unmapped_attribute )
                            {
                                if ( isset($unmapped_attribute['type']) && strtolower($unmapped_attribute['type']) == 'type' )
                                {
                                    $propertyTypeStyles[] = $unmapped_attribute['value'];
                                }
                            }
                        }
                    }

                    if ( !empty($propertyTypeStyles) )
                    {
                        $type_term_ids = array();

                        foreach ( $propertyTypeStyles as $propertyTypeStyle )
                        {
                            if ( !empty($taxonomy_mappings) && isset($taxonomy_mappings[$propertyTypeStyle]) )
                            {
                                $type_term_ids[] = (int)$taxonomy_mappings[$propertyTypeStyle];
                            }
                            else
                            {
                                $this->log( 'Property received with a type (' . $propertyTypeStyle . ') that isn\'t mapped in the import settings', $property['id'], $post_id );

                                $import_settings = $this->add_missing_mapping( $taxonomy_mappings, 'property_type', $propertyTypeStyle, $this->import_id );
                            }
                        }
                        
                        if ( !empty($type_term_ids) )
                        {
                            wp_set_post_terms( $post_id, $type_term_ids, 'property_type' );
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

    				// If there is media, order the array by the order field
                    if (isset($property['_embedded']['images']) && !empty($property['_embedded']['images']))
                    {
                        $media_order = array_column($property['_embedded']['images'], 'order');

                        array_multisort($media_order, SORT_ASC, $property['_embedded']['images']);
                    }

    				// Images
    				if ( 
    					apply_filters('houzez_property_feed_images_stored_as_urls', false, $post_id, $property, $this->import_id) === true ||
    					apply_filters('houzez_property_feed_images_stored_as_urls_reapit_foundations', false, $post_id, $property, $this->import_id) === true
    				)
    				{
    					$urls = array();

    					if (isset($property['_embedded']['images']) && !empty($property['_embedded']['images']))
                        {
                            foreach ($property['_embedded']['images'] as $photo)
                            {
                                if ( isset( $photo['type'] ) && in_array( $photo['type'], apply_filters( 'houzez_property_feed_reapit_foundations_photo_types', array('photograph', 'map') ) ) )
                                {
                                	$url = $photo['url'];

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

    					if (isset($property['_embedded']['images']) && !empty($property['_embedded']['images']))
                        {
                            foreach ($property['_embedded']['images'] as $photo)
                            {
                                if ( isset( $photo['type'] ) && in_array( $photo['type'], apply_filters( 'houzez_property_feed_reapit_foundations_photo_types', array('photograph', 'map') ) ) )
                                {
                                	$url = $photo['url'];

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
    									$description = ( (isset($photo['caption'])) ? $photo['caption'] : '' );
    									$modified = ( (isset($photo['modified'])) ? $photo['modified'] : '' );
    								    
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
                                                    get_post_meta( $previous_media_id, '_modified', TRUE ) == $modified
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
    										    	update_post_meta( $id, '_modified', $modified);

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

    				// Floorplans
    				$floorplans = array();

    				if (isset($property['_embedded']['images']) && !empty($property['_embedded']['images']))
                    {
                        foreach ($property['_embedded']['images'] as $photo)
                        {
                            if ( isset( $photo['type'] ) && in_array( $photo['type'], array('floorPlan') ) )
                            {
                                if (
                                    substr( strtolower($photo['url']), 0, 2 ) == '//' ||
                                    substr( strtolower($photo['url']), 0, 4 ) == 'http'
                                )
                                {
    								$description = ( ( isset($photo['caption']) && !empty($photo['caption']) ) ? $photo['caption']: __( 'Floorplan', 'houzezpropertyfeed' ) );

    								$floorplans[] = array( 
    									"fave_plan_title" => $description, 
    									"fave_plan_image" => $photo['url']
    								);
    							}
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

    				$media_urls = array();
                    if ( isset( $property['selling']['publicBrochureUrl'] ) && !empty($property['selling']['publicBrochureUrl']) )
                    {
                        if (
                            substr( strtolower($property['selling']['publicBrochureUrl']), 0, 2 ) == '//' ||
                            substr( strtolower($property['selling']['publicBrochureUrl']), 0, 4 ) == 'http'
                        )
                        {
                            $media_urls[] = array('url' => $property['selling']['publicBrochureUrl']);
                        }
                    }
                    if ( isset( $property['letting']['publicBrochureUrl'] ) && !empty($property['letting']['publicBrochureUrl']) )
                    {
                        if (
                            substr( strtolower($property['letting']['publicBrochureUrl']), 0, 2 ) == '//' ||
                            substr( strtolower($property['letting']['publicBrochureUrl']), 0, 4 ) == 'http'
                        )
                        {
                            $media_urls[] = array('url' => $property['letting']['publicBrochureUrl']);
                        }
                    }

    				if ( !empty($media_urls) )
    				{
    					foreach ( $media_urls as $media_url )
    					{
    						$url = $media_url['url'];
    						if ( 
    							substr( strtolower($url), 0, 2 ) == '//' || 
    							substr( strtolower($url), 0, 4 ) == 'http'
    						)
    						{
    							// This is a URL
    							$description = __( 'Brochure', 'houzezpropertyfeed' );
    						    $modified = $property['modified'];

    							$filename = 'brochure-' . $post_id . '.pdf';

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
                                            get_post_meta( $previous_media_id, '_modified', TRUE ) == $modified 
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
    								    	update_post_meta( $id, '_modified', $modified);

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

    				$this->log( 'Imported ' . count($media_ids) . ' brochures (' . $new . ' new, ' . $existing . ' existing, ' . $deleted . ' deleted)', $property['id'], $post_id );
    				
    				$virtual_tours = array();
                    $virtualTourNames = array( '', '2' );

                    foreach( $virtualTourNames as $virtualTourName )
                    {
                        if ( isset( $property['video' . $virtualTourName . 'Url'] ) && !empty( $property['video' . $virtualTourName . 'Url'] ) )
                        {
                            $virtual_tours[] = $property['video' . $virtualTourName . 'Url'];
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

                    // eTag only changes when property data changes, so store it and compare when parsing data to only import updated properties
                    update_post_meta( $post_id, '_reapit_foundations_json_eTag_' . $this->import_id, $property['_eTag'] );

    				do_action( "houzez_property_feed_property_imported", $post_id, $property, $this->import_id );
    				do_action( "houzez_property_feed_property_imported_reapit_foundations", $post_id, $property, $this->import_id );

    				$post = get_post( $post_id );
    				do_action( "save_post_property", $post_id, $post, false );
    				do_action( "save_post", $post_id, $post, false );

    				if ( $inserted_updated == 'updated' )
    				{
    					$this->compare_meta_and_taxonomy_data( $post_id, $property['id'], $metadata_before, $taxonomy_terms_before );
    				}
    			}
            }

			++$property_row;

		} // end foreach property

		do_action( "houzez_property_feed_post_import_properties_reapit_foundations", $this->import_id );

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
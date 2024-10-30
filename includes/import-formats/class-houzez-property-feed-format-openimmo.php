<?php
/**
 * Class for managing the import process of an OpenImmo XML file
 *
 * @package WordPress
 */
if ( class_exists( 'Houzez_Property_Feed_Process' ) ) {

class Houzez_Property_Feed_Format_OpenImmo extends Houzez_Property_Feed_Process {

	/**
	 * @var SimpleXMLObject
	 */
	private $agent_xml;

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

	public function parse_and_import()
	{
		$this->properties = array(); // Reset properties in the event we're importing multiple files

		$import_settings = get_import_settings_from_id( $this->import_id );

		if ( $import_settings['format'] == 'openimmo_local' )
		{
			$local_directory = rtrim($import_settings['local_directory'], '/');

			// Get all zip files in date order
			$zip_files = array();
			if ($handle = opendir($local_directory)) 
			{
			    while (false !== ($file = readdir($handle))) 
			    {
			        if (
			        	$file != "." && $file != ".." && 
			        	substr(strtolower($file), -3) == 'zip'
			        ) 
			        {
			           $zip_files[filemtime($local_directory . '/' . $file)] = $local_directory . '/' . $file;
			        }
			    }
			    closedir($handle);
			}
			else
			{
				$this->log_error( 'Failed to read from directory ' . $local_directory . '. Please ensure the local directory specified exists, is the full server path and is readable.' );
				return false;
			}

			if (!empty($zip_files))
			{
				$this->log('Found ' . count($zip_files) . ' ZIPs ready to extract'); 

				if ( !class_exists('ZipArchive') ) 
				{ 
					$this->log_error('The ZipArchive class does not exist but is needed to extract the zip files provided'); 
					return false; 
				}

				ksort($zip_files);

				foreach ($zip_files as $mtime => $zip_file)
				{
					$zip = new ZipArchive;
					if ($zip->open($zip_file) === TRUE) 
					{
					    $zip->extractTo($local_directory);
					    $zip->close();
					    sleep(1); // We sleep to ensure each XML has a different modified time in the same order

					    $this->log('Extracted ZIP ' . $zip_file); 
					}
					else
					{
						$this->log_error('Failed to open the ZIP ' . $zip_file); 
						return false; 
					}
					unlink($zip_file);
				}
			}

			unset($zip_files);

			// Now they've all been extracted, get XML files in date order
			$xml_files = array();
			if ($handle = opendir($local_directory)) 
			{
			    while (false !== ($file = readdir($handle))) 
			    {
			        if (
			        	$file != "." && $file != ".." && 
			        	substr(strtolower($file), -3) == 'xml'
			        ) 
			        {
			           $xml_files[filemtime($local_directory . '/' . $file) . '-' . $file] = $local_directory . '/' . $file;
			        }
			    }
			    closedir($handle);
			}

			if (!empty($xml_files))
			{
				ksort($xml_files); // sort by date modified

				// We've got at least one XML to process

                foreach ($xml_files as $mtime => $xml_file)
                {
                	$this->properties = array(); // Reset properties in the event we're importing multiple files

                	$this->log("Parsing properties");

                	$parsed = false;

                	// Get XML contents into memory
                	if ( file_exists($xml_file) && filesize($xml_file) > 0 ) 
                	{
						$handle = fopen($xml_file, "r");
				        $xml_contents = fread($handle, filesize($xml_file));
				        fclose($handle);

				        $xml = simplexml_load_string($xml_contents);

				        if ($xml !== FALSE)
						{
							if ( isset($xml->anbieter->immobilie) && !empty($xml->anbieter->immobilie) )
							{
								foreach ( $xml->anbieter->immobilie as $property )
								{
									if ( isset($property->verwaltung_techn->aktion) )
									{
										$aktion_attributes = $property->verwaltung_techn->aktion->attributes();

										if ( isset($aktion_attributes['aktionart']) && strtolower((string)$aktion_attributes['aktionart']) == 'delete' )
										{
											// DELETE PROPERTY
											$openimmo_id = (string)$property->verwaltung_techn->openimmo_obid;

											$this->remove_property( $openimmo_id );
										}
										else
										{
											// NEW / UPDATE
											$this->properties[] = $property;
										}
									}
								}
							}

		                	// Parsed it succesfully. Ok to continue
		                	if ( empty($this->properties) )
							{
								$this->log_error( 'No properties found to import. We\'re not going to continue as this could likely be wrong and all properties will get removed if we continue.' );
							}
							else
							{
			                    $this->import();

			                    //$this->remove_old_properties();
			                }
			            }
			            else
			            {
			            	// Failed to parse XML
	        				$this->log_error( 'Failed to parse XML file. Possibly invalid XML' );
			            }
		            }
		            else
		            {
		            	$this->log_error( 'File doesn\'t exist or is empty' );
		            }

	                $this->archive( $xml_file );
                }
			}
			else
			{
				$this->log_error( 'No XML\'s found to process' );
			}

			$this->clean_up_old_xmls();
		}

		return true;
	}

	public function import()
	{
		global $wpdb;

		$imported_ref_key = ( ( $this->import_id != '' ) ? '_imported_ref_' . $this->import_id : '_imported_ref' );
		$imported_ref_key = apply_filters( 'houzez_property_feed_property_imported_ref_key', $imported_ref_key, $this->import_id );

		$import_settings = get_import_settings_from_id( $this->import_id );

		$local_directory = $import_settings['local_directory'];

		$this->import_start();

		do_action( "houzez_property_feed_pre_import_properties", $this->properties, $this->import_id );
        do_action( "houzez_property_feed_pre_import_properties_openimmo", $this->properties, $this->import_id );

        $this->properties = apply_filters( "houzez_property_feed_properties_due_import", $this->properties, $this->import_id );
        $this->properties = apply_filters( "houzez_property_feed_properties_due_import_openimmo", $this->properties, $this->import_id );

        $limit = apply_filters( "houzez_property_feed_property_limit", 25 );
        $additional_message = '';
        if ( $limit !== false )
        {
        	$this->properties = array_slice( $this->properties, 0, $limit );
        	$additional_message = '. <a href="https://houzezpropertyfeed.com/#pricing" target="_blank">Upgrade to PRO</a> to import unlimited properties';
        }

		$this->log( 'Beginning to loop through ' . count($this->properties) . ' properties' . $additional_message );

		$property_row = 1;
		foreach ( $this->properties as $property )
		{
			$openimmo_id = (string)$property->verwaltung_techn->openimmo_obid;

			$this->log( 'Importing property ' . $property_row . ' with reference ' . $openimmo_id, $openimmo_id, 0, '', false );

			$this->ping(array('status' => 'importing', 'property' => $property_row, 'total' => count($this->properties)));

			$inserted_updated = false;

			$args = array(
	            'post_type' => 'property',
	            'posts_per_page' => 1,
	            'post_status' => 'any',
	            'meta_query' => array(
	            	array(
		            	'key' => $imported_ref_key,
		            	'value' => $openimmo_id
		            )
	            )
	        );
	        $property_query = new WP_Query($args);

	        $display_address = array();
	        if ( isset($property->freitexte->objekttitel) && (string)$property->freitexte->objekttitel != '' )
	        {
	        	$display_address[] = (string)$property->freitexte->objekttitel;
	        }
	        else
	        {
				if ( isset($property->geo->strasse) && (string)$property->geo->strasse != '' )
				{
					$display_address[] = (string)$property->geo->strasse;
				}
				if ( isset($property->geo->ort) && (string)$property->geo->ort != '' )
				{
					$display_address[] = (string)$property->geo->ort;
				}
				if ( isset($property->geo->bundesland) && (string)$property->geo->bundesland != '' )
				{
					$display_address[] = (string)$property->geo->bundesland;
				}
			}
			$display_address = implode(", ", $display_address);

			$summary_description = '';
			if ( isset($property->freitexte->dreizeiler) && (string)$property->freitexte->dreizeiler != '' )
	        {
	        	$summary_description = (string)$property->freitexte->dreizeiler;
	        }

	        $full_description = '';
			if ( isset($property->freitexte->objektbeschreibung) && (string)$property->freitexte->objektbeschreibung != '' )
	        {
	        	$full_description = (string)$property->freitexte->objektbeschreibung;
	        }
	        
	        if ($property_query->have_posts())
	        {
	        	$this->log( 'This property has been imported before. Updating it', $openimmo_id );

	        	// We've imported this property before
	            while ($property_query->have_posts())
	            {
	                $property_query->the_post();

	                $post_id = get_the_ID();

	                $my_post = array(
				    	'ID'          	 => $post_id,
				    	'post_title'     => wp_strip_all_tags( $display_address ),
				    	'post_excerpt'   => $summary_description,
				    	'post_content' 	 => $full_description,
				    	'post_status'    => 'publish',
				  	);

				 	// Update the post into the database
				    $post_id = wp_update_post( $my_post, true );

				    if ( is_wp_error( $post_id ) ) 
					{
						$this->log_error( 'Failed to update post. The error was as follows: ' . $post_id->get_error_message(), $openimmo_id );
					}
					else
					{
						$inserted_updated = 'updated';
					}
	            }
	        }
	        else
	        {
	        	$this->log( 'This property hasn\'t been imported before. Inserting it', $openimmo_id );

	        	// We've not imported this property before
				$postdata = array(
					'post_excerpt'   => $summary_description,
					'post_content' 	 => $full_description,
					'post_title'     => wp_strip_all_tags( $display_address ),
					'post_status'    => 'publish',
					'post_type'      => 'property',
					'comment_status' => 'closed',
				);

				$post_id = wp_insert_post( $postdata, true );

				if ( is_wp_error( $post_id ) ) 
				{
					$this->log_error( 'Failed to insert post. The error was as follows: ' . $post_id->get_error_message(), $openimmo_id );
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

				$this->log( 'Successfully ' . $inserted_updated . ' post', $openimmo_id, $post_id );

				update_post_meta( $post_id, $imported_ref_key, $openimmo_id );

				update_post_meta( $post_id, '_property_import_data', $property->asXML() );

				$department = 'residential-sales';
				if ( isset($property->objektkategorie->vermarktungsart ) )
				{
					$vermarktungsart_attributes = $property->objektkategorie->vermarktungsart->attributes();

					if ( isset($vermarktungsart_attributes['MIETE_PACHT']) && ( (string)$vermarktungsart_attributes['MIETE_PACHT'] == '1' || (string)$vermarktungsart_attributes['MIETE_PACHT'] == 'true' ) )
					{
						$department = 'residential-lettings';
					}
				}

            	if ( $department == 'residential-sales' )
            	{
            		$price_attributes = $property->preise->kaufpreis->attributes();

            		if ( isset($price_attributes['auf_anfrage']) && (string)$price_attributes['auf_anfrage'] == '1' ) 
	                {
	                    update_post_meta( $post_id, 'fave_property_price', 'POA');
	                    update_post_meta( $post_id, 'fave_property_price_postfix', '' );
	                }
	                else
	                {
	                	$price = round(preg_replace("/[^0-9.]/", '', (string)$property->preise->kaufpreis));

	                    update_post_meta( $post_id, 'fave_property_price_prefix', '' );
	                    update_post_meta( $post_id, 'fave_property_price', $price );
	                    update_post_meta( $post_id, 'fave_property_price_postfix', '' );
	                }
                }

                if ( $department == 'residential-lettings' )
                {
                	$price_attributes = $property->preise->miete->attributes();

            		if ( isset($price_attributes['auf_anfrage']) && (string)$price_attributes['auf_anfrage'] == '1' ) 
	                {
	                    update_post_meta( $post_id, 'fave_property_price', 'POA');
	                    update_post_meta( $post_id, 'fave_property_price_postfix', '' );
	                }
	                else
	                {	
	                	$price = '';
	                	if ( isset($property->preise->kaltmiete) && (string)$property->preise->kaltmiete != '' )
	                	{
	                		$price = round(preg_replace("/[^0-9.]/", '', (string)$property->preise->kaltmiete));
		                }
	                    update_post_meta( $post_id, 'fave_property_price_prefix', '' );
	                    update_post_meta( $post_id, 'fave_property_price', $price );
	                    update_post_meta( $post_id, 'fave_property_price_postfix', '' );

	                	$rent_frequency = 'pcm';
	                	if ( isset($property->preise->preis_zeiteinheit) )
	                	{
							switch (strtolower($property->preise->preis_zeiteinheit))
							{
								case "tag": { $rent_frequency = 'pd'; break; }
								case "woche": { $rent_frequency = 'pw'; break; }
								case "quartal": { $rent_frequency = 'pq'; break; }
								case "jahr": { $rent_frequency = 'pa'; break; }
							}
						}
						update_post_meta( $post_id, 'fave_property_price_postfix', $rent_frequency );
					}
                }

                update_post_meta( $post_id, 'fave_property_bedrooms', ( ( isset($property->flaechen->anzahl_schlafzimmer) && !empty((string)$property->flaechen->anzahl_schlafzimmer) ) ? round((string)$property->flaechen->anzahl_schlafzimmer) : '' ) );
	            update_post_meta( $post_id, 'fave_property_bathrooms', ( ( isset($property->flaechen->anzahl_badezimmer) && !empty((string)$property->flaechen->anzahl_badezimmer) ) ? round((string)$property->flaechen->anzahl_badezimmer) : '' ) );
	            update_post_meta( $post_id, 'fave_property_rooms', '' );
	            
	            update_post_meta( $post_id, 'fave_property_size', ( ( isset($property->flaechen->anzahl_schlafzimmer) && !empty((string)$property->flaechen->wohnflaeche) ) ? round((string)$property->flaechen->wohnflaeche) : '' ) );
	            update_post_meta( $post_id, 'fave_property_size_prefix', ( ( isset($property->flaechen->anzahl_schlafzimmer) && !empty((string)$property->flaechen->wohnflaeche) ) ? 'Sq M' : '' ) );
	            update_post_meta( $post_id, 'fave_property_land', ( ( isset($property->flaechen->grundstuecksflaeche) && !empty((string)$property->flaechen->grundstuecksflaeche) ) ? round((string)$property->flaechen->grundstuecksflaeche) : '' ) );
	            update_post_meta( $post_id, 'fave_property_land_postfix', ( ( isset($property->flaechen->grundstuecksflaeche) && !empty((string)$property->flaechen->grundstuecksflaeche) ) ? 'Sq M' : '' ) );

	            /*update_post_meta( $post_id, 'fave_property_garage', '' );*/
	            update_post_meta( $post_id, 'fave_property_id', (string)$property->verwaltung_techn->objektnr_intern );

	            $address_parts = array();
	            if ( isset($property->geo->ort) && (string)$property->geo->ort != '' )
				{
					$address_parts[] = (string)$property->geo->ort;
				}
				if ( isset($property->geo->bundesland) && (string)$property->geo->bundesland != '' )
				{
					$address_parts[] = (string)$property->geo->bundesland;
				}

	            update_post_meta( $post_id, 'fave_property_map', '1' );
	            update_post_meta( $post_id, 'fave_property_map_address', implode(", ", $address_parts) );
	            $lat = '';
	            $lng = '';
	            if ( isset($property->geo->geokoordinaten) )
	            {
	            	$geokoordinaten_attributes = $property->geo->geokoordinaten->attributes();
		            if ( isset($geokoordinaten_attributes['breitengrad']) && !empty((string)$geokoordinaten_attributes['breitengrad']) )
		            {
		                update_post_meta( $post_id, 'houzez_geolocation_lat', (string)$geokoordinaten_attributes['breitengrad'] );
		                $lat = (string)$geokoordinaten_attributes['breitengrad'];
		            }
		            if ( isset($geokoordinaten_attributes['laengengrad']) && !empty((string)$geokoordinaten_attributes['laengengrad']) )
		            {
		                update_post_meta( $post_id, 'houzez_geolocation_long', (string)$geokoordinaten_attributes['laengengrad'] );
		                $lng = (string)$geokoordinaten_attributes['laengengrad'];
		            }
		        }
	            update_post_meta( $post_id, 'fave_property_location', $lat . "," . $lng . ",14" );
	            //update_post_meta( $post_id, 'fave_property_country', (string)$property->country );
	            
	            $address_parts = array();
	            if ( isset($property->geo->strasse) && (string)$property->geo->strasse != '' )
				{
					$address_parts[] = (string)$property->geo->ort;
				}
	            update_post_meta( $post_id, 'fave_property_address', implode(", ", $address_parts) );
	            update_post_meta( $post_id, 'fave_property_zip', ( ( isset($property->geo->plz) && !empty((string)$property->geo->plz) ) ? (string)$property->geo->plz : '' ) );

	            //update_post_meta( $post_id, 'fave_featured', ( ( isset($property->prime) && (string)$property->prime == '1' ) ? '1' : '0' ) );
	            
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
		            				case "Person ID":
		            				{
		            					$value_in_feed_to_check = isset($property->kontaktperson->personennummer) ? (string)$property->kontaktperson->personennummer : '';
		            				}
		            				case "Person Name":
		            				{
		            					$value_in_feed_to_check = ( isset($property->kontaktperson->vorname) && isset($property->kontaktperson->name) ) ? (string)$property->kontaktperson->vorname . ' ' . (string)$property->kontaktperson->name : '';
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
		            				case "Person ID":
		            				{
		            					$value_in_feed_to_check = isset($property->kontaktperson->personennummer) ? (string)$property->kontaktperson->personennummer : '';
		            				}
		            				case "Person Name":
		            				{
		            					$value_in_feed_to_check = ( isset($property->kontaktperson->vorname) && isset($property->kontaktperson->name) ) ? (string)$property->kontaktperson->vorname . ' ' . (string)$property->kontaktperson->name : '';
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
		            				case "Person ID":
		            				{
		            					$value_in_feed_to_check = isset($property->kontaktperson->personennummer) ? (string)$property->kontaktperson->personennummer : '';
		            				}
		            				case "Person Name":
		            				{
		            					$value_in_feed_to_check = ( isset($property->kontaktperson->vorname) && isset($property->kontaktperson->name) ) ? (string)$property->kontaktperson->vorname . ' ' . (string)$property->kontaktperson->name : '';
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
	            /*$feature_term_ids = array();
	            if ( isset($property->features) && !empty($property->features) )
				{
					foreach ( $property->features->feature as $feature )
					{
						$feature = (string)$feature;

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
				}*/

				$mappings = ( isset($import_settings['mappings']) && is_array($import_settings['mappings']) && !empty($import_settings['mappings']) ) ? $import_settings['mappings'] : array();
				
				$mapping_name = 'lettings_status';
				if ( $department == 'residential-sales' )
				{
					$mapping_name = 'sales_status';
				}

				$taxonomy_mappings = ( isset($mappings[$mapping_name]) && is_array($mappings[$mapping_name]) && !empty($mappings[$mapping_name]) ) ? $mappings[$mapping_name] : array();

				if ( isset($property->zustand_angaben->verkaufstatus) )
				{
					$verkaufstatus_attributes = $property->zustand_angaben->verkaufstatus->attributes();

					if ( isset($verkaufstatus_attributes['stand']) && (string)$verkaufstatus_attributes['stand'] != '' )
					{
						if ( isset($taxonomy_mappings[(string)$verkaufstatus_attributes['stand']]) && !empty($taxonomy_mappings[(string)$verkaufstatus_attributes['stand']]) )
						{
							wp_set_object_terms( $post_id, (int)$taxonomy_mappings[(string)$verkaufstatus_attributes['stand']], "property_status" );
						}
						else
						{
							$this->log( 'Received status of ' . (string)$verkaufstatus_attributes['stand'] . ' that isn\'t mapped in the import settings', $openimmo_id, $post_id );

							$import_settings = $this->add_missing_mapping( $mappings, $mapping_name, (string)$verkaufstatus_attributes['stand'], $this->import_id );
						}
					}
				}

				// property type taxonomies
				$taxonomy_mappings = ( isset($mappings['property_type']) && is_array($mappings['property_type']) && !empty($mappings['property_type']) ) ? $mappings['property_type'] : array();

				if ( isset($property->objektkategorie->objektart->haus) )
				{
					$haus_attributes = $property->objektkategorie->objektart->haus->attributes();

					if ( isset($haus_attributes['haustyp']) && !empty((string)$haus_attributes['haustyp']) )
					{
						if ( isset($taxonomy_mappings[(string)$haus_attributes['haustyp']]) && !empty($taxonomy_mappings[(string)$haus_attributes['haustyp']]) )
						{
							wp_set_object_terms( $post_id, (int)$taxonomy_mappings[(string)$haus_attributes['haustyp']], "property_type" );
						}
						else
						{
							$this->log( 'Received property type of ' . (string)$haus_attributes['haustyp'] . ' that isn\'t mapped in the import settings', $openimmo_id, $post_id );

							$import_settings = $this->add_missing_mapping( $mappings, 'property_type', (string)$haus_attributes['haustyp'], $this->import_id );
						}
					}
				}
				elseif ( isset($property->objektkategorie->objektart->wohnung) )
				{
					$wohnung_attributes = $property->objektkategorie->objektart->wohnung->attributes();

					if ( isset($wohnung_attributes['wohnungtyp']) && !empty((string)$wohnung_attributes['wohnungtyp']) )
					{
						if ( isset($taxonomy_mappings[(string)$wohnung_attributes['wohnungtyp']]) && !empty($taxonomy_mappings[(string)$wohnung_attributes['wohnungtyp']]) )
						{
							wp_set_object_terms( $post_id, (int)$taxonomy_mappings[(string)$wohnung_attributes['wohnungtyp']], "property_type" );
						}
						else
						{
							$this->log( 'Received property type of ' . (string)$wohnung_attributes['wohnungtyp'] . ' that isn\'t mapped in the import settings', $openimmo_id, $post_id );

							$import_settings = $this->add_missing_mapping( $mappings, 'property_type', (string)$wohnung_attributes['wohnungtyp'], $this->import_id );
						}
					}
				}
				elseif ( isset($property->objektkategorie->objektart->zinshaus_renditeobjekt) )
				{
					$zinshaus_attributes = $property->objektkategorie->objektart->zinshaus_renditeobjekt->attributes();

					if ( isset($zinshaus_attributes['zins_typ']) && !empty((string)$zinshaus_attributes['zins_typ']) )
					{
						if ( isset($taxonomy_mappings[(string)$zinshaus_attributes['zins_typ']]) && !empty($taxonomy_mappings[(string)$zinshaus_attributes['zins_typ']]) )
						{
							wp_set_object_terms( $post_id, (int)$taxonomy_mappings[(string)$zinshaus_attributes['zins_typ']], "property_type" );
						}
						else
						{
							$this->log( 'Received property type of ' . (string)$zinshaus_attributes['zins_typ'] . ' that isn\'t mapped in the import settings', $openimmo_id, $post_id );

							$import_settings = $this->add_missing_mapping( $mappings, 'property_type', (string)$zinshaus_attributes['zins_typ'], $this->import_id );
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
						if ( isset($property->geo->{$address_field_to_use}) && !empty((string)$property->geo->{$address_field_to_use}) )
		            	{
		            		$term = term_exists( trim((string)$property->geo->{$address_field_to_use}), $location_taxonomy);
							if ( $term !== 0 && $term !== null && isset($term['term_id']) )
							{
								$location_term_ids[] = (int)$term['term_id'];
							}
							else
							{
								if ( $create_location_taxonomy_terms === true )
								{
									$term = wp_insert_term( trim((string)$property->geo->{$address_field_to_use}), $location_taxonomy );
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
					apply_filters('houzez_property_feed_images_stored_as_urls_openimmo', false, $post_id, $property, $this->import_id) === true
				)
				{
					$urls = array();

					if (isset($property->anhaenge->anhang) && !empty($property->anhaenge->anhang))
	                {
	                    foreach ($property->anhaenge->anhang as $image)
	                    {
	                        $image_attributes = $image->attributes();
	                        if ( 
	                        	isset($image_attributes['gruppe']) && 
	                        	in_array((string)$image_attributes['gruppe'], array('TITELBILD', 'BILD', 'INNENANSICHTEN', 'AUSSENANSICHTEN')) &&
	                        	isset($image->daten->pfad)
	                        )
	                        {
								$url = trim((string)$image->daten->pfad);
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

					$this->log( 'Imported ' . count($urls) . ' photo URLs', $openimmo_id, $post_id );
				}
				else
				{
					$media_ids = array();
					$new = 0;
					$existing = 0;
					$deleted = 0;
					$image_i = 0;
					$previous_media_ids = get_post_meta( $post_id, 'fave_property_images' );

					if (isset($property->anhaenge->anhang) && !empty($property->anhaenge->anhang))
	                {
	                    foreach ($property->anhaenge->anhang as $image)
	                    {
	                        $image_attributes = $image->attributes();
	                        if ( 
	                        	isset($image_attributes['gruppe']) && 
	                        	in_array((string)$image_attributes['gruppe'], array('TITELBILD', 'BILD', 'INNENANSICHTEN', 'AUSSENANSICHTEN')) &&
	                        	isset($image->daten->pfad)
	                        )
	                        {
								$url = trim((string)$image->daten->pfad);
								if ( 
									substr( strtolower($url), 0, 2 ) == '//' ||
									substr( strtolower($url), 0, 4 ) == 'http'
								)
								{
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
									        $this->log_error( 'An error occurred whilst importing ' . $url . '. The error was as follows: ' . $tmp->get_error_message(), $openimmo_id, $post_id );
									    }
									    else
									    {
										    $id = media_handle_sideload( $file_array, $post_id, $description );

										    // Check for handle sideload errors.
										    if ( is_wp_error( $id ) ) 
										    {
										        @unlink( $file_array['tmp_name'] );
										        
										        $this->log_error( 'ERROR: An error occurred whilst importing ' . $url . '. The error was as follows: ' . $id->get_error_message(), $openimmo_id, $post_id );
										    }
										    else
										    {
										    	$media_ids[] = $id;

										    	update_post_meta( $id, '_imported_url', $url);

										    	if ( $image_i == 0 ) set_post_thumbnail( $post_id, $id );

										    	++$new;

										    	++$image_i;
											}
										}
									}
								}
								else
								{
									// Not a URL. Must've been physically uploaded or already exists
									$media_file_name = trim((string)$image->daten->pfad);
									$description = '';

									if ( file_exists( $local_directory . '/' . $media_file_name ) )
									{
										$upload = true;
		                                $replacing_attachment_id = '';
		                                if ( isset($previous_media_ids[$i]) ) 
		                                {                                    
		                                    // get this attachment
		                                    $current_image_path = get_post_meta( $previous_media_ids[$i], '_imported_path', TRUE );
		                                    $current_image_size = filesize( $current_image_path );
		                                    
		                                    if ($current_image_size > 0 && $current_image_size !== FALSE)
		                                    {
		                                        $replacing_attachment_id = $previous_media_ids[$i];
		                                        
		                                        $new_image_size = filesize( $local_directory . '/' . $media_file_name );
		                                        
		                                        if ($new_image_size > 0 && $new_image_size !== FALSE)
		                                        {
		                                            if ($current_image_size == $new_image_size)
		                                            {
		                                                $upload = false;
		                                            }
		                                            else
		                                            {
		                                                
		                                            }
		                                        }
		                                        else
			                                    {
			                                    	$this->log_error( 'Failed to get filesize of new image file ' . $local_directory . '/' . $media_file_name, $openimmo_id );
			                                    }
		                                        
		                                        unset($new_image_size);
		                                    }
		                                    else
		                                    {
		                                    	$this->log_error( 'Failed to get filesize of existing image file ' . $current_image_path, $openimmo_id );
		                                    }
		                                    
		                                    unset($current_image_size);
		                                }

		                                if ($upload)
		                                {
		                                	$this->ping();

		                                	$description = ( $description != '' ) ? $description : preg_replace('/\.[^.]+$/', '', trim($media_file_name, '_'));

											// We've physically received the file
											$upload = wp_upload_bits(trim($media_file_name, '_'), null, file_get_contents($local_directory . '/' . $media_file_name));  
											
											if( isset($upload['error']) && $upload['error'] !== FALSE )
											{
												$this->log_error( print_r($upload['error'], TRUE), $openimmo_id );
											}
											else
											{
												// We don't already have a thumbnail and we're presented with an image
												$wp_filetype = wp_check_filetype( $upload['file'], null );
											
												$attachment = array(
													//'guid' => $wp_upload_dir['url'] . '/' . trim($media_file_name, '_'), 
													'post_mime_type' => $wp_filetype['type'],
													'post_title' => $description,
													'post_content' => '',
													'post_status' => 'inherit'
												);
												$attach_id = wp_insert_attachment( $attachment, $upload['file'], $post_id );
												
												if ( $attach_id === FALSE || $attach_id == 0 )
												{    
													$this->log_error( 'Failed inserting image attachment ' . $upload['file'] . ' - ' . print_r($attachment, TRUE), $openimmo_id );
												}
												else
												{  
													$attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
													wp_update_attachment_metadata( $attach_id, $attach_data );

													update_post_meta( $attach_id, '_imported_path', $upload['file']);

													$media_ids[] = $attach_id;

													if ( $image_i == 0 ) set_post_thumbnail( $post_id, $attach_id );

													++$new;

													++$image_i;

													update_option( 'houzez_property_feed_property_image_media_ids_' . $this->import_id, $post_id . '|' . implode(",", $media_ids), false );
												}
											}

											$files_to_unlink[] = $local_directory . '/' . $media_file_name;
			                            }
			                            else
			                            {
			                            	if ( isset($previous_media_ids[$i]) ) 
		                                	{
		                                		$media_ids[] = $previous_media_ids[$i];

		                                		if ( $description != '' )
												{
													$my_post = array(
												    	'ID'          	 => $previous_media_ids[$i],
												    	'post_title'     => $description,
												    );

												 	// Update the post into the database
												    wp_update_post( $my_post );
												}

												if ( $image_i == 0 ) set_post_thumbnail( $post_id, $previous_media_ids[$i] );

												++$existing;

												++$image_i;

												update_option( 'houzez_property_feed_property_image_media_ids_' . $this->import_id, $post_id . '|' . implode(",", $media_ids), false );
		                                	}

		                                	$files_to_unlink[] =$local_directory . '/' . $media_file_name;
			                            }
									}
									else
									{
										if ( isset($previous_media_ids[$i]) ) 
				                    	{
				                    		$media_ids[] = $previous_media_ids[$i];

				                    		if ( $description != '' )
											{
												$my_post = array(
											    	'ID'          	 => $previous_media_ids[$i],
											    	'post_title'     => $description,
											    );

											 	// Update the post into the database
											    wp_update_post( $my_post );
											}

											if ( $image_i == 0 ) set_post_thumbnail( $post_id, $previous_media_ids[$i] );

											++$existing;

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

					$this->log( 'Imported ' . count($media_ids) . ' photos (' . $new . ' new, ' . $existing . ' existing, ' . $deleted . ' deleted)', $openimmo_id, $post_id );
				}

				// Floorplans
				$floorplans = array();
				$media_ids = array();
				$previous_media_ids = get_post_meta( $post_id, '_floorplan_attachment_ids', TRUE );
				$media_i = 0;
				$files_to_unlink = array();
				if (isset($property->anhaenge->anhang) && !empty($property->anhaenge->anhang))
                {
                    foreach ($property->anhaenge->anhang as $image)
                    {
                        $image_attributes = $image->attributes();
                        if ( 
                        	isset($image_attributes['gruppe']) && 
                        	in_array((string)$image_attributes['gruppe'], array('GRUNDRISS', 'KARTEN_LAGEPLAN')) &&
                        	isset($image->daten->pfad)
                        )
                        {
							$url = trim((string)$image->daten->pfad);
							if ( 
								substr( strtolower($url), 0, 2 ) == '//' ||
								substr( strtolower($url), 0, 4 ) == 'http'
							)
							{
								$floorplans[] = array( 
									"fave_plan_title" => __( 'Floorplan', 'houzezpropertyfeed' ), 
									"fave_plan_image" => $property['MEDIA_FLOOR_PLAN_' . $j]
								);
							}
							else
							{
								// Not a URL. Must've been physically uploaded or already exists
								$media_file_name = (string)$image->daten->pfad;
								$description = '';

								if ( file_exists( $local_directory . '/' . $media_file_name ) )
								{
									$upload = true;
	                                $replacing_attachment_id = '';
	                                if ( isset($previous_media_ids[$media_i]) ) 
	                                {                                    
	                                    // get this attachment
	                                    $current_image_path = get_post_meta( $previous_media_ids[$media_i], '_imported_path', TRUE );
	                                    $current_image_size = filesize( $current_image_path );
	                                    
	                                    if ($current_image_size > 0 && $current_image_size !== FALSE)
	                                    {
	                                        $replacing_attachment_id = $previous_media_ids[$media_i];
	                                        
	                                        $new_image_size = filesize( $local_directory . '/' . $media_file_name );
	                                        
	                                        if ($new_image_size > 0 && $new_image_size !== FALSE)
	                                        {
	                                            if ($current_image_size == $new_image_size)
	                                            {
	                                                $upload = false;
	                                            }
	                                            else
	                                            {
	                                                
	                                            }
	                                        }
	                                        else
		                                    {
		                                    	$this->log_error( 'Failed to get filesize of new floorplan file ' . $local_directory . '/' . $media_file_name, $openimmo_id );
		                                    }
	                                        
	                                        unset($new_image_size);
	                                    }
	                                    else
	                                    {
	                                    	$this->log_error( 'Failed to get filesize of existing floorplan file ' . $current_image_path, $openimmo_id );
	                                    }
	                                    
	                                    unset($current_image_size);
	                                }

	                                if ($upload)
	                                {
	                                	$this->ping();

										// We've physically received the file
										$upload = wp_upload_bits(trim($media_file_name, '_'), null, file_get_contents($local_directory . '/' . $media_file_name));  
										$this->log( print_r($upload, TRUE) );
										if( isset($upload['error']) && $upload['error'] !== FALSE )
										{
											$this->log_error( print_r($upload['error'], TRUE), $openimmo_id );
										}
										else
										{
											// We don't already have a thumbnail and we're presented with an image
											$wp_filetype = wp_check_filetype( $upload['file'], null );
										
											$attachment = array(
												//'guid' => $wp_upload_dir['url'] . '/' . trim($media_file_name, '_'), 
												'post_mime_type' => $wp_filetype['type'],
												'post_title' => $description,
												'post_content' => '',
												'post_status' => 'inherit'
											);
											$attach_id = wp_insert_attachment( $attachment, $upload['file'], $post_id );
											
											if ( $attach_id === FALSE || $attach_id == 0 )
											{    
												$this->log_error( 'Failed inserting floorplan attachment ' . $upload['file'] . ' - ' . print_r($attachment, TRUE), $openimmo_id );
											}
											else
											{  
												$attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
												wp_update_attachment_metadata( $attach_id, $attach_data );

												update_post_meta( $attach_id, '_imported_path', $upload['file']);

												$media_ids[] = $attach_id;

												$floorplans[] = array( 
													"fave_plan_title" => __( 'Floorplan', 'houzezpropertyfeed' ), 
													"fave_plan_image" => wp_get_attachment_url($attach_id),
												);
											}
										}

										$files_to_unlink[] = $local_directory . '/' . $media_file_name;
		                            }
		                            else
		                            {
		                            	if ( isset($previous_media_ids[$media_i]) ) 
	                                	{
	                                		$media_ids[] = $previous_media_ids[$media_i];

	                                		if ( $description != '' )
											{
												$my_post = array(
											    	'ID'          	 => $previous_media_ids[$media_i],
											    	'post_title'     => $description,
											    );

											 	// Update the post into the database
											    wp_update_post( $my_post );
											}

											$floorplans[] = array( 
												"fave_plan_title" => __( 'Floorplan', 'houzezpropertyfeed' ), 
												"fave_plan_image" => wp_get_attachment_url($previous_media_ids[$media_i]),
											);
	                                	}

	                                	$files_to_unlink[] = $local_directory . '/' . $media_file_name;
		                            }
								}
								else
								{
									if ( isset($previous_media_ids[$media_i]) ) 
			                    	{
			                    		$media_ids[] = $previous_media_ids[$media_i];

			                    		if ( $description != '' )
										{
											$my_post = array(
										    	'ID'          	 => $previous_media_ids[$media_i],
										    	'post_title'     => $description,
										    );

										 	// Update the post into the database
										    wp_update_post( $my_post );
										}

										$floorplans[] = array( 
											"fave_plan_title" => __( 'Floorplan', 'houzezpropertyfeed' ), 
											"fave_plan_image" => wp_get_attachment_url($previous_media_ids[$media_i]),
										);
			                    	}
								}
								++$media_i;
							}
						}
					}
				}

				update_post_meta( $post_id, '_floorplan_attachment_ids', $media_ids );

				if ( !empty($floorplans) )
				{
	                update_post_meta( $post_id, 'floor_plans', $floorplans );
	                update_post_meta( $post_id, 'fave_floor_plans_enable', 'enable' );
	            }
	            else
	            {
	            	update_post_meta( $post_id, 'fave_floor_plans_enable', 'disable' );
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

	            if ( !empty($files_to_unlink) )
				{
					foreach ( $files_to_unlink as $file_to_unlink )
					{
						unlink($file_to_unlink);
					}
				}

				$this->log( 'Imported ' . count($floorplans) . ' floorplans', $openimmo_id, $post_id );

				update_post_meta( $post_id, 'fave_video_url', '' );
				update_post_meta( $post_id, 'fave_virtual_tour', '' );

				$virtual_tours = array();
				if (isset($property->anhaenge->anhang) && !empty($property->anhaenge->anhang))
                {
                    foreach ($property->anhaenge->anhang as $image)
                    {
                        $image_attributes = $image->attributes();
                        if ( 
                        	isset($image_attributes['gruppe']) && 
                        	in_array((string)$image_attributes['gruppe'], array('FILM', 'FILMLINK')) &&
                        	isset($image->daten->pfad)
                        )
                        {
							$url = trim((string)$image->daten->pfad);
							if ( 
								substr( strtolower($url), 0, 2 ) == '//' ||
								substr( strtolower($url), 0, 4 ) == 'http'
							)
							{
                				$virtual_tours[] = trim($url);
                			}
                		}
                	}
                }

                $virtual_tours = array_filter($virtual_tours);

				if ( !empty($virtual_tours) )
                {
                    foreach ($virtual_tours as $virtual_tour )
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

				do_action( "houzez_property_feed_property_imported", $post_id, $property, $this->import_id );
				do_action( "houzez_property_feed_property_imported_openimmo", $post_id, $property, $this->import_id );

				$post = get_post( $post_id );
				do_action( "save_post_property", $post_id, $post, false );
				do_action( "save_post", $post_id, $post, false );

				if ( $inserted_updated == 'updated' )
				{
					$this->compare_meta_and_taxonomy_data( $post_id, $openimmo_id, $metadata_before, $taxonomy_terms_before );
				}
			}

			++$property_row;

		} // end foreach property

		do_action( "houzez_property_feed_post_import_properties_openimmo", $this->import_id );

		$this->import_end();
	}

	private function clean_up_old_xmls()
    {
    	$import_settings = get_import_settings_from_id( $this->import_id );

    	$local_directory = $import_settings['local_directory'];

    	// Clean up processed .XMLs and unused media older than 7 days old (7 days = 604800 seconds)
		if ($handle = opendir($local_directory)) 
		{
		    while (false !== ($file = readdir($handle))) 
		    {
		        if (
		        	$file != "." && $file != ".." && 
		        	(
		        		substr($file, -9) == 'processed' || 
		        		substr(strtolower($file), -4) == '.jpg' || 
		        		substr(strtolower($file), -4) == '.gif' || 
		        		substr(strtolower($file), -5) == '.jpeg' || 
		        		substr(strtolower($file), -4) == '.png' || 
		        		substr(strtolower($file), -4) == '.bmp' || 
		        		substr(strtolower($file), -4) == '.pdf'
		        	)
		        ) 
		        {
		        	if ( filemtime($local_directory . '/' . $file) !== FALSE && filemtime($local_directory . '/' . $file) < (time() - 604800) )
		        	{
		        		unlink($local_directory . '/' . $file);
		        	}
		        }
		    }
		    closedir($handle);
		}
		else
		{
			$this->log_error( 'Failed to read from directory ' . $local_directory . '. Please ensure the local directory specified exists, is the full server path and is readable.' );
			return false;
		}
	}

	private function archive( $xml_file )
    {
    	// Rename to append the date and '.processed' as to not get picked up again. Will be cleaned up every 7 days
    	$new_target_file = $xml_file . '-' . time() .'.processed';
		rename( $xml_file, $new_target_file );
		
		$this->log( 'Archived XML. Available for download for 7 days: <a href="' . str_replace("/includes/import-formats", "", plugin_dir_url( __FILE__ )) . "/download.php?import_id=" . $this->import_id . "&file=" . base64_encode(basename($new_target_file)) . '" target="_blank">Download</a>' );
	}
}

}
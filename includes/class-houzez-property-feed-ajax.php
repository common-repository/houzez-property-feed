<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Houzez Property Feed Ajax Functions
 */
class Houzez_Property_Feed_Ajax {

	public function __construct() {

        add_action( "wp_ajax_houzez_property_feed_fetch_xml_nodes", array( $this, "fetch_xml_nodes" ) );

        add_action( "wp_ajax_houzez_property_feed_fetch_csv_fields", array( $this, "fetch_csv_fields" ) );

        add_action( "wp_ajax_houzez_property_feed_draw_automatic_imports_table", array( $this, "draw_automatic_imports_table" ) );

        add_action( "wp_ajax_houzez_property_feed_get_running_status", array( $this, "get_running_status" ) );

	}

    public function fetch_xml_nodes()
    {
        header( 'Content-Type: application/json; charset=utf-8' );

        if ( !wp_verify_nonce( $_GET['ajax_nonce'], "hpf_ajax_nonce" ) ) 
        {
            $return = array(
                'success' => false,
                'error' => __( 'Invalid nonce provided', 'houzezpropertyfeed' )
            );
            echo json_encode($return);
            die();
        } 

        // nonce ok. Let's get the XML

        $contents = '';

        $args = array( 'timeout' => 120, 'sslverify' => false );
        $args = apply_filters( 'houzez_property_feed_xml_request_args', $args, $_GET['url'] );
        $response = wp_remote_get( $_GET['url'], $args );
        if ( !is_wp_error($response) && is_array( $response ) ) 
        {
            $contents = $response['body'];
        }
        else
        {
            $error = __( 'Failed to obtain XML. Dump of response as follows', 'houzezpropertyfeed' ) . ': ' . print_r($response, TRUE);
            if ( is_wp_error($response) )
            {
                $error = $response->get_error_message();
            }
            $return = array(
                'success' => false,
                'error' => $error
            );
            echo json_encode($return);
            die();
        }

        $xml = simplexml_load_string($contents);

        if ($xml !== FALSE)
        {
            $node_names = get_all_node_names($xml, array_merge(array(''), $xml->getNamespaces(true)));
            $node_names = array_unique($node_names);

            $return = array(
                'success' => true,
                'nodes' => $node_names
            );
            echo json_encode($return);
            die();
        }
        else
        {
            // Failed to parse XML
            $return = array(
                'success' => false,
                'error' => __( 'Failed to parse XML file', 'houzezpropertyfeed' ) . ': ' . print_r($contents, TRUE)
            );
            echo json_encode($return);
            die();
        }

        wp_die();
    }

    public function fetch_csv_fields()
    {
        header( 'Content-Type: application/json; charset=utf-8' );

        if ( !wp_verify_nonce( $_GET['ajax_nonce'], "hpf_ajax_nonce" ) ) 
        {
            $return = array(
                'success' => false,
                'error' => __( 'Invalid nonce provided', 'houzezpropertyfeed' )
            );
            echo json_encode($return);
            die();
        } 

        // nonce ok. Let's get the XML

        $contents = '';

        $args = array( 'timeout' => 120, 'sslverify' => false );
        $args = apply_filters( 'houzez_property_feed_csv_request_args', $args, $_GET['url'] );
        $response = wp_remote_get( $_GET['url'], $args );
        if ( !is_wp_error($response) && is_array( $response ) ) 
        {
            $contents = $response['body'];
        }
        else
        {
            $error = __( 'Failed to obtain CSV. Dump of response as follows', 'houzezpropertyfeed' ) . ': ' . print_r($response, TRUE);
            if ( is_wp_error($response) )
            {
                $error = $response->get_error_message();
            }
            $return = array(
                'success' => false,
                'error' => $error
            );
            echo json_encode($return);
            die();
        }

        $lines = explode( "\n", $contents );
        $headers = str_getcsv( array_shift( $lines ), ( isset($_GET['delimiter']) ? sanitize_text_field($_GET['delimiter']) : ',' ) );

        $return = array(
            'success' => true,
            'fields' => $headers
        );
        echo json_encode($return);

        wp_die();
    }

    public function draw_automatic_imports_table()
    {
        if ( !wp_verify_nonce( $_GET['ajax_nonce'], "hpf_ajax_nonce" ) ) 
        {
            echo 'Failed to verify nonce. Please reload the page';
            die();
        }

        include( dirname(HOUZEZ_PROPERTY_FEED_PLUGIN_FILE) . '/includes/class-houzez-property-feed-admin-automatic-imports-table.php' );

        $automatic_imports_table = new Houzez_Property_Feed_Admin_Automatic_Imports_Table();
        $automatic_imports_table->prepare_items();

        echo $automatic_imports_table->display();

        wp_die();
    }

    public function get_running_status()
    {
        header( 'Content-Type: application/json; charset=utf-8' );

        if ( !wp_verify_nonce( $_GET['ajax_nonce'], "hpf_ajax_nonce" ) ) 
        {
            $return = array(
                'success' => false,
                'error' => __( 'Invalid nonce provided', 'houzezpropertyfeed' )
            );
            echo json_encode($return);
            die();
        }

        if ( !isset($_GET['import_ids']) || empty($_GET['import_ids']) )
        {
            $return = array(
                'success' => false,
                'error' => __( 'No import ID(s) passed', 'houzezpropertyfeed' )
            );
            echo json_encode($return);
            die();
        }

        global $wpdb;

        $statuses = array();

        $failed = false;

        $options = get_option( 'houzez_property_feed' , array() );

        $queued_media = array();
        if ( apply_filters( 'houzez_property_feed_pro_active', false ) === true )
        {
            if ( isset($options['media_processing']) && $options['media_processing'] === 'background' )
            {
                $media_queue_counts = $wpdb->get_results(
                    "
                    SELECT 
                        `import_id`, 
                        COUNT(DISTINCT `post_id`, `media_type`, `media_order`) AS `queued_media_count` 
                    FROM
                        " . $wpdb->prefix . "houzez_property_feed_media_queue 
                    GROUP BY 
                    `import_id`
                    "
                );
                if ( count($media_queue_counts) > 0 )
                {
                    foreach ( $media_queue_counts as $media_queue_count )
                    {
                        $queued_media[(int)$media_queue_count->import_id] = (int)$media_queue_count->queued_media_count;
                    }
                }
            }
        }

        foreach ( $_GET['import_ids'] as $import_id )
        {
            $import_id = (int)$import_id;

            $status = '';

            $row = $wpdb->get_row( $wpdb->prepare("
                SELECT 
                    end_date, status, status_date, media
                FROM 
                    {$wpdb->prefix}houzez_property_feed_logs_instance
                WHERE 
                    import_id = %d
                ORDER BY start_date DESC 
                LIMIT 1
            ", $import_id), ARRAY_A );
            if ( null !== $row )
            {
                if ( isset($row['end_date']) && $row['end_date'] != '0000-00-00 00:00:00' )
                {
                    $statuses[$import_id] = array( 
                        'status' => 'finished', 
                        'queued_media' => ( isset($queued_media[$import_id]) ? $queued_media[$import_id] : '' ) 
                    );
                    continue;
                }

                if ( isset($row['media']) && $row['media'] == '1' )
                {
                    $status = '<br>Importing media';
                    $statuses[$import_id] = array( 
                        'status' => $status, 
                        'queued_media' => ( isset($queued_media[$import_id]) ? $queued_media[$import_id] : '' ) 
                    );
                    continue;
                }

                if ( isset($row['status_date']) && $row['status_date'] != '0000-00-00 00:00:00' && isset($row['status']) && !empty($row['status']) )
                {
                    $decoded_status = json_decode($row['status'], true);

                    if ( isset($decoded_status['status']) && $decoded_status['status'] == 'importing' )
                    {
                        if ( ( ( time() - strtotime($row['status_date']) ) / 60 ) < 5 )
                        {
                            $property = isset($decoded_status['property']) ? (int)$decoded_status['property'] : 0;
                            $total = isset($decoded_status['total']) ? (int)$decoded_status['total'] : 1; // Default to 1 to avoid division by zero
                            $progress = ($property / $total) * 100;
                            
                            $status = '
                            <br>Importing property ' . $property . '/' . $total . '
                            <div class="progress-bar-container" style="width: 100%; background-color: #f3f3f3; border-radius: 5px; overflow: hidden; margin-top: 5px;">
                                <div class="progress-bar" style="width: ' . $progress . '%; height: 8px; background-color: #4caf50; text-align: center; line-height: 20px;"></div>
                            </div>';
                        }
                        else
                        {
                            $status = '<br>Failed to complete';

                            $failed = $import_id;
                        }
                    }
                    elseif ( isset($decoded_status['status']) && $decoded_status['status'] == 'parsing' )
                    {
                        if ( ( ( time() - strtotime($row['status_date']) ) / 60 ) < 5 )
                        {
                            $status = '<br>Parsing properties';
                        }
                        else
                        {
                            $status = '<br>Failed to complete';

                            $failed = $import_id;
                        }
                    }
                    elseif ( isset($decoded_status['status']) && $decoded_status['status'] == 'finished' )
                    {
                        $status = 'finished';
                    }
                }
            }

            $statuses[$import_id] = array( 
                'status' => $status, 
                'queued_media' => ( isset($queued_media[$import_id]) ? $queued_media[$import_id] : '' ) 
            );
        }

        if ( $failed !== false )
        {
            $_GET['custom_property_import_cron'] = 'houzezpropertyfeedcronhook';
            $_GET['import_id'] = $failed;

            ob_start();
            do_action('houzezpropertyfeedcronhook');
            ob_end_clean();
        }

        echo json_encode($statuses);

        wp_die();
    }
}

new Houzez_Property_Feed_Ajax();
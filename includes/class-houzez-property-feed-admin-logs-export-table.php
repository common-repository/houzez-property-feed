<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( !class_exists('WP_List_Table') )
{
   require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Houzez Property Feed Admin Logs Export Table Functions
 */
class Houzez_Property_Feed_Admin_Logs_Export_Table extends WP_List_Table {

	public function __construct( $args = array() ) 
    {
        parent::__construct( array(
            'singular'=> 'Log',
            'plural' => 'Logs',
            'ajax'   => false // We won't support Ajax for this table, ye
        ) );
	}

    public function extra_tablenav( $which ) 
    {
        /*if ( $which == "top" )
        {
            //The code that goes before the table is here
            echo"Hello, I'm before the table";
        }
        if ( $which == "bottom" )
        {
            //The code that goes after the table is there
            echo"Hi, I'm after the table";
        }*/
    }

    public function get_columns() 
    {
        $columns = array();

        $columns['col_log_date'] = __('Date / Time', 'houzezpropertyfeed' );
        $columns['col_log_duration'] = __('Duration', 'houzezpropertyfeed' );
        $columns['col_log_property'] = __('Properties Included', 'houzezpropertyfeed' );
        /*$export_id = !empty($_GET['export_id']) ? (int)$_GET['export_id'] : '';
        if ( !empty($export_id) )
        {
            $export_settings = get_export_settings_from_id( $export_id );

            if ( $export_settings !== false )
            {
                $format = get_houzez_property_feed_export_format( $export_settings['format'] );

                if ($format['method'] == 'realtime')
                {
                    $columns['col_log_property'] = __('Properties Included', 'houzezpropertyfeed' );
                }
            }
        }*/

        $columns['col_log_export_format'] = __('Export Format', 'houzezpropertyfeed' );

        return $columns;
    }

    public function column_default( $item, $column_name )
    {
        global $wpdb;

        switch( $column_name ) 
        {
            case 'col_log_date':
            {
                $return = '<strong><a href="' . admin_url('admin.php?page=houzez-property-feed-export&tab=logs&action=view&log_id=' . $item->id . ( ( isset($_GET['export_id']) && !empty((int)$_GET['export_id']) ) ? '&export_id=' . (int)$_GET['export_id'] : '' ) . '&paged=' . ( isset($_GET['paged']) ? (int)$_GET['paged'] : '' ) . '&orderby=' . ( isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : '' ) . '&order=' . ( isset($_GET['order']) ? sanitize_text_field($_GET['order']) : '' ) ) . '">' . get_date_from_gmt( $item->start_date, "H:i:s jS F Y" ) . '</a></strong>';

                $return .= '<div class="row-actions">
                        <span class="edit"><a href="' . admin_url('admin.php?page=houzez-property-feed-export&tab=logs&action=view&log_id=' . $item->id . ( ( isset($_GET['export_id']) && !empty((int)$_GET['export_id']) ) ? '&export_id=' . (int)$_GET['export_id'] : '' ) . '&paged=' . ( isset($_GET['paged']) ? (int)$_GET['paged'] : '' ) . '&orderby=' . ( isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : '' ) . '&order=' . ( isset($_GET['order']) ? sanitize_text_field($_GET['order']) : '' ) ) . '" aria-label="' . __( 'View Log', 'houzezpropertyfeed' ) . '">' . __( 'View Log', 'houzezpropertyfeed' ) . '</a></span>
                    </div>';

                return $return;
            }
            case 'col_log_duration':
            {
                if ( $item->end_date == '0000-00-00 00:00:00' )
                {
                    return '-';
                }

                $diff = '';

                $diff_secs = strtotime($item->end_date) - strtotime($item->start_date);

                if ( $diff_secs >= 60 )
                {
                    $diff_mins = floor( $diff_secs / 60 );
                    $diff = $diff_mins . ' minutes, ';
                    $diff_secs = $diff_secs - ( $diff_mins * 60 );
                }

                $diff .= $diff_secs . ' seconds';

                return $diff;
            }
            case 'col_log_property':
            {
                if ( $item->property_ids != '' )
                {
                    $explode_property_ids = explode(",", $item->property_ids);
                    if ( count($explode_property_ids) == 1 )
                    {
                        $title = get_the_title($explode_property_ids[0]);
                        if ( empty($title) )
                        {
                            $title = '(no title)';
                        }

                        return '<a href="' . get_edit_post_link($explode_property_ids[0]) . '" target="_blank">' . $title . '</a>';
                    }
                    else
                    {
                        return count($explode_property_ids) . ' ' . __( 'properties','houzezpropertyfeed' );
                    }
                }
                return '-';
                break;
            }
            case 'col_log_export_format':
            {
                $format = get_format_from_export_id( $item->export_id );

                if ( $format === false)
                {
                    return '-';
                }

                return $format['name'];
            }
            default:
                return print_r( $item, true ) ;
        }
    }

    // Adding sortable columns
    public function get_sortable_columns() 
    {
        $sortable_columns = array(
            'col_log_date' => array('start_date', 'asc')
        );
        return $sortable_columns;
    }

    public function prepare_items() 
    {
        global $wpdb;

        $columns = $this->get_columns(); 
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $per_page = apply_filters('houzez_property_feed_logs_per_page', 20);
        $current_page = $this->get_pagenum();
        $offset = ( $current_page - 1 ) * $per_page;

        $this->_column_headers = array($columns, $hidden, $sortable);

        $export_id = !empty($_GET['export_id']) ? (int)$_GET['export_id'] : '';

        /*$extra_query = "";
        if ( !empty($export_id) )
        {
            $export_settings = get_export_settings_from_id( $export_id );

            if ( $export_settings !== false )
            {
                $format = get_houzez_property_feed_export_format( $export_settings['format'] );

                if ($format['method'] == 'realtime')
                {*/
                    $extra_query = ", (
                    SELECT GROUP_CONCAT(DISTINCT post_id SEPARATOR ',')
                        FROM 
                            " . $wpdb->prefix . "houzez_property_feed_export_logs_instance_log
                        WHERE 
                            " . $wpdb->prefix . "houzez_property_feed_export_logs_instance_log.instance_id = " . $wpdb->prefix . "houzez_property_feed_export_logs_instance.id
                        AND
                            post_id != 0
                    ) AS property_ids";
                /*}
            }
        }*/

        $query = "SELECT
            COUNT(*)
        FROM 
            " . $wpdb->prefix . "houzez_property_feed_export_logs_instance ";
        if ( !empty($export_id) )
        {
            $query .= " WHERE export_id = '" . $export_id . "' ";
        }

        $totalitems = $wpdb->get_var($query);

        $orderby = (!empty($_GET['orderby'])) ? sanitize_text_field($_GET['orderby']) : 'start_date'; // default order
        $order = (!empty($_GET['order'])) ? sanitize_text_field($_GET['order']) : 'asc'; // default order direction

        $query = "SELECT
            id, 
            start_date, 
            end_date, 
            export_id " . $extra_query . "
        FROM 
            " . $wpdb->prefix . "houzez_property_feed_export_logs_instance ";
        if ( !empty($export_id) )
        {
            $query .= " WHERE export_id = '" . $export_id . "' ";
        }
        $query .= " ORDER BY $orderby $order";
        $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, ($current_page - 1) * $per_page);

        $this->items = $wpdb->get_results($query);

        $this->set_pagination_args(
            array(
                'total_items' => $totalitems,
                'per_page'    => $per_page,
                'total_pages' => ceil($totalitems / $per_page),
            )
        );
        
    }

    public function display() {
        $singular = $this->_args['singular'];

        // Add pagination above the table
        $this->display_tablenav( 'top' );

        $this->screen->render_screen_reader_content( 'heading_list' );
        ?>
<table class="wp-list-table <?php echo implode( ' ', $this->get_table_classes() ); ?>">
    <thead>
    <tr>
        <?php $this->print_column_headers(); ?>
    </tr>
    </thead>

    <tbody id="the-list"
        <?php
        if ( $singular ) {
            echo ' data-wp-lists="list:' . esc_attr($singular) . '"';
        }
        ?>
        >
        <?php $this->display_rows_or_placeholder(); ?>
    </tbody>

</table>
        <?php
        // Add pagination below the table
        $this->display_tablenav( 'bottom' );
    }

    protected function get_table_classes() {
        $mode = get_user_setting( 'posts_list_mode', 'list' );

        $mode_class = esc_attr( 'table-view-' . $mode );

        return array( 'widefat', 'striped', $mode_class, esc_attr($this->_args['plural']) );
    }

}
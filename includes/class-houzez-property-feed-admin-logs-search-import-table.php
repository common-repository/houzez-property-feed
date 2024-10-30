<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( !class_exists('WP_List_Table') )
{
   require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Houzez Property Feed Admin Logs Search Import Table Functions
 */
class Houzez_Property_Feed_Admin_Logs_Search_Import_Table extends WP_List_Table {

    private $log_id;

	public function __construct( $args = array(), $log_id = '' ) 
    {
        $this->log_id = $log_id;

        parent::__construct( array(
            'singular'=> 'Log Entry',
            'plural' => 'Log Entries',
            'ajax'   => false // We won't support Ajax for this table, yet
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
        return array(
            'col_log_date' =>__('Date / Time', 'houzezpropertyfeed' ),
            'col_log_property' =>__( 'Related To Property', 'houzezpropertyfeed' ),
            'col_log_crm_id' =>__( 'CRM ID', 'houzezpropertyfeed' ),
            'col_log_entry' =>__( 'Log Entry', 'houzezpropertyfeed' ),
        );
    }

    public function column_default( $item, $column_name )
    {
        switch( $column_name ) 
        {
            case 'col_log_date':
            {
                $return = get_date_from_gmt( $item->log_date, "H:i:s jS F Y" );

                return $return;
            }
            case 'col_log_property':
            {
                if ( empty($item->post_id) )
                {
                    return '-';
                }

                $title = get_the_title($item->post_id);
                if ( empty($title) )
                {
                    $title = '(no title)';
                }

                if ( strpos(strtolower($title), strtolower(sanitize_text_field($_POST['log_search']))) !== false )
                {
                    $pattern = '/' . preg_quote(sanitize_text_field($_POST['log_search']), '/') . '/i';

                    $title = preg_replace($pattern, '<span style="background:yellow">$0</span>', $title);
                }
                else if ( is_numeric($_POST['log_search']) && (int)$_POST['log_search'] == $item->post_id )
                {
                    $title = '<span style="background:yellow">' . $title . '</span>';
                }

                return '<a href="' . get_edit_post_link($item->post_id) . '" target="_blank">' . $title . '</a>';
            }
            case 'col_log_crm_id':
            {
                $crm_id = $item->crm_id;

                if ( $_POST['log_search'] == $crm_id )
                {
                    $crm_id = '<span style="background:yellow">' . $crm_id . '</span>';
                }

                return $crm_id;
            }
            case 'col_log_entry':
            {   
                $return = $item->entry;
                if ( strpos($item->entry, '<iframe') )
                {
                    $return = htmlentities($item->entry);
                }

                return $return;
            }
            default:
                return print_r( $item, true ) ;
        }
    }

    public function prepare_items() 
    {
        global $wpdb;

        $columns = $this->get_columns(); 
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $per_page = 100000;
        $current_page = $this->get_pagenum();
        $offset = ( $current_page - 1 ) * $per_page;

        $this->_column_headers = array($columns, $hidden, $sortable);

        $query = "
        (
            SELECT
                t1.id,
                t1.log_date,
                t1.entry,
                t1.post_id,
                t1.crm_id
            FROM 
                " . $wpdb->prefix . "houzez_property_feed_logs_instance_log t1
            INNER JOIN (
                SELECT instance_id
                FROM {$wpdb->prefix}houzez_property_feed_logs_instance_log
                WHERE id = " .  (int)$this->log_id  . "
            ) t2 ON t1.instance_id = t2.instance_id
            WHERE
                t1.id <= " .  (int)$this->log_id  . "
            ORDER BY t1.id DESC
            LIMIT 4
        )
        UNION ALL 
        (
            SELECT
                t1.id,
                t1.log_date,
                t1.entry,
                t1.post_id,
                t1.crm_id
            FROM 
                " . $wpdb->prefix . "houzez_property_feed_logs_instance_log t1
            INNER JOIN (
                SELECT instance_id
                FROM {$wpdb->prefix}houzez_property_feed_logs_instance_log
                WHERE id = " .  (int)$this->log_id  . "
            ) t2 ON t1.instance_id = t2.instance_id
            WHERE
                t1.id > " .  (int)$this->log_id  . "
            ORDER BY t1.id ASC
            LIMIT 3
        )
        ORDER BY id ASC";

        $this->items = $wpdb->get_results($query);
        $totalitems = count($this->items);

        $this->set_pagination_args(
            array(
                'total_items' => $totalitems,
                'per_page'    => $per_page,
            )
        );
        
    }

    public function display() {
        $singular = $this->_args['singular'];

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
    }

    protected function get_table_classes() {
        $mode = get_user_setting( 'posts_list_mode', 'list' );

        $mode_class = esc_attr( 'table-view-' . $mode );

        return array( 'widefat', 'striped', $mode_class, esc_attr($this->_args['plural']) );
    }

}
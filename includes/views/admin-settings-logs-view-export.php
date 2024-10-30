<?php 
	$extra_query_string = ( isset($_GET['paged']) ? '&paged=' . (int)$_GET['paged'] : '' );
	$extra_query_string .= ( isset($_GET['orderby']) ? '&orderby=' . sanitize_text_field($_GET['orderby']) : '' );
	$extra_query_string .= ( isset($_GET['order']) ? '&order=' . sanitize_text_field($_GET['order']) : '' );
?>

<div class="hpf-admin-settings-body wrap">

	<div class="hpf-admin-settings-logs">

		<?php include( dirname(HOUZEZ_PROPERTY_FEED_PLUGIN_FILE) . '/includes/views/admin-settings-notice.php' ); ?>

		<h1><?php echo __( 'Export Logs', 'houzezpropertyfeed' ); ?></h1>

		<div class="log-buttons log-buttons-top">
			<a href="<?php echo admin_url('admin.php?page=' . ( isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'houzez-property-feed-export' ) . '&tab=logs' . ( isset($_GET['export_id']) ? '&export_id=' . (int)$_GET['export_id'] : '' ) . $extra_query_string ); ?>" class="button">Back To Logs</a>
		
			<?php
				if ( $previous_instance !== false )
				{
					echo ' <a href="' . admin_url( 'admin.php?page=' . ( isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'houzez-property-feed-export' ) . '&tab=logs&action=view&log_id=' . (int)$previous_instance . ( isset($_GET['export_id']) ? '&export_id=' . (int)$_GET['export_id'] : '' ) . $extra_query_string ) . '" class="button">Previous Log</a> ';
				}
				if ( $next_instance !== false )
				{
					echo ' <a href="' . admin_url( 'admin.php?page=' . ( isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'houzez-property-feed-export' ) . '&tab=logs&action=view&log_id=' . (int)$next_instance . ( isset($_GET['export_id']) ? '&export_id=' . (int)$_GET['export_id'] : '' ) . $extra_query_string ) . '" class="button">Next Log</a> ';
				}
			?>
		</div>

		<?php 
			echo '<div class="logs-table">';
				echo $logs_view_table->display(); 
			echo '</div>';
		?>

		<div class="log-buttons log-buttons-bottom">
			<a href="<?php echo admin_url('admin.php?page=' . ( isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'houzez-property-feed-export' ) . '&tab=logs' . ( isset($_GET['export_id']) ? '&export_id=' . (int)$_GET['export_id'] : '' ) . $extra_query_string ); ?>" class="button">Back To Logs</a>
		
			<?php
				if ( $previous_instance !== false )
				{
					echo ' <a href="' . admin_url( 'admin.php?page=' . ( isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'houzez-property-feed-export' ) . '&tab=logs&action=view&log_id=' . (int)$previous_instance . ( isset($_GET['export_id']) ? '&export_id=' . (int)$_GET['export_id'] : '' ) . $extra_query_string ) . '" class="button">Previous Log</a> ';
				}
				if ( $next_instance !== false )
				{
					echo ' <a href="' . admin_url( 'admin.php?page=' . ( isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'houzez-property-feed-export' ) . '&tab=logs&action=view&log_id=' . (int)$next_instance . ( isset($_GET['export_id']) ? '&export_id=' . (int)$_GET['export_id'] : '' ) . $extra_query_string ) . '" class="button">Next Log</a> ';
				}
			?>
		</div>

	</div>

</div>
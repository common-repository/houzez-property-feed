<div class="hpf-admin-settings-body wrap">

	<div class="hpf-admin-settings-automatic-imports">

		<?php include( dirname(HOUZEZ_PROPERTY_FEED_PLUGIN_FILE) . '/includes/views/admin-settings-notice.php' ); ?>

		<h1><?php echo __( 'Automatic Imports', 'houzezpropertyfeed' ); ?></h1>

		<?php
			$all_imports_count = 0;
			$all_imports_active = 0;
			$all_imports_inactive = 0;
			$all_imports_running = 0;
			$format_counts = array();
	        foreach ( $imports as $key => $import )
	        {
	            if ( isset($imports[$key]['deleted']) && $imports[$key]['deleted'] === true )
	            {
	                unset( $imports[$key] );
	            }
	        }

	        foreach ( $imports as $key => $import )
	        {
	        	++$all_imports_count;

	        	if ( isset($import['format']) && !isset($format_counts[$import['format']]) ) { $format_counts[$import['format']] = 0; }
	        	++$format_counts[$import['format']];

	        	if ( isset($import['running']) && $import['running'] === true )
	        	{
	        		++$all_imports_active;

		        	$row = $wpdb->get_row( "
		                SELECT 
		                    start_date, end_date
		                FROM 
		                    " .$wpdb->prefix . "houzez_property_feed_logs_instance
		                WHERE 
		                    import_id = '" . $key . "'
		                ORDER BY start_date DESC LIMIT 1
		            ", ARRAY_A);
		            if ( null !== $row )
		            {
		                if ($row['start_date'] <= $row['end_date'])
		                {

		                }
		                elseif ($row['end_date'] == '0000-00-00 00:00:00')
		                {
		                    ++$all_imports_running;
		                }
		            }
	        	}
	        	else
	        	{
	        		++$all_imports_inactive;
	        	}
	        }
		?>

		<?php
			if ( !empty($imports) )
			{
		?>
		<ul class="subsubsub" style="margin-bottom:10px;">
			<li class="all"><a href="<?php echo esc_url(admin_url('admin.php?page=houzez-property-feed-import')); ?>"<?php if ( !isset($_GET['hpf_filter']) ) { echo ' class="current" aria-current="page"'; } ?>>All <span class="count">(<?php echo number_format($all_imports_count, 0); ?>)</span></a> |</li>
			<li class="active"><a href="<?php echo esc_url(admin_url('admin.php?page=houzez-property-feed-import&hpf_filter=active')); ?>"<?php if ( isset($_GET['hpf_filter']) && $_GET['hpf_filter'] == 'active' ) { echo ' class="current" aria-current="page"'; } ?>>Active <span class="count">(<?php echo number_format($all_imports_active, 0); ?>)</span></a> |</li>
			<li class="inactive"><a href="<?php echo esc_url(admin_url('admin.php?page=houzez-property-feed-import&hpf_filter=inactive')); ?>"<?php if ( isset($_GET['hpf_filter']) && $_GET['hpf_filter'] == 'inactive' ) { echo ' class="current" aria-current="page"'; } ?>>Inactive <span class="count">(<?php echo number_format($all_imports_inactive, 0); ?>)</span></a> |</li>
			<?php
				if ( !empty($format_counts) && count($format_counts) > 1 )
				{
					ksort($format_counts);
					foreach ( $format_counts as $format => $count )
					{
						$format_name = $format;
						$format_details = get_houzez_property_feed_import_format( $format );
						if ( $format_details !== FALSE )
						{
							$format_name = $format_details['name'];
						}
			?>
						<li class="format"><a href="<?php echo esc_url(admin_url('admin.php?page=houzez-property-feed-import&hpf_filter=format&hpf_filter_format=' . $format)); ?>"<?php if ( isset($_GET['hpf_filter']) && $_GET['hpf_filter'] == 'format' && isset($_GET['hpf_filter_format']) && $_GET['hpf_filter_format'] == $format ) { echo ' class="current" aria-current="page"'; } ?>><?php echo $format_name; ?> <span class="count">(<?php echo number_format($count, 0); ?>)</span></a> |</li>
			<?php
					}
				}
			?>
			<li class="running"><a href="<?php echo esc_url(admin_url('admin.php?page=houzez-property-feed-import&hpf_filter=running')); ?>"<?php if ( isset($_GET['hpf_filter']) && $_GET['hpf_filter'] == 'running' ) { echo ' class="current" aria-current="page"'; } ?>>Running Now <span class="count">(<?php echo number_format($all_imports_running, 0); ?>)</span></a></li>
		</ul>
		<?php
				echo '<div class="automatic-imports-table">' . __('Loading', 'houzezpropertyfeed') . '...</div>';

				if ( $run_now_button )
				{
					$orderby = (!empty($_REQUEST['orderby'])) ? sanitize_text_field($_REQUEST['orderby']) : '';
			        $order = (!empty($_REQUEST['order']) && in_array(strtolower($_REQUEST['order']), array('asc', 'desc')) ) ? sanitize_text_field($_REQUEST['order']) : '';

			        $hpf_filter = (!empty($_REQUEST['hpf_filter'])) ? sanitize_text_field($_REQUEST['hpf_filter']) : '';
			        $hpf_filter_format = (!empty($_REQUEST['hpf_filter_format'])) ? sanitize_text_field($_REQUEST['hpf_filter_format']) : '';
					
					$nonce = wp_create_nonce('houzez_property_feed_import');

					echo '<a href="' . admin_url('admin.php?page=houzez-property-feed-import&custom_property_import_cron=houzezpropertyfeedcronhook&orderby=' . $orderby . '&order=' . $order . '&hpf_filter=' . $hpf_filter . '&hpf_filter_format=' . $hpf_filter_format . '&_wpnonce=' . $nonce) . '" class="button button-manually-execute" onclick="hpf_click_run_now(this);" rel="nofollow noopener noreferrer">Manually Execute Import</a>';
				}
			}
			else
			{
		?>

		<div class="no-imports-exports">

			<h2><?php echo __( 'Your automatic imports will appear here', 'houzezpropertyfeed' ); ?></h2>

			<p>You don't have any imports running at the moment. Why not go ahead and try creating one now?</p>

			<p><a href="<?php echo admin_url('admin.php?page=houzez-property-feed-import&action=addimport'); ?>" class="button button-primary button-hero"><span class="dashicons dashicons-plus-alt2"></span> <?php echo __( 'Create New Import', 'houzezpropertyfeed' ); ?></a></p>

			<p><strong>Need help?</strong> Our <a href="https://houzezpropertyfeed.com/documentation/" target="_blank">in-depth documentation</a> will guide you through the process.</p>

		</div>

		<?php
			}
		?>

	</div>

</div>
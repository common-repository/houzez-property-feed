<div class="hpf-admin-settings-body wrap">

	<div class="hpf-admin-settings-logs">

		<?php include( dirname(HOUZEZ_PROPERTY_FEED_PLUGIN_FILE) . '/includes/views/admin-settings-notice.php' ); ?>

		<form method="post" action="<?php echo esc_url(admin_url('admin.php?page=houzez-property-feed-import&tab=logs&action=search')); ?>">
			<p class="search-box">
				<label class="screen-reader-text" for="logs-search-input">Search Logs:</label>
				<input type="search" id="logs-search-input" name="log_search" value="<?php if ( isset($_POST['log_search']) ) { echo esc_attr($_POST['log_search']); } ?>">
				<input type="submit" id="search-submit" class="button" value="Search Logs">
			</p>
		</form>

		<h1><?php echo __( 'Import Logs Search', 'houzezpropertyfeed' ); ?></h1>

		<?php 
			if ( !empty($log_tables) )
			{
				foreach ( $log_tables as $log_table )
				{
					echo '<div class="logs-table">';
						$log_table->display(); 
					echo '</div>';
				}
			}
			else
			{
				echo '<p>No logs found containing the term \'' . esc_html($_POST['log']) . '\'</p>';
			}
		?>

	</div>

</div>
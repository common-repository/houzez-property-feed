<h3><?php echo __( 'Import Frequency', 'houzezpropertyfeed' ); ?></h3>

<p><?php echo __( 'Choose how often imports should run by selecting the frequency below', 'houzezpropertyfeed' ); ?>:</p>

<table class="form-table">
	<tbody>
		<tr>
			<th><label for="frequency"><?php echo __( 'Frequency', 'houzezpropertyfeed' ); ?></label></th>
			<td style="padding-top:20px;">
				<?php
					foreach ( $frequencies as $key => $frequency )
					{
						$checked = false;
						if ( isset($import_settings['frequency']) && $import_settings['frequency'] == $key )
						{
							$checked = true;
						}
						elseif ( apply_filters( 'houzez_property_feed_pro_active', false ) !== true && $key == 'daily' )
						{
							$checked = true;
						}
						elseif( !isset($import_settings['frequency']) && $key == 'daily' )
						{
							$checked = true;
						}

						echo '<div style="padding:3px 0"><label><input type="radio" name="frequency" value="' . esc_attr($key) . '"' . ( $checked === true ? 'checked' : '' ) . ' ' . ( ( isset($frequency['pro']) && $frequency['pro'] === true && apply_filters( 'houzez_property_feed_pro_active', false ) !== true ) ? 'disabled' : '' ) . '> ' . esc_html($frequency['name']) . '</label> ';
						if ( $key == 'exact_hours' )
						{
							echo ': <input ' . ( apply_filters( 'houzez_property_feed_pro_active', false ) !== true ? ' disabled' : '' ) . ' type="text" name="exact_hours" value="' . ( ( isset($import_settings['exact_hours']) && is_array($import_settings['exact_hours']) && !empty($import_settings['exact_hours']) ) ? implode(", ", $import_settings['exact_hours']) : '' ) . '" placeholder="Hours only (e.g. 8, 12, 16)">';
						}
						if ( isset($frequency['pro']) && $frequency['pro'] === true )
						{
							include( dirname(HOUZEZ_PROPERTY_FEED_PLUGIN_FILE) . '/includes/views/pro-label.php' );
						}
						echo '</div>';
					}
				?>
			</td>
		</tr>
	</tbody>
</table>
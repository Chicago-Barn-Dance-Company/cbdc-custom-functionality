<?php
/**
 * Plugin Name: CBDC Custom Functionality
 * Plugin URI: http://chicagobarndance.org
 * Description: Provides functions specific to the operation of the Chicago Barn Dance Company. Specifically, it customizes the quick edit menu for events so that they can be easily managed and displayed. It also duplicates events by adding 4 weeks to the new event for ease of adding monthly dances. 
 * Author: Jonathan Whitall
 * Author URI: http://chicagobarndance.org
 * Version: 0.2.2
 */
add_filter ( 'admin_init', 'cbdc_init' );
function cbdc_init() {
	// add band and caller columns
	add_filter ( 'manage_ai1ec_event_posts_columns', 'cbdc_add_post_columns', 20 ); // priority 20 means it runs after the ai1ec plugin
	                                                                                // make band and caller columns sortable
	add_filter ( 'manage_edit-ai1ec_event_sortable_columns', 'cbdc_sortable_columns' );
	add_action ( 'pre_get_posts', 'cbdc_meta_orderby' );
	// fill band and caller columns with data
	add_action ( 'manage_ai1ec_event_posts_custom_column', 'cbdc_render_post_columns', 10, 2 );
	// present inputs for event date, band, and caller
	add_action ( 'quick_edit_custom_box', 'cbdc_add_quick_edit', 10, 2 );
	// update event date, band, and caller
	add_action ( 'save_post', 'cbdc_save_quick_edit_data', 10, 3 );
	// enqueue edit script to admin screens
	add_action ( 'admin_print_scripts-edit.php', 'cbdc_enqueue_edit_scripts' );
	
	// for cloned posts, set the publish date to current time
	add_action ( 'dp_duplicate_page', 'cbdc_duplicate_reset_publish_date_and_remove_content', 10, 2 );
	add_action ( 'dp_duplicate_page', 'cbdc_duplicate_add_four_weeks', 10, 2 ); // this was priority 20, but there's a strange bug in WP that makes that not work with nested action hooks
}


/**
 * Add custom columns (ai1ec date, caller, band) to the All Events listing
 */
function cbdc_add_post_columns($columns) {
	$new_columns ['cb'] = '<input type="checkbox" />';
	$new_columns ['title'] = 'Event name';
	$new_columns ['ai1ec_event_date'] = 'Event date/time';
	$new_columns ['band'] = 'Band';
	$new_columns ['caller'] = 'Caller';
	$new_columns ['location'] = 'Location';
	$new_columns ['author'] = 'Author';
	return $new_columns;
}

/**
 * Identify sortable columns
 */
function cbdc_sortable_columns($columns) {
	$columns ['band'] = 'band';
	$columns ['caller'] = 'caller';
	$columns ['location'] = 'location';
	return $columns;
}

/**
 * Implement sortable columns
 */
function cbdc_meta_orderby($query) {
	if (! is_admin ())
		return;
	$orderby = $query->get ( 'orderby' );
	if ('band' == $orderby) {
		$query->set ( 'meta_key', 'Band' );
		$query->set ( 'orderby', 'meta_value' );
	} elseif ('caller' == $orderby) {
		$query->set ( 'meta_key', 'Caller' );
		$query->set ( 'orderby', 'meta_value' );
	} elseif ('location' == $orderby) {
		$query->set ( 'meta_key', 'location_alias' );
		$query->set ( 'orderby', 'meta_value' );
	}
}

/**
 * Fill columns with data
 */
function cbdc_render_post_columns($column_name, $id) {
	switch ($column_name) {
		case 'band' :
			// show band
			$band = get_post_meta ( $id, 'Band', TRUE );
			if ($band)
				echo '<div id="band-' . $id . '">' . $band . '</div>';
			;
			break;
		case 'caller' :
			// show caller
			$caller = get_post_meta ( $id, 'Caller', TRUE );
			if ($caller)
				echo '<div id="caller-' . $id . '">' . $caller . '</div>';
			;
			break;
		case 'location' :
			// show caller
			$location = get_post_meta ( $id, 'location_alias', TRUE );
			if ($location)
				echo '<div id="location-' . $id . '">' . $location . '</div>';
			;
			break;
	}
}

/**
 * Add custom fields to Quick Edit
 */
function cbdc_quick_edit_fieldset_start() {
	?>
<fieldset class="inline-edit-col-left cbdc-quick-edit-fields"
	style="clear: both;">
	<?php
	wp_nonce_field ( plugin_basename ( __FILE__ ), 'cbdc_event_quick_edit_nonce' );
	?>
	<div class="inline-edit-col">
		<h4>Quick Edit</h4>
		<?php
}
function cbdc_add_quick_edit($column_name, $post_type) {
	static $cbdc_fields_processed = 0;
	
	if ($post_type != 'ai1ec_event')
		return;
	
	if ($cbdc_fields_processed == 0) {
		cbdc_quick_edit_fieldset_start ();
	}
	
	switch ($column_name) {
		case 'ai1ec_event_date' :
			?>
		<div class="inline-edit-group">
			<label class="alignleft"> <span class="title">Date</span> <input
				type="text" id="cbdc_event_date" name="ai1ec_event_date" value="" />
			</label>
		</div>
		<?php
			$cbdc_fields_processed ++;
			break;
		case 'band' :
			?>
		<div class="inline-edit-group">
			<label class="alignleft"> <span class="title">Band</span> <input
				type="text" id="cbdc_band" name="band" value="" />
			</label>
		</div>
		<?php
			$cbdc_fields_processed ++;
			break;
		case 'caller' :
			?>
		<div class="inline-edit-group">
			<label class="alignleft"> <span class="title">Caller</span> <input
				type="text" id="cbdc_caller" name="caller" value="" />
			</label>
		</div>
		<?php
			$cbdc_fields_processed ++;
			break;
		case 'location' :
			?>
		<div class="inline-edit-group">
			<label class="alignleft"> <span class="title">Location</span> <input
				type="text" id="cbdc_location" name="location" value=""
				readonly="readonly" />
			</label>
		</div>
		<?php
			$cbdc_fields_processed ++;
			break;
	}
	
	if ($cbdc_fields_processed == 4) {
		?>
	</div>
</fieldset>
<?php
	}
}

/**
 * Save data from the Quick Edit to the database.
 */
function cbdc_save_quick_edit_data($post_id, $post, $update) {

	// verify if this is an auto save routine. If it is, our form has not been submitted, so exit
	if (defined ( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE)
		return $post_id;


	// LABEL:magicquotes
	// remove WordPress `magical` slashes - we work around it ourselves
	$_POST = stripslashes_deep( $_POST );

	$event_posttype = 'ai1ec_event';
	if ($event_posttype !== $_POST ['post_type'])
		return null;
	if (! current_user_can ( 'edit_ai1ec_event', $post_id ))
		return $post;
	
	$_POST += array (
		"cbdc_event_quick_edit_nonce" => '' 
	);
	if (! wp_verify_nonce ( $_POST ["cbdc_event_quick_edit_nonce"], plugin_basename ( __FILE__ ) ))
		return null;
	
	if (isset ( $_POST ['band'] )) {
		update_post_meta ( $post_id, 'Band', $_POST ["band"] );
	}
	if (isset ( $_POST ['caller'] )) {
		update_post_meta ( $post_id, 'Caller', $_POST ["caller"] );
	}
	
	if (isset ( $_POST ['ai1ec_event_date'] )) {
		$ai1ec_event_date_entered = $_POST ['ai1ec_event_date'];



		// Lacking a better way to get the original start and end date and time
		// from the event object in WP/ai1ec-approved ways, let's stick our fingers
		// into the database
		global $wpdb;
		$query = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}ai1ec_events WHERE post_id = %s", $post_id);
		$event_row = $wpdb->get_row($query);

		/*
		 * Get entered date, preserve the old time, and modify both the start and end of the ai1ec event, leaving the same time in place. Example: an event originally scheduled for February 2nd from 7-9:30 pm is cloned using the Events menu. When the date is changed from 2/2/2018 to 3/2/2018, the new start time should be 3/2/2018 at 7 pm and the end time 3/2/2018 at 9:30 pm.
		 */
		$tz = new DateTimeZone($event_row->timezone_name);
		$entered_datetime = new DateTimeImmutable($ai1ec_event_date_entered, $tz);
		$old_start_datetime = (new DateTimeImmutable("", $tz))->setTimestamp($event_row->start);

		$hour = intval($old_start_datetime->format("H"));
		$minute = intval($old_start_datetime->format("i"));
		$new_start_datetime = $entered_datetime->setTime($hour, $minute); // add the time offset
		$new_start_timestamp = $new_start_datetime->getTimestamp(); // ai1ec stores GMT
		
		$old_end_datetime = (new DateTimeImmutable("", $tz))->setTimestamp($event_row->end);
		$old_duration = $old_start_datetime->diff($old_end_datetime);
		$new_end_datetime = $new_start_datetime->add($old_duration);
		$new_end_timestamp = $new_end_datetime->getTimestamp(); // ai1ec stores GMT
		
		$wpdb->update(
			"{$wpdb->prefix}ai1ec_events",
			array("start" => $new_start_timestamp, "end" => $new_end_timestamp),
			array("post_id" => $post_id),
			"%d",
			"%d",
		);
		                       
		// LABEL:magicquotes
		// restore `magic` WordPress quotes to maintain compatibility
		$_POST = add_magic_quotes( $_POST );
	}
}
function cbdc_enqueue_edit_scripts() {
	wp_enqueue_script ( 'cbdc-admin-edit', plugins_url ( '', __FILE__ ) . '/js/quick_edit.js', array (
			'jquery',
			'inline-edit-post' 
	), '2013.3.5', true );
}
function cbdc_duplicate_reset_publish_date_and_remove_content($new_id, $post) {
	if ($post->post_type == 'ai1ec_event') {
		$new_post = array ();
		$new_post ['ID'] = $new_id;
		$new_post ['post_date'] = current_time ( 'mysql' );
		$new_post ['post_date_gmt'] = get_gmt_from_date ( $new_post ['post_date'] );
		
		$copy_contents = get_post_meta($new_id, 'copy_contents', true);
     	if ($copy_contents != 'true') {
			// don't copy content over, in case the previous post has said something specific about the previous event
			$new_post ['post_content'] = '';
        } 
		
		// Update the post into the database
		wp_update_post ( $new_post );
	}
}
function cbdc_duplicate_add_four_weeks($new_id, $post) {
	if ($post->post_type == 'ai1ec_event') {
		$event = new Ai1ec_Event ( $new_id ); // should already exist
		$event->start = add_four_weeks ( $event->start );
		$event->end = add_four_weeks ( $event->end );
		$event->save ( TRUE ); // TRUE means update, don't create new
	}
}
function add_four_weeks($date) {
	// global $ai1ec_events_helper;
	$old_date = new DateTime ( '@' . $date ); // new DateTime( $ai1ec_events_helper->gmt_to_local($date) );
	$new_date = date_add ( $old_date, new DateInterval ( 'P4W' ) ); // add four weeks to keep the event on the same day of the week
	return $new_date->format ( 'U' ); // $ai1ec_events_helper->local_to_gmt( $new_date );
}

// add_action( 'admin_notices', 'dev_check_current_screen' );
// function dev_check_current_screen() {
// 	if( !is_admin() ) return;

// 	global $current_screen;

// 	print_r($current_screen);
// }

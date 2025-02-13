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
 * _parse_post_to_event is taken directly from     
 * all-in-one-event-calendar/app/model/event/creating.php
 * please update this function when the ai1ec plugin is updated
 */
function parse_post_to_event($post_id) {
	error_log("parsepost");
	global $ai1ec_registry;
	$all_day          = isset( $_POST['ai1ec_all_day_event'] )    ? 1                                                                 : 0;
	$instant_event    = isset( $_POST['ai1ec_instant_event'] )    ? 1                                                                 : 0;
	$timezone_name    = isset( $_POST['ai1ec_timezone_name'] )    ? sanitize_text_field( $_POST['ai1ec_timezone_name'] )              : 'sys.default';
	$start_time       = isset( $_POST['ai1ec_start_time'] )       ? sanitize_text_field( $_POST['ai1ec_start_time'] )                 : '';
	$end_time         = isset( $_POST['ai1ec_end_time'] )         ? sanitize_text_field( $_POST['ai1ec_end_time'] )                   : '';
	$venue            = isset( $_POST['ai1ec_venue'] )            ? sanitize_text_field( $_POST['ai1ec_venue'] )                      : '';
	$address          = isset( $_POST['ai1ec_address'] )          ? sanitize_text_field( $_POST['ai1ec_address'] )                    : '';
	$city             = isset( $_POST['ai1ec_city'] )             ? sanitize_text_field( $_POST['ai1ec_city'] )                       : '';
	$province         = isset( $_POST['ai1ec_province'] )         ? sanitize_text_field( $_POST['ai1ec_province'] )                   : '';
	$postal_code      = isset( $_POST['ai1ec_postal_code'] )      ? sanitize_text_field( $_POST['ai1ec_postal_code'] )                : '';
	$country          = isset( $_POST['ai1ec_country'] )          ? sanitize_text_field( $_POST['ai1ec_country'] )                    : '';
	$google_map       = isset( $_POST['ai1ec_google_map'] )       ? 1                                                                 : 0;
	$cost             = isset( $_POST['ai1ec_cost'] )             ? sanitize_text_field( $_POST['ai1ec_cost'] )                       : '';
	$is_free          = isset( $_POST['ai1ec_is_free'] )          ? (bool)$_POST['ai1ec_is_free']                                     : false;
	$ticket_url       = isset( $_POST['ai1ec_ticket_url'] )       ? sanitize_text_field( $_POST['ai1ec_ticket_url'] )                 : '';
	$contact_name     = isset( $_POST['ai1ec_contact_name'] )     ? sanitize_text_field( $_POST['ai1ec_contact_name'] )               : '';
	$contact_phone    = isset( $_POST['ai1ec_contact_phone'] )    ? sanitize_text_field( $_POST['ai1ec_contact_phone'] )              : '';
	$contact_email    = isset( $_POST['ai1ec_contact_email'] )    ? sanitize_text_field( $_POST['ai1ec_contact_email'] )              : '';
	$contact_url      = isset( $_POST['ai1ec_contact_url'] )      ? sanitize_text_field( $_POST['ai1ec_contact_url'] )                : '';
	$show_coordinates = isset( $_POST['ai1ec_input_coordinates'] )? 1                                                                 : 0;
	$longitude        = isset( $_POST['ai1ec_longitude'] )        ? sanitize_text_field( $_POST['ai1ec_longitude'] )                  : '';
	$latitude         = isset( $_POST['ai1ec_latitude'] )         ? sanitize_text_field( $_POST['ai1ec_latitude'] )                   : '';
	$cost_type        = isset( $_POST['ai1ec_cost_type'] )        ? sanitize_text_field( $_POST['ai1ec_cost_type'] )                  : '';
	$rrule            = null;
	$exrule           = null;
	$exdate           = null;
	$rdate            = null;


	// if rrule is set, convert it from local to UTC time
	if (
		isset( $_POST['ai1ec_repeat'] ) &&
		! empty( $_POST['ai1ec_repeat'] )
	) {
		$rrule = $_POST['ai1ec_rrule'];
	}

	// add manual dates
	if (
		isset( $_POST['ai1ec_exdate'] ) &&
		! empty( $_POST['ai1ec_exdate'] )
	) {
		$exdate = $_POST['ai1ec_exdate'];
	}
	if (
		isset( $_POST['ai1ec_rdate'] ) &&
		! empty( $_POST['ai1ec_rdate'] )
	) {
		$rdate = $_POST['ai1ec_rdate'];
	}

	// if exrule is set, convert it from local to UTC time
	if (
		isset( $_POST['ai1ec_exclude'] ) &&
		! empty( $_POST['ai1ec_exclude'] ) &&
		( null !== $rrule || null !== $rdate ) // no point for exclusion, if repetition is not set
	) {
		$exrule = $ai1ec_registry->get( 'recurrence.rule' )->merge_exrule(
			$_POST['ai1ec_exrule'],
			$rrule
		);
	}

	error_log($start_time);
	$is_new = false;
	try {
		$event =  $ai1ec_registry->get(
			'model.event',
			$post_id ? $post_id : null
		);
		error_log("done trying");
	} catch ( Ai1ec_Event_Not_Found_Exception $excpt ) {
		error_log("catch");
		// Post exists, but event data hasn't been saved yet. Create new event
		// object.
		$is_new = true;
		$event  =  $ai1ec_registry->get( 'model.event' );
	}
	$formatted_timezone = $ai1ec_registry->get( 'date.timezone' )
			->get_name( $timezone_name );
	error_log($formatted_timezone);
	if ( empty( $timezone_name ) || ! $formatted_timezone ) {
		$timezone_name = 'sys.default';
	}

	unset( $formatted_timezone );
	$start_time_entry = $ai1ec_registry
		->get( 'date.time', $start_time, $timezone_name );
	error_log($start_time_entry);
	$end_time_entry   = $ai1ec_registry
		->get( 'date.time', $end_time,   $timezone_name );

	$timezone_name = $start_time_entry->get_timezone();
	if ( null === $timezone_name ) {
		$timezone_name = $start_time_entry->get_default_format_timezone();
	}

	$event->set( 'post_id',          $post_id );
	$event->set('start-time', $start_time);
	$event->set( 'start',            $start_time_entry );
	if ( $instant_event ) {
		$event->set_no_end_time();
	} else {
		$event->set( 'end',           $end_time_entry );
		$event->set( 'instant_event', false );
	}
	$event->set( 'timezone_name',    $timezone_name );
	$event->set( 'allday',           $all_day );
	$event->set( 'venue',            $venue );
	$event->set( 'address',          $address );
	$event->set( 'city',             $city );
	$event->set( 'province',         $province );
	$event->set( 'postal_code',      $postal_code );
	$event->set( 'country',          $country );
	$event->set( 'show_map',         $google_map );
	$event->set( 'cost',             $cost );
	$event->set( 'is_free',          $is_free );
	$event->set( 'ticket_url',       $ticket_url );
	$event->set( 'contact_name',     $contact_name );
	$event->set( 'contact_phone',    $contact_phone );
	$event->set( 'contact_email',    $contact_email );
	$event->set( 'contact_url',      $contact_url );
	$event->set( 'recurrence_rules', $rrule );
	$event->set( 'exception_rules',  $exrule );
	$event->set( 'exception_dates',  $exdate );
	$event->set( 'recurrence_dates', $rdate );
	$event->set( 'show_coordinates', $show_coordinates );
	$event->set( 'longitude',        trim( $longitude ) );
	$event->set( 'latitude',         trim( $latitude ) );
	$event->set( 'ical_uid',         $event->get_uid() );

	return $event;
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
		// this date is at midnight
		$entered_datetime = new DateTime ( $ai1ec_event_date_entered );
		// datetime as measured in unix time
		$entered_date = $entered_datetime->format ( 'U' );

		$event = parse_post_to_event($post_id);


		// Lacking a better way to get the original start and end date and time
		// from the event object in WP/ai1ec-approved ways, let's stick our fingers
		// into the database
		global $wpdb;
		$query = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}ai1ec_events WHERE post_id = %s", $post_id);
		error_log($query);
		$event_row = $wpdb->get_row($query);
		error_log(json_encode($event_row));
		$old_start_date = $event_row->start;

		$tz_offset = -6 * 3600;

		// THIS IS A BUG: the offset is to the current time not the entered time
		$old_start_time_offset = ($old_start_date + $tz_offset) % 86400 - $tz_offset; // get the time offset from midnight of the old date
		error_log($old_start_time_offset);
		$new_start_date = $entered_date + $old_start_time_offset; // add the time offset
		$event->set('start', $new_start_date); 

		$old_end_date = $event_row->end;
		$old_end_time_offset = ($old_end_date + $tz_offset) % 86400 - $tz_offset; // get the time offset from midnight of the old date
		$new_end_date = $entered_date + $old_end_time_offset; // add the time offset
		$event->set('end', $new_end_date); // ai1ec stores GMT
		error_log("750");

		do_action( 'ai1ec_save_post', $event );
		$event->save ( TRUE ); // TRUE means update, don't create new

		// reset the cache
		//$ai1ec_events_helper->delete_event_cache ( $post_id );
		//$ai1ec_events_helper->cache_event ( $event );

		// LABEL:magicquotes
		// restore `magic` WordPress quotes to maintain compatibility
		$_POST = add_magic_quotes( $_POST );

		return $event;
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

    (function($) {

    	   // we create a copy of the WP inline edit post function
    	   var $wp_inline_edit = inlineEditPost.edit;
    	   // and then we overwrite the function with our own code
    	   inlineEditPost.edit = function( id ) {

    	      // "call" the original WP edit function
    	      // we don't want to leave WordPress hanging
    	      $wp_inline_edit.apply( this, arguments );

    	      // Now we take care of our business!
    	      // Get the post ID
    	      var $post_id = 0;
    	      if ( typeof( id ) == 'object' )
    	         $post_id = parseInt( this.getId( id ) );

    	      if ( $post_id > 0 ) {
    	         // define the edit row
    	         var $edit_row = $( '#edit-' + $post_id );
    	         
       	         
       	         // get the data from the DOM
    	         var $event_date_time = $( '#post-' + $post_id + ' > td.ai1ec_event_date').text();
    	         var $event_date = $event_date_time.substring(0,$event_date_time.indexOf('@')-1);     	         // lop off the time
        		 var $band = $( '#band-' + $post_id ).text();
        		 var $caller = $( '#caller-' + $post_id ).text();
        		 var $location = $( '#location-' + $post_id ).text();
	    		 // populate the release date
        		 $edit_row.find( 'input[name="ai1ec_event_date"]' ).val( $event_date );
        		 $edit_row.find( 'input[name="band"]' ).val( $band );
        		 $edit_row.find( 'input[name="caller"]' ).val( $caller );
        		 $edit_row.find( 'input[name="location"]' ).val( $location );
    	      }

    	   };

    	})(jQuery);

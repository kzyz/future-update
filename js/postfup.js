( function( $ ) {
    $( "#futureupdate_date_div" ).siblings( "a.edit-futureupdate_date" ).click( function () {
        if ( $( "#futureupdate_date_div" ).is( ":hidden" ) ) {
             $( "#futureupdate_date_div" ).slideDown( "normal" );
             $( this ).hide();
        }
        return false;
    });
    $( ".cancel-futureupdate_date", "#futureupdate_date_div" ).click( function () {
        $( "#futureupdate_date_div" ).slideUp( "normal" );
        $( "#futureupdate_date_div" ).siblings( "a.edit-futureupdate_date" ).show();
        return false;
    });
    $( ".save-futureupdate_date", "#futureupdate_date_div" ).click( function () {
        $( "#futureupdate_date_div" ).slideUp( "normal" );
        $( "#futureupdate_date_div" ).siblings( "a.edit-futureupdate_date" ).show();
        if ( $( "#fup_check" ).attr('checked') ) {
	        $( "#futureupdate_timestamp b" ).html( $( "option[value=" + $( "#fup_month" ).val() + "]", "#fup_month" ).text() + " " + $( "#fup_day" ).val() + ", " + $( "#fup_year" ).val() + " @ " + $( "#fup_hour" ).val() + ":" + $( "#fup_minute" ).val() );
        }
        return false;
    });
} )( jQuery );
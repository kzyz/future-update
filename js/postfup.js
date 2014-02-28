(function ( $ ) {
	$( "#futureupdate_date_div" ).siblings( "a.edit-futureupdate_date" ).click( function () {
		if ( $( "#futureupdate_date_div" ).is( ":hidden" ) ) {
			$( "#futureupdate_date_div" ).slideDown( "normal" );
			$( this ).hide();
		}
		return false;
	} );
	$( ".cancel-futureupdate_date", "#futureupdate_date_div" ).click( function () {
		$( "#futureupdate_date_div" ).slideUp( "normal" );
		$( "#futureupdate_date_div" ).siblings( "a.edit-futureupdate_date" ).show();
		return false;
	} );
	$( ".save-futureupdate_date", "#futureupdate_date_div" ).click( function () {
		$( "#futureupdate_date_div" ).slideUp( "normal" );
		$( "#futureupdate_date_div" ).siblings( "a.edit-futureupdate_date" ).show();
		$( "#futureupdate_timestamp b" ).html(
				postL10n.dateFormat.replace( '%1$s', $( 'option[value="' + $( "#fup_month" ).val() + '"]', '#fup_month' ).text() )
						.replace( '%2$s', $( "#fup_day" ).val() )
						.replace( '%3$s', $( "#fup_year" ).val() )
						.replace( '%4$s', $( "#fup_hour" ).val() )
						.replace( '%5$s', $( "#fup_minute" ).val() )
		);
		return false;
	} );
})( jQuery );
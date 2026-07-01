jQuery( document ).ready(
	function ( $ ) {
		var SellerLedgerAdmin = ( function ( $ ) {
			var setup = function () {
				$( 'button.js-sellerledger-transaction-sync' )
					.off( 'click', instigateSync )
					.on( 'click', instigateSync );
			};

			var instigateSync = function ( e ) {
				e.preventDefault();

				var button = $( e.currentTarget );
				var status = $( '.js-sellerledger-sync-status' );

				button.prop( 'disabled', true );
				status.text( 'Starting import…' );

				$.ajax(
					{
						method: 'POST',
						dataType: 'json',
						url: sellerledger_admin_data.ajax_url,
						data: {
							action: 'sellerledger_run_transaction_sync',
							security: sellerledger_admin_data.transaction_sync_nonce,
							start_date: $( 'input#start_date' ).val(),
							end_date: $( 'input#end_date' ).val()
						}
					}
				).done(
					function ( response ) {
						if ( response && response.success ) {
							status.text( response.data.message );
						} else {
							status.text( ( response && response.data && response.data.error ) || 'Something went wrong.' );
						}
					}
				).fail(
					function () {
						status.text( 'Something went wrong. Please try again.' );
					}
				).always(
					function () {
						button.prop( 'disabled', false );
					}
				);
			};

			return {
				setup: setup
			};
		}( jQuery ) );

		SellerLedgerAdmin.setup();
	}
);

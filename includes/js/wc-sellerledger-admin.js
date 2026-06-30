jQuery( document ).ready(
	function ( $ ) {
		var SellerLedgerAdmin = ( function ( $ ) {
			var setup = function () {
				$( 'button.js-wc-sellerledger-transaction-sync' )
					.off( 'click', instigateSync )
					.on( 'click', instigateSync );
			};

			var instigateSync = function ( e ) {
				e.preventDefault();

				var button = $( e.currentTarget );
				var status = $( '.js-wc-sellerledger-sync-status' );

				button.prop( 'disabled', true );
				status.text( 'Starting import…' );

				$.ajax(
					{
						method: 'POST',
						dataType: 'json',
						url: woocommerce_sellerledger_admin.ajax_url,
						data: {
							action: 'wc_sellerledger_run_transaction_sync',
							security: woocommerce_sellerledger_admin.transaction_sync_nonce,
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

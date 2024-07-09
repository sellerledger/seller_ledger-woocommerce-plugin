jQuery( document ).ready(
	function () {
		var SellerLedgerAdmin = ( function ($, m) {
			var setup = function () {
				$( 'button.js-wc-sellerledger-transaction-sync' ).on( 'click', instigate_sync );
			};

			var instigate_sync = function (e) {
				e.preventDefault();

				$.ajax(
					{
						method: 'POST',
						dataType: 'json',
						url: woocommerce_sellerledger_admin.ajax_url,
						data: {
							action: 'wc_sellerledger_run_transaction_sync',
							security: woocommerce_sellerledger_admin.transaction_sync_nonce,
							start_date: $( "input#start_date" ).val(),
							end_date: $( "input#end_date" ).val()
						}
					}
				).done(
					function ( data ) {
						if ( data.error != null ) {
							alert( data.error );
						} else {
							alert( data.count );
						}
					}
				);
			};

			return {
				setup: setup
			};
		}( jQuery, SellerLedgerAdmin || {} ) );

		SellerLedgerAdmin.setup();
	}
);

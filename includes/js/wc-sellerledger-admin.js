jQuery( document ).ready( function() {
  var SellerLedgerAdmin = ( function ($, m) {
    var setup = function() {
      $('#sellerledger-activate').on('click', activate_plugin );
      $('#sellerledger-sync').on('click', instigate_sync );
    };

    var activate_plugin = function(e) {
      e.preventDefault();

      $.ajax({
        method: 'POST',
        dataType: 'json',
        url: woocommerce_sellerledger_admin.ajax_url,
        data: {
          action: 'wc_sellerledger_activate_plugin',
          security: woocommerce_sellerledger_admin.activate_plugin_nonce
        }
      }).done( function( data ) {
        console.log(data);
      });
    };

    var instigate_sync = function(e) {
      e.preventDefault();

      alert(woocommerce_sellerledger_admin.ajax_url);

      $.ajax({
        method: 'POST',
        dataType: 'json',
        url: woocommerce_sellerledger_admin.ajax_url,
        data: {
          action: 'wc_sellerledger_run_transaction_sync',
          security: woocommerce_sellerledger_admin.transaction_sync_nonce
        }
      }).done( function( data ) {
        console.log(data);
      });
    };

    return {
      setup: setup
    };
  }( jQuery, SellerLedgerAdmin || {} ) );

  SellerLedgerAdmin.setup();
});

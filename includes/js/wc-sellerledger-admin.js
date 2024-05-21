jQuery( document ).ready( function() {
  var SellerLedgerAdmin = ( function ($, m) {
    $('.sellerledger-sync').on('click', instigate_sync );
  });

  var instigate_sync = function(e) {
    e.preventDefault();

    alert("Triggering backfill!");

    // $.ajax({
    //   method: 'POST',
    //   dataType: 'json',
    //   url: woocommerce_sellerledger_admin.ajax_url,
    //   data: {
    //     action: 'wc_sellerledger_run_transaction_sync',
    //     security: woocommerce_sellerledger_admin.transaction_sync_nonce
    //   }
    // }).done( function( data ) {
    // });
  };
});

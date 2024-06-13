<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_SellerLedger_Transaction_Backfill {
  public function print() {
    $current_date = current_time( 'Y-m-d' );
    ?>
      <p>Select start and end dates; transactions that fall in these ranges will be queued for sync with Seller Ledger.</p>
      <label for="start_date">Sync Start Date</label>
      <input type="text" class="sellerledger-datepicker" style="" name="start_date" id="start_date" value="<?php echo esc_html( $current_date ); ?>" placeholder="YYYY-MM-DD" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])">
      <br />
      <label for="end_date">Sync End Date</label>
      <input type="text" class="sellerledger-datepicker" style="" name="end_date" id="end_date" value="<?php echo esc_html( $current_date ); ?>" placeholder="YYYY-MM-DD" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])">
    <p>
      <button class="button js-wc-sellerledger-transaction-sync">Run Sync</button>
    </p>
    <?php
  }
}

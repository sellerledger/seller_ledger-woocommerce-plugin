<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class SellerLedger_Settings_Backfill {
	private $business;

	public function __construct( $business ) {
		$this->business = $business;
	}

	public function render() {
		$current_date = current_time( 'Y-m-d' );
		$start_date   = new DateTime( $this->business->sync_start_date() );
		$start_date   = $start_date->format( 'Y-m-d' );
		?>
		<p><?php esc_html_e( 'Import completed and refunded orders from a date range into Seller Ledger. Your initial history was imported automatically when you connected; use this to re-import a specific range.', 'seller-ledger' ); ?></p>
		<p>
			<?php
			/* translators: %s: earliest importable date */
			printf( esc_html__( 'Orders back to %s can be imported.', 'seller-ledger' ), esc_html( $start_date ) );
			?>
		</p>
		<p>
			<label for="start_date"><?php esc_html_e( 'Start date', 'seller-ledger' ); ?></label>
			<input type="text" class="sellerledger-datepicker" name="start_date" id="start_date" value="<?php echo esc_attr( $current_date ); ?>" placeholder="YYYY-MM-DD" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])">
		</p>
		<p>
			<label for="end_date"><?php esc_html_e( 'End date', 'seller-ledger' ); ?></label>
			<input type="text" class="sellerledger-datepicker" name="end_date" id="end_date" value="<?php echo esc_attr( $current_date ); ?>" placeholder="YYYY-MM-DD" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])">
		</p>
		<p>
			<button class="button button-primary js-sellerledger-transaction-sync"><?php esc_html_e( 'Import transactions', 'seller-ledger' ); ?></button>
			<span class="js-sellerledger-sync-status sellerledger-sync-status"></span>
		</p>
		<?php
	}
}

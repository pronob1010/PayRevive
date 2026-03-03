<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle Recovery Analytics
 */
class PayRevive_Analytics {

	/**
	 * Instance
	 */
	private static $instance;

	/**
	 * Get Instance
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Hook into order completed/processing to track recovery success
		add_action( 'woocommerce_order_status_completed', array( $this, 'track_recovery_success' ), 10, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'track_recovery_success' ), 10, 1 );
	}

	/**
	 * Record Failed Payment
	 */
	public function record_failed_payment( $order ) {
		$stats = get_option( 'payrevive_stats', array(
			'failed_count' => 0,
			'recovered_count' => 0,
			'recovered_revenue' => 0,
			'history' => array(),
		) );

		$stats['failed_count']++;
		
		// History for trends
		$stats['history'][] = array(
			'order_id' => $order->get_id(),
			'type'     => 'failed',
			'amount'   => $order->get_total(),
			'time'     => time(),
		);

		update_option( 'payrevive_stats', $stats );
	}

	/**
	 * Track Recovery Success
	 */
	public function track_recovery_success( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Only track if it was previously scheduled for recovery by PayRevive
		if ( 'yes' === $order->get_meta( '_payrevive_retry_scheduled' ) ) {
			$stats = get_option( 'payrevive_stats', array(
				'failed_count' => 0,
				'recovered_count' => 0,
				'recovered_revenue' => 0,
				'history' => array(),
			) );

			$stats['recovered_count']++;
			$stats['recovered_revenue'] += $order->get_total();

			// History for trends
			$stats['history'][] = array(
				'order_id' => $order_id,
				'type'     => 'recovered',
				'amount'   => $order->get_total(),
				'time'     => time(),
			);

			// Mark as recovered and stop further retries
			$order->update_meta_data( '_payrevive_retry_scheduled', 'recovered' );
			$order->save();

			update_option( 'payrevive_stats', $stats );
			
			$order->add_order_note( __( 'PayRevive: Payment successfully recovered!', 'payrevive' ) );
		}
	}

	/**
	 * Get Stats
	 */
	public function get_stats() {
		return get_option( 'payrevive_stats', array(
			'failed_count' => 0,
			'recovered_count' => 0,
			'recovered_revenue' => 0,
		) );
	}
}

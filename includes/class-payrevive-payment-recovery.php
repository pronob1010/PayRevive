<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle Payment Recovery Logic
 */
class PayRevive_Payment_Recovery {

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
		// Hook into failed payments
		add_action( 'woocommerce_payment_failed', array( $this, 'handle_failed_payment' ), 10, 1 );
		add_action( 'woocommerce_order_status_failed', array( $this, 'handle_failed_payment' ), 10, 1 );

		// Scheduled retry action
		add_action( 'payrevive_retry_payment', array( $this, 'retry_payment_attempt' ), 10, 1 );
	}

	/**
	 * Handle Failed Payment
	 */
	public function handle_failed_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Prevent multiple schedules for the same order if already scheduled
		if ( $order->get_meta( '_payrevive_retry_scheduled' ) ) {
			return;
		}

		// Log failed payment
		$order->add_order_note( __( 'PayRevive: Failed payment detected. Initiating recovery process.', 'payrevive' ) );
		
		// Initial setup for retry counter
		$order->update_meta_data( '_payrevive_retry_count', 0 );
		$order->update_meta_data( '_payrevive_retry_scheduled', 'yes' );
		$order->save();

		// Update analytics
		PayRevive_Analytics::get_instance()->record_failed_payment( $order );

		// Schedule first retry
		$this->schedule_retry( $order_id );

		// Trigger notifications
		PayRevive_Notifications::get_instance()->trigger_failed_payment_notifications( $order_id );
	}

	/**
	 * Schedule Retry using Action Scheduler (built into WooCommerce)
	 */
	public function schedule_retry( $order_id ) {
		$settings = get_option( 'payrevive_settings' );
		$interval = isset( $settings['retry_interval'] ) ? intval( $settings['retry_interval'] ) : 24;
		
		$order = wc_get_order( $order_id );
		$retry_count = 0;
		if ( $order ) {
			$retry_count = intval( $order->get_meta( '_payrevive_retry_count' ) );
		}

		// Smart Retry Logic
		if ( isset( $settings['smart_retry_enabled'] ) && 'yes' === $settings['smart_retry_enabled'] && $retry_count > 0 ) {
			$multiplier = isset( $settings['smart_retry_interval'] ) ? intval( $settings['smart_retry_interval'] ) : 2;
			// Increase interval exponentially: 24h, 48h, 96h etc based on retry count
			$interval = $interval * pow( $multiplier, $retry_count );
		}

		// Convert hours to seconds
		$delay = $interval * HOUR_IN_SECONDS;

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + $delay, 'payrevive_retry_payment', array( 'order_id' => $order_id ) );
		}
	}

	/**
	 * Retry Payment Attempt
	 */
	public function retry_payment_attempt( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->is_paid() ) {
			return;
		}

		// Check card/token validity if it's a subscription order
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order );
			foreach ( $subscriptions as $subscription ) {
				if ( $subscription->get_payment_method() === 'stripe' ) {
					// In a real scenario, we could check Stripe token status here.
					// For v1/v2, we'll log that it's a subscription recovery.
					$order->add_order_note( sprintf( __( 'PayRevive: Subscription recovery for Order #%s.', 'payrevive' ), $order->get_order_number() ) );
				}
			}
		}

		$settings = get_option( 'payrevive_settings' );
		$max_retries = isset( $settings['retry_attempts'] ) ? intval( $settings['retry_attempts'] ) : 3;
		$retry_count = intval( $order->get_meta( '_payrevive_retry_count' ) );

		if ( $retry_count < $max_retries ) {
			$retry_count++;
			$order->update_meta_data( '_payrevive_retry_count', $retry_count );
			$order->add_order_note( sprintf( __( 'PayRevive: Automated retry attempt %d of %d.', 'payrevive' ), $retry_count, $max_retries ) );
			$order->save();

			// For most gateways, we can't just "charge" again without a token.
			// This retry logic might need gateway-specific implementations.
			// For v1, we focus on notifying the user to try again, or if it's a subscription, WooCommerce Subscriptions might handle it.
			// Here we just log the attempt and re-trigger notifications.
			
			PayRevive_Notifications::get_instance()->trigger_failed_payment_notifications( $order_id );

			// Schedule next retry if still not paid
			if ( $retry_count < $max_retries ) {
				$this->schedule_retry( $order_id );
			} else {
				$order->add_order_note( __( 'PayRevive: Maximum retry attempts reached.', 'payrevive' ) );
				$order->update_meta_data( '_payrevive_retry_scheduled', 'no' );
				$order->save();
			}
		}
	}
}

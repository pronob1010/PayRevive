<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle Notifications (Email + WhatsApp)
 */
class PayRevive_Notifications {

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
		// Actions
	}

	/**
	 * Trigger notifications after failed payment
	 */
	public function trigger_failed_payment_notifications( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$settings = get_option( 'payrevive_settings' );

		// Email notification
		if ( isset( $settings['email_enabled'] ) && 'yes' === $settings['email_enabled'] ) {
			$this->send_recovery_email( $order );
		}

		// WhatsApp notification
		if ( isset( $settings['whatsapp_enabled'] ) && 'yes' === $settings['whatsapp_enabled'] ) {
			$this->send_whatsapp_reminder( $order );
		}
	}

	/**
	 * Send Recovery Email
	 */
	public function send_recovery_email( $order ) {
		$settings = get_option( 'payrevive_settings' );
		$to = $order->get_billing_email();
		
		$subject_template = isset( $settings['email_subject'] ) ? $settings['email_subject'] : __( 'Action Required: Payment failed for Order #{order_number}', 'payrevive' );
		$body_template = isset( $settings['email_body'] ) ? $settings['email_body'] : __( "Hi {customer_name},\n\nUnfortunately, your payment for order #{order_number} failed. Don't worry, your items are still reserved.\n\nYou can complete your payment by clicking the link below:\n{checkout_url}\n\nThank you!", 'payrevive' );

		$replace = array(
			'{order_number}'  => $order->get_order_number(),
			'{customer_name}' => $order->get_billing_first_name(),
			'{checkout_url}'  => $order->get_checkout_payment_url(),
		);

		$subject = str_replace( array_keys( $replace ), array_values( $replace ), $subject_template );
		$message = str_replace( array_keys( $replace ), array_values( $replace ), $body_template );

		wp_mail( $to, $subject, $message );
		$order->add_order_note( __( 'PayRevive: Recovery email sent to customer.', 'payrevive' ) );
	}

	/**
	 * Send WhatsApp Reminder
	 */
	public function send_whatsapp_reminder( $order ) {
		$phone = $order->get_billing_phone();
		if ( empty( $phone ) ) {
			return;
		}

		$settings = get_option( 'payrevive_settings' );
		$api_key = isset( $settings['whatsapp_api_key'] ) ? $settings['whatsapp_api_key'] : '';

		if ( empty( $api_key ) ) {
			$order->add_order_note( __( 'PayRevive: WhatsApp skipped - API Key missing.', 'payrevive' ) );
			return;
		}

		$template = isset( $settings['whatsapp_message'] ) ? $settings['whatsapp_message'] : __( "Your payment failed for order #{order_number}. Click here to complete: {checkout_url}", 'payrevive' );
		
		$replace = array(
			'{order_number}' => $order->get_order_number(),
			'{checkout_url}'  => $order->get_checkout_payment_url(),
		);

		$message = str_replace( array_keys( $replace ), array_values( $replace ), $template );

		// Placeholder for WhatsApp API call

		// Example using wp_remote_post to a hypothetical endpoint
		/*
		wp_remote_post( 'https://graph.facebook.com/v17.0/YOUR_PHONE_NUMBER_ID/messages', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body' => wp_json_encode( array(
				'messaging_product' => 'whatsapp',
				'to' => $phone,
				'type' => 'template',
				'template' => array(
					'name' => 'payment_failed_recovery',
					'language' => array( 'code' => 'en_US' ),
				),
			) ),
		) );
		*/

		$order->add_order_note( __( 'PayRevive: WhatsApp reminder triggered (Simulation).', 'payrevive' ) );
	}
}

<?php
/**
 * Plugin Name: PayRevive
 * Plugin URI:  https://github.com/pronob1010/PayRevive
 * Description: WooCommerce Smart Payment Recovery + WhatsApp Reminder.
 * Version:     1.0.0
 * Author:      Pronob Mozumder
 * Text Domain: payrevive
 * Domain Path: /languages
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Tested up to: 6.9
 * WC requires at least: 8.0
 * WC tested up to: 9.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants
define( 'PAYREVIVE_VERSION', '1.0.0' );
define( 'PAYREVIVE_PATH', plugin_dir_path( __FILE__ ) );
define( 'PAYREVIVE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Initialize PayRevive Plugin
 */
class PayRevive {

	/**
	 * Instance of this class
	 * @var PayRevive
	 */
	private static $instance;

	/**
	 * Get instance of this class
	 * @return PayRevive
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
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required files
	 */
	private function includes() {
		require_once PAYREVIVE_PATH . 'includes/class-payrevive-payment-recovery.php';
		require_once PAYREVIVE_PATH . 'includes/class-payrevive-admin.php';
		require_once PAYREVIVE_PATH . 'includes/class-payrevive-notifications.php';
		require_once PAYREVIVE_PATH . 'includes/class-payrevive-analytics.php';
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		add_action( 'plugins_loaded', array( $this, 'load_plugin' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
	}

	/**
	 * Declare HPOS Compatibility
	 */
	public function declare_hpos_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}

	/**
	 * Activation Hook
	 */
	public function activate() {
		// Initialize tables or options if needed
		if ( ! get_option( 'payrevive_settings' ) ) {
			update_option( 'payrevive_settings', array(
				'retry_attempts' => 3,
				'retry_interval' => 24, // hours
				'whatsapp_api_key' => '',
				'email_enabled' => 'yes',
				'whatsapp_enabled' => 'no',
				'smart_retry_enabled' => 'no',
				'smart_retry_interval' => 2, // multiplier
				'email_subject' => __( 'Action Required: Your payment for order #{order_number} failed', 'payrevive' ),
				'email_body' => __( "Hi {customer_name},\n\nWe noticed that your payment for order #{order_number} didn't go through. Don't worry, your items are still reserved for you!\n\nYou can easily complete your purchase by clicking the secure checkout link below:\n\n{checkout_url}\n\nIf you have any questions or need assistance, feel free to reply to this email.\n\nBest regards,\nThe Team", 'payrevive' ),
				'whatsapp_message' => __( "Hi {customer_name}, your payment for order #{order_number} failed. You can complete it here: {checkout_url}", 'payrevive' ),
			) );
		}
	}

	/**
	 * Load plugin after WooCommerce
	 */
	public function load_plugin() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		// Initialize modules
		PayRevive_Payment_Recovery::get_instance();
		PayRevive_Admin::get_instance();
		PayRevive_Notifications::get_instance();
		PayRevive_Analytics::get_instance();
	}

	/**
	 * Notice if WooCommerce is missing
	 */
	public function woocommerce_missing_notice() {
		echo '<div class="error"><p>' . esc_html__( 'PayRevive requires WooCommerce to be installed and active.', 'payrevive' ) . '</p></div>';
	}
}

// Start the plugin
PayRevive::get_instance();

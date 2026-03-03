<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin interface for PayRevive
 */
class PayRevive_Admin {

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
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_export' ) );
	}

	/**
	 * Handle CSV Export
	 */
	public function handle_export() {
		if ( isset( $_GET['page'] ) && 'payrevive' === $_GET['page'] && isset( $_GET['action'] ) && 'export_csv' === $_GET['action'] ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			check_admin_referer( 'payrevive_export_csv' );

			$stats = PayRevive_Analytics::get_instance()->get_stats();
			$history = isset( $stats['history'] ) ? $stats['history'] : array();

			header( 'Content-Type: text/csv' );
			header( 'Content-Disposition: attachment; filename="payrevive-recovery-stats.csv"' );

			$output = fopen( 'php://output', 'w' );
			fputcsv( $output, array( 'Date', 'Type', 'Amount', 'Order ID' ) );

			foreach ( $history as $item ) {
				fputcsv( $output, array(
					date( 'Y-m-d H:i:s', $item['time'] ),
					ucfirst( $item['type'] ),
					$item['amount'],
					$item['order_id'],
				) );
			}

			fclose( $output );
			exit;
		}
	}

	/**
	 * Add Admin Menu
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'PayRevive', 'payrevive' ),
			__( 'PayRevive', 'payrevive' ),
			'manage_options',
			'payrevive',
			array( $this, 'render_dashboard' ),
			'dashicons-money-alt',
			58
		);

		add_submenu_page(
			'payrevive',
			__( 'Dashboard', 'payrevive' ),
			__( 'Dashboard', 'payrevive' ),
			'manage_options',
			'payrevive',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'payrevive',
			__( 'Settings', 'payrevive' ),
			__( 'Settings', 'payrevive' ),
			'manage_options',
			'payrevive-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Register Settings
	 */
	public function register_settings() {
		register_setting( 'payrevive_settings_group', 'payrevive_settings', array(
			'sanitize_callback' => array( $this, 'sanitize_settings' ),
		) );
	}

	/**
	 * Sanitize Settings
	 */
	public function sanitize_settings( $input ) {
		$new_input = array();
		if ( isset( $input['retry_attempts'] ) ) {
			$new_input['retry_attempts'] = absint( $input['retry_attempts'] );
		}
		if ( isset( $input['retry_interval'] ) ) {
			$new_input['retry_interval'] = absint( $input['retry_interval'] );
		}
		if ( isset( $input['whatsapp_api_key'] ) ) {
			$new_input['whatsapp_api_key'] = sanitize_text_field( $input['whatsapp_api_key'] );
		}
		if ( isset( $input['email_enabled'] ) ) {
			$new_input['email_enabled'] = 'yes' === $input['email_enabled'] ? 'yes' : 'no';
		}
		if ( isset( $input['whatsapp_enabled'] ) ) {
			$new_input['whatsapp_enabled'] = 'yes' === $input['whatsapp_enabled'] ? 'yes' : 'no';
		}
		if ( isset( $input['smart_retry_enabled'] ) ) {
			$new_input['smart_retry_enabled'] = 'yes' === $input['smart_retry_enabled'] ? 'yes' : 'no';
		}
		if ( isset( $input['smart_retry_interval'] ) ) {
			$new_input['smart_retry_interval'] = absint( $input['smart_retry_interval'] );
		}
		if ( isset( $input['email_subject'] ) ) {
			$new_input['email_subject'] = sanitize_text_field( $input['email_subject'] );
		}
		if ( isset( $input['email_body'] ) ) {
			$new_input['email_body'] = sanitize_textarea_field( $input['email_body'] );
		}
		if ( isset( $input['whatsapp_message'] ) ) {
			$new_input['whatsapp_message'] = sanitize_textarea_field( $input['whatsapp_message'] );
		}
		return $new_input;
	}

	/**
	 * Enqueue Assets
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'payrevive' ) === false ) {
			return;
		}
		wp_enqueue_style( 'payrevive-admin-style', PAYREVIVE_URL . 'assets/css/admin.css', array(), PAYREVIVE_VERSION );
		wp_enqueue_script( 'payrevive-admin-script', PAYREVIVE_URL . 'assets/js/admin.js', array(), PAYREVIVE_VERSION, true );
		
		// Chart.js for trends
		if ( 'toplevel_page_payrevive' === $hook ) {
			wp_enqueue_script( 'chart-js', PAYREVIVE_URL . 'assets/js/chart.min.js', array(), '4.4.1', true );
		}

		// Tailwind CSS
		wp_enqueue_script( 'tailwind-js', PAYREVIVE_URL . 'assets/js/tailwind.min.js', array(), '3.4.1', false );
	}

	/**
	 * Render Dashboard
	 */
	public function render_dashboard() {
		$stats = PayRevive_Analytics::get_instance()->get_stats();
		$failed_count = isset( $stats['failed_count'] ) ? intval( $stats['failed_count'] ) : 0;
		$recovered_count = isset( $stats['recovered_count'] ) ? intval( $stats['recovered_count'] ) : 0;
		$history = isset( $stats['history'] ) ? $stats['history'] : array();
		
		$recovery_rate = 0;
		if ( $failed_count > 0 ) {
			$recovery_rate = round( ( $recovered_count / $failed_count ) * 100, 2 );
		}

		// Prepare chart data
		$chart_labels = array();
		$chart_failed = array();
		$chart_recovered = array();

		// Group by last 7 days
		for ( $i = 6; $i >= 0; $i-- ) {
			$date = date( 'Y-m-d', strtotime( "-$i days" ) );
			$chart_labels[] = date( 'M d', strtotime( $date ) );
			
			$failed_today = 0;
			$recovered_today = 0;

			foreach ( $history as $item ) {
				if ( date( 'Y-m-d', $item['time'] ) === $date ) {
					if ( $item['type'] === 'failed' ) {
						$failed_today++;
					} else {
						$recovered_today++;
					}
				}
			}
			$chart_failed[] = $failed_today;
			$chart_recovered[] = $recovered_today;
		}
		?>
		<div class="wrap payrevive-admin max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
			<div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 pb-4 border-b border-gray-200">
				<div>
					<h1 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-sky-500 m-0 leading-tight tracking-tight">
						<?php _e( 'PayRevive Dashboard', 'payrevive' ); ?>
					</h1>
					<p class="text-gray-500 mt-1"><?php _e( 'Recovery performance and insights.', 'payrevive' ); ?></p>
				</div>
				<div class="flex space-x-3 mt-4 md:mt-0">
					<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', 'export_csv' ), 'payrevive_export_csv' ) ); ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors focus:outline-none">
						<span class="dashicons dashicons-download mr-2 text-gray-400"></span> <?php _e( 'Export Stats', 'payrevive' ); ?>
					</a>
					<a href="<?php echo admin_url( 'admin.php?page=payrevive-settings' ); ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 transition-colors focus:outline-none">
						<span class="dashicons dashicons-admin-generic mr-2"></span> <?php _e( 'Settings', 'payrevive' ); ?>
					</a>
				</div>
			</div>

			<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
				<div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 transition-transform hover:scale-[1.02]">
					<h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2"><?php _e( 'Total Failed', 'payrevive' ); ?></h3>
					<div class="text-3xl font-bold text-gray-900"><?php echo esc_html( $failed_count ); ?></div>
				</div>
				<div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 transition-transform hover:scale-[1.02]">
					<h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2"><?php _e( 'Recovered', 'payrevive' ); ?></h3>
					<div class="text-3xl font-bold text-green-600"><?php echo esc_html( $recovered_count ); ?></div>
				</div>
				<div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 transition-transform hover:scale-[1.02]">
					<h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2"><?php _e( 'Recovery Rate', 'payrevive' ); ?></h3>
					<div class="text-3xl font-bold text-blue-600"><?php echo esc_html( $recovery_rate ); ?>%</div>
				</div>
				<div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 transition-transform hover:scale-[1.02]">
					<h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2"><?php _e( 'Revenue Saved', 'payrevive' ); ?></h3>
					<div class="text-3xl font-bold text-emerald-600"><?php echo wp_kses_post( wc_price( $stats['recovered_revenue'] ) ); ?></div>
				</div>
			</div>

			<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
				<div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-sm border border-gray-100">
					<h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
						<span class="dashicons dashicons-chart-line mr-2 text-blue-500"></span> <?php _e( 'Recovery Trends', 'payrevive' ); ?>
					</h2>
					<div class="h-[300px] relative">
						<canvas id="payrevive-trends-chart"></canvas>
					</div>
				</div>

				<div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
					<h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
						<span class="dashicons dashicons-list-view mr-2 text-blue-500"></span> <?php _e( 'Recent Activity', 'payrevive' ); ?>
					</h2>
					<ul class="space-y-4 m-0 p-0 list-none">
						<?php
						$recent = array_slice( array_reverse( $history ), 0, 8 );
						if ( empty( $recent ) ) {
							echo '<li class="text-gray-500 text-sm">' . esc_html__( 'No recent activity.', 'payrevive' ) . '</li>';
						}
						foreach ( $recent as $item ) : ?>
							<li class="pb-4 border-b border-gray-50 last:border-0 flex justify-between items-center group">
								<div>
									<strong class="text-sm font-semibold <?php echo $item['type'] === 'recovered' ? 'text-green-600' : 'text-red-600'; ?>">
										<?php echo esc_html( ucfirst( $item['type'] ) ); ?>
									</strong>
									<span class="block text-xs text-gray-400 mt-1">
										<?php printf( esc_html__( 'Order #%s - %s', 'payrevive' ), esc_html( $item['order_id'] ), wp_kses_post( wc_price( $item['amount'] ) ) ); ?>
									</span>
								</div>
								<span class="text-xs text-gray-400">
									<?php echo esc_html( human_time_diff( $item['time'], time() ) ) . ' ' . esc_html__( 'ago', 'payrevive' ); ?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>

			<script>
				document.addEventListener('DOMContentLoaded', function() {
					const ctx = document.getElementById('payrevive-trends-chart').getContext('2d');
					if (typeof Chart !== 'undefined') {
						new Chart(ctx, {
							type: 'line',
							data: {
								labels: <?php echo json_encode( $chart_labels ); ?>,
								datasets: [{
									label: '<?php _e( 'Failed', 'payrevive' ); ?>',
									data: <?php echo json_encode( $chart_failed ); ?>,
									borderColor: '#d63638',
									backgroundColor: 'rgba(214, 54, 56, 0.1)',
									fill: true,
									tension: 0.4
								}, {
									label: '<?php _e( 'Recovered', 'payrevive' ); ?>',
									data: <?php echo json_encode( $chart_recovered ); ?>,
									borderColor: '#008a20',
									backgroundColor: 'rgba(0, 138, 32, 0.1)',
									fill: true,
									tension: 0.4
								}]
							},
							options: {
								responsive: true,
								plugins: {
									legend: { position: 'top' }
								},
								scales: {
									y: { beginAtZero: true, ticks: { stepSize: 1 } }
								}
							}
						});
					}
				});
			</script>
		</div>
		<?php
	}

	/**
	 * Render Settings
	 */
	public function render_settings() {
		$settings = get_option( 'payrevive_settings' );
		
		// Fallbacks for display
		$email_subject = ! empty( $settings['email_subject'] ) ? $settings['email_subject'] : __( 'Action Required: Your payment for order #{order_number} failed', 'payrevive' );
		$email_body = ! empty( $settings['email_body'] ) ? $settings['email_body'] : __( "Hi {customer_name},\n\nWe noticed that your payment for order #{order_number} didn't go through. Don't worry, your items are still reserved for you!\n\nYou can easily complete your purchase by clicking the secure checkout link below:\n\n{checkout_url}\n\nIf you have any questions or need assistance, feel free to reply to this email.\n\nBest regards,\nThe Team", 'payrevive' );
		$whatsapp_message = ! empty( $settings['whatsapp_message'] ) ? $settings['whatsapp_message'] : __( "Hi {customer_name}, your payment for order #{order_number} failed. You can complete it here: {checkout_url}", 'payrevive' );
		?>
		<div class="wrap payrevive-admin max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
			<div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 pb-4 border-b border-gray-200">
				<div>
					<h1 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-sky-500 m-0 leading-tight tracking-tight">
						<?php _e( 'PayRevive Settings', 'payrevive' ); ?>
					</h1>
					<p class="text-gray-500 mt-1"><?php _e( 'Configure your payment recovery rules and notifications.', 'payrevive' ); ?></p>
				</div>
				<a href="<?php echo admin_url( 'admin.php?page=payrevive' ); ?>" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors focus:outline-none">
					<span class="dashicons dashicons-dashboard mr-2 text-gray-400"></span> <?php _e( 'Back to Dashboard', 'payrevive' ); ?>
				</a>
			</div>

			<form method="post" action="options.php" class="payrevive-tabs space-y-8">
				<?php settings_fields( 'payrevive_settings_group' ); ?>
				
				<div class="flex p-1 space-x-1 bg-gray-100 rounded-xl max-w-md">
					<button type="button" class="payrevive-tab-link w-full py-2.5 text-sm font-medium leading-5 rounded-lg focus:outline-none transition-all duration-200 active" data-tab="tab-retry">
						<?php _e( 'Retry Logic', 'payrevive' ); ?>
					</button>
					<button type="button" class="payrevive-tab-link w-full py-2.5 text-sm font-medium leading-5 rounded-lg focus:outline-none transition-all duration-200" data-tab="tab-email">
						<?php _e( 'Email Template', 'payrevive' ); ?>
					</button>
					<button type="button" class="payrevive-tab-link w-full py-2.5 text-sm font-medium leading-5 rounded-lg focus:outline-none transition-all duration-200" data-tab="tab-whatsapp">
						<?php _e( 'WhatsApp', 'payrevive' ); ?>
					</button>
				</div>

				<!-- Tab: Retry Logic -->
				<div id="tab-retry" class="payrevive-tab-content block">
					<div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
						<h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center border-b pb-4">
							<span class="dashicons dashicons-update mr-2 text-blue-500"></span> <?php _e( 'Retry Logic & Intervals', 'payrevive' ); ?>
						</h2>
						<div class="grid grid-cols-1 md:grid-cols-2 gap-8">
							<div class="space-y-4">
								<label class="block text-sm font-bold text-gray-700"><?php _e( 'Retry Attempts', 'payrevive' ); ?></label>
								<input type="number" name="payrevive_settings[retry_attempts]" value="<?php echo esc_attr( $settings['retry_attempts'] ); ?>" min="1" max="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border" />
								<p class="text-xs text-gray-400"><?php _e( 'How many recovery attempts to make for each failed payment.', 'payrevive' ); ?></p>
							</div>
							<div class="space-y-4">
								<label class="block text-sm font-bold text-gray-700"><?php _e( 'Base Interval (Hours)', 'payrevive' ); ?></label>
								<input type="number" name="payrevive_settings[retry_interval]" value="<?php echo esc_attr( $settings['retry_interval'] ); ?>" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border" />
								<p class="text-xs text-gray-400"><?php _e( 'Delay before the first recovery notification.', 'payrevive' ); ?></p>
							</div>
							<div class="md:col-span-2 flex items-center p-4 bg-blue-50 rounded-lg">
								<input type="checkbox" name="payrevive_settings[smart_retry_enabled]" id="smart_retry_enabled" value="yes" <?php checked( $settings['smart_retry_enabled'], 'yes' ); ?> class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" />
								<div class="ml-3">
									<label for="smart_retry_enabled" class="font-medium text-blue-900"><?php _e( 'Enable Smart Backoff', 'payrevive' ); ?></label>
									<p class="text-sm text-blue-700"><?php _e( 'Increase interval exponentially after each failed attempt to avoid overwhelming customers.', 'payrevive' ); ?></p>
								</div>
							</div>
							<div class="space-y-4">
								<label class="block text-sm font-bold text-gray-700"><?php _e( 'Backoff Multiplier', 'payrevive' ); ?></label>
								<input type="number" name="payrevive_settings[smart_retry_interval]" value="<?php echo esc_attr( $settings['smart_retry_interval'] ); ?>" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border" />
								<p class="text-xs text-gray-400"><?php _e( 'Example: Multiplier 2 with 24h base = 24h, 48h, 96h...', 'payrevive' ); ?></p>
							</div>
						</div>
					</div>
				</div>

				<!-- Tab: Email Template -->
				<div id="tab-email" class="payrevive-tab-content hidden">
					<div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
						<h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center border-b pb-4">
							<span class="dashicons dashicons-email mr-2 text-blue-500"></span> <?php _e( 'Email Notification Settings', 'payrevive' ); ?>
						</h2>
						<div class="space-y-6">
							<div class="flex items-center p-4 bg-gray-50 rounded-lg">
								<input type="checkbox" name="payrevive_settings[email_enabled]" id="email_enabled" value="yes" <?php checked( $settings['email_enabled'], 'yes' ); ?> class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" />
								<label for="email_enabled" class="ml-3 font-medium text-gray-700"><?php _e( 'Enable Email Reminders', 'payrevive' ); ?></label>
							</div>
							<div class="grid grid-cols-1 gap-6">
								<div>
									<label class="block text-sm font-bold text-gray-700 mb-1"><?php _e( 'Email Subject', 'payrevive' ); ?></label>
									<input type="text" name="payrevive_settings[email_subject]" value="<?php echo esc_attr( $email_subject ); ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border" />
								</div>
								<div>
									<label class="block text-sm font-bold text-gray-700 mb-1"><?php _e( 'Email Body', 'payrevive' ); ?></label>
									<textarea name="payrevive_settings[email_body]" rows="8" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border"><?php echo esc_textarea( $email_body ); ?></textarea>
									<p class="text-xs text-gray-400 mt-2"><?php _e( 'Available tags: {order_number}, {customer_name}, {checkout_url}', 'payrevive' ); ?></p>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Tab: WhatsApp Template -->
				<div id="tab-whatsapp" class="payrevive-tab-content hidden">
					<div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
						<h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center border-b pb-4">
							<span class="dashicons dashicons-whatsapp mr-2 text-green-500"></span> <?php _e( 'WhatsApp Notification Settings', 'payrevive' ); ?>
						</h2>
						<div class="space-y-6">
							<div class="flex items-center p-4 bg-gray-50 rounded-lg">
								<input type="checkbox" name="payrevive_settings[whatsapp_enabled]" id="whatsapp_enabled" value="yes" <?php checked( $settings['whatsapp_enabled'], 'yes' ); ?> class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded" />
								<label for="whatsapp_enabled" class="ml-3 font-medium text-gray-700"><?php _e( 'Enable WhatsApp Reminders', 'payrevive' ); ?></label>
							</div>
							<div class="grid grid-cols-1 gap-6">
								<div>
									<label class="block text-sm font-bold text-gray-700 mb-1"><?php _e( 'WhatsApp API Key (Cloud API / Twilio)', 'payrevive' ); ?></label>
									<input type="password" name="payrevive_settings[whatsapp_api_key]" value="<?php echo esc_attr( $settings['whatsapp_api_key'] ); ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border" />
								</div>
								<div>
									<label class="block text-sm font-bold text-gray-700 mb-1"><?php _e( 'WhatsApp Message Template', 'payrevive' ); ?></label>
									<textarea name="payrevive_settings[whatsapp_message]" rows="4" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border"><?php echo esc_textarea( $whatsapp_message ); ?></textarea>
									<p class="text-xs text-gray-400 mt-2"><?php _e( 'Available tags: {order_number}, {checkout_url}', 'payrevive' ); ?></p>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="pt-5 border-t border-gray-200">
					<div class="flex justify-end">
						<button type="submit" name="submit" id="submit" class="ml-3 inline-flex justify-center py-2 px-6 border border-transparent shadow-sm text-sm font-bold rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
							<?php _e( 'Save Settings', 'payrevive' ); ?>
						</button>
					</div>
				</div>
			</form>
		</div>

		<style>
			/* Active tab style */
			.payrevive-tab-link.active {
				background-color: white;
				color: #1d4ed8;
				box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
			}
			.payrevive-tab-link:not(.active) {
				color: #4b5563;
			}
			.payrevive-tab-link:not(.active):hover {
				color: #111827;
			}
			/* Remove WordPress default wrap padding */
			#wpbody-content .wrap.payrevive-admin {
				margin: 0;
			}
		</style>
		<?php
	}
}

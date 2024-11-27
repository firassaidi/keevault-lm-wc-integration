<?php
/*
Plugin Name: Keevault License Manager - WooCommerce Integration
Description: Sell Keevault license keys through WooCommerce.
Version: 1.0.1
Author: Firas Saidi
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

defined( 'KEEVAULT_LM' ) || define( 'KEEVAULT_LM', __DIR__ );

class Keevault_LM {

	private array $setting_tabs = [];

	public function __construct() {
		add_action( 'init', [ $this, 'webhooks_handler' ] );

		// Order Hook for contract creation
		add_action( 'woocommerce_thankyou', [ $this, 'assign_license_keys_on_order' ] );

		if ( is_user_logged_in() ) {
			// Add my-account endpoint
			add_action( 'init', [ $this, 'license_keys_my_account_endpoint' ] );
			add_filter( 'query_vars', [ $this, 'license_keys_query_vars' ], 0 );
			add_action( 'woocommerce_account_license-keys_endpoint', [ $this, 'license_keys_endpoint_content' ] );
			add_filter( 'woocommerce_account_menu_items', [ $this, 'license_keys_my_account_menu_items' ] );

			add_action( 'woocommerce_after_order_details', [ $this, 'show_license_key_details_on_the_order_page' ] );
			add_action( 'wp_ajax_get_license_key_details', [ $this, 'get_license_key_details' ] );
		}

		if ( is_admin() && current_user_can( 'manage_options' ) ) {
			// Initialize Keevault menu
			add_action( 'admin_menu', [ $this, 'add_keevault_menu' ] );
			add_action( 'admin_init', [ $this, 'settings_init' ] );

			// Product-specific Keevault settings
			add_action( 'woocommerce_product_options_general_product_data', [ $this, 'add_keevault_product_settings' ] );
			add_action( 'woocommerce_process_product_meta', [ $this, 'save_keevault_product_settings' ] );

			// Add settings for product variations
			add_action( 'woocommerce_product_after_variable_attributes', [ $this, 'add_keevault_variation_settings' ], 10, 3 );
			add_action( 'woocommerce_save_product_variation', [ $this, 'save_keevault_variation_settings' ], 10, 2 );
		}
	}

	public function webhooks_handler(): void {
		if ( isset( $_GET['keevault-webhook'] ) && $_GET['keevault-webhook'] == get_option( 'keevault_lm_webhook_key', 'not-set' ) && $_GET['keevault-webhook'] != 'not-set' ) {
			$data = json_decode( file_get_contents( 'php://input' ), true );

			error_log( 'webhook: ' . print_r( $data, true ) );

			if ( isset( $data['original']['response']['identifier'] ) && $data['original']['response']['code'] == 803 ) {
				$this->process_license_keys( $data['original']['response']['identifier'], $data['original']['response']['license_keys'] );
			}

			die();
		}
	}

	public function process_license_keys( $oder_id, $license_keys ): void {
		$order = wc_get_order( $oder_id );
		foreach ( $license_keys as $license_key ) {
			global $wpdb;

			$license_key['order_id'] = $oder_id;
			$license_key['item_id']  = 0;
			$license_key['user_id']  = $order->get_user_id();
			unset( $license_key['meta_data'] );
			unset( $license_key['id'] );

			$wpdb->insert( $wpdb->prefix . 'keevault_license_keys', $license_key );
		}
	}

	// Content for the contracts endpoint
	public function license_keys_endpoint_content(): void {
		global $wpdb;

		// Define the table name
		$table_name = $wpdb->prefix . 'keevault_license_keys';

		// Get the current page from the URL (default to 1 if not set)
		$current_page = isset( $_GET['license-keys-page'] ) ? max( 1, intval( $_GET['license-keys-page'] ) ) : 1;
		$per_page     = 10; // Number of contracts per page

		// Calculate the offset for the SQL query
		$offset = ( $current_page - 1 ) * $per_page;

		// Query to fetch total number of records for pagination
		$total_count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $table_name WHERE user_id = %d",
			get_current_user_id()
		) );

		// Query to fetch paginated data from the table
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, order_id, license_key, activation_limit, validity, status, created_at 
        FROM $table_name 
        WHERE user_id = %d 
        ORDER BY id DESC
        LIMIT %d OFFSET %d ",
			get_current_user_id(),
			$per_page,
			$offset
		), ARRAY_A );

		// Check if there are any records
		if ( ! empty( $results ) ) {
			echo '<h3>' . esc_html__( 'License Keys Information', 'keevault' ) . '</h3>';

			// Start the WooCommerce-like table structure
			echo '<table class="woocommerce-orders-table woocommerce-orders-table--contracts shop_table shop_table_responsive">';
			echo '<thead>';
			echo '<tr>';
			echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-item-id"><span class="nobr">' . esc_html__( 'Order ID', 'keevault' ) . '</span></th>';
			echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-name"><span class="nobr">' . esc_html__( 'License Key', 'keevault' ) . '</span></th>';
			echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-contract-key"><span class="nobr">' . esc_html__( 'Activation Limit', 'keevault' ) . '</span></th>';
			echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-contract-key"><span class="nobr">' . esc_html__( 'Validity', 'keevault' ) . '</span></th>';
			echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-contract-key"><span class="nobr">' . esc_html__( 'Status', 'keevault' ) . '</span></th>';
			echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-created"><span class="nobr">' . esc_html__( 'Created At', 'keevault' ) . '</span></th>';
			echo '</tr>';
			echo '</thead>';

			echo '<tbody>';
			// Loop through the results and output each row in the table
			foreach ( $results as $row ) {
				$created_at = ( ! empty( $row['created_at'] ) ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row['created_at'] ) ) : '';

				echo '<tr class="woocommerce-orders-table__row">';
				echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-item-id">' . esc_html( $row['order_id'] ) . '</td>';
				echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-name">' . esc_html( $row['license_key'] ) . '</td>';
				echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-contract-key">' . esc_html( $row['activation_limit'] ) . '</td>';
				echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-contract-key">' . ( $row['validity'] > 0 ? ( esc_html( $row['validity'] ) . ' ' . esc_html__( 'day(s)', 'keevault' ) ) : esc_html__( 'Unlimited', 'keevault' ) ) . '</td>';
				echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-contract-key">' . esc_html( ucfirst( $row['status'] ) ) . '</td>';
				echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-created">' . esc_html( $created_at ) . '</td>';
				echo '</tr>';
			}

			echo '</tbody>';
			echo '</table>';

			// Pagination logic using WooCommerce's pagination function
			$total_pages = ceil( $total_count / $per_page );

			// Only show pagination if there are multiple pages
			if ( $total_pages > 1 ) {
				// Define previous and next page URLs
				$prev_link = $current_page > 1 ? esc_url( add_query_arg( 'license-keys-page', $current_page - 1 ) ) : '';
				$next_link = $current_page < $total_pages ? esc_url( add_query_arg( 'license-keys-page', $current_page + 1 ) ) : '';

				// Display pagination buttons
				echo '<div class="woocommerce-pagination">';

				// Previous page button
				if ( $prev_link ) {
					echo '<a class="woocommerce-button button" href="' . $prev_link . '">' . __( 'Previous', 'keevault' ) . '</a>';
				}

				// Page numbers logic
				$start_page = max( 1, $current_page - 1 ); // Start from 1 or 1 page before current page
				$end_page   = min( $total_pages, $current_page + 1 ); // End at current page + 1 or the total number of pages

				// Adjust the range if the current page is near the beginning or end
				if ( $current_page == 1 ) {
					$end_page = min( 3, $total_pages ); // If we're on the first page, show the first 3 pages
				}
				if ( $current_page == $total_pages ) {
					$start_page = max( $total_pages - 2, 1 ); // If we're on the last page, show the last 3 pages
				}

				// Loop through the pages and display the page numbers (3 pages at most)
				for ( $i = $start_page; $i <= $end_page; $i ++ ) {
					// Highlight the current page
					$current = ( $i == $current_page ) ? ' style="background-color: #ddd;"' : '';

					// Output page number link
					echo '<a class="woocommerce-button button"' . $current . ' href="' . esc_url( add_query_arg( 'license-keys-page', $i ) ) . '">' . $i . '</a>';
				}

				// Next page button
				if ( $next_link ) {
					echo '<a class="woocommerce-button button" href="' . $next_link . '">' . __( 'Next', 'keevault' ) . '</a>';
				}

				echo '</div>';
			}
		} else {
			echo '<p>' . esc_html__( 'No license keys found.', 'keevault' ) . '</p>';
		}
	}

	public function license_keys_my_account_menu_items( $items ) {
		$logout = $items['customer-logout'];
		unset( $items['customer-logout'] );

		$items['license-keys']    = esc_html__( 'License Keys', 'keevault' );
		$items['customer-logout'] = $logout;

		return $items;
	}

	// Register new endpoint for My Account
	public function license_keys_my_account_endpoint(): void {
		add_rewrite_endpoint( 'license-keys', EP_ROOT | EP_PAGES );
	}


	// Ensure endpoint is available on My Account page
	public function license_keys_query_vars( $vars ) {
		$vars[] = 'license-keys';

		return $vars;
	}


	public function show_license_key_details_on_the_order_page( $order ): void {
		// Enqueue the JavaScript for AJAX
		wp_enqueue_script( 'show-license-key-details-js', plugin_dir_url( __FILE__ ) . 'assets/js/show-order-license-keys.js', array( 'jquery' ), '1.0', true );

		// Pass order ID and AJAX URL to JavaScript
		wp_localize_script( 'show-license-key-details-js', 'licenseKeyDetailsData', array(
			'ajax_url'              => admin_url( 'admin-ajax.php' ),
			'order_id'              => $order->get_id(),
			'license_key_id'        => esc_html__( 'ID', 'keevault' ),
			'no_license_keys_found' => esc_html__( 'No license keys details available for this order.', 'keevault' ),
			'license_key'           => esc_html__( 'License Keys', 'keevault' ),
			'activation_limit'      => esc_html__( 'Activation Limit', 'keevault' ),
			'validity'              => esc_html__( 'validity', 'keevault' ),
			'days'                  => esc_html__( 'day(s)', 'keevault' ),
			'license_key_status'    => esc_html__( 'Status', 'keevault' ),
			'unlimited'             => esc_html__( 'Unlimited', 'keevault' ),
			'failed_to_load'        => esc_html__( 'Failed to load license key details. Please try again later.', 'keevault' ),
			'wait'                  => esc_html__( 'Please wait on this page while we get your license keys', 'keevault' ),
		) );

		// Add a container where contract details will be displayed
		echo '<h2>' . esc_html__( 'License Key Details', 'keevault' ) . '</h2>';
		echo '<div id="license-key-details-container">' . esc_html__( 'Loading license key details...', 'keevault' ) . '</div>';
	}

	public function get_license_key_details(): void {
		global $wpdb;

		// Verify that the user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( esc_html__( 'You do not have permission to view this content.', 'keevault' ) );
		}

		// Get the current user ID
		$current_user_id = get_current_user_id();

		// Get order ID from AJAX request
		$order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;

		// Return error if no valid order ID
		if ( ! $order_id ) {
			wp_send_json_error( esc_html__( 'Invalid order ID.', 'keevault' ) );
		}

		// Get the order object
		$order = wc_get_order( $order_id );

		// Check if the order exists
		if ( ! $order ) {
			wp_send_json_error( esc_html__( 'Order not found.', 'keevault' ) );
		}

		// Check if the current user is either an admin or the order owner
		if ( ! current_user_can( 'manage_woocommerce' ) && $order->get_user_id() !== $current_user_id ) {
			wp_send_json_error( esc_html__( 'You do not have permission to view this content.', 'keevault' ) );
		}

		// Query the table for license key data related to the order
		$table_name   = $wpdb->prefix . 'keevault_license_keys';
		$license_keys = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE order_id = %d ORDER BY id DESC",
			$order_id
		) );

		// Check if license keys were found
		if ( ! $license_keys ) {
			wp_send_json_error( esc_html__( 'Please wait on this page while we get your license keys.', 'keevault' ) );
		}

		// Return license keys data as JSON
		wp_send_json_success( $license_keys );
	}

	public function assign_license_keys_on_order( $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( $order->get_status() == 'processing' || $order->get_status() == 'completed' ) {
			$api_key = get_option( 'keevault_lm_api_key' );
			$api_url = get_option( 'keevault_lm_api_url' );

			foreach ( $order->get_items() as $item ) {
				$product_id   = $item->get_product_id();
				$variation_id = $item->get_variation_id();

				// Get Keevault details for either simple or variation product
				$keevault_product_id = $variation_id ? get_post_meta( $variation_id, '_keevault_product_id_for_license_keys', true ) : get_post_meta( $product_id, '_keevault_product_id_for_license_keys', true );

				if ( $keevault_product_id ) {
					$assigned_quantity = $this->license_keys_assigned( $order_id, $item->get_id() );

					if ( $assigned_quantity < $item->get_quantity() ) {
						$this->assign_license_keys( $api_key, $api_url, $keevault_product_id, $order, $item->get_id(), ( $item->get_quantity() - $assigned_quantity ) );
					}
				}
			}
		}
	}

	private function assign_license_keys( $api_key, $api_url, $product_id, $order, $item_id, $quantity ): void {
		$already_queued = $this->already_queued( $order->get_id(), $item_id );

		if ( $already_queued == 0 ) {
			$endpoint_option = get_option( 'keevault_lm_api_assign_license_keys_endpoint', 2 );
			$api_endpoint    = 'random-assign-license-keys-queued';
			if ( $endpoint_option == 1 ) {
				$api_endpoint = 'random-assign-license-keys';
			}

			$owner_name = $order->get_billing_company();

			if ( empty( trim( $owner_name ) ) ) {
				$owner_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
			}

			if ( empty( trim( $owner_name ) ) ) {
				$owner_name = esc_html__( 'Not set', 'keevault' );
			}

			$owner_email = $order->get_billing_email();;

			if ( empty( trim( $owner_email ) ) ) {
				$owner_email = esc_html__( 'Not set', 'keevault' );
			}

			$endpoint = '/api/v1/' . $api_endpoint;
			$body     = [
				'api_key'     => $api_key,
				'product_id'  => $product_id,
				'owner_name'  => $owner_name,
				'owner_email' => $owner_email,
				'quantity'    => $quantity,
				'generate'    => 1,
				'identifier'  => $order->get_id(),
				'webhook_url' => site_url() . '/?keevault-webhook=' . get_option( 'keevault_lm_webhook_key', 'not-set' )
			];

			$retry_limit = 0;

			do {
				$response      = wp_remote_post( $api_url . $endpoint, [
					'method'    => 'POST',
					'body'      => $body,
					'sslverify' => false,
				] );
				$response_body = null;

				if ( ! is_wp_error( $response ) ) {
					$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
				}

				$retry_limit ++;
			} while ( is_wp_error( $response ) && $retry_limit < 15 );

			if ( $endpoint_option == 2 ) {
				if ( ! is_wp_error( $response ) && isset( $response_body['response']['code'] ) && $response_body['response']['code'] == 807 ) {
					global $wpdb;

					$wpdb->insert( $wpdb->prefix . 'keevault_orders_queues', [
						'order_id' => $order->get_id(),
						'item_id'  => $item_id
					] );
				}
			} elseif ( $endpoint_option == 1 ) {
				if ( $response_body['response']['code'] == 803 ) {
					global $wpdb;

					$wpdb->insert( $wpdb->prefix . 'keevault_orders_queues', [
						'order_id' => $order->get_id(),
						'item_id'  => $item_id
					] );

					$this->process_license_keys( $order->get_id(), $response_body['response']['license_keys'] );
				}
			}
		}
	}

	private function already_queued( $order_id, $item_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . $wpdb->prefix . "keevault_orders_queues WHERE order_id = $order_id AND item_id = $item_id" );
	}

	private function license_keys_assigned( $order_id, $item_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . $wpdb->prefix . "keevault_license_keys WHERE order_id = $order_id AND item_id = $item_id" );
	}

	public function add_keevault_product_settings(): void {
		echo '<div class="options_group show_if_simple">';

		woocommerce_wp_text_input( [
			'id'          => '_keevault_product_id_for_license_keys',
			'label'       => esc_html__( 'Keevault Product ID For License Keys', 'keevault' ),
			'desc_tip'    => 'true',
			'description' => esc_html__( 'Keevault Product ID for license key assignment.', 'keevault' ),
		] );

		echo '</div>';
	}

	public function save_keevault_product_settings( $post_id ): void {
		$keevault_product_id = isset( $_POST['_keevault_product_id_for_license_keys'] ) ? sanitize_text_field( $_POST['_keevault_product_id_for_license_keys'] ) : '';

		update_post_meta( $post_id, '_keevault_product_id_for_license_keys', $keevault_product_id );
	}

	public function add_keevault_variation_settings( $loop, $variation_data, $variation ) {
		echo '<div class="options_group show_if_variable">';

		woocommerce_wp_text_input( [
			'id'          => '_keevault_product_id_for_license_keys_' . $variation->ID,
			'label'       => esc_html__( 'Keevault Product ID For License Keys', 'keevault' ),
			'description' => esc_html__( 'Keevault Product ID for this variation.', 'keevault' ),
			'value'       => get_post_meta( $variation->ID, '_keevault_product_id_for_license_keys', true )
		] );

		echo '</div>';
	}

	public function save_keevault_variation_settings( $variation_id, $i ): void {
		$keevault_product_id = isset( $_POST[ '_keevault_product_id_for_license_keys_' . $variation_id ] ) ? sanitize_text_field( $_POST[ '_keevault_product_id_for_license_keys_' . $variation_id ] ) : '';

		update_post_meta( $variation_id, '_keevault_product_id_for_license_keys', $keevault_product_id );
	}

	public function add_keevault_menu(): void {
		if ( ! $this->is_top_level_menu_slug_exists( 'keevault' ) ) {
			add_menu_page(
				esc_html__( 'Keevault', 'keevault' ),
				esc_html__( 'Keevault', 'keevault' ),
				'manage_options',
				'keevault',
				'__return_null',
				'dashicons-admin-network',
				30
			);
		}

		add_submenu_page(
			'keevault',
			esc_html__( 'License Keys', 'keevault' ),
			esc_html__( 'License Keys', 'keevault' ),
			'manage_options',
			'keevault-license-keys',
			[ $this, 'keevault_page' ],
			40
		);

		add_submenu_page(
			'keevault',
			esc_html__( 'Settings', 'keevault' ),
			esc_html__( 'Settings', 'keevault' ),
			'manage_options',
			'keevault-settings',
			[ $this, 'settings_page_content' ],
			50
		);

		remove_submenu_page( 'keevault', 'keevault' );
	}

	public function keevault_page(): void {
		include KEEVAULT_LM . '/dashboard/pages/license-keys.php';
	}

	public function settings_page_content(): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<a href="?page=keevault-settings&tab=api" class="nav-tab <?php echo ( ( isset( $_GET['tab'] ) && $_GET['tab'] == 'api' ) || ! isset( $_GET['tab'] ) ) ? 'nav-tab-active' : '' ?>"><?php esc_html_e( 'API', 'keevault' ); ?></a>
				<a href="?page=keevault-settings&tab=webhooks" class="nav-tab <?php echo ( ( isset( $_GET['tab'] ) && $_GET['tab'] == 'webhooks' ) ) ? 'nav-tab-active' : '' ?>"><?php esc_html_e( 'Webhooks', 'keevault' ); ?></a>
				<?php if ( $this->setting_tabs ) {
					foreach ( $this->setting_tabs as $setting_tab ) { ?>
						<a href="?page=keevault-settings&tab=<?php echo esc_html( $setting_tab['name'] ) ?>" class="nav-tab <?php echo isset( $_GET['tab'] ) && $_GET['tab'] == $setting_tab['slug'] ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $setting_tab['name'] ) ?></a>
					<?php }
				} ?>
			</h2>
			<form method="post" action="options.php">
				<?php
				$active_tab = $_GET['tab'] ?? 'api';
				if ( $active_tab == 'api' ) {
					settings_fields( 'keevault_lm_api_settings' );
					do_settings_sections( 'keevault_lm_api_settings' );
				} elseif ( $active_tab == 'webhooks' ) {
					settings_fields( 'keevault_lm_webhooks_settings' );
					do_settings_sections( 'keevault_lm_webhooks_settings' );
				}
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function settings_init(): void {
		// API
		register_setting( 'keevault_lm_api_settings', 'keevault_lm_api_key' );
		register_setting( 'keevault_lm_api_settings', 'keevault_lm_api_url' );
		register_setting( 'keevault_lm_api_settings', 'keevault_lm_api_assign_license_keys_endpoint' );

		add_settings_section( 'keevault_lm_api_section', esc_html__( 'Keevault API Configuration', 'keevault' ), null, 'keevault_lm_api_settings' );

		add_settings_field(
			'keevault_lm_api_key',
			esc_html__( 'API Key', 'keevault' ),
			function () {
				$keevault_lm_api_key = get_option( 'keevault_lm_api_key' );
				echo "<input type='text' class='regular-text' name='keevault_lm_api_key' value='{$keevault_lm_api_key}' />";
			},
			'keevault_lm_api_settings',
			'keevault_lm_api_section',
		);

		add_settings_field(
			'keevault_lm_api_url',
			esc_html__( 'API URL', 'keevault' ),
			function () {
				$keevault_lm_api_url = get_option( 'keevault_lm_api_url' );
				echo "<input type='text' class='regular-text' name='keevault_lm_api_url' value='{$keevault_lm_api_url}' />";
			},
			'keevault_lm_api_settings',
			'keevault_lm_api_section'
		);

		add_settings_field(
			'keevault_lm_api_assign_license_keys_endpoint',
			esc_html__( 'Select License Keys Endpoint', 'keevault' ),
			[ $this, 'api_assign_license_keys_endpoint_render_select_field' ],
			'keevault_lm_api_settings',
			'keevault_lm_api_section'
		);

		// Webhooks
		register_setting( 'keevault_lm_webhooks_settings', 'keevault_lm_webhook_key' );

		add_settings_section( 'keevault_lm_webhooks_section', esc_html__( 'Webhooks Configuration', 'keevault' ), null, 'keevault_lm_webhooks_settings' );

		add_settings_field(
			'keevault_lm_webhook_key',
			esc_html__( 'Webhook Key', 'keevault' ),
			function () {
				$keevault_lm_webhook_key = get_option( 'keevault_lm_webhook_key', 'not-set' );
				echo "<input type='text' class='regular-text' name='keevault_lm_webhook_key' value='{$keevault_lm_webhook_key}' />";
			},
			'keevault_lm_webhooks_settings',
			'keevault_lm_webhooks_section'
		);
	}

	public function api_assign_license_keys_endpoint_render_select_field(): void {
		$selected = get_option( 'keevault_lm_api_assign_license_keys_endpoint', 2 );
		$choices  = [
			'1' => esc_html__( 'random-assign-license-keys', 'keevault' ),
			'2' => esc_html__( 'random-assign-license-keys-queued', 'keevault' )
		];

		echo '<select name="keevault_lm_api_assign_license_keys_endpoint">';
		foreach ( $choices as $key => $label ) {
			$is_selected = $key == $selected ? 'selected' : '';
			echo '<option value="' . esc_attr( $key ) . '" ' . $is_selected . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	public function is_top_level_menu_slug_exists( $slug ): bool {
		global $menu;

		// Loop through the top-level menus
		foreach ( $menu as $menu_item ) {
			if ( isset( $menu_item[2] ) && $menu_item[2] === $slug ) {
				return true;
			}
		}

		return false;
	}

	// Register activation hook for creating the database table
	public static function activate() {
		add_rewrite_endpoint( 'license-keys', EP_ROOT | EP_PAGES );
		flush_rewrite_rules(); // Flush rewrite rules

		global $wpdb;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$table_name      = $wpdb->prefix . 'keevault_license_keys';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		            order_id INT UNSIGNED NOT NULL,
		            item_id INT UNSIGNED NOT NULL,
		            user_id INT UNSIGNED NOT NULL,
		            
		            product_id int UNSIGNED NOT NULL,
					license_key varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
					owner_name varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
					owner_email varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
					activation_limit int NOT NULL DEFAULT '0',
					validity int NOT NULL DEFAULT '0',
					assigned_at timestamp NULL DEFAULT NULL,
					status varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
					created_at timestamp NULL DEFAULT NULL,
					updated_at timestamp NULL DEFAULT NULL,
					contract_id tinyint(1) DEFAULT NULL,
		            PRIMARY KEY (id)
        	) $charset_collate;";

		dbDelta( $sql );

		$table_name      = $wpdb->prefix . 'keevault_orders_queues';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		            order_id INT UNSIGNED NOT NULL,
		            item_id INT UNSIGNED NOT NULL,
		            status varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
		            PRIMARY KEY (id)
        	) $charset_collate;";

		dbDelta( $sql );
	}

	public function log( $text ): void {
		$this_directory = dirname( __FILE__ );
		$fp             = fopen( $this_directory . "/logs.txt", "w" );
		fwrite( $fp, $text );
		fclose( $fp );
	}
}

function keevault_integration_init(): void {
	new Keevault_LM();
}

add_action( 'plugins_loaded', 'keevault_integration_init' );

// Register the activation hook to create the database table
register_activation_hook( __FILE__, [ 'Keevault_LM', 'activate' ] );

<?php
/*
Plugin Name: Keevault License Manager - WooCommerce Integration
Description: Sell Keevault license keys through WooCommerce.
Version: 1.0.0
Author: Firas Saidi
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

defined( 'KEEVAULT_LM_BASE' ) || define( 'KEEVAULT_LM_BASE', __DIR__ );

class Keevault_LM {

	public function __construct() {
		// Initialize Keevault menu
		add_action( 'admin_menu', [ $this, 'add_keevault_menu' ] );

	}


	public function add_keevault_menu(): void {
		add_menu_page(
			esc_html__( 'Keevault', 'keevault' ),
			esc_html__( 'Dashboard', 'keevault' ),
			'manage_options',
			'keevault',
			[ $this, 'keevault_page' ],
			'dashicons-admin-network',
			30
		);

		add_submenu_page(
			'keevault',
			esc_html__( 'Settings', 'keevault' ),
			esc_html__( 'Settings', 'keevault' ),
			'manage_options',
			'keevault-settings',
			[ $this, 'keevault_settings_page' ],
			50
		);
	}

	public function keevault_page() {
		echo 1;
	}

	public function keevault_settings_page() {
		echo 2;
	}
}

new Keevault_LM();

do_action( 'keevault_init' );
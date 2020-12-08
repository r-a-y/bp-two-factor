<?php
/**
 * Admin pluggable functions
 *
 * These functions are required for the two-factor plugin to work
 * properly on the frontend. This is primarily for the U2F
 * Security Keys table to display without fatal errors.
 *
 * @package    bp-two-factor
 * @subpackage pluggable
 */

// Sneakily impersonate wp_screen() to prevent fatals.
if ( ! function_exists( 'convert_to_screen' ) ) {
	function convert_to_screen() {
		$screen = new CAC_Dummy_Admin_Profile_Screen();
		return $screen;
	}
}

// get_column_headers() needs to be declared as well.
if ( ! function_exists( 'get_column_headers' ) ) {
	function get_column_headers( $screen ) {
		if ( is_string( $screen ) ) {
			$screen = convert_to_screen( $screen );
		}

		static $column_headers = array();

		if ( ! isset( $column_headers[ $screen->id ] ) ) {
			/** This filter is documented in /wp-admin/includes/screen.php */
			$column_headers[ $screen->id ] = apply_filters( "manage_{$screen->id}_columns", array() );
		}

		return $column_headers[ $screen->id ];
	}
}

/**
 * Dummy class to impersonate wp_screen().
 */
class CAC_Dummy_Admin_Profile_Screen {
	public $id   = 'profile';
	public $base = 'profile';

	public function render_screen_reader_content( $type = '' ) {}
}
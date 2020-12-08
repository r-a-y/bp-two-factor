<?php
/**
 * Plugin Name: BP Two Factor
 * Description: BuddyPress integration for the Two Factor plugin.
 * Version:     0.1-alpha
 * Network:     true
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace CAC\BP2FA;

/**
 * File constant.
 *
 * @var string Absolute path to this file.
 */
CONST FILE = __FILE__;

/**
 * Directory constant.
 *
 * @var string Absolute path to this directory.
 */
CONST DIR = __DIR__;

/**
 * Is the Two Factor plugin activated?
 *
 * @return bool
 */
function is_2fa_activated() {
	return defined( 'TWO_FACTOR_DIR' );
}

/**
 * Modify 2FA providers.
 *
 * @param  array $retval Current 2FA providers.
 * @return array
 */
function modify_providers( $retval ) {
	// Remove dummy provider.
	unset( $retval['Two_Factor_Dummy'] );

	// Remove FIDO U2F if not on SSL, dunno why 2FA core doesn't do this...
	if ( ! is_ssl() ) {
		unset( $retval['Two_Factor_FIDO_U2F'] );
	}

	return $retval;
}
add_filter( 'two_factor_providers', __NAMESPACE__ . '\\modify_providers' );

/**
 * BuddyPress "Settings > General" page loader.
 */
function settings() {
	// Bail if 2FA not activated or not on Settings > General page.
	if ( ! is_2fa_activated() || ! bp_is_settings_component() || ! bp_is_current_action( 'general' ) || bp_action_variables() ) {
		return;
	}

	require __DIR__ . '/hooks/settings.php';
}
add_action( 'bp_actions', __NAMESPACE__ . '\\settings' );

/**
 * Login loader.
 */
function login() {
	if ( is_2fa_activated() ) {
		require __DIR__ . '/hooks/login.php';
	}
}
add_action( 'login_init', __NAMESPACE__ . '\\login', 9 );

/**
 * Rename "Settings > General" tab to "Settings > Security".
 */
function rename_general_nav() {
	if ( is_2fa_activated() && bp_is_user() ) {
		buddypress()->members->nav->edit_nav( array( 'name' => esc_html__( 'Security', 'bp-two-factor' ) ), 'general', 'settings' );
	}
}
add_action( 'bp_actions', __NAMESPACE__ . '\\rename_general_nav' );

/**
 * Rename "Settings > General" toolbar menu to "Settings > Security".
 */
function rename_general_toolbar( $retval ) {
	if ( is_2fa_activated() && ! empty( $retval[1]['id'] ) && 'my-account-settings-general' === $retval[1]['id'] ) {
		$retval[1]['title'] = esc_html__( 'Security', 'bp-two-factor' );
	}
	return $retval;
}
add_filter( 'bp_settings_admin_nav', __NAMESPACE__ . '\\rename_general_toolbar' );

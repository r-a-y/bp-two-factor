<?php
/**
 * BuddyPress "Settings > General" integration
 *
 * This code handles two-factor plugin integration into a user's
 * BuddyPress "Settings > General" page. The "General" tab is renamed
 * to "Security" to better describe what is on the page.
 *
 * @package    bp-two-factor
 * @subpackage hooks
 */

namespace CAC\BP2FA\Settings;

use CAC\BP2FA as Loader;
use Two_Factor_Core;

/**
 * Validation routine.
 */
function validate() {
	$user_id  = bp_displayed_user_id();
	$redirect = bp_displayed_user_url( bp_members_get_path_chunks( array( bp_get_settings_slug() ) ) );

	// Handle 2FA provider custom action saving like resetting TOTP key.
	Two_Factor_Core::trigger_user_settings_action();

	// Handle 2FA options saving.
	if ( isset( $_POST['_nonce_user_two_factor_options'] ) ) {
		$should_redirect = false;

		$message = esc_html__( 'Two-factor authentication options updated', 'bp-two-factor' );

		// If enabled providers changed, redirect.
		$new_providers = isset( $_POST[ Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY ] ) ? $_POST[ Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY ] : [];
		$providers     = get_user_meta( $user_id, Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY, true );

		$new_providers = array_filter( $new_providers );

		if ( empty( $providers ) ) {
			$providers = [];
		}

		if ( $new_providers !== $providers ) {
			$should_redirect = true;
		}

		// If primary provider changed, redirect.
		$new_provider      = isset( $_POST[ Two_Factor_Core::PROVIDER_USER_META_KEY ] ) ? $_POST[ Two_Factor_Core::PROVIDER_USER_META_KEY ] : '';
		$existing_provider = get_user_meta( $user_id, Two_Factor_Core::PROVIDER_USER_META_KEY, true );

		if ( ! $should_redirect && '' !== $new_provider && $existing_provider !== $new_provider ) {
			$should_redirect = true;
		}

		// Recovery Codes.
		if ( in_array( 'Two_Factor_Backup_Codes', $new_providers ) ) {
			$codes = get_user_meta( $user_id, '_two_factor_backup_codes', true );

			// User did not generate recovery codes, but tried to enable it.
			if ( empty( $codes ) ) {
				$message = esc_html__( 'Please generate your recovery codes before enabling it as a two-factor method', 'bp-two-factor' );
				$should_redirect = true;
			}
		}

		// Do the 2FA save routine.
		Two_Factor_Core::user_two_factor_options_update( $user_id );

		// Redirect if necessary to display our custom message.
		if ( $should_redirect ) {
			bp_core_add_message( $message );
			bp_core_redirect( $redirect );
		}
	}
}

// Run validation routine.
validate();

/**
 * Enqueue assets.
 */
function enqueue_assets() {
	// WebAuthn.
	if ( class_exists( '\WildWolf\WordPress\TwoFactorWebAuthn\Admin' ) ) {
		// WebAuthn's admin_enqueue_scripts() requires this global set.
		$GLOBALS['user_id'] = bp_displayed_user_id();

		// Load up WebAuthn's scripts.
		$webauthn = \WildWolf\WordPress\TwoFactorWebAuthn\Admin::instance();
		$webauthn->admin_enqueue_scripts( 'profile.php' );

		// WebAuthn's profile.min.js requires the #user_id value set.
		add_action( 'bp_2fa_after_settings_output', function() {
			printf( '<input type="hidden" name="user_id" id="user_id" value="%d" />', bp_displayed_user_id() );
		} );
	}

	// CSS
	wp_enqueue_style( 'bp-2fa', plugins_url( 'assets/settings.css', Loader\FILE ), [ 'dashicons' ], '20260129' );
	wp_add_inline_style( 'bp-2fa', '
		#security-keys-section .spinner {background-image: url(' . admin_url( '/images/spinner.gif' ) . ')}
	' );


	// JS
	wp_enqueue_script( 'bp-2fa', plugins_url( 'assets/settings.js', Loader\FILE ), [ 'jquery', 'wp-api' ], '20260129', true );
	wp_localize_script( 'bp-2fa', 'bp2fa', [
		'security_key_desc' => sprintf( '<p>%s</p>', esc_html__( "To register your security key, click on the button below and plug your key into your device's USB port when prompted.", 'bp-two-factor' ) ),
		'security_key_webauthn_desc' => sprintf( '<p>%s</p>', esc_html__( "To register your WebAuthn security key, enter a key name. Next, click on the \"Register New Key\" button below and plug your key into your device's USB port when prompted.", 'bp-two-factor' ) ),
		'backup_codes_count' => \Two_Factor_Backup_Codes::codes_remaining_for_user( buddypress()->displayed_user->userdata ),
		'backup_codes_misplaced' => sprintf( '<p>%s</p>', esc_html__( "If you misplaced your recovery codes, you can generate a new set of recovery codes below. Please note that your old codes will no longer work.", 'bp-two-factor' ) ),
		'backup_codes_generate' => sprintf( '<p>%s</p>', esc_html__( "Click on the button below to generate your recovery codes.", 'bp-two-factor' ) ),
		'recovery_codes_desc' => sprintf( '<p>%s</p>', esc_html__( "Recovery codes can be used to access your account if you lose access to your device and cannot receive two-factor authentication codes.", 'bp-two-factor' ) )
	] );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_assets' );

/**
 * Output 2FA options.
 */
function output() {
	require Loader\DIR . '/pluggable.php';

	// Modify user admin settings URL to use BP user settings page.
	$user_settings_page_url = function( $url, $path ) {
		if ( 'user-edit.php' === $path ) {
			return bp_displayed_user_domain() . bp_get_settings_slug() . '/';
		}
		return $url;
	};

	$userdata = get_userdata( bp_displayed_user_id() );

	// Add some filters.
	add_filter( 'self_admin_url', $user_settings_page_url, 10, 3 );

	echo '<div class="bp-2fa">';

	/**
	 * Do something before BP 2FA output.
	 *
	 * @param WP_User $userdata WP user data.
	 */
	do_action( 'bp_2fa_before_settings_output', $userdata );

	// Output 2FA options table.
	Two_Factor_Core::user_two_factor_options( $userdata );

	// Remove filters.
	remove_filter( 'self_admin_url', $user_settings_page_url, 10 );

	/**
	 * Do something after BP 2FA output.
	 *
	 * @param WP_User $userdata WP user data.
	 */
	do_action( 'bp_2fa_after_settings_output', $userdata );

	echo '</div>';
}
add_action( 'bp_core_general_settings_before_submit', __NAMESPACE__ . '\\output' );

/**
 * Remove Recovery Codes as a recommended provider in certain situations.
 *
 * Recovery Codes is removed as a recommended provider if:
 *  - The user has no 2FA provider enabled
 *  - Recovery Codes is already enabled by the user
 *  - Two or more 2FA providers are enabled by the user
 *
 * @param array   $retval Current recommended 2FA providers.
 * @param WP_User $user   Current user.
 * @return array
 */
function remove_recovery_codes_as_recommended_provider( $retval, $user ) {
	$available = Two_Factor_Core::get_available_providers_for_user( $user );

	if ( empty( $available ) || isset( $available['Two_Factor_Backup_Codes'] ) || count( $available ) > 1 ) {
		$find_backup_codes = array_search( 'Two_Factor_Backup_Codes', $retval );
		if ( false !== $find_backup_codes ) {
			unset( $retval[ $find_backup_codes ] );
		}
	}

	return $retval;
}
add_filter( 'two_factor_recommended_providers', __NAMESPACE__ . '\\remove_recovery_codes_as_recommended_provider', 10, 2 );

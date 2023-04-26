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
	$redirect = trailingslashit( bp_displayed_user_domain() . bp_get_settings_slug() );

	// Eek. Ensure we add a notice if a save attempt was made.
	add_action( 'updated_user_meta', function( $meta_id, $object_id, $meta_key ) use ( $redirect ) {
		if ( ! isset( $_POST['_nonce_user_two_factor_options'] ) ) {
			return;
		}

		$should_redirect = false;

		// Enabled providers.
		if ( Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY === $meta_key ) {
			$should_redirect = true;

			/*
			 * Check to see if the primary provider changed.
			 *
			 * If primary provider did change, we redirect then.
			 */
			$new_provider = isset( $_POST[ Two_Factor_Core::PROVIDER_USER_META_KEY ] ) ? $_POST[ Two_Factor_Core::PROVIDER_USER_META_KEY ] : '';
			$primary      = get_user_meta( $object_id, Two_Factor_Core::PROVIDER_USER_META_KEY, true );
			if ( '' !== $new_provider && $primary !== $new_provider ) {
				$should_redirect = false;
			}

		// Primary provider.
		} elseif ( Two_Factor_Core::PROVIDER_USER_META_KEY === $meta_key ) {
			$should_redirect = true;
		}

		if ( $should_redirect ) {
			bp_core_add_message( esc_html__( 'Two-factor authentication options updated', 'bp-two-factor' ) );
			bp_core_redirect( $redirect );
		}
	}, 10, 3 );

	// TOTP.
	if ( class_exists( 'Two_Factor_Totp' ) ) {
		$totp = \Two_Factor_Totp::get_instance();

		// Add a notice and redirect if deleting TOTP secret key.
		add_action( 'two_factor_user_settings_action', function( $user_id, $action ) use ( $totp, $redirect ) {
			if ( $totp::ACTION_SECRET_DELETE === $action ) {
				bp_core_add_message( esc_html__( 'Two-factor authentication options updated', 'bp-two-factor' ) );
				bp_core_redirect( $redirect );
			}
		}, 20, 2 );

		// Set TOTP as enabled (and primary if blank) during secret key save.
		add_action( 'added_user_meta', function( $meta_id, $object_id, $meta_key ) use ( $totp ) {
			if ( $totp::SECRET_META_KEY === $meta_key ) {
				$enabled_providers_for_user = Two_Factor_Core::get_enabled_providers_for_user( $object_id );

				$_POST[ Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY ] = ! empty( $enabled_providers_for_user ) ? $enabled_providers_for_user : [];
				$_POST[ Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY ][] = 'Two_Factor_Totp';

				// Sanity check.
				$_POST[ Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY ] = array_unique( $_POST[ Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY ] );

				// Primary.
				$_POST[ Two_Factor_Core::PROVIDER_USER_META_KEY ] = Two_Factor_Core::is_user_using_two_factor( $object_id ) ? Two_Factor_Core::get_primary_provider_for_user( $object_id ) : 'Two_Factor_Totp';

				// Save primary and enabled providers again.
				Two_Factor_Core::user_two_factor_options_update( $object_id );
			}
		}, 10, 3 );
	}

	// U2F.
	// @todo Notice handling?
	if ( class_exists( 'Two_Factor_FIDO_U2F' ) ) {
		// Actions.
		\Two_Factor_FIDO_U2F_Admin::catch_submission( $user_id );
		\Two_Factor_FIDO_U2F_Admin::catch_delete_security_key();
	}

	// Handle 2FA provider custom action saving like resetting TOTP key.
	Two_Factor_Core::trigger_user_settings_action();

	// Handle 2FA options saving.
	Two_Factor_Core::user_two_factor_options_update( bp_displayed_user_id() );
}

// Run validation routine.
validate();

/**
 * Enqueue assets.
 */
function enqueue_assets() {
	// U2F.
	if ( class_exists( 'Two_Factor_FIDO_U2F' ) ) {
		/*
		 * Override 2FA fido-u2f-admin as it relies on the WP admin form.
		 *
		 * This will be removed once fixed upstream.
		 */
		wp_register_script(
			'fido-u2f-admin',
			plugins_url( 'assets/fido-u2f-admin.js', Loader\FILE ),
			array( 'jquery', 'fido-u2f-api' ),
			'20201026',
			true
		);

		wp_enqueue_style( 'list-tables' );

		\Two_Factor_FIDO_U2F_Admin::enqueue_assets( 'profile.php' );
	}

	// WebAuthn.
	if ( class_exists( '\WildWolf\WordPress\TwoFactorWebAuthn\Admin' ) ) {
		$webauthn = \WildWolf\WordPress\TwoFactorWebAuthn\Admin::instance();
		$webauthn->admin_enqueue_scripts( 'profile.php' );
	}

	// CSS
	wp_enqueue_style( 'bp-2fa', plugins_url( 'assets/settings.css', Loader\FILE ), [ 'dashicons' ], '20220511' );
	wp_add_inline_style( 'bp-2fa', '
		#security-keys-section .spinner {background-image: url(' . admin_url( '/images/spinner.gif' ) . ')}
	' );


	// JS
	wp_enqueue_script( 'bp-2fa', plugins_url( 'assets/settings.js', Loader\FILE ), [ 'jquery', 'wp-api' ], '20220511', true );
	wp_localize_script( 'bp-2fa', 'bp2fa', [
		'security_key_desc' => sprintf( '<p>%s</p>', esc_html__( "To register your security key, click on the button below and plug your key into your device's USB port when prompted.", 'bp-two-factor' ) ),
		'security_key_webauthn_desc' => sprintf( '<p>%s</p>', esc_html__( "To register your WebAuthn security key, enter a key name. Next, click on the \"Register New Key\" button below and plug your key into your device's USB port when prompted.", 'bp-two-factor' ) ),
		'backup_codes_count' => \Two_Factor_Backup_Codes::codes_remaining_for_user( buddypress()->displayed_user->userdata ),
		'backup_codes_misplaced' => sprintf( '<p>%s</p>', esc_html__( "If you misplaced your recovery codes, you can generate a new set of recovery codes below. Please note that your old codes will no longer work.", 'bp-two-factor' ) ),
		'backup_codes_generate' => sprintf( '<p>%s</p>', esc_html__( "Click on the button below to generate your recovery codes.", 'bp-two-factor' ) ),
		'recovery_codes_desc' => sprintf( '<p>%s</p>', esc_html__( "Recovery codes can be used to access your account if you lose access to your device and cannot receive two-factor authentication codes.", 'bp-two-factor' ) ),
		'totp_key' => sprintf( '<strong>%s</strong> ', esc_html__( 'Key:', 'bp-two-factor' ) )
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
	add_filter( 'gettext_two-factor', __NAMESPACE__ . '\\gettext_overrides', 10, 2 );
	add_filter( 'gettext_with_context_two-factor', __NAMESPACE__ . '\\gettext_overrides', 10, 2 );
	add_filter( 'gettext_two-factor-provider-webauthn', __NAMESPACE__ . '\\gettext_overrides', 10, 2 );
	add_filter( 'gettext_with_context_two-factor-provider-webauthn', __NAMESPACE__ . '\\gettext_overrides', 10, 2 );
	add_filter( 'ngettext_two-factor', __NAMESPACE__ . '\\ngettext_overrides', 10, 4 );

	// Heading and description. Can be removed by wiping out the hook.
	add_action( 'bp_2fa_before_settings_output', function() {
		printf( '<h3 id="two-factor-heading">%s</h3>', esc_html__( 'Two-factor Authentication', 'bp-two-factor' ) );

		printf( '<p>%s</p>', esc_html__( 'Two-factor authentication adds an optional, additional layer of security to your account by requiring more than your password to log in. Configure these additional methods below.', 'bp-two-factor' ) );
	} );

	/**
	 * Do something before BP 2FA output.
	 */
	do_action( 'bp_2fa_before_settings_output' );

	// Output 2FA options table.
	Two_Factor_Core::user_two_factor_options( $userdata );

	// Remove filters.
	remove_filter( 'self_admin_url', $user_settings_page_url, 10 );
	remove_filter( 'gettext_two-factor', __NAMESPACE__ . '\\gettext_overrides', 10 );
	remove_filter( 'gettext_with_context_two-factor', __NAMESPACE__ . '\\gettext_overrides', 10 );
	remove_filter( 'gettext_two-factor-provider-webauthn', __NAMESPACE__ . '\\gettext_overrides', 10 );
	remove_filter( 'gettext_with_context_two-factor-provider-webauthn', __NAMESPACE__ . '\\gettext_overrides', 10 );
	remove_filter( 'ngettext_two-factor', __NAMESPACE__ . '\\ngettext_overrides', 10 );

	/**
	 * Do something after BP 2FA output.
	 */
	do_action( 'bp_2fa_after_settings_output' );
}
add_action( 'bp_core_general_settings_before_submit', __NAMESPACE__ . '\\output' );

/**
 * Override strings for the two-factor plugin.
 *
 * @param  string $retval       Translated string.
 * @param  string $untranslated Untranslated string.
 * @return string
 */
function gettext_overrides( $retval, $untranslated ) {
	switch ( $untranslated ) {
		case 'Name' :
			//return esc_html__( 'Type', 'bp-two-factor' );
			break;

		case 'Please scan the QR code or manually enter the key, then enter an authentication code from your app in order to complete setup.' :
			return esc_html__( 'Please scan the QR code or manually enter the key into your authenticator app. Next, enter the authentication code from your app to complete set up.', 'bp-two-factor' );
			break;

		case 'Submit' :
			return esc_html__( 'Complete Set Up', 'bp-two-factor' );
			break;

		case 'FIDO U2F Security Keys' :
			return esc_html__( 'Security Keys', 'bp-two-factor' );
			break;

		case 'Requires an HTTPS connection. Configure your security keys in the "Security Keys" section below.' :
			return esc_html__( 'Security keys are hardware devices that can be used as your second factor of authentication. To configure your security keys, click on the "Enabled" checkbox and view the "Security Keys" section below.', 'bp-two-factor' );
			break;

		case 'Requires an HTTPS connection. Please configure your security keys in the <a href="#webauthn-security-keys-section">Security Keys (WebAuthn)</a> section below.' :
			return esc_html__( 'WebAuthn can be used as your second factor of authentication. To configure your WebAuthn security keys, click on the "Enabled" checkbox and view the "Security Keys (WebAuthn)" section below.', 'bp-two-factor' );
			break;

		case 'You will have to re-scan the QR code on all devices as the previous codes will stop working.' :
			return esc_html__( 'If you misplaced your TOTP device, you can reset your secret key below. If you used the previous key on other devices, they will also need to be updated with the new key in order to continue working.', 'bp-two-factor' );
			break;

		case 'Backup Verification Codes (Single Use)' :
			return esc_html__( 'Recovery Codes', 'bp-two-factor' );
			break;

		case 'Generate Verification Codes' :
			return esc_html__( 'Generate New Recovery Codes', 'bp-two-factor' );
			break;

		// Removing this string since it sounds like we're upselling something.
		// This text just links to a Google support article anyway...
		case 'You can find FIDO U2F Security Key devices for sale from here.' :
			return '';
			break;
	}

	return $retval;
}

/**
 * Override context strings for the two-factor plugin.
 *
 * @param  string $retval Translated string.
 * @param  string $single Untranslated string.
 * @param  string $plural
 * @param  int    $number
 * @return string
 */
function ngettext_overrides( $retval, $single, $plural, $number ) {
	if ( '%s unused code remaining.' === $single ) {
		return _n( 'You previously generated some prior codes and have %s unused code left.', 'You previously generated some prior codes and have %s unused codes left.', $number, 'bp-two-factor' );
	}

	return $retval;
}

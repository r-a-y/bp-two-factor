<?php
/**
 * BuddyPress HTML email integration
 *
 * Overrides the default wp_mail() call to use the BuddyPress HTML email post
 * template if available.
 *
 * @package    bp-two-factor
 * @subpackage email
 */

namespace CAC\BP2FA\BPEmail;

CONST EMAIL_TYPE = '2fa-email-token';

// Bail if our BP email post does not exist.
if ( is_wp_error( bp_get_email( EMAIL_TYPE ) ) ) {
	return;
}

/**
 * Save 2FA's email token and user ID during email message set up.
 *
 * @param  string $retval   Email message.
 * @param  int    $token    2FA token.
 * @param  int    $user_id  WP user ID.
 * @return string
 */
function save_token( $retval, $token, $user_id ) {
	Store::set( 'token',   $token );
	Store::set( 'user_id', (int) $user_id );

	return $retval;
}
add_filter( 'two_factor_token_email_message', __NAMESPACE__ . '\\save_token', 10, 3 );

/**
 * Use our BuddyPress HTML post template to send the 2FA token via email.
 *
 * @param  null  $retval Null by default.
 * @param  array $r      Email arguments.
 * @return bool Returns false to override wp_mail().
 */
function use_bp_email( $retval, $r ) {
	// Sanity check: Make sure we have our email token before continuing.
	$token = Store::get( 'token' );
	if ( false === $token ) {
		return $retval;
	}

	$user_id = Store::get( 'user_id' );

	// Remove our filter.
	remove_filter( 'pre_wp_mail', __NAMESPACE__ . '\\use_bp_email', 0, 2 );

	// Use BP's email.
	bp_send_email(
		EMAIL_TYPE,
		$user_id,
		[
			'tokens' => [
				'site.name'            => get_blog_option( bp_get_root_blog_id(), 'blogname' ),
				'site.admin_email'     => get_site_option( 'admin_email' ),
				'2fa.token'            => $token,
				'2fa.expiry'           => get_token_expiry( $user_id ),
				'2fa.lostpassword_url' => wp_lostpassword_url(),
			]
		]
	);

	// Do not allow wp_mail() to run!
	return false;
}
add_filter( 'pre_wp_mail', __NAMESPACE__ . '\\use_bp_email', 0, 2 );

/**
 * Returns the token expiry in minutes.
 *
 * @param  int $user_id User ID
 * @return int|float 
 */
function get_token_expiry( $user_id ) {
	if ( method_exists( 'Two_Factor_Email', 'user_token_ttl' ) ) {
		$retval = \Two_Factor_Email::get_instance()->user_token_ttl( $user_id );

	// Fallback for older 2FA plugin versions.
	} else {
		/** This filter is documented in /two-factor/providers/class-two-factor-email.php */
		$retval = (int) apply_filters( 'two_factor_email_token_ttl', 15 * MINUTE_IN_SECONDS, $user_id );
	}

	return round( $retval / MINUTE_IN_SECONDS, 2 );
}

/** HELPER **/

/**
 * Simple class to store local variables within our namespace.
 */
class Store {
	/**
	 * Data holder.
	 *
	 * @var array
	 */
	private static $data = [];

	/**
	 * Getter.
	 *
	 * @param  string $item Variable name.
	 * @return mixed
	 */
	public static function get( $var ) {
		if ( isset( self::$data[ $var ] ) ) {
			return self::$data[ $var ];
		}

		return false;
	}

	/**
	 * Setter.
	 *
	 * @param string $item Variable name.
	 * @param mixed  $val  Variable value.
	 */
	public static function set( $var, $val ) {
		self::$data[ $var ] = $val;
	}
}

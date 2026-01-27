jQuery(function($){
	var $securityKeys = $( '#security-keys-section' );
	var $u2fCheckbox = $( '#two-factor-options input[value="Two_Factor_FIDO_U2F"]' );
	var $checkboxes = $( '.two-factor-methods-table input[type="checkbox"]' );
	var $backupCodes = $( '#two-factor-backup-codes' );
	var $totp = $( '#two-factor-totp-options' );
	var $webAuthnCheckbox = $( '#two-factor-options input[value="TwoFactor_Provider_WebAuthn"]' );
	var $securityKeysWebAuthn = $('#webauthn-security-keys-section');
	var $revalidateButton = $( '.two-factor-warning-revalidate-session .button' );
	var $primaryMethods = $( '#two-factor-options .two-factor-primary-method-table select option:not(:disabled,[value=""],[value="Two_Factor_Backup_Codes"])' );

	// Only show Security Keys section if checked.
	if ( $u2fCheckbox.prop( 'checked' ) ) {
		$securityKeys.show();
	}
	$u2fCheckbox.on( 'change', function() {
		if ( $(this).prop( 'checked' ) ) {
			$securityKeys.show();
		} else {
			$securityKeys.hide();
		}
	} );

	// Only show WebAuthn Security Keys section if checked.
	if ( $webAuthnCheckbox.prop( 'checked' ) ) {
		$securityKeysWebAuthn.show();
	}
	$webAuthnCheckbox.on( 'change', function() {
		if ( $(this).prop( 'checked' ) ) {
			$securityKeysWebAuthn.show();
		} else {
			$securityKeysWebAuthn.hide();
		}
	} );

	// Eek. Inject strings and other stuff.
	$securityKeys.find( '.register-security-key' ).prepend( bp2fa.security_key_desc );
	$securityKeysWebAuthn.find( '.add-webauthn-key' ).prepend( bp2fa.security_key_webauthn_desc );
	$backupCodes.wrap( '<div id="two-factor-backup-codes-container"></div>' );
	$backupCodes.attr( 'data-count', bp2fa.backup_codes_count );
	if ( $backupCodes.data( 'count' ) > 0 ) {
		$backupCodes.parent().prepend( bp2fa.backup_codes_misplaced );
		$backupCodes.find('span').wrap('<p id="previous-codes"></p>' )
		$backupCodes.parent().prepend( $( '#previous-codes' ) );
	} else {
		$backupCodes.parent().prepend( bp2fa.backup_codes_generate );
	}
	$backupCodes.parent().prepend( bp2fa.recovery_codes_desc );

	if ( $revalidateButton.length ) {
		$revalidateButton.addClass( 'two-factor-revalidate' );
	}

	if ( $primaryMethods.length < 2  ) {
		$( '.two-factor-primary-method-table, .two-factor-methods-table + hr' ).hide();
	}

	// Customizations for TOTP provider.
	function totp_toggler() {
		if ( $totp.find( 'a.button' ).length ) {
			$totp.addClass( 'configured' ).removeClass( 'not-configured' );
		} else {
			$totp.addClass( 'not-configured' ).removeClass( 'configured' );
		}
	}

	totp_toggler();

	/*
	 * Select backup codes as a provider, only if a 2FA provider is enabled
	 * during backup code viewing.
	 */
	$( 'button.button-two-factor-backup-codes-generate' ).on( 'click', function() {
		if ( $checkboxes.filter( ':checked' ).length ) {
			$( 'table.two-factor-methods-table input[type="checkbox"][value="Two_Factor_Backup_Codes"]' ).prop( 'checked', true );
		}
	} );

	function backupCodes_toggler( providers ) {
		// If no providers checked, hide Recovery Codes option.
		if ( 0 === providers.length ) {
			$backupCodes.parents('tr').hide();
		} else {
			$backupCodes.parents('tr').show();
		}
	}

	backupCodes_toggler( $checkboxes.filter( ':checked' ) );

	$checkboxes.on( 'change', function() {
		var checked = $checkboxes.filter( ':checked' );

		backupCodes_toggler( checked );

		// Unchecked a provider.
		if ( ! $(this).prop( 'checked' ) ) {
			if ( ! checked.length ) {
				return;
			}

			// Uncheck Recovery Codes if it is the only provider remaining.
			if ( 'Two_Factor_Backup_Codes' === checked.first().val() && checked.length === 1 ) {
				$( 'table.two-factor-methods-table input[type="checkbox"][value="' + checked.first().val() + '"]' ).prop( 'checked', false );
			}
		}
	} );

	// Add CSS class when clicking on "Generate New Recovery Codes" button.
	$( '.button-two-factor-backup-codes-generate' ).click( function() {
		$(this).addClass( 'code-loading' );
	});


	// Remove 'code-loading' class after backup count is updated via AJAX.
	var mut = new MutationObserver(function(mutations){
	    mutations.forEach(function(mutationRecord) {
			document.querySelector('.button-two-factor-backup-codes-generate').classList.remove( 'code-loading' );
	    });
	});
	mut.observe(document.querySelector(".two-factor-backup-codes-count"),{
	  'childList': true
	});

	// AJAX mods.
	$( document ).on( "ajaxComplete", function( event, xhr, settings ) {
		if ( -1 === settings.url.indexOf( '/wp-json/two-factor' ) ) {
			return;
		}

		// TOTP.
		if ( -1 !== settings.url.indexOf( '/totp' ) ) {
			var checkbox = $('#enabled-Two_Factor_Totp'),
				checked = true;

			// Invalid TOTP auth code.
			if ( 400 === xhr.status ) {
				checked = false;
				setTimeout( () => {
					$( '#totp-setup-error' ).prop( 'id', 'message' ).addClass( 'totp-setup-error' ).delay(5000).fadeOut();
				}, 250 );
			}

			// Reset TOTP key.
			if ( xhr.responseJSON.success && settings.headers.hasOwnProperty( 'X-HTTP-Method-Override' ) && 'DELETE' === settings.headers['X-HTTP-Method-Override'] ) {
				checked = false;
			}

			checkbox.prop( 'checked', checked ).trigger( 'change' );

			setTimeout( () => {
				totp_toggler();
			}, 250 );
		}
	} );
})
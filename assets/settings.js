jQuery(function($){
	var $securityKeys = $( '#security-keys-section' );
	var $u2fCheckbox = $( '#two-factor-options input[value="Two_Factor_FIDO_U2F"]' );
	var $checkboxes = $( '.two-factor-methods-table input[type="checkbox"]' );
	var $backupCodes = $( '#two-factor-backup-codes' );
	var $totp = $( '#two-factor-totp-options' );

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

	// Allow 'Enter' key to submit TOTP auth code.
	$('#two-factor-totp-authcode').keypress(function(e) {
		if (13 === e.which) {
			$('input["name=two-factor-totp-submit"]').focus().click();
			return false;
		}
	});

	// Eek. Inject strings and other stuff.
	$securityKeys.find( '.register-security-key' ).prepend( bp2fa.security_key_desc );
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
	if ( $totp.find( 'a.button' ).length ) {
		$totp.addClass( 'configured' );
	} else {
		$totp.addClass( 'not-configured' );
		$totp.find( 'code' ).before( bp2fa.totp_key );
	}

	// Inject ID to checkbox.
	$('table.two-factor-methods-table input[type="checkbox"]').each( function() {
		$(this).attr( 'id', 'checkbox-' + $(this).val() );
	});

	// Inject label for each 2FA type.
	$('table.two-factor-methods-table td').each( function() {
		var label = $.trim( $(this).contents().get(0).nodeValue ),
			inputName = $(this).prev().find('input').val();

		$(this).html(function() {
			return $(this).html().replace( label, "<label for='checkbox-" + inputName + "'>" + label + "</label>" );
		});
	});

	/*
	 * Select backup codes as a provider, only if a 2FA provider is enabled
	 * during backup code viewing.
	 */
	$( 'button.button-two-factor-backup-codes-generate' ).on( 'click', function() {
		if ( $checkboxes.filter( ':checked' ).length ) {
			$( 'table.two-factor-methods-table input[type="checkbox"][value="Two_Factor_Backup_Codes"]' ).prop( 'checked', true );
		}
	} );

	// Check corresponding enabled checkbox during Primary provider click.
	$('input[type="radio"][name="_two_factor_provider"]').change(function() {
		var enabled = $( '#checkbox-' + this.value );

		if ( ! enabled.prop( 'checked' ) ) {
			enabled.prop( 'checked', true );
		}
	} );

	$checkboxes.on( 'change', function() {
		var radio = $( 'input[name="_two_factor_provider"]' ),
			radioVal = radio.filter( ':checked' ).val(),
			checkPrimary = false;

		// Enabled a provider.
		if ( $(this).prop( 'checked' ) ) {
			// Primary is Backup codes and Enabled is anything but Backup Codes.
			if ( 'Two_Factor_Backup_Codes' !== $(this).val() && 'Two_Factor_Backup_Codes' === radioVal ) {
				checkPrimary = true;

			// No primary and Enabled is anything but Backup Codes.
			} else if ( ! radioVal &&  'Two_Factor_Backup_Codes' !== $(this).val() ) {
				checkPrimary = true;
			}

			// Check corresponding Primary if allowed.
			if ( checkPrimary ) {
				$(this).parent().next().find('input').prop( 'checked', true );
			}

		// Disabled a provider.
		} else {
			// Deselect Primary provider for unchecked provider.
			if ( radioVal === $(this).val() ) {
				$( 'table.two-factor-methods-table input[type="radio"][value="' + $(this).val() + '"]' ).prop( 'checked', false );
			}

			checked = $checkboxes.filter( ':checked' );

			// Set a fallback primary provider that isn't Backup Codes.
			if ( checked.length && 'Two_Factor_Backup_Codes' !== checked.first().val() ) {
				$( 'table.two-factor-methods-table input[type="radio"][value="' + checked.first().val() + '"]' ).prop( 'checked', true );
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
})
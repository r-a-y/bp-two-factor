# BP Two Factor

Integrates the [Two Factor](https://wordpress.org/plugins/two-factor/) plugin into BuddyPress.

Compatible with Two Factor v0.14.2.

## How to use?

1. Install and activate the [Two Factor](https://wordpress.org/plugins/two-factor/) plugin.
2. Install and activate this plugin.
3. Navigate to your BuddyPress profile's "Settings" page. You should see a **Two Factor Authentication** block.
4. Enable your preferred 2FA method.
5. Logout and login again to test two-factor authentication.

## Notes

This plugin also makes the following usability improvements:

- Added more descriptive text to the "Security Keys" and "Recovery Codes" sections.
- Changed the BuddyPress "Settings > General" tab to "Settings > Security" to better reflect the content on the page.
- Hides the "Primary Method" 2FA field if only one 2FA method is enabled.
- De-emphasizes Recovery Codes as a recommended provider as it shouldn't be recommended if no 2FA methods are configured by the user or if the user already has two or more other 2FA methods enabled.

These improvements **only** take place on the BuddyPress profile's "Settings" page, and not in the admin dashboard's profile page.
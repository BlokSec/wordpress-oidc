<?php
/**
 * Phpstan bootstrap file.
 *
 * @package   BlokSec_OIDC
 * @author    Kevin Wicken <kwicken@bloksec.com>
 * @author    Jonathan Daggerhart <jonathan@daggerhart.com>
 * @author    Tim Nolte <tim.nolte@ndigitals.com>
 * @copyright 2015-2020
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

// Define WordPress language directory.
defined( 'WP_LANG_DIR' ) || define( 'WP_LANG_DIR', 'wordpress/src/wp-includes/languages/' );

defined( 'COOKIE_DOMAIN' ) || define( 'COOKIE_DOMAIN', 'localhost' );
defined( 'COOKIEPATH' ) || define( 'COOKIEPATH', '/');

// Define Plugin Globals.
defined( 'OIDC_CLIENT_ID' ) || define( 'OIDC_CLIENT_ID', bin2hex( random_bytes( 32 ) ) );
defined( 'OIDC_CLIENT_SECRET' ) || define( 'OIDC_CLIENT_SECRET', bin2hex( random_bytes( 16 ) ) );
defined( 'OIDC_ENDPOINT_LOGIN_URL' ) || define( 'OIDC_ENDPOINT_LOGIN_URL', 'https://oidc/oauth2/authorize' );
defined( 'OIDC_ENDPOINT_USERINFO_URL' ) || define( 'OIDC_ENDPOINT_USERINFO_URL', 'https://oidc/oauth2/userinfo' );
defined( 'OIDC_ENDPOINT_TOKEN_URL' ) || define( 'OIDC_ENDPOINT_TOKEN_URL', 'https://oidc/oauth2/token' );
defined( 'OIDC_ENDPOINT_LOGOUT_URL' ) || define( 'OIDC_ENDPOINT_LOGOUT_URL', 'https://oidc/oauth2/logout' );

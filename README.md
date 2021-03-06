# BlokSec OpenID Connect Client #
**Contributors:** [daggerhart](https://profiles.wordpress.org/daggerhart), [tnolte](https://profiles.wordpress.org/tnolte), [kwicken](https://profiles.wordpress.org/kwicken)  
**Tags:** security, login, oauth2, openidconnect, apps, authentication, autologin, sso  
**Requires at least:** 4.9  
**Tested up to:** 5.4.2  
**Stable tag:** 3.3.0  
**Requires PHP:** 5.6  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

A simple client that provides SSO or opt-in authentication against a generic OAuth2 Server implementation.

## Description ##

This plugin allows to authenticate users against OpenID Connect OAuth2 API with Authorization Code Flow.
Once installed, it can be configured to automatically authenticate users (SSO), or provide a "Login with OpenID Connect"
button on the login form. After consent has been obtained, an existing user is automatically logged into WordPress, while
new users are created in WordPress database.

Much of the documentation can be found on the Settings > BlokSec OIDC dashboard page.

Please submit issues kwicken@bloksec.com

## Installation ##

1. Upload to the `/wp-content/plugins/` directory
1. Activate the plugin
1. Visit Settings > BlokSec OIDC and configure to meet your needs

## Frequently Asked Questions ##

### What is the client's Redirect URI? ###

Most OAuth2 servers will require whitelisting a set of redirect URIs for security purposes. The Redirect URI provided
by this client is like so:  https://example.com/wp-admin/admin-ajax.php?action=openid-connect-authorize

Replace `example.com` with your domain name and path to WordPress.

### Can I change the client's Redirect URI? ###

Some OAuth2 servers do not allow for a client redirect URI to contain a query string. The default URI provided by
this module leverages WordPress's `admin-ajax.php` endpoint as an easy way to provide a route that does not include
HTML, but this will naturally involve a query string. Fortunately, this plugin provides a setting that will make use of
an alternate redirect URI that does not include a query string.

On the settings page for this plugin (Dashboard > Settings > OpenID Connect Generic) there is a checkbox for
**Alternate Redirect URI**. When checked, the plugin will use the Redirect URI
`https://example.com/openid-connect-authorize`.


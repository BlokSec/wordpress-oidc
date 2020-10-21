<?php
/**
 * Bloksec OpenID Connect Client
 *
 * This plugin provides the ability to authenticate users with Identity
 * Providers using the OpenID Connect OAuth2 API with Authorization Code Flow.
 *
 * @package   Bloksec_OIDC
 * @category  General
 * @author    Kevin Wicken <kwicken@bloksec.com>
 * @copyright 2015-2020 Bloksec
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 * @link      https://github.com/wicken
 *
 * @wordpress-plugin
 * Plugin Name:       Bloksec OpenID Connect
 * Plugin URI:        https://bloksec.com
 * Description:       Connect to an Bloksec OpenID Connect client.
 * Version:           3.0.0
 * Author:            Bloksec Inc.
 * Author URI:        https://bloksec.com
 * Text Domain:       bloksec-oidc
 * Domain Path:       /languages
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

/*
Notes
  Spec Doc - http://openid.net/specs/openid-connect-basic-1_0-32.html

  Filters
  - openid-connect-generic-alter-request       - 3 args: request array, plugin settings, specific request op
  - openid-connect-generic-settings-fields     - modify the fields provided on the settings page
  - openid-connect-generic-login-button-text   - modify the login button text
  - openid-connect-generic-cookie-redirect-url - modify the redirect url stored as a cookie
  - openid-connect-generic-user-login-test     - (bool) should the user be logged in based on their claim
  - openid-connect-generic-user-creation-test  - (bool) should the user be created based on their claim
  - openid-connect-generic-auth-url            - modify the authentication url
  - openid-connect-generic-alter-user-claim    - modify the user_claim before a new user is created
  - openid-connect-generic-alter-user-data     - modify user data before a new user is created
  - openid-connect-modify-token-response-before-validation - modify the token response before validation
  - openid-connect-modify-id-token-claim-before-validation - modify the token claim before validation

  Actions
  - openid-connect-generic-user-create        - 2 args: fires when a new user is created by this plugin
  - openid-connect-generic-user-update        - 1 arg: user ID, fires when user is updated by this plugin
  - openid-connect-generic-update-user-using-current-claim - 2 args: fires every time an existing user logs
  - openid-connect-generic-redirect-user-back - 2 args: $redirect_url, $user. Allows interruption of redirect during login.
  - openid-connect-generic-user-logged-in     - 1 arg: $user, fires when user is logged in.
  - openid-connect-generic-cron-daily         - daily cron action
  - openid-connect-generic-state-not-found    - the given state does not exist in the database, regardless of its expiration.
  - openid-connect-generic-state-expired      - the given state exists, but expired before this login attempt.

  User Meta
  - openid-connect-generic-subject-identity    - the identity of the user provided by the idp
  - openid-connect-generic-last-id-token-claim - the user's most recent id_token claim, decoded
  - openid-connect-generic-last-user-claim     - the user's most recent user_claim
  - openid-connect-generic-last-token-response - the user's most recent token response

  Options
  - openid_connect_generic_settings     - plugin settings
  - openid-connect-generic-valid-states - locally stored generated states
*/


/**
 * OpenID_Connect_Generic class.
 *
 * Defines plugin initialization functionality.
 *
 * @package Bloksec_OIDC
 * @category  General
 */
class OpenID_Connect_Generic {

	/**
	 * Plugin version.
	 *
	 * @var
	 */
	const VERSION = '3.9.0';

	/**
	 * Plugin settings.
	 *
	 * @var OpenID_Connect_Generic_Option_Settings
	 */
	private $settings;

	/**
	 * Plugin logs.
	 *
	 * @var OpenID_Connect_Generic_Option_Logger
	 */
	private $logger;

	/**
	 * Openid Connect Generic client
	 *
	 * @var OpenID_Connect_Generic_Client
	 */
	private $client;

	/**
	 * Client wrapper.
	 *
	 * @var OpenID_Connect_Generic_Client_Wrapper
	 */
	private $client_wrapper;

	/**
	 * Setup the plugin
	 *
	 * @param OpenID_Connect_Generic_Option_Settings $settings The settings object.
	 * @param OpenID_Connect_Generic_Option_Logger   $logger   The loggin object.
	 *
	 * @return void
	 */
	function __construct( OpenID_Connect_Generic_Option_Settings $settings, OpenID_Connect_Generic_Option_Logger $logger ) {
		$this->settings = $settings;
		$this->logger = $logger;
	}

	/**
	 * WordPress Hook 'init'.
	 *
	 * @return void
	 */
	function init() {

		$redirect_uri = admin_url( 'admin-ajax.php?action=openid-connect-authorize' );

		if ( $this->settings->alternate_redirect_uri ) {
			$redirect_uri = site_url( '/openid-connect-authorize' );
		}

		$state_time_limit = 180;
		if ( $this->settings->state_time_limit ) {
			$state_time_limit = intval( $this->settings->state_time_limit );
		}

		$this->client = new OpenID_Connect_Generic_Client(
			$this->settings->client_id,
			$this->settings->client_secret,
			$this->settings->scope,
			$this->settings->endpoint_login,
			$this->settings->endpoint_userinfo,
			$this->settings->endpoint_token,
			$redirect_uri,
			$state_time_limit,
			$this->logger
		);

		$this->client_wrapper = OpenID_Connect_Generic_Client_Wrapper::register( $this->client, $this->settings, $this->logger );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		OpenID_Connect_Generic_Login_Form::register( $this->settings, $this->client_wrapper );

		// Add a shortcode to get the auth URL.
		add_shortcode( 'openid_connect_generic_auth_url', array( $this->client_wrapper, 'get_authentication_url' ) );

		// Add actions to our scheduled cron jobs.
		add_action( 'openid-connect-generic-cron-daily', array( $this, 'cron_states_garbage_collection' ) );

		$this->upgrade();

		if ( is_admin() ) {
			OpenID_Connect_Generic_Settings_Page::register( $this->settings, $this->logger );
		}
	}

	/**
	 * Check if privacy enforcement is enabled, and redirect users that aren't
	 * logged in.
	 *
	 * @return void
	 */
	function enforce_privacy_redirect() {
		if ( $this->settings->enforce_privacy && ! is_user_logged_in() ) {
			// The client endpoint relies on the wp admind ajax endpoint.
			if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX || ! isset( $_GET['action'] ) || 'openid-connect-authorize' != $_GET['action'] ) {
				auth_redirect();
			}
		}
	}

	/**
	 * Check if privacy enforcement is enabled, and redirect users that aren't
	 * logged in.
	 *
	 * @return void
	 */
	function login_redirect() {
		return '/';
	}

	/**
	 * Enforce privacy settings for rss feeds.
	 *
	 * @param string $content The content.
	 *
	 * @return mixed
	 */
	function enforce_privacy_feeds( $content ) {
		if ( $this->settings->enforce_privacy && ! is_user_logged_in() ) {
			$content = __( 'Private site', 'bloksec-oidc' );
		}
		return $content;
	}

	/**
	 * Handle plugin upgrades
	 *
	 * @return void
	 */
	function upgrade() {
		$last_version = get_option( 'openid-connect-generic-plugin-version', 0 );
		$settings = $this->settings;

		if ( version_compare( self::VERSION, $last_version, '>' ) ) {
			// An upgrade is required.
			self::setup_cron_jobs();

			// @todo move this to another file for upgrade scripts
			if ( isset( $settings->ep_login ) ) {
				$settings->endpoint_login = $settings->ep_login;
				$settings->endpoint_token = $settings->ep_token;
				$settings->endpoint_userinfo = $settings->ep_userinfo;

				unset( $settings->ep_login, $settings->ep_token, $settings->ep_userinfo );
				$settings->save();
			}

			// Update the stored version number.
			update_option( 'openid-connect-generic-plugin-version', self::VERSION );
		}
	}

	/**
	 * Expire state transients by attempting to access them and allowing the
	 * transient's own mechanisms to delete any that have expired.
	 *
	 * @return void
	 */
	function cron_states_garbage_collection() {
		global $wpdb;
		$states = $wpdb->get_col( "SELECT `option_name` FROM {$wpdb->options} WHERE `option_name` LIKE '_transient_openid-connect-generic-state--%'" );

		if ( ! empty( $states ) ) {
			foreach ( $states as $state ) {
				$transient = str_replace( '_transient_', '', $state );
				get_transient( $transient );
			}
		}
	}

	/**
	 * Ensure cron jobs are added to the schedule.
	 *
	 * @return void
	 */
	static public function setup_cron_jobs() {
		if ( ! wp_next_scheduled( 'openid-connect-generic-cron-daily' ) ) {
			wp_schedule_event( time(), 'daily', 'openid-connect-generic-cron-daily' );
		}
	}

	/**
	 * Activation hook.
	 *
	 * @return void
	 */
	static public function activation() {
		self::setup_cron_jobs();
	}

	/**
	 * Deactivation hook.
	 *
	 * @return void
	 */
	static public function deactivation() {
		wp_clear_scheduled_hook( 'openid-connect-generic-cron-daily' );
	}

	/**
	 * Simple autoloader.
	 *
	 * @param string $class The class name.
	 *
	 * @return void
	 */
	static public function autoload( $class ) {
		$prefix = 'OpenID_Connect_Generic_';

		if ( stripos( $class, $prefix ) !== 0 ) {
			return;
		}

		$filename = $class . '.php';

		// Internal files are all lowercase and use dashes in filenames.
		if ( false === strpos( $filename, '\\' ) ) {
			$filename = strtolower( str_replace( '_', '-', $filename ) );
		} else {
			$filename  = str_replace( '\\', DIRECTORY_SEPARATOR, $filename );
		}

		$filepath = dirname( __FILE__ ) . '/includes/' . $filename;

		if ( file_exists( $filepath ) ) {
			require_once $filepath;
		}
	}

	/**
	 * Instantiate the plugin and hook into WordPress.
	 *
	 * @return void
	 */
	static public function bootstrap() {
		/**
		 * This is a documented valid call for spl_autoload_register.
		 *
		 * @link https://www.php.net/manual/en/function.spl-autoload-register.php#71155
		 */
		spl_autoload_register( array( 'OpenID_Connect_Generic', 'autoload' ) );

		$settings = new OpenID_Connect_Generic_Option_Settings(
			'openid_connect_generic_settings',
			// Default settings values.
			array(
				// OAuth client settings.
				'login_type'           => 'button',
				'client_id'            => defined( 'OIDC_CLIENT_ID' ) ? OIDC_CLIENT_ID : '',
				'client_secret'        => defined( 'OIDC_CLIENT_SECRET' ) ? OIDC_CLIENT_SECRET : '',
				'scope'                => '',
				'endpoint_login'       => defined( 'OIDC_ENDPOINT_LOGIN_URL' ) ? OIDC_ENDPOINT_LOGIN_URL : 'https://api.bloksec.io/oidc/auth',
				'endpoint_userinfo'    => defined( 'OIDC_ENDPOINT_USERINFO_URL' ) ? OIDC_ENDPOINT_USERINFO_URL : 'https://api.bloksec.io/oidc/me',
				'endpoint_token'       => defined( 'OIDC_ENDPOINT_TOKEN_URL' ) ? OIDC_ENDPOINT_TOKEN_URL : 'https://api.bloksec.io/oidc/token',
				'endpoint_end_session' => defined( 'OIDC_ENDPOINT_LOGOUT_URL' ) ? OIDC_ENDPOINT_LOGOUT_URL : 'https://api.bloksec.io/oidc/session/end',

				'register_popup_title'       => defined( 'OIDC_REGISTER_POPUP_TITLE' ) ? OIDC_REGISTER_POPUP_TITLE : 'Register for passwordless login',
				'register_popup_content'       => defined( 'OIDC_REGISTER_POPUP_CONTENT' ) ? OIDC_REGISTER_POPUP_CONTENT : 'Would you like to try passwordless login?',

				// Non-standard settings.
				'no_sslverify'    => 0,
				'http_request_timeout' => 5,
				'identity_key'    => 'email',
				'nickname_key'    => 'email',
				'email_format'       => '{email}',
				'displayname_format' => '',
				'identify_with_username' => true,

				// Plugin settings.
				'enforce_privacy' => 0,
				'alternate_redirect_uri' => 0,
				'token_refresh_enable' => 1,
				'link_existing_users' => 1,
				'create_if_does_not_exist' => 1,
				'redirect_user_back' => 0,
				'redirect_on_logout' => 1,
				'enable_logging'  => 1,
				'log_limit'       => 1000,
			)
		);

		$logger = new OpenID_Connect_Generic_Option_Logger( 'openid-connect-generic-logs', 'error', $settings->enable_logging, $settings->log_limit );

		$plugin = new self( $settings, $logger );

		add_action( 'init', array( $plugin, 'init' ) );
		add_action( 'wp_ajax_register_for_bloksec', array( $plugin, 'register_for_bloksec' ) );
		add_action( 'wp_ajax_ignore_bloksec_question', array( $plugin, 'ignore_bloksec_question' ) );
		add_action('wp_footer', array( $plugin, 'login_question' ));
		// Privacy hooks.
		add_action( 'template_redirect', array( $plugin, 'enforce_privacy_redirect' ), 0 );
		add_action( 'login_redirect', array( $plugin, 'login_redirect' ), 0 );
		add_filter( 'the_content_feed', array( $plugin, 'enforce_privacy_feeds' ), 999 );
		add_filter( 'the_excerpt_rss', array( $plugin, 'enforce_privacy_feeds' ), 999 );
		add_filter( 'comment_text_rss', array( $plugin, 'enforce_privacy_feeds' ), 999 );
	}

	function register_for_bloksec(){
		$user = wp_get_current_user();
		if($user){
			$username = $user->user_login;
			$email = $user->user_email;
			$firstName = $user->first_name;
			$lastName = $user->last_name;
			$userBody = array(
				'name' => $firstName . ' ' . $lastName,
				'email' => $email
			);
			$accountBody = array(
				'name' => $username,
				'appId' => $this->settings->client_id
			);
			$body = array(
				'auth_token' => $this->settings->client_secret,
				'user' => $userBody,
				'account' => $accountBody
			);
			wp_remote_request ('https://api.bloksec.io/registration', array(
				'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
				'body'        => json_encode($body),
				'method'      => 'POST',
				'data_format' => 'body'
			));
		}
		add_user_meta( $user->ID, '_bloksec_question_asked', 1, true );

	}

	function ignore_bloksec_question(){
		$user = wp_get_current_user();
		add_user_meta( $user->ID, '_bloksec_question_asked', 1, true );
	}


	/**
	 * Loads pop-up on first login.
	 */
	function login_question() {
		$user = wp_get_current_user();
		if($user && is_user_logged_in()){
			$question_asked = get_user_meta( $user->ID, '_bloksec_question_asked', true );
			if ( !$question_asked ) {
			?>
			<script>
				function callBackend(action, close){
					const data = new FormData();
					data.append('action', action);
					fetch( '<?php echo admin_url('admin-ajax.php'); ?>', {
						method: "POST",
						credentials: 'same-origin',
						body: data
					})
						.then(response => response.json())
						.then(commits => {
							document.getElementById('register-content').style.display = 'none';
							document.getElementById('thankyou-content').style.display = 'block';
							if(close) closeRegisterPopup();
						});
				}

				function openRegisterPopup(){
					const element = document.getElementById('registerPopup');
					element.style.display = 'grid';
				}
				function closeRegisterPopup(){
					const element = document.getElementById('registerPopup');
					element.style.display = 'none';
				}
			</script>
			<style>
			#thankyou-content {
				display: none;
			}
			.register-popup{
				background: rgba(0,0,0,0.7);
				place-items: center;
				position: fixed;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				z-index: 4;
			}
			.input:focus{
				border-color: #007cba;
				box-shadow: 0 0 0 1px #007cba;
				outline: 2px solid transparent;
			}
			.popup-content {
				padding: 50px;
				position: relative;
				display: grid;
				align-items: center;
				width: 300px;
				height 400px!important;
				background-color: white;
				color: rgba(0,0,0,0.8);
				border-radius: 10px;
			}
			.popup-content {
				padding: 50px;
				position: relative;
				display: grid;
				align-items: center;
				background-color: white;
				color: rgba(0,0,0,0.8);
				border-radius: 10px;
				width: 600px;
				margin: auto;
				margin-top: 200px;
			}
			.bloksec-buttons {
				display: grid;
				grid-template-columns: 1fr 1fr;
				grid-gap: 20px;
				padding: 20px 0px;
			}
			.bloksec-header {
				margin-top: 0px;
			}
			</style>
			<div id="registerPopup" class="register-popup">
				<div id="register-content" class="popup-content">
					<h3 class="bloksec-header"><?php echo $this->settings->register_popup_title; ?></h3>
					<p><?php echo $this->settings->register_popup_content; ?></p>
					<div class="bloksec-buttons">
						<button type="button" class="button button-primary button-large" onclick="callBackend('register_for_bloksec', false)">Register</button>
						<button type="button" class="button button-large" onclick="callBackend('ignore_bloksec_question', true)">No thank you</button>
					</div>
				</div>
				<div id="thankyou-content" class="popup-content">
					<h3 class="bloksec-header">Thank you!</h3>
					<p>An email has been sent with instructions on how to complete the setup of passwordless login.</p>
					<div class="bloksec-buttons">
						<button type="button" class="button button-large" onclick="closeRegisterPopup()">Close</button>
					</div>
				</div>
			</div>

			<?php
			}
		}
	}

}

OpenID_Connect_Generic::bootstrap();

register_activation_hook( __FILE__, array( 'OpenID_Connect_Generic', 'activation' ) );
register_deactivation_hook( __FILE__, array( 'OpenID_Connect_Generic', 'deactivation' ) );

<?php
/**
 * Plugin Admin settings page class.
 *
 * @package   OpenID_Connect_Generic
 * @category  Settings
 * @author    Jonathan Daggerhart <jonathan@daggerhart.com>
 * @copyright 2015-2020 daggerhart
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * OpenID_Connect_Generic_Settings_Page class.
 *
 * Admin settings page.
 *
 * @package OpenID_Connect_Generic
 * @category  Settings
 */
class OpenID_Connect_Generic_Settings_Page {

	/**
	 * Local copy of the settings provided by the base plugin.
	 *
	 * @var OpenID_Connect_Generic_Option_Settings
	 */
	private $settings;

	/**
	 * Instance of the plugin logger.
	 *
	 * @var OpenID_Connect_Generic_Option_Logger
	 */
	private $logger;

	/**
	 * The controlled list of settings & associated defined during
	 * construction for i18n reasons.
	 *
	 * @var array
	 */
	private $settings_fields = array();

	/**
	 * Options page slug.
	 *
	 * @var string
	 */
	private $options_page_name = 'openid-connect-generic-settings';

	/**
	 * Options page settings group name.
	 *
	 * @var string
	 */
	private $settings_field_group;

	/**
	 * Settings page class constructor.
	 *
	 * @param OpenID_Connect_Generic_Option_Settings $settings The plugin settings object.
	 * @param OpenID_Connect_Generic_Option_Logger   $logger   The plugin logging class object.
	 */
	function __construct( OpenID_Connect_Generic_Option_Settings $settings, OpenID_Connect_Generic_Option_Logger $logger ) {

		$this->settings             = $settings;
		$this->logger               = $logger;
		$this->settings_field_group = $this->settings->get_option_name() . '-group';

		$fields = $this->get_settings_fields();

		// Some simple pre-processing.
		foreach ( $fields as $key => &$field ) {
			$field['key']  = $key;
			$field['name'] = $this->settings->get_option_name() . '[' . $key . ']';
		}

		// Allow alterations of the fields.
		$this->settings_fields = $fields;
	}

	/**
	 * Hook the settings page into WordPress.
	 *
	 * @param OpenID_Connect_Generic_Option_Settings $settings A plugin settings object instance.
	 * @param OpenID_Connect_Generic_Option_Logger   $logger   A plugin logger object instance.
	 *
	 * @return void
	 */
	static public function register( OpenID_Connect_Generic_Option_Settings $settings, OpenID_Connect_Generic_Option_Logger $logger ) {
		$settings_page = new self( $settings, $logger );

		// Add our options page the the admin menu.
		add_action( 'admin_menu', array( $settings_page, 'admin_menu' ) );

		// Register our settings.
		add_action( 'admin_init', array( $settings_page, 'admin_init' ) );
	}

	/**
	 * Implements hook admin_menu to add our options/settings page to the
	 *  dashboard menu.
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_options_page(
			__( 'Bloksec - OpenID Connect', 'bloksec-oidc' ),
			__( 'Bloksec OIDC', 'bloksec-oidc' ),
			'manage_options',
			$this->options_page_name,
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Implements hook admin_init to register our settings.
	 *
	 * @return void
	 */
	public function admin_init() {
		register_setting(
			$this->settings_field_group,
			$this->settings->get_option_name(),
			array(
				$this,
				'sanitize_settings',
			)
		);

		add_settings_section(
			'client_settings',
			__( 'Client Settings', 'bloksec-oidc' ),
			array( $this, 'client_settings_description' ),
			$this->options_page_name
		);

		// Preprocess fields and add them to the page.
		foreach ( $this->settings_fields as $key => $field ) {
			// Make sure each key exists in the settings array.
			if ( ! isset( $this->settings->{ $key } ) ) {
				$this->settings->{ $key } = null;
			}

			// Determine appropriate output callback.
			switch ( $field['type'] ) {
				case 'checkbox':
					$callback = 'do_checkbox';
					break;

				case 'select':
					$callback = 'do_select';
					break;

				case 'text':
				default:
					$callback = 'do_text_field';
					break;
			}

			// Add the field.
			add_settings_field(
				$key,
				$field['title'],
				array( $this, $callback ),
				$this->options_page_name,
				$field['section'],
				$field
			);
		}
	}

	/**
	 * Get the plugin settings fields definition.
	 *
	 * @return array
	 */
	private function get_settings_fields() {

		/**
		 * Simple settings fields have:
		 *
		 * - title
		 * - description
		 * - type ( checkbox | text | select )
		 * - section - settings/option page section ( client_settings | authorization_settings )
		 * - example (optional example will appear beneath description and be wrapped in <code>)
		 */
		$fields = array(
			'login_type'        => array(
				'title'       => __( 'Login Type', 'bloksec-oidc' ),
				'description' => __( 'Select how the client (login form) should provide login options.', 'bloksec-oidc' ),
				'type'        => 'select',
				'options'     => array(
					'button' => __( 'Bloksec button on login form', 'bloksec-oidc' ),
					'auto'   => __( 'Auto Login - SSO', 'bloksec-oidc' ),
				),
				'section'     => 'client_settings',
			),
			'client_id'         => array(
				'title'       => __( 'Client ID', 'bloksec-oidc' ),
				'description' => __( 'The ID this client will be recognized as when connecting the to Identity provider server.', 'bloksec-oidc' ),
				'example'     => 'my-wordpress-client-id',
				'type'        => 'text',
				'disabled'    => defined( 'OIDC_CLIENT_ID' ),
				'section'     => 'client_settings',
			),
			'client_secret'     => array(
				'title'       => __( 'Client Secret Key', 'bloksec-oidc' ),
				'description' => __( 'Arbitrary secret key the server expects from this client. Can be anything, but should be very unique.', 'bloksec-oidc' ),
				'type'        => 'text',
				'disabled'    => defined( 'OIDC_CLIENT_SECRET' ),
				'section'     => 'client_settings',
			),
			'scope'             => array(
				'title'       => __( 'OpenID Scope', 'bloksec-oidc' ),
				'description' => __( 'Space separated list of scopes this client should access.', 'bloksec-oidc' ),
				'example'     => 'email profile openid offline_access',
				'type'        => 'text',
				'section'     => 'client_settings',
			),
		);

		return apply_filters( 'openid-connect-generic-settings-fields', $fields );

	}

	/**
	 * Sanitization callback for settings/option page.
	 *
	 * @param array $input The submitted settings values.
	 *
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$options = array();

		// Loop through settings fields to control what we're saving.
		foreach ( $this->settings_fields as $key => $field ) {
			if ( isset( $input[ $key ] ) ) {
				$options[ $key ] = sanitize_text_field( trim( $input[ $key ] ) );
			} else {
				$options[ $key ] = '';
			}
		}

		return $options;
	}

	/**
	 * Output the options/settings page.
	 *
	 * @return void
	 */
	public function settings_page() {
		$redirect_uri = admin_url( 'admin-ajax.php?action=openid-connect-authorize' );

		if ( $this->settings->alternate_redirect_uri ) {
			$redirect_uri = site_url( '/openid-connect-authorize' );
		}
		?>
		<div class="wrap">
			<h2><?php print esc_html( get_admin_page_title() ); ?></h2>

			<form method="post" action="options.php">
				<?php
				settings_fields( $this->settings_field_group );
				do_settings_sections( $this->options_page_name );
				submit_button();

				// Simple debug to view settings array.
				if ( isset( $_GET['debug'] ) ) {
					var_dump( $this->settings->get_values() );
				}
				?>
			</form>

			<h4><?php _e( 'Notes', 'bloksec-oidc' ); ?></h4>

			<p class="description">
				<strong><?php _e( 'Redirect URI', 'bloksec-oidc' ); ?></strong>
				<code><?php print $redirect_uri; ?></code>
			</p>
			<p class="description">
				<strong><?php _e( 'Login Button Shortcode', 'bloksec-oidc' ); ?></strong>
				<code>[openid_connect_generic_login_button]</code>
			</p>
			<p class="description">
				<strong><?php _e( 'Authentication URL Shortcode', 'bloksec-oidc' ); ?></strong>
				<code>[openid_connect_generic_auth_url]</code>
			</p>

			<?php if ( $this->settings->enable_logging ) { ?>
				<h2><?php _e( 'Logs', 'bloksec-oidc' ); ?></h2>
				<div id="logger-table-wrapper">
					<?php print $this->logger->get_logs_table(); ?>
				</div>

			<?php } ?>
		</div>
		<?php
	}

	/**
	 * Output a standard text field.
	 *
	 * @param array $field The settings field definition array.
	 *
	 * @return void
	 */
	public function do_text_field( $field ) {
		?>
		<input type="<?php print esc_attr( $field['type'] ); ?>"
				<?php echo ( ! empty( $field['disabled'] ) && boolval( $field['disabled'] ) ) ? ' disabled' : ''; ?>
			  id="<?php print esc_attr( $field['key'] ); ?>"
			  class="large-text<?php echo ( ! empty( $field['disabled'] ) && boolval( $field['disabled'] ) ) ? ' disabled' : ''; ?>"
			  name="<?php print esc_attr( $field['name'] ); ?>"
			  value="<?php print esc_attr( $this->settings->{ $field['key'] } ); ?>">
		<?php
		$this->do_field_description( $field );
	}

	/**
	 * Output a checkbox for a boolean setting.
	 *  - hidden field is default value so we don't have to check isset() on save.
	 *
	 * @param array $field The settings field definition array.
	 *
	 * @return void
	 */
	public function do_checkbox( $field ) {
		?>
		<input type="hidden" name="<?php print esc_attr( $field['name'] ); ?>" value="0">
		<input type="checkbox"
			   id="<?php print esc_attr( $field['key'] ); ?>"
			   name="<?php print esc_attr( $field['name'] ); ?>"
			   value="1"
			<?php checked( $this->settings->{ $field['key'] }, 1 ); ?>>
		<?php
		$this->do_field_description( $field );
	}

	/**
	 * Output a select control.
	 *
	 * @param array $field The settings field definition array.
	 *
	 * @return void
	 */
	function do_select( $field ) {
		$current_value = isset( $this->settings->{ $field['key'] } ) ? $this->settings->{ $field['key'] } : '';
		?>
		<select name="<?php print esc_attr( $field['name'] ); ?>">
			<?php foreach ( $field['options'] as $value => $text ) : ?>
				<option value="<?php print esc_attr( $value ); ?>" <?php selected( $value, $current_value ); ?>><?php print esc_html( $text ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
		$this->do_field_description( $field );
	}

	/**
	 * Output the field description, and example if present.
	 *
	 * @param array $field The settings field definition array.
	 *
	 * @return void
	 */
	public function do_field_description( $field ) {
		?>
		<p class="description">
			<?php print $field['description']; ?>
			<?php if ( isset( $field['example'] ) ) : ?>
				<br/><strong><?php _e( 'Example', 'bloksec-oidc' ); ?>: </strong>
				<code><?php print $field['example']; ?></code>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Output the 'Client Settings' plugin setting section description.
	 *
	 * @return void
	 */
	public function client_settings_description() {
		_e( 'Enter your Bloksec application config.', 'bloksec-oidc' );
	}

	/**
	 * Output the 'WordPress User Settings' plugin setting section description.
	 *
	 * @return void
	 */
	public function user_settings_description() {
		_e( 'Modify the interaction between OpenID Connect and WordPress users.', 'bloksec-oidc' );
	}

	/**
	 * Output the 'Authorization Settings' plugin setting section description.
	 *
	 * @return void
	 */
	public function authorization_settings_description() {
		_e( 'Control the authorization mechanics of the site.', 'bloksec-oidc' );
	}

	/**
	 * Output the 'Log Settings' plugin setting section description.
	 *
	 * @return void
	 */
	public function log_settings_description() {
		_e( 'Log information about login attempts through OpenID Connect Generic.', 'bloksec-oidc' );
	}
}

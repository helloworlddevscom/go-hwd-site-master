<?php

/**
 * Class TVA_Email_Templates
 * - localizes required data
 * - saves an template item into DB
 * - handles the email templates sent to users
 */
class TVA_Email_Templates {

	/**
	 * @var self
	 */
	protected static $instance;

	/**
	 * @var array
	 */
	protected $_templates = array();

	/**
	 * @var WP_User newly created user; kept on this instance to be available for rendering the shortcodes
	 */
	protected $_user;

	/**
	 * TVA_Email_Templates constructor.
	 */
	private function __construct() {

		$this->_templates = $this->_get_option();
		/*
		 * Backwards compatibility: [user_pass] shortcode should always have a `if_user_provided` parameter
		 */
		if ( ! empty( $this->_templates['newAccount'] ) && ! empty( $this->_templates['newAccount']['body'] ) ) {
			$this->_templates['newAccount']['body'] = str_replace( '[user_pass]', '[user_pass if_user_provided="The password you chose during registration"]', $this->_templates['newAccount']['body'] );
		}

		$this->_init();
	}

	/**
	 * @return array
	 */
	protected function _get_option() {
		return get_option( 'tva_email_templates', array() );
	}

	/**
	 * @return bool
	 */
	protected function _save_option() {
		return update_option( 'tva_email_templates', $this->_templates );
	}

	/**
	 * Handles wp hooks
	 */
	protected function _init() {
		add_filter( 'tva_admin_localize', array( $this, 'get_connected_email_apis' ) );
		add_filter( 'tva_admin_localize', array( $this, 'get_admin_data_localization' ) );
		add_filter( 'tva_admin_localize', array( $this, 'get_shortcodes' ) );
		add_filter( 'tva_admin_localize', array( $this, 'get_triggers' ) );
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
		add_action( 'tva_prepare_new_user_email_template', array( $this, 'prepare_new_user_email_template' ) );
		add_action( 'tvd_after_create_wordpress_account', array( $this, 'after_create_wordpress_account' ), 10, 2 );
		add_filter( 'pre_wp_mail', array( $this, 'maybe_send_mail_via_api' ), 10, 2 );

		add_shortcode( 'first_name', function () {

			if ( $this->_user ) {
				$first_name = $this->_user->first_name;

				if ( empty( $first_name ) ) {

					if ( ! empty( $_POST['name'] ) ) {
						/**
						 * This is a bit ugly
						 * Creating a user can be from apprentice or from a lead generation with WP connection form (from TAR)
						 *
						 * When created via lead generation form from TAR (with function register_new_user) the first name of the user is updated after the user has been created with function wp_update_user()
						 * The send user email trigger is fired on user creation.
						 */
						$first_name = sanitize_text_field( $_POST['name'] );
					} else {
						$first_name = $this->_user->user_email;
					}
				}

				return $first_name;
			}

			return null;
		} );

		add_shortcode( 'user_name', function () {
			return $this->_user->user_login;
		} );

		add_shortcode( 'user_pass', function ( $attributes ) {
			/* if the password has been generated, include it in the email message */
			if ( ! empty( $GLOBALS['tva_user_pass_generated'] ) ) {
				return $this->_user->user_pass;
			}

			/* if not, the password must have been chosen by the user, return the `if_user_provided` message */
			if ( empty( $attributes['if_user_provided'] ) ) {
				$attributes['if_user_provided'] = 'The password you chose during registration';
			}

			return $attributes['if_user_provided'];
		} );

		add_shortcode( 'login_button', function ( $attributes, $content ) {
			return '<a target="_blank" href="' . $this->_get_login_url() . '" style="color: #ffffff; border-radius: 4px; background-color: #236085; display: inline-block; padding: 5px 40px;">' . $content . '</a>';
		} );

		add_shortcode( 'login_link', function ( $attributes, $content ) {
			return '<a target="_blank" href="' . $this->_get_login_url() . '">' . $content . '</a>';
		} );

		add_shortcode( 'site_name', function () {
			return get_bloginfo( 'name' );
		} );

		add_filter( 'tcb_api_subscribe_data_instance', array( $this, 'trigger_wp_new_registration' ), 10, 3 );

		/**
		 * When sending emails from automator or from the default register page via WordPress,
		 * the email templates needs to be changed in case the the backend option is on
		 *
		 * 0 priority is set to hook before the WP hook
		 */
		add_action( 'register_new_user', static function () {
			$email_template = tva_email_templates()->check_templates_for_trigger( 'wordpress' );

			if ( false !== $email_template ) {
				tva_email_templates()->trigger_process( $email_template );
			}
		}, 0 );
	}

	/**
	 * Checks if there is set a Login Page and returns its URL
	 * - otherwise returns wp login url
	 *
	 * @return string
	 */
	protected function _get_login_url() {

		$login_url  = wp_login_url();
		$login_page = tva_get_settings_manager()->get_setting( 'login_page' );

		if ( $login_page ) {
			$login_url = get_permalink( $login_page );
		}

		return $login_url;
	}

	/**
	 * Hooks into `wp_new_user_notification_email` with specified template
	 *
	 * @param array $email_template
	 */
	public function prepare_new_user_email_template( $email_template ) {

		add_filter( 'wp_mail_content_type', function () {
			return "text/html";
		} );

		add_filter( 'wp_mail_from_name', static function ( $from_name ) use ( $email_template ) {

			if ( ! empty( $email_template['from_name'] ) ) {
				$from_name = $email_template['from_name'];
			}

			return $from_name;
		}, PHP_INT_MAX );

		add_filter( 'wp_new_user_notification_email', function ( $email_data, $user, $blog_name ) use ( $email_template ) {
			/** @var WP_User $user */
			$this->_user = $user;

			if ( empty( $email_template['user_pass'] ) ) {
				$GLOBALS['tva_user_pass_generated'] = true;
				$new_pass                           = wp_generate_password( 12, false );
				wp_set_password( $new_pass, $user->ID );
			} else {
				$new_pass = $email_template['user_pass'];
			}

			$this->_user->user_pass = $new_pass; //used on generating email body

			$email_data['subject'] = do_shortcode( $email_template['subject'] );
			$email_data['message'] = do_shortcode( nl2br( $email_template['body'] ) );

			return $email_data;
		}, 10, 3 );
	}

	/**
	 * Registers required rest API endpoints
	 */
	public function rest_api_init() {
		register_rest_route( 'tva/v1', '/emailTemplate', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'save_template' ),
			'permission_callback' => array( $this, 'permissions_check' ),
		) );
	}


	/**
	 * Checks if there are templates saved for a specified trigger slug
	 *
	 * @param string $trigger_slug
	 *
	 * @return bool|array of template
	 */
	public function check_templates_for_trigger( $trigger_slug ) {

		foreach ( $this->_templates as $tpl_slug => $template ) {
			if ( ! empty( $template['triggers'] ) && in_array( $trigger_slug, $template['triggers'] ) ) {
				return $template;
			}
		}

		return false;
	}

	/**
	 * Loops through all triggers and if there is a template set for it then return the template
	 * otherwise return false
	 *
	 * @return array|bool
	 */
	public function check_template_for_any_trigger() {

		foreach ( array( 'thrivecart', 'sendowl', 'wordpress' ) as $trigger ) {
			$template = $this->check_templates_for_trigger( $trigger );
			if ( false !== $template ) {
				return $template;
			}
		}

		return false;
	}

	/**
	 * Callback for saving a template API endpoint
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return true
	 */
	public function save_template( $request ) {

		$this->_templates[ $request->get_param( 'slug' ) ] = array(
			'subject'   => $request->get_param( 'subject' ),
			'from_name' => (string) $request->get_param( 'from_name' ),
			'body'      => $request->get_param( 'body' ),
			'triggers'  => $request->get_param( 'triggers' ),
		);

		$this->_save_option();

		return true;
	}


	/**
	 * Check if a given request has access to the product
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function permissions_check( $request ) {
		return TVA_Product::has_access();
	}

	/**
	 * Localizes triggers
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function get_triggers( $data ) {

		$data['emailTriggers'] = array();

		$data['emailTriggers']['sendowl'] = array(
			'slug'        => 'sendowl',
			'description' => esc_html__( 'SendOwl - new account created on registration page (during purchase flow)', 'thrive-apprentice' ),
		);

		$data['emailTriggers']['thrivecart'] = array(
			'slug'        => 'thrivecart',
			'description' => esc_html__( 'ThriveCart - new account created after purchase', 'thrive-apprentice' ),
		);

		$data['emailTriggers']['wordpress'] = array(
			'slug'        => 'wordpress',
			'description' => esc_html__( 'When a user registers to create a new free account', 'thrive-apprentice' ),
		);

		return $data;
	}

	/**
	 * Localizes shortcodes
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function get_shortcodes( $data ) {

		$data['emailShortcodes'] = array();

		$data['emailShortcodes']['firstName'] = array(
			'slug'  => 'firstName',
			'label' => esc_html__( 'First name' ),
			'text'  => '[first_name]',
		);

		$data['emailShortcodes']['username'] = array(
			'slug'  => 'username',
			'label' => esc_html__( 'Username' ),
			'text'  => '[user_name]',
		);

		$data['emailShortcodes']['password'] = array(
			'slug'  => 'password',
			'label' => esc_html__( 'Password' ),
			'text'  => '[user_pass if_user_provided="The password you chose during registration"]',
		);

		$data['emailShortcodes']['loginButton'] = array(
			'slug'  => 'loginButton',
			'label' => esc_html__( 'Login button' ),
			'text'  => '[login_button]' . esc_html__( 'Log into your account', 'thrive-apprentice' ) . '[/login_button]',
		);

		$data['emailShortcodes']['loginLink'] = array(
			'slug'  => 'loginLink',
			'label' => esc_html__( 'Login link' ),
			'text'  => '[login_link]' . esc_html__( 'Log into your account', 'thrive-apprentice' ) . '[/login_link]',
		);

		$data['emailShortcodes']['siteName'] = array(
			'slug'  => 'siteName',
			'label' => esc_html__( 'Site name' ),
			'text'  => '[site_name]',
		);

		return $data;
	}

	/**
	 * Gets a template's body by template's name
	 * - from DB if exists or from file as default
	 *
	 * @param string $tpl_slug
	 *
	 * @return string
	 */
	private function _get_template_body( $tpl_slug ) {

		/**
		 * default body
		 */
		ob_start();
		include TVA_Const::plugin_path( '/admin/views/template/emailTemplates/bodies/' ) . $tpl_slug . '.phtml';
		$body = ob_get_contents();
		ob_end_clean();

		/**
		 * DB saved body
		 */
		if ( ! empty( $this->_templates[ $tpl_slug ]['body'] ) ) {
			$body = $this->_templates[ $tpl_slug ]['body'];
		}

		return $body;
	}

	/**
	 * Based on template's name returns a string as email subject
	 *
	 * @param string $tpl_slug
	 *
	 * @return string
	 */
	private function _get_template_subject( $tpl_slug ) {

		$subject = 'Your account has been created';

		if ( ! empty( $this->_templates[ $tpl_slug ]['subject'] ) ) {
			$subject = $this->_templates[ $tpl_slug ]['subject'];
		}

		return $subject;
	}

	private function _get_template_from_name( $tpl_slug ) {
		$from_name = get_bloginfo( 'name' );

		if ( ! empty( $this->_templates[ $tpl_slug ]['from_name'] ) ) {
			$from_name = $this->_templates[ $tpl_slug ]['from_name'];
		}

		return $from_name;
	}

	/**
	 * Gets a list of trigger slugs for which a template is activated
	 *
	 * @param string $tpl_slug
	 *
	 * @return array
	 */
	private function _get_template_triggers( $tpl_slug ) {

		/**
		 * by default thrivecart trigger has to be selected
		 */
		if ( empty( $this->_templates[ $tpl_slug ]['triggers'] ) ) {
			$this->_templates[ $tpl_slug ]['triggers'] = array(
				'thrivecart',
			);
		}

		return $this->_templates[ $tpl_slug ]['triggers'];
	}

	/**
	 * Localization of available email services
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function get_connected_email_apis( $data ) {

		$email_connection_instance = Thrive_Dash_List_Manager::connectionInstance( 'email' );

		$data['connected_email_providers'] = $email_connection_instance->get_connected_email_providers();

		return $data;
	}

	/**
	 * Checks if the system should send emails through Emails APIs.
	 *
	 * The system will send emails through APIs if the option is selected in the database and the email contains no attachments.
	 * If the API doesn't return a successful response, it sends emails through WordPress
	 *
	 * @param null|mixed $return
	 * @param array      $attrs
	 *
	 * @return mixed
	 */
	public function maybe_send_mail_via_api( $return, $attrs = array() ) {
		$email_service = tva_get_setting( 'email_service' );

		$send_via_api = false;
		$from_name    = get_bloginfo( 'name' );
		if ( did_action( 'tva_prepare_new_user_email_template' ) > 0 ) {
			$send_via_api = true;
			if ( ! empty( $this->_templates['newAccount'] ) && ! empty( $this->_templates['newAccount']['from_name'] ) ) {
				$from_name = $this->_templates['newAccount']['from_name'];
			}
		}

		if ( $send_via_api && empty( $attrs['attachments'] ) && ! empty( $email_service ) && $email_service !== 'own_site' ) {
			$api  = Thrive_List_Manager::connectionInstance( $email_service );
			$data = array(
				'html_content' => $attrs['message'],
				'text_content' => strip_tags( $attrs['message'] ),
				'subject'      => $attrs['subject'],
				'from_name'    => $from_name,
				'from_email'   => get_option( 'admin_email' ),
				'bcc'          => '',
				'cc'           => '',
				'emails'       => is_string( $attrs['to'] ) ? array( $attrs['to'] ) : $attrs['to'],
			);

			$sent = $api->sendMultipleEmails( $data );

			if ( $sent === true ) {
				/**
				 * If the email API returns success -> we stop emails sending through WordPress
				 */
				$return = false;
			}
		}

		return $return;
	}

	/**
	 * Localizes required data for admin
	 *
	 * @param array $data
	 *
	 * @return array mixed
	 */
	public function get_admin_data_localization( $data ) {

		$tpl_slug                            = 'newAccount';
		$data['emailTemplates']              = array();
		$data['emailTemplates'][ $tpl_slug ] = array(
			'slug'      => $tpl_slug,
			'name'      => esc_html__( 'New Account Created', 'thrive-apprentice' ),
			'subject'   => $this->_get_template_subject( $tpl_slug ),
			'from_name' => $this->_get_template_from_name( $tpl_slug ),
			'body'      => $this->_get_template_body( $tpl_slug ),
			'triggers'  => $this->_get_template_triggers( $tpl_slug ),
		);

		return $data;
	}

	/**
	 * Singleton instance
	 *
	 * @return TVA_Email_Templates
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Call this when necessary:
	 * - new Thrive Cart Orders takes place
	 * - new account was created on registration page
	 * - new user is registered over WP Connection on LG Element
	 * - executes do_action() with a specified template for later process
	 *
	 * @param array $email_template
	 *
	 * @see prepare_new_user_email_template()
	 *
	 */
	public function trigger_process( $email_template ) {
		do_action( 'tva_prepare_new_user_email_template', $email_template );
	}

	/**
	 * On LG Submit if the connection is Wordpress checks if there is an email template
	 * triggered for Wordpress connection, if yes then execute trigger_process()
	 * and after the user is saved hooks onto `tvd_after_create_wordpress_account` action to send new registration email
	 *
	 * @param array                                 $data from LG Element
	 * @param Thrive_Dash_List_Connection_Wordpress $connection_instance
	 * @param mixed                                 $list
	 *
	 * @return mixed
	 */
	public function trigger_wp_new_registration( $data, $connection_instance, $list ) {

		if ( false === $connection_instance instanceof Thrive_Dash_List_Connection_Wordpress ) {
			return $data;
		}

		//if there is any email template set for new wp user registration
		$email_template = tva_email_templates()->check_templates_for_trigger( 'wordpress' );
		if ( false !== $email_template ) {
			if ( ! empty( $data['password'] ) ) {
				$email_template['user_pass'] = $data['password'];
			}
			tva_email_templates()->trigger_process( $email_template );
		}

		return $data;
	}

	/**
	 * When password field is sent from LG notification process is not triggered so we have to do it here
	 *
	 * @param WP_User $user
	 * @param array   $arguments
	 */
	public function after_create_wordpress_account( $user, $arguments ) {

		$email_template = tva_email_templates()->check_templates_for_trigger( 'wordpress' );

		if ( false !== $email_template && isset( $arguments['password'] ) ) {
			wp_send_new_user_notifications( $user->ID );
		}
	}
}

global $tva_email_templates;

/**
 * Method wrapper for singleton
 *
 * @return TVA_Email_Templates
 */
function tva_email_templates() {

	global $tva_email_templates;

	$tva_email_templates = TVA_Email_Templates::get_instance();

	return $tva_email_templates;
}

tva_email_templates();

<?php

/**
 * Thrive Themes - https://thrivethemes.com
 *
 * @package thrive-apprentice
 */

namespace TVA\TTB;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

/**
 * Class Skin
 *
 * @package TVA\TTB
 *
 * @property-read string $tag                tag
 * @property-read string $thumb              thumbnail URL
 * @property-read string $name               term name
 * @property-read string $description        term description
 * @property-read int    $term_id            ID of term
 * @property-read int    $logo               attachment ID of the logo selected for this skin
 * @property-read bool   $inherit_typography whether or not to inherit the typography from the theme
 *
 */
class Skin extends \Thrive_Skin implements \JsonSerializable {
	protected static $_instances = [];

	/**
	 * @var null Cache - General No Access templates
	 */
	protected static $_cache_no_access = null;

	/**
	 * Modified from Thrive_Skin
	 * Contains prefix for style file
	 *
	 * @var string
	 */
	protected $style_file_prefix = 'apprentice-template';

	/**
	 * Override the option from the Theme Skin
	 *
	 * @return string|void
	 */
	public function get_template_style_option_name() {
		return 'thrive_apprentice_template_style';
	}

	/**
	 * General singleton implementation for class instance that also requires an id
	 *
	 * @param int $id
	 *
	 * @return static
	 */
	public static function instance_with_id( $id = 0 ) {
		if ( ! isset( static::$_instances[ $id ] ) ) {
			static::$_instances[ $id ] = new static( $id );
		}

		return static::$_instances[ $id ];
	}

	/**
	 * Thrive_Skin constructor. Modified to also support WP_Term parameter
	 *
	 * @param int|string|\WP_Term $skin
	 */
	public function __construct( $skin ) {
		if ( $skin instanceof \WP_Term ) {
			$skin = $skin->term_id;
		}
		parent::__construct( $skin );
	}

	/**
	 * Serialization needed for admin CRUD
	 *
	 * @return array|mixed
	 */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		$default_skin_id = Main::get_default_skin_id();
		$data            = $this->term->to_array();

		if ( \TVA\TTB\Check::is_end_user_site() ) {
			$this->sanity_check();
		}

		return array_merge( $data, [
			'is_active'          => $default_skin_id === $this->term->term_id,
			'legacy'             => 0,
			'thumb'              => $this->thumb,
			'templates'          => Skin_Template::localize_all( $this->term->term_id ),
			'inherit_typography' => (bool) $this->inherit_typography,
			'typography'         => $this->get_active_typography( 'array' ),
		] );
	}

	/**
	 * Magic (meta) getter. Adds a tva_ prefix for the meta key before querying
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function __get( $name ) {

		if ( method_exists( $this, 'get_' . $name ) ) {
			$method_name = 'get_' . $name;

			return $this->$method_name();
		}

		if ( isset( $this->term->$name ) ) {
			return $this->term->$name;
		}

		$name = 'tva_' . str_replace( 'tva_', '', $name );

		return $this->get_meta( $name );
	}

	/**
	 * Clone the current skin
	 *
	 * @return int the new skin ID
	 */
	public function duplicate() {
		$name     = 'Copy of ' . $this->name;
		$new_skin = new static( Main::create_skin( $name, null, false ) );

		/* duplicate the meta fields from the source to the new skin */
		$new_skin->duplicate_meta( $this->ID );

		/* create templates */
		Default_Data::create_skin_templates( $new_skin->ID, $this->ID );

		/* create default typography */
		Default_Data::create_skin_typographies( $new_skin->ID, $this->ID );

		return $new_skin->ID;
	}

	/**
	 * Get all the meta fields for a skin
	 *
	 * @return array
	 */
	public static function meta_fields() {
		return parent::meta_fields() + [
				'thrive_scope'           => 'tva',
				'tva_thumb'              => '',
				'tva_inherit_typography' => true,
			];
	}

	/**
	 * Ensure that this skin has the correct `thrive_scope` meta assigned
	 */
	public function ensure_scope() {
		update_term_meta( $this->ID, 'thrive_scope', 'tva' );
	}

	/**
	 * Set skin palettes
	 *
	 * @param array $palettes
	 *
	 * @return Skin
	 */
	public function set_palettes( $palettes ) {
		$this->set_meta( static::SKIN_META_PALETTES_V2, $palettes );

		return $this;
	}

	/**
	 * Check if a skin has TVA scope
	 *
	 * @param $skin_id
	 *
	 * @return bool
	 */
	public static function has_tva_scope( $skin_id ) {
		return get_term_meta( $skin_id, 'thrive_scope', true ) === 'tva';
	}

	/**
	 * This function should be overridden with empty content because we do not want the normalize logic for skin to be applied also here
	 */
	public function normalize_palettes() {
		//Nothing should happen here
	}

	/**
	 * We always return the template string here
	 *
	 * Overrides the Builder Skin function
	 *
	 * @param array  $templates
	 * @param string $type
	 *
	 * @return array|mixed
	 */
	public function filter_templates( $templates, $type = '' ) {
		return $templates;
	}

	/**
	 * @return string
	 */
	public function css( $include_theme_master = false ) {

		$data = '';

		if ( empty( tva_palettes()->get_master_hsl() ) ) {
			tva_palettes()->reset_master_hsl();
		}

		if ( tva_palettes()->has_palettes() && ! empty( $this->get_meta( static::SKIN_META_PALETTES_V2 ) ) ) {

			$palette = tva_palettes()->get_palette();

			foreach ( $palette as $variable_id => $variable ) {

				$color_name = '--tva-skin-color-' . $variable['id'];
				if ( ! empty( $variable['hsla_code'] ) && ! empty( $variable['hsla_vars'] ) && is_array( $variable['hsla_vars'] ) ) {
					$data .= $color_name . ':' . $variable['hsla_code'] . ';';

					foreach ( $variable['hsla_vars'] as $var => $css_variable ) {
						$data .= $color_name . '-' . $var . ':' . $css_variable . ';';
					}
				} else {
					$data .= $color_name . ':' . $variable['color'] . ';';

					if ( function_exists( 'tve_rgb2hsl' ) && function_exists( 'tve_print_color_hsl' ) ) {
						$data .= tve_print_color_hsl( $color_name, tve_rgb2hsl( $variable['color'] ) );
					}
				}
			}
		}

		if ( $include_theme_master && \Thrive_Theme::is_active() ) {
			$theme_master_variable = thrive_palettes()->get_master_hsl();

			$data .= str_replace( '--tcb-main-master', '--tcb-theme-main-master', tve_prepare_master_variable( array( 'hsl' => $theme_master_variable ) ) );
		}

		$share_ttb_color = tva_get_settings_manager()->factory( 'share_ttb_color' )->get_value();
		if ( ! empty( $share_ttb_color ) ) {
			$master_variable = [
				'h' => 'var(--tcb-theme-main-master-h)',
				's' => 'var(--tcb-theme-main-master-s)',
				'l' => 'var(--tcb-theme-main-master-l)',
				'a' => 'var(--tcb-theme-main-master-a)',
			];
		} else {
			$master_variable = tva_palettes()->get_master_hsl();
		}

		$general_master_variable = tve_prepare_master_variable( array( 'hsl' => $master_variable ) );
		$ta_master_variable      = str_replace( '--tcb-main-master', '--tva-main-master', $general_master_variable );

		$data .= $general_master_variable;
		$data .= $ta_master_variable;

		return $data;
	}

	/**
	 * Get an array of Skin template objects that match primary, secondary and variable template fields
	 *
	 * @param string|null $primary
	 * @param string|null $secondary
	 * @param string|null $variable
	 * @param array       $query_args extra query args
	 *
	 * @return Skin_Template[]
	 */
	public function get_templates_by_type( $primary = null, $secondary = null, $variable = null, $query_args = [] ) {
		$filters = array_filter( compact( 'primary', 'secondary', 'variable' ) );

		$args = [
			'post_type'      => THRIVE_TEMPLATE,
			'posts_per_page' => - 1,
			'tax_query'      => [ $this->build_skin_query_params() ],
		];

		if ( $filters ) {
			$args['meta_query'] = [
				'relation' => 'AND',
			];
			foreach ( $filters as $meta_field => $meta_value ) {
				$args['meta_query'][] = [
					'key'   => "{$meta_field}_template",
					'value' => $meta_value,
				];
			}
		}
		if ( isset( $query_args['default'] ) ) {
			$args['meta_query'] [] = [
				'key'   => 'default',
				'value' => (int) $query_args['default'],
			];
		}
		if ( isset( $query_args['format'] ) ) {
			$args['meta_query'] [] = [
				'key'   => 'format',
				'value' => $query_args['format'],
			];
		}

		return array_map( static function ( $post ) {
			return new Skin_Template( $post->ID );
		}, get_posts( $args ) );
	}

	/**
	 * Get a default template for the $content_type parameter
	 *
	 * @param string $content_type
	 *
	 * @return Skin_Template|null
	 */
	public function get_default_template( $content_type = 'lesson' ) {
		$primary   = THRIVE_SINGULAR_TEMPLATE;
		$secondary = null;
		$variable  = null;
		$params    = [ 'default' => 1 ];

		switch ( $content_type ) {
			case 'lesson':
				$secondary        = \TVA_Const::LESSON_POST_TYPE;
				$params['format'] = 'standard';
				break;
			case 'module':
				$secondary = \TVA_Const::MODULE_POST_TYPE;
				break;
			case 'school':
				$primary   = THRIVE_HOMEPAGE_TEMPLATE;
				$secondary = \TVA_Const::COURSE_POST_TYPE;
				break;
			case 'course':
				$primary   = THRIVE_ARCHIVE_TEMPLATE;
				$secondary = \TVA_Const::COURSE_TAXONOMY;
				break;
		}
		$templates = $this->get_templates_by_type( $primary, $secondary, $variable, $params );

		if ( empty( $templates ) ) {
			$templates = $this->get_templates_by_type( $primary, $secondary, $variable );
		}

		return isset( $templates[0] ) ? $templates[0] : null;
	}

	/**
	 * Get the skin typography - always return the first typography - TA skins have only one typography
	 *
	 * @param string $output
	 *
	 * @return mixed
	 */
	public function get_active_typography( $output = 'ids' ) {
		/* also allow singular */
		if ( $output === 'id' ) {
			$output = 'ids';
		} elseif ( $output === 'object' ) {
			$output = 'objects';
		}
		$typographies = $this->get_typographies( $output );

		switch ( $output ) {
			case 'ids':
				$default = 0;
				break;
			case 'array':
				$default = [];
				break;
			case 'objects':
			default:
				$default = new \Thrive_Typography( 0 );
				break;
		}

		return isset( $typographies[0] ) ? $typographies[0] : $default;
	}

	/**
	 * Getter / setter for inherit_typography field
	 *
	 * @param null $value
	 *
	 * @return mixed
	 */
	public function inherit_typography( $value = null ) {
		if ( $value === null ) {
			return $this->inherit_typography;
		}

		update_term_meta( $this->ID, 'tva_inherit_typography', (bool) $value );
	}

	/**
	 * Returns the skin thumb
	 *
	 * @return string
	 */
	private function get_thumb() {

		/**
		 * Allow custom thumb location for skins. If any of the filter implementations return a non-empty string, it will be used as thumb url
		 *
		 * @param string $thumb_url
		 * @param Skin   $skin skin instance
		 */
		$thumb_url = apply_filters( 'tva_skin_thumb_url', '', $this );

		if ( $thumb_url !== '' ) {
			return $thumb_url;
		}

		$host = '//landingpages.thrivethemes.com';

		if ( defined( 'TCB_CLOUD_API_LOCAL' ) ) {
			$host = str_replace( '/cloud-api/index-api.php', '', TCB_CLOUD_API_LOCAL );
		}

		$thumb = (string) $this->get_meta( 'tva_thumb' );

		return rtrim( $host, '/' ) . '/data/skins/thumbnails/' . ( $thumb ? $thumb : 'thumb-' . $this->tag . '.png' );
	}

	/**
	 * Mark this skin as active and deactivate the currently active skin
	 *
	 * @return Skin
	 */
	public function activate() {
		Main::set_default_skin_id( $this->ID );
		Main::set_use_builder_templates();
		/* make sure the "load_scripts" setting is turned ON */
		tva_get_settings_manager()->save_setting( 'load_scripts', 1 );

		return $this;
	}

	/**
	 * Checks if the skin is valid for localization
	 * Contains logic that was added after the visual builder release
	 *
	 * Ex: auto-downloads General No Access template if it doesn't exist
	 */
	public function sanity_check() {

		$check_general_no_access = new \WP_Query( [
			'post_type'  => THRIVE_TEMPLATE,
			'tax_query'  => [ $this->build_skin_query_params() ],
			'meta_query' => [
				'relation' => 'AND',
				[
					'key'     => THRIVE_PRIMARY_TEMPLATE,
					'value'   => THRIVE_SINGULAR_TEMPLATE,
					'compare' => '=',
				],
				[
					'key'     => THRIVE_SECONDARY_TEMPLATE,
					'value'   => \TVA_Const::NO_ACCESS_POST,
					'compare' => '=',
				],
			],
		] );

		/**
		 * Try to download it, if the template has not been found
		 */
		if ( $check_general_no_access->found_posts === 0 ) {

			if ( static::$_cache_no_access === null ) {
				static::$_cache_no_access = \Thrive_Theme_Cloud_Api_Factory::build( 'templates' )->get_items( [
					'filters' => [
						'primary'   => 'singular',
						'secondary' => 'tva_post_no_access',
					],
				] );
			}

			if ( is_array( static::$_cache_no_access ) && ! empty( static::$_cache_no_access ) ) {
				$current = reset( static::$_cache_no_access );
				if ( ! empty( $current['id'] ) ) {

					/**
					 * Fix issues for downloading the general no access templates and assigning it to the skin
					 */
					$_REQUEST['tva_skin_id'] = $this->ID;

					$request = new \WP_REST_Request( \WP_REST_Server::CREATABLE, '/tva/v1/templates' );
					$request->set_header( 'content-type', 'application/json' );
					$request->set_body_params( [
						'tva_skin_id'  => $this->ID,
						'post_title'   => 'Restricted Site Content',
						'inherit_from' => $current['id'],
						'meta_input'   => [
							THRIVE_PRIMARY_TEMPLATE   => THRIVE_SINGULAR_TEMPLATE,
							THRIVE_SECONDARY_TEMPLATE => \TVA_Const::NO_ACCESS_POST,
						],
					] );

					try {
						rest_get_server()->dispatch( $request );
					} catch ( \Exception $exception ) {
						//do nothing
						return false;
					}
				}
			}
		}
	}
}

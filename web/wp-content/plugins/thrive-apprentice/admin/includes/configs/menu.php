<?php

$index_page            = tva_get_settings_manager()->factory( 'index_page' )->get_value();
$visual_editor_welcome = tva_get_settings_manager()->factory( 'visual_editor_welcome' )->get_value();

return array(
	'home'            => array(
		'slug'  => 'home',
		'route' => '#home',
		'icon'  => 'app-logo',
		'label' => esc_html__( 'Home', 'thrive-apprentice' ),
	),
	'wizard'          => array(
		'slug'   => 'wizard',
		'route'  => '#wizard',
		'hidden' => true,
		'icon'   => 'icon-wizard',
		'label'  => esc_html__( 'Wizard', 'thrive-apprentice' ),
	),
	'courses'         => array(
		'slug'     => 'courses',
		'route'    => '#courses',
		'icon'     => 'icon-courses',
		'label'    => esc_html__( 'Courses', 'thrive-apprentice' ),
		'sections' => array(
			array(
				'simple' => true,
				'slug'     => 'settings',
				'label'    => esc_html__( 'Settings', 'thrive-apprentice' ),
			),
		),
		'items'    => array(
			'courses'        => array(
				'slug'  => 'courses',
				'route' => '#courses',
				'icon'  => 'all-courses-icon',
				'label' => esc_html__( 'All courses', 'thrive-apprentice' ),
			),
			'course-topics'  => array(
				'slug'    => 'topics',
				'section' => 'settings',
				'route'   => '#courses/topics',
				'icon'    => 'course-topics-icon',
				'label'   => esc_html__( 'Course topics', 'thrive-apprentice' ),
			),
			'course-bundles' => array(
				'hidden'  => \TVA\Product_Migration::is_debug_on() === 0,
				'slug'    => 'bundles',
				'route'   => '#courses/bundles',
				'section' => 'settings',
				'icon'    => 'bundle-small',
				'label'   => esc_html__( 'Course bundles', 'thrive-apprentice' ),
			),
		),
	),
	'customers'       => array(
		'slug'  => 'customers',
		'route' => '#customers',
		'icon'  => 'icon-customers',
		'label' => esc_html__( 'Customers', 'thrive-apprentice' ),
		'items' => array(),
	),
	'design'          => array(
		'slug'          => 'design',
		'route'         => empty( $visual_editor_welcome ) ? '#design-welcome' : '#design',
		'icon'          => 'icon-design',
		'label'         => esc_html__( 'Design', 'thrive-apprentice' ),
		'dynamic_items' => 1,
	),
	'products'        => array(
		'slug'  => 'products',
		'route' => '#products',
		'icon'  => 'icon-products',
		'label' => esc_html__( 'Products', 'thrive-apprentice' ),
		'items' => array(
			'products' => array(
				'hidden' => true,
				'slug'   => 'products',
				'route'  => '#products',
				'icon'   => '',
				'label'  => esc_html__( 'Products', 'thrive-apprentice' ),
			),
		),
	),
	'settings'        => array(
		'slug'  => 'settings',
		'route' => '#settings',
		'icon'  => 'icon-settings',
		'label' => esc_html__( 'Settings', 'thrive-apprentice' ),
		'items' => array(
			'settings'           => array(
				'slug'  => 'settings',
				'route' => '#settings',
				'icon'  => 'settings-icon',
				'label' => esc_html__( 'General settings', 'thrive-apprentice' ),
			),
			'sendowl'            => array(
				'slug'     => 'sendowl',
				'route'    => '#settings/sendowl',
				'disabled' => TVA_SendOwl::is_connected() ? 0 : esc_attr__( 'You need to have an active SendOwl API Connection to use this menu.', 'thrive-apprentice' ),
				'icon'     => 'sendowl-logo',
				'label'    => esc_html__( 'SendOwl', 'thrive-apprentice' ),
			),
			'email-templates'    => array(
				'slug'  => 'email-templates',
				'route' => '#settings/email-templates',
				'icon'  => 'email-templates-icon',
				'label' => esc_html__( 'Email templates', 'thrive-apprentice' ),
			),
			'labels'             => array(
				'slug'  => 'translations',
				'route' => '#settings/translations/access-restrictions',
				'icon'  => 'labels-translations-icon',
				'label' => esc_html__( 'Labels & translations', 'thrive-apprentice' ),
				'items' => array(
					'access-restrictions'    => array(
						'slug'  => 'access-restrictions',
						'route' => '#settings/translations/access-restrictions',
						'label' => esc_html__( 'Access restrictions', 'thrive-apprentice' ),
					),
					'call-to-action-buttons' => array(
						'slug'  => 'call-to-action-buttons',
						'route' => '#settings/translations/call-to-action-buttons',
						'label' => esc_html__( 'Call to action buttons', 'thrive-apprentice' ),
					),
					'course-content-types'   => array(
						'slug'  => 'course-content-types',
						'route' => '#settings/translations/course-content-types',
						'label' => esc_html__( 'Course content types', 'thrive-apprentice' ),
					),
					'course-navigation'      => array(
						'slug'  => 'course-navigation',
						'route' => '#settings/translations/course-navigation',
						'label' => esc_html__( 'Course navigation', 'thrive-apprentice' ),
					),
					'course-structure'       => array(
						'slug'  => 'course-structure',
						'route' => '#settings/translations/course-structure',
						'label' => esc_html__( 'Course structure', 'thrive-apprentice' ),
					),
					'course-progress'        => array(
						'slug'  => 'course-progress',
						'route' => '#settings/translations/course-progress',
						'label' => esc_html__( 'Course progress', 'thrive-apprentice' ),
					),
				),
			),
			'access-restriction' => array(
				'slug'  => 'access-restriction',
				'route' => '#settings/access-restriction',
				'icon'  => 'login-icon',
				'label' => esc_html__( 'Login & access restriction', 'thrive-apprentice' ),
			),
			'logs'               => array(
				'slug'  => 'logs',
				'route' => '#settings/logs',
				'icon'  => 'logs-icon',
				'label' => esc_html__( 'Logs', 'thrive-apprentice' ),
			),
			'api-keys'           => array(
				'slug'  => 'api-keys',
				'route' => '#settings/api-keys',
				'icon'  => 'api-key-icon',
				'label' => esc_html__( 'Api keys', 'thrive-apprentice' ),
			),
		),
	),
	'course-homepage' => array(
		'slug'     => 'course-homepage',
		'href'     => tva_get_settings_manager()->factory( 'index_page' )->get_link(),
		'disabled' => empty( $index_page ) ? esc_attr__( 'You need to have defined a course page', 'thrive-apprentice' ) : 0,
		'icon'     => 'icon-eye',
		'label'    => esc_html__( 'Preview', 'thrive-apprentice' ),
		'items'    => array(),
	),
);

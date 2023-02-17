<?php

/**
 * Thrive Themes - https://thrivethemes.com
 *
 * @package thrive-apprentice
 */

namespace TVA\Drip;

use TVA\Product;
use TVA\TTB\Check;

class Drip {

	/**
	 * @return array[]
	 *
	 * @codeCoverageIgnore
	 */
	public static function get_campaign_types() {
		$types = [
			[
				'type'     => 'evergreen-repeating',
				'label'    => __( 'Evergreen repeating', \TVA_Const::T ),
				'desc'     => __( 'Unlock content at consistent intervals for each student', \TVA_Const::T ),
				'longdesc' => __( 'Evergreen repeating campaigns unlock content at consistent intervals, such as one lesson or module per week. Each student has their own unlock timeline depending on the trigger you define (when the user purchases the product, or starts the course).', \TVA_Const::T ),
			],
			[
				'type'     => 'scheduled-repeating',
				'label'    => __( 'Scheduled repeating', \TVA_Const::T ),
				'desc'     => __( 'Unlock content at consistent intervals after a scheduled start date', \TVA_Const::T ),
				'longdesc' => __( 'Scheduled repeating campaigns unlock content at consistent intervals, such as one lesson or module per week. Unlike evergreen campaigns, the content is unlocked at the same time for everyone starting from the scheduled date and time you choose.', \TVA_Const::T ),
			],
			[
				'type'     => 'day-of-week',
				'label'    => __( 'Day of the week or month', \TVA_Const::T ),
				'desc'     => __( 'Unlock content on a specific week day or day of the month', \TVA_Const::T ),
				'longdesc' => __( 'Unlock content on a specific day of the week such as every Monday, a day of the month such as every second Thursday, or a monthly calendar date such as the 15th of each month.', \TVA_Const::T ),
			],
			[
				'type'     => 'live-launch',
				'label'    => __( 'Drip on specific dates', \TVA_Const::T ),
				'desc'     => __( 'Unlock content on specific calendar dates that you can customize', \TVA_Const::T ),
				'longdesc' => __( 'This campaign gives you the freedom to unlock content at custom intervals. For example, you may want to unlock module 1 on the 12th February and then module 2 on the 21st February. You can choose the exact unlock dates for each piece of content in your course.', \TVA_Const::T ),
			],
			[
				'type'          => 'custom',
				'label'         => __( 'Start from scratch', \TVA_Const::T ),
				'details_label' => __( 'Custom drip schedule', \TVA_Const::T ),
				'desc'          => __( 'Choose your own trigger and unlock schedule', \TVA_Const::T ),
				'longdesc'      => __( 'Build your campaign from scratch. Choose your trigger event, set unlock intervals or enable custom unlock conditions for all of the content in your course', \TVA_Const::T ),
			],
			[
				'type'     => 'automator',
				'disabled' => ! Check::automator(),
				'label'    => __( 'Thrive Automator Unlock', \TVA_Const::T ),
				'desc'     => __( 'Use custom event triggers and 3rd party integrations to unlock content', \TVA_Const::T ),
				'longdesc' => __( 'The Thrive Automator Unlock schedule allows you to lock your content without setting unlock rules. You can then create custom automations in Thrive Automator based on website events or 3rd party integrations to unlock each piece of content.', \TVA_Const::T ),
			],
		];

		return $types;
	}

	/**
	 * @return array[]
	 * @codeCoverageIgnore
	 */
	public static function get_content_triggers() {
		return [
			[
				'id'      => 'campaign',
				'name'    => __( 'Time after campaign trigger', \TVA_Const::T ),
				'summary' => __( 'Campaign trigger', \TVA_Const::T ),
			],
			[
				'id'      => 'datetime',
				'name'    => __( 'At a specific date/time', \TVA_Const::T ),
				'summary' => __( 'Specific time and date', \TVA_Const::T ),
			],
			[
				'id'      => 'purchase',
				'name'    => __( 'Time after user purchases product', \TVA_Const::T ),
				'summary' => __( 'Purchase', \TVA_Const::T ),
			],
			[
				'id'       => 'tqb_result',
				'name'     => __( 'Thrive Quiz Result', \TVA_Const::T ),
				'summary'  => __( 'Quiz result', \TVA_Const::T ),
				'disabled' => ! \TVA\TQB\tva_tqb_integration()->is_quiz_builder_active(),
			],
			[
				'id'      => 'first-lesson',
				'name'    => __( 'Time after user starts the course', \TVA_Const::T ),
				'summary' => __( 'Accesses the course', \TVA_Const::T ),
			],
			[
				'id'       => 'automator',
				'name'     => __( 'Thrive Automator action', \TVA_Const::T ),
				'summary'  => __( 'Action', \TVA_Const::T ),
				'disabled' => ! Check::automator(),
			],
			[
				'id'      => 'course-content',
				'name'    => __( 'When course content is marked as complete', \TVA_Const::T ),
				'summary' => __( 'Course content is completed', \TVA_Const::T ),
			],
		];
	}
}

<?php
/**
 * Created by PhpStorm.
 * User: stork
 * Date: 10.07.2017
 * Time: 09:21
 */

/**
 * Global instance to be used allover
 */
global $tva_db;

/**
 * Encapsulates the global $wpdb object
 *
 * Class TVA_Db
 */
class TVA_Db {
	/**
	 * @var wpdb|null
	 */
	protected $wpdb = null;
	/**
	 * @var bool
	 */
	public static $withcomments = true;

	/**
	 * TVA_Db constructor.
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb = $wpdb;
	}

	/**
	 * Wrapper around $withcomments
	 */
	public static function setCommentsStatus() {
		global $withcomments;
		$withcomments = self::$withcomments;
	}
}

/**
 * Set the db object
 */
$tva_db = new TVA_Db();

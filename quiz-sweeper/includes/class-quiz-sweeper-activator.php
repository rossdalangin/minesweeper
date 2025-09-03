<?php

/**
 * Fired during plugin activation
 *
 * @link       https://example.com/
 * @since      1.0.0
 *
 * @package    Quiz_Sweeper
 * @subpackage Quiz_Sweeper/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Quiz_Sweeper
 * @subpackage Quiz_Sweeper/includes
 * @author     Jules <you@example.com>
 */
class Quiz_Sweeper_Activator {

    /**
     * Runs on plugin activation.
     *
     * Creates custom database tables and sets up default options.
     *
     * @since    1.0.0
     */
    public static function activate() {
        require_once plugin_dir_path( __FILE__ ) . 'class-quiz-sweeper-db.php';
        Quiz_Sweeper_DB::create_tables();
    }

}

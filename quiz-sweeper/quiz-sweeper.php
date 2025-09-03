<?php
/**
 * Plugin Name:       Quiz Sweeper
 * Plugin URI:        https://example.com/
 * Description:       A competitive quiz game for students, inspired by Minesweeper.
 * Version:           1.0.1
 * Author:            Jules
 * Author URI:        https://example.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       quiz-sweeper
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-quiz-sweeper.php';

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-quiz-sweeper-activator.php
 */
function activate_quiz_sweeper() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-quiz-sweeper-activator.php';
    Quiz_Sweeper_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-quiz-sweeper-deactivator.php
 */
function deactivate_quiz_sweeper() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-quiz-sweeper-deactivator.php';
    Quiz_Sweeper_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_quiz_sweeper' );
register_deactivation_hook( __FILE__, 'deactivate_quiz_sweeper' );


/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_quiz_sweeper() {

    $plugin = new Quiz_Sweeper();
    $plugin->run();

}
run_quiz_sweeper();

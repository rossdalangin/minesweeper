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


/**
 * AJAX handler for adding a question to a quiz.
 * Moved here for debugging purposes.
 */
function quiz_sweeper_ajax_add_question_to_quiz() {
    if ( ! check_ajax_referer( 'quiz_sweeper_admin_nonce', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => 'Error: Nonce verification failed.' ) );
        return;
    }

    $quiz_id = isset( $_POST['quiz_id'] ) ? intval( $_POST['quiz_id'] ) : 0;
    if ( ! current_user_can( 'edit_post', $quiz_id ) ) {
        wp_send_json_error( array( 'message' => 'Error: You do not have permission to edit this quiz.' ) );
        return;
    }

    $title = isset( $_POST['question_title'] ) ? sanitize_text_field( $_POST['question_title'] ) : '';
    if ( empty( $title ) ) {
        wp_send_json_error( array( 'message' => 'Error: Question title cannot be empty.' ) );
        return;
    }

    $choices = isset( $_POST['choices'] ) ? array_map( 'sanitize_text_field', $_POST['choices'] ) : array();
    $correct_choice = isset( $_POST['correct_choice'] ) ? intval( $_POST['correct_choice'] ) : -1;

    $question_post_data = array(
        'post_type'    => 'question',
        'post_title'   => $title,
        'post_status'  => 'publish',
    );

    $question_id = wp_insert_post( $question_post_data, true );

    if ( is_wp_error( $question_id ) ) {
        wp_send_json_error( array( 'message' => 'Error on wp_insert_post: ' . $question_id->get_error_message() ) );
        return;
    }

    if ( $question_id === 0 ) {
         wp_send_json_error( array( 'message' => 'Error: wp_insert_post returned 0. The question was not created.' ) );
         return;
    }

    update_post_meta( $question_id, '_quiz_id', $quiz_id );
    update_post_meta( $question_id, '_choices', $choices );
    update_post_meta( $question_id, '_correct_choice', $correct_choice );

    wp_send_json_success( array(
        'id' => $question_id,
        'title' => $title,
    ) );
}
add_action( 'wp_ajax_add_question_to_quiz', 'quiz_sweeper_ajax_add_question_to_quiz' );

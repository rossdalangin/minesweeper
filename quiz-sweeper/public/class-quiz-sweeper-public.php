<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://example.com/
 * @since      1.0.0
 *
 * @package    Quiz_Sweeper
 * @subpackage Quiz_Sweeper/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Quiz_Sweeper
 * @subpackage Quiz_Sweeper/public
 * @author     Jules <you@example.com>
 */
class Quiz_Sweeper_Public {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of the plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {

        $this->plugin_name = $plugin_name;
        $this->version = $version;

    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/quiz-sweeper-public.css', array(), $this->version, 'all' );
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        wp_register_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/quiz-sweeper-public.js', array( 'jquery' ), $this->version, true );
    }

    /**
	 * Registers the shortcodes used by the plugin.
	 */
    public function register_shortcodes() {
        add_shortcode( 'quiz_sweeper_board', array( $this, 'render_game_board_shortcode' ) );
    }

    /**
	 * The callback function for the [quiz_sweeper_board] shortcode.
	 * It performs checks to ensure a student is logged in and part of an active game.
	 * If so, it enqueues the game script and renders the HTML structure for the game board.
	 */
    public function render_game_board_shortcode() {
        global $wpdb;
        $user = wp_get_current_user();

        if ( ! is_user_logged_in() || ! in_array( 'subscriber', (array) $user->roles ) ) {
            return '<p>You must be a logged-in student to play.</p>';
        }

        $active_game = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}quiz_sweeper_games WHERE status = 'active'" );
        if ( ! $active_game ) {
            return '<p>There is no active game at the moment. Please wait for your teacher to start one.</p>';
        }

        // Check if user is in a participating group
        $user_groups = wp_get_object_terms( $user->ID, 'student_group' );
        if ( is_wp_error( $user_groups ) || empty( $user_groups ) ) {
             return '<p>You are not assigned to a group. Please contact your teacher.</p>';
        }
        $user_group = $user_groups[0];

        $participating_group_ids = $wpdb->get_col( $wpdb->prepare( "SELECT group_id FROM {$wpdb->prefix}quiz_sweeper_game_scores WHERE game_id = %d", $active_game->game_id ) );

        if ( ! in_array( $user_group->term_id, $participating_group_ids ) ) {
            $participating_group_names = array();
            foreach($participating_group_ids as $gid) {
                $g = get_term($gid, 'student_group');
                if ($g) {
                    $participating_group_names[] = $g->name;
                }
            }
            $output = '<h3>Game Status</h3>';
            $output .= '<p>A game is active, but your group is not participating.</p>';
            $output .= '<ul>';
            $output .= '<li><strong>Your Name:</strong> ' . esc_html($user->display_name) . '</li>';
            $output .= '<li><strong>Your Group:</strong> ' . esc_html($user_group->name) . '</li>';
            $output .= '<li><strong>Groups Playing This Game:</strong> ' . esc_html(implode(', ', $participating_group_names)) . '</li>';
            $output .= '</ul>';
            $output .= '<p>Please ask your teacher to include your group in the game.</p>';
            return $output;
        }

        // Enqueue the main script.
        wp_enqueue_script( $this->plugin_name );

        // Directly print the JS object as a workaround for localization issues.
        $data = array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'quiz_sweeper_student_nonce' ),
            'game_id'  => $active_game->game_id,
            'group_id' => $user_group->term_id
        );
        ?>
        <script type="text/javascript">
            var quiz_sweeper_student_ajax = <?php echo json_encode($data); ?>;
        </script>
        <div id="quiz-sweeper-app">
            <div id="quiz-sweeper-scores"></div>
            <div id="quiz-sweeper-board"></div>
            <div id="quiz-sweeper-modal" style="display:none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
	 * AJAX handler to get the current state of the game.
	 * Returns the grid layout and scores for all groups.
	 * This is polled by the student's browser to keep the game board updated.
	 */
    public function ajax_get_game_state() {
        global $wpdb;
        $log_data = array( 'action' => 'get_game_state', 'timestamp' => current_time('mysql'), 'get_data' => $_GET );

        if ( ! check_ajax_referer( 'quiz_sweeper_student_nonce', 'nonce', false ) ) {
            $log_data['error'] = 'Nonce verification failed.';
            set_transient('quiz_sweeper_debug_log', $log_data, HOUR_IN_SECONDS);
            wp_send_json_error( array( 'message' => 'Nonce error.' ) );
            return;
        }

        $game_id = isset( $_GET['game_id'] ) ? intval( $_GET['game_id'] ) : 0;
        if ( empty($game_id) ) {
            $log_data['error'] = 'Game ID was empty.';
            set_transient('quiz_sweeper_debug_log', $log_data, HOUR_IN_SECONDS);
            wp_send_json_error( array( 'message' => 'Game ID error.' ) );
            return;
        }

        // Get grid state
        $grid_table = $wpdb->prefix . 'quiz_sweeper_game_grid';
        $grid_results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $grid_table WHERE game_id = %d ORDER BY row_num, col_num", $game_id ) );

        $grid_data = array();
        foreach ( $grid_results as $cell ) {
            $cell_data = array(
                'row' => $cell->row_num,
                'col' => $cell->col_num,
                'is_revealed' => $cell->is_revealed,
            );
            if ( $cell->is_revealed ) {
                $cell_data['type'] = $cell->cell_type;
                if ($cell->cell_type === 'question') {
                    $cell_data['was_correct'] = $cell->was_correct;
                }
            }
            $grid_data[] = $cell_data;
        }

        // Get scores
        $scores_table = $wpdb->prefix . 'quiz_sweeper_game_scores';
        $score_results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $scores_table WHERE game_id = %d", $game_id ) );

        $score_data = array();
        foreach($score_results as $score) {
            $group = get_term( $score->group_id, 'student_group' );
            $score_data[] = array(
                'group_id' => $score->group_id,
                'group_name' => $group ? $group->name : 'Unknown Group',
                'score' => $score->score
            );
        }

        wp_send_json_success( array( 'grid' => $grid_data, 'scores' => $score_data ) );
    }

    /**
	 * AJAX handler to get the details of a specific question.
	 * Called when a student clicks on a question cell, before they answer.
	 * Does not reveal the cell, only fetches the question text and choices.
	 */
    public function ajax_get_question_details() {
        global $wpdb;
        $log_data = array( 'action' => 'get_question_details', 'timestamp' => current_time('mysql'), 'get_data' => $_GET );

        if ( ! check_ajax_referer( 'quiz_sweeper_student_nonce', 'nonce', false ) ) {
            $log_data['error'] = 'Nonce verification failed.';
            set_transient('quiz_sweeper_debug_log', $log_data, HOUR_IN_SECONDS);
            wp_send_json_error( array( 'message' => 'Nonce error.' ) );
            return;
        }

        $game_id = isset( $_GET['game_id'] ) ? intval( $_GET['game_id'] ) : 0;
        $row = isset( $_GET['row'] ) ? intval( $_GET['row'] ) : -1;
        $col = isset( $_GET['col'] ) ? intval( $_GET['col'] ) : -1;

        $grid_table = $wpdb->prefix . 'quiz_sweeper_game_grid';
        $cell = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $grid_table WHERE game_id = %d AND row_num = %d AND col_num = %d", $game_id, $row, $col ) );

        if ( !$cell || $cell->is_revealed ) {
            $log_data['error'] = 'Cell invalid or already revealed.';
            $log_data['cell'] = $cell;
            set_transient('quiz_sweeper_debug_log', $log_data, HOUR_IN_SECONDS);
            wp_send_json_error( array( 'message' => 'Cell invalid or already revealed.' ) );
        }

        if ( $cell->cell_type !== 'question' ) {
            wp_send_json_success( array( 'type' => $cell->cell_type ) );
        } else {
            $question_id = $cell->cell_value;
            $question = get_post( $question_id );
            $choices = get_post_meta( $question_id, '_choices', true );

            wp_send_json_success( array(
                'type' => 'question',
                'details' => array(
                    'title' => $question->post_title,
                    'choices' => $choices,
                )
            ) );
        }
    }

    /**
	 * AJAX handler for a student revealing a cell on the game board.
	 * This is the core gameplay logic trigger.
	 */
    public function ajax_reveal_cell() {
        global $wpdb;
        $log_data = array( 'action' => 'reveal_cell', 'timestamp' => current_time('mysql'), 'post_data' => $_POST );

        if ( ! check_ajax_referer( 'quiz_sweeper_student_nonce', 'nonce', false ) ) {
            $log_data['error'] = 'Nonce verification failed.';
            set_transient('quiz_sweeper_debug_log', $log_data, HOUR_IN_SECONDS);
            wp_send_json_error( array( 'message' => 'Nonce error.' ) );
            return;
        }

        $user_id = get_current_user_id();
        if ( ! $user_id || ! in_array( 'subscriber', (array) wp_get_current_user()->roles ) ) {
            $log_data['error'] = 'Invalid user role or not logged in.';
            set_transient('quiz_sweeper_debug_log', $log_data, HOUR_IN_SECONDS);
            wp_send_json_error( array( 'message' => 'Invalid user.' ) );
            return;
        }

        $game_id = isset( $_POST['game_id'] ) ? intval( $_POST['game_id'] ) : 0;
        $row = isset( $_POST['row'] ) ? intval( $_POST['row'] ) : -1;
        $col = isset( $_POST['col'] ) ? intval( $_POST['col'] ) : -1;

        // Get user's group
        $user_groups = wp_get_object_terms( $user_id, 'student_group' );
        if ( is_wp_error( $user_groups ) || empty( $user_groups ) ) {
            wp_send_json_error( array( 'message' => 'You are not in a group.' ) );
            return;
        }
        $group_id = $user_groups[0]->term_id;

        // Get cell from DB
        $table_grid = $wpdb->prefix . 'quiz_sweeper_game_grid';
        $cell = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_grid WHERE game_id = %d AND row_num = %d AND col_num = %d", $game_id, $row, $col ) );

        if ( ! $cell || $cell->is_revealed ) {
            wp_send_json_error( array( 'message' => 'Cell is invalid or already revealed.' ) );
        }

        // Mark as revealed
        $update_data = array( 'is_revealed' => 1, 'revealed_by_group_id' => $group_id );
        $response_data = array( 'cell_type' => $cell->cell_type );
        $score_change = 0;

        switch ( $cell->cell_type ) {
            case 'bomb':
                $score_change = -2;
                break;
            case 'knife':
                $wpdb->update( $wpdb->prefix . 'quiz_sweeper_game_scores', array( 'score' => 0 ), array( 'game_id' => $game_id, 'group_id' => $group_id ) );
                break;
            case 'question':
                $answer_index = isset( $_POST['answer_index'] ) ? intval( $_POST['answer_index'] ) : -1;
                $correct_answer = get_post_meta( $cell->cell_value, '_correct_choice', true );
                $was_correct = ( $answer_index == $correct_answer );
                $response_data['was_correct'] = $was_correct;
                $update_data['was_correct'] = $was_correct ? 1 : 0;
                if ( $was_correct ) {
                    $score_change = 5;
                }
                break;
        }

        // Mark as revealed with correctness info
        $wpdb->update( $table_grid, $update_data, array( 'grid_id' => $cell->grid_id ) );

        if ( $score_change != 0 ) {
            $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}quiz_sweeper_game_scores SET score = score + %d WHERE game_id = %d AND group_id = %d", $score_change, $game_id, $group_id ) );
        }

        $new_score = $wpdb->get_var( $wpdb->prepare( "SELECT score FROM {$wpdb->prefix}quiz_sweeper_game_scores WHERE game_id = %d AND group_id = %d", $game_id, $group_id ) );
        $response_data['new_score'] = $new_score;

        // Check if the game is over
        $remaining_cells = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_grid WHERE game_id = %d AND is_revealed = 0", $game_id ) );
        if ( $remaining_cells == 0 ) {
            $wpdb->update( $wpdb->prefix . 'quiz_sweeper_games', array( 'status' => 'complete', 'end_time' => current_time( 'mysql' ) ), array( 'game_id' => $game_id ) );
        }

        wp_send_json_success( $response_data );
    }
}

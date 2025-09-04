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
        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/quiz-sweeper-public.js', array( 'jquery' ), $this->version, true );

        // We need to pass data to the script
        // This will be done in the shortcode handler to ensure it only loads when needed
    }

    public function register_shortcodes() {
        add_shortcode( 'quiz_sweeper_board', array( $this, 'render_game_board_shortcode' ) );
    }

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

        // If all checks pass, enqueue the script and pass data
        wp_enqueue_script( $this->plugin_name );
        wp_localize_script(
            $this->plugin_name,
            'quiz_sweeper_student_ajax',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'quiz_sweeper_student_nonce' ),
                'game_id'  => $active_game->game_id,
                'group_id' => $group_id
            )
        );

        // Return the HTML structure for the JS to populate
        ob_start();
        ?>
        <div id="quiz-sweeper-app">
            <div id="quiz-sweeper-scores"></div>
            <div id="quiz-sweeper-board"></div>
            <div id="quiz-sweeper-modal" style="display:none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function ajax_get_game_state() {
        global $wpdb;
        $log_data = array( 'timestamp' => current_time('mysql'), 'get_data' => $_GET );

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

    public function ajax_get_question_details() {
        global $wpdb;
        check_ajax_referer( 'quiz_sweeper_student_nonce', 'nonce' );
        $game_id = isset( $_GET['game_id'] ) ? intval( $_GET['game_id'] ) : 0;
        $row = isset( $_GET['row'] ) ? intval( $_GET['row'] ) : -1;
        $col = isset( $_GET['col'] ) ? intval( $_GET['col'] ) : -1;

        $grid_table = $wpdb->prefix . 'quiz_sweeper_game_grid';
        $cell = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $grid_table WHERE game_id = %d AND row_num = %d AND col_num = %d", $game_id, $row, $col ) );

        if ( !$cell || $cell->is_revealed ) {
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
}

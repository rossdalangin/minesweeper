<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://example.com/
 * @since      1.0.0
 *
 * @package    Quiz_Sweeper
 * @subpackage Quiz_Sweeper/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Quiz_Sweeper
 * @subpackage Quiz_Sweeper/admin
 * @author     Jules <you@example.com>
 */
class Quiz_Sweeper_Admin {

    public function ajax_add_question_to_quiz() {
        check_ajax_referer( 'quiz_sweeper_admin_nonce', 'nonce' );

        $quiz_id = isset( $_POST['quiz_id'] ) ? intval( $_POST['quiz_id'] ) : 0;
        if ( ! current_user_can( 'edit_post', $quiz_id ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $title = sanitize_text_field( $_POST['question_title'] );
        $choices = array_map( 'sanitize_text_field', $_POST['choices'] );
        $correct_choice = intval( $_POST['correct_choice'] );

        if ( empty( $title ) ) {
            wp_send_json_error( array( 'message' => 'Question title cannot be empty.' ) );
        }

        $question_id = wp_insert_post( array(
            'post_type'    => 'question',
            'post_title'   => $title,
            'post_status'  => 'publish',
        ) );

        if ( $question_id ) {
            update_post_meta( $question_id, '_quiz_id', $quiz_id );
            update_post_meta( $question_id, '_choices', $choices );
            update_post_meta( $question_id, '_correct_choice', $correct_choice );
            wp_send_json_success();
        } else {
            wp_send_json_error( array( 'message' => 'Could not save question.' ) );
        }
    }

    public function ajax_get_quiz_questions() {
        check_ajax_referer( 'quiz_sweeper_admin_nonce', 'nonce' );

        $quiz_id = isset( $_GET['quiz_id'] ) ? intval( $_GET['quiz_id'] ) : 0;
        if ( ! current_user_can( 'edit_post', $quiz_id ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $args = array(
            'post_type'  => 'question',
            'posts_per_page' => -1,
            'meta_key'   => '_quiz_id',
            'meta_value' => $quiz_id,
        );

        $questions = get_posts( $args );
        $data = array();

        foreach ( $questions as $q ) {
            $data[] = array(
                'id'    => $q->ID,
                'title' => $q->post_title,
            );
        }

        wp_send_json_success( $data );
    }

    public function ajax_delete_quiz_question() {
        check_ajax_referer( 'quiz_sweeper_admin_nonce', 'nonce' );

        $question_id = isset( $_POST['question_id'] ) ? intval( $_POST['question_id'] ) : 0;
        $quiz_id = get_post_meta( $question_id, '_quiz_id', true );

        if ( ! current_user_can( 'edit_post', $quiz_id ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $result = wp_delete_post( $question_id, true ); // true to force delete

        if ( $result ) {
            wp_send_json_success();
        } else {
            wp_send_json_error( array( 'message' => 'Could not delete question.' ) );
        }
    }

    public function ajax_reveal_cell() {
        global $wpdb;
        // This nonce will be passed from the student's JS
        check_ajax_referer( 'quiz_sweeper_student_nonce', 'nonce' );

        $user_id = get_current_user_id();
        if ( ! $user_id || ! in_array( 'subscriber', (array) wp_get_current_user()->roles ) ) {
            wp_send_json_error( array( 'message' => 'Invalid user.' ) );
        }

        $game_id = isset( $_POST['game_id'] ) ? intval( $_POST['game_id'] ) : 0;
        $row = isset( $_POST['row'] ) ? intval( $_POST['row'] ) : -1;
        $col = isset( $_POST['col'] ) ? intval( $_POST['col'] ) : -1;

        // Get user's group
        $user_groups = wp_get_object_terms( $user_id, 'student_group' );
        if ( is_wp_error( $user_groups ) || empty( $user_groups ) ) {
            wp_send_json_error( array( 'message' => 'You are not in a group.' ) );
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

    public function add_quiz_meta_boxes() {
        add_meta_box(
            'quiz_sweeper_questions',
            __( 'Quiz Questions', 'quiz-sweeper' ),
            array( $this, 'render_questions_meta_box' ),
            'quiz', // The CPT slug
            'normal',
            'high'
        );
    }

    public function render_questions_meta_box( $post ) {
        ?>
        <div id="questions-container">
            <p><?php _e( 'Questions associated with this quiz will appear here.', 'quiz-sweeper' ); ?></p>
        </div>
        <div id="add-question-form-wrapper" style="display: none;">
            <!-- The form for adding a new question will be injected here by JavaScript -->
        </div>
        <button type="button" id="add-question-button" class="button"><?php _e( 'Add a Question', 'quiz-sweeper' ); ?></button>
        <?php
    }

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
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {

        $this->plugin_name = $plugin_name;
        $this->version = $version;

    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        // This function will be filled in later.
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts( $hook ) {
        global $post;

        // Only load this script on the quiz edit screen
        if ( 'post.php' != $hook && 'post-new.php' != $hook ) {
            return;
        }
        if ( ! isset( $post->post_type ) || 'quiz' != $post->post_type ) {
            return;
        }

        wp_enqueue_script(
            $this->plugin_name . '_admin',
            plugin_dir_url( __FILE__ ) . 'js/quiz-sweeper-admin.js',
            array( 'jquery' ),
            $this->version,
            true
        );

        wp_localize_script(
            $this->plugin_name . '_admin',
            'quiz_sweeper_admin_ajax',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'quiz_sweeper_admin_nonce' ),
                'quiz_id'  => $post->ID,
            )
        );
    }

    /**
     * Add the top-level admin menu for the plugin.
     *
     * @since    1.0.0
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Quiz Sweeper', 'quiz-sweeper' ),
            __( 'Quiz Sweeper', 'quiz-sweeper' ),
            'manage_options',
            $this->plugin_name,
            array( $this, 'render_main_dashboard_page' ),
            'dashicons-games'
        );

        add_submenu_page(
            $this->plugin_name,
            __( 'Manage Groups', 'quiz-sweeper' ),
            __( 'Manage Groups', 'quiz-sweeper' ),
            'manage_options',
            $this->plugin_name . '-groups',
            array( $this, 'render_groups_page' )
        );

        add_submenu_page(
            $this->plugin_name,
            __( 'Start Quiz', 'quiz-sweeper' ),
            __( 'Start Quiz', 'quiz-sweeper' ),
            'manage_options',
            $this->plugin_name . '-start',
            array( $this, 'render_start_quiz_page' )
        );
    }

    /**
     * Render the main dashboard page for the plugin.
     *
     * @since    1.0.0
     */
    public function render_main_dashboard_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <p><?php _e( 'Welcome to Quiz Sweeper! This is your main dashboard.', 'quiz-sweeper' ); ?></p>
            <p>
                <a href="<?php echo admin_url( 'edit.php?post_type=quiz' ); ?>" class="button button-primary"><?php _e( 'Manage Quizzes', 'quiz-sweeper' ); ?></a>
                <a href="<?php echo admin_url( 'admin.php?page=' . $this->plugin_name . '-groups' ); ?>" class="button button-secondary"><?php _e( 'Manage Groups', 'quiz-sweeper' ); ?></a>
                <a href="<?php echo admin_url( 'admin.php?page=' . $this->plugin_name . '-start' ); ?>" class="button button-secondary"><?php _e( 'Start Quiz', 'quiz-sweeper' ); ?></a>
            </p>
        </div>
        <?php
    }

    /**
     * Render the groups management page.
     *
     * @since    1.0.0
     */
    public function render_groups_page() {
        // Handle Save Members
        if ( isset( $_POST['action'] ) && $_POST['action'] == 'save_members' && isset( $_POST['group_id'] ) && check_admin_referer( 'save_members_nonce' ) ) {
            $group_id = intval( $_POST['group_id'] );
            $member_ids = isset( $_POST['member_ids'] ) ? array_map( 'intval', $_POST['member_ids'] ) : array();

            // Get all users associated with the student_group taxonomy
            $all_users_in_any_group = get_objects_in_term( get_terms('student_group', array('fields' => 'ids')), 'student_group' );

            foreach($all_users_in_any_group as $user_id) {
                // If a user was previously in a group but now is not in the current group, remove them.
                if(!in_array($user_id, $member_ids)) {
                    wp_remove_object_terms($user_id, $group_id, 'student_group');
                }
            }

            wp_set_object_terms( $group_id, $member_ids, 'student_group', false );

            wp_redirect( admin_url( 'admin.php?page=' . $this->plugin_name . '-groups' ) );
            exit;
        }

        // Check if we are editing members
        if ( isset( $_GET['action'] ) && $_GET['action'] == 'edit_members' && isset( $_GET['group_id'] ) ) {
            $this->render_members_assignment_page( intval( $_GET['group_id'] ) );
            return;
        }

        // Handle Add New Group
        if ( isset( $_POST['action'] ) && $_POST['action'] == 'add_group' && check_admin_referer( 'add_group_nonce' ) ) {
            $group_name = sanitize_text_field( $_POST['group_name'] );
            if ( ! empty( $group_name ) ) {
                wp_insert_term( $group_name, 'student_group' );
            }
        }

        // Handle Delete Group
        if ( isset( $_GET['action'] ) && $_GET['action'] == 'delete_group' && isset( $_GET['group_id'] ) && check_admin_referer( 'delete_group_' . $_GET['group_id'] ) ) {
            $group_id = intval( $_GET['group_id'] );
            wp_delete_term( $group_id, 'student_group' );
        }

        // Get all student groups
        $groups = get_terms( array( 'taxonomy' => 'student_group', 'hide_empty' => false ) );
        ?>
        <div class="wrap">
            <h2><?php _e( 'Manage Student Groups', 'quiz-sweeper' ); ?></h2>
            <div id="col-container">
                <div id="col-left">
                    <div class="col-wrap">
                        <h3><?php _e( 'Add New Student Group', 'quiz-sweeper' ); ?></h3>
                        <form method="post">
                            <input type="hidden" name="action" value="add_group">
                            <?php wp_nonce_field( 'add_group_nonce' ); ?>
                            <div class="form-field">
                                <label for="group_name"><?php _e( 'Group Name', 'quiz-sweeper' ); ?></label>
                                <input name="group_name" id="group_name" type="text" value="" style="width: 95%;">
                            </div>
                            <p class="submit">
                                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e( 'Add New Group', 'quiz-sweeper' ); ?>">
                            </p>
                        </form>
                    </div>
                </div>
                <div id="col-right">
                    <div class="col-wrap">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th scope="col" class="manage-column"><?php _e( 'Group Name', 'quiz-sweeper' ); ?></th>
                                    <th scope="col" class="manage-column"><?php _e( 'Student Count', 'quiz-sweeper' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( ! empty( $groups ) && ! is_wp_error( $groups ) ) : ?>
                                    <?php foreach ( $groups as $group ) : ?>
                                        <?php
                                        $edit_url = add_query_arg( array( 'action' => 'edit_members', 'group_id' => $group->term_id ), admin_url( 'admin.php?page=' . $this->plugin_name . '-groups' ) );
                                        $delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'delete_group', 'group_id' => $group->term_id ), admin_url( 'admin.php?page=' . $this->plugin_name . '-groups' ) ), 'delete_group_' . $group->term_id );
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $group->name ); ?></a></strong>
                                                <div class="row-actions">
                                                    <span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php _e( 'Add/Edit Members', 'quiz-sweeper' ); ?></a> | </span>
                                                    <span class="trash"><a href="<?php echo esc_url( $delete_url ); ?>" class="text-danger" onclick="return confirm('Are you sure you want to delete this group?');"><?php _e( 'Delete', 'quiz-sweeper' ); ?></a></span>
                                                </div>
                                            </td>
                                            <td><?php echo esc_html( $group->count ); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="2"><?php _e( 'No groups found.', 'quiz-sweeper' ); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the members assignment page for a specific group.
     *
     * @since    1.0.0
     * @param    int    $group_id    The ID of the group to edit.
     */
    private function render_members_assignment_page( $group_id ) {
        $group = get_term( $group_id, 'student_group' );

        if ( ! $group || is_wp_error( $group ) ) {
            wp_die( __( 'Group not found.', 'quiz-sweeper' ) );
        }

        $subscribers = get_users( array( 'role' => 'subscriber', 'fields' => array( 'ID', 'display_name' ) ) );
        $member_ids = get_objects_in_term( $group_id, 'student_group' );
        ?>
        <div class="wrap">
            <h1><?php printf( __( 'Assign Members to "%s"', 'quiz-sweeper' ), esc_html( $group->name ) ); ?></h1>
            <p><?php _e( 'Select the students you want to assign to this group.', 'quiz-sweeper' ); ?></p>

            <form method="post">
                <input type="hidden" name="action" value="save_members">
                <input type="hidden" name="group_id" value="<?php echo esc_attr( $group_id ); ?>">
                <?php wp_nonce_field( 'save_members_nonce' ); ?>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" id="cb" class="manage-column column-cb check-column"><input type="checkbox"></th>
                            <th scope="col" class="manage-column"><?php _e( 'Student Name', 'quiz-sweeper' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $subscribers ) ) : ?>
                            <?php foreach ( $subscribers as $user ) : ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="member_ids[]" value="<?php echo esc_attr( $user->ID ); ?>" <?php checked( in_array( $user->ID, $member_ids ) ); ?>>
                                    </th>
                                    <td><?php echo esc_html( $user->display_name ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="2"><?php _e( 'No students (subscribers) found.', 'quiz-sweeper' ); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e( 'Save Members', 'quiz-sweeper' ); ?>">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->plugin_name . '-groups' ) ); ?>" class="button button-secondary"><?php _e( 'Cancel', 'quiz-sweeper' ); ?></a>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render the start quiz page.
     *
     * @since    1.0.0
     */
    public function render_start_quiz_page() {
        global $wpdb;

        // Handle the form submission
        if ( isset( $_POST['action'] ) && $_POST['action'] == 'start_game' && check_admin_referer( 'start_game_nonce' ) ) {
            $quiz_id = intval( $_POST['quiz_id'] );
            $group_ids = isset( $_POST['group_ids'] ) ? array_map( 'intval', $_POST['group_ids'] ) : array();

            // Simple validation
            if ( empty( $quiz_id ) || empty( $group_ids ) ) {
                echo '<div class="error notice"><p>Please select a quiz and at least one group.</p></div>';
            } else {
                // Check for an existing active game
                $active_game = $wpdb->get_var( "SELECT game_id FROM {$wpdb->prefix}quiz_sweeper_games WHERE status = 'active'" );
                if ( $active_game ) {
                    echo '<div class="error notice"><p>An active game is already in progress. Please end it before starting a new one.</p></div>';
                } else {
                    // Create the game record
                    $wpdb->insert(
                        $wpdb->prefix . 'quiz_sweeper_games',
                        array(
                            'quiz_id'    => $quiz_id,
                            'status'     => 'active',
                            'start_time' => current_time( 'mysql' ),
                        )
                    );
                    $game_id = $wpdb->insert_id;

                    // Initialize scores
                    foreach ( $group_ids as $group_id ) {
                        $wpdb->insert( $wpdb->prefix . 'quiz_sweeper_game_scores', array( 'game_id' => $game_id, 'group_id' => $group_id, 'score' => 0 ) );
                    }

                    // Generate grid items
                    $question_ids = get_posts( array( 'post_type' => 'question', 'posts_per_page' => -1, 'meta_key' => '_quiz_id', 'meta_value' => $quiz_id, 'fields' => 'ids' ) );
                    $items = array();
                    foreach ( $question_ids as $qid ) {
                        $items[] = array( 'type' => 'question', 'value' => $qid );
                    }
                    // Add bombs and knives
                    for ( $i = 0; $i < 2; $i++ ) $items[] = array( 'type' => 'bomb', 'value' => 0 );
                    for ( $i = 0; $i < 1; $i++ ) $items[] = array( 'type' => 'knife', 'value' => 0 );

                    shuffle( $items );

                    // For now, let's use a 5x5 grid (25 cells)
                    $grid_size = 25;
                    $rows = 5;
                    $cols = 5;

                    // Populate the grid
                    for ( $i = 0; $i < $grid_size; $i++ ) {
                        $row = floor( $i / $cols );
                        $col = $i % $cols;
                        $item = array_pop( $items );

                        if ( $item ) {
                            $wpdb->insert( $wpdb->prefix . 'quiz_sweeper_game_grid', array( 'game_id' => $game_id, 'row_num' => $row, 'col_num' => $col, 'cell_type' => $item['type'], 'cell_value' => $item['value'] ) );
                        } else {
                             $wpdb->insert( $wpdb->prefix . 'quiz_sweeper_game_grid', array( 'game_id' => $game_id, 'row_num' => $row, 'col_num' => $col, 'cell_type' => 'empty', 'cell_value' => 0 ) );
                        }
                    }

                    echo '<div class="updated notice"><p><strong>Game started successfully!</strong> Students can now log in to play.</p></div>';
                }
            }
        }

        // Fetch data for the form
        $quizzes = get_posts( array( 'post_type' => 'quiz', 'post_status' => 'publish', 'posts_per_page' => -1 ) );
        $groups = get_terms( array( 'taxonomy' => 'student_group', 'hide_empty' => false ) );
        ?>
        <div class="wrap">
            <h2><?php _e( 'Start a New Quiz', 'quiz-sweeper' ); ?></h2>
            <p><?php _e( 'Select a quiz and the groups that will participate in the game.', 'quiz-sweeper' ); ?></p>

            <form method="post">
                <input type="hidden" name="action" value="start_game">
                <?php wp_nonce_field( 'start_game_nonce' ); ?>

                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="quiz_id"><?php _e( 'Select Quiz', 'quiz-sweeper' ); ?></label></th>
                            <td>
                                <select name="quiz_id" id="quiz_id" required>
                                    <option value=""><?php _e( '--- Select a Quiz ---', 'quiz-sweeper' ); ?></option>
                                    <?php foreach ( $quizzes as $quiz ) : ?>
                                        <option value="<?php echo esc_attr( $quiz->ID ); ?>"><?php echo esc_html( $quiz->post_title ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e( 'Select Groups', 'quiz-sweeper' ); ?></th>
                            <td>
                                <fieldset>
                                    <?php if ( ! empty( $groups ) && ! is_wp_error( $groups ) ) : ?>
                                        <?php foreach ( $groups as $group ) : ?>
                                            <label style="display: block; margin-bottom: 5px;">
                                                <input type="checkbox" name="group_ids[]" value="<?php echo esc_attr( $group->term_id ); ?>">
                                                <?php echo esc_html( $group->name ); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p><?php _e( 'No groups have been created yet.', 'quiz-sweeper' ); ?></p>
                                    <?php endif; ?>
                                </fieldset>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e( 'Start Quiz Game', 'quiz-sweeper' ); ?>" <?php if ( empty( $quizzes ) || empty( $groups ) ) echo 'disabled'; ?>>
                </p>
            </form>
        </div>
        <?php
    }
}

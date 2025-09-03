<?php

/**
 * The file that defines the custom post types and taxonomies.
 *
 * @link       https://example.com/
 * @since      1.0.0
 *
 * @package    Quiz_Sweeper
 * @subpackage Quiz_Sweeper/includes
 */

/**
 * Defines the custom post types and taxonomies for the plugin.
 *
 * @package    Quiz_Sweeper
 * @subpackage Quiz_Sweeper/includes
 * @author     Jules <you@example.com>
 */
class Quiz_Sweeper_CPTs {

    /**
     * Register all CPTs and taxonomies.
     *
     * @since    1.0.0
     */
    public function register_cpts_and_taxonomies() {
        $this->register_quiz_cpt();
        $this->register_question_cpt();
        $this->register_group_taxonomy();
    }

    /**
     * Register the Group custom taxonomy.
     *
     * @since    1.0.0
     */
    public function register_group_taxonomy() {
        $labels = array(
            'name'              => _x( 'Student Groups', 'taxonomy general name', 'quiz-sweeper' ),
            'singular_name'     => _x( 'Student Group', 'taxonomy singular name', 'quiz-sweeper' ),
            'search_items'      => __( 'Search Student Groups', 'quiz-sweeper' ),
            'all_items'         => __( 'All Student Groups', 'quiz-sweeper' ),
            'parent_item'       => __( 'Parent Student Group', 'quiz-sweeper' ),
            'parent_item_colon' => __( 'Parent Student Group:', 'quiz-sweeper' ),
            'edit_item'         => __( 'Edit Student Group', 'quiz-sweeper' ),
            'update_item'       => __( 'Update Student Group', 'quiz-sweeper' ),
            'add_new_item'      => __( 'Add New Student Group', 'quiz-sweeper' ),
            'new_item_name'     => __( 'New Student Group Name', 'quiz-sweeper' ),
            'menu_name'         => __( 'Student Groups', 'quiz-sweeper' ),
        );

        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'student-group' ),
            'public'            => true,
            'show_in_nav_menus' => false,
            'show_tagcloud'     => false,
            'show_in_rest'      => true,
            'capabilities'      => array(
                'manage_terms'  => 'edit_users',
                'edit_terms'    => 'edit_users',
                'delete_terms'  => 'edit_users',
                'assign_terms'  => 'read',
            ),
        );

        register_taxonomy( 'student_group', array( 'user' ), $args );
    }

    /**
     * Register the Question CPT.
     *
     * @since    1.0.0
     */
    public function register_question_cpt() {
        $labels = array(
            'name'                  => _x( 'Questions', 'Post Type General Name', 'quiz-sweeper' ),
            'singular_name'         => _x( 'Question', 'Post Type Singular Name', 'quiz-sweeper' ),
            'menu_name'             => __( 'Questions', 'quiz-sweeper' ),
            'name_admin_bar'        => __( 'Question', 'quiz-sweeper' ),
            'archives'              => __( 'Question Archives', 'quiz-sweeper' ),
            'attributes'            => __( 'Question Attributes', 'quiz-sweeper' ),
            'parent_item_colon'     => __( 'Parent Question:', 'quiz-sweeper' ),
            'all_items'             => __( 'All Questions', 'quiz-sweeper' ),
            'add_new_item'          => __( 'Add New Question', 'quiz-sweeper' ),
            'add_new'               => __( 'Add New', 'quiz-sweeper' ),
            'new_item'              => __( 'New Question', 'quiz-sweeper' ),
            'edit_item'             => __( 'Edit Question', 'quiz-sweeper' ),
            'update_item'           => __( 'Update Question', 'quiz-sweeper' ),
            'view_item'             => __( 'View Question', 'quiz-sweeper' ),
            'view_items'            => __( 'View Questions', 'quiz-sweeper' ),
            'search_items'          => __( 'Search Question', 'quiz-sweeper' ),
            'not_found'             => __( 'Not found', 'quiz-sweeper' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'quiz-sweeper' ),
        );
        $args = array(
            'label'                 => __( 'Question', 'quiz-sweeper' ),
            'description'           => __( 'A post type for quiz questions.', 'quiz-sweeper' ),
            'labels'                => $labels,
            'supports'              => array( 'title', 'editor' ),
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => 'quiz-sweeper',
            'show_in_admin_bar'     => false,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'capability_type'       => 'post',
            'show_in_rest'          => true,
        );
        register_post_type( 'question', $args );
    }

    /**
     * Register the Quiz CPT.
     *
     * @since    1.0.0
     */
    public function register_quiz_cpt() {
        $labels = array(
            'name'                  => _x( 'Quizzes', 'Post Type General Name', 'quiz-sweeper' ),
            'singular_name'         => _x( 'Quiz', 'Post Type Singular Name', 'quiz-sweeper' ),
            'menu_name'             => __( 'Quizzes', 'quiz-sweeper' ),
            'name_admin_bar'        => __( 'Quiz', 'quiz-sweeper' ),
            'archives'              => __( 'Quiz Archives', 'quiz-sweeper' ),
            'attributes'            => __( 'Quiz Attributes', 'quiz-sweeper' ),
            'parent_item_colon'     => __( 'Parent Quiz:', 'quiz-sweeper' ),
            'all_items'             => __( 'All Quizzes', 'quiz-sweeper' ),
            'add_new_item'          => __( 'Add New Quiz', 'quiz-sweeper' ),
            'add_new'               => __( 'Add New', 'quiz-sweeper' ),
            'new_item'              => __( 'New Quiz', 'quiz-sweeper' ),
            'edit_item'             => __( 'Edit Quiz', 'quiz-sweeper' ),
            'update_item'           => __( 'Update Quiz', 'quiz-sweeper' ),
            'view_item'             => __( 'View Quiz', 'quiz-sweeper' ),
            'view_items'            => __( 'View Quizzes', 'quiz-sweeper' ),
            'search_items'          => __( 'Search Quiz', 'quiz-sweeper' ),
            'not_found'             => __( 'Not found', 'quiz-sweeper' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'quiz-sweeper' ),
            'featured_image'        => __( 'Featured Image', 'quiz-sweeper' ),
            'set_featured_image'    => __( 'Set featured image', 'quiz-sweeper' ),
            'remove_featured_image' => __( 'Remove featured image', 'quiz-sweeper' ),
            'use_featured_image'    => __( 'Use as featured image', 'quiz-sweeper' ),
            'insert_into_item'      => __( 'Insert into quiz', 'quiz-sweeper' ),
            'uploaded_to_this_item' => __( 'Uploaded to this quiz', 'quiz-sweeper' ),
            'items_list'            => __( 'Quizzes list', 'quiz-sweeper' ),
            'items_list_navigation' => __( 'Quizzes list navigation', 'quiz-sweeper' ),
            'filter_items_list'     => __( 'Filter quizzes list', 'quiz-sweeper' ),
        );
        $args = array(
            'label'                 => __( 'Quiz', 'quiz-sweeper' ),
            'description'           => __( 'A post type for creating quizzes.', 'quiz-sweeper' ),
            'labels'                => $labels,
            'supports'              => array( 'title', 'editor', 'author' ),
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => 'quiz-sweeper',
            'menu_position'         => 5,
            'menu_icon'             => 'dashicons-welcome-learn-more',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => true,
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'capability_type'       => 'post',
            'show_in_rest'          => true,
        );
        register_post_type( 'quiz', $args );
    }
}

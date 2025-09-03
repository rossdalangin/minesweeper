<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://example.com/
 * @since      1.0.0
 *
 * @package    Quiz_Sweeper
 * @subpackage Quiz_Sweeper/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Quiz_Sweeper
 * @subpackage Quiz_Sweeper/includes
 * @author     Jules <you@example.com>
 */
class Quiz_Sweeper {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Quiz_Sweeper_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct() {
        if ( defined( 'QUIZ_SWEEPER_VERSION' ) ) {
            $this->version = QUIZ_SWEEPER_VERSION;
        } else {
            $this->version = '1.0.1';
        }
        $this->plugin_name = 'quiz-sweeper';

        $this->load_dependencies();
        $this->loader = new Quiz_Sweeper_Loader();
        $this->define_admin_hooks();
        $this->define_public_hooks();

    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Quiz_Sweeper_Loader. Orchestrates the hooks of the plugin.
     * - Quiz_Sweeper_Admin. Defines all hooks for the admin area.
     * - Quiz_Sweeper_Public. Defines all hooks for the public side of the site.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {

        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-quiz-sweeper-loader.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-quiz-sweeper-admin.php';

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-quiz-sweeper-public.php';

        /**
         * The class responsible for defining the custom post types and taxonomies.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-quiz-sweeper-cpts.php';

    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {

        $plugin_admin = new Quiz_Sweeper_Admin( $this->get_plugin_name(), $this->get_version() );
        $plugin_cpts = new Quiz_Sweeper_CPTs();

        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
        $this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
        $this->loader->add_action( 'add_meta_boxes', $plugin_admin, 'add_quiz_meta_boxes' );
        $this->loader->add_action( 'wp_ajax_add_question_to_quiz', $plugin_admin, 'ajax_add_question_to_quiz' );
        $this->loader->add_action( 'wp_ajax_get_quiz_questions', $plugin_admin, 'ajax_get_quiz_questions' );
        $this->loader->add_action( 'wp_ajax_delete_quiz_question', $plugin_admin, 'ajax_delete_quiz_question' );
        $this->loader->add_action( 'wp_ajax_reveal_cell', $plugin_admin, 'ajax_reveal_cell' );
        $this->loader->add_action( 'init', $plugin_cpts, 'register_cpts_and_taxonomies' );

    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks() {

        $plugin_public = new Quiz_Sweeper_Public( $this->get_plugin_name(), $this->get_version() );

        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
        $this->loader->add_action( 'init', $plugin_public, 'register_shortcodes' );
        $this->loader->add_action( 'wp_ajax_get_game_state', $plugin_public, 'ajax_get_game_state' );
        $this->loader->add_action( 'wp_ajax_get_question_details', $plugin_public, 'ajax_get_question_details' );

    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }

}

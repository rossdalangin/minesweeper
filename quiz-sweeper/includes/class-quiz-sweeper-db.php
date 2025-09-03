<?php
/**
 * The file that defines the database management class
 *
 * @link       https://example.com/
 * @since      1.0.0
 *
 * @package    Quiz_Sweeper
 * @subpackage Quiz_Sweeper/includes
 */

/**
 * The database management class.
 *
 * This is used to create and manage custom database tables.
 *
 * @since      1.0.0
 * @package    Quiz_Sweeper
 * @subpackage Quiz_Sweeper/includes
 * @author     Jules <you@example.com>
 */
class Quiz_Sweeper_DB {

    /**
     * Create the custom database tables required for the plugin.
     *
     * @since    1.0.0
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $table_name_games = $wpdb->prefix . 'quiz_sweeper_games';
        $sql_games = "CREATE TABLE $table_name_games (
            game_id mediumint(9) NOT NULL AUTO_INCREMENT,
            quiz_id mediumint(9) NOT NULL,
            status varchar(20) DEFAULT 'active' NOT NULL,
            start_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            end_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (game_id)
        ) $charset_collate;";
        dbDelta( $sql_games );

        $table_name_grid = $wpdb->prefix . 'quiz_sweeper_game_grid';
        $sql_grid = "CREATE TABLE $table_name_grid (
            grid_id bigint(20) NOT NULL AUTO_INCREMENT,
            game_id mediumint(9) NOT NULL,
            row_num tinyint(4) NOT NULL,
            col_num tinyint(4) NOT NULL,
            cell_type varchar(20) NOT NULL,
            cell_value mediumint(9) DEFAULT 0 NOT NULL,
            is_revealed tinyint(1) DEFAULT 0 NOT NULL,
            revealed_by_group_id mediumint(9) DEFAULT 0 NOT NULL,
            was_correct tinyint(1) DEFAULT -1 NOT NULL,
            PRIMARY KEY  (grid_id),
            KEY game_id (game_id)
        ) $charset_collate;";
        dbDelta( $sql_grid );

        $table_name_scores = $wpdb->prefix . 'quiz_sweeper_game_scores';
        $sql_scores = "CREATE TABLE $table_name_scores (
            score_id bigint(20) NOT NULL AUTO_INCREMENT,
            game_id mediumint(9) NOT NULL,
            group_id mediumint(9) NOT NULL,
            score int(11) DEFAULT 0 NOT NULL,
            PRIMARY KEY  (score_id),
            KEY game_id (game_id)
        ) $charset_collate;";
        dbDelta( $sql_scores );
    }
}

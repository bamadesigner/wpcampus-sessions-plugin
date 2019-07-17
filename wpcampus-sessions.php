<?php
/**
 * Plugin Name:       WPCampus: Sessions
 * Plugin URI:        https://wpcampus.org
 * Description:       Manages session and speaker functionality for WPCampus conference websites.
 * Version:           1.0.0
 * Author:            WPCampus
 * Author URI:        https://wpcampus.org
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// We only need you in the admin.
if ( is_admin() ) {
	require_once plugin_dir_path( __FILE__ ) . 'inc/wpcampus-sessions-admin.php';
}

class WPCampus_Sessions {

	/**
	 * Holds the class instance.
	 *
	 * @access	private
	 * @var		WPCampus_Sessions
	 */
	private static $instance;

	/**
	 * Returns the instance of this class.
	 *
	 * @access  public
	 * @return	WPCampus_Sessions
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			$class_name = __CLASS__;
			self::$instance = new $class_name;
		}
		return self::$instance;
	}

	/**
	 * Warming up the engine.
	 */
	protected function __construct() {

		// Filter queries.
		add_filter( 'posts_clauses', array( $this, 'filter_posts_clauses' ), 100, 2 );

		// Globally modifying the schedule plugin.
		add_filter( 'conf_schedule_locations_cpt_args', array( $this, 'filter_conf_schedule_locations_cpt_args' ) );
		add_filter( 'post_type_link', array( $this, 'filter_conf_schedule_permalinks' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'modify_conf_schedule_query' ) );

		// Add rewrite rules.
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_action( 'init', array( $this, 'add_rewrite_tags' ) );

		// Set the livestream and feedback URLs.
		add_filter( 'conf_sch_livestream_url', array( $this, 'filter_conf_sch_livestream_url' ), 100, 2 );
		add_filter( 'conf_sch_feedback_url', array( $this, 'filter_conf_sch_feedback_url' ), 100, 2 );

	}

	/**
	 * Having a private clone and wakeup
	 * method prevents cloning of the instance.
	 *
	 * @access  private
	 * @return  void
	 */
	private function __clone() {}
	private function __wakeup() {}

	/**
	 * Filter the queries to "join" and order schedule information.
	 */
	public function filter_posts_clauses( $pieces, $query ) {
		global $wpdb;

		// Not in admin.
		if ( is_admin() ) {
			return $pieces;
		}

		// Get the post type.
		$post_type = $query->get( 'post_type' );

		// For speakers...
		if ( 'speakers' == $post_type
		     || ( is_array( $post_type ) && count( $post_type ) == 1 && in_array( 'speakers', $post_type ) ) ) {

			// "Join" to only get confirmed speakers.
			$pieces['join'] .= " INNER JOIN {$wpdb->postmeta} speaker_status ON speaker_status.post_id = {$wpdb->posts}.ID AND speaker_status.meta_key = 'conf_sch_speaker_status' AND speaker_status.meta_value = 'confirmed'";

		}

		return $pieces;
	}

	/**
	 * Globally modifying CPT arguments
	 * for the schedule locations.
	 *
	 * @access  public
	 * @param   $args - array - the default args.
	 * @return  array - the filtered args.
	 */
	public function filter_conf_schedule_locations_cpt_args( $args ) {

		// No locations archive.
		$args['has_archive'] = false;
		$args['rewrite'] = false;

		return $args;
	}

	/**
	 * Redirect or change permalinks for
	 * schedule post types.
	 *
	 * @access  public
	 * @param   $post_link - string - The post's permalink.
	 * @param   $post - WP_Post - The post in question.
	 * @return  string - the filtered permalink
	 */
	public function filter_conf_schedule_permalinks( $post_link, $post ) {

		/*
		 * Redirect all locations to the schedule.
		 *
		 * Redirect all speakers to speakers archive.
		 */
		if ( 'locations' == $post->post_type ) {
			return trailingslashit( get_bloginfo( 'url' ) ) . 'schedule/';
		} elseif ( 'speakers' == $post->post_type ) {
			return get_post_type_archive_link( 'speakers' );
		}

		return $post_link;
	}

	/**
	 * Get all of the speakers
	 * on the speakers archive.
	 *
	 * @access  public
	 * @param   $query - WP_Query - The WP_Query instance (passed by reference).
	 * @return  void
	 */
	public function modify_conf_schedule_query( $query ) {

		// Only for speakers.
		if ( 'speakers' != $query->get( 'post_type' ) ) {
			return;
		}

		// Get all of the speakers.
		$query->set( 'posts_per_page', -1 );

		// Order by title/name.
		$query->set( 'orderby', 'title' );
		$query->set( 'order', 'ASC' );

	}

	/**
	 * Add rewrite rules.
	 *
	 * @access  public
	 */
	public function add_rewrite_rules() {

		// For session surveys.
		add_rewrite_rule( '^session\-survey\/([0-9]+)\/?', 'index.php?pagename=session-survey&session=$matches[1]', 'top');

	}

	/**
	 * Add rewrite tags.
	 *
	 * @access  public
	 */
	public function add_rewrite_tags() {

		// Will hold session ID.
		add_rewrite_tag( '%session%', '([0-9]+)' );

	}

	/**
	 * Filter the livestream URL.
	 *
	 * @access  public
	 * @param   $livestream_url - string - the default livestream URL.
	 * @param   $post - object - the post information.
	 * @return  string - the filtered livestream URL.
	 */
	public function filter_conf_sch_livestream_url( $livestream_url, $post ) {

		if ( '573' == $post->ID ) {
			return '';
		}

		// Get the date.
		$event_date = get_post_meta( $post->ID, 'conf_sch_event_date', true );
		if ( ! $event_date || ! strtotime( $event_date ) ) {
			return $livestream_url;
		}

		// Convert.
		$event_date = new DateTime( $event_date );
		$event_date_string = $event_date->format( 'Y-m-d' );

		// Get the day.
		$event_day = '';
		if ( '2017-07-14' == $event_date_string ) {
			$event_day = 'fri';
		} else if ( '2017-07-15' == $event_date_string ) {
			$event_day = 'sat';
		}

		// Get the location.
		$location = get_post_meta( $post->ID, 'conf_sch_event_location', true );

		// Return URL for Room 1004.
		if ( '956' == $location ) {
			if ( 'fri' == $event_day ) {
				return 'https://zoom.us/j/566100647';
			} else if ( 'sat' == $event_day ) {
				return 'https://zoom.us/j/370277206';
			}
		} else if ( '954' == $location ) {
			if ( 'fri' == $event_day ) {
				return 'https://zoom.us/j/492704312';
			} else if ( 'sat' == $event_day ) {
				return 'https://zoom.us/j/153508653';
			}
		} else if ( '829' == $location ) {
			if ( 'fri' == $event_day ) {
				return 'https://zoom.us/j/199433513';
			} else if ( 'sat' == $event_day ) {
				return 'https://zoom.us/j/264420179';
			}
		}

		return $livestream_url;
	}

	/**
	 * Filter the feedback URL.
	 *
	 * @access  public
	 * @param   $feedback_url - string - the default feedback URL.
	 * @param   $post - object - the post information.
	 * @return  string - the filtered feedback URL.
	 */
	public function filter_conf_sch_feedback_url( $feedback_url, $post ) {

		// If a session, define the URL.
		$event_types = wp_get_object_terms( $post->ID, 'event_types', array( 'fields' => 'slugs' ) );
		if ( ! empty( $event_types ) && in_array( 'session', $event_types ) ) {
			return get_bloginfo( 'url' ) . '/session-survey/' . $post->ID . '/';
		}

		return $feedback_url;
	}
}

/**
 * Returns the instance of our main WPCampus_Sessions class.
 *
 * Will come in handy when we need to access the
 * class to retrieve data throughout the plugin.
 *
 * @access	public
 * @return	WPCampus_Sessions
 */
function wpcampus_sessions() {
	return WPCampus_Sessions::instance();
}

// Let's get this show on the road
wpcampus_sessions();

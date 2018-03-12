<?php
/**
 * Plugin Name:       WPCampus Sessions
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

		// Register taxonomies.
		add_action( 'init', array( $this, 'register_taxonomies' ), 0 );

		// Filter queries.
		add_filter( 'posts_clauses', array( $this, 'filter_posts_clauses' ), 100, 2 );

		// Globally modifying the schedule plugin.
		add_filter( 'conf_schedule_locations_cpt_args', array( $this, 'filter_conf_schedule_locations_cpt_args' ) );
		add_filter( 'post_type_link', array( $this, 'filter_conf_schedule_permalinks' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'modify_conf_schedule_query' ) );

		// Populate the session survey form.
		add_filter( 'gform_pre_render_9', array( $this, 'populate_session_survey_form' ) );
		add_filter( 'gform_pre_validation_9', array( $this, 'populate_session_survey_form' ) );
		add_filter( 'gform_admin_pre_render_9', array( $this, 'populate_session_survey_form' ) );
		add_filter( 'gform_pre_submission_filter_9', array( $this, 'populate_session_survey_form' ) );

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
	 * Get the speaker ID for a form.
	 */
	public function get_form_speaker_id() {
		return isset( $_GET['speaker'] ) ? $_GET['speaker'] : 0;
	}

	/**
	 * Get the session ID for a form.
	 */
	public function get_form_session_id() {
		return isset( $_GET['session'] ) ? $_GET['session'] : 0;
	}

	/**
	 * Get a speaker's confirmation ID.
	 */
	public function get_speaker_confirmation_id( $speaker_id, $create = false ) {

		// Get speaker's confirmation id.
		$speaker_confirmation_id = get_post_meta( $speaker_id, 'conf_sch_confirmation_id', true );

		// If no confirmation ID, create one.
		if ( ! $speaker_confirmation_id && $create ) {
			$speaker_confirmation_id = $this->create_speaker_confirmation_id( $speaker_id );
		}

		return ! empty( $speaker_confirmation_id ) ? $speaker_confirmation_id : false;
	}

	/**
	 * Create a speaker's confirmation ID.
	 */
	public function create_speaker_confirmation_id( $speaker_id ) {
		global $wpdb;
		$new_id = $wpdb->get_var( "SELECT SUBSTRING(MD5(RAND()),16)" );
		if ( ! empty( $new_id ) ) {
			update_post_meta( $speaker_id, 'conf_sch_confirmation_id', $new_id );
		}
		return $new_id;
	}

	/**
	 * Check the confirmation ID for a speaker.
	 */
	public function check_form_speaker_confirmation_id( $speaker_id = 0 ) {

		// Get the form confirmation ID.
		$form_confirmation_id = isset( $_GET['c'] ) ? $_GET['c'] : 0;
		if ( ! $form_confirmation_id ) {
			return false;
		}

		// Make sure we have the speaker ID.
		if ( ! $speaker_id ) {
			$speaker_id = $this->get_form_speaker_id();
		}

		if ( ! $speaker_id ) {
			return false;
		}

		// Get speaker's confirmation id.
		$speaker_confirmation_id = $this->get_speaker_confirmation_id( $speaker_id );

		return $form_confirmation_id === $speaker_confirmation_id;
	}

	/**
	 * Get the speaker's post for a form.
	 */
	public function get_form_speaker_post( $speaker_id = 0 ) {

		// Make sure we have the speaker ID.
		if ( ! $speaker_id ) {
			$speaker_id = $this->get_form_speaker_id();
		}

		if ( ! $speaker_id ) {
			return false;
		}

		// Get the speaker post.
		$speaker_post = get_post( $speaker_id );
		if ( empty( $speaker_post ) || ! is_a( $speaker_post, 'WP_Post' ) ) {
			return false;
		}

		return $speaker_post;
	}

	/**
	 * Get the session's post for a form.
	 */
	public function get_form_session_post( $session_id = 0 ) {

		// Make sure we have the session ID.
		if ( ! $session_id ) {
			$session_id = $this->get_form_session_id();
		}

		if ( ! $session_id ) {
			return false;
		}

		// Get the session post.
		$session_post = get_post( $session_id );
		if ( empty( $session_post ) || ! is_a( $session_post, 'WP_Post' ) ) {
			return false;
		}

		return $session_post;
	}

	/**
	 * Get the session's speakers for a form.
	 */
	public function get_form_session_speakers( $session_id ) {
		global $wpdb;

		// Make sure we have a session ID.
		if ( ! $session_id ) {
			$session_id = $this->get_form_speaker_id();
		}

		if ( ! $session_id ) {
			return false;
		}

		// Get the speaker IDs.
		return $wpdb->get_col( $wpdb->prepare( "SELECT speakers.ID FROM {$wpdb->posts} speakers
			INNER JOIN {$wpdb->postmeta} meta ON meta.meta_value = speakers.ID AND meta.meta_key = 'conf_sch_event_speaker' AND meta.post_id = %s
			INNER JOIN {$wpdb->posts} schedule ON schedule.ID = meta.post_id AND schedule.post_type = 'schedule'
			WHERE speakers.post_type = 'speakers'", $session_id ) );
	}

	/**
	 * Does this speaker have a partner?
	 * Returns the primary speaker ID. They will
	 * be the only speaker who can edit the
	 * session information.
	 */
	public function get_form_session_primary_speaker( $session_id ) {
		global $wpdb;

		// Make sure we have a session ID.
		if ( ! $session_id ) {
			$session_id = $this->get_form_speaker_id();
		}

		if ( ! $session_id ) {
			return 0;
		}

		$primary_speaker_id = $wpdb->get_var( $wpdb->prepare( "SELECT speakers.ID FROM {$wpdb->posts} speakers
			INNER JOIN {$wpdb->postmeta} meta ON meta.meta_value = speakers.ID AND meta.meta_key = 'conf_sch_event_speaker' AND meta.post_id = %s
			INNER JOIN {$wpdb->posts} schedule ON schedule.ID = meta.post_id AND schedule.post_type = 'schedule'
			WHERE speakers.post_type = 'speakers' ORDER BY speakers.ID ASC LIMIT 1", $session_id ) );

		return $primary_speaker_id > 0 ? $primary_speaker_id : 0;
	}

	/**
	 * Registers our plugins's taxonomies.
	 */
	public function register_taxonomies() {

		// Define the labels for the session technical taxonomy.
		$session_technical_labels = array(
			'name'                          => _x( 'Technical Levels', 'Taxonomy General Name', 'conf-schedule' ),
			'singular_name'                 => _x( 'Technical Level', 'Taxonomy Singular Name', 'conf-schedule' ),
			'menu_name'                     => __( 'Technical Levels', 'conf-schedule' ),
			'all_items'                     => __( 'All Technical Levels', 'conf-schedule' ),
			'new_item_name'                 => __( 'New Technical Level', 'conf-schedule' ),
			'add_new_item'                  => __( 'Add New Technical Level', 'conf-schedule' ),
			'edit_item'                     => __( 'Edit Technical Level', 'conf-schedule' ),
			'update_item'                   => __( 'Update Technical Level', 'conf-schedule' ),
			'view_item'                     => __( 'View Technical Level', 'conf-schedule' ),
			'separate_items_with_commas'    => __( 'Separate technical levels with commas', 'conf-schedule' ),
			'add_or_remove_items'           => __( 'Add or remove technical levels', 'conf-schedule' ),
			'choose_from_most_used'         => __( 'Choose from the most used technical levels', 'conf-schedule' ),
			'popular_items'                 => __( 'Popular technical levels', 'conf-schedule' ),
			'search_items'                  => __( 'Search Technical Levels', 'conf-schedule' ),
			'not_found'                     => __( 'No technical levels found.', 'conf-schedule' ),
			'no_terms'                      => __( 'No technical levels', 'conf-schedule' ),
		);

		// Define the arguments for the session technical taxonomy.
		$session_technical_args = array(
			'labels'                => $session_technical_labels,
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'show_admin_column'     => true,
			'show_in_nav_menus'     => true,
			'show_tagcloud'         => false,
			//'meta_box_cb'         => 'post_categories_meta_box',
			'show_in_rest'          => true,
		);

		// Register the session technical taxonomy.
		register_taxonomy( 'session_technical', array( 'schedule' ), $session_technical_args );

	}

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
	 * Populate the session survey form.
	 *
	 * @access  public
	 * @param   $form - array - the form information.
	 * @return  array - the filtered form.
	 */
	public function populate_session_survey_form( $form ) {

		// Get the post.
		$session_id = get_query_var( 'session' );
		if ( ! $session_id ) {
			return $form;
		}

		// Get session information.
		$session_post = get_post( $session_id );
		if ( ! $session_post ) {
			return $form;
		}

		// Loop through the fields.
		foreach ( $form['fields'] as &$field ) {

			switch ( $field->inputName ) {

				case 'sessiontitle':

					// Get the title.
					$session_title = get_the_title( $session_id );
					if ( ! empty( $session_title ) ) {

						// Set title.
						$field->defaultValue = $session_title;

						// Add CSS class so read only.
						$field->cssClass .= ' gf-read-only';

					}

					break;

				case 'speakername':

					$event_speaker_ids = get_post_meta( $session_id, 'conf_sch_event_speaker', false );
					if ( ! empty( $event_speaker_ids ) ) {

						// Get speakers info.
						$speakers = array();
						foreach ( $event_speaker_ids as $speaker_id ) {
							$speakers[] = get_the_title( $speaker_id );
						}

						// If we have speakers...
						if ( ! empty( $speakers ) ) {

							// Set speakers.
							$field->defaultValue = implode( ', ', $speakers );

							// Add CSS class so read only.
							$field->cssClass .= ' gf-read-only';

						}
					}

					break;
			}
		}

		?>
		<script type="text/javascript">
			jQuery(document).ready(function(){
				jQuery('li.gf-read-only input').attr('readonly','readonly');
			});
		</script>
		<?php

		return $form;
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

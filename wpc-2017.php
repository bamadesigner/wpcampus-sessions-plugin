<?php

/**
 * Plugin Name:       WPCampus 2017
 * Plugin URI:        https://2017.wpcampus.org
 * Description:       Holds functionality for the WPCampus 2017 website.
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

class WPCampus_2017 {

	/**
	 * Holds the class instance.
	 *
	 * @access	private
	 * @var		WPCampus_2017
	 */
	private static $instance;

	/**
	 * Returns the instance of this class.
	 *
	 * @access  public
	 * @return	WPCampus_2017
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			$className = __CLASS__;
			self::$instance = new $className;
		}
		return self::$instance;
	}

	/**
	 * Warming up the engine.
	 */
	protected function __construct() {

		// Filter field values.
		add_filter( 'gform_field_value', array( $this, 'filter_field_value' ), 10, 3 );

		// Populate field choices.
		add_filter( 'gform_pre_render', array( $this, 'populate_field_choices' ) );
		add_filter( 'gform_pre_validation', array( $this, 'populate_field_choices' ) );
		add_filter( 'gform_pre_submission_filter', array( $this, 'populate_field_choices' ) );
		add_filter( 'gform_admin_pre_render', array( $this, 'populate_field_choices' ) );

		// Custom process the user registration form
		//add_action( 'gform_user_registered', array( $this, 'after_user_registration_submission' ), 10, 3 );

		// Convert get involved form entries to CPT upon submission
		//add_action( 'gform_after_submission_1', array( $this, 'get_involved_sub_convert_to_post' ), 10, 2 );

		// Process the "Submit Editorial Idea" form submissions
		//add_action( 'gform_after_submission_15', array( $this, 'process_editorial_idea_form' ), 10, 2 );

	}

	/**
	 * Method to keep our instance from being cloned.
	 *
	 * @access	private
	 * @return	void
	 */
	private function __clone() {}

	/**
	 * Method to keep our instance from being unserialized.
	 *
	 * @access	private
	 * @return	void
	 */
	private function __wakeup() {}

	/**
	 * Get post created from entry.
	 */
	public function get_entry_post( $entry_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT posts.*, meta.meta_value AS gf_entry_id FROM {$wpdb->posts} posts INNER JOIN {$wpdb->postmeta} meta ON meta.post_id = posts.ID AND meta.meta_key = 'gf_entry_id' AND meta.meta_value = %s", $entry_id ) );
	}

	/**
	 * Filter field values.
	 */
	public function filter_field_value( $value, $field, $name ) {

		switch( $name ) {

			// Get user information.
			case 'user_firstname':
			case 'user_lastname':
			case 'user_email':
			case 'user_url':

				// Get the current user.
				$current_user = wp_get_current_user();
				if ( ! empty( $current_user->{$name} ) ) {
					return $current_user->{$name};
				}

				break;

			// Get user meta.
			case 'twitter':
				return get_user_meta( get_current_user_id(), $name, true );

			// Populate the current user ID
			case 'userid':
				return get_current_user_id();

		}

		return $value;
	}

	/**
	 * Dynamically populate field choices.
	 */
	public function populate_field_choices( $form ) {

		foreach ( $form['fields'] as &$field ) {

			switch ( $field->inputName ) {

				// The "Session Categories" form field
				case 'sessioncats':

					// Get the terms
					$terms = get_terms( array(
						'taxonomy'      => 'session_categories',
						'hide_empty'    => false,
						'orderby'       => 'name',
						'order'         => 'ASC',
						'fields'        => 'all',
					) );
					if ( ! empty( $terms ) ) {

						// Add the terms as choices
						$choices = array();
						$inputs = array();

						$term_index = 1;
						foreach ( $terms as $term ) {

							// Add the choice
							$choices[] = array(
								'text'  => $term->name,
								'value' => $term->term_id,
							);

							// Add the input
							$inputs[] = array(
								'id' => $field->id . '.' . $term_index,
								'label' => $term->name,
							);

							$term_index++;

						}

						// Assign the new choices and inputs
						$field->choices = $choices;
						$field->inputs = $inputs;

					}

					break;

			}

		}

		return $form;
	}

	/**
	 * Custom process the user registration form.

	public function after_user_registration_submission( $user_id, $feed, $entry ) {

		// If entry is the ID, get the entry
		if ( is_numeric( $entry ) && $entry > 0 ) {
			$entry = GFAPI::get_entry( $entry );
		}

		// Get the form
		$form = false;
		if ( isset( $feed['form_id'] ) && $feed['form_id'] > 0 ) {
			$form = GFAPI::get_form( $feed['form_id'] );
		}

		// Make sure we have some info
		if ( ! $entry || ! $form ) {
			return false;
		}

		// Process one field at a time
		foreach( $form[ 'fields']  as $field ) {

			// Process fields according to admin label
			switch( $field[ 'adminLabel' ] ) {

				case 'wpcsubjects':

					// Get all the user defined subjects and place in array
					$user_subjects = array();
					foreach( $field->inputs as $input ) {
						if ( $this_data = rgar( $entry, $input['id'] ) ) {
							$user_subjects[] = $this_data;
						}
					}

					// Make sure we have a subjects
					if ( ! empty( $user_subjects ) ) {

						// Make sure its all integers
						$user_subjects = array_map( 'intval', $user_subjects );

						// Set the terms for the user
						wp_set_object_terms( $user_id, $user_subjects, 'subjects', false );

					}

					break;

			}

		}

	}*/

	/**
	 * Convert get involved form entries to CPT upon submission.

	public function get_involved_sub_convert_to_post( $entry, $form ) {

		// Convert this entry to a post
		$this->convert_entry_to_post( $entry, $form );

	}*/

	/**
	 * Process specific form entry to convert to CPT.
	 *
	 * Can pass entry or form object or entry or form ID.

	public function convert_entry_to_post( $entry, $form ) {

		// If ID, get the entry
		if ( is_numeric( $entry ) && $entry > 0 ) {
			$entry = GFAPI::get_entry( $entry );
		}

		// If ID, get the form
		if ( is_numeric( $form ) && $form > 0 ) {
			$form = GFAPI::get_form( $form );
		}

		// Make sure we have some info
		if ( ! $entry || ! $form ) {
			return false;
		}

		// Set the entry id
		$entry_id = $entry['id'];

		// First, check to see if the entry has already been processed
		$entry_post = $this->get_entry_post( $entry_id );

		// If this entry has already been processed, then skip
		if ( $entry_post && isset( $entry_post->ID ) ) {
			return false;
		}

		*//*
		 * Fields to store in post meta.
		 *
		 * Names will be used dynamically
		 * when processing fields below.
		 *//*
		$fields_to_store = array(
			'name',
			'involvement',
			'sessions',
			'event_time',
			'email',
			'status',
			'employer',
			'attend_preference',
			'traveling_city',
			'traveling_state',
			'traveling_country',
			'traveling_latitude',
			'traveling_longitude',
			'slack_invite',
			'slack_email'
		);

		// Process one field at a time
		foreach( $form[ 'fields']  as $field ) {

			// Set the admin label
			$admin_label = strtolower( preg_replace( '/\s/i', '_', $field[ 'adminLabel' ] ) );

			// Only process if one of our fields
			// We need to process traveling_from but not store it in post meta which is why it's not in the array
			if ( ! in_array( $admin_label, array_merge( $fields_to_store, array( 'traveling_from' ) ) ) ) {
				continue;
			}

			// Process fields according to admin label
			switch( $admin_label ) {

				case 'name':

					// Get name parts
					$first_name = null;
					$last_name = null;

					// Process each name part
					foreach( $field->inputs as $input ) {
						$name_label = strtolower( $input['label'] );
						switch( $name_label ) {
							case 'first':
							case 'last':
								${$name_label.'_name'} = rgar( $entry, $input['id'] );
								break;
						}
					}

					// Build name to use when creating post
					$name = trim( "{$first_name} {$last_name}" );

					break;

				case 'involvement':
				case 'sessions':
				case 'event_time':

					// Get all the input data and place in array
					${$admin_label} = array();
					foreach( $field->inputs as $input ) {

						if ( $this_data = rgar( $entry, $input['id'] ) ) {
							${$admin_label}[] = $this_data;
						}

					}

					break;

				case 'traveling_from':

					// Get all the input data and place in array
					${$admin_label} = array();
					foreach( $field->inputs as $input ) {

						// Create the data index
						$input_label = strtolower( preg_replace( '/\s/i', '_', preg_replace( '/\s\/\s/i', '_', $input['label'] ) ) );

						// Change to simply state
						if ( 'state_province' == $input_label ) {
							$input_label = 'state';
						}

						// Store data
						if ( $this_data = rgar( $entry, $input['id'] ) ) {
							${"traveling_{$input_label}"} = $this_data;
						}

						// Store all traveling data in an array
						${$admin_label}[$input_label] = $this_data;

					}

					// Create string of traveling data
					$traveling_string = preg_replace( '/[\s]{2,}/i', ' ', implode( ' ', ${$admin_label} ) );

					// Get latitude and longitude
					$traveling_lat_long = wpcampus_plugin()->get_lat_long( $traveling_string );
					if ( ! empty( $traveling_lat_long ) ) {

						// Store data (will be stored in post meta later)
						$traveling_latitude = isset( $traveling_lat_long->lat ) ? $traveling_lat_long->lat : false;
						$traveling_longitude = isset( $traveling_lat_long->lng ) ? $traveling_lat_long->lng : false;

					}

					break;

				// Get everyone else
				default:

					// Get field value
					${$admin_label} = rgar( $entry, $field->id );

					break;

			}

		}

		// Create entry post title
		$post_title = "Entry #{$entry_id}";

		// Add name
		if ( ! empty( $name ) ) {
			$post_title .= " - {$name}";
		}

		// Create entry
		$new_entry_post_id = wp_insert_post( array(
			'post_type' => 'wpcampus_interest',
			'post_status' => 'publish',
			'post_title' => $post_title,
			'post_content' => '',
		) );
		if ( $new_entry_post_id > 0 ) {

			// Store entry ID in post
			update_post_meta( $new_entry_post_id, 'gf_entry_id', $entry_id );

			// Store post ID in the entry
			GFAPI::update_entry_property( $entry_id, 'post_id', $new_entry_post_id );

			// Store fields
			foreach( $fields_to_store as $field_name ) {
				update_post_meta( $new_entry_post_id, $field_name, ${$field_name} );
			}

			return true;

		}

		return false;
	}*/

	/**
	 * Process the editorial idea form submissions,

	public function process_editorial_idea_form( $entry, $form ) {

		// Only if the editorial plugin exists
		if ( ! function_exists( 'wpcampus_editorial' ) ) {
			return;
		}

		// Build the topic parameters
		$topic_params = array();

		// Will hold the subjects
		$wpc_subjects = array();

		// Process each form field by their admin label
		foreach( $form['fields'] as $field ) {
			switch( $field->adminLabel ) {

				case 'wpcsubjects':

					// Get all of the subjects and place in array
					$wpc_subjects = array();
					foreach( $field->inputs as $input ) {
						if ( $this_data = rgar( $entry, $input['id'] ) ) {
							$wpc_subjects[] = $this_data;
						}
					}
					break;

				case 'Topic Content':

					// Get the data
					$topic_desc = rgar( $entry, $field->id );
					if ( ! empty( $topic_desc ) ) {

						// Sanitize the data
						$topic_desc = sanitize_text_field( $topic_desc );

						// Store in topic post
						$topic_params['post_content'] = $topic_desc;

					}
					break;

				case 'Topic Title':

					// Get the data
					$topic_desc = rgar( $entry, $field->id );
					if ( ! empty( $topic_desc ) ) {

						// Sanitize the data
						$topic_desc = sanitize_text_field( $topic_desc );

						// Store in topic post
						$topic_params['post_title'] = $topic_desc;

					}
					break;

			}

		}

		// Create the topic
		$topic_id = wpcampus_editorial()->create_topic( $topic_params );
		if ( ! is_wp_error( $topic_id ) && $topic_id > 0 ) {

			// Store the entry ID
			add_post_meta( $topic_id, 'gf_entry_id', $entry['id'], true );

			// Assign subjects for topic
			if ( ! empty( $wpc_subjects ) ) {

				// Make sure its all integers
				$wpc_subjects = array_map( 'intval', $wpc_subjects );

				// Set the terms for the user
				wp_set_object_terms( $topic_id, $wpc_subjects, 'subjects', false );

			}

		}

	}*/

}

/**
 * Returns the instance of our main WPCampus_2017 class.
 *
 * Will come in handy when we need to access the
 * class to retrieve data throughout the plugin.
 *
 * @access	public
 * @return	WPCampus_2017
 */
function wpcampus_2017() {
	return WPCampus_2017::instance();
}

// Let's get this show on the road
wpcampus_2017();
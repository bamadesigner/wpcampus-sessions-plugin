<?php

/**
 * Holds all of our admin functionality.
 */
class WPCampus_Sessions_Admin {

	/**
	 * Holds the class instance.
	 *
	 * @access	private
	 * @var		WPCampus_Sessions_Admin
	 */
	private static $instance;

	/**
	 * Returns the instance of this class.
	 *
	 * @access  public
	 * @return	WPCampus_Sessions_Admin
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

		// Add meta boxes.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), -1, 2 );

		// Add custom columns.
		add_filter( 'manage_posts_columns', array( $this, 'add_posts_columns' ), 10, 2 );

		// Populate our custom admin columns.
		add_action( 'manage_speakers_posts_custom_column', array( $this, 'populate_posts_columns' ), 10, 2 );

		// Manually update session information from speaker confirmations.
		add_action( 'admin_init', array( $this, 'update_sessions_from_speaker_confirmations' ) );

	}

	/**
	 * Method to keep our instance
	 * from being cloned or unserialized.
	 *
	 * @access	private
	 * @return	void
	 */
	private function __clone() {}
	private function __wakeup() {}

	/**
	 * Print the speaker's status.
	 *
	 * @access  public
	 * @since   1.0.0
	 * @param   $post_id - int - the speaker ID.
	 * @return  void
	 */
	public function print_speaker_status( $post_id ) {

		// Get the status.
		$status = get_post_meta( $post_id, 'conf_sch_speaker_status', true );

		// Print the status.
		if ( 'confirmed' == $status ) {
			?><span style="color:green;">Confirmed</span><?php
		} else if ( 'declined' == $status ) {
			?><span style="color:red;">Declined</span><?php
		} else if ( 'selected' == $status ) {
			?><strong>Pending</strong><?php
		} else {
			?><em>Not selected</em><?php
		}
	}

	/**
	 * Adds our admin meta boxes.
	 *
	 * @access  public
	 * @since   1.0.0
	 * @param   $post_type - string - the post type
	 * @param   $post - WP_Post - the post object
	 * @return  void
	 */
	public function add_meta_boxes( $post_type, $post ) {

		switch ( $post_type ) {

			case 'speakers':

				// WPCampus Speaker Information.
				add_meta_box(
					'wpc-speaker-information',
					sprintf( __( '%s Speaker Information', 'conf-schedule' ), 'WPCampus' ),
					array( $this, 'print_meta_boxes' ),
					$post_type,
					'normal',
					'high'
				);

				break;
		}
	}

	/**
	 * Prints the content in our admin meta boxes.
	 *
	 * @access  public
	 * @since   1.0.0
	 * @param   $post - WP_Post - the post object
	 * @param   $metabox - array - meta box arguments
	 * @return  void
	 */
	public function print_meta_boxes( $post, $metabox ) {

		switch ( $metabox['id'] ) {

			case 'wpc-speaker-information':
				$this->print_wpc_speaker_information( $post->ID );
				break;
		}
	}

	/**
	 * Add custom admin columns.
	 *
	 * @access  public
	 * @since   1.0.0
	 * @param   $columns - array - An array of column names.
	 * @param   $post_type - string - The post type slug.
	 * @return  array - the filtered column names.
	 */
	public function add_posts_columns( $columns, $post_type ) {

		// Only for these post types.
		if ( ! in_array( $post_type, array( 'speakers' ) ) ) {
			return $columns;
		}

		// Store new columns.
		$new_columns = array();

		foreach ( $columns as $key => $value ) {

			// Add to new columns.
			$new_columns[ $key ] = $value;

			// Add custom columns after title.
			if ( 'title' == $key ) {
				$new_columns['wpc-speaker-status'] = 'Status';
			}
		}

		return $new_columns;
	}

	/**
	 * Populate our custom admin columns.
	 *
	 * @access  public
	 * @since   1.0.0
	 * @param   $column - string - The name of the column to display.
	 * @param   $post_id - int - The current post ID.
	 * @return  void
	 */
	public function populate_posts_columns( $column, $post_id ) {

		// Add data for our custom date column.
		if ( 'wpc-speaker-status' == $column ) {

			// Print the speaker's status.
			$this->print_speaker_status( $post_id );

		}
	}

	/**
	 * Print the WPCampus speaker information.
	 *
	 * @access  public
	 * @since   1.0.0
	 * @param   int - $post_id - the ID of the event
	 * @return  void
	 */
	public function print_wpc_speaker_information( $post_id ) {

		// Get the information.
		$status = get_post_meta( $post_id, 'conf_sch_speaker_status', true );
		$technology = get_post_meta( $post_id, 'wpc_speaker_technology', true );
		$video_release = get_post_meta( $post_id, 'wpc_speaker_video_release', true );
		$unavailability = get_post_meta( $post_id, 'wpc_speaker_unavailability', true );
		$arrival = get_post_meta( $post_id, 'wpc_speaker_arrival', true );
		$special_requests = get_post_meta( $post_id, 'wpc_speaker_special_requests', true );

		?>
		<p><strong>Status:</strong><br /><?php

			// Print the speaker's status.
			$this->print_speaker_status( $post_id );

		?></p>
		<p><strong>Technology:</strong><br /><?php echo $technology ?: '<em>This speaker did not specify which technology they\'ll use.</em>'; ?></p>
		<p><strong>Video Release:</strong><br /><?php echo $video_release ?: '<em>This speaker did not specify their video release agreement.</em>'; ?></p>
		<p><strong>Unavailability:</strong><br /><?php echo $unavailability ?: '<em>This speaker did not specify any unavailability.</em>'; ?></p>
		<p><strong>Arrival:</strong><br /><?php echo $arrival ?: '<em>This speaker did not specify their arrival time.</em>'; ?></p>
		<p><strong>Special Requests:</strong><br /><?php echo $special_requests ?: '<em>This speaker did not specify any special requests.</em>'; ?></p>
		<?php
	}

	/**
	 * Manually update session information
	 * from ALL speaker confirmations.
	 *
	 * @TODO:
	 * - create an admin button for this?
	 */
	public function update_sessions_from_speaker_confirmations() {

		// Make sure GFAPI exists.
		if ( ! class_exists( 'GFAPI' ) ) {
			return false;
		}

		/*
		 * !!!!!!
		 * @TODO
		 * !!!!!!
		 *
		 * Keep from running on every page load.
		 *
		 * !!!!!
		 */

		// ID for speaker confirmation form.
		$form_id = 8;

		// What entry should we start on?
		$entry_offset = 0;

		// How many entries?
		$entry_count = 100;

		// Get entries.
		$entries = GFAPI::get_entries( $form_id, array( 'status' => 'active' ), array(), array( 'offset' => $entry_offset, 'page_size' => $entry_count ) );
		if ( ! empty( $entries ) ) {

			// Get form data.
			$form = GFAPI::get_form( $form_id );

			// Process each entry.
			foreach ( $entries as $entry ) {
				$this->update_session_from_speaker_confirmation( $entry, $form );
			}
		}
	}

	/**
	 * Update session information from
	 * a SPECIFIC speaker confirmation.
	 *
	 * Can pass entry ID or object.
	 * Can oass form ID or object.
	 *
	 * @TODO:
	 * - create an admin button for this?
	 */
	public function update_session_from_speaker_confirmation( $entry, $form ) {
		global $wpdb;

		// Make sure GFAPI exists.
		if ( ! class_exists( 'GFAPI' ) ) {
			return false;
		}

		// If ID, get the entry.
		if ( is_numeric( $entry ) && $entry > 0 ) {
			$entry = GFAPI::get_entry( $entry );
		}

		// If ID, get the form.
		if ( is_numeric( $form ) && $form > 0 ) {
			$form = GFAPI::get_form( $form );
		}

		// Make sure we have some info.
		if ( ! $entry || ! $form ) {
			return false;
		}

		// Set the entry id.
		$entry_id = $entry['id'];

		// Will hold the speaker and session ID.
		$speaker_id = 0;
		$session_id = null;

		// Is the meta value the entry ID is being stored under.
		$speaker_post_type = 'speakers';
		$speaker_entry_meta_key = 'gf_speaker_conf_entry_id';

		// Get the entry's speaker ID.
		foreach ( $form[ 'fields']  as $field ) {

			// Get out of here if speaker and session ID have been checked.
			if ( isset( $speaker_id ) && isset( $session_id ) ) {
				break;
			}

			// Get speaker and session IDs.
			if ( 'speaker' == $field->inputName ) {
				$speaker_id = rgar( $entry, $field->id );
				if ( ! ( $speaker_id > 0 ) ) {
					$speaker_id = null;
				}
			} else if ( 'session' == $field->inputName ) {
				$session_id = rgar( $entry, $field->id );
				if ( ! ( $session_id > 0 ) ) {
					$session_id = null;
				}
			}
		}

		// If no speaker ID, get out of here.
		if ( ! $speaker_id ) {
			return false;
		}

		// Check to see if the speaker has already been processed.
		$speaker_post = $wpdb->get_row( $wpdb->prepare( "SELECT posts.*, meta.meta_value AS gf_entry_id FROM {$wpdb->posts} posts INNER JOIN {$wpdb->postmeta} meta ON meta.post_id = posts.ID AND meta.meta_key = %s AND meta.meta_value = %s WHERE posts.post_type = %s", $speaker_entry_meta_key, $entry_id, $speaker_post_type ) );

		// If this speaker has already been processed, then skip.
		if ( ! empty( $speaker_post->ID ) && $speaker_post->ID == $speaker_id ) {
			return false;
		}

		// Straightforward input to post meta fields.
		$speaker_meta_input_fields = array(
			'speaker_website'           => 'conf_sch_speaker_url',
			'speaker_company'           => 'conf_sch_speaker_company',
			'speaker_company_website'   => 'conf_sch_speaker_company_url',
			'speaker_position'          => 'conf_sch_speaker_position',
			'speaker_twitter'           => 'conf_sch_speaker_twitter',
			'speaker_linkedin'          => 'conf_sch_speaker_linkedin',
			'speaker_email'             => 'conf_sch_speaker_email',
			'speaker_phone'             => 'conf_sch_speaker_phone',
		);

		// Will hold speaker post information to update.
		$update_speaker_post = array();

		// Will hold session post information to update.
		$update_session_post = array();

		// Process one field at a time.
		foreach ( $form[ 'fields'] as $field ) {

			// Don't worry about these types.
			if ( in_array( $field->type, array( 'html', 'section' ) ) ) {
				continue;
			}

			// Get the field value.
			$field_value = rgar( $entry, $field->id );

			// Get confirmation status.
			if ( 'Speaker Confirmation' == $field->label ) {
				if ( ! empty( $field_value ) ) {

					// Set the status.
					if ( in_array( $field_value, array( 'I can attend and speak at WPCampus 2017' ) ) ) {
						$speaker_status = 'confirmed';
					} else {
						$speaker_status = 'declined';
					}

					// Update the speaker's status.
					update_post_meta( $speaker_id, 'conf_sch_speaker_status', $speaker_status );

				}
			} else if ( ! empty( $speaker_meta_input_fields[ $field->inputName ] ) ) {

				// Update the speaker's post meta from the input.
				if ( ! empty( $field_value ) ) {
					update_post_meta( $speaker_id, $speaker_meta_input_fields[ $field->inputName ], sanitize_text_field( $field_value ) );
				}
			} else if ( 'speaker_name' == $field->inputName ) {

				// Set the speaker title to be updated.
				if ( ! empty( $field_value ) ) {
					$update_speaker_post['post_title'] = sanitize_text_field( $field_value );
				}
			} else if ( 'speaker_bio' == $field->inputName ) {

				// Set the speaker biography to be updated.
				if ( ! empty( $field_value ) ) {
					$update_speaker_post['post_content'] = $this->strip_content_tags( $field_value );
				}
			} else if ( 'session_title' == $field->inputName ) {

				// Set the session title to be updated.
				if ( ! empty( $field_value ) ) {
					$update_session_post['post_title'] = sanitize_text_field( $field_value );
				}
			} else if ( 'session_desc' == $field->inputName ) {

				// Set the session description to be updated.
				if ( ! empty( $field_value ) ) {
					$update_session_post['post_content'] = $this->strip_content_tags( $field_value );
				}
			} else if ( 'Technology' == $field->adminLabel ) {

				// Update the speaker's technology.
				if ( ! empty( $field_value ) ) {
					update_post_meta( $speaker_id, 'wpc_speaker_technology', sanitize_text_field( $field_value ) );
				}
			} else if ( 'Video Release' == $field->adminLabel ) {

				// Update the speaker's video release.
				if ( ! empty( $field_value ) ) {
					$allowable_tags = '<a><ul><ol><li><em><strong><b><br><br />';
					update_post_meta( $speaker_id, 'wpc_speaker_video_release', $this->strip_content_tags( $field_value, $allowable_tags ) );
				}
			} else if ( 'Special Requests' == $field->adminLabel ) {

				// Update the speaker's special requests.
				if ( ! empty( $field_value ) ) {
					update_post_meta( $speaker_id, 'wpc_speaker_special_requests', $this->strip_content_tags( $field_value ) );
				}
			} else if ( 'Arrival' == $field->adminLabel ) {

				// Update the speaker's arrival.
				if ( ! empty( $field_value ) ) {
					update_post_meta( $speaker_id, 'wpc_speaker_arrival', sanitize_text_field( $field_value ) );
				}
			} else if ( 'Session Unavailability' == $field->label ) {

				// Get all the input data and place in array.
				$unavailability = array();
				foreach ( $field->inputs as $input ) {
					if ( $this_data = rgar( $entry, $input['id'] ) ) {
						$unavailability[] = sanitize_text_field( $this_data );
					}
				}

				// Update the speaker's unavailability.
				if ( ! empty( $unavailability ) ) {
					update_post_meta( $speaker_id, 'wpc_speaker_unavailability', implode( ', ', $unavailability ) );
				}
			} else if ( in_array( $field->inputName, array( 'session_categories', 'session_technical' ) ) ) {

				// Make sure we have a session ID.
				if ( $session_id > 0 ) {

					// Get all the input data and place in array.
					$term_ids = array();
					foreach ( $field->inputs as $input ) {
						if ( $this_data = rgar( $entry, $input['id'] ) ) {
							$term_ids[] = $this_data;
						}
					}

					// Make sure they're integers.
					$term_ids = array_map( 'intval', $term_ids );

					// Update the terms.
					wp_set_post_terms( $session_id, $term_ids, $field->inputName );

				}
			}
		}

		// Update the speaker post.
		if ( $speaker_id > 0 && ! empty( $update_speaker_post ) ) {

			// Add the speaker ID.
			$update_speaker_post['ID'] = $speaker_id;
			$update_speaker_post['post_type'] = 'speakers';

			// Update the speaker post.
			wp_update_post( $update_speaker_post );

		}

		// Update the session post.
		if ( $session_id > 0 && ! empty( $update_session_post ) ) {

			// Add the session ID.
			$update_session_post['ID'] = $session_id;
			$update_session_post['post_type'] = 'schedule';

			// Update the session post.
			wp_update_post( $update_session_post );

		}

		// Store entry ID in post.
		update_post_meta( $speaker_id, $speaker_entry_meta_key, $entry_id );

		return true;
	}

	/**
	 * Properly strip HTML tags including script and style.
	 *
	 * Adapted from wp_strip_all_tags().
	 *
	 * @param   $string - string - String containing HTML tags.
	 * @param   $allowed_tags - string - the tags we don't want to strip.
	 * @return  string - The processed string.
	 */
	public function strip_content_tags( $string, $allowed_tags = '<a><ul><ol><li><em><strong>' ) {

		// Remove <script> and <style> tags.
		$string = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $string );

		// Remove all but allowed tags.
		$string = strip_tags( $string, $allowed_tags );

		// Remove line breaks.
		$string = preg_replace( '/[\r\n\t ]+/', ' ', $string );

		return trim( $string );
	}
}

/**
 * Returns the instance of our main WPCampus_Sessions_Admin class.
 *
 * Will come in handy when we need to access the
 * class to retrieve data throughout the plugin.
 *
 * @access	public
 * @return	WPCampus_Sessions_Admin
 */
function wpcampus_sessions_admin() {
	return WPCampus_Sessions_Admin::instance();
}

// Let's get this show on the road
wpcampus_sessions_admin();

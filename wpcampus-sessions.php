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

		// Filter field values.
		add_filter( 'gform_field_value', array( $this, 'filter_field_value' ), 10, 3 );

		// Populate field choices.
		add_filter( 'gform_pre_render', array( $this, 'populate_field_choices' ) );
		add_filter( 'gform_pre_validation', array( $this, 'populate_field_choices' ) );
		add_filter( 'gform_pre_submission_filter', array( $this, 'populate_field_choices' ) );
		add_filter( 'gform_admin_pre_render', array( $this, 'populate_field_choices' ) );

		// Process 2017 speaker application.
		add_action( 'gform_after_submission_1', array( $this, 'process_2017_speaker_application' ), 10, 2 );

		// Process 2017 speaker confirmation.
		add_filter( 'gform_get_form_filter_8', array( $this, 'filter_2017_speaker_confirmation_form' ), 100, 2 );
		//add_action( 'gform_after_submission_8', array( $this, 'process_2017_speaker_confirmation' ), 10, 2 );

		// Register taxonomies.
		add_action( 'init', array( $this, 'register_taxonomies' ), 0 );

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
	 * Check the confirmation ID for a speaker.
	 */
	public function check_form_confirmation_id( $speaker_id = 0 ) {

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
		$speaker_confirmation_id = get_post_meta( $speaker_id, 'conf_sch_confirmation_id', true );

		return $form_confirmation_id == $speaker_confirmation_id;
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
	 * Filter field values.
	 */
	public function filter_field_value( $value, $field, $name ) {
		global $blog_id;

		// Is 2017 speaker confirmation form?
		$is_2017_speaker_confirm = ( 7 == $blog_id && 8 == $field->formId );
		if ( $is_2017_speaker_confirm ) {

			// Get the speaker and session ID.
			$speaker_id = $this->get_form_speaker_id();
			$session_id = $this->get_form_session_id();
			if ( $speaker_id > 0 && $session_id > 0 ) {

				// Get the speaker and session post.
				$speaker_post = $this->get_form_speaker_post( $speaker_id );
				$session_post = $this->get_form_session_post( $session_id );
				if ( ! empty( $speaker_post ) && ! empty( $session_post ) ) {

					switch ( $name ) {

						case 'speaker_primary':
							return $this->get_form_session_primary_speaker( $session_id );

						case 'speaker_name':
							return $speaker_post->post_title;

						case 'speaker_bio':
							return $speaker_post->post_content;

						case 'speaker_website':
							return get_post_meta( $speaker_id, 'conf_sch_speaker_url', true );

						case 'speaker_company':
							return get_post_meta( $speaker_id, 'conf_sch_speaker_company', true );

						case 'speaker_company_website':
							return get_post_meta( $speaker_id, 'conf_sch_speaker_company_url', true );

						case 'speaker_position':
							return get_post_meta( $speaker_id, 'conf_sch_speaker_position', true );

						case 'speaker_twitter':
							return get_post_meta( $speaker_id, 'conf_sch_speaker_twitter', true );

						case 'speaker_linkedin':
							return get_post_meta( $speaker_id, 'conf_sch_speaker_linkedin', true );

						case 'session_title':
							return ! empty( $session_post->post_title ) ? $session_post->post_title : null;

						case 'session_desc':
							return ! empty( $session_post->post_content ) ? $session_post->post_content : null;

					}
				}
			}

			// We don't want this form to use the values below.
			return $value;
		}

		switch ( $name ) {

			// Get user information.
			case 'speaker_first_name':
			case 'speaker_last_name':
			case 'speaker_email':
			case 'speaker_website':

				// Get the current user.
				$current_user = wp_get_current_user();

				// Return the user data.
				if ( 'speaker_first_name' == $name && ! empty( $current_user->user_firstname ) ) {
					return $current_user->user_firstname;
				} elseif ( 'speaker_last_name' == $name && ! empty( $current_user->user_lastname ) ) {
					return $current_user->user_lastname;
				} elseif ( 'speaker_email' == $name && ! empty( $current_user->user_email ) ) {
					return $current_user->user_email;
				} elseif ( 'speaker_website' == $name && ! empty( $current_user->user_url ) ) {
					return $current_user->user_url;
				}

				break;

			// Get user meta.
			case 'speaker_twitter':
				return get_user_meta( get_current_user_id(), 'twitter', true );

			// Populate the current user ID.
			case 'userid':
				return get_current_user_id();

		}

		return $value;
	}

	/**
	 * Dynamically populate field choices.
	 */
	public function populate_field_choices( $form ) {
		global $blog_id;

		// Is 2017 speaker confirmation form?
		$is_2017_speaker_confirm = ( 7 == $blog_id && 8 == $form['id'] );

		// Get the speaker and session ID.
		$speaker_id = $is_2017_speaker_confirm ? $this->get_form_speaker_id() : 0;
		$session_id = $is_2017_speaker_confirm ? $this->get_form_session_id() : 0;

		// Get the session's speakers.
		$session_speakers = $this->get_form_session_speakers( $session_id );

		/*
		 * Does this speaker have a partner?
		 * Get the primary speaker ID. They will
		 * be the only speaker who can edit the
		 * session information.
		 *
		 * If so, disable all session edit fields.
		 */
		$session_primary_speaker = $session_id > 0 ? $this->get_form_session_primary_speaker( $session_id ) : 0;

		foreach ( $form['fields'] as &$field ) {

			// Hide this message for single or primary speakers.
			if ( 'Session Edit Message' == $field->label && ! is_admin() ) {

				if ( count( $session_speakers ) < 2 ) {
					$field->type = 'hidden';
					$field->visibility = 'hidden';
				} else {

					// Get the primary speaker title.
					$session_primary_speaker_title = get_post_field( 'post_title', $session_primary_speaker );

					// Edit the content.
					$field->content .= '<p><em><strong>' . ( ( $session_primary_speaker == $speaker_id ) ? 'You have' : ( $session_primary_speaker_title . ' has' ) ) . ' the ability to edit your session information.</strong></em></p>';

				}

				// Wrap the content
				$field->content = '<div class="callout">' . $field->content . '</div>';

			}

			switch ( $field->inputName ) {

				// Hide if multiple speakers.
				case 'session_desc':
				case 'session_title':
					if ( $session_primary_speaker != $speaker_id && ! is_admin() ) {
						$field->type = 'hidden';
						$field->visibility = 'hidden';
					}
					break;

				// The "Session Categories" and "Session Technical" taxonomy form field.
				case 'session_categories':
				case 'session_technical':

					// Hide if multiple speakers.
					if ( $session_primary_speaker != $speaker_id ) {
						if ( ! is_admin() ) {
							$field->type = 'hidden';
							$field->visibility = 'hidden';
						}
					} else {

						// Get the terms.
						$terms = get_terms( array(
							'taxonomy'   => $field->inputName,
							'hide_empty' => FALSE,
							'orderby'    => 'name',
							'order'      => 'ASC',
							'fields'     => 'all',
						) );
						if ( ! empty( $terms ) ) {

							// Add the terms as choices.
							$choices = array();
							$inputs  = array();

							// Will hold selected terms.
							$selected_terms = array();

							// We need the speaker and session ID.
							if ( $speaker_id > 0 && $session_id > 0 ) {

								// Get the speaker's terms.
								$selected_terms = wp_get_object_terms( $session_id, $field->inputName, array( 'fields' => 'ids' ) );
								if ( empty( $terms ) || is_wp_error( $terms ) ) {
									$selected_terms = array();
								}
							}

							$term_index = 1;
							foreach ( $terms as $term ) {

								// Add the choice.
								$choices[] = array(
									'text'       => $term->name,
									'value'      => $term->term_id,
									'isSelected' => in_array( $term->term_id, $selected_terms ),
								);

								// Add the input.
								$inputs[] = array(
									'id'    => $field->id . '.' . $term_index,
									'label' => $term->name,
								);

								$term_index ++;

							}

							// Assign the new choices and inputs
							$field->choices = $choices;
							$field->inputs  = $inputs;

						}
					}

					break;
			}
		}

		return $form;
	}

	/**
	 * Process the WPCampus 2017 speaker application.
	 */
	public function process_2017_speaker_application( $entry, $form ) {
		global $blog_id;

		// Only on 2017 website.
		if ( 7 != $blog_id ) {
			return false;
		}

		// Make sure the form is active.
		if ( ! isset( $form['is_active'] ) || ! $form['is_active'] ) {
			return false;
		}

		// Set the entry ID.
		$entry_id = $entry['id'];

		// Make sure we have an entry ID.
		if ( ! $entry_id ) {
			return false;
		}

		// First, check to see if the entry has already been processed.
		$entry_post = wpcampus_forms()->get_entry_post( $entry_id );

		// If this entry has already been processed, then skip.
		if ( $entry_post && isset( $entry_post->ID ) ) {
			return false;
		}

		// Set the schedule post ID.
		$schedule_post_id = $entry['post_id'];

		// Hold speaker information.
		$speaker = array();
		$speaker2 = array();

		// Hold session information.
		$session = array();

		// Process one field at a time.
		foreach ( $form['fields'] as $field ) {

			// Skip certain types.
			if ( in_array( $field->type, array( 'section' ) ) ) {
				continue;
			}

			// Process names.
			if ( 'name' == $field->type ) {

				// Process each name part.
				foreach ( $field->inputs as $input ) {

					// Get the input value.
					$input_value = rgar( $entry, $input['id'] );

					switch ( $input['name'] ) {

						case 'speaker_first_name':
							$speaker['first_name'] = $input_value;
							break;

						case 'speaker2_first_name':
							$speaker2['first_name'] = $input_value;
							break;

						case 'speaker_last_name':
							$speaker['last_name'] = $input_value;
							break;

						case 'speaker2_last_name':
							$speaker2['last_name'] = $input_value;
							break;

					}
				}
			} elseif ( 'session_categories' == $field->inputName ) {

				// Get all the categories and place in array.
				$session['categories'] = array();

				foreach ( $field->inputs as $input ) {
					if ( $this_data = rgar( $entry, $input['id'] ) ) {
						$session['categories'][] = $this_data;
					}
				}

				// Make sure we have categories.
				if ( ! empty( $session['categories'] ) ) {

					// Make sure its all integers.
					$session['categories'] = array_map( 'intval', $session['categories'] );

				}
			} elseif ( 'session_technical' == $field->inputName ) {

				// Get all the skill levels and place in array.
				$session['levels'] = array();

				foreach ( $field->inputs as $input ) {
					if ( $this_data = rgar( $entry, $input['id'] ) ) {
						$session['levels'][] = $this_data;
					}
				}

				// Make sure we have levels.
				if ( ! empty( $session['levels'] ) ) {

					// Make sure its all integers.
					$session['levels'] = array_map( 'intval', $session['levels'] );

				}
			} else {

				// Get the field value.
				$field_value = rgar( $entry, $field->id );

				// Process the speaker photos.
				if ( 'Speaker Photo' == $field->adminLabel ) {

					$speaker['photo'] = $field_value;

				} elseif ( 'Speaker Two Photo' == $field->adminLabel ) {

					$speaker2['photo'] = $field_value;

				} else {

					// Process other input names.
					switch ( $field->inputName ) {

						case 'speaker_email':
							$speaker['email'] = $field_value;
							break;

						case 'speaker2_email':
							$speaker2['email'] = $field_value;
							break;

						case 'speaker_bio':
							$speaker['bio'] = $field_value;
							break;

						case 'speaker2_bio':
							$speaker2['bio'] = $field_value;
							break;

						case 'speaker_website':
							$speaker['website'] = $field_value;
							break;

						case 'speaker2_website':
							$speaker2['website'] = $field_value;
							break;

						case 'speaker_twitter':

							// Remove any non alphanumeric characters.
							$speaker['twitter'] = preg_replace( '/[^a-z0-9]/i', '', $field_value );
							break;

						case 'speaker2_twitter':

							// Remove any non alphanumeric characters.
							$speaker2['twitter'] = preg_replace( '/[^a-z0-9]/i', '', $field_value );
							break;

						case 'speaker_linkedin':
							$speaker['linkedin'] = $field_value;
							break;

						case 'speaker2_linkedin':
							$speaker2['linkedin'] = $field_value;
							break;

						case 'speaker_company':
							$speaker['company'] = $field_value;
							break;

						case 'speaker2_company':
							$speaker2['company'] = $field_value;
							break;

						case 'speaker_company_website':
							$speaker['company_website'] = $field_value;
							break;

						case 'speaker2_company_website':
							$speaker2['company_website'] = $field_value;
							break;

						case 'speaker_position':
							$speaker['position'] = $field_value;
							break;

						case 'speaker2_position':
							$speaker2['position'] = $field_value;
							break;

						case 'session_title':
							$session['title'] = $field_value;
							break;

						case 'session_desc':
							$session['desc'] = $field_value;
							break;

						case 'other_session_categories':
							$session['other_categories'] = $field_value;
							break;

					}
				}
			}
		}

		// If no schedule post was made, create a post.
		if ( ! $schedule_post_id ) {
			$schedule_post_id = wp_insert_post( array(
				'post_type'     => 'schedule',
				'post_status'   => 'pending',
				'post_title'    => $session['title'],
				'post_content'  => $session['desc'],
			));
		} else {

			// Otherwise, make sure the post is updated.
			$schedule_post_id = wp_insert_post( array(
				'ID'            => $schedule_post_id,
				'post_type'     => 'schedule',
				'post_status'   => 'pending',
				'post_title'    => $session['title'],
				'post_content'  => $session['desc'],
			));

		}

		// No point in continuing if no schedule post ID.
		if ( is_wp_error( $schedule_post_id ) || ! $schedule_post_id ) {
			return false;
		}

		// Set the categories for the session.
		if ( ! empty( $session['categories'] ) ) {
			wp_set_object_terms( $schedule_post_id, $session['categories'], 'session_categories', false );
		}

		// Set the "other" categories for the session.
		if ( ! empty( $session['other_categories'] ) ) {

			// Convert to array.
			$other_categories = explode( ',', $session['other_categories'] );
			if ( ! empty( $other_categories ) ) {

				// Will hold final term IDs.
				$other_category_ids = array();

				// Add term.
				foreach ( $other_categories as $new_term_string ) {

					// Create the term.
					$new_term = wp_insert_term( $new_term_string, 'session_categories' );
					if ( ! is_wp_error( $new_term ) && ! empty( $new_term['term_id'] ) ) {

						// Add to list to assign later.
						$other_category_ids[] = $new_term['term_id'];

					}
				}

				// Assign all new categories to session.
				if ( ! empty( $other_category_ids ) ) {
					wp_set_object_terms( $schedule_post_id, $other_category_ids, 'session_categories', false );
				}
			}
		}

		// Set the technical levels for the session.
		if ( ! empty( $session['levels'] ) ) {
			wp_set_object_terms( $schedule_post_id, $session['levels'], 'session_technical', false );
		}

		// Set the event type to "session".
		wp_set_object_terms( $schedule_post_id, 'session', 'event_types', false );

		// Store the GF entry ID for the schedule post.
		add_post_meta( $schedule_post_id, 'gf_entry_id', $entry_id, true );

		// Will hold the speaker post IDs for the schedule post.
		$schedule_post_speakers = array();

		// Create speaker posts.
		foreach ( array( $speaker, $speaker2 ) as $this_speaker ) {

			// Make sure they have an email.
			if ( empty( $this_speaker['email'] ) ) {
				continue;
			}

			// See if a WP user exists for their email.
			$wp_user = get_user_by( 'email', $this_speaker['email'] );

			// Build the speaker name.
			$name = '';

			// Build from form.
			if ( ! empty( $this_speaker['first_name'] ) ) {
				$name .= $this_speaker['first_name'];

				if ( ! empty( $this_speaker['last_name'] ) ) {
					$name .= ' ' . $this_speaker['last_name'];
				}
			}

			// If no name but found WP user, get from user data.
			if ( ! $name && is_a( $wp_user, 'WP_User' ) && ! empty( $wp_user->data->display_name ) ) {
				$name = $wp_user->data->display_name;
			}

			// No point if no name.
			if ( ! $name ) {
				continue;
			}

			// Create the speaker.
			$speaker_post_id = wp_insert_post( array(
				'post_type'     => 'speakers',
				'post_status'   => 'pending',
				'post_title'    => $name,
				'post_content'  => $this_speaker['bio'],
			));

			// Make sure the post was created before continuing.
			if ( ! $speaker_post_id ) {
				continue;
			}

			// Store the WordPress user ID.
			if ( ! empty( $wp_user->ID ) && $wp_user->ID > 0 ) {
				add_post_meta( $speaker_post_id, 'conf_sch_speaker_user_id', $wp_user->ID, true );
			}

			// Store speaker post meta.
			add_post_meta( $speaker_post_id, 'conf_sch_speaker_email', $this_speaker['email'], true );
			add_post_meta( $speaker_post_id, 'conf_sch_speaker_url', $this_speaker['website'], true );
			add_post_meta( $speaker_post_id, 'conf_sch_speaker_company', $this_speaker['company'], true );
			add_post_meta( $speaker_post_id, 'conf_sch_speaker_company_url', $this_speaker['company_website'], true );
			add_post_meta( $speaker_post_id, 'conf_sch_speaker_position', $this_speaker['position'], true );
			add_post_meta( $speaker_post_id, 'conf_sch_speaker_twitter', $this_speaker['twitter'], true );
			add_post_meta( $speaker_post_id, 'conf_sch_speaker_linkedin', $this_speaker['linkedin'], true );

			// Add the speaker photo.
			if ( ! empty( $this_speaker['photo'] ) ) {

				// Set variables for storage, fix file filename for query strings.
				preg_match( '/[^\?]+\.(jpe?g|png)\b/i', $this_speaker['photo'], $matches );
				if ( ! empty( $matches[0] ) ) {

					// Make sure we have the files we need.
					require_once( ABSPATH . 'wp-admin/includes/file.php' );
					require_once( ABSPATH . 'wp-admin/includes/image.php' );
					require_once( ABSPATH . 'wp-admin/includes/media.php' );

					// Download the file to temp location.
					$tmp_file = download_url( $this_speaker['photo'] );

					// Setup the file info.
					$file_array = array(
						'name'     => basename( $matches[0] ),
						'tmp_name' => $tmp_file,
					);

					// If no issues with the file...
					if ( ! is_wp_error( $file_array['tmp_name'] ) ) {

						// Upload the image to the media library.
						$speaker_image_id = media_handle_sideload( $file_array, $speaker_post_id, $name );

						// Assign image to the speaker.
						if ( ! is_wp_error( $speaker_image_id ) ) {
							set_post_thumbnail( $speaker_post_id, $speaker_image_id );
						}
					}
				}
			}

			// Store for the schedule post.
			$schedule_post_speakers[] = $speaker_post_id;

			// Store the GF entry ID.
			add_post_meta( $speaker_post_id, 'gf_entry_id', $entry_id, true );

		}

		// Assign speakers to schedule post.
		if ( ! empty( $schedule_post_speakers ) ) {
			foreach ( $schedule_post_speakers as $speaker_id ) {
				add_post_meta( $schedule_post_id, 'conf_sch_event_speaker', $speaker_id, false );
			}
		}
	}

	/**
	 * Filter the output for the 2017 speaker confirmation form.
	 *
	 * @access  public
	 * @since   1.0.0
	 * @param   $form_string - string - the default form HTML.
	 * @param   $form - array - the form array
	 * @return  string - the filtered HTML.
	 */
	public function filter_2017_speaker_confirmation_form( $form_string, $form ) {
		global $blog_id;

		// Only on 2017 website.
		if ( 7 != $blog_id ) {
			return false;
		}

		// Build error message.
		$error_message = '<div class="callout">
			<p>Oops! It looks like we\'re missing some important information to confirm your session.</p>
			<p>Try the link from your confirmation email again and, if the form continues to fail, please <a href="/contact/">let us know</a>.</p>
		</div>';

		// Get the speaker ID
		$speaker_id = $this->get_form_speaker_id();
		if ( ! $speaker_id ) {
			return $error_message;
		}

		// Check the confirmation ID.
		$check_confirmation_id = $this->check_form_confirmation_id( $speaker_id );
		if ( ! $check_confirmation_id ) {
			return $error_message;
		}

		// Get the speaker post, session ID and session post.
		$speaker_post = $this->get_form_speaker_post( $speaker_id );
		$session_id = $this->get_form_session_id();
		$session_post = $this->get_form_session_post( $session_id );
		if ( ! $speaker_post || ! $session_id || ! $session_post ) {
			return $error_message;
		}

		// Build format string.
		$format_key = get_post_meta( $session_id, 'conf_sch_event_format', true );
		switch( $format_key ) {

			case 'lightning':
				$format = 'lightning talk';
				break;

			case 'workshop':
				$format = 'workshop';
				break;

			default:
				$format = '45-minute session';
				break;
		}

		// Add message.
		$message = '<div class="callout">
			<p><strong>Hello ' . $speaker_post->post_title . '!</strong> You have been selected to present on "' . $session_post->post_title . '" as a ' . $format . '.</p>
			<p>Congratulations and thank you from all of us in the WPCampus community.</p>
			<p><strong>Please review and confirm your acceptance to present as soon as you can, and no later than Wednesday, April 19.</strong></p>
			<p>We\'re really grateful to have you present and share your knowledge and experience at WPCampus 2017. Please answer a few questions to confirm your session and help ensure a great conference.</p>
		</div>';

		return $message . $form_string;
	}

	/**
	 * Process the WPCampus 2017 speaker confirmation.
	 */
	public function process_2017_speaker_confirmation( $entry, $form ) {
		global $blog_id;

		return;

		// Only on 2017 website.
		if ( 7 != $blog_id ) {
			return false;
		}

		// Make sure the form is active.
		if ( ! isset( $form['is_active'] ) || ! $form['is_active'] ) {
			return false;
		}

		// Set the entry ID.
		$entry_id = $entry['id'];

		// Make sure we have an entry ID.
		if ( ! $entry_id ) {
			return false;
		}

		echo '<pre>';
		print_r( $entry );
		echo '</pre>';

		exit;

		/*// First, check to see if the entry has already been processed.
		$entry_post = wpcampus_forms()->get_entry_post( $entry_id );

		// If this entry has already been processed, then skip.
		if ( $entry_post && isset( $entry_post->ID ) ) {
			return false;
		}

		// Set the schedule post ID.
		$schedule_post_id = $entry['post_id'];

		// Hold speaker information.
		$speaker = array();
		$speaker2 = array();

		// Hold session information.
		$session = array();

		// Process one field at a time.
		foreach ( $form['fields'] as $field ) {

			// Skip certain types.
			if ( in_array( $field->type, array( 'section' ) ) ) {
				continue;
			}

			// Process names.
			if ( 'name' == $field->type ) {

				// Process each name part.
				foreach ( $field->inputs as $input ) {

					// Get the input value.
					$input_value = rgar( $entry, $input['id'] );

					switch ( $input['name'] ) {

						case 'speaker_first_name':
							$speaker['first_name'] = $input_value;
							break;

						case 'speaker2_first_name':
							$speaker2['first_name'] = $input_value;
							break;

						case 'speaker_last_name':
							$speaker['last_name'] = $input_value;
							break;

						case 'speaker2_last_name':
							$speaker2['last_name'] = $input_value;
							break;

					}
				}
			} elseif ( 'session_categories' == $field->inputName ) {

				// Get all the categories and place in array.
				$session['categories'] = array();

				foreach ( $field->inputs as $input ) {
					if ( $this_data = rgar( $entry, $input['id'] ) ) {
						$session['categories'][] = $this_data;
					}
				}

				// Make sure we have categories.
				if ( ! empty( $session['categories'] ) ) {

					// Make sure its all integers.
					$session['categories'] = array_map( 'intval', $session['categories'] );

				}
			} elseif ( 'session_technical' == $field->inputName ) {

				// Get all the skill levels and place in array.
				$session['levels'] = array();

				foreach ( $field->inputs as $input ) {
					if ( $this_data = rgar( $entry, $input['id'] ) ) {
						$session['levels'][] = $this_data;
					}
				}

				// Make sure we have levels.
				if ( ! empty( $session['levels'] ) ) {

					// Make sure its all integers.
					$session['levels'] = array_map( 'intval', $session['levels'] );

				}
			} else {

				// Get the field value.
				$field_value = rgar( $entry, $field->id );

				// Process the speaker photos.
				if ( 'Speaker Photo' == $field->adminLabel ) {

					$speaker['photo'] = $field_value;

				} elseif ( 'Speaker Two Photo' == $field->adminLabel ) {

					$speaker2['photo'] = $field_value;

				} else {

					// Process other input names.
					switch ( $field->inputName ) {

						case 'speaker_email':
							$speaker['email'] = $field_value;
							break;

						case 'speaker2_email':
							$speaker2['email'] = $field_value;
							break;

						case 'speaker_bio':
							$speaker['bio'] = $field_value;
							break;

						case 'speaker2_bio':
							$speaker2['bio'] = $field_value;
							break;

						case 'speaker_website':
							$speaker['website'] = $field_value;
							break;

						case 'speaker2_website':
							$speaker2['website'] = $field_value;
							break;

						case 'speaker_twitter':

							// Remove any non alphanumeric characters.
							$speaker['twitter'] = preg_replace( '/[^a-z0-9]/i', '', $field_value );
							break;

						case 'speaker2_twitter':

							// Remove any non alphanumeric characters.
							$speaker2['twitter'] = preg_replace( '/[^a-z0-9]/i', '', $field_value );
							break;

						case 'speaker_linkedin':
							$speaker['linkedin'] = $field_value;
							break;

						case 'speaker2_linkedin':
							$speaker2['linkedin'] = $field_value;
							break;

						case 'speaker_company':
							$speaker['company'] = $field_value;
							break;

						case 'speaker2_company':
							$speaker2['company'] = $field_value;
							break;

						case 'speaker_company_website':
							$speaker['company_website'] = $field_value;
							break;

						case 'speaker2_company_website':
							$speaker2['company_website'] = $field_value;
							break;

						case 'speaker_position':
							$speaker['position'] = $field_value;
							break;

						case 'speaker2_position':
							$speaker2['position'] = $field_value;
							break;

						case 'session_title':
							$session['title'] = $field_value;
							break;

						case 'session_desc':
							$session['desc'] = $field_value;
							break;

						case 'other_session_categories':
							$session['other_categories'] = $field_value;
							break;

					}
				}
			}
		}

		// If no schedule post was made, create a post.
		if ( ! $schedule_post_id ) {
			$schedule_post_id = wp_insert_post( array(
				'post_type'     => 'schedule',
				'post_status'   => 'pending',
				'post_title'    => $session['title'],
				'post_content'  => $session['desc'],
			));
		} else {

			// Otherwise, make sure the post is updated.
			$schedule_post_id = wp_insert_post( array(
				'ID'            => $schedule_post_id,
				'post_type'     => 'schedule',
				'post_status'   => 'pending',
				'post_title'    => $session['title'],
				'post_content'  => $session['desc'],
			));

		}

		// No point in continuing if no schedule post ID.
		if ( is_wp_error( $schedule_post_id ) || ! $schedule_post_id ) {
			return false;
		}

		// Set the categories for the session.
		if ( ! empty( $session['categories'] ) ) {
			wp_set_object_terms( $schedule_post_id, $session['categories'], 'session_categories', false );
		}

		// Set the "other" categories for the session.
		if ( ! empty( $session['other_categories'] ) ) {

			// Convert to array.
			$other_categories = explode( ',', $session['other_categories'] );
			if ( ! empty( $other_categories ) ) {

				// Will hold final term IDs.
				$other_category_ids = array();

				// Add term.
				foreach ( $other_categories as $new_term_string ) {

					// Create the term.
					$new_term = wp_insert_term( $new_term_string, 'session_categories' );
					if ( ! is_wp_error( $new_term ) && ! empty( $new_term['term_id'] ) ) {

						// Add to list to assign later.
						$other_category_ids[] = $new_term['term_id'];

					}
				}

				// Assign all new categories to session.
				if ( ! empty( $other_category_ids ) ) {
					wp_set_object_terms( $schedule_post_id, $other_category_ids, 'session_categories', false );
				}
			}
		}

		// Set the technical levels for the session.
		if ( ! empty( $session['levels'] ) ) {
			wp_set_object_terms( $schedule_post_id, $session['levels'], 'session_technical', false );
		}

		// Set the event type to "session".
		wp_set_object_terms( $schedule_post_id, 'session', 'event_types', false );

		// Store the GF entry ID for the schedule post.
		add_post_meta( $schedule_post_id, 'gf_entry_id', $entry_id, true );

		// Will hold the speaker post IDs for the schedule post.
		$schedule_post_speakers = array();

		// Create speaker posts.
		foreach ( array( $speaker, $speaker2 ) as $this_speaker ) {

			// Make sure they have an email.
			if ( empty( $this_speaker['email'] ) ) {
				continue;
			}

			// See if a WP user exists for their email.
			$wp_user = get_user_by( 'email', $this_speaker['email'] );

			// Build the speaker name.
			$name = '';

			// Build from form.
			if ( ! empty( $this_speaker['first_name'] ) ) {
				$name .= $this_speaker['first_name'];

				if ( ! empty( $this_speaker['last_name'] ) ) {
					$name .= ' ' . $this_speaker['last_name'];
				}
			}

			// If no name but found WP user, get from user data.
			if ( ! $name && is_a( $wp_user, 'WP_User' ) && ! empty( $wp_user->data->display_name ) ) {
				$name = $wp_user->data->display_name;
			}

			// No point if no name.
			if ( ! $name ) {
				continue;
			}

			// Create the speaker.
			$speaker_post_id = wp_insert_post( array(
				'post_type'     => 'speakers',
				'post_status'   => 'pending',
				'post_title'    => $name,
				'post_content'  => $this_speaker['bio'],
			));

			// Make sure the post was created before continuing.
			if ( ! $speaker_post_id ) {
				continue;
			}

			// Store the WordPress user ID.
			if ( ! empty( $wp_user->ID ) && $wp_user->ID > 0 ) {
				add_post_meta( $speaker_post_id, 'conf_sch_speaker_user_id', $wp_user->ID, true );
			}

			// Store speaker post meta.
			add_post_meta( $speaker_post_id, 'conf_sch_speaker_email', $this_speaker['email'], true );
			add_post_meta( $speaker_post_id, 'conf_sch_speaker_url', $this_speaker['website'], true );
			add_post_meta( $speaker_post_id, 'conf_sch_speaker_company', $this_speaker['company'], true );
			add_post_meta( $speaker_post_id, 'conf_sch_speaker_company_url', $this_speaker['company_website'], true );
			add_post_meta( $speaker_post_id, 'conf_sch_speaker_position', $this_speaker['position'], true );
			add_post_meta( $speaker_post_id, 'conf_sch_speaker_twitter', $this_speaker['twitter'], true );
			add_post_meta( $speaker_post_id, 'conf_sch_speaker_linkedin', $this_speaker['linkedin'], true );

			// Add the speaker photo.
			if ( ! empty( $this_speaker['photo'] ) ) {

				// Set variables for storage, fix file filename for query strings.
				preg_match( '/[^\?]+\.(jpe?g|png)\b/i', $this_speaker['photo'], $matches );
				if ( ! empty( $matches[0] ) ) {

					// Make sure we have the files we need.
					require_once( ABSPATH . 'wp-admin/includes/file.php' );
					require_once( ABSPATH . 'wp-admin/includes/image.php' );
					require_once( ABSPATH . 'wp-admin/includes/media.php' );

					// Download the file to temp location.
					$tmp_file = download_url( $this_speaker['photo'] );

					// Setup the file info.
					$file_array = array(
						'name'     => basename( $matches[0] ),
						'tmp_name' => $tmp_file,
					);

					// If no issues with the file...
					if ( ! is_wp_error( $file_array['tmp_name'] ) ) {

						// Upload the image to the media library.
						$speaker_image_id = media_handle_sideload( $file_array, $speaker_post_id, $name );

						// Assign image to the speaker.
						if ( ! is_wp_error( $speaker_image_id ) ) {
							set_post_thumbnail( $speaker_post_id, $speaker_image_id );
						}
					}
				}
			}

			// Store for the schedule post.
			$schedule_post_speakers[] = $speaker_post_id;

			// Store the GF entry ID.
			add_post_meta( $speaker_post_id, 'gf_entry_id', $entry_id, true );

		}

		// Assign speakers to schedule post.
		if ( ! empty( $schedule_post_speakers ) ) {
			foreach ( $schedule_post_speakers as $speaker_id ) {
				add_post_meta( $schedule_post_id, 'conf_sch_event_speaker', $speaker_id, false );
			}
		}*/
	}

	/**
	 * Registers our plugins's taxonomies.
	 *
	 * @access  public
	 * @since   1.0.0
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

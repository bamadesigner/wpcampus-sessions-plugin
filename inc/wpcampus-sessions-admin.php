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

		// Adds dropdown to filter the speaker status.
		add_action( 'restrict_manage_posts', array( $this, 'add_speaker_status_dropdown' ), 100, 2 );

		// Add meta boxes.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), -1, 2 );

		// Add custom columns.
		add_filter( 'manage_posts_columns', array( $this, 'add_posts_columns' ), 10, 2 );

		// Populate our custom admin columns.
		add_action( 'manage_speakers_posts_custom_column', array( $this, 'populate_posts_columns' ), 10, 2 );

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
	 * @param   $post_id - int - the speaker ID.
	 * @return  void
	 */
	public function print_speaker_status( $post_id ) {

		// Get the status.
		$status = get_post_meta( $post_id, 'conf_sch_speaker_status', true );

		// Print the status.
		if ( 'confirmed' == $status ) {
			?><span style="color:green;">Confirmed</span><?php
		} elseif ( 'declined' == $status ) {
			?><span style="color:red;">Declined</span><?php
		} elseif ( 'selected' == $status ) {
			?><strong>Pending</strong><?php
		} else {
			?><em>Not selected</em><?php
		}
	}

	/**
	 * Allows us to add a dropdown to filter by speaker status.
	 *
	 * @param   $post_type - string - The post type slug.
	 * @param   $which - string - The location of the extra table nav markup: 'top' or 'bottom'.
	 * @return  void
	 */
	public function add_speaker_status_dropdown( $post_type, $which ) {
		global $wpdb;

		switch ( $post_type ) {

			case 'speakers':

				// Get the post status.
				$post_status = ! empty( $_REQUEST['post_status'] ) ? $_REQUEST['post_status'] : '';
				if ( 'all' == $post_status ) {
					$post_status = '';
				}

				// Don't filter in the trash.
				if ( 'trash' == $post_status ) {
					break;
				}

				// Set the status meta key.
				$status_meta_key = 'conf_sch_speaker_status';

				// Get the counts.
				$confirmed_count = 0;
				$declined_count = 0;
				$selected_count = 0;

				// Process each meta values.
				foreach ( array( 'confirmed', 'declined', 'selected' ) as $status_meta_value ) {

					// Build the query.
					$count_query = $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} posts INNER JOIN {$wpdb->postmeta} status ON status.post_id = posts.ID AND status.meta_key = %s AND status.meta_value = %s WHERE posts.post_type = %s", $status_meta_key, $status_meta_value, $post_type );

					// Add the post status.
					if ( $post_status ) {
						$count_query .= $wpdb->prepare( ' AND posts.post_status = %s', $post_status );
					} else {
						$count_query .= " AND posts.post_status IN ('publish','future','draft','pending','private')";
					}

					// Get the count.
					${"{$status_meta_value}_count"} = $wpdb->get_var( $count_query );

				}

				// Build the not selected count query.
				$not_selected_count_query = $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} posts LEFT JOIN {$wpdb->postmeta} status ON status.post_id = posts.ID AND status.meta_key = %s WHERE posts.post_type = %s AND status.post_id IS NULL", $status_meta_key, $post_type );

				// Add the post status.
				if ( $post_status ) {
					$not_selected_count_query .= $wpdb->prepare( ' AND posts.post_status = %s', $post_status );
				} else {
					$not_selected_count_query .= " AND posts.post_status IN ('publish','future','draft','pending','private')";
				}

				// Get the not selected count.
				$not_selected_count = $wpdb->get_var( $not_selected_count_query );

				// Are we viewing a specific status?
				$declined_selected = isset( $_GET['status'] ) && 'declined' == $_GET['status'];
				$pending_selected = isset( $_GET['status'] ) && 'selected' == $_GET['status'];
				$not_selected = isset( $_GET['status'] ) && ! $_GET['status'];

				// Confirmed is the default selected.
				$confirmed_selected = ! isset( $_GET['status'] ) || ( isset( $_GET['status'] ) && 'confirmed' == $_GET['status'] );

				?>
				<select name="status">
					<option value="">Sort by status</option>
					<option value="confirmed"<?php selected( $confirmed_selected ); ?>>Confirmed (<?php echo $confirmed_count; ?>)</option>
					<option value="declined"<?php selected( $declined_selected ); ?>>Declined (<?php echo $declined_count; ?>)</option>
					<option value="selected"<?php selected( $pending_selected ); ?>>Pending (<?php echo $selected_count; ?>)</option>
					<option value=""<?php selected( $not_selected ); ?>>Not selected (<?php echo $not_selected_count; ?>)</option>
				</select>
				<?php

				break;
		}
	}

	/**
	 * Adds our admin meta boxes.
	 *
	 * @access  public
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
				$new_columns['wpc-speaker-status'] = __( 'Status', 'wpcampus' );
			}
		}

		return $new_columns;
	}

	/**
	 * Populate our custom admin columns.
	 *
	 * @access  public
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
	 * @param   int - $post_id - the ID of the event
	 * @return  void
	 */
	public function print_wpc_speaker_information( $post_id ) {

		// Get the information.
		$technology = get_post_meta( $post_id, 'wpc_speaker_technology', true );
		$video_release = get_post_meta( $post_id, 'wpc_speaker_video_release', true );
		$unavailability = get_post_meta( $post_id, 'wpc_speaker_unavailability', true );
		$arrival = get_post_meta( $post_id, 'wpc_speaker_arrival', true );
		$special_requests = get_post_meta( $post_id, 'wpc_speaker_special_requests', true );

		?>
		<p>
			<strong><?php _e( 'Status:', 'wpcampus' ); ?></strong><br />
			<?php

			// Print the speaker's status.
			$this->print_speaker_status( $post_id );

			?>
		</p>
		<p><strong>Technology:</strong><br /><?php echo $technology ?: '<em>This speaker did not specify which technology they\'ll use.</em>'; ?></p>
		<p><strong>Video Release:</strong><br /><?php echo $video_release ?: '<em>This speaker did not specify their video release agreement.</em>'; ?></p>
		<p><strong>Unavailability:</strong><br /><?php echo $unavailability ?: '<em>This speaker did not specify any unavailability.</em>'; ?></p>
		<p><strong>Arrival:</strong><br /><?php echo $arrival ?: '<em>This speaker did not specify their arrival time.</em>'; ?></p>
		<p><strong>Special Requests:</strong><br /><?php echo $special_requests ?: '<em>This speaker did not specify any special requests.</em>'; ?></p>
		<?php
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

<?php
/** BBS edit controls, admin columns, and write validation. */

defined( 'ABSPATH' ) || exit;

final class FNBBS_Admin {
	private static $checking = false;

	public static function hooks() {
		add_action( 'add_meta_boxes_' . FNBBS_POST_TYPE, array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . FNBBS_POST_TYPE, array( __CLASS__, 'save_fields' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'clean_title' ), 10, 2 );
		add_action( 'wp_after_insert_post', array( __CLASS__, 'check_published' ), 20, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		add_filter( 'manage_' . FNBBS_POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . FNBBS_POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_filter( 'pre_insert_term', array( __CLASS__, 'check_new_term' ), 10, 2 );
		add_filter( 'pre_term_name', array( __CLASS__, 'clean_term_name' ), 10, 2 );
		add_filter( 'wp_update_term_data', array( __CLASS__, 'check_term_update' ), 10, 3 );
	}

	public static function add_meta_box() {
		add_meta_box( 'fnbbs_connection', __( 'BBS Connection', 'fujinet-bbs-directory' ), array( __CLASS__, 'render_meta_box' ), FNBBS_POST_TYPE, 'normal', 'high' );
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'fnbbs_save_' . $post->ID, 'fnbbs_nonce' );
		$hostname = get_post_meta( $post->ID, FNBBS_META_HOSTNAME, true );
		$port     = get_post_meta( $post->ID, FNBBS_META_PORT, true );
		$status   = get_post_meta( $post->ID, FNBBS_META_STATUS, true );
		if ( ! $status ) {
			$status = 'active';
		}
		?>
		<p><label for="fnbbs_hostname"><strong><?php esc_html_e( 'Hostname or IP address', 'fujinet-bbs-directory' ); ?></strong></label></p>
		<p><input type="text" id="fnbbs_hostname" name="fnbbs_hostname" value="<?php echo esc_attr( $hostname ); ?>" class="regular-text" required></p>
		<p><label for="fnbbs_port"><strong><?php esc_html_e( 'Port', 'fujinet-bbs-directory' ); ?></strong></label></p>
		<p><input type="number" id="fnbbs_port" name="fnbbs_port" value="<?php echo esc_attr( $port ); ?>" min="1" max="65535" step="1" required></p>
		<p><label for="fnbbs_status"><strong><?php esc_html_e( 'Directory status', 'fujinet-bbs-directory' ); ?></strong></label></p>
		<p><select id="fnbbs_status" name="fnbbs_status">
			<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'fujinet-bbs-directory' ); ?></option>
			<option value="inactive" <?php selected( $status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'fujinet-bbs-directory' ); ?></option>
		</select></p>
		<p><?php esc_html_e( 'Assign at least one Platform and Terminal Type using the boxes on this screen before publishing.', 'fujinet-bbs-directory' ); ?></p>
		<?php
	}

	public static function save_fields( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}
		if ( ! isset( $_POST['fnbbs_nonce'] ) || ! is_string( $_POST['fnbbs_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fnbbs_nonce'] ) ), 'fnbbs_save_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$hostname = isset( $_POST['fnbbs_hostname'] ) && is_string( $_POST['fnbbs_hostname'] ) ? FNBBS_Core::valid_hostname( wp_unslash( $_POST['fnbbs_hostname'] ) ) : false;
		$port     = isset( $_POST['fnbbs_port'] ) && is_string( $_POST['fnbbs_port'] ) ? trim( wp_unslash( $_POST['fnbbs_port'] ) ) : '';
		$status   = isset( $_POST['fnbbs_status'] ) && is_string( $_POST['fnbbs_status'] ) ? sanitize_key( wp_unslash( $_POST['fnbbs_status'] ) ) : '';
		if ( false === $hostname || ! FNBBS_Core::valid_port( $port ) || ! in_array( $status, array( 'active', 'inactive' ), true ) ) {
			self::notice( __( 'Invalid hostname, port, or status. The connection fields were not changed.', 'fujinet-bbs-directory' ) );
			return;
		}
		update_post_meta( $post_id, FNBBS_META_HOSTNAME, $hostname );
		update_post_meta( $post_id, FNBBS_META_PORT, (string) (int) $port );
		update_post_meta( $post_id, FNBBS_META_STATUS, $status );
	}

	public static function clean_title( $data, $postarr ) {
		if ( FNBBS_POST_TYPE !== ( $data['post_type'] ?? '' ) ) {
			return $data;
		}
		$title = sanitize_text_field( wp_unslash( $data['post_title'] ?? '' ) );
		$title = trim( str_replace( '|', '', $title ) );
		$data['post_title'] = wp_slash( $title );
		if ( '' === $title && 'publish' === ( $data['post_status'] ?? '' ) ) {
			$data['post_status'] = 'draft';
			self::notice( __( 'A BBS name is required before publishing.', 'fujinet-bbs-directory' ) );
		}
		return $data;
	}

	public static function record_is_valid( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || false === FNBBS_Core::safe_name( $post->post_title ) || false === FNBBS_Core::valid_hostname( get_post_meta( $post_id, FNBBS_META_HOSTNAME, true ) ) || ! FNBBS_Core::valid_port( get_post_meta( $post_id, FNBBS_META_PORT, true ) ) || ! in_array( get_post_meta( $post_id, FNBBS_META_STATUS, true ), array( 'active', 'inactive' ), true ) ) {
			return false;
		}
		foreach ( array( FNBBS_PLATFORM_TAXONOMY, FNBBS_TERMINAL_TAXONOMY ) as $taxonomy ) {
			$terms = get_the_terms( $post_id, $taxonomy );
			if ( ! $terms || is_wp_error( $terms ) ) {
				return false;
			}
			if ( FNBBS_TERMINAL_TAXONOMY === $taxonomy ) {
				foreach ( $terms as $term ) {
					if ( false === self::valid_terminal_name( $term->name ) ) {
						return false;
					}
				}
			}
		}
		return true;
	}

	public static function check_published( $post_id, $post ) {
		if ( self::$checking || FNBBS_POST_TYPE !== $post->post_type || 'publish' !== $post->post_status || self::record_is_valid( $post_id ) ) {
			return;
		}
		self::$checking = true;
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
		self::$checking = false;
		self::notice( __( 'The BBS was saved as a draft because its name, connection, platform, or terminal type is incomplete or invalid.', 'fujinet-bbs-directory' ) );
	}

	private static function notice( $message ) {
		if ( get_current_user_id() ) {
			set_transient( 'fnbbs_notice_' . get_current_user_id(), $message, 120 );
		}
	}

	public static function admin_notice() {
		if ( ! in_array( get_current_screen()->id, array( FNBBS_POST_TYPE, 'edit-' . FNBBS_POST_TYPE ), true ) ) {
			return;
		}
		$key = 'fnbbs_notice_' . get_current_user_id();
		$message = get_transient( $key );
		if ( $message ) {
			delete_transient( $key );
			echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
		}
	}

	public static function columns( $columns ) {
		$columns['fnbbs_hostname'] = __( 'Hostname', 'fujinet-bbs-directory' );
		$columns['fnbbs_port'] = __( 'Port', 'fujinet-bbs-directory' );
		$columns['fnbbs_status'] = __( 'Status', 'fujinet-bbs-directory' );
		$columns['fnbbs_platforms'] = __( 'Platforms', 'fujinet-bbs-directory' );
		$columns['fnbbs_terminals'] = __( 'Terminal Types', 'fujinet-bbs-directory' );
		$columns['fnbbs_modified'] = __( 'Last Modified', 'fujinet-bbs-directory' );
		return $columns;
	}

	public static function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'fnbbs_hostname':
				echo esc_html( get_post_meta( $post_id, FNBBS_META_HOSTNAME, true ) );
				break;
			case 'fnbbs_port':
				echo esc_html( get_post_meta( $post_id, FNBBS_META_PORT, true ) );
				break;
			case 'fnbbs_status':
				echo esc_html( get_post_meta( $post_id, FNBBS_META_STATUS, true ) );
				break;
			case 'fnbbs_platforms':
			case 'fnbbs_terminals':
				$taxonomy = 'fnbbs_platforms' === $column ? FNBBS_PLATFORM_TAXONOMY : FNBBS_TERMINAL_TAXONOMY;
				$names = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );
				echo esc_html( is_wp_error( $names ) ? '' : implode( ', ', $names ) );
				break;
			case 'fnbbs_modified':
				echo esc_html( get_post_modified_time( 'Y-m-d H:i', false, $post_id ) );
				break;
		}
	}

	public static function valid_terminal_name( $name ) {
		$name = FNBBS_Core::safe_name( $name );
		return false !== $name && ! str_contains( $name, ',' ) ? $name : false;
	}

	public static function check_new_term( $term, $taxonomy ) {
		if ( FNBBS_TERMINAL_TAXONOMY === $taxonomy && false === self::valid_terminal_name( $term ) ) {
			return new WP_Error( 'fnbbs_invalid_terminal_name', __( 'Terminal names cannot contain commas, pipes, or line breaks.', 'fujinet-bbs-directory' ) );
		}
		return $term;
	}

	public static function clean_term_name( $name, $taxonomy ) {
		if ( FNBBS_TERMINAL_TAXONOMY === $taxonomy ) {
			return trim( preg_replace( '/[,|\x00-\x1F\x7F]/', '', (string) $name ) );
		}
		return $name;
	}

	public static function check_term_update( $data, $term_id, $taxonomy ) {
		if ( FNBBS_TERMINAL_TAXONOMY === $taxonomy && false === self::valid_terminal_name( $data['name'] ?? '' ) ) {
			$old = get_term( $term_id, $taxonomy );
			if ( $old && ! is_wp_error( $old ) ) {
				$data['name'] = $old->name;
			}
		}
		return $data;
	}
}

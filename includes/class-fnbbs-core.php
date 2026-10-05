<?php
/** Core registration, roles, and shared validation. */

defined( 'ABSPATH' ) || exit;

final class FNBBS_Core {
	public static function register() {
		register_post_type(
			FNBBS_POST_TYPE,
			array(
				'labels'             => array(
					'name'          => __( 'BBS Directory', 'fujinet-bbs-directory' ),
					'singular_name' => __( 'BBS', 'fujinet-bbs-directory' ),
					'add_new_item'  => __( 'Add New BBS', 'fujinet-bbs-directory' ),
					'edit_item'     => __( 'Edit BBS', 'fujinet-bbs-directory' ),
					'all_items'     => __( 'All BBSes', 'fujinet-bbs-directory' ),
				),
				'public'             => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => false,
				'exclude_from_search' => true,
				'supports'           => array( 'title' ),
				'capability_type'    => array( 'bbs', 'bbses' ),
				'map_meta_cap'       => true,
				'capabilities'       => array( 'create_posts' => 'edit_bbses' ),
				'menu_icon'          => 'dashicons-list-view',
			)
		);

		$tax_caps = array(
			'manage_terms' => 'manage_bbs_terms',
			'edit_terms'   => 'edit_bbs_terms',
			'delete_terms' => 'delete_bbs_terms',
			'assign_terms' => 'assign_bbs_terms',
		);
		foreach ( array(
			FNBBS_PLATFORM_TAXONOMY => array( __( 'Platforms', 'fujinet-bbs-directory' ), __( 'Platform', 'fujinet-bbs-directory' ) ),
			FNBBS_TERMINAL_TAXONOMY => array( __( 'Terminal Types', 'fujinet-bbs-directory' ), __( 'Terminal Type', 'fujinet-bbs-directory' ) ),
		) as $taxonomy => $labels ) {
			register_taxonomy(
				$taxonomy,
				FNBBS_POST_TYPE,
				array(
					'labels'       => array( 'name' => $labels[0], 'singular_name' => $labels[1] ),
					'public'       => false,
					'show_ui'      => true,
					'show_in_rest' => false,
					'hierarchical' => true,
					'capabilities' => $tax_caps,
					'rewrite'      => false,
					'query_var'    => false,
				)
			);
		}
	}

	public static function activate() {
		self::register();
		FNBBS_API::register_routes();
		$manager = get_role( FNBBS_ROLE );
		if ( ! $manager ) {
			$manager = add_role( FNBBS_ROLE, __( 'BBS Directory Manager', 'fujinet-bbs-directory' ), array( 'read' => true ) );
		}
		$capabilities = array(
			'edit_bbses', 'edit_others_bbses', 'edit_published_bbses', 'edit_private_bbses',
			'publish_bbses', 'read_private_bbses', 'delete_bbses', 'delete_others_bbses',
			'delete_published_bbses', 'delete_private_bbses', 'assign_bbs_terms',
		);
		foreach ( array( $manager, get_role( 'administrator' ) ) as $role ) {
			if ( ! $role ) {
				continue;
			}
			foreach ( $capabilities as $capability ) {
				$role->add_cap( $capability );
			}
		}
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( array( 'manage_bbs_terms', 'edit_bbs_terms', 'delete_bbs_terms' ) as $capability ) {
				$administrator->add_cap( $capability );
			}
		}
		foreach ( array(
			FNBBS_PLATFORM_TAXONOMY => array(
				'atari8' => 'Atari 8-bit', 'c64' => 'Commodore 64', 'apple2' => 'Apple II',
				'coco' => 'TRS-80 CoCo', 'pc' => 'PC / DOS',
			),
			FNBBS_TERMINAL_TAXONOMY => array(
				'atascii' => 'ATASCII', 'ascii' => 'ASCII', 'petscii' => 'PETSCII',
				'ansi' => 'ANSI', 'vt100' => 'VT100', 'vt52' => 'VT52',
			),
		) as $taxonomy => $terms ) {
			foreach ( $terms as $slug => $name ) {
				if ( ! term_exists( $slug, $taxonomy ) ) {
					wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
				}
			}
		}
		flush_rewrite_rules();
	}

	public static function valid_hostname( $value ) {
		if ( ! is_string( $value ) ) {
			return false;
		}
		$value = strtolower( trim( $value ) );
		if ( filter_var( $value, FILTER_VALIDATE_IP ) ) {
			return $value;
		}
		if ( strlen( $value ) > 253 || ! preg_match( '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*$/D', $value ) ) {
			return false;
		}
		return $value;
	}

	public static function valid_port( $value ) {
		return ( is_string( $value ) || is_int( $value ) ) && preg_match( '/^[0-9]{1,5}$/D', (string) $value ) && (int) $value >= 1 && (int) $value <= 65535;
	}

	public static function safe_name( $value ) {
		if ( ! is_string( $value ) ) {
			return false;
		}
		$value = trim( wp_specialchars_decode( wp_strip_all_tags( $value ), ENT_QUOTES ) );
		return '' !== $value && ! preg_match( '/[|\x00-\x1F\x7F]/', $value ) ? $value : false;
	}
}

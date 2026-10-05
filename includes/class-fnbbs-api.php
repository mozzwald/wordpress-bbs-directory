<?php
/** Read-only plain-text client routes. */

defined( 'ABSPATH' ) || exit;

final class FNBBS_API {
	public static function hooks() {
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'respond' ), 0 );
	}

	public static function register_routes() {
		add_rewrite_rule( '^bbs/list/([^/]+)/([^/]+)/?$', 'index.php?fnbbs_platform=$matches[1]&fnbbs_terminal=$matches[2]', 'top' );
		add_rewrite_rule( '^bbs/list/([^/]+)/?$', 'index.php?fnbbs_platform=$matches[1]', 'top' );
	}

	public static function query_vars( $vars ) {
		$vars[] = 'fnbbs_platform';
		$vars[] = 'fnbbs_terminal';
		return $vars;
	}

	public static function respond() {
		$platform_slug = get_query_var( 'fnbbs_platform', '' );
		if ( '' === $platform_slug ) {
			return;
		}
		$terminal_slug = get_query_var( 'fnbbs_terminal', '' );
		$platform_slug = strtolower( $platform_slug );
		$terminal_slug = strtolower( $terminal_slug );
		if ( ! preg_match( '/^[a-z0-9_-]+$/D', $platform_slug ) ) {
			self::plain_response( 404, 'Unknown platform' . "\n" );
		}
		if ( '' !== $terminal_slug && ! preg_match( '/^[a-z0-9_-]+$/D', $terminal_slug ) ) {
			self::plain_response( 404, 'Unknown terminal type' . "\n" );
		}
		$platform = get_term_by( 'slug', $platform_slug, FNBBS_PLATFORM_TAXONOMY );
		if ( ! $platform ) {
			self::plain_response( 404, 'Unknown platform' . "\n" );
		}
		if ( '' !== $terminal_slug && ! get_term_by( 'slug', $terminal_slug, FNBBS_TERMINAL_TAXONOMY ) ) {
			self::plain_response( 404, 'Unknown terminal type' . "\n" );
		}
		$tax_query = array(
			array(
				'taxonomy' => FNBBS_PLATFORM_TAXONOMY,
				'field'    => 'term_id',
				'terms'    => array( $platform->term_id ),
			),
		);
		if ( '' !== $terminal_slug ) {
			$tax_query[] = array(
				'taxonomy' => FNBBS_TERMINAL_TAXONOMY,
				'field'    => 'slug',
				'terms'    => array( $terminal_slug ),
			);
		}
		$posts = get_posts(
			array(
				'post_type'              => FNBBS_POST_TYPE,
				'post_status'            => 'publish',
				'numberposts'           => -1,
				'fields'                => 'ids',
				'suppress_filters'      => false,
				'no_found_rows'         => true,
				'update_post_meta_cache'=> false,
				'meta_query'            => array(
					array( 'key' => FNBBS_META_STATUS, 'value' => 'active' ),
				),
				'tax_query'             => $tax_query,
			)
		);
		$rows = array();
		foreach ( $posts as $post_id ) {
			if ( ! FNBBS_Admin::record_is_valid( $post_id ) ) {
				continue;
			}
			$name = FNBBS_Core::safe_name( get_post_field( 'post_title', $post_id ) );
			$hostname = FNBBS_Core::valid_hostname( get_post_meta( $post_id, FNBBS_META_HOSTNAME, true ) );
			$port = (int) get_post_meta( $post_id, FNBBS_META_PORT, true );
			$term_names = wp_get_post_terms( $post_id, FNBBS_TERMINAL_TAXONOMY, array( 'fields' => 'names' ) );
			if ( is_wp_error( $term_names ) || ! $term_names ) {
				continue;
			}
			$term_names = array_map( array( 'FNBBS_Admin', 'valid_terminal_name' ), $term_names );
			if ( in_array( false, $term_names, true ) ) {
				continue;
			}
			usort( $term_names, array( __CLASS__, 'compare_names' ) );
			$rows[] = array( 'name' => $name, 'id' => $post_id, 'line' => implode( '|', array( $name, $hostname, (string) $port, 'active', implode( ',', $term_names ) ) ) );
		}
		usort(
			$rows,
			static function ( $a, $b ) {
				$comparison = self::compare_names( $a['name'], $b['name'] );
				return $comparison ?: $a['id'] <=> $b['id'];
			}
		);
		$lines = array_column( $rows, 'line' );
		self::plain_response( 200, $lines ? implode( "\n", $lines ) . "\n" : '' );
	}

	public static function compare_names( $a, $b ) {
		$fold_a = function_exists( 'mb_strtolower' ) ? mb_strtolower( $a, 'UTF-8' ) : strtolower( $a );
		$fold_b = function_exists( 'mb_strtolower' ) ? mb_strtolower( $b, 'UTF-8' ) : strtolower( $b );
		return strcmp( $fold_a, $fold_b ) ?: strcmp( $a, $b );
	}

	private static function plain_response( $status, $body ) {
		status_header( $status );
		header( 'Content-Type: text/plain; charset=UTF-8' );
		header( 'X-Content-Type-Options: nosniff' );
		if ( 'HEAD' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			echo $body; // All fields have been validated against delimiters and control characters.
		}
		exit;
	}
}

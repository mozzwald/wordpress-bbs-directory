<?php
/**
 * Plugin Name: FujiNet BBS Directory
 * Description: A managed BBS directory with compact plain-text listings for FujiNet clients.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: FujiNet
 * License: GPL-3.0-or-later
 * Text Domain: fujinet-bbs-directory
 */

defined( 'ABSPATH' ) || exit;

define( 'FNBBS_POST_TYPE', 'fujinet_bbs' );
define( 'FNBBS_PLATFORM_TAXONOMY', 'fnbbs_platform' );
define( 'FNBBS_TERMINAL_TAXONOMY', 'fnbbs_terminal' );
define( 'FNBBS_ROLE', 'bbs_directory_manager' );
define( 'FNBBS_META_HOSTNAME', '_fnbbs_hostname' );
define( 'FNBBS_META_PORT', '_fnbbs_port' );
define( 'FNBBS_META_STATUS', '_fnbbs_status' );
define( 'FNBBS_META_LAST_CHECKED', '_fnbbs_last_checked' );
define( 'FNBBS_META_LAST_SEEN_ONLINE', '_fnbbs_last_seen_online' );
define( 'FNBBS_META_FAILURE_SINCE', '_fnbbs_failure_since' );

require_once __DIR__ . '/includes/class-fnbbs-core.php';
require_once __DIR__ . '/includes/class-fnbbs-admin.php';
require_once __DIR__ . '/includes/class-fnbbs-api.php';

add_action( 'init', array( 'FNBBS_Core', 'register' ) );
add_action( 'init', array( 'FNBBS_API', 'register_routes' ) );
FNBBS_Admin::hooks();
FNBBS_API::hooks();

register_activation_hook( __FILE__, array( 'FNBBS_Core', 'activate' ) );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

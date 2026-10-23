<?php
/**
 * Plugin Name: Brain Bytes AI Alt Text (Prototype)
 * Description: Generate and review image alt text in the Media Library and WordPress media popup using OpenAI.
 * Version: 0.1.0
 * Author: Brain Bytes Creative
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: bb-ai-alt-text
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BBAIAT_VERSION', '0.1.0' );
define( 'BBAIAT_FILE', __FILE__ );
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/admin.php';

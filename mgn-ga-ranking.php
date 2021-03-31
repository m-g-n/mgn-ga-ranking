<?php
/**
 * Plugin Name:       m-g-n GA Ranking
 * Plugin URI:        https://github.com/m-g-n/mgn-ga-ranking
 * Description:       Post viewing ranking making from Google Analytics.
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.2
 * Author:            m-g-n
 * Author URI:        https://www.m-g-n.me/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package mgn-ga-ranking
 * @since 1.0.0
 */

namespace MGNGA;

define( 'MGNGA_PLUGIN_DIR', __DIR__ );
define( 'MGNGA_PLUGIN_CLASS_DIR', __DIR__ . '/classes' );
define( 'MGNGA_PLUGIN_DOMAIN', 'mgnga-ranking' );


require_once MGNGA_PLUGIN_CLASS_DIR . '/class-mgnga-ranking-settings.php';
require_once MGNGA_PLUGIN_CLASS_DIR . '/class-mgnga-ga-access.php';
require_once MGNGA_PLUGIN_DIR . '/mgnga-functions.php';

if ( is_admin() ) {
	new GA_Access_Ranking_Settings();
}

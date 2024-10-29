<?php
/**
 * Plugin Name: BadgeOS AffiliateWP
 * Description: Integrate BadgeOS with AffiliateWP
 * Version: 1.0
 * Author: BadgeOS
 * Author URI: https://badgeos.org
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bos-awp
 */

defined('ABSPATH') || exit;

//Define CONSTANTS
define( 'BOS_AWP_VERSION', '1.0.0' );
define( 'BOS_AWP_DIR', plugin_dir_path ( __FILE__ ) );
define( 'BOS_AWP_DIR_FILE', BOS_AWP_DIR . basename ( __FILE__ ) );
define( 'BOS_AWP_INCLUDES_DIR', trailingslashit ( BOS_AWP_DIR . 'includes' ) );
define( 'BOS_AWP_TEMPLATES_DIR', trailingslashit ( BOS_AWP_DIR . 'templates' ) );
define( 'BOS_AWP_BASE_DIR', plugin_basename(__FILE__));
define( 'BOS_AWP_URL', trailingslashit ( plugins_url ( '', __FILE__ ) ) );
define( 'BOS_AWP_ASSETS_URL', trailingslashit ( BOS_AWP_URL . 'assets' ) );

if(!class_exists('BadgeOS_AffiliateWP')) {

    class BadgeOS_AffiliateWP {

        public function __construct() {
            $this->includes();
        }

        public static function activate() {

            if (!current_user_can('activate_plugins')) return;
        }

        public static function deactivate() {

            if (!current_user_can('activate_plugins')) return;
        }

        public static function missing_dependent_plugin_notice() {
            deactivate_plugins(plugin_basename(__FILE__), true);
            if (isset($_GET['activate'])) unset($_GET['activate']);
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php _e('BadgeOS AffiliateWP requires <a href="https://wordpress.org/plugins/badgeos/" target="_blank">BadgeOS</a> and <a href="https://affiliatewp.com/" target="_blank">Affiliate WP</a> plugins to be activated.', 'bos-awp'); ?></p>
            </div>
            <?php
        }

        private function includes() {
            if(file_exists(plugin_dir_path( __FILE__ ) . 'includes/badgeos/bos-affwp-integration.php')) {
                include_once plugin_dir_path( __FILE__ ) . 'includes/badgeos/bos-affwp-integration.php';
            }
        }
    }
}

register_activation_hook( __FILE__, array( 'BadgeOS_AffiliateWP', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BadgeOS_AffiliateWP', 'deactivate' ) );

if(!function_exists('bos_awp_plugins_loaded_cb')) {

    function bos_awp_plugins_loaded_cb() {

        if ( !class_exists('BadgeOS') || !class_exists('Affiliate_WP') ) {
            add_action('admin_notices', array('BadgeOS_AffiliateWP', 'missing_dependent_plugin_notice'));
            return false;
        }

        new BadgeOS_AffiliateWP();
    }
}

add_action( 'plugins_loaded', 'bos_awp_plugins_loaded_cb' );

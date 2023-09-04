<?php
/**
 * Plugin Name:      QuickForm
 * Version:           3.3.03
 * Author:            var x
 * Description:       Cool Form Builder.
 * Author URI:        https://plasma-web.ru/en/
 * Requires PHP:      5.5
 * License: GPLv2 or later
 */

if (! defined('ABSPATH')) {
    exit;
}

defined('QF3_DIR') or define('QF3_DIR', plugin_dir_path(__FILE__));
defined('QF3_PLUGIN_URL') or define('QF3_PLUGIN_URL', plugin_dir_url(__FILE__));
defined('QF3_PLUGIN_DIR') or define('QF3_PLUGIN_DIR', QF3_DIR . 'site/');
defined('QF3_ADMIN_DIR') or define('QF3_ADMIN_DIR', QF3_DIR . 'admin/');
defined('QF3_VERSION') or define('QF3_VERSION', '3.3.02');


register_activation_hook(__FILE__, 'qf3_activation');
register_deactivation_hook(__FILE__, 'qf3_deactivate');
register_uninstall_hook(__FILE__, 'qf3_uninstall');


require_once(QF3_DIR . 'classes.php');

if (is_admin()) {
    require_once(QF3_ADMIN_DIR . 'qf3admin.php');
} else {
    require_once(QF3_PLUGIN_DIR . 'qf3site.php');
}


function qf3_activation()
{
    require_once(QF3_ADMIN_DIR . 'qf3install.php');
    $inst = new QuickForm\qf_install();
    $inst->install();
}

function qf3_deactivate()
{
    return true;
}

function qf3_uninstall()
{
    global $wpdb;

    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}qf3_forms");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}qf3_projects");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}qf3_ps");
}

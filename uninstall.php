<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if( ! defined( 'WP_UNINSTALL_PLUGIN' ) )
    exit();

drop_push_table();

function drop_push_table() {
    global $wpdb;

    $wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}mobileassistant_push_settings`");
    $wpdb->delete($wpdb->options, array('option_name' => 'mobassistantconnector'));
}
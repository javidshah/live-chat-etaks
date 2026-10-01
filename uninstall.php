<?php
/**
 * Uninstall Live Chat - etaks
 *
 * Fired when the plugin is deleted via the WordPress Admin Plugins screen.
 * Cleans up custom database tables and options.
 *
 * @package LiveChatEtaks
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop custom table
$table_name = $wpdb->prefix . 'slc_messages';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );

// Delete plugin options
delete_option( 'slc_chat_settings' );
delete_option( 'slc_db_version' );

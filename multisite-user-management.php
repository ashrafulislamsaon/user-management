<?php
/*
Plugin Name: Auto Delete Unverified Users
Description: Automatically deletes users with unverified emails
Version: 1.0
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add cron schedule
add_filter('cron_schedules', 'add_cleanup_schedule');
function add_cleanup_schedule($schedules) {
    $schedules['daily'] = array(
        'interval' => 86400,
        'display' => 'Once Daily'
    );
    return $schedules;
}

// Function to delete unverified users
function delete_unverified_users_function() {
    global $wpdb;
    
    // Get all sites in the network
    $sites = get_sites();
    
    foreach ($sites as $site) {
        switch_to_blog($site->blog_id);
        
        // Get unverified users - using 0 for false boolean value
        $unverified_users = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->users} 
                WHERE email_verified = %d",
                0
            )
        );
        
        // Delete each user
        foreach ($unverified_users as $user_id) {
            wpmu_delete_user($user_id);
        }
        
        restore_current_blog();
    }
    
    // Log the cleanup
    error_log('Unverified users cleanup completed at ' . current_time('mysql'));
}

// Schedule the cleanup event AND run immediate cleanup
register_activation_hook(__FILE__, 'plugin_activation_handler');
function plugin_activation_handler() {
    // Schedule daily cleanup
    if (!wp_next_scheduled('delete_unverified_users')) {
        wp_schedule_event(time(), 'daily', 'delete_unverified_users');
    }
    
    // Run immediate cleanup
    delete_unverified_users_function();
}

// Hook for scheduled cleanup
add_action('delete_unverified_users', 'delete_unverified_users_function');

// Deactivation cleanup
register_deactivation_hook(__FILE__, 'cleanup_deactivation');
function cleanup_deactivation() {
    wp_clear_scheduled_hook('delete_unverified_users');
}
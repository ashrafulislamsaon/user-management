<?php
/*
Plugin Name: Auto Delete Unverified Users
Description: Automatically deletes users with unverified emails every 2 minutes
Version: 1.0.1
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add custom 2-minute schedule
add_filter('cron_schedules', 'add_cleanup_schedule');
function add_cleanup_schedule($schedules) {
    $schedules['every_two_minutes'] = array(
        'interval' => 120,
        'display' => 'Every 2 Minutes'
    );
    return $schedules;
}

// Function to delete unverified users
function delete_unverified_users_function() {
    global $wpdb;
    
    // Get all sites in the network
    $sites = get_sites();
    $total_deleted = 0;
    
    foreach ($sites as $site) {
        switch_to_blog($site->blog_id);
        
        // Get unverified users
        $unverified_users = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->users} 
                WHERE email_verified = %d",
                0
            )
        );
        
        // Delete each user
        if (!empty($unverified_users)) {
            foreach ($unverified_users as $user_id) {
                if (is_multisite()) {
                    require_once(ABSPATH . 'wp-admin/includes/ms.php');
                    wpmu_delete_user($user_id);
                } else {
                    wp_delete_user($user_id);
                }
                $total_deleted++;
            }
        }
        
        restore_current_blog();
    }
    
    // Log the cleanup with timestamp and count
    error_log(sprintf(
        'Unverified users cleanup completed at %s. Users deleted: %d',
        current_time('mysql'),
        $total_deleted
    ));
}

// Add debug logging
add_action('init', 'debug_cron_schedule');
function debug_cron_schedule() {
    if (current_user_can('manage_options')) {
        $timestamp = wp_next_scheduled('delete_unverified_users');
        error_log('Next scheduled cleanup at: ' . date('Y-m-d H:i:s', $timestamp));
    }
}

// Schedule the cleanup event
register_activation_hook(__FILE__, 'plugin_activation_handler');
function plugin_activation_handler() {
    // Include required files for multisite functions
    if (is_multisite()) {
        require_once(ABSPATH . 'wp-admin/includes/ms.php');
    }
    
    // Clear any existing schedule
    wp_clear_scheduled_hook('delete_unverified_users');
    
    // Schedule first run to occur after 2 minutes
    $first_run = time() + 120; // Current time + 2 minutes
    wp_schedule_event($first_run, 'every_two_minutes', 'delete_unverified_users');
    
    error_log('Plugin activated at: ' . current_time('mysql') . '. First cleanup scheduled for: ' . date('Y-m-d H:i:s', $first_run));
}

// Hook for scheduled cleanup
add_action('delete_unverified_users', 'delete_unverified_users_function');

// Deactivation cleanup
register_deactivation_hook(__FILE__, 'cleanup_deactivation');
function cleanup_deactivation() {
    wp_clear_scheduled_hook('delete_unverified_users');
    error_log('Plugin deactivated at: ' . current_time('mysql'));
}
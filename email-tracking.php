<?php
/**
 * Plugin Name: Email Open Tracker
 * Description: Tracks email opens using a tracking pixel and provides an email sending option.
 * Version: 1.6
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Create separate database table for tracking email opens
function eot_create_tracking_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'email_tracking_data';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NULL,
        view_count INT DEFAULT 0,
        last_opened_at DATETIME NULL,
        generate_time DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'eot_create_tracking_table');

// Generate tracking pixel URL
function eot_tracking_pixel($atts) {
    $atts = shortcode_atts(['email' => 'unknown', 'subject' => 'unknown'], $atts);
    return '<img src="' . site_url('/wp-json/eot/v1/track?email=' . urlencode($atts['email']) . '&subject=' . urlencode($atts['subject']) . '&rand=' . rand()) . '" width="1" height="1" style="display:none;">';
}
add_shortcode('email_tracker', 'eot_tracking_pixel');

// Handle tracking request
function eot_track_email() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'email_tracking_data';
    $email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
    $subject = isset($_GET['subject']) ? sanitize_text_field($_GET['subject']) : ''; // Get the email subject

    if (!empty($email)) {
        // Check if email already exists in the database
        $existing_entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE email = %s", $email));

        if ($existing_entry) {
            // Update view count, last opened timestamp, and subject
            $wpdb->update(
                $table_name,
                [
                    'view_count' => $existing_entry->view_count + 1,
                    'last_opened_at' => current_time('mysql'),
                    'subject' => $subject // Update subject
                ],
                ['email' => $email]
            );
            error_log("Updated existing email: " . $email);
        } else {
            // Insert new entry if email does not exist
            $wpdb->insert(
                $table_name,
                [
                    'email' => $email,
                    'view_count' => 1,
                    'last_opened_at' => current_time('mysql'),
                    'subject' => $subject, // Insert subject
                    'generate_time' => current_time('mysql') // Insert generate time
                ]
            );
            error_log("Inserted new email: " . $email);
        }
    } else {
        error_log("Invalid or missing email.");
    }

    // Send a transparent pixel image as response
    header("Content-Type: image/png");
    echo base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/wcAAwAB/ef2X7sAAAAASUVORK5CYII=");
    exit;
}
add_action('rest_api_init', function() {
    register_rest_route('eot/v1', '/track', ['methods' => 'GET', 'callback' => 'eot_track_email']);
});

// Admin menu to view tracked emails
function eot_admin_menu() {
    add_menu_page('Email Tracking', 'Email Tracking', 'manage_options', 'email-tracking', 'eot_admin_page');
    
    // Add sub-menu for generating email
    add_submenu_page(
        'email-tracking',                 // Parent menu slug
        'Generate Mail',                  // Page title
        'Generate Mail',                  // Menu title
        'manage_options',                 // Capability
        'generate-mail',                  // Menu slug
        'eot_generate_mail_page'          // Function to display the sub-menu content
    );
}
add_action('admin_menu', 'eot_admin_menu');

// Admin page for tracking overview
function eot_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'email_tracking_data';
    
    // Fetch all tracked emails
    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY generate_time DESC");

    echo '<div class="wrap"><h1>Email Open Tracking</h1>';
    echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Email</th><th>Subject</th><th>Status</th><th>View Count</th><th>Last Opened</th><th>Generate Time</th></tr></thead><tbody>';
    
    // Display tracked emails
    foreach ($results as $row) {
        $status = $row->view_count > 0 ? 'Seen' : 'Not Seen';
        echo "<tr><td>{$row->email}</td><td>{$row->subject}</td><td>{$status}</td><td>{$row->view_count}</td><td>{$row->last_opened_at}</td><td>{$row->generate_time}</td></tr>";
    }

    echo '</tbody></table></div>';
}

// Function to load the 'Generate Mail' page
function eot_generate_mail_page() {
    // Include the generate-mail.php file
    include plugin_dir_path(__FILE__) . 'generate-mail.php';
}

// Handle email sending and include tracking pixel
function eot_send_email_with_tracking($to, $subject, $message) {
    global $wpdb;
    
    // Include tracking pixel in the message
    $tracking_pixel = '<img src="' . site_url('/wp-json/eot/v1/track?email=' . urlencode($to) . '&subject=' . urlencode($subject) . '&rand=' . rand()) . '" width="1" height="1" style="display:none;">';
    $message .= $tracking_pixel;

    // Insert email data into the database
    $table_name = $wpdb->prefix . 'email_tracking_data';
    $wpdb->insert($table_name, [
        'email' => $to,
        'view_count' => 0, // Initial view count
        'last_opened_at' => NULL, // No view yet
        'subject' => $subject, // Insert the email subject
        'generate_time' => current_time('mysql') // Store the time the entry was created
    ]);

    // Send email with tracking pixel
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    wp_mail($to, $subject, $message, $headers);
    echo 'Email sent with tracking pixel!';
}

// Example of how you could use the function to send an email
// eot_send_email_with_tracking('customer@example.com', 'Test Email', 'Hello, this is a test email!');
?>

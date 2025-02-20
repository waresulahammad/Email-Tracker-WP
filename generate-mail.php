<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$table_name = $wpdb->prefix . 'email_tracking_data'; // Ensure correct table name
?>

<div class="wrap">
    <h1>Generate Email Template</h1>

    <form id="email-template-form" method="POST">
        <table class="form-table">
            <tr>
                <th><label for="email">Email:</label></th>
                <td><input type="email" id="email" name="email" class="regular-text" required /></td>
            </tr>
            <tr>
                <th><label for="email_subject">Subject:</label></th>
                <td><input type="text" id="email_subject" name="subject" class="regular-text" required /></td>
            </tr>
            <tr>
                <th><label for="email_body">Email Body:</label></th>
                <td>
                    <?php
                    wp_editor('', 'email_body', [
                        'textarea_rows' => 10,
                        'media_buttons' => false
                    ]);
                    ?>
                </td>
            </tr>
        </table>
        <p>
            <button type="button" id="generate_template" class="button button-primary">Generate Email Template</button>
            <button type="submit" id="save_tracking_data" class="button button-secondary" disabled>Save Data for Tracking</button>
        </p>
    </form>

    <!-- Block Below the Form -->
    <div id="template-block" style="display: none; margin-top: 20px; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;">
        <h2>Generated HTML Template</h2>
        <pre id="template-output" style="background: #fff; padding: 10px; border: 1px solid #ccc; white-space: pre-wrap;"></pre>
        <button id="copy_template" class="button button-secondary">Copy Template</button>
    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const generateButton = document.getElementById("generate_template");
    const copyButton = document.getElementById("copy_template");
    const saveButton = document.getElementById("save_tracking_data");
    const templateOutput = document.getElementById("template-output");
    const templateBlock = document.getElementById("template-block");

    generateButton.addEventListener("click", function() {
        const email = document.getElementById("email").value.trim();
        const subject = document.getElementById("email_subject").value.trim();
        const body = tinymce.get("email_body").getContent();

        if (!email || !subject || !body) {
            alert("Please fill out all fields before generating the template.");
            return;
        }

        // Email tracking open link
        const trackingURL = `http://waresul.site/wp-json/eot/v1/track?email=${encodeURIComponent(email)}`;

        // Generate the email template with tracking pixel
        const templateHTML = `
        <div style="width: 100%; max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; font-family: Arial, sans-serif;">

            <div style="font-size: 16px; color: #555; line-height: 1.5; padding-bottom: 20px;">
                ${body}
            </div>

            <div style="font-size: 14px; color: #777; padding-top: 20px; border-top: 1px solid #ddd; text-align: center;">
                <p>Best regards,</p>
                <div style="margin-top: 20px;">
                    <p style="font-weight: bold;">Website Name</p>
                    <p>info@domain.com</p>
                </div>
                <p><a href="https://website.com/contact" style="color: #1a73e8; text-decoration: none;">Contact Us</a></p>
            </div>

            <!-- Tracking pixel -->
            <img src="${trackingURL}" width="1" height="1" style="display: none;" />
        </div>`;

        templateOutput.textContent = templateHTML; // Display template in <pre> tag
        templateBlock.style.display = "block"; // Show the generated template block
    });

    copyButton.addEventListener("click", function() {
        const textToCopy = templateOutput.textContent;
        navigator.clipboard.writeText(textToCopy).then(() => {
            alert("Template copied to clipboard!");
            saveButton.disabled = false; // Enable the "Save Data for Tracking" button
        }).catch(err => {
            console.error("Error copying text: ", err);
        });
    });
});
</script>

<?php
// Check if form is submitted to save data to the database
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['email']) && isset($_POST['subject'])) {
        global $wpdb;
        $email = sanitize_email($_POST['email']);
        $subject = sanitize_text_field($_POST['subject']);
        $generate_time = current_time('mysql');

        // Insert the data into the database
        $wpdb->insert($table_name, [
            'email' => $email,
            'subject' => $subject,
            'generate_time' => $generate_time
        ]);

        // Display success message
        echo '<div class="updated"><p>Data saved successfully!</p></div>';
    }
}
?>

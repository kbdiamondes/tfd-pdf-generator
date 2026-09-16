<?php
/**
 * Plugin Name: Credit Application PDF
 * Plugin URI: https://github.com/kbdiamondes/tfd-pdf-generator
 * Description: Generates a branded PDF from Ninja Forms credit application submissions and attaches it to email notifications.
 * Version: 1.19.6
 * Author: keithdoesmarketing.com
 * Requires PHP: 7.0
 * Requires Plugins: ninja-forms
 */

if (!defined('ABSPATH')) exit;

// ============================================================
// GITHUB AUTO-UPDATER (manual via settings page)
// ============================================================
// Removed pre_set_site_transient_update_plugins — it caused WP native updater to
// show a broken "Update now" link that redirects to plugins page without updating.
// Updates now happen via the "Update Now" button on the plugin settings page.

add_filter('upgrader_source_selection', function($source, $remote_source, $upgrader_object) {
    // GitHub zips extract to repo-branch/, rename to plugin folder
    $desired = dirname(plugin_basename(__FILE__));
    $extracted = basename(rtrim($source, '/'));
    if ($extracted !== $desired) {
        $new_source = trailingslashit(dirname($source)) . $desired . '/';
        if (@rename($source, $new_source)) {
            return $new_source;
        }
    }
    return $source;
}, 10, 3);

function tfcap_get_version() {
    $plugin_data = get_plugin_data(__FILE__);
    return $plugin_data['Version'] ?? '0.0.0';
}

function tfcap_check_github_update() {
    $cache_key = 'tfcap_github_update';
    $cached = get_transient($cache_key);
    if ($cached !== false) return $cached;

    $repo = 'kbdiamondes/tfd-pdf-generator';
    $api_url = "https://api.github.com/repos/{$repo}/releases/latest";

    $args = ['timeout' => 15, 'headers' => ['Accept' => 'application/vnd.github.v3+json']];

    // Private repo support — set TFCAP_GITHUB_TOKEN in wp-config.php
    if (defined('TFCAP_GITHUB_TOKEN') && TFCAP_GITHUB_TOKEN) {
        $args['headers']['Authorization'] = 'token ' . TFCAP_GITHUB_TOKEN;
    }

    $response = wp_remote_get($api_url, $args);
    if (is_wp_error($response)) return false;

    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) return false;

    $release = json_decode(wp_remote_retrieve_body($response), true);
    if (!$release || empty($release['tag_name'])) return false;

    $version = ltrim($release['tag_name'], 'v');
    $zip_url = $release['zipball_url'] ?? '';

    $result = [
        'version' => $version,
        'url'     => $release['html_url'] ?? '',
        'zip_url' => $zip_url,
        'notes'   => $release['body'] ?? '',
    ];

    set_transient($cache_key, $result, 3600); // cache 1 hour
    return $result;
}

// ============================================================
// FIX FROM ADDRESS — override Gmail From to use plugin setting
// Prevents SPF failure when host sends via PHP mail()
// ============================================================
add_filter('wp_mail', function($args) {
    if (empty($args['headers'])) return $args;

    $from_email = tfcap_get_option('from_email', '');
    if (!$from_email) return $args;

    // Only fix emails sent by Ninja Forms (identified by X-Ninja-Forms header)
    $has_nf_header = false;
    foreach ((array) $args['headers'] as $h) {
        if (stripos($h, 'X-Ninja-Forms') !== false) { $has_nf_header = true; break; }
    }
    if (!$has_nf_header) return $args;

    // Replace From header with plugin setting
    $site_name = get_bloginfo('name');

    foreach ($args['headers'] as $i => $h) {
        if (stripos($h, 'From:') === 0) {
            $args['headers'][$i] = "From: {$site_name} <{$from_email}>";
            break;
        }
    }

    return $args;
});

// ============================================================
// VERSION CHECKER AJAX ENDPOINT
// ============================================================
add_action('wp_ajax_tfcap_check_version', function() {
    check_ajax_referer('tfcap_version_check', 'nonce');
    delete_transient('tfcap_github_update'); // force fresh check
    $remote = tfcap_check_github_update();
    $current = tfcap_get_version();

    if (!$remote) {
        wp_send_json_success(['status' => 'error', 'message' => 'Could not reach GitHub.']);
    }

    if (version_compare($remote['version'], $current, '>')) {
        wp_send_json_success([
            'status'        => 'update_available',
            'current'       => $current,
            'latest'        => $remote['version'],
            'url'           => $remote['url'],
            'release_notes' => wp_strip_all_tags(substr($remote['notes'], 0, 500)),
        ]);
    } else {
        wp_send_json_success(['status' => 'up_to_date', 'current' => $current]);
    }
});

// ============================================================
// MANUAL UPDATE HANDLER — downloads zip, extracts, replaces plugin
// ============================================================
add_action('wp_ajax_tfcap_run_update', function() {
    check_ajax_referer('tfcap_run_update', 'nonce');

    if (!current_user_can('update_plugins')) {
        wp_send_json_success(['status' => 'error', 'message' => 'Insufficient permissions.']);
    }

    $version = isset($_POST['version']) ? sanitize_text_field($_POST['version']) : '';
    if (!$version) {
        wp_send_json_success(['status' => 'error', 'message' => 'No version specified.']);
    }

    $zip_url = "https://api.github.com/repos/kbdiamondes/tfd-pdf-generator/zipball/v{$version}";
    $plugin_dir = plugin_dir_path(__FILE__);
    $plugin_slug = dirname(plugin_basename(__FILE__));

    // Download zip
    $args = ['timeout' => 60, 'headers' => ['Accept' => 'application/vnd.github.v3+json']];
    if (defined('TFCAP_GITHUB_TOKEN') && TFCAP_GITHUB_TOKEN) {
        $args['headers']['Authorization'] = 'token ' . TFCAP_GITHUB_TOKEN;
    }
    $response = wp_remote_get($zip_url, $args);
    if (is_wp_error($response)) {
        wp_send_json_success(['status' => 'error', 'message' => 'Download failed: ' . $response->get_error_message()]);
    }
    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        wp_send_json_success(['status' => 'error', 'message' => 'Download failed (HTTP ' . $code . ').']);
    }

    // Save zip to temp file
    $tmp_zip = wp_tempnam('tfcap_update_');
    file_put_contents($tmp_zip, wp_remote_retrieve_body($response));

    // Extract to temp dir
    $tmp_dir = $tmp_zip . '_extracted';
    if (!@mkdir($tmp_dir, 0755, true)) {
        @unlink($tmp_zip);
        wp_send_json_success(['status' => 'error', 'message' => 'Could not create temp directory.']);
    }

    $zip = new ZipArchive();
    if ($zip->open($tmp_zip) !== true) {
        @unlink($tmp_zip);
        @rmdir($tmp_dir);
        wp_send_json_success(['status' => 'error', 'message' => 'Could not open zip: ' . $zip->getStatusString()]);
    }
    $zip->extractTo($tmp_dir);
    $zip->close();
    @unlink($tmp_zip);

    // Find extracted folder (GitHub names it repo-hash/)
    $extracted_dirs = array_filter(glob($tmp_dir . '/*'), 'is_dir');
    if (empty($extracted_dirs)) {
        @rmdir($tmp_dir);
        wp_send_json_success(['status' => 'error', 'message' => 'Zip was empty.']);
    }
    $extracted = reset($extracted_dirs);

    // Verify it contains the plugin file
    if (!file_exists($extracted . '/tfd-pdf-generator.php')) {
        @unlink($tmp_zip);
        @rmdir($tmp_dir);
        wp_send_json_success(['status' => 'error', 'message' => 'Invalid plugin zip — missing tfd-pdf-generator.php']);
    }

    // Move current plugin to backup
    $backup_dir = $plugin_dir . '../tfd-pdf-generator-backup-' . time();
    if (!@rename($plugin_dir, $backup_dir)) {
        @rmdir($tmp_dir);
        wp_send_json_success(['status' => 'error', 'message' => 'Could not backup current plugin.']);
    }

    // Move new version into place
    if (!@rename($extracted, $plugin_dir)) {
        // Try to restore backup
        @rename($backup_dir, $plugin_dir);
        @rmdir($tmp_dir);
        wp_send_json_success(['status' => 'error', 'message' => 'Could not install new version.']);
    }

    // Cleanup
    @rmdir($tmp_dir);

    // Clear cache
    delete_transient('tfcap_github_update');

    wp_send_json_success(['status' => 'success', 'message' => "Updated to v{$version}"]);
});

// ============================================================
// CONFIG
// ============================================================
define('TFCAP_PDF_DIR', WP_CONTENT_DIR . '/uploads/tfcap-pdfs/');
define('TFCAP_GREEN', [76, 175, 80]); // #4CAF50

// ============================================================
// EMBEDDED NFF TEMPLATE — Download from Settings page
// ============================================================
add_action('admin_init', function() {
    if (isset($_GET['tfcap_download_nff']) && current_user_can('manage_options')) {
        $nff_path = __DIR__ . '/tfd-credit-application.nff';
        if (file_exists($nff_path)) {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="tfd-credit-application.nff"');
            header('Content-Length: ' . filesize($nff_path));
            readfile($nff_path);
            exit;
        }
        // Fallback: embedded template
        $json = tfcap_get_nff_template();
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="tfd-credit-application.nff"');
        header('Content-Length: ' . strlen($json));
        echo $json;
        exit;
    }
});

function tfcap_get_nff_template() {
    // Embedded NFF — 60 fields, 2 signatures, email action
    return '{
  "settings": {
    "title": "TFD Credit Account Application",
    "created_at": "2026-08-07 10:00:00",
    "default_label_pos": "above",
    "show_title": 0,
    "clear_complete": 1,
    "hide_complete": 1,
    "logged_in": 0,
    "seq_num": 1,
    "form_ajax": 0,
    "preserve_entries": 0,
    "logged_in_condition": "",
    "element_label_pos": "above",
    "honeypot": 0
  },
  "fields": [
    {"id":70,"key":"html_70","type":"html","label":"","order":0,"required":0,"default":"<p>This application allows approved customers to pay after their event, within 30 days of the invoice date, instead of paying in full before the event. Please complete every section, sign where indicated, and return by email to bookings@thefundepot.com.au. Incomplete applications cannot be processed.</p>","label_pos":"hidden","personally_identifiable":0},
    {"id":1,"key":"sectiondiv_1","type":"sectiondiv","label":"1. APPLICANT DETAILS","order":1,"required":0,"default":"","label_pos":"above","personally_identifiable":0},
    {"id":2,"key":"textbox_2","type":"textbox","label":"Applicant\'s Full Name / Company Name","order":1,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"Full Name or Company Name"},
    {"id":3,"key":"textbox_3","type":"textbox","label":"A.C.N. (if a company)","order":2,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"A.C.N."},
    {"id":4,"key":"textbox_4","type":"textbox","label":"A.B.N.","order":3,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"A.B.N."},
    {"id":5,"key":"listradio_5","type":"listradio","label":"Applicant is a:","order":4,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"options":[{"label":"Pty Ltd Company","value":"pty_ltd_company","calc":"","selected":0,"order":0},{"label":"Public Company","value":"public_company","calc":"","selected":0,"order":1},{"label":"Individual","value":"individual","calc":"","selected":0,"order":2},{"label":"Partnership","value":"partnership","calc":"","selected":0,"order":3},{"label":"Other","value":"other","calc":"","selected":0,"order":4}],"list_orientation":"vertical","num_columns":1,"show_option_labels":1},
    {"id":6,"key":"textbox_6","type":"textbox","label":"If \\"Other\\", please give details","order":5,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"Please specify"},
    {"id":7,"key":"textbox_7","type":"textbox","label":"Trading Name (only if different from above)","order":6,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"Trading Name"},
    {"id":8,"key":"listradio_8","type":"listradio","label":"Is the trading name a registered business name?","order":7,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"options":[{"label":"Yes","value":"yes","calc":"","selected":0,"order":0},{"label":"No","value":"no","calc":"","selected":0,"order":1}],"list_orientation":"horizontal","num_columns":2,"show_option_labels":1},
    {"id":9,"key":"sectiondiv_9","type":"sectiondiv","label":"2. ACCOUNTS CONTACT DETAILS","order":8,"required":0,"default":"","label_pos":"above","personally_identifiable":0},
    {"id":10,"key":"textbox_10","type":"textbox","label":"Contact Name (Mr/Mrs/Ms)","order":9,"required":1,"default":"","label_pos":"above","personally_identifiable":1,"placeholder":"Mr/Mrs/Ms"},
    {"id":11,"key":"textbox_11","type":"textbox","label":"Position Held","order":10,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"Position"},
    {"id":12,"key":"email_12","type":"email","label":"Accounts Email (for invoices/statements)","order":11,"required":1,"default":"","label_pos":"above","personally_identifiable":1,"placeholder":"accounts@company.com.au"},
    {"id":13,"key":"phone_13","type":"phone","label":"Direct Phone","order":12,"required":0,"default":"","label_pos":"above","personally_identifiable":1,"placeholder":"0400 000 000"},
    {"id":14,"key":"textbox_14","type":"textbox","label":"Postal Address","order":13,"required":0,"default":"","label_pos":"above","personally_identifiable":1,"placeholder":"Street Address, Suburb"},
    {"id":15,"key":"textbox_15","type":"textbox","label":"Postcode","order":14,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"6000","mask":"9999"},
    {"id":16,"key":"sectiondiv_16","type":"sectiondiv","label":"3. BUSINESS ADDRESS","order":15,"required":0,"default":"","label_pos":"above","personally_identifiable":0},
    {"id":17,"key":"textbox_17","type":"textbox","label":"Registered / Business Street Address","order":16,"required":0,"default":"","label_pos":"above","personally_identifiable":1,"placeholder":"Street Address, Suburb"},
    {"id":18,"key":"textbox_18","type":"textbox","label":"Postcode","order":17,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"6000","mask":"9999"},
    {"id":19,"key":"phone_19","type":"phone","label":"Business Landline","order":18,"required":0,"default":"","label_pos":"above","personally_identifiable":1,"placeholder":"08 9000 0000"},
    {"id":20,"key":"phone_20","type":"phone","label":"Mobile","order":19,"required":0,"default":"","label_pos":"above","personally_identifiable":1,"placeholder":"0400 000 000"},
    {"id":21,"key":"sectiondiv_21","type":"sectiondiv","label":"4. DIRECTORS\' PRIVATE ADDRESSES","order":20,"required":0,"default":"","label_pos":"above","personally_identifiable":0},
    {"id":22,"key":"html_22","type":"html","label":"","order":21,"required":0,"default":"<p>If the applicant is a company, please provide details for each director.</p>","label_pos":"hidden","personally_identifiable":0},
    {"id":23,"key":"html_23","type":"html","label":"","order":22,"required":0,"default":"<h4>Director 1</h4>","label_pos":"hidden","personally_identifiable":0},
    {"id":24,"key":"textbox_24","type":"textbox","label":"Director 1 \\u2014 Full Name","order":23,"required":0,"default":"","label_pos":"above","personally_identifiable":1,"placeholder":"Director 1 Full Name"},
    {"id":25,"key":"phone_25","type":"phone","label":"Director 1 \\u2014 Phone / Mobile","order":24,"required":0,"default":"","label_pos":"above","personally_identifiable":1,"placeholder":"0400 000 000"},
    {"id":26,"key":"textbox_26","type":"textbox","label":"Director 1 \\u2014 Address","order":25,"required":0,"default":"","label_pos":"above","personally_identifiable":1,"placeholder":"Residential Address"},
    {"id":27,"key":"textbox_27","type":"textbox","label":"Director 1 \\u2014 Postcode","order":26,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"6000","mask":"9999"},
    {"id":28,"key":"html_28","type":"html","label":"","order":27,"required":0,"default":"<h4>Director 2 (if applicable)</h4>","label_pos":"hidden","personally_identifiable":0},
    {"id":29,"key":"textbox_29","type":"textbox","label":"Director 2 \\u2014 Full Name","order":28,"required":0,"default":"","label_pos":"above","personally_identifiable":1,"placeholder":"Director 2 Full Name"},
    {"id":30,"key":"phone_30","type":"phone","label":"Director 2 \\u2014 Phone / Mobile","order":29,"required":0,"default":"","label_pos":"above","personally_identifiable":1,"placeholder":"0400 000 000"},
    {"id":31,"key":"textbox_31","type":"textbox","label":"Director 2 \\u2014 Address","order":30,"required":0,"default":"","label_pos":"above","personally_identifiable":1,"placeholder":"Residential Address"},
    {"id":32,"key":"textbox_32","type":"textbox","label":"Director 2 \\u2014 Postcode","order":31,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"6000","mask":"9999"},
    {"id":33,"key":"sectiondiv_33","type":"sectiondiv","label":"5. BANKING DETAILS","order":32,"required":0,"default":"","label_pos":"above","personally_identifiable":0},
    {"id":34,"key":"textbox_34","type":"textbox","label":"Name of Bank / Financial Institution","order":33,"required":1,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"e.g. Commonwealth Bank"},
    {"id":35,"key":"textbox_35","type":"textbox","label":"Branch","order":34,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"Branch name or BSB"},
    {"id":36,"key":"html_36","type":"html","label":"","order":35,"required":0,"default":"<p>I/We hereby authorise The Fun Depot (KGO Enterprises Pty Ltd) to make inquiries with the bank named above.</p>","label_pos":"hidden","personally_identifiable":0},
    {"id":37,"key":"sectiondiv_37","type":"sectiondiv","label":"6. TRADE REFERENCES","order":36,"required":0,"default":"","label_pos":"above","personally_identifiable":0},
    {"id":38,"key":"html_38","type":"html","label":"","order":37,"required":0,"default":"<p>Please provide three (3) current trade references.</p>","label_pos":"hidden","personally_identifiable":0},
    {"id":39,"key":"textbox_39","type":"textbox","label":"Reference 1 \\u2014 Company Name","order":38,"required":1,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"Company Name"},
    {"id":40,"key":"phone_40","type":"phone","label":"Reference 1 \\u2014 Phone Number","order":39,"required":1,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"Phone Number"},
    {"id":41,"key":"textbox_41","type":"textbox","label":"Reference 2 \\u2014 Company Name","order":40,"required":1,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"Company Name"},
    {"id":42,"key":"phone_42","type":"phone","label":"Reference 2 \\u2014 Phone Number","order":41,"required":1,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"Phone Number"},
    {"id":43,"key":"textbox_43","type":"textbox","label":"Reference 3 \\u2014 Company Name","order":42,"required":1,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"Company Name"},
    {"id":44,"key":"phone_44","type":"phone","label":"Reference 3 \\u2014 Phone Number","order":43,"required":1,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"Phone Number"},
    {"id":45,"key":"sectiondiv_45","type":"sectiondiv","label":"7. TERMS OF APPLICATION","order":44,"required":0,"default":"","label_pos":"above","personally_identifiable":0},
    {"id":46,"key":"html_46","type":"html","label":"","order":45,"required":0,"default":"<ol><li>I/We declare the information provided is true and correct.</li><li>I/We agree to notify The Fun Depot of any change to the information provided.</li><li>I/We agree to be bound by The Fun Depot\'s Terms and Conditions of Hire.</li><li>Approval to pay on credit terms is granted at The Fun Depot\'s sole discretion.</li><li>The Fun Depot may disclose application details to a credit reporting body.</li><li>Approved credit terms apply only to invoices issued after written approval.</li><li>I certify that I am duly authorised to sign this application.</li></ol>","label_pos":"hidden","personally_identifiable":0},
    {"id":47,"key":"checkbox_47","type":"checkbox","label":"I/We have read, understood and agree to the above Terms","order":46,"required":1,"default":"unchecked","label_pos":"right","personally_identifiable":0,"checked_value":"I / We Agree","unchecked_value":"","checked_calc_value":"1","unchecked_calc_value":"0"},
    {"id":48,"key":"sectiondiv_48","type":"sectiondiv","label":"8. ENDORSEMENT","order":47,"required":0,"default":"","label_pos":"above","personally_identifiable":0},
    {"id":49,"key":"textbox_49","type":"textbox","label":"Signed for and on behalf of (Company / Applicant Name)","order":48,"required":1,"default":"","label_pos":"above","personally_identifiable":1,"placeholder":"Company or Applicant Name"},
    {"id":50,"key":"textbox_50","type":"textbox","label":"Full Name (Director / Company Secretary)","order":49,"required":1,"default":"","label_pos":"above","personally_identifiable":1,"placeholder":"Full Name"},
    {"id":51,"key":"textbox_51","type":"textbox","label":"Position","order":50,"required":1,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"Director / Secretary"},
    {"id":52,"key":"signature_52","type":"signature","label":"Endorsement Signature","order":51,"required":1,"default":"","label_pos":"above","personally_identifiable":0,"signature_method":"drawn","signature_font":"dancing-script","drawn_placeholder":"Sign here","canvas_width":1000,"canvas_height":400,"pen_color":"#000000","background_color":"#ffffff"},
    {"id":53,"key":"date_53","type":"date","label":"Date","order":52,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"dd/mm/yyyy"},
    {"id":54,"key":"sectiondiv_54","type":"sectiondiv","label":"9. DIRECTORS\' GUARANTEE AND INDEMNITY","order":53,"required":0,"default":"","label_pos":"above","personally_identifiable":0},
    {"id":55,"key":"html_55","type":"html","label":"","order":54,"required":0,"default":"<p>In consideration of The Fun Depot agreeing to provide credit terms, we personally guarantee payment of all money owing.</p>","label_pos":"hidden","personally_identifiable":0},
    {"id":56,"key":"html_56","type":"html","label":"","order":55,"required":0,"default":"<h4>Guarantor 1</h4>","label_pos":"hidden","personally_identifiable":0},
    {"id":57,"key":"textbox_57","type":"textbox","label":"Full Name","order":56,"required":0,"default":"","label_pos":"above","personally_identifiable":1,"placeholder":"Guarantor Name"},
    {"id":58,"key":"textbox_58","type":"textbox","label":"Relationship to Applicant (e.g. Director)","order":57,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"e.g. Director"},
    {"id":59,"key":"textbox_59","type":"textbox","label":"Residential Address","order":58,"required":0,"default":"","label_pos":"above","personally_identifiable":1,"placeholder":"Residential Address"},
    {"id":60,"key":"signature_60","type":"signature","label":"Guarantor Signature","order":59,"required":1,"default":"","label_pos":"above","personally_identifiable":0,"signature_method":"drawn","signature_font":"dancing-script","drawn_placeholder":"Sign here","canvas_width":1000,"canvas_height":400,"pen_color":"#000000","background_color":"#ffffff"},
    {"id":61,"key":"date_61","type":"date","label":"Date","order":60,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"dd/mm/yyyy"},
    {"id":62,"key":"html_62","type":"html","label":"","order":61,"required":0,"default":"<h4>Guarantor 2 (if applicable)</h4>","label_pos":"hidden","personally_identifiable":0},
    {"id":63,"key":"textbox_63","type":"textbox","label":"Full Name","order":62,"required":0,"default":"","label_pos":"above","personally_identifiable":1,"placeholder":"Guarantor 2 Name"},
    {"id":64,"key":"textbox_64","type":"textbox","label":"Relationship to Applicant (e.g. Director)","order":63,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"e.g. Director"},
    {"id":65,"key":"textbox_65","type":"textbox","label":"Residential Address","order":64,"required":0,"default":"","label_pos":"above","personally_identifiable":1,"placeholder":"Residential Address"},
    {"id":66,"key":"signature_66","type":"signature","label":"Guarantor 2 Signature","order":65,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"signature_method":"drawn","signature_font":"dancing-script","drawn_placeholder":"Sign here","canvas_width":1000,"canvas_height":400,"pen_color":"#000000","background_color":"#ffffff"},
    {"id":67,"key":"date_67","type":"date","label":"Date","order":66,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"dd/mm/yyyy"},
    {"id":68,"key":"checkbox_68","type":"checkbox","label":"I/We have read, understood and agree to the Directors\' Guarantee","order":67,"required":1,"default":"unchecked","label_pos":"right","personally_identifiable":0,"checked_value":"I / We Agree","unchecked_value":"","checked_calc_value":"1","unchecked_calc_value":"0"},
    {"id":71,"key":"html_71","type":"html","label":"","order":68,"required":0,"default":"<p>Please email the completed and signed application to bookings@thefundepot.com.au. If any section does not apply, please write &quot;N/A&quot; rather than leaving it blank. We will confirm your approved credit terms in writing before they take effect.</p>","label_pos":"hidden","personally_identifiable":0},
    {"id":72,"key":"sectiondiv_72","type":"sectiondiv","label":"THE FUN DEPOT \\u2014 OFFICE USE ONLY","order":69,"required":0,"default":"","label_pos":"above","personally_identifiable":0},
    {"id":73,"key":"textbox_73","type":"textbox","label":"Date Received","order":70,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"dd/mm/yyyy"},
    {"id":74,"key":"textbox_74","type":"textbox","label":"Administration Check By","order":71,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"Name"},
    {"id":75,"key":"textbox_75","type":"textbox","label":"Approved By","order":72,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"Name"},
    {"id":76,"key":"textbox_76","type":"textbox","label":"Approval Date","order":73,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"dd/mm/yyyy"},
    {"id":77,"key":"textbox_77","type":"textbox","label":"Signature of Approver","order":74,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"Signature"},
    {"id":78,"key":"textbox_78","type":"textbox","label":"Account Name","order":75,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"Account Name"},
    {"id":79,"key":"textbox_79","type":"textbox","label":"Approval Letter Sent (Email/Post + Date)","order":76,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"e.g. Email 16/09/2026"},
    {"id":80,"key":"textbox_80","type":"textbox","label":"Customer Managed By","order":77,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"placeholder":"Name"},
    {"id":81,"key":"submit_81","type":"submit","label":"Submit Credit Application","order":78,"required":0,"default":"","label_pos":"above","personally_identifiable":0,"processing_label":"Processing..."}
  ],
  "actions": [
    {"type":"save","label":"Save Form Submission","active":true},
    {"type":"email","label":"Email Notification","active":true,"to":"bookings@thefundepot.com.au","from":"{wp:admin_email}","from_name":"The Fun Depot","email_subject":"New Credit Application \\u2014 {field:textbox_49}","email_message":"<h2>New Credit Account Application</h2><p><strong>Company/Applicant:</strong> {field:textbox_49}</p><p><strong>Contact Name:</strong> {field:textbox_10}</p><p><strong>Email:</strong> {field:email_12}</p><p><strong>Phone:</strong> {field:phone_13}</p><hr><p>Full submission in WordPress admin \\u2192 Ninja Forms \\u2192 Submissions.</p>","email_format":"html"}
  ]
}';
}

function tfcap_get_option($key, $default = '') {
    return get_option('tfcap_' . $key, $default);
}

// ============================================================
// FIX SIGNATURE PAD FADING
// ============================================================
add_action('wp_enqueue_scripts', function() {
    if (class_exists('Ninja_Forms')) {
        wp_enqueue_script('tfcap-sig-fix', plugin_dir_url(__FILE__) . 'tfd-signature-fix.js', [], '1.0', true);
    }
});
add_action('admin_enqueue_scripts', function() {
    if (class_exists('Ninja_Forms')) {
        wp_enqueue_script('tfcap-sig-fix', plugin_dir_url(__FILE__) . 'tfd-signature-fix.js', [], '1.0', true);
    }
});

// ============================================================
// HELPER FUNCTIONS
// ============================================================
function tfcap_val($fields, $id, $default = '') {
    // Try by array key first (internal NF ID)
    if (is_array($fields) && isset($fields[$id])) {
        $f = $fields[$id];
        return isset($f['value']) ? $f['value'] : $default;
    }
    return $default;
}

// Look up field value by field key name (e.g. 'first_name', 'textbox_3')
function tfcap_by_key($fields, $key_name, $default = '') {
    if (!is_array($fields)) return $default;
    foreach ($fields as $f) {
        if (is_array($f) && isset($f['key']) && $f['key'] === $key_name) {
            return isset($f['value']) ? $f['value'] : $default;
        }
    }
    return $default;
}

// Look up field value by its position (0-based index) in the fields array
function tfcap_by_index($fields, $index, $default = '') {
    if (!is_array($fields)) return $default;
    $arr = array_values($fields);
    if (isset($arr[$index])) {
        $f = $arr[$index];
        return (is_array($f) && isset($f['value'])) ? $f['value'] : $default;
    }
    return $default;
}

function tfcap_safe($fields, $id, $default = '') {
    $val = tfcap_val($fields, $id, $default);
    return htmlspecialchars(strip_tags((string) $val), ENT_QUOTES, 'UTF-8');
}

// ============================================================
// PNG HELPER FUNCTIONS (for direct PNG embedding)
// ============================================================
function tfcap_png_reverse_filter($filter, $row, $prev_row, $bpp) {
    $len = strlen($row);
    $out = $row;

    switch ($filter) {
        case 0: // None — no-op
            break;
        case 1: // Sub
            for ($i = $bpp; $i < $len; $i++) {
                $out[$i] = chr((ord($out[$i]) + ord($out[$i - $bpp])) & 0xFF);
            }
            break;
        case 2: // Up
            for ($i = 0; $i < $len; $i++) {
                $out[$i] = chr((ord($out[$i]) + ord($prev_row[$i])) & 0xFF);
            }
            break;
        case 3: // Average
            for ($i = 0; $i < $len; $i++) {
                $left = ($i >= $bpp) ? ord($out[$i - $bpp]) : 0;
                $up = ord($prev_row[$i]);
                $out[$i] = chr((ord($out[$i]) + (int)(($left + $up) / 2)) & 0xFF);
            }
            break;
        case 4: // Paeth
            for ($i = 0; $i < $len; $i++) {
                $left = ($i >= $bpp) ? ord($out[$i - $bpp]) : 0;
                $up = ord($prev_row[$i]);
                $up_left = ($i >= $bpp) ? ord($prev_row[$i - $bpp]) : 0;
                $out[$i] = chr((ord($out[$i]) + tfcap_paeth($left, $up, $up_left)) & 0xFF);
            }
            break;
    }
    return $out;
}

function tfcap_paeth($a, $b, $c) {
    $p = $a + $b - $c;
    $pa = abs($p - $a);
    $pb = abs($p - $b);
    $pc = abs($p - $c);
    if ($pa <= $pb && $pa <= $pc) return $a;
    if ($pb <= $pc) return $b;
    return $c;
}

// ============================================================
// MINIMAL PDF GENERATOR (Zero Dependencies)
// ============================================================
class TFCAP_PDF {
    private $pages = [];
    private $current_page = -1;
    private $images = []; // embedded images (PNG data)

    const PAGE_W = 595.28; // A4 in points
    const PAGE_H = 841.89;
    const MARGIN = 56.69;  // 20mm

    function __construct() {
        $this->addPage();
    }

    function addPage() {
        $this->pages[] = ['content' => '', 'y' => self::PAGE_H - self::MARGIN];
        $this->current_page = count($this->pages) - 1;
    }

    private function &page() {
        return $this->pages[$this->current_page];
    }

    function setY($y) { $this->page()['y'] = $y; }
    function getY()    { return $this->page()['y']; }

    function checkPage($needed = 20) {
        if ($this->getY() - $needed < self::MARGIN) {
            $this->addPage();
        }
    }

    function raw($content) {
        $this->page()['content'] .= $content;
    }

    function text($x, $y, $text, $size = 10, $color = [26, 26, 26], $style = '') {
        // Replace unsupported UTF-8 chars with PDF-safe equivalents
        $text = str_replace('™', '(TM)', $text);
        $text = str_replace('©', '(c)', $text);
        $text = str_replace('®', '(R)', $text);
        $text = str_replace('—', '-', $text);
        $text = str_replace('–', '-', $text);
        $text = str_replace('"', '"', $text);
        $text = str_replace('"', '"', $text);
        $text = str_replace("'", "'", $text);
        $text = str_replace("'", "'", $text);
        $text = str_replace('…', '...', $text);

        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace('(', '\\(', $text);
        $text = str_replace(')', '\\)', $text);

        $font = 'Helvetica';
        if (strpos($style, 'B') !== false) $font = 'Helvetica-Bold';
        if (strpos($style, 'I') !== false) $font = 'Helvetica-Oblique';

        // PDF colors are 0-1, not 0-255
        $r = round($color[0] / 255, 3);
        $g = round($color[1] / 255, 3);
        $b = round($color[2] / 255, 3);
        $this->raw("BT /F1 {$size} Tf {$r} {$g} {$b} rg {$x} {$y} Td ({$text}) Tj ET\n");
    }

    function line($x1, $y1, $x2, $y2, $width = 0.5, $color = [26, 26, 26]) {
        $r = round($color[0] / 255, 3);
        $g = round($color[1] / 255, 3);
        $b = round($color[2] / 255, 3);
        $this->raw("q {$r} {$g} {$b} RG {$width} w {$x1} {$y1} m {$x2} {$y2} l S Q\n");
    }

    function rect($x, $y, $w, $h, $fill = false, $color = [200, 200, 200], $width = 0.5) {
        $r = round($color[0] / 255, 3);
        $g = round($color[1] / 255, 3);
        $b = round($color[2] / 255, 3);
        if ($fill) {
            $this->raw("q {$r} {$g} {$b} rg {$x} {$y} {$w} {$h} re f Q\n");
        } else {
            $this->raw("q {$r} {$g} {$b} RG {$width} w {$x} {$y} {$w} {$h} re S Q\n");
        }
    }

    function dashedRect($x, $y, $w, $h, $color = [200, 200, 200]) {
        $r = round($color[0] / 255, 3);
        $g = round($color[1] / 255, 3);
        $b = round($color[2] / 255, 3);
        $this->raw("q {$r} {$g} {$b} RG 0.5 w [3 2] 0 d {$x} {$y} {$w} {$h} re S Q\n");
    }

    // Greyed-out read-only field (for OFFICE USE section)
    function readOnlyField($label, $value = '', $label_w = 150) {
        $this->checkPage(14);
        $y = $this->getY();
        // Light grey background
        $this->raw("q 0.95 0.95 0.95 rg " . self::MARGIN . " " . ($y - 2) . " " . (self::PAGE_W - 2 * self::MARGIN) . " 12 re f Q\n");
        $this->text(self::MARGIN + 2, $y, $label, 8, [153, 153, 153]);
        if ($value) {
            $this->text(self::MARGIN + $label_w + 2, $y, $value, 9, [153, 153, 153]);
        }
        $this->setY($y - 14);
    }

    function readOnlyTwoCol($label1, $val1, $label2, $val2) {
        $this->checkPage(14);
        $y = $this->getY();
        $mid = self::PAGE_W / 2;
        // Light grey background
        $this->raw("q 0.95 0.95 0.95 rg " . self::MARGIN . " " . ($y - 2) . " " . (self::PAGE_W - 2 * self::MARGIN) . " 12 re f Q\n");
        $this->text(self::MARGIN + 2, $y, $label1, 8, [153, 153, 153]);
        if ($val1) $this->text(self::MARGIN + 112, $y, $val1, 9, [153, 153, 153]);
        $this->text($mid + 2, $y, $label2, 8, [153, 153, 153]);
        if ($val2) $this->text($mid + 112, $y, $val2, 9, [153, 153, 153]);
        $this->setY($y - 14);
    }

    function sectionHeader($text, $size = 13) {
        $this->checkPage(40);
        $y = $this->getY();
        $y -= 10; // top padding before header
        $this->text(self::MARGIN, $y, $text, $size, [26, 26, 26], 'B');
        $this->line(self::MARGIN, $y - 4, self::PAGE_W - self::MARGIN, $y - 4, 2, TFCAP_GREEN);
        $this->setY($y - 30); // bottom padding after header
    }

    function subHeader($text) {
        $this->checkPage(20);
        $y = $this->getY();
        $this->text(self::MARGIN, $y, $text, 11, [51, 51, 51], 'B');
        $this->line(self::MARGIN, $y - 3, self::PAGE_W - self::MARGIN, $y - 3, 0.5, [224, 224, 224]);
        $this->setY($y - 14);
    }

    // Measure approximate width of a string in Helvetica Bold at given pt size
    private function measureBold($text, $size) {
        // Helvetica Bold AFM character widths at 10pt scale (divide by 10 to get per-pt)
        $widths = [
            ' '=>2.78,'!'=>2.78,'"'=>3.55,'#'=>5.56,'$'=>5.56,'%'=>8.89,
            '&'=>7.22,'\''=>2.22,'('=>3.33,')'=>3.33,'*'=>3.89,'+'=>5.84,
            ','=>2.78,'-'=>3.33,'.'=>2.78,'/'=>3.33,'0'=>5.56,'1'=>5.56,
            '2'=>5.56,'3'=>5.56,'4'=>5.56,'5'=>5.56,'6'=>5.56,'7'=>5.56,
            '8'=>5.56,'9'=>5.56,':'=>2.78,';'=>2.78,'<'=>5.84,'='=>5.84,
            '>'=>5.84,'?'=>5.56,'@'=>7.37,'A'=>6.67,'B'=>6.67,'C'=>7.22,
            'D'=>7.22,'E'=>6.67,'F'=>6.11,'G'=>7.78,'H'=>7.22,'I'=>2.78,
            'J'=>5.56,'K'=>7.22,'L'=>6.11,'M'=>8.89,'N'=>7.22,'O'=>7.78,
            'P'=>6.67,'Q'=>7.78,'R'=>7.22,'S'=>6.67,'T'=>6.11,'U'=>7.22,
            'V'=>6.67,'W'=>9.44,'X'=>6.67,'Y'=>6.67,'Z'=>6.11,
            '['=>3.33,'\\'=>3.33,']'=>3.33,'^'=>5.84,'_'=>5.56,
            '`'=>3.33,'a'=>5.56,'b'=>6.11,'c'=>5.56,'d'=>6.11,'e'=>5.56,
            'f'=>3.89,'g'=>6.11,'h'=>6.11,'i'=>2.78,'j'=>3.33,'k'=>6.11,
            'l'=>2.78,'m'=>8.89,'n'=>6.11,'o'=>6.11,'p'=>6.11,'q'=>6.11,
            'r'=>3.89,'s'=>5.56,'t'=>3.89,'u'=>6.11,'v'=>5.56,'w'=>7.78,
            'x'=>5.56,'y'=>5.56,'z'=>5.56,
        ];
        $w = 0;
        for ($i = 0; $i < mb_strlen($text); $i++) {
            $ch = mb_substr($text, $i, 1);
            $w += isset($widths[$ch]) ? $widths[$ch] : 5.56;
        }
        // widths are at 10pt scale — divide by 10, then multiply by actual size
        return $w * $size / 10;
    }

    function fieldRow($label, $value, $label_w = 0) {
        $this->checkPage(14);
        $y = $this->getY();
        $line_h = 12;
        $full_w = self::PAGE_W - 2 * self::MARGIN;
        if ($label_w <= 0) {
            $measured = $this->measureBold($label, 9);
            $label_w = min($full_w * 0.58, $measured + 12);
        }
        $val_x = self::MARGIN + $label_w;
        $val_max = self::PAGE_W - self::MARGIN - $val_x;
        $lines = $this->wrapText($value ?: '—', $val_max, 10);
        $this->text(self::MARGIN, $y, $label, 9, [51, 51, 51], 'B');
        foreach ($lines as $i => $l) {
            $this->text($val_x, $y - ($i * $line_h), $l, 10, [26, 26, 26]);
        }
        $this->setY($y - (count($lines) * $line_h));
    }

    function twoColField($label1, $value1, $label2, $value2) {
        $this->checkPage(14);
        $y = $this->getY();
        $mid = self::PAGE_W / 2;
        $line_h = 12;
        $col_w = $mid - self::MARGIN - 8;

        // Measure labels and cap at 58% of column width
        $lbl1_w = min($col_w * 0.58, $this->measureBold($label1, 9) + 12);
        $lbl2_w = min($col_w * 0.58, $this->measureBold($label2, 9) + 12);

        // Column 1
        $val1_x = self::MARGIN + $lbl1_w;
        $val1_max = $mid - $val1_x - 4;
        $lines1 = $this->wrapText($value1 ?: '—', $val1_max, 10);
        $this->text(self::MARGIN, $y, $label1, 9, [51, 51, 51], 'B');
        foreach ($lines1 as $i => $l) {
            $this->text($val1_x, $y - ($i * $line_h), $l, 10, [26, 26, 26]);
        }

        // Column 2
        $val2_x = $mid + $lbl2_w;
        $val2_max = self::PAGE_W - self::MARGIN - $val2_x;
        $lines2 = $this->wrapText($value2 ?: '—', $val2_max, 10);
        $this->text($mid, $y, $label2, 9, [51, 51, 51], 'B');
        foreach ($lines2 as $i => $l) {
            $this->text($val2_x, $y - ($i * $line_h), $l, 10, [26, 26, 26]);
        }

        $max_lines = max(count($lines1), count($lines2));
        $this->setY($y - ($max_lines * $line_h));
    }

    // Wrap text to fit within $max_w points
    private function wrapText($text, $max_w, $size = 10) {
        if (!$text) return ['—'];
        $pt_per_char = $size * 0.52;
        $max_chars = (int)floor($max_w / $pt_per_char);
        if ($max_chars < 10) $max_chars = 10;

        $words = explode(' ', $text);
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            // If single word is longer than max_chars, split it
            if (mb_strlen($word) > $max_chars) {
                // Finish current line first
                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }
                // Split long word into chunks
                while (mb_strlen($word) > $max_chars) {
                    $lines[] = mb_substr($word, 0, $max_chars);
                    $word = mb_substr($word, $max_chars);
                }
                $line = $word;
                continue;
            }

            $test = $line . ($line ? ' ' : '') . $word;
            if (mb_strlen($test) > $max_chars && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $test;
            }
        }
        if ($line) $lines[] = $line;
        return $lines ?: ['—'];
    }

    function note($text) {
        $this->checkPage(14);
        $y = $this->getY();
        $words = explode(' ', $text);
        $line = '';
        $lines = [];
        foreach ($words as $word) {
            $test = $line . ($line ? ' ' : '') . $word;
            if (strlen($test) * 4.5 > (self::PAGE_W - 2 * self::MARGIN)) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $test;
            }
        }
        if ($line) $lines[] = $line;
        foreach ($lines as $l) {
            $this->text(self::MARGIN, $y, $l, 9, [102, 102, 102]);
            $y -= 12;
        }
        $this->setY($y - 4);
    }

    function signatureBox($label, $sig_data) {
        $this->checkPage(200);
        $y = $this->getY();
        $box_h = 200;
        $box_w = 500;
        $box_x = self::MARGIN;

        $this->text($box_x, $y, $label, 9, [102, 102, 102]);
        $y -= 6;
        $this->dashedRect($box_x, $y - $box_h, $box_w, $box_h);

        $has_sig = false;
        if (!empty($sig_data)) {
            // NF3 signature is JSON: {"signature_type":"drawn","signature_data":"data:image/png;base64,..."}
            $decoded = json_decode($sig_data, true);
            $img_b64 = null;
            if ($decoded && isset($decoded['signature_data'])) {
                $img_b64 = $decoded['signature_data'];
            } elseif (strpos($sig_data, 'data:image') !== false) {
                $img_b64 = $sig_data;
            }

            if ($img_b64) {
                // White background behind signature (prevents transparent PNG fading)
                $this->raw("q 1 1 1 rg " . ($box_x + 8) . " " . ($y - $box_h + 8) . " " . ($box_w - 16) . " " . ($box_h - 16) . " re f Q\n");
                // Scale to fit box — maintain aspect ratio
                $has_sig = $this->embedImage($box_x + 8, $y - $box_h + 8, $box_w - 16, $box_h - 16, $img_b64);
            }
        }

        if (!$has_sig) {
            $this->text($box_x + 80, $y - 30, '[No signature captured]', 9, [153, 153, 153]);
        }

        $this->setY($y - $box_h - 10);
    }

    // Embed a base64 image into the PDF — direct PNG embed with correct alpha handling
    private function embedImage($x, $y, $max_w, $max_h, $data_uri) {
        if (preg_match('/data:image\/(\w+);base64,(.*)/', $data_uri, $m)) {
            $b64 = $m[2];
        } else {
            return false;
        }

        $png_data = base64_decode($b64);
        if (!$png_data || strlen($png_data) < 8) return false;

        // Verify PNG signature
        if (substr($png_data, 0, 8) !== "\x89PNG\r\n\x1a\n") return false;

        // Parse PNG chunks
        $pos = 8;
        $len = strlen($png_data);
        $idat = '';
        $width = $height = $bit_depth = $color_type = 0;

        while ($pos < $len) {
            if ($pos + 8 > $len) break;
            $chunk_len = unpack('N', substr($png_data, $pos, 4))[1];
            $chunk_type = substr($png_data, $pos + 4, 4);
            $chunk_data = substr($png_data, $pos + 8, $chunk_len);

            switch ($chunk_type) {
                case 'IHDR':
                    $width = unpack('N', substr($chunk_data, 0, 4))[1];
                    $height = unpack('N', substr($chunk_data, 4, 4))[1];
                    $bit_depth = ord($chunk_data[8]);
                    $color_type = ord($chunk_data[9]);
                    break;
                case 'IDAT':
                    $idat .= $chunk_data;
                    break;
                case 'IEND':
                    break 2;
            }

            $pos += 12 + $chunk_len;
        }

        if (!$width || !$height || !$idat) return false;

        $has_alpha = ($color_type === 4 || $color_type === 6);

        tfcap_log("embedImage: PNG {$width}x{$height}, color_type={$color_type}, has_alpha=" . ($has_alpha ? 'yes' : 'no'));

        // No alpha — decompress, crop whitespace, resize, recompress
        if (!$has_alpha) {
            $colors = ($color_type === 0) ? 1 : 3;
            $colorspace = ($color_type === 0) ? '/DeviceGray' : '/DeviceRGB';
            $channels_out = $colors;

            $filtered = @gzuncompress($idat);
            if ($filtered === false) { tfcap_log("embedImage(no-alpha): gzuncompress failed"); return false; }

            // Reconstruct raw pixel rows (with filter bytes)
            $row_stride = 1 + $width * $channels_out;
            $raw_out = '';
            $prev_raw = str_repeat("\x00", $width * $channels_out);
            for ($y_row = 0; $y_row < $height; $y_row++) {
                $offset = $y_row * $row_stride;
                $filter_byte = ord($filtered[$offset]);
                $row_bytes = substr($filtered, $offset + 1, $width * $channels_out);
                $row_raw = tfcap_png_reverse_filter($filter_byte, $row_bytes, $prev_raw, $channels_out);
                $prev_raw = $row_raw;
                $raw_out .= "\x00" . $row_raw;
            }

            // Crop whitespace
            list($raw_out, $width, $height) = $this->cropWhitespace($raw_out, $width, $height, $channels_out, 1 + $width * $channels_out);

            // Resize to fit
            if ($width > $max_w || $height > $max_h) {
                $scale = min($max_w / $width, $max_h / $height);
                $final_w = (int)floor($width * $scale);
                $final_h = (int)floor($height * $scale);
                $old_stride = 1 + $width * $channels_out;
                $resized = '';
                for ($ry = 0; $ry < $final_h; $ry++) {
                    $src_y = (int)floor($ry / $scale);
                    $resized .= "\x00";
                    for ($rx = 0; $rx < $final_w; $rx++) {
                        $src_x = (int)floor($rx / $scale);
                        $src_off = $src_y * $old_stride + 1 + $src_x * $channels_out;
                        $resized .= substr($raw_out, $src_off, $channels_out);
                    }
                }
                $raw_out = $resized;
                $width = $final_w;
                $height = $final_h;
                tfcap_log("embedImage(no-alpha): resized to {$final_w}x{$final_h}");
            } else {
                $final_w = $width;
                $final_h = $height;
            }

            $compressed_out = @gzcompress($raw_out, 6);
            if (!$compressed_out) { tfcap_log("embedImage(no-alpha): gzcompress failed"); return false; }

            $this->images[] = [
                'data' => $compressed_out,
                'w' => $final_w,
                'h' => $final_h,
                'filter' => '/FlateDecode',
                'colorspace' => $colorspace,
                'bit_depth' => $bit_depth,
                'decode_parms' => "<< /Predictor 15 /Colors {$channels_out} /BitsPerComponent {$bit_depth} /Columns {$final_w} >>",
            ];
            $img_idx = count($this->images);
            $this->raw("q {$final_w} 0 0 {$final_h} {$x} {$y} cm /I{$img_idx} Do Q\n");
            return true;
        }

        // Has alpha — decompress, reverse filters, flatten onto white, recompress
        $channels_in = ($color_type === 6) ? 4 : 2;
        $channels_out = ($color_type === 6) ? 3 : 1;
        $colorspace = ($color_type === 6) ? '/DeviceRGB' : '/DeviceGray';

        $filtered = @gzuncompress($idat);
        if ($filtered === false) { tfcap_log("embedImage: gzuncompress failed"); return false; }

        $row_stride = 1 + $width * $channels_in;
        $prev_raw = str_repeat("\x00", $width * $channels_in);
        $raw_out = '';

        for ($y_row = 0; $y_row < $height; $y_row++) {
            $offset = $y_row * $row_stride;
            $filter_byte = ord($filtered[$offset]);
            $row_bytes = substr($filtered, $offset + 1, $width * $channels_in);

            // Reverse the PNG row filter to get actual pixel values
            $row_raw = tfcap_png_reverse_filter($filter_byte, $row_bytes, $prev_raw, $channels_in);
            $prev_raw = $row_raw;

            $out_row = "\x00"; // filter byte = None for output rows
            for ($x_col = 0; $x_col < $width; $x_col++) {
                $px = $x_col * $channels_in;

                if ($color_type === 6) { // RGBA
                    $r = ord($row_raw[$px]);
                    $g = ord($row_raw[$px + 1]);
                    $b = ord($row_raw[$px + 2]);
                    $a = ord($row_raw[$px + 3]);
                    // Correct alpha blend onto white — a is 0-255
                    $out_row .= chr((int)(($r * $a + 255 * (255 - $a)) / 255));
                    $out_row .= chr((int)(($g * $a + 255 * (255 - $a)) / 255));
                    $out_row .= chr((int)(($b * $a + 255 * (255 - $a)) / 255));
                } else { // Gray+Alpha
                    $gray = ord($row_raw[$px]);
                    $a = ord($row_raw[$px + 1]);
                    $out_row .= chr((int)(($gray * $a + 255 * (255 - $a)) / 255));
                }
            }
            $raw_out .= $out_row;
        }

        // ── Crop whitespace ──────────────────────────────────────────────────
        list($raw_out, $width, $height) = $this->cropWhitespace($raw_out, $width, $height, $channels_out, 1 + $width * $channels_out);
        // ── End crop ────────────────────────────────────────────────────────

        // ── Nearest-neighbor resize ─────────────────────────────────────────
        if ( $width > $max_w || $height > $max_h ) {
            $scale   = min( $max_w / $width, $max_h / $height );
            $final_w = (int) floor( $width  * $scale );
            $final_h = (int) floor( $height * $scale );

            $row_stride = 1 + $width * $channels_out;
            $resized    = '';

            for ( $ry = 0; $ry < $final_h; $ry++ ) {
                $src_y    = (int) floor( $ry / $scale );
                $resized .= "\x00"; // filter byte = None for every output row

                for ( $rx = 0; $rx < $final_w; $rx++ ) {
                    $src_x   = (int) floor( $rx / $scale );
                    $src_off = $src_y * $row_stride + 1 + $src_x * $channels_out;
                    $resized .= substr( $raw_out, $src_off, $channels_out );
                }
            }

            $raw_out = $resized;

            error_log( "TFCAP resize: {$width}x{$height} → {$final_w}x{$final_h} (scale={$scale})" );

        } else {
            $final_w = $width;
            $final_h = $height;
        }
        // ── End resize ─────────────────────────────────────────────────────────

        $compressed_out = @gzcompress($raw_out, 6);
        if (!$compressed_out) { tfcap_log("embedImage: gzcompress failed"); return false; }

        tfcap_log("embedImage: flattened RGB=" . strlen($raw_out) . " bytes, compressed=" . strlen($compressed_out));

        $this->images[] = [
            'data' => $compressed_out,
            'w' => $final_w,
            'h' => $final_h,
            'filter' => '/FlateDecode',
            'colorspace' => $colorspace,
            'bit_depth' => $bit_depth,
            // Output rows have filter byte 0 (None) — Predictor 15 reads this correctly
            'decode_parms' => "<< /Predictor 15 /Colors {$channels_out} /BitsPerComponent {$bit_depth} /Columns {$final_w} >>",
        ];
        $img_idx = count($this->images);
        $this->raw("q {$final_w} 0 0 {$final_h} {$x} {$y} cm /I{$img_idx} Do Q\n");
        return true;
    }

    // Crop whitespace from raw pixel data — returns [cropped_data, new_width, new_height]
    private function cropWhitespace($raw_out, $width, $height, $channels, $stride) {
        $white_thresh = 250;
        $crop_top = $crop_bottom = $crop_left = $crop_right = 0;
        $found = false;

        // Scan from top
        for ($cy = 0; $cy < $height && !$found; $cy++) {
            for ($cx = 0; $cx < $width; $cx++) {
                $px = $cy * $stride + 1 + $cx * $channels;
                $non_white = false;
                for ($ch = 0; $ch < $channels; $ch++) {
                    if (ord($raw_out[$px + $ch]) < $white_thresh) { $non_white = true; break; }
                }
                if ($non_white) { $crop_top = $cy; $found = true; break; }
            }
        }

        // Scan from bottom
        $found = false;
        for ($cy = $height - 1; $cy >= 0 && !$found; $cy--) {
            for ($cx = 0; $cx < $width; $cx++) {
                $px = $cy * $stride + 1 + $cx * $channels;
                $non_white = false;
                for ($ch = 0; $ch < $channels; $ch++) {
                    if (ord($raw_out[$px + $ch]) < $white_thresh) { $non_white = true; break; }
                }
                if ($non_white) { $crop_bottom = $cy; $found = true; break; }
            }
        }

        // Scan from left
        $found = false;
        for ($cx = 0; $cx < $width && !$found; $cx++) {
            for ($cy = $crop_top; $cy <= $crop_bottom; $cy++) {
                $px = $cy * $stride + 1 + $cx * $channels;
                $non_white = false;
                for ($ch = 0; $ch < $channels; $ch++) {
                    if (ord($raw_out[$px + $ch]) < $white_thresh) { $non_white = true; break; }
                }
                if ($non_white) { $crop_left = $cx; $found = true; break; }
            }
        }

        // Scan from right
        $found = false;
        for ($cx = $width - 1; $cx >= 0 && !$found; $cx--) {
            for ($cy = $crop_top; $cy <= $crop_bottom; $cy++) {
                $px = $cy * $stride + 1 + $cx * $channels;
                $non_white = false;
                for ($ch = 0; $ch < $channels; $ch++) {
                    if (ord($raw_out[$px + $ch]) < $white_thresh) { $non_white = true; break; }
                }
                if ($non_white) { $crop_right = $cx; $found = true; break; }
            }
        }

        // Apply crop with 8px padding
        $pad = 8;
        $crop_top    = max(0, $crop_top - $pad);
        $crop_bottom = min($height - 1, $crop_bottom + $pad);
        $crop_left   = max(0, $crop_left - $pad);
        $crop_right  = min($width - 1, $crop_right + $pad);

        $crop_w = $crop_right - $crop_left + 1;
        $crop_h = $crop_bottom - $crop_top + 1;

        if ($crop_w > 0 && $crop_h > 0 && ($crop_w < $width || $crop_h < $height)) {
            $new_stride = 1 + $crop_w * $channels;
            $cropped = '';
            for ($cy = $crop_top; $cy <= $crop_bottom; $cy++) {
                $cropped .= "\x00";
                $src_off = $cy * $stride + 1 + $crop_left * $channels;
                $cropped .= substr($raw_out, $src_off, $crop_w * $channels);
            }
            tfcap_log("cropWhitespace: {$crop_w}x{$crop_h} from {$width}x{$height}");
            return [$cropped, $crop_w, $crop_h];
        }

        return [$raw_out, $width, $height];
    }

    // Embed a base64 PNG at native resolution — no resize, no quality loss
    private function embedImageNoResize($x, $y, $max_w, $max_h, $data_uri) {
        if (preg_match('/data:image\/(\w+);base64,(.*)/', $data_uri, $m)) {
            $b64 = $m[2];
        } else {
            return false;
        }

        $png_data = base64_decode($b64);
        if (!$png_data || strlen($png_data) < 8) return false;

        // Verify PNG signature
        if (substr($png_data, 0, 8) !== "\x89PNG\r\n\x1a\n") return false;

        // Parse PNG chunks
        $pos = 8;
        $len = strlen($png_data);
        $idat = '';
        $width = $height = $bit_depth = $color_type = 0;

        while ($pos < $len) {
            if ($pos + 8 > $len) break;
            $chunk_len = unpack('N', substr($png_data, $pos, 4))[1];
            $chunk_type = substr($png_data, $pos + 4, 4);
            $chunk_data = substr($png_data, $pos + 8, $chunk_len);

            switch ($chunk_type) {
                case 'IHDR':
                    $width = unpack('N', substr($chunk_data, 0, 4))[1];
                    $height = unpack('N', substr($chunk_data, 4, 4))[1];
                    $bit_depth = ord($chunk_data[8]);
                    $color_type = ord($chunk_data[9]);
                    break;
                case 'IDAT':
                    $idat .= $chunk_data;
                    break;
                case 'IEND':
                    break 2;
            }

            $pos += 12 + $chunk_len;
        }

        if (!$width || !$height || !$idat) return false;

        $has_alpha = ($color_type === 4 || $color_type === 6);

        tfcap_log("embedImageNoResize: PNG {$width}x{$height}, color_type={$color_type}, has_alpha=" . ($has_alpha ? 'yes' : 'no'));

        // No alpha — pass IDAT straight through
        if (!$has_alpha) {
            $colors = ($color_type === 0) ? 1 : 3;
            $colorspace = ($color_type === 0) ? '/DeviceGray' : '/DeviceRGB';

            $this->images[] = [
                'data' => $idat,
                'w' => $width,
                'h' => $height,
                'filter' => '/FlateDecode',
                'colorspace' => $colorspace,
                'bit_depth' => $bit_depth,
                'decode_parms' => "<< /Predictor 15 /Colors {$colors} /BitsPerComponent {$bit_depth} /Columns {$width} >>",
            ];
            $img_idx = count($this->images);
            $this->raw("q {$width} 0 0 {$height} {$x} {$y} cm /I{$img_idx} Do Q\n");
            return true;
        }

        // Has alpha — flatten onto white at native resolution (no resize)
        $channels_in = ($color_type === 6) ? 4 : 2;
        $channels_out = ($color_type === 6) ? 3 : 1;
        $colorspace = ($color_type === 6) ? '/DeviceRGB' : '/DeviceGray';

        $filtered = @gzuncompress($idat);
        if ($filtered === false) { tfcap_log("embedImageNoResize: gzuncompress failed"); return false; }

        $row_stride = 1 + $width * $channels_in;
        $prev_raw = str_repeat("\x00", $width * $channels_in);
        $raw_out = '';

        for ($y_row = 0; $y_row < $height; $y_row++) {
            $offset = $y_row * $row_stride;
            $filter_byte = ord($filtered[$offset]);
            $row_bytes = substr($filtered, $offset + 1, $width * $channels_in);

            $row_raw = tfcap_png_reverse_filter($filter_byte, $row_bytes, $prev_raw, $channels_in);
            $prev_raw = $row_raw;

            $out_row = "\x00";
            for ($x_col = 0; $x_col < $width; $x_col++) {
                $px = $x_col * $channels_in;

                if ($color_type === 6) {
                    $r = ord($row_raw[$px]);
                    $g = ord($row_raw[$px + 1]);
                    $b = ord($row_raw[$px + 2]);
                    $a = ord($row_raw[$px + 3]);
                    // Alpha blend onto white
                    $out_row .= chr((int)(($r * $a + 255 * (255 - $a)) / 255));
                    $out_row .= chr((int)(($g * $a + 255 * (255 - $a)) / 255));
                    $out_row .= chr((int)(($b * $a + 255 * (255 - $a)) / 255));
                } else {
                    $gray = ord($row_raw[$px]);
                    $a = ord($row_raw[$px + 1]);
                    $out_row .= chr((int)(($gray * $a + 255 * (255 - $a)) / 255));
                }
            }
            $raw_out .= $out_row;
        }

        $compressed_out = @gzcompress($raw_out, 6);
        if (!$compressed_out) { tfcap_log("embedImageNoResize: gzcompress failed"); return false; }

        tfcap_log("embedImageNoResize: flattened RGB=" . strlen($raw_out) . " bytes, native {$width}x{$height}");

        $this->images[] = [
            'data' => $compressed_out,
            'w' => $width,
            'h' => $height,
            'filter' => '/FlateDecode',
            'colorspace' => $colorspace,
            'bit_depth' => $bit_depth,
            'decode_parms' => "<< /Predictor 15 /Colors {$channels_out} /BitsPerComponent {$bit_depth} /Columns {$width} >>",
        ];
        $img_idx = count($this->images);
        $this->raw("q {$width} 0 0 {$height} {$x} {$y} cm /I{$img_idx} Do Q\n");
        return true;
    }

    function checkbox($label, $checked) {
        $this->checkPage(14);
        $y = $this->getY();
        $icon = $checked ? chr(0xE2) . chr(0x98) . chr(0x93) : chr(0xE2) . chr(0x98) . chr(0x90);
        $color = $checked ? [76, 175, 80] : [153, 153, 153];
        $this->text(self::MARGIN, $y, ($checked ? '[X]' : '[ ]') . ' ' . $label, 10, $color, $checked ? 'B' : '');
        $this->setY($y - 14);
    }

    function build() {
        $pdf = "%PDF-1.4\n";
        $offsets = [];

        $offsets[1] = strlen($pdf);
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        $offsets[2] = strlen($pdf);
        $kids_arr = array_map(function($i) { return ($i + 3) . ' 0 R'; }, range(0, count($this->pages) - 1));
        $kids = implode(' ', $kids_arr);
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [{$kids}] /Count " . count($this->pages) . " >>\nendobj\n";

        $font_obj = count($this->pages) + 3;
        $offsets[$font_obj] = strlen($pdf);
        $pdf .= "{$font_obj} 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";

        // Track image objects
        $img_start_obj = $font_obj + 1;
        $next_obj = $img_start_obj;

        // Build image XObjects first (need dimensions for page resources)
        $img_objs = [];
        foreach ($this->images as $idx => $img_info) {
            $img_w = $img_info['w'];
            $img_h = $img_info['h'];
            $img_data = $img_info['data'];
            $filter = $img_info['filter'];
            $colorspace = $img_info['colorspace'];
            $bit_depth = $img_info['bit_depth'];
            $decode_parms = isset($img_info['decode_parms']) ? $img_info['decode_parms'] : null;

            // For direct PNG: data is already compressed IDAT (FlateDecode)
            // For JPEG: data is already compressed JPEG (DCTDecode)
            // No additional compression needed
            $stream_len = strlen($img_data);

            $offsets[$next_obj] = strlen($pdf);
            $pdf .= "{$next_obj} 0 obj\n";
            $pdf .= "<< /Type /XObject /Subtype /Image /Width {$img_w} /Height {$img_h} /ColorSpace {$colorspace} /BitsPerComponent {$bit_depth} /Filter {$filter} /Length {$stream_len}";
            if ($decode_parms) {
                $pdf .= " /DecodeParms {$decode_parms}";
            }
            $pdf .= " >>\n";
            $pdf .= "stream\n{$img_data}\nendstream\n";
            $pdf .= "endobj\n";
            tfcap_log("build: image I{$idx} = obj {$next_obj}, {$img_w}x{$img_h}, filter={$filter}, size=" . strlen($img_data));
            $img_objs[$idx] = $next_obj;
            $next_obj++;
        }

        // Page objects
        foreach ($this->pages as $i => $page) {
            $obj_id = $i + 3;
            $offsets[$obj_id] = strlen($pdf);

            $content = $page['content'];
            $compressed = gzcompress($content);
            $stream_length = strlen($compressed);

            $pdf .= "{$obj_id} 0 obj\n";
            $pdf .= "<< /Type /Page /Parent 2 0 R ";
            $pdf .= "/MediaBox [0 0 " . self::PAGE_W . " " . self::PAGE_H . "] ";
            $pdf .= "/Contents " . $next_obj . " 0 R ";
            $pdf .= "/Resources << /Font << /F1 {$font_obj} 0 R >> ";
            if (!empty($img_objs)) {
                $xo = [];
                $img_num = 1;
                foreach ($img_objs as $idx => $oid) {
                    $xo[] = "/I{$img_num} {$oid} 0 R";
                    $img_num++;
                }
                $pdf .= "/XObject << " . implode(' ', $xo) . " >> ";
            }
            $pdf .= ">> >>\nendobj\n";

            $offsets[$next_obj] = strlen($pdf);
            $pdf .= "{$next_obj} 0 obj\n";
            $pdf .= "<< /Length {$stream_length} /Filter /FlateDecode >>\n";
            $pdf .= "stream\n{$compressed}\nendstream\n";
            $pdf .= "endobj\n";
            $next_obj++;
        }

        $xref_offset = strlen($pdf);
        $total = $next_obj - 1;
        $pdf .= "xref\n0 " . ($total + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $total; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        $pdf .= "trailer\n<< /Size " . ($total + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xref_offset}\n";
        $pdf .= "%%EOF";

        return $pdf;
    }
}

// ============================================================
// STORE PDF PATH FOR EMAIL ATTACHMENT
// ============================================================
$TFCAP_LAST_PDF = null;

// ============================================================
// LOG FILE (always writes, no WP_DEBUG needed)
// ============================================================
function tfcap_log($msg) {
    @file_put_contents(TFCAP_PDF_DIR . 'debug.log', date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
}

// ============================================================
// RADIO LABEL MAPPER — converts raw NF3 radio values to labels
// ============================================================
function tfcap_radio_label($raw) {
    $map = [
        'sole_trader'              => 'Sole Trader',
        'partnership'              => 'Partnership',
        'pty_ltd_company'          => 'Pty Ltd Company',
        'public_company'           => 'Public Company',
        'individual'               => 'Individual',
        'other'                    => 'Other',
        'company'                  => 'Company',
        'trust'                    => 'Trust',
        // Registered Business Name options
        'yes'                      => 'Yes',
        'no'                       => 'No',
        'same_as_applicant'        => 'Same as Applicant Name',
        'registered_business_name' => 'Registered Business Name',
    ];
    return isset($map[$raw]) ? $map[$raw] : $raw;
}

// ============================================================
// PDF GENERATION — ninja_forms_submit_data (FILTER)
// Fires BEFORE processing, so PDF exists when email action runs.
// ============================================================
add_filter('ninja_forms_submit_data', 'tfcap_generate_pdf', 10, 1);

function tfcap_generate_pdf($form_data) {
    global $TFCAP_LAST_PDF;

    tfcap_log('HOOK FIRED: ninja_forms_submit_data');

    try {
        if (tfcap_get_option('enabled', 'yes') !== 'yes') {
            tfcap_log('PDF disabled in settings');
            return $form_data;
        }

        $form_id = isset($form_data['id']) ? intval($form_data['id']) : 0;
        $saved_form_id = intval(tfcap_get_option('form_id', 0));
        tfcap_log("form_id={$form_id}, saved_form_id={$saved_form_id}");

        if ($saved_form_id !== 0 && $form_id !== $saved_form_id) {
            tfcap_log('Form ID mismatch — skipping');
            return $form_data;
        }

        if (!file_exists(TFCAP_PDF_DIR)) {
            wp_mkdir_p(TFCAP_PDF_DIR);
            @file_put_contents(TFCAP_PDF_DIR . '.htaccess', 'Deny from all');
        }

        $fields = isset($form_data['fields']) ? $form_data['fields'] : [];
        tfcap_log('Fields count: ' . count($fields));

        $company = tfcap_safe($fields, 2, 'Applicant');
        $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '-', $company);
        $filename = 'Credit-Application-' . $safe_name . '-' . date('Y-m-d-His') . '.pdf';
        $filepath = TFCAP_PDF_DIR . $filename;

        $pdf = new TFCAP_PDF();
        $y = TFCAP_PDF::PAGE_H - TFCAP_PDF::MARGIN;

        // HEADER
        $pdf->text(TFCAP_PDF::MARGIN, $y, 'CREDIT ACCOUNT APPLICATION', 15, [26, 26, 26], 'B');
        $pdf->text(TFCAP_PDF::MARGIN, $y - 14, 'KGO Enterprises Pty Ltd T/A The Fun Depot', 9, [102, 102, 102]);
        $right_x = TFCAP_PDF::PAGE_W - TFCAP_PDF::MARGIN - 190;
        $pdf->text($right_x, $y, 'ABN 63 667 911 944', 8, [102, 102, 102]);
        $pdf->text($right_x, $y - 10, '30 Emerald Road, Maddington WA 6109', 8, [102, 102, 102]);
        $pdf->text($right_x, $y - 20, '0406 161 959 | bookings@thefundepot.com.au', 8, [102, 102, 102]);
        $pdf->line(TFCAP_PDF::MARGIN, $y - 26, TFCAP_PDF::PAGE_W - TFCAP_PDF::MARGIN, $y - 26, 1, [200, 200, 200]);
        $pdf->setY($y - 36);
        $pdf->note('This application allows approved customers to pay after their event, within 30 days of the invoice date, instead of paying in full before the event. Please complete every section, sign where indicated, and return by email to bookings@thefundepot.com.au. Incomplete applications cannot be processed.');

        // 1. APPLICANT DETAILS
        $pdf->sectionHeader('1. Applicant Details');
        $pdf->fieldRow("Applicant's Full Name / Company Name:", tfcap_by_key($fields, 'textbox_2'));
        $pdf->twoColField('A.C.N. (if a company):', tfcap_by_key($fields, 'textbox_3'), 'A.B.N.:', tfcap_by_key($fields, 'textbox_4'));
        $pdf->fieldRow('Applicant is a:', tfcap_radio_label(tfcap_by_key($fields, 'listradio_5')));
        $pdf->fieldRow('If "Other", please give details:', tfcap_by_key($fields, 'textbox_6'));
        $pdf->fieldRow('Trading Name (only if different from above):', tfcap_by_key($fields, 'textbox_7'));
        $pdf->fieldRow('Is the trading name a registered business name?:', tfcap_radio_label(tfcap_by_key($fields, 'listradio_8')));

        // 2. ACCOUNTS CONTACT
        $pdf->sectionHeader('2. Accounts Contact Details');
        $pdf->twoColField('Contact Name (Mr/Mrs/Ms):', tfcap_by_key($fields, 'textbox_10'), 'Position Held:', tfcap_by_key($fields, 'textbox_11'));
        $pdf->twoColField('Accounts Email (for invoices/statements):', tfcap_by_key($fields, 'email_12'), 'Direct Phone:', tfcap_by_key($fields, 'phone_13'));
        $pdf->twoColField('Postal Address:', tfcap_by_key($fields, 'textbox_14'), 'Postcode:', tfcap_by_key($fields, 'textbox_15'));

        // 3. BUSINESS ADDRESS
        $pdf->sectionHeader('3. Business Address');
        $pdf->twoColField('Registered / Business Street Address:', tfcap_by_key($fields, 'textbox_17'), 'Postcode:', tfcap_by_key($fields, 'textbox_18'));
        $pdf->twoColField('Business Landline:', tfcap_by_key($fields, 'phone_19'), 'Mobile:', tfcap_by_key($fields, 'phone_20'));

        // 4. DIRECTORS
        $pdf->sectionHeader("4. Directors' Private Addresses");
        $pdf->note('If the applicant is a company, please provide details for each director. This information supports the Director\'s Guarantee in Section 9.');
        $pdf->subHeader('Director 1');
        $pdf->twoColField('Full Name:', tfcap_by_key($fields, 'textbox_24'), 'Phone / Mobile:', tfcap_by_key($fields, 'phone_25'));
        $pdf->twoColField('Address:', tfcap_by_key($fields, 'textbox_26'), 'Postcode:', tfcap_by_key($fields, 'textbox_27'));
        $pdf->subHeader('Director 2 (if applicable)');
        $pdf->twoColField('Full Name:', tfcap_by_key($fields, 'textbox_29'), 'Phone / Mobile:', tfcap_by_key($fields, 'phone_30'));
        $pdf->twoColField('Address:', tfcap_by_key($fields, 'textbox_31'), 'Postcode:', tfcap_by_key($fields, 'textbox_32'));

        // 5. BANKING
        $pdf->sectionHeader('5. Banking Details');
        $pdf->twoColField('Name of Bank / Financial Institution:', tfcap_by_key($fields, 'textbox_34'), 'Branch:', tfcap_by_key($fields, 'textbox_35'));
        $pdf->note('I/We hereby authorise The Fun Depot (KGO Enterprises Pty Ltd) to make oral or written inquiries with the bank or financial institution named above to obtain information in support of this application. Information will be handled in accordance with the Privacy Act 1988 (Cth).');

        // 6. TRADE REFERENCES
        $pdf->sectionHeader('6. Trade References');
        $pdf->note('Please provide three (3) current trade references.');
        $pdf->twoColField('Reference 1 — Company Name:', tfcap_by_key($fields, 'textbox_39'), 'Phone Number:', tfcap_by_key($fields, 'phone_40'));
        $pdf->twoColField('Reference 2 — Company Name:', tfcap_by_key($fields, 'textbox_41'), 'Phone Number:', tfcap_by_key($fields, 'phone_42'));
        $pdf->twoColField('Reference 3 — Company Name:', tfcap_by_key($fields, 'textbox_43'), 'Phone Number:', tfcap_by_key($fields, 'phone_44'));

        // 7. TERMS
        $pdf->sectionHeader('7. Terms of Application');
        $terms = [
            'I/We declare that the information provided in this application is true and correct to the best of my/our knowledge.',
            'I/We agree to notify The Fun Depot immediately, in writing, of any change to the information provided or to the circumstances outlined in this application, including any change in directors, shareholders, partnership or trusteeship.',
            "I/We agree to be bound by these terms and by The Fun Depot's Terms and Conditions of Hire (available at perthbouncycastlehire.com.au/terms), which have been read and understood, including the payment terms at clause 5 and overdue account terms at clause 7.",
            "I/We acknowledge that approval to pay on credit terms is granted at The Fun Depot's sole discretion and may be varied or withdrawn at any time, without liability.",
            'I/We acknowledge that The Fun Depot may disclose application details and details of overdue accounts to a credit reporting body, in accordance with the Privacy Act 1988 (Cth).',
            'I/We acknowledge that approved credit terms apply only to invoices issued after written approval is received, and that our standard deposit and pre-event payment terms continue to apply until that approval is confirmed in writing.',
            'I certify that I am duly authorised to sign this application on behalf of the applicant.',
        ];
        foreach ($terms as $i => $t) {
            $term_text = ($i + 1) . '. ' . $t;
            $pdf->checkPage(24);
            $y = $pdf->getY();
            $lines = $pdf->wrapText($term_text, TFCAP_PDF::PAGE_W - 2 * TFCAP_PDF::MARGIN - 10, 9);
            foreach ($lines as $li => $l) {
                $pdf->text(TFCAP_PDF::MARGIN + 10, $y - ($li * 11), $l, 9, [68, 68, 68]);
            }
            $pdf->setY($y - (count($lines) * 11));
        }
        $terms_checked = (tfcap_by_key($fields, 'checkbox_47') === '1');
        $pdf->checkbox('I/We have read, understood and agree to the above: I / We Agree', $terms_checked);

        // 8. ENDORSEMENT
        $pdf->sectionHeader('8. Endorsement');
        $pdf->fieldRow('Signed for and on behalf of (Company / Applicant Name):', tfcap_by_key($fields, 'textbox_49'));
        $pdf->twoColField('Full Name (Director / Company Secretary):', tfcap_by_key($fields, 'textbox_50'), 'Position:', tfcap_by_key($fields, 'textbox_51'));
        $sig52 = tfcap_by_key($fields, 'signature_52');
        $pdf->signatureBox('Endorsement Signature', $sig52);
        $pdf->fieldRow('Date:', tfcap_by_key($fields, 'date_53'));

        // 9. GUARANTEE
        $pdf->sectionHeader("9. Directors' Guarantee and Indemnity");
        $pdf->note("In consideration of The Fun Depot (KGO Enterprises Pty Ltd, ABN 63 667 911 944) agreeing to provide credit terms on the basis set out in this application and The Fun Depot's Terms and Conditions of Hire, we, the undersigned director(s), personally and unconditionally guarantee the due and punctual payment by the applicant of all money owing to The Fun Depot from time to time (\"the guaranteed amount\"). As a separate and additional obligation, we also agree to indemnify and keep indemnified The Fun Depot against all losses, costs, charges and expenses it may suffer or incur as a result of the applicant's failure or default in paying the guaranteed amount.");
        $pdf->note('This guarantee remains in effect for all credit extended to the applicant by The Fun Depot until revoked in writing and acknowledged by The Fun Depot.');

        // Guarantor 1
        $pdf->subHeader('Guarantor 1');
        $pdf->twoColField('Full Name:', tfcap_by_key($fields, 'textbox_57'), 'Relationship to Applicant (e.g. Director):', tfcap_by_key($fields, 'textbox_58'));
        $pdf->fieldRow('Residential Address:', tfcap_by_key($fields, 'textbox_59'));
        $sig60 = tfcap_by_key($fields, 'signature_60');
        $pdf->signatureBox('Guarantor Signature', $sig60);
        $pdf->fieldRow('Date:', tfcap_by_key($fields, 'date_61'));

        // Guarantor 2
        $pdf->subHeader('Guarantor 2 (if applicable)');
        $pdf->twoColField('Full Name:', tfcap_by_key($fields, 'textbox_63'), 'Relationship to Applicant (e.g. Director):', tfcap_by_key($fields, 'textbox_64'));
        $pdf->fieldRow('Residential Address:', tfcap_by_key($fields, 'textbox_65'));
        $sig66 = tfcap_by_key($fields, 'signature_66');
        $pdf->signatureBox('Guarantor 2 Signature', $sig66);
        $pdf->fieldRow('Date:', tfcap_by_key($fields, 'date_67'));

        $guarantee_checked = (tfcap_by_key($fields, 'checkbox_68') === '1');
        $pdf->checkbox("I/We have read, understood and agree to the Directors' Guarantee", $guarantee_checked);

        // FOOTER
        $pdf->checkPage(60);
        // Draw divider line
        $pdf->line(TFCAP_PDF::MARGIN, $pdf->getY() - 4, TFCAP_PDF::PAGE_W - TFCAP_PDF::MARGIN, $pdf->getY() - 4, 0.5, [200, 200, 200]);
        // Move below the line, then render note and footer text
        $pdf->setY($pdf->getY() - 18);
        $pdf->note('Please email the completed and signed application to bookings@thefundepot.com.au. If any section does not apply, please write "N/A" rather than leaving it blank. We will confirm your approved credit terms in writing before they take effect.');
        $y_footer = $pdf->getY();
        $pdf->text(TFCAP_PDF::MARGIN, $y_footer - 4, 'The Fun Depot(TM) | ABN 63 667 911 944 | perthbouncycastlehire.com.au', 8, [102, 102, 102]);
        $pdf->text(TFCAP_PDF::MARGIN, $y_footer - 14, 'Generated ' . date('d/m/Y \\a\\t g:i A') . ' | Credit Application - ' . $company, 8, [102, 102, 102]);

        // Save
        $result = file_put_contents($filepath, $pdf->build());
        tfcap_log("PDF saved: {$filepath} ({$result} bytes)");

        // Store for email attachment
        $TFCAP_LAST_PDF = $filepath;
        tfcap_log("Global TFCAP_LAST_PDF set");

    } catch (\Throwable $e) {
        tfcap_log('ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    }

    // Must return $form_data (this is a filter, not an action)
    return $form_data;
}

// ============================================================
// ATTACH PDF TO EMAIL — ninja_forms_action_email_attachments
// NF3 filter: ($attachments, $form_data, $action_settings)
// ============================================================
add_filter('ninja_forms_action_email_attachments', 'tfcap_attach_pdf', 10, 3);

function tfcap_attach_pdf($attachments, $form_data, $action_settings) {
    global $TFCAP_LAST_PDF;

    tfcap_log('HOOK FIRED: ninja_forms_action_email_attachments');

    try {
        if (tfcap_get_option('enabled', 'yes') !== 'yes') {
            tfcap_log('PDF disabled — skipping attachment');
            return $attachments;
        }

        // Attachment filter uses 'form_id', not 'id'
        $form_id = isset($form_data['form_id']) ? intval($form_data['form_id']) : (isset($form_data['id']) ? intval($form_data['id']) : 0);
        $saved_form_id = intval(tfcap_get_option('form_id', 0));
        tfcap_log("Attach check: form_id={$form_id}, saved={$saved_form_id}");

        if ($saved_form_id !== 0 && $form_id !== $saved_form_id) {
            tfcap_log('Form ID mismatch — no attachment');
            return $attachments;
        }

        // Check attachment mode
        $mode = tfcap_get_option('attachment_mode', 'all');
        $admin_email = strtolower(trim(tfcap_get_option('admin_email', '')));
        $recipient = '';

        // Get recipient email from action settings
        if (is_array($action_settings)) {
            if (isset($action_settings['email'])) {
                $recipient = strtolower(trim($action_settings['email']));
            } elseif (isset($action_settings['to'])) {
                $recipient = strtolower(trim($action_settings['to']));
            }
        }
        tfcap_log("Mode={$mode}, admin_email={$admin_email}, recipient={$recipient}");

        // Decide whether to attach based on mode
        $should_attach = true;
        if ($mode === 'disabled') {
            $should_attach = false;
            tfcap_log('Attachment mode is disabled');
        } elseif ($mode === 'admin' && $admin_email && $recipient) {
            $should_attach = ($recipient === $admin_email);
            tfcap_log($should_attach ? 'Recipient matches admin — attaching' : 'Recipient is NOT admin — skipping');
        } elseif ($mode === 'customer' && $admin_email && $recipient) {
            $should_attach = ($recipient !== $admin_email);
            tfcap_log($should_attach ? 'Recipient is customer — attaching' : 'Recipient matches admin — skipping');
        }

        if (!$should_attach) {
            return $attachments;
        }

        // Try global first (same request)
        if ($TFCAP_LAST_PDF && file_exists($TFCAP_LAST_PDF)) {
            tfcap_log('Attaching PDF: ' . $TFCAP_LAST_PDF);
            $attachments[] = $TFCAP_LAST_PDF;
            return $attachments;
        }

        tfcap_log('Global PDF path empty or file missing');

        // Fallback: find most recent PDF
        $files = glob(TFCAP_PDF_DIR . 'Credit-Application-*.pdf');
        if (!empty($files)) {
            usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
            tfcap_log('Fallback: attaching most recent PDF: ' . $files[0]);
            $attachments[] = $files[0];
        } else {
            tfcap_log('No PDFs found in ' . TFCAP_PDF_DIR);
        }
    } catch (\Throwable $e) {
        tfcap_log('Attach error: ' . $e->getMessage());
    }

    return $attachments;
}

// ============================================================
// CREATE DIRECTORY ON ACTIVATION
// ============================================================
register_activation_hook(__FILE__, function() {
    if (!file_exists(TFCAP_PDF_DIR)) {
        wp_mkdir_p(TFCAP_PDF_DIR);
        @file_put_contents(TFCAP_PDF_DIR . '.htaccess', 'Deny from all');
    }
});

// ============================================================
// SETTINGS PAGE
// ============================================================
add_action('admin_menu', function() {
    add_options_page(
        'Credit Application PDF',
        'Credit App PDF',
        'manage_options',
        'tfcap-settings',
        'tfcap_render_settings_page'
    );
});

add_action('admin_init', function() {
    register_setting('tfcap_settings', 'tfcap_form_id', ['type' => 'integer', 'default' => 0]);
    register_setting('tfcap_settings', 'tfcap_logo_url', ['type' => 'string', 'default' => '']);
    register_setting('tfcap_settings', 'tfcap_enabled', ['type' => 'string', 'default' => 'yes']);
    register_setting('tfcap_settings', 'tfcap_attachment_mode', ['type' => 'string', 'default' => 'all']);
    register_setting('tfcap_settings', 'tfcap_admin_email', ['type' => 'string', 'default' => '']);
    register_setting('tfcap_settings', 'tfcap_from_email', ['type' => 'string', 'default' => '']);
});

function tfcap_render_settings_page() {
    if (!current_user_can('manage_options')) return;

    // Get available Ninja Forms (direct DB query for max compatibility)
    $forms = [];
    global $wpdb;

    // Find the NF forms table (naming varies by version)
    $nf_table = null;
    foreach ([$wpdb->prefix . 'nf3_forms', $wpdb->prefix . 'ninja_forms_forms'] as $t) {
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t)) === $t) {
            $nf_table = $t;
            break;
        }
    }
    if (!$nf_table) {
        foreach ($wpdb->get_col("SHOW TABLES") as $t) {
            if (stripos($t, 'nf') !== false && stripos($t, 'form') !== false) {
                $nf_table = $t;
                break;
            }
        }
    }

    if ($nf_table) {
        $results = $wpdb->get_results("SELECT id, title FROM {$nf_table} WHERE deleted = 0 ORDER BY id ASC");
        if ($wpdb->last_error) {
            $results = $wpdb->get_results("SELECT id, title FROM {$nf_table} ORDER BY id ASC");
        }
        if ($results) {
            foreach ($results as $row) {
                $forms[$row->id] = $row->title;
            }
        }
    }
    ?>
    <div class="wrap">
        <h1>Credit Application PDF Settings</h1>
        <p>Configure how the credit application PDF is generated and attached to emails.</p>

        <?php
        $current_version = tfcap_get_version();
        $remote = tfcap_check_github_update();
        $has_update = $remote && version_compare($remote['version'], $current_version, '>');
        $latest_version = $remote ? $remote['version'] : '';
        $release_url = $remote ? $remote['url'] : '';
        $release_notes = $remote ? wp_strip_all_tags(substr($remote['notes'], 0, 300)) : '';
        ?>
        <style>
            .tfcap-version-bar{display:flex;align-items:center;gap:16px;padding:14px 18px;border-radius:8px;margin:16px 0 20px;font-size:14px;line-height:1.5}
            .tfcap-version-bar.up-to-date{background:#e8f5e9;border:1px solid #a5d6a7;color:#2e7d32}
            .tfcap-version-bar.update-available{background:#fff3e0;border:1px solid #ffcc80;color:#e65100}
            .tfcap-version-bar.checking{background:#e3f2fd;border:1px solid #90caf9;color:#1565c0}
            .tfcap-version-bar.error{background:#fce4ec;border:1px solid #ef9a9a;color:#c62828}
            .tfcap-version-badge{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:4px;font-weight:600;font-size:13px;white-space:nowrap}
            .tfcap-version-badge.current{background:rgba(0,0,0,.08)}
            .tfcap-version-badge.latest{background:#ff9800;color:#fff}
            .tfcap-version-btn{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:5px;border:none;cursor:pointer;font-size:13px;font-weight:600;transition:all .15s}
            .tfcap-version-btn.primary{background:#1976d2;color:#fff}.tfcap-version-btn.primary:hover{background:#1565c0}
            .tfcap-version-btn.secondary{background:rgba(0,0,0,.06);color:#333}.tfcap-version-btn.secondary:hover{background:rgba(0,0,0,.1)}
            .tfcap-version-spinner{display:inline-block;width:16px;height:16px;border:2px solid rgba(0,0,0,.15);border-top-color:#1976d2;border-radius:50%;animation:tfcap-spin .6s linear infinite}
            @keyframes tfcap-spin{to{transform:rotate(360deg)}}
            .tfcap-version-notes{margin-top:8px;font-size:12px;opacity:.8;max-height:60px;overflow:hidden}
        </style>
        <div id="tfcap-version-bar" class="tfcap-version-bar <?php echo $has_update ? 'update-available' : 'up-to-date'; ?>">
            <div style="flex:1">
                <strong>Plugin Version</strong>
                <span class="tfcap-version-badge current">v<?php echo esc_html($current_version); ?></span>
                <?php if ($has_update) : ?>
                    <span style="margin:0 4px">&rarr;</span>
                    <span class="tfcap-version-badge latest">v<?php echo esc_html($latest_version); ?> available</span>
                    <?php if ($release_notes) : ?>
                        <div class="tfcap-version-notes"><?php echo esc_html($release_notes); ?></div>
                    <?php endif; ?>
                <?php else : ?>
                    <span style="margin-left:8px;opacity:.7">&#10003; Up to date</span>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($has_update) : ?>
                    <button type="button" id="tfcap-update-btn" class="tfcap-version-btn primary" onclick="tfcapRunUpdate('<?php echo esc_attr($latest_version); ?>')">
                        Update Now
                    </button>
                <?php endif; ?>
                <button type="button" id="tfcap-check-btn" class="tfcap-version-btn secondary" onclick="tfcapCheckVersion()">
                    <span id="tfcap-check-icon">&#8635;</span> Check for Updates
                </button>
            </div>
        </div>
        <script>
        function tfcapCheckVersion(){
            var btn=document.getElementById('tfcap-check-btn'),
                icon=document.getElementById('tfcap-check-icon'),
                bar=document.getElementById('tfcap-version-bar');
            btn.disabled=true;icon.innerHTML='<span class="tfcap-version-spinner"></span>';
            bar.className='tfcap-version-bar checking';
            bar.querySelector('div:first-child').innerHTML='<strong>Checking for updates...</strong>';
            jQuery.post(ajaxurl,{action:'tfcap_check_version',nonce:'<?php echo wp_create_nonce('tfcap_version_check'); ?>'},function(r){
                btn.disabled=false;icon.innerHTML='&#8635;';
                if(!r||!r.data){bar.className='tfcap-version-bar error';bar.querySelector('div:first-child').innerHTML='<strong>Error</strong> Could not check for updates.';return;}
                var d=r.data;
                if(d.status==='up_to_date'){bar.className='tfcap-version-bar up-to-date';bar.querySelector('div:first-child').innerHTML='<strong>Plugin Version</strong> <span class="tfcap-version-badge current">v'+d.current+'</span> <span style="margin-left:8px;opacity:.7">&#10003; Up to date</span>';}
                else if(d.status==='update_available'){bar.className='tfcap-version-bar update-available';bar.querySelector('div:first-child').innerHTML='<strong>Plugin Version</strong> <span class="tfcap-version-badge current">v'+d.current+'</span> <span style="margin:0 4px">&rarr;</span> <span class="tfcap-version-badge latest">v'+d.latest+' available</span>'+(d.release_notes?'<div class="tfcap-version-notes">'+d.release_notes+'</div>':'')+'<div style="margin-top:8px"><button type="button" class="tfcap-version-btn primary" onclick="tfcapRunUpdate(\''+d.latest+'\')">Update Now</button></div>';}
                else{bar.className='tfcap-version-bar error';bar.querySelector('div:first-child').innerHTML='<strong>Error</strong> '+(d.message||'Could not check for updates.');}
            }).fail(function(){btn.disabled=false;icon.innerHTML='&#8635;';bar.className='tfcap-version-bar error';bar.querySelector('div:first-child').innerHTML='<strong>Error</strong> Request failed.';});
        }
        function tfcapRunUpdate(version){
            if(!confirm('Update plugin to v'+version+'? The page will reload when done.'))return;
            var bar=document.getElementById('tfcap-version-bar'),
                btn=document.getElementById('tfcap-update-btn');
            btn.disabled=true;btn.innerHTML='<span class="tfcap-version-spinner"></span> Updating...';
            bar.className='tfcap-version-bar checking';
            bar.querySelector('div:first-child').innerHTML='<strong>Downloading and installing v'+version+'...</strong>';
            jQuery.post(ajaxurl,{action:'tfcap_run_update',version:version,nonce:'<?php echo wp_create_nonce('tfcap_run_update'); ?>'},function(r){
                if(r&&r.data&&r.data.status==='success'){
                    bar.className='tfcap-version-bar up-to-date';
                    bar.querySelector('div:first-child').innerHTML='<strong>Updated to v'+version+'!</strong> Reloading...';
                    setTimeout(function(){location.reload();},1500);
                }else{
                    bar.className='tfcap-version-bar error';
                    bar.querySelector('div:first-child').innerHTML='<strong>Update failed</strong> '+(r&&r.data&&r.data.message||'Unknown error. Try downloading from GitHub manually.');
                    btn.disabled=false;btn.innerHTML='Update Now';
                }
            }).fail(function(){
                bar.className='tfcap-version-bar error';
                bar.querySelector('div:first-child').innerHTML='<strong>Update failed</strong> Request error.';
                btn.disabled=false;btn.innerHTML='Update Now';
            });
        }
        </script>

        <form method="post" action="options.php">
            <?php settings_fields('tfcap_settings'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="tfcap_enabled">Enable PDF Generation</label></th>
                    <td>
                        <select name="tfcap_enabled" id="tfcap_enabled">
                            <option value="yes" <?php selected(tfcap_get_option('enabled', 'yes'), 'yes'); ?>>Enabled</option>
                            <option value="no" <?php selected(tfcap_get_option('enabled', 'yes'), 'no'); ?>>Disabled</option>
                        </select>
                        <p class="description">Turn PDF generation on or off without deactivating the plugin.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="tfcap_form_id">Ninja Forms Form</label></th>
                    <td>
                        <?php if (!empty($forms)) : ?>
                            <select name="tfcap_form_id" id="tfcap_form_id">
                                <option value="0" <?php selected(tfcap_get_option('form_id', 0), 0); ?>>— Select a form —</option>
                                <?php foreach ($forms as $id => $title) : ?>
                                    <option value="<?php echo esc_attr($id); ?>" <?php selected(tfcap_get_option('form_id', 0), $id); ?>>
                                        <?php echo esc_html($title); ?> (ID: <?php echo $id; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Select which Ninja Forms form is the credit application.</p>
                        <?php else : ?>
                            <p class="description" style="color: #cc0000;">
                                No Ninja Forms found.
                                <?php if ($nf_table) echo ' (table ' . esc_html($nf_table) . ' exists but has no rows)'; ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="tfcap_logo_url">Logo URL (optional)</label></th>
                    <td>
                        <input type="url" name="tfcap_logo_url" id="tfcap_logo_url"
                               value="<?php echo esc_attr(tfcap_get_option('logo_url', '')); ?>"
                               class="regular-text"
                               placeholder="https://yoursite.com/logo.png">
                        <p class="description">Full URL to your logo image. Leave blank to use default TFD branding.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="tfcap_attachment_mode">PDF Attachment</label></th>
                    <td>
                        <select name="tfcap_attachment_mode" id="tfcap_attachment_mode">
                            <option value="all" <?php selected(tfcap_get_option('attachment_mode', 'all'), 'all'); ?>>All email notifications</option>
                            <option value="admin" <?php selected(tfcap_get_option('attachment_mode', 'all'), 'admin'); ?>>Admin only</option>
                            <option value="customer" <?php selected(tfcap_get_option('attachment_mode', 'all'), 'customer'); ?>>Customer only</option>
                            <option value="disabled" <?php selected(tfcap_get_option('attachment_mode', 'all'), 'disabled'); ?>>Disabled (PDF saved to disk only)</option>
                        </select>
                        <p class="description">Which email notifications should receive the PDF attachment?</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="tfcap_admin_email">Admin Email Address</label></th>
                    <td>
                        <input type="email" name="tfcap_admin_email" id="tfcap_admin_email"
                               value="<?php echo esc_attr(tfcap_get_option('admin_email', '')); ?>"
                               class="regular-text"
                               placeholder="bookings@thefundepot.com.au">
                        <p class="description">Your business email. Used to identify which notification is the admin email when "Admin only" or "Customer only" is selected above. Leave blank to treat all notifications the same.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="tfcap_from_email">From Email Address</label></th>
                    <td>
                        <input type="email" name="tfcap_from_email" id="tfcap_from_email"
                               value="<?php echo esc_attr(tfcap_get_option('from_email', '')); ?>"
                               class="regular-text"
                               placeholder="bookings@thefundepot.com.au">
                        <p class="description">The "From" address on notification emails. Must use your site domain (e.g. <code>bookings@gogodigital.com.au</code>) — not Gmail. Fixes email delivery failures caused by SPF checks. Leave blank to use WordPress default.</p>
                    </td>
                </tr>
            </table>

            <?php submit_button('Save Settings'); ?>
        </form>

        <hr>

        <h2>Form Template</h2>
        <p>Download the pre-built Ninja Forms template (60 fields, 2 signature pads, email action). Import it into Ninja Forms → Import/Export → Import.</p>
        <p><a href="<?php echo admin_url('options-general.php?page=tfcap-settings&tfcap_download_nff=1'); ?>" class="button button-secondary">Download .nff Template</a></p>

        <hr>

        <h2>How It Works</h2>
        <ol>
            <li>Select your credit application form above</li>
            <li>When someone submits the form, a branded A4 PDF is generated automatically</li>
            <li>The PDF is attached to the email notification sent to your configured address</li>
            <li>A copy is saved to <code>wp-content/uploads/tfcap-pdfs/</code></li>
            <li>Check your email inbox for the attachment — the PDF will be attached to the admin notification</li>
        </ol>

        <h2>Field Mapping</h2>
        <p>These Ninja Forms field IDs are used (auto-mapped from the .nff import):</p>
        <table class="widefat" style="max-width: 600px;">
            <thead><tr><th>Section</th><th>Field IDs</th></tr></thead>
            <tbody>
                <tr><td>Applicant Details</td><td>2, 3, 4, 5, 6, 7, 8</td></tr>
                <tr><td>Accounts Contact</td><td>10, 11, 12, 13, 14, 15</td></tr>
                <tr><td>Business Address</td><td>17, 18, 19, 20</td></tr>
                <tr><td>Directors</td><td>24–27, 29–32</td></tr>
                <tr><td>Banking</td><td>34, 35</td></tr>
                <tr><td>Trade References</td><td>39–44</td></tr>
                <tr><td>Terms</td><td>47</td></tr>
                <tr><td>Endorsement</td><td>49, 50, 51, 52 (sig)</td></tr>
                <tr><td>Guarantee</td><td>55, 56, 57, 58 (sig), 59</td></tr>
            </tbody>
        </table>

        <h2>Troubleshooting</h2>
        <p>Check <code>wp-content/uploads/tfcap-pdfs/</code> for generated PDFs. If no PDFs appear there:</p>
        <ul>
            <li>Make sure the plugin is enabled and a form is selected in settings above</li>
            <li>Check the debug log below for "HOOK FIRED" entries</li>
            <li>Verify the form ID matches your credit application form</li>
        </ul>

        <?php
        // Show debug log contents
        $log_file = TFCAP_PDF_DIR . 'debug.log';
        if (file_exists($log_file)) {
            $log_content = @file_get_contents($log_file);
            if ($log_content) {
                $lines = array_slice(explode("\n", trim($log_content)), -30); // last 30 lines
                echo '<h2>Debug Log (last 30 lines)</h2>';
                echo '<p class="description">Log file: <code>wp-content/uploads/tfcap-pdfs/debug.log</code></p>';
                echo '<pre style="background:#1e1e1e;color:#d4d4d4;padding:12px;max-height:300px;overflow:auto;font-size:12px;">';
                echo esc_html(implode("\n", $lines));
                echo '</pre>';
            }
        } else {
            echo '<p class="description"><em>No debug log yet — submit a form test to generate one.</em></p>';
        }
        ?>
    </div>
    <?php
}

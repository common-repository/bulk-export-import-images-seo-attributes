<?php
/*
Plugin Name: Bulk Export Import Images SEO Attributes 
Description: Export media image attributes and import them after update
Version:1.1
Author: Simple Intelligent Systems
Author URI: https://simpleintelligentsystems.com
Requires at least: 5.5
Tested up to: 6.6.1
Requires PHP: 7.1
License: GPL-3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.txt
Text Domain: bulk-export-import-images-seo-attributes
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}


$wp_version = get_bloginfo('version');
if ( $wp_version < 5.5 ) {
    add_action( 'admin_init', 'mediametaexpimp_deactivate_plugin_now' );
    add_action( 'admin_notices', 'mediametaexpimp_errormsg' );
}

function mediametaexpimp_deactivate_plugin_now() {
    $self=plugin_basename(__FILE__);
    if ( is_plugin_active($self) ) {
        deactivate_plugins($self); //deactivate self if WP version not met.
        unset($_GET['activate']);
    }
}

function mediametaexpimp_errormsg () {
    $class = 'notice notice-error';
    $message = __( 'Error you did not meet the WP minimum version', 'bulk-export-import-images-seo-attributes' );
    printf( '<div class="%1$s"><p>%2$s</p></div>', esc_html($class), esc_html($message) );
}


// print messages
add_action( 'admin_notices', 'mediametaexpimp_print_notices' );
function mediametaexpimp_print_notices() 
{              
    if ( ! empty( $_GET['mediametaexpimp_admin_notice'] ) ) // phpcs:ignore
    {
        $classnm='';
        if( $_GET['mediametaexpimp_notice_mode'] === "success") // phpcs:ignore
            $classnm='notice-success';
        else
            $classnm='notice-error';

        $html =  '<div class="notice '.esc_html($classnm).' is-dismissible">';
        $html .= '<p><strong>' . esc_html(sanitize_text_field($_GET['mediametaexpimp_admin_notice'])) . '</strong></p>';// phpcs:ignore
        $html .= '</div>';
        echo wp_kses_post($html);
    }
}

// Add a menu item for the plugin page
function mediametaexpimp_menu() {
    add_menu_page(
        'Bulk Export Import Images SEO Attributes',
        'Export Import Images SEO Attributes',
        'manage_options',
        'mediametaexpimp-page',
        'mediametaexpimp_page_content',
        'dashicons-admin-generic',
        20
    );
}
add_action('admin_menu', 'mediametaexpimp_menu');

//CSS enqueue
function mediametaexpimp_enqueue_admin_style() {
    
    wp_register_style( 'mediametaexpimp_admin_style', plugin_dir_url( __FILE__ ) . 'admin-style.css', false, '1.0.0' );
    wp_enqueue_style( 'mediametaexpimp_admin_style' );
    
}
add_action( 'admin_enqueue_scripts', 'mediametaexpimp_enqueue_admin_style' );


// Content of the plugin page
function mediametaexpimp_page_content() 
{
    if( current_user_can( 'edit_users' ) ) { //check capability
?>
    <div class="wrap mediametaexpimp">
        <h2>Bulk Export Import Images SEO Attributes</h2>
        
        <section id="section1" class="plugin-section">
            <div class="plugin">
                <h3>Export</h3>
                <p>Click the button below to download latest 50 images' meta data in CSV format.</p>
                <form id="downloadForm" method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="mediametaexpimp_generate_csv_file">
                    <?php wp_nonce_field('mediametaexpimp_exp_action', 'mediametaexpimp_exp_nonce'); ?>
                    <button type="submit" name="submit" id="downloadButton">Download CSV File</button>
                </form>
            </div>
        </section>

        <section id="section2" class="plugin-section">
            <div class="plugin">
                <h3>Import</h3>
                <p>Please read the instructions below.</p>
                <ul>
                    <li>Download the CSV file from the above section and update the required values. </li>
                    <li>Do not remove the Heading row.</li>
                    <li>Do not remove any columns from the CSV file. You should not make chnages to the ID and URL columns as they will be ignored.</li>
                    <li>Do not change any ID values as it will result in updating wrong image's meta data or leaving the image if no records are found against the modified ID value.</li>
                    <li>Keep only those records for which you  want to update the meta data.</li>
                    <li>Upload the CSV file once you are done with the updates. Click the button below to upload your file in CSV format.</li>
                </ul>

                <form id="uploadForm" method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" >
                    <input type="hidden" name="action" value="mediametaexpimp_import_csv_file">
                    <?php wp_nonce_field('mediametaexpimp_imp_action', 'mediametaexpimp_imp_nonce'); ?>
                    <input type="file" name="csv_file" id="csv_file">
                    <input type="submit" name="submit" value="Import CSV">
                </form>
            </div>
        </section>

        

    </div>
<?php
    } //end of checking capability
}

// Callback function 
function mediametaexpimp_imp_action() {
    
    if(isset($_POST['submit'])) 
    {
        // Verify nonce
        if ( !isset( $_POST['mediametaexpimp_imp_nonce'] ) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mediametaexpimp_imp_nonce'])), 'mediametaexpimp_imp_action' ) ) {
            die( 'Unauthorized request' );
        }
        if(isset($_FILES['csv_file'])) 
        {
            $file = $_FILES['csv_file'];
            $file_type = wp_check_filetype($file['name']);
            if($file['error'] == UPLOAD_ERR_OK && $file_type['type'] == 'text/csv') 
            {
                $csv_data = array_map('str_getcsv', file($file['tmp_name']));

                // Process $csv_data to update WordPress data
                foreach ($csv_data as $ind=>$row) 
                {
                    if($ind>0) //leave the header row
                    {
                        $post_id = (int) sanitize_text_field($row[0]);
                        // Change basic fields on attachment post
                        wp_update_post(array(
                           'ID'           => $post_id,
                           'post_title'   => sanitize_text_field($row[4]),
                           'post_content' => sanitize_text_field($row[2]),
                           'post_excerpt' => sanitize_text_field($row[3]),
                       ));
                        // Change ALT Text
                        update_post_meta($post_id, '_wp_attachment_image_alt', sanitize_text_field($row[5]));
                    }
                }
                // redirect the user to the appropriate page with message
                wp_redirect( esc_url_raw( add_query_arg( 
                    array(
                    'mediametaexpimp_admin_notice' => 'CSV file imported successfully.',
                    'mediametaexpimp_notice_mode' => 'success',
                    ),
                    admin_url('admin.php?page=mediametaexpimp-page') 
                    ) ) );
            } 
            else {
                // redirect the user to the appropriate page with message
                wp_redirect( esc_url_raw( add_query_arg( 
                    array(
                    'mediametaexpimp_admin_notice' => 'Invalid file format. Please upload a valid CSV file.',
                    'mediametaexpimp_notice_mode' => 'error',
                    ),
                    admin_url('admin.php?page=mediametaexpimp-page') 
                    ) ) );
            }
        }
    }
}
// Register the Import form submission action
add_action('admin_post_mediametaexpimp_import_csv_file', 'mediametaexpimp_imp_action');

// Callback function 
function mediametaexpimp_exp_action() {

    // Verify nonce
    if ( !isset( $_POST['mediametaexpimp_exp_nonce'] ) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mediametaexpimp_exp_nonce'])), 'mediametaexpimp_exp_action' ) ) {
        die( 'Unauthorized request' );
    }
    
    $args = array(
    'post_type' => 'attachment',
    'numberposts' => 50,
    'post_mime_type' => 'image',
    ); 

    $attachments = get_posts($args);
    if ($attachments) 
    {
        $props =array();
        $csvFileName = 'output.csv';
        $tempFile = tmpfile();
        // Set CSV file header
        fputcsv($tempFile, array('id','url','descr','caption','title','alt'));
        $i=0;
        foreach ($attachments as $post) 
        {
            $props[$i]['id'] = $post -> ID;
            $props[$i]['url']  = wp_get_attachment_image_url($post->ID, 'full'); //for direct path to image
            $props[$i]['descr'] = $post -> post_content;
            $props[$i]['caption'] = $post -> post_excerpt;
            $props[$i]['title'] = $post -> post_title;
            $alt_text = get_post_meta($post ->ID, '_wp_attachment_image_alt', true);
            $props[$i]['alt'] = $alt_text;
            fputcsv($tempFile, $props[$i]);
            $i++;
        }

        
        // force download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $csvFileName . '"');

        rewind($tempFile);

        // Output the temporary file's content
        fpassthru($tempFile);

        // Close the temporary file handle
        fclose($tempFile); // phpcs:ignore
    }
    exit;
}

// Register the export form submission action
add_action('admin_post_mediametaexpimp_generate_csv_file', 'mediametaexpimp_exp_action');

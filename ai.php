<?php
/*
Plugin Name: AI Content Generator
Description: Generates content using Google Gemini API.
Version: 1.1
Author: Kamrul
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action( 'woocommerce_single_product_summary', 'woocommerce_show_AI_image', 65 );
function woocommerce_show_AI_image () {
    ?>
        <div id="modern-ajax-form">
            <div id="result"></div>
            <form action="#" method="post">
                <input type="hidden" name="product_id" value="<?php echo esc_attr( get_the_ID() ); ?>">
                <label for="photoUpload" style="display:inline-block;padding:10px 20px;background:#007bff;color:white;border-radius:8px;cursor:pointer;" class="photoUpload-label">
                  Upload Image
                </label>
                <input name="photoUpload" id="photoUpload" type="file" accept="image/*" capture="environment" style="display:none;">
            </form>
        </div>
        <?php 
}

add_action( 'init', function(){

});


/**
 * Modern Dark WordPress AJAX Form with File Uploads
 * Add this code to your theme's functions.php or create a custom plugin
 */

// Enqueue scripts and styles
function modern_form_enqueue_scripts() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('#modern-ajax-form').on('change', 'input[type="file"]', function(e){
            $(this).parents('form').trigger('submit');
        });

        // Form submission
        $('#modern-ajax-form form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);

            var formData = new FormData(this);
            $('.photoUpload-label').html('generating...');

            formData.append('action', 'process_modern_form');
            formData.append('nonce', modern_form_ajax.nonce);

            
            $.ajax({
                url: modern_form_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $('.photoUpload-label').html('Generated')
                        $('#result').html('<img src="'+ response['data']['image_url'] +'" />');

                    }
                },
                // error: function() {
                //     $message.removeClass('success').addClass('error')
                //         .html('✗ An error occurred. Please try again.').show();
                // },
                // complete: function() {
                //     $submitBtn.prop('disabled', false).html('Submit');
                // }
            });
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'modern_form_enqueue_scripts');

// Localize script for AJAX
function modern_form_localize_script() {
    ?>
    <script>
        var modern_form_ajax = {
            ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('modern_form_nonce'); ?>'
        };
    </script>
    <?php
}
add_action('wp_footer', 'modern_form_localize_script', 5);

// AJAX handler for form submission
function handle_modern_form_submission() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'modern_form_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed.'));
    }
    
    // Get form data
    $prompt = "Create a professional, full-body e-commerce fashion photograph. The subject is a person/model who strongly resembles the individual in [REFERENCE: IMAGE OF THE PERSON/MODEL]. This person is wearing the exact specific piece of clothing seen in [REFERENCE: IMAGE OF THE CLOTHING ITEM] and make sure model face exact same. The garment must be tailored to fit naturally and realistically on the person's body, maintaining the style and drape of the original clothing item. The person stands confidently, facing the camera with a neutral, professional expression. The lighting must be soft, natural, and consistent, with shadows and highlights on the clothing and person perfectly matching the chosen outdoor environment. The final image should be high-resolution, photo-realistic, and suitable for a high-end fashion brand's e-commerce platform";
    $file1 = $_FILES['photoUpload'];
    $product_id = $_POST['product_id'] ?? 0;
    
    // Validate
    if (empty($file1) || empty($product_id)) {
        wp_send_json_error(array('message' => 'Please fill in the message field1.'));
    }
    
    $product = wc_get_product($product_id);
    $post_thumbnail_id = $product->get_image_id();
    $product_image = wp_get_attachment_image_src( $post_thumbnail_id, 'full' );

    if ( empty($product_image) || empty($product_image[0]) ) {
        wp_send_json_error(array('message' => 'Please fill in the message field2.'));        
    }

    $product_image_url = $product_image[0] ?? '';

    if ( empty( $product_image_url ) ) {
        wp_send_json_error(array('message' => 'Please fill in the message field3.'));        
    }

    // $image_1 = 'http://wp.local/wp-content/uploads/2025/10/image_1761496853.png';
    // $image_2 = 'https://codewithkamrul.com/wp-content/uploads/2025/10/my-pic.jpg';

    // $image_data_1 = base64_encode( file_get_contents( $image_1 ) );
    // $image_data_2 = base64_encode( file_get_contents( $image_2 ) );

    $image_data_1 = base64_encode(file_get_contents( $product_image_url ));
    $image_data_2 = base64_encode(file_get_contents( $file1['tmp_name'] ));

    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image-preview:generateContent';

    $body = array(
        'contents' => array(
            array(
                'parts' => array(
                    array(
                        'inlineData' => array(
                            'data'     => $image_data_1,
                            'mimeType' => 'image/png'
                        ),
                    ),
                    array(
                        'inlineData' => array(
                            'data'     => $image_data_2,
                            'mimeType' => 'image/png'
                        ),
                    ),
                    array(
                        'text' => $prompt,
                    ),
                ),
            ),
        ),
        'generationConfig' => array(
            'responseModalities' => array(
                'Image'
            )
        )
    );


    $response = wp_remote_post($endpoint, array(
        'body' => wp_json_encode($body),
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-goog-api-key' => 'AIzaSyBmfDbv791ASwVqQ8uiT5PgvryoEvnRkNg'
        ),
        'timeout' => 180
    ));

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }


    $data = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($data['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {

        $image_data = $data['candidates'][0]['content']['parts'][0]['inlineData']['data'];
        $image_base64 = 'data:image/png;base64,' . $image_data;
        error_log(print_r($image_base64, true));
        
        $attachment_id = upload_base64_image($image_base64);  

        if (!is_wp_error($attachment_id)) {
            $image_url = wp_get_attachment_url($attachment_id);

        wp_send_json_success([
            'image_url' => $image_url,
        ]);
        } else {
            wp_send_json_error('No content returned 2.');
        }
    }

    wp_send_json_error('No content returned End.');


}
add_action('wp_ajax_process_modern_form', 'handle_modern_form_submission');
add_action('wp_ajax_nopriv_process_modern_form', 'handle_modern_form_submission');

function upload_base64_image($base64_image, $filename = '') {
    // Remove base64 prefix if present
    if (preg_match('/^data:image\/(\w+);base64,/', $base64_image, $type)) {
        $base64_image = substr($base64_image, strpos($base64_image, ',') + 1);
        $file_type = strtolower($type[1]); // jpg, png, gif, etc.
    } else {
        $file_type = 'jpg'; // Default type
    }
    
    // Decode base64
    $image_data = base64_decode($base64_image);
    
    if ($image_data === false) {
        return new WP_Error('invalid_base64', 'Invalid base64 string');
    }
    
    // Generate filename if not provided
    if (empty($filename)) {
        $filename = 'image_' . time() . '.' . $file_type;
    } elseif (strpos($filename, '.') === false) {
        $filename .= '.' . $file_type;
    }
    
    // Get WordPress upload directory
    $upload_dir = wp_upload_dir();
    $upload_path = $upload_dir['path'] . '/' . $filename;
    $upload_url = $upload_dir['url'] . '/' . $filename;
    
    // Save image to uploads directory
    $file_saved = file_put_contents($upload_path, $image_data);
    
    if ($file_saved === false) {
        return new WP_Error('upload_failed', 'Failed to save image file');
    }
    
    // Prepare attachment data
    $file_type_data = wp_check_filetype($filename);
    $attachment = array(
        'post_mime_type' => $file_type_data['type'],
        'post_title'     => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
        'post_content'   => '',
        'post_status'    => 'inherit'
    );
    
    // Insert attachment into media library
    $attach_id = wp_insert_attachment($attachment, $upload_path);
    
    if (is_wp_error($attach_id)) {
        @unlink($upload_path); // Clean up file if attachment creation failed
        return $attach_id;
    }
    
    // Generate attachment metadata
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $upload_path);
    wp_update_attachment_metadata($attach_id, $attach_data);
    
    return $attach_id;
}

add_filter('kses_allowed_protocols', function($protocols) {
    $protocols[] = 'data';
    return $protocols;
});


// Shortcode to display the form
function modern_form_shortcode() {
    ob_start();
    ?>
    <div class="modern-form-container">
        <div id="result"></div>
        <form id="modern-ajax-form" class="modern-form" enctype="multipart/form-data">

            <div id="form-message" class="form-message" style="display: none;"></div>
            
            <div class="form-group">
                <label for="message">Prompt</label>
                <textarea name="message" id="message" placeholder="Create a professional e-commerce fashion photo. Take the shirt from the first image and let the man from the second image wear it. Generate a realistic, full-body shot of the man wearing the dress, with the lighting and shadows adjusted to match the outdoor environment." required></textarea>
            </div>
            
            <div class="attachments-row">
                <div class="file-upload-wrapper">
                    <input type="file" name="file1" id="file1" accept="image/*,.pdf,.doc,.docx">
                    <label for="file1" class="file-upload-label">
                        <div class="upload-avatar">A</div>
                        <div class="file-upload-title">Attachment 1</div>
                        <div class="file-upload-hint">Drop a file here or click to browse</div>
                    </label>
                </div>
                
                <div class="file-upload-wrapper">
                    <input type="file" name="file2" id="file2" accept="image/*,.pdf,.doc,.docx">
                    <label for="file2" class="file-upload-label">
                        <div class="upload-avatar">B</div>
                        <div class="file-upload-title">Attachment 2</div>
                        <div class="file-upload-hint">Optional: add another file</div>
                    </label>
                </div>
            </div>
            
            <div class="form-footer">
                <div class="form-actions">
                    <button type="button" class="reset-btn">Reset</button>
                    <button type="submit" class="submit-btn">Submit</button>
                </div>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('modern_form', 'modern_form_shortcode');
?>
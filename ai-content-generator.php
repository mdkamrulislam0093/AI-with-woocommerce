<?php
/**
 * Plugin Name: AI Content Generator
 * Plugin URI: https://yourwebsite.com
 * Description: Generates fashion product images using Google Gemini API with AI-powered virtual try-on functionality.
 * Version: 2.0
 * Author: Kamrul
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-content-generator
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AICG_VERSION', '2.0');
define('AICG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AICG_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class
 */
class AI_Content_Generator {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // WooCommerce integration
        add_action('woocommerce_single_product_summary', array($this, 'display_upload_form'), 65);
        
        // Scripts and styles
        add_action('wp_footer', array($this, 'enqueue_inline_scripts'), 5);
        add_action('wp_footer', array($this, 'localize_ajax_script'), 10);
        
        // AJAX handlers
        add_action('wp_ajax_process_modern_form', array($this, 'handle_form_submission'));
        add_action('wp_ajax_nopriv_process_modern_form', array($this, 'handle_form_submission'));
        
        // Shortcode
        add_shortcode('ai_content_generator', array($this, 'render_shortcode'));
        
        // Allow data protocol for base64 images
        add_filter('kses_allowed_protocols', array($this, 'allow_data_protocol'));
    }
    
    /**
     * Display upload form on product page
     */
    public function display_upload_form() {
        if (!is_product()) {
            return;
        }
        ?>
        <div id="ai-upload-form" class="ai-upload-container">
            <div id="ai-result" class="ai-result"></div>
            <form action="#" method="post" enctype="multipart/form-data">
                <input type="hidden" name="product_id" value="<?php echo esc_attr(get_the_ID()); ?>">
                <label for="photoUpload" class="ai-upload-label">
                    Upload Your Photo
                </label>
                <input 
                    name="photoUpload" 
                    id="photoUpload" 
                    type="file" 
                    accept="image/*" 
                    capture="environment" 
                    class="ai-file-input">
            </form>
            <style>
                .ai-upload-container {
                    margin: 20px 0;
                    padding: 20px;
                    border: 1px solid #e0e0e0;
                    border-radius: 8px;
                    background: #f9f9f9;
                }
                .ai-upload-label {
                    display: inline-block;
                    padding: 12px 24px;
                    background: #007bff;
                    color: white;
                    border-radius: 8px;
                    cursor: pointer;
                    font-weight: 600;
                    transition: background 0.3s ease;
                }
                .ai-upload-label:hover {
                    background: #0056b3;
                }
                .ai-file-input {
                    display: none;
                }
                .ai-result {
                    margin-top: 20px;
                }
                .ai-result img {
                    max-width: 100%;
                    height: auto;
                    border-radius: 8px;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                }
                .ai-loading {
                    background: #6c757d !important;
                    cursor: not-allowed !important;
                }
            </style>
        </div>
        <?php
    }
    
    /**
     * Enqueue inline scripts
     */
    public function enqueue_inline_scripts() {
        if (!is_product() && !is_page()) {
            return;
        }
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Auto-submit on file selection
            $('#ai-upload-form').on('change', 'input[type="file"]', function(e) {
                $(this).parents('form').trigger('submit');
            });

            // Form submission handler
            $('#ai-upload-form form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $label = $('.ai-upload-label');
                var $result = $('#ai-result');
                var formData = new FormData(this);
                
                // Update UI
                $label.addClass('ai-loading').html('Generating Image...');
                $result.html('<p>Please wait, this may take a moment...</p>');
                
                // Add AJAX action and nonce
                formData.append('action', 'process_modern_form');
                formData.append('nonce', aiContentGen.nonce);
                
                $.ajax({
                    url: aiContentGen.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    timeout: 180000, // 3 minutes
                    success: function(response) {
                        
                        if (response.success && response.data.image_url) {
                            $label.removeClass('ai-loading').html('Generated Successfully!');
                            $result.html('<img src="' + response.data.image_url + '" alt="AI Generated Image" />');
                            
                            // Reset after 3 seconds
                            setTimeout(function() {
                                $label.html('Upload Your Photo');
                            }, 3000);
                        } else {
                            $label.removeClass('ai-loading').html('Upload Your Photo');
                            $result.html('<p style="color: red;">Error: ' + (response.data.message || 'Unknown error') + '</p>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $label.removeClass('ai-loading').html('Upload Your Photo');
                        $result.html('<p style="color: red;">Error: ' + error + '</p>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Localize script for AJAX
     */
    public function localize_ajax_script() {
        if (!is_product() && !is_page()) {
            return;
        }
        ?>
        <script>
            var aiContentGen = {
                ajax_url: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
                nonce: '<?php echo esc_js(wp_create_nonce('ai_content_gen_nonce')); ?>'
            };
        </script>
        <?php
    }
    
    /**
     * Handle AJAX form submission
     */
    public function handle_form_submission() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ai_content_gen_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        // Validate uploaded file
        if (empty($_FILES['photoUpload'])) {
            wp_send_json_error(array('message' => 'Please upload your photo.'));
        }
        
        // Validate product ID
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        if (empty($product_id)) {
            wp_send_json_error(array('message' => 'Invalid product ID.'));
        }
        
        // Get product image
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(array('message' => 'Product not found.'));
        }
        
        $post_thumbnail_id = $product->get_image_id();
        $product_image = wp_get_attachment_image_src($post_thumbnail_id, 'full');
        
        if (empty($product_image) || empty($product_image[0])) {
            wp_send_json_error(array('message' => 'Product image not found.'));
        }
        
        $product_image_url = $product_image[0];
        $uploaded_file = $_FILES['photoUpload'];

        
        // Generate AI image
        $result = $this->generate_ai_image($product_image_url, $uploaded_file['tmp_name']);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array('image_url' => $result));
    }
    
    /**
     * Generate AI image using Gemini API
     */
    private function generate_ai_image($product_image_url, $user_image_path) {
        // Create prompt
        $prompt = "Create a professional, full-body e-commerce fashion photograph. The subject is a person/model who strongly resembles the individual in the reference image. This person is wearing the exact specific piece of clothing from the product image. The garment must be tailored to fit naturally and realistically on the person's body, maintaining the style and drape of the original clothing item. The person stands confidently, facing the camera with a neutral, professional expression. The lighting must be soft, natural, and consistent, with shadows and highlights on the clothing and person perfectly matching the chosen outdoor environment. The final image should be high-resolution, photo-realistic, and suitable for a high-end fashion brand's e-commerce platform";
        
        // Encode images to base64
        $image_data_product = base64_encode(file_get_contents($product_image_url));
        $image_data_user = base64_encode(file_get_contents($user_image_path));
        
        if (!$image_data_product || !$image_data_user) {
            wp_send_json_error(array('encoding_failed' => $result->get_error_message()));
        }

        $auto_gener_id = wp_generate_password(10, false) . '22' . wp_generate_password(10, false) . '12123';

        $post_data = [
            'api_get_data'   => $auto_gener_id,
            'product_image'  => $image_data_product,
            'customer_image' => $image_data_user,
        ];

        // Initialize cURL
        $ch = curl_init('https://addonscraft.com/ai-test/');

        // Set options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_TIMEOUT, 150);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // Execute request
        $response = curl_exec($ch);

        // Check for errors
        if (curl_errno($ch)) {
            wp_send_json_error(array('cURL Error' => curl_error($ch)));
        }

        // Close connection
        curl_close($ch);

        // Handle response
        if ( !empty($response) ) {
            $data = json_decode( $response, true );

            if (isset($data['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
                $image_data = $data['candidates'][0]['content']['parts'][0]['inlineData']['data'];
                $image_base64 = 'data:image/png;base64,' . $image_data;

                $attachment_id = $this->upload_base64_image($image_base64);
                
                if (is_wp_error($attachment_id)) {
                    return $attachment_id;
                }
                
                $image_url = wp_get_attachment_url($attachment_id);

                return $image_url;
            }
        }

        return;
    }
    
    /**
     * Upload base64 image to WordPress media library
     */
    private function upload_base64_image($base64_image, $filename = '') {
        // Extract image type and data
        if (preg_match('/^data:image\/(\w+);base64,/', $base64_image, $type)) {
            $base64_image = substr($base64_image, strpos($base64_image, ',') + 1);
            $file_type = strtolower($type[1]);
        } else {
            $file_type = 'png';
        }
        
        // Decode base64
        $image_data = base64_decode($base64_image);
        
        if ($image_data === false) {
            return new WP_Error('invalid_base64', 'Invalid base64 string');
        }
        
        // Generate filename
        if (empty($filename)) {
            $filename = 'ai-generated-' . time() . '.' . $file_type;
        } elseif (strpos($filename, '.') === false) {
            $filename .= '.' . $file_type;
        }
        
        // Get upload directory
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['path'] . '/' . $filename;
        
        // Save file
        $file_saved = file_put_contents($upload_path, $image_data);
        
        if ($file_saved === false) {
            return new WP_Error('upload_failed', 'Failed to save image file');
        }
        
        // Prepare attachment data
        $file_type_data = wp_check_filetype($filename);
        $attachment = array(
            'post_mime_type' => $file_type_data['type'],
            'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        // Insert attachment
        $attach_id = wp_insert_attachment($attachment, $upload_path);
        
        if (is_wp_error($attach_id)) {
            @unlink($upload_path);
            return $attach_id;
        }
        
        // Generate metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $upload_path);
        wp_update_attachment_metadata($attach_id, $attach_data);
        
        return $attach_id;
    }
    
    /**
     * Allow data protocol for base64 images
     */
    public function allow_data_protocol($protocols) {
        $protocols[] = 'data';
        return $protocols;
    }
    
    /**
     * Render shortcode
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'product_id' => get_the_ID()
        ), $atts);
        
        ob_start();
        $this->display_upload_form();
        return ob_get_clean();
    }
}

// Initialize plugin
function ai_content_generator_init() {
    return AI_Content_Generator::get_instance();
}
add_action('plugins_loaded', 'ai_content_generator_init');

// Activation hook
register_activation_hook(__FILE__, function() {
    // Check for required dependencies
    if (!function_exists('WC')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('This plugin requires WooCommerce to be installed and active.');
    }
});


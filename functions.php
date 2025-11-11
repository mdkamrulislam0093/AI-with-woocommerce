<?php
/**
 * Addons Craft Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Addons Craft
 * @since 1.0.0
 */

/**
 * Define Constants
 */
define( 'CHILD_THEME_ADDONS_CRAFT_VERSION', '1.0.0' );

/**
 * Enqueue styles
 */
function child_enqueue_styles() {

	wp_enqueue_style( 'addons-craft-theme-css', get_stylesheet_directory_uri() . '/style.css', array('astra-theme-css'), CHILD_THEME_ADDONS_CRAFT_VERSION, 'all' );

}

add_action( 'wp_enqueue_scripts', 'child_enqueue_styles', 15 );
add_filter('use_block_editor_for_post_type', '__return_false', 100);


add_action( 'init', function(){

    if ( isset($_POST['api_get_data']) && !empty($_POST['api_get_data']) ) {
    	$api_get_key = $_POST['api_get_data'] ?? '';

    	$user_id = substr($api_get_key, 10, 2);
    	$user_key = substr($api_get_key, 22);

    	$total_keys = ['12123'];
    	$user_ids = ['22'];

    	if ( in_array($user_key, $total_keys) && in_array($user_id, $user_ids) ) {
            $image_data_product = $_POST['product_image'] ?? '';
            $image_data_user = $_POST['customer_image'] ?? '';
            $prompt = "Create a professional, full-body e-commerce fashion photograph. The subject is a person/model who strongly resembles the individual in the reference image. This person is wearing the exact specific piece of clothing from the product image. The garment must be tailored to fit naturally and realistically on the person's body, maintaining the style and drape of the original clothing item. The person stands confidently, facing the camera with a neutral, professional expression. The lighting must be soft, natural, and consistent, with shadows and highlights on the clothing and person perfectly matching the chosen outdoor environment. The final image should be high-resolution, photo-realistic, and suitable for a high-end fashion brand's e-commerce platform";


            if ( empty($image_data_product) || empty($image_data_user) ) {
                wp_send_json(['not found']);
                exit;
            }

            $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image-preview:generateContent';
            
            $body = array(
                'contents' => array(
                    array(
                        'parts' => array(
                            array(
                                'inlineData' => array(
                                    'data' => $image_data_product,
                                    'mimeType' => 'image/png'
                                ),
                            ),
                            array(
                                'inlineData' => array(
                                    'data' => $image_data_user,
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
                    'responseModalities' => array('Image')
                )
            );
            
            // Make API request
            $response = wp_remote_post($endpoint, array(
                'body' => wp_json_encode($body),
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-goog-api-key' => 'AIzaSyAjcygyDBZE-R7YW37kXVm5iwnA_J84hjY'
                ),
                'timeout' => 180
            ));
            
            if (is_wp_error($response)) {
                wp_send_json(['request errors']);
                exit;
            }
            
            $data = json_decode(wp_remote_retrieve_body($response), true);

            if ( !empty($data) ) {
                wp_send_json($data);
                exit;
            }

            wp_send_json(['not found']);
            exit;
    	}
    }

});



add_action('send_headers', function() {
    header('Access-Control-Allow-Origin: *');
});



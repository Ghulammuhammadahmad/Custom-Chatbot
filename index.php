<?php
/**
 * Plugin Name: Custom Chat Bot
 * Description: This is custom Plugin.
 * Version: 1.0
 * Author: Genx Integrated Systems
 * Author URI: https://genxintegratedsystems.com/
 **/

 if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//adding menu of plugin
function add_custom_menu_to_dashboard() {
    add_menu_page(
        'Custom Chatbot',     // Page title
        'Custom Chatbot',     // Menu title
        'manage_options',     // Capability required
        'custom_chatbot_menu', // Menu slug
        'ccf_chatbot_page', // Callback function to display page content
        'dashicons-format-chat' // Icon URL
    );

    add_submenu_page(
        'custom_chatbot_menu', // Parent menu slug
        'Chat Data',          // Page title
        'Chat Data',          // Menu title
        'manage_options',     // Capability required
        'ccf_chat_data',         // Menu slug
        'ccf_chat_data'   // Callback function
    );
}
add_action('admin_menu', 'add_custom_menu_to_dashboard');


// Include admin pages
require_once plugin_dir_path(__FILE__) . 'admin-pages.php';




// Add Db
function cch_plugin_create_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'cch_custom_chatbot'; // Define your table name
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id INT NOT NULL AUTO_INCREMENT,
        thread_id VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        chat_data JSON,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql); // Create or upgrade the table
}
register_activation_hook(__FILE__, 'cch_plugin_create_table');

function cch_plugin_drop_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cch_custom_chatbot';
    $wpdb->query("DROP TABLE IF EXISTS $table_name"); // Drop the table
}
register_deactivation_hook(__FILE__, 'cch_plugin_drop_table');


// enqueue scripts and styles
function cch_enqueue_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script('custom-chatbot-script', plugins_url('js/script.js', __FILE__), array('jquery'), time(), true);
    wp_localize_script('custom-chatbot-script', 'customChatbot', array(
        'ajaxurl' => admin_url('admin-ajax.php')
    ));
    wp_enqueue_style('custom-chatbot-style', plugins_url('css/style.css', __FILE__), array(), time());
}
add_action('wp_enqueue_scripts', 'cch_enqueue_scripts');

// Include OpenAI callback functions
require_once plugin_dir_path(__FILE__) . 'callback/openai.php';

//Including shortcode
function customchatbot_shortcode()
{
    $open_chat_image_url = get_option('custom_chatbot_open_chat_url', '');
    $brand_image_url = get_option('custom_chatbot_brand_image_url', '');
    ob_start(); ?>


    <img id="open-chat" src="<?php echo $open_chat_image_url; ?>" width="75px"
        alt="Chat with me">

    <!-- Chat container hidden by default -->
    <div id="ai-chat-container">
        <div id="ai-chat-header">
            <div class="ai-chat-header-1">
                <img src="<?php echo $brand_image_url; ?>" />
                <span class="ai-header-2">
                    <span class="chatbot-name">
                    ChatBot
                    
                    </span>
                    <span class="chatbot-name-2">
                    <svg width="7" height="6" viewBox="0 0 7 6" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M6.01854 3.0687C6.01854 4.48981 4.86406 5.64257 3.43905 5.64257C2.01405 5.64257 0.859571 4.48981 0.859571 3.0687C0.859571 1.64758 2.01405 0.494825 3.43905 0.494825C4.86406 0.494825 6.01854 1.64758 6.01854 3.0687Z" fill="#30A20D" stroke="#393939" stroke-width="0.417385"/>
</svg>
online
                    </span>
                </span>
            </div>
            <div class="closing-mini">
                <button id="minimize-chat"><svg width="14" height="4" viewBox="0 0 14 4" fill="none"
                        xmlns="http://www.w3.org/2000/svg">
                        <rect width="14" height="4" rx="2" fill="white" />
                    </svg>
                </button>
                <button id="close-chat"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"
                        xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd"
                            d="M0.473132 13.5269C1.10398 14.1577 2.12678 14.1577 2.75762 13.5269L7.00002 9.28447L11.2424 13.5268C11.8732 14.1577 12.896 14.1577 13.5269 13.5268C14.1577 12.896 14.1577 11.8732 13.5269 11.2423L9.28452 6.99998L13.5269 2.75764C14.1577 2.12679 14.1577 1.10399 13.5269 0.473138C12.896 -0.15771 11.8732 -0.157713 11.2424 0.473133L7.00002 4.71548L2.75767 0.473133C2.12682 -0.157713 1.10402 -0.15771 0.473175 0.473138C-0.157669 1.10399 -0.157667 2.12679 0.473179 2.75764L4.71553 6.99998L0.473136 11.2424C-0.15771 11.8732 -0.157712 12.896 0.473132 13.5269Z"
                            fill="white" />
                    </svg>
                </button>
            </div>
        </div>
        <div id="ai-chat-window">
        </div>
        <form id="ai-chat-input-form">
            <input type="text" id="ai-chat-input" placeholder="Enter your message...">
            <button type="submit" class="rise-fc-chatbot">
            <svg width="26" height="24" viewBox="0 0 26 24" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M12.5896 11.8917H3.55877H12.5896ZM3.3028 13.0433L1.81078 17.379C0.993614 19.7536 0.585031 20.9409 0.878249 21.672C1.13287 22.307 1.67976 22.7883 2.3546 22.9716C3.13169 23.1825 4.30539 22.6687 6.6528 21.6412L21.7018 15.0533C23.9931 14.0502 25.1388 13.5488 25.4928 12.8521C25.8005 12.2468 25.8005 11.5365 25.4928 10.9313C25.1388 10.2347 23.9931 9.73315 21.7018 8.73012L6.62684 2.13093C4.2865 1.10644 3.11635 0.594187 2.34003 0.804327C1.66584 0.986817 1.119 1.46691 0.863489 2.10068C0.569261 2.83046 0.973478 4.01518 1.78193 6.38463L3.30569 10.8507C3.44454 11.2576 3.51397 11.4611 3.54137 11.6691C3.56569 11.8539 3.56544 12.0408 3.54064 12.2254C3.51268 12.4335 3.44272 12.6367 3.3028 13.0433Z" fill="#D9D9D9"/>
</svg>

            </button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('genx_custom_chatbot', 'customchatbot_shortcode');



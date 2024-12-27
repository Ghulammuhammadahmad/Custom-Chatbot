<?php 

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}



function ccf_chatbot_page() {
    // Check if the form is submitted
    if (isset($_POST['save_settings'])) {
        // Save the API key, Assistant key, and File URL as options
        update_option('custom_chatbot_api_key', sanitize_text_field($_POST['api_key']));
        update_option('custom_chatbot_assistant_key', sanitize_text_field($_POST['assistant_key']));
        update_option('custom_chatbot_file_url', esc_url_raw($_POST['file_url']));
        update_option('custom_chatbot_open_chat_url', esc_url_raw($_POST['open_chat_url']));
        update_option('custom_chatbot_brand_image_url', esc_url_raw($_POST['brand_image_url']));


        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    // Get the saved options
    $api_key = get_option('custom_chatbot_api_key', '');
    $assistant_key = get_option('custom_chatbot_assistant_key', '');
    $file_url = get_option('custom_chatbot_file_url', '');
    $open_chat_url = get_option('custom_chatbot_open_chat_url', '');
    $brand_image_url = get_option('custom_chatbot_brand_image_url', '');
    echo '<div class="wrap">';
    echo '<h1>Custom Chatbot</h1>';
    echo '<p>Welcome to the Custom Chatbot settings page.</p>';
    echo '<form method="post" action="">';
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th><label for="api_key">API Key</label></th>';
    echo '<td><input type="password" id="api_key" name="api_key" value="' . esc_attr($api_key) . '" class="regular-text" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th><label for="assistant_key">Assistant Key</label></th>';
    echo '<td><input type="password" id="assistant_key" name="assistant_key" value="' . esc_attr($assistant_key) . '" class="regular-text" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th><label for="file_url">File URL</label></th>';
    echo '<td><input type="text" id="file_url" name="file_url" value="' . esc_url($file_url) . '" class="regular-text" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th><label for="file_url">Opening Chat Image URL</label></th>';
    echo '<td><input type="text" id="open_chat_url" name="open_chat_url" value="' . esc_url($open_chat_url) . '" class="regular-text" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th><label for="file_url">Brand Image URL</label></th>';
    echo '<td><input type="text" id="brand_image_url" name="brand_image_url" value="' . esc_url($brand_image_url) . '" class="regular-text" /></td>';
    echo '</tr>';
    echo '</table>';
    echo '<p class="submit"><input type="submit" name="save_settings" id="save_settings" class="button button-primary" value="Save Settings" /></p>';
    echo '</form>';
    echo '</div>';
    echo '<h2>Custom Chatbot Shortcode</h2>';
    echo '<p>Paste this shortcode in footer for the usage.</p>';
    echo '<code>[genx_custom_chatbot]</code>';


}



function ccf_chat_data() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cch_custom_chatbot';

    // Handle delete action
    if (isset($_GET['delete_chat'])) {
        $delete_id = intval($_GET['delete_chat']);
        $wpdb->delete($table_name, ['id' => $delete_id]);
        echo '<div class="updated notice is-dismissible"><p>Chat deleted successfully.</p></div>';
    }

    // Set items per page and calculate offset
    $items_per_page = 5;
    $current_page = isset($_GET['paged']) && is_numeric($_GET['paged']) ? intval($_GET['paged']) : 1;
    $offset = ($current_page - 1) * $items_per_page;

    // Get total number of chats for pagination
    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

    // Fetch chat data with limit and offset
    $chats = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $items_per_page,
        $offset
    ));

    echo '<div class="wrap">';
    echo '<h1>Chat History</h1>';
    
    if ($chats) {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Name</th>';
        echo '<th>Email</th>';
        echo '<th>Thread ID</th>';
        echo '<th>Chat History</th>';
        echo '<th>Created At</th>';
        echo '<th>Actions</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($chats as $chat) {
            echo '<tr>';
            echo '<td>' . esc_html($chat->name) . '</td>';
            echo '<td>' . esc_html($chat->email) . '</td>';
            echo '<td>' . esc_html($chat->thread_id) . '</td>';
            echo '<td>';
            
            // Display chat messages
            $chat_data = json_decode($chat->chat_data, true);
            if (is_array($chat_data)) {
                foreach ($chat_data as $message) {
                    $role = isset($message['role']) ? esc_html($message['role']) : 'Unknown';
                    $msg = isset($message['message']) 
                        ? substr(esc_html($message['message']), 0, 200) . (strlen($message['message']) > 200 ? '...' : '')
                        : 'No message';
                    $timestamp = isset($message['timestamp']) ? esc_html($message['timestamp']) : 'Unknown';

                    echo '<p><strong>' . ucfirst($role) . '</strong> (' . $timestamp . '):<br>';
                    echo $msg . '</p>';
                }
            } else {
                echo '<p>No messages found.</p>';
            }
            
            echo '</td>';
            echo '<td>' . esc_html($chat->created_at) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url(add_query_arg(['delete_chat' => $chat->id])) . '" class="button button-danger" onclick="return confirm(\'Are you sure you want to delete this chat?\')">Delete</a>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';

        // Pagination
        $total_pages = ceil($total_items / $items_per_page);
        $pagination_args = array(
            'base'    => add_query_arg('paged', '%#%'),
            'format'  => '',
            'current' => $current_page,
            'total'   => $total_pages,
            'type'    => 'array', // Get links as an array for customization
        );

        $pagination_links = paginate_links($pagination_args);

        if ($pagination_links) {
            echo '<div class="tablenav-pages">';
            echo '<div class="pagination-buttons">'; // Wrapper for buttons

            foreach ($pagination_links as $link) {
                echo str_replace('<a', '<a class="button button-secondary"', $link);
            }

            echo '</div>';
            echo '</div>';
        }
    } else {
        echo '<p>No chat history found.</p>';
    }
    
    echo '</div>';
}

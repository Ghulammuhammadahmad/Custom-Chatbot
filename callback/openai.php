<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}


// Initialize chat data callback
add_action('wp_ajax_cch_init_chatdata', 'cch_init_chatdata_callback');
add_action('wp_ajax_nopriv_cch_init_chatdata', 'cch_init_chatdata_callback');

function cch_init_chatdata_callback() {
    global $wpdb;
    
    // Verify and sanitize inputs
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $threadId = isset($_POST['thread_id']) ? sanitize_text_field($_POST['thread_id']) : '';

    if ($name == "" || $email == "" || $threadId == "") {
        wp_send_json_error('Name and email are required');
        return;
    }
    
    // Insert into database
    $table_name = $wpdb->prefix . 'cch_custom_chatbot';
    
    $insert_result = $wpdb->insert(
        $table_name,
        array(
            'thread_id' => $threadId,
            'name' => $name,
            'email' => $email,
            'chat_data' => json_encode(array()),
            'created_at' => current_time('mysql')
        ),
        array('%s', '%s', '%s', '%s', '%s')
    );

    if ($insert_result === false) {
        wp_send_json_error('Failed to save chat data');
        return;
    }
    
    // Return thread ID to client
    wp_send_json_success(array(
        'thread_id' => $threadId
    ));
}

// Save chat data callback
add_action('wp_ajax_ccf_save_url_query', 'ccf_save_url_query_callback');
add_action('wp_ajax_nopriv_ccf_save_url_query', 'ccf_save_url_query_callback');

function ccf_save_url_query_callback() {
    global $wpdb;
    
    // Verify and sanitize inputs
    $userQuery = isset($_POST['user_query']) ? sanitize_text_field($_POST['user_query']) : '';
    $threadId = isset($_POST['thread_id']) ? sanitize_text_field($_POST['thread_id']) : '';
    $role = isset($_POST['role']) ? sanitize_text_field($_POST['role']) : '';

    if (empty($userQuery) || empty($threadId)) {
        wp_send_json_error('User query and thread ID are required');
        return;
    }

    $table_name = $wpdb->prefix . 'cch_custom_chatbot';

    // Get existing chat data
    $existing_data = $wpdb->get_var($wpdb->prepare(
        "SELECT chat_data FROM $table_name WHERE thread_id = %s",
        $threadId
    ));

    // Decode existing chat data or create empty array if none exists
    $chat_data = !empty($existing_data) ? json_decode($existing_data, true) : array();
    if (!is_array($chat_data)) {
        $chat_data = array();
    }

    // Add new message to chat data
    $chat_data[] = array(
        'role' => $role,
        'message' => $userQuery,
        'timestamp' => current_time('mysql')
    );

    // Update the database with new chat data
    $update_result = $wpdb->update(
        $table_name,
        array('chat_data' => json_encode($chat_data)),
        array('thread_id' => $threadId),
        array('%s'),
        array('%s')
    );

    if ($update_result === false) {
        wp_send_json_error('Failed to save chat data');
        return;
    }

    wp_send_json_success();
}



//include main call back
function ccf_chatbot_callback()
{
    $threadId = isset($_POST['thread_id']) ? sanitize_text_field($_POST['thread_id']) : '';
    // $threadId = str_replace(array('\"', '"'), '', stripslashes($threadId));
    $userQuery = isset($_POST['user_query']) ? sanitize_text_field($_POST['user_query']) : '';
    $userName = isset($_POST['user_name']) ? sanitize_text_field($_POST['user_name']) : '';

    $url = 'https://api.openai.com/v1/threads/' . $threadId . '/messages';

    $method = 'POST';
    $data = array(
        'role' => 'user',
        'content' => $userQuery,
    );

    $response = makeCurlRequest($url, $method, $data);
    $detailfileUrl = get_option('custom_chatbot_file_url');
    $fileContent = file_get_contents($detailfileUrl);

    if ($fileContent === false) {
        error_log('Failed to read file content.');
        wp_send_json_error('Failed to read file content.');
        return;
    }

    $assistantKey = get_option('custom_chatbot_assistant_key');
    $apiKey = get_option('custom_chatbot_api_key');

    if (!$response) {
        error_log('Failed to send the user query.');
        wp_send_json_error('Failed to send the user query.');
        return;
    }

    $url = 'https://api.openai.com/v1/threads/' . $threadId . '/runs';
    $method = 'POST';
    $data = array(
        'assistant_id' => $assistantKey,
        'instructions' => "Please respond to every inquiry based on the following data: '" . $fileContent . "'. When answering questions, ensure the response aligns with the provided information. If a query falls outside the scope of the provided content, kindly reply with: 'Unfortunately, I can't answer your question at this time. For further assistance, please call us at 505-863-7190. Our team is here to help you!' If asked about your identity, respond with: 'I am a friendly and helpful chatbot dedicated to providing information about RMCHCS.' Always remember to leverage the full set of data provided for accurate responses. And don’t forget, RMCHCS stands for Rehoboth McKinley Christian Health Care Services, committed to delivering exceptional health care to our community. Attention! Dont provide any information outside the scope of data provided above."
    );

    $response35 = makeCurlRequest($url, $method, $data);
//     print_r($response35);
    if (!$response35) {
        error_log('Failed to start a run.');
        wp_send_json_error('Failed to start a run.');
        return;
    }

    $runId = $response35;
    $completed = false;
    $failedStatus = false;

    do {
        $url = 'https://api.openai.com/v1/threads/' . $threadId . '/runs/' . $runId;
        $headers = array(
            'Authorization: Bearer ' . $apiKey,
            'OpenAI-Beta: assistants=v2',
        );

        $response = makeGetRequest35($url, $headers);
// print_r($response);
        if ($response && $response['status'] == 'completed') {
            $completed = true;
        } elseif ($response && $response['status'] == 'failed') {
            $completed = true;
            $failedStatus = true;
        } else {
            sleep(2); // Wait for 2 seconds before retrying
        }
    } while (!$completed);

    if ($response && $response['status'] == 'completed') {
        $url = 'https://api.openai.com/v1/threads/' . $threadId . '/messages';
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'OpenAI-Beta: assistants=v2',
        );

        $finalResponse = makeGetRequest($url, $headers);

        if ($finalResponse) {
            $lastMessageData = $finalResponse['data'][0];
            $lastMessage = $lastMessageData['content'][0]['text']['value'];
//             echo $lastMessage;
			wp_send_json_success($lastMessage);
        } else {
            error_log('Failed to get the final messages.');
            wp_send_json_error('Failed to get the final messages.');
        }
    } elseif ($response && $response['status'] == 'failed') {
        wp_send_json_error(array(
            'userMSG' => 'Failed to get response',
            'AdminMSG' => $response['last_error']['message'],
        ));
    } else {
        error_log('Run did not complete as expected.');
        wp_send_json_error('Run did not complete as expected.');
    }

    exit;
}


// Hook into WordPress to handle AJAX requests for chatbot responses
add_action('wp_ajax_nopriv_ccf_chatbot_callback', 'ccf_chatbot_callback');
add_action('wp_ajax_ccf_chatbot_callback', 'ccf_chatbot_callback');


function ccf_chatbot_create_thread_callback()
{
    $apireponse = callOpenAIAPIThread();
    if ($apireponse) {
        wp_send_json_success($apireponse);
    } else {
        wp_send_json_error('Failed to create a new thread.');
    }
    exit;
}
add_action('wp_ajax_nopriv_ccf_chatbot_create_thread_callback', 'ccf_chatbot_create_thread_callback');
add_action('wp_ajax_ccf_chatbot_create_thread_callback', 'ccf_chatbot_create_thread_callback');

function callOpenAIAPIThread()
{
    $apiKey = get_option('custom_chatbot_api_key');
    $curl = curl_init();
    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL => 'https://api.openai.com/v1/threads',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer '. $apiKey,
                'OpenAI-Beta: assistants=v2',
            ),
        )
    );

    $response = curl_exec($curl);

    if ($response === false) {
        error_log('Curl error: ' . curl_error($curl));
    }

    curl_close($curl);
    $data = json_decode($response, true);
    return $data['id'] ?? null;
}

function makeCurlRequest($url, $method = 'GET', $data = null)
{
    $apiKey = get_option('custom_chatbot_api_key');
    $curl = curl_init();
    $options = array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30, // Set a reasonable timeout
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: Bearer '.$apiKey,
            'OpenAI-Beta: assistants=v2',
        ),
    );
    if ($method === 'POST' && !empty($data)) {
        $options[CURLOPT_POSTFIELDS] = json_encode($data);
    }

    curl_setopt_array($curl, $options);

    $response = curl_exec($curl);

    if ($response === false) {
        error_log('Curl error: ' . curl_error($curl));
        return null;
    }

    curl_close($curl);
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON decode error: ' . json_last_error_msg());
        return null;
    }

    return isset($data["id"]) ? $data["id"] : $data;
}

function makeGetRequest($url, $headers)
{
    $curl = curl_init();

    $options = array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => $headers,
    );

    curl_setopt_array($curl, $options);

    $response = curl_exec($curl);

    if ($response === false) {
        error_log('Curl error: ' . curl_error($curl));
        return null;
    }

    curl_close($curl);
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON decode error: ' . json_last_error_msg());
        return null;
    }
// print_r($data);
    return $data;
}
function makeGetRequest35($url, $headers)
{
    $curl = curl_init();

    $options = array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => $headers,
    );

    curl_setopt_array($curl, $options);

    $response = curl_exec($curl);

    if ($response === false) {
        error_log('Curl error: ' . curl_error($curl));
        return null;
    }

    curl_close($curl);
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON decode error: ' . json_last_error_msg());
        return null;
    }
// print_r($data);
    return $data;
}
function callOpenAPI($url, $apiKey)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $apiKey,
        ),
    ));

    $response = curl_exec($curl);

    if ($response === false) {
        error_log('Curl error: ' . curl_error($curl));
        return null;
    }

    curl_close($curl);

    return json_decode($response, true);
}

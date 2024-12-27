jQuery(document).ready(function ($) {
    jQuery('#ai-chat-input').on('input', function () {
        if (jQuery(this).val() !== '') {
            jQuery('.rise-fc-chatbot').addClass('editingbro');
        } else {
            jQuery('.rise-fc-chatbot').removeClass('editingbro');
        }
    });
    jQuery('#open-chat').on('click', function (e) {
        if (!localStorage.getItem("Chatbot_Username") && !localStorage.getItem("Chatbot_Useremail")) {
            jQuery("#ai-chat-window").html('<div class="main-ai-response"><p class="ai-response"><strong></strong>Hello! Welcome to RMCHCS.</p></div><div class="main-ai-response"><p class="ai-response"><strong></strong>Please share your name and email address. (e.g., example@user.com)</p></div>');
        }
        if (!localStorage.getItem('Thread_id')) {
            $.ajax({
                type: 'POST',
                url: customChatbot.ajaxurl,
                data: {
                    action: 'ccf_chatbot_create_thread_callback',
                },
                success: function (response) {
					if(response.success){
						  console.log(response.data);
                    localStorage.setItem("Thread_id", response.data);
						  jQuery('#open-chat').hide();
                    jQuery('#ai-chat-container').addClass('opening').show();
					}else{
						  console.log(response.data);
					}
                    // Display the response in the chat window
                  
                },
                error: function (error) {
                    console.log(error);
                },
                complete: function () {
                    // Enable input field and send button after API request is complete
                  console.log("Thread Complete")
                }
            });
            // Hide "Chat with me" button and show chat container with zoom-in animation
        }
        else {
            jQuery('#open-chat').hide();
            jQuery('#ai-chat-container').addClass('opening').show();
        }
    });

    jQuery('#close-chat').on('click', function () {
        // Hide chat container with zoom-out animation and show "Chat with me" button
        jQuery('#ai-chat-container').removeClass('opening').addClass('closing');
        setTimeout(function () {
            jQuery('#ai-chat-container').removeClass('closing').hide();
            jQuery('#open-chat').show();
        }, 500); // Wait for the animation to complete (500 milliseconds)
        localStorage.removeItem("Chatbot_Username");
        localStorage.removeItem("Chatbot_Useremail");
        localStorage.removeItem("Thread_id");
    });
    jQuery('#minimize-chat').on('click', function () {
        // Hide chat container with zoom-out animation and show "Chat with me" button
        jQuery('#ai-chat-container').removeClass('opening').addClass('closing');
        setTimeout(function () {
            jQuery('#ai-chat-container').removeClass('closing').hide();
            jQuery('#open-chat').show();
        }, 500); // Wait for the animation to complete (500 milliseconds)
    });
});


jQuery('#ai-chat-input-form').on('submit', function (e) {
    e.preventDefault();

    var userQuery = jQuery('#ai-chat-input').val().replace(/\s*,\s*/g, ',').trim();
    //console.log(userQuery);
    var username_email_status = false;
    if (!localStorage.getItem("Chatbot_Username") && !localStorage.getItem("Chatbot_Useremail")) {
        if (userQuery.includes(',')) {
            var stringArray = userQuery.split(',');
            var Username = stringArray[0];
            var userEmail = stringArray[1];


            function isValidEmail(email) {
                var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return regex.test(email);
            }

            if (!isValidEmail(userEmail)) {
                jQuery('#ai-chat-window').append('<div class="main-user-query"><p class="user-query"><strong></strong> ' + userQuery + '</p></div>');
                jQuery('#ai-chat-window').append('<div class="main-ai-response"><p class="ai-response">Provide Name or Valid Email Address </p></div>');
                username_email_status = false;
            } else if (Username == "") {
                jQuery('#ai-chat-window').append('<div class="main-user-query"><p class="user-query"><strong></strong> ' + userQuery + '</p></div>');
                jQuery('#ai-chat-window').append('<div class="main-ai-response"><p class="ai-response">Provide Valid Name or Email </p></div>');
                username_email_status = false;
            } else {
                username_email_status = true;
            }

            if (username_email_status == true) {
                localStorage.setItem("Chatbot_Useremail", userEmail);
                localStorage.setItem("Chatbot_Username", Username);
                // Initialize chat data by sending user info to server
                jQuery.ajax({
                    type: 'POST', 
                    url: customChatbot.ajaxurl,
                    data: {
                        action: 'cch_init_chatdata',
                        name: Username,
                        email: userEmail,
                        thread_id: localStorage.getItem('Thread_id')
                    },
                    success: function(response) {
                        var capitalizedUsername = Username.charAt(0).toUpperCase() + Username.slice(1);
                        jQuery('#ai-chat-window').append('<div class="main-user-query"><p class="user-query"><strong></strong> ' + userQuery + '</p></div>');
                        jQuery('#ai-chat-window').append('<div class="main-ai-response"><p class="ai-response">Welcome ' + capitalizedUsername + '!  How can I assist you today?</p></div>');
                    },
                    error: function(xhr, status, error) {
                        console.error('Error initializing chat data:', error);
                        jQuery('#ai-chat-window').append('<div class="main-user-query"><p class="user-query"><strong></strong> ' + userQuery + '</p></div>');
                        jQuery('#ai-chat-window').append('<div class="main-ai-response"><p class="ai-response">Please try again later </p></div>');
                        username_email_status = false;
                    }
                });
                username_email_status = false;
                jQuery('#ai-chat-input').val('');
            }
        } else {
            if (!userQuery == "") {
                jQuery('#ai-chat-window').append('<div class="main-user-query"><p class="user-query"><strong></strong> ' + userQuery + '</p></div>');
                jQuery('#ai-chat-window').append('<div class="main-ai-response"><p class="ai-response">Please share your name and email address. (e.g., example@user.com)</p></div>');
                jQuery('#ai-chat-input').val('');
                username_email_status = false;
            }
        }


    } else {
        username_email_status = true;
    }



    // var username = localStorage.setItem()
    if (userQuery !== '' && username_email_status == true) {
        // Disable input field and send button during API request
        jQuery('#ai-chat-input').prop('disabled', true);
        jQuery('#ai-chat-input-form button').prop('disabled', true);

        jQuery('#ai-chat-window').append('<div class="main-user-query"><p class="user-query"><strong></strong> ' + userQuery + '</p></div>');
        jQuery('#ai-chat-window').append('<div class="main-ai-response current_request"><div class="loading"><div></div></div>');

        // Save user query and thread ID
        jQuery.ajax({
            type: 'POST',
            url: customChatbot.ajaxurl, 
            data: {
                action: 'ccf_save_url_query',
                user_query: userQuery,
                thread_id: localStorage.getItem('Thread_id'),
                role: 'user'
            },
            error: function(xhr, status, error) {
                console.error('Error saving query:', error);
            }
        });
        // Assuming 'chat-container' is your main container for chat messages
        var chatContainer = document.getElementById('ai-chat-window');
        var currentRequest = chatContainer.querySelector('.current_request');
        if (currentRequest) {
            //console.log('scroll')
            // Scrolls the chat container to the current request message
            chatContainer.scrollTop = currentRequest.offsetTop;
        }

        jQuery.ajax({
            type: 'POST',
            url: customChatbot.ajaxurl,
            data: {
                action: 'ccf_chatbot_callback',
                user_name: localStorage.getItem("Chatbot_Username"),
                user_query: userQuery,
                thread_id: localStorage.getItem('Thread_id'), // Include the 'Thread_id' from local storage
            },
            success: function (response) {
                if (response.success) {
                    // Display the response in the chat window
                    //console.log(response);
                    function modifyResponse(responseData) {
                        // Replace ** with <strong> tags
                        responseData = responseData.replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>");
                        // Remove &#8203;``【oaicite:1】``&#8203;
                        responseData = responseData.replace(/【\d+†source】/g, "");
                        responseData = responseData.replace(/\[[^\]]+\]/g, "");
                        responseData = responseData.replace(/(?:\(|\s)(https?:\/\/[^\s\)]+)\)?/g, ' <a href="$1">$1</a>');
                        return responseData;
                    }

                    jQuery.ajax({
                        type: 'POST',
                        url: customChatbot.ajaxurl, 
                        data: {
                            action: 'ccf_save_url_query',
                            user_query: modifyResponse(response.data),
                            thread_id: localStorage.getItem('Thread_id'),
                            role: 'assistant'
                        },
                        error: function(xhr, status, error) {
                            console.error('Error saving query:', error);
                        }
                    });


                   
                    jQuery('#ai-chat-window .main-ai-response.current_request').html('<pre class="ai-response"><strong></strong> ' + modifyResponse(response.data) + '</pre>').removeClass("current_request");
                    // Clear the input field
                    jQuery('#ai-chat-input').val('');
                } else {
                    // Handle the failure gracefully
                    jQuery('#ai-chat-window .main-ai-response.current_request').html('<pre class="ai-response">' + (response.data.userMSG || 'Unknown error') + '</pre>').removeClass("current_request");
                    // Clear the input field
                    jQuery('#ai-chat-input').val('');
                    // Log the admin message for debugging
                    console.error(response.data.AdminMSG || 'No admin message provided');
                }

            },
            error: function (error) {
                console.log(error);
            },
            complete: function () {
                // Enable input field and send button after API request is complete
                jQuery('#ai-chat-input').prop('disabled', false);
                jQuery('#ai-chat-input-form button').prop('disabled', false);
                jQuery('.rise-fc-chatbot').removeClass('editingbro');
            }
        });
    }
});

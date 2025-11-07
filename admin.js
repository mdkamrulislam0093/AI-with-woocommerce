jQuery(document).ready(function($) {
    $('#aigc_submit').click(function() {
        var prompt = $('#aigc_prompt').val();
        $('#aigc_result').html('Loading...');

        $.ajax({
            url: aigc_ajax.ajax_url,
            type: "POST",
            data: {
                action: 'aigc_generate_content',
                prompt: prompt,
                nonce: aigc_ajax.nonce,
            },
            timeout: 120000,
            success: function(response) {
                console.log(response);
                if (response.success) {

                    // var ai_text_data = response['data']['text'] ?? '';
                    var ai_img_url = response['data']['image_url'] ?? '';

                    if ( ai_img_url.length > 0 ) {
                        var img_html = '<img src="'+ ai_img_url +'"/>';
                        // var ai_text = '<h2>'+ ai_text_data +'</h2>';
                        $('#aigc_result').html('<div>'+ img_html +'</div>');
                    }

                } else {
                    $('#aigc_result').html('<span style="color:red;">' + response.data + '</span>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                if (textStatus === "timeout") {
                    console.log("Request timed out");
                } else {
                    console.log("Error:", textStatus, errorThrown);
                }
            }
        });
    });
});

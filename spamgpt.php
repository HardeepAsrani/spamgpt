<?php
/*
Plugin Name: SpamGPT
Plugin URI: https://github.com/HardeepAsrani/spamgpt
Description: A plugin that checks new comments for spam using ChatGPT API.
Version: 1.0
Author: Hardeep Asrani
Author URI: https://hardeepasrani.com/
*/

define( 'SPAMGPT_VERSION', '1.0' );
define( 'SPAMGPT_API_KEY', 'Bearer API_TOKEN HERE' );

function spamgpt_spam_checker( $comment_id, $comment_approved ) {
	$is_spam = $comment_approved === 'spam';

	// get the comment data
	$comment = get_comment( $comment_id );
	// get the comment author's email
	$comment_author_email = $comment->comment_author_email;
	// check if the comment author has already approved comments
	$previous_comments = get_comments( array( 'author_email' => $comment_author_email ) );
	$approved_comments = wp_list_filter( $previous_comments, array( 'comment_approved' => 1 ) );

	if ( empty( $approved_comments ) ) {
		// if the author has not approved any comments yet, send a request to the ChatGPT API to check if the comment is spam
		$request_url = 'https://api.openai.com/v1/completions';
		$response = wp_remote_post( $request_url, array(
			'method'      => 'POST',
			'headers'     => array(
				'Content-Type' => 'application/json',
				'Authorization' => SPAMGPT_API_KEY
			),
			'body'        => json_encode(
				array(
					"model" => "text-davinci-003",
					"prompt" => "I would like you to check a comment for spam. Please analyze the comment and let me know on a scale of 1-5, with 5 being most probable for spam, if it contains any spam or potentially harmful content. Only return the number without any text. This is the comment: '' .  $comment->comment_content . ''",
					"max_tokens" => 5,
					"temperature" => 0
				)
			)
		) );
			
		if ( is_wp_error( $response ) ) {
			error_log( 'ChatGPT API request failed: ' . $response->get_error_message() );
		} else {
			$response_data = json_decode( $response['body'], true );

			if ( isset( $response_data['choices'][0]['text'] ) && is_numeric( $response_data['choices'][0]['text'] ) ) {
				// If the 'text' key exists in the response and it's numeric, extract the rating
				$rating = intval( $response_data['choices'][0]['text'] );
				
				if ( $rating === 5 ) {
					// If the rating is 5, tag the comment as spam
					wp_set_comment_status( $comment_id, 'spam' );
				} else if ( $rating < 3 && false === $is_spam ) {
					// If the rating is less than 3, approve the comment instantly
					wp_set_comment_status( $comment_id, 'approve' );
				} else {
					// If the rating is between 3 and 5 (exclusive), put the comment on hold
					wp_set_comment_status( $comment_id, 'hold' );
				}
			} else {
				// Handle error if the 'text' key does not exist or is not numeric
				error_log( 'ChatGPT API response is invalid: ' . $response['body'] );
			}
		}
	}
}

add_action( 'comment_post', 'spamgpt_spam_checker', 10, 2 );

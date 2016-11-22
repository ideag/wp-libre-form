<?php

/**
 * Ajax handler for the form submissions
 */
add_action( 'wp_ajax_wplf_submit', 'wplf_ajax_submit_handler' );
add_action( 'wp_ajax_nopriv_wplf_submit', 'wplf_ajax_submit_handler' );
function wplf_ajax_submit_handler() {
  $return = new stdClass();
  $return->ok = 1;

  // allow user to pre-process the post fields
  do_action('wplf_pre_validate_submission');

  // validate form fields
  // @see: wplf-form-validation.php
  $return = apply_filters( 'wplf_validate_submission', $return );

  if( $return->ok ) {
    // form existence has already been validated via filters

    // copy $_POST to $form_data in order to undo WordPress' magic quotes
    $form_data = stripslashes_deep($_POST);

    // Make form data filterable
    $form_data = apply_filters( 'wplf_form_data', $form_data );

    $form = get_post( intval( $form_data['_form_id'] ) );

    // the title is the value of whatever the first field was in the form
    $title_format = get_post_meta( $form->ID, '_wplf_title_format', true );

    // substitute the %..% tags with field values
    $post_title = $title_format;

    $wplf = WP_Libre_Form::init();
    $post_title = $wplf->substitute($post_title, $form_data);

    // create submission post
    $post_id = wp_insert_post( array(
      'post_title'     => $post_title,
      'post_status'    => 'publish',
      'post_type'      => 'wplf-submission',
    ));

    // add submission data as meta values
    foreach( $form_data as $key => $value ) {
      if ( $slash = is_array($value ) )
        $value = json_encode($value);

      // Escape slashes for add_post_meta (https://codex.wordpress.org/Function_Reference/update_post_meta#Character_Escaping)
      $value = wp_slash($value);

      add_post_meta($post_id, $key, $value, true);
    }

    // handle files
    foreach( $_FILES as $key => $file) {
      // Is this enough security wise?
      // Currenly only supports 1 file per input
      $attach_id = media_handle_upload( $key, 0, array(), array( "test_form" => false ) );
      add_post_meta( $post_id, $key, wp_get_attachment_url($attach_id) );
      add_post_meta( $post_id, $key . "_attachment", $attach_id );
    }



    $return->submission_id = $post_id;
    $return->submission_title = $post_title;
    $return->form_id = $form->ID;

    // return the success message for the form
    $return->success = apply_filters( 'the_content', get_post_meta( $form->ID, '_wplf_thank_you', true ) );

    // allow user to attach custom actions after the submission has been received
    // these could be confirmation emails, additional processing for the submission fields, e.g.
    do_action('wplf_post_validate_submission', $return);

  }

  // respond with json
  wp_send_json( $return );
  wp_die();
}

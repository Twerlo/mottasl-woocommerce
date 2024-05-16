<?php
add_action('woocommerce_register_form', 'misha_add_register_form_field');
function misha_add_register_form_field()
{

    woocommerce_form_field(
        'phone_number',
        array(
            'type' => 'tel',
            'required' => true, // just adds an "*"
            'label' => 'Phone number'
        ),
        (isset($_POST['phone_number']) ? $_POST['phone_number'] : '')
    );
}

// save to database
add_action('woocommerce_created_customer', 'misha_save_register_fields');
function misha_save_register_fields($customer_id)
{

    if (isset($_POST['phone_number']))
    {
        update_user_meta($customer_id, 'phone_number', wc_clean($_POST['phone_number']));
    }

}
add_action('wp_enqueue_scripts', 'misha_add_phone_mask');
function misha_add_phone_mask()
{

    wp_enqueue_style('intltelinput', 'intlTelInput.min.css');
    wp_enqueue_script('intltelinput', 'intlTelInput-jquery.min.js', 'jquery');

}
add_action('woocommerce_edit_account_form', 'misha_add_field_edit_account_form');
// or add_action( 'woocommerce_edit_account_form_start', 'misha_add_field_edit_account_form' );
function misha_add_field_edit_account_form()
{

    woocommerce_form_field(
        'phone_number',
        array(
            'type' => 'tel',
            'required' => true, // remember, this doesn't make the field required, just adds an "*"
            'label' => 'Phone number'
        ),
        get_user_meta(get_current_user_id(), 'phone_number', true) // get the data
    );

}
// Save field value
add_action('woocommerce_save_account_details', 'misha_save_account_details');
function misha_save_account_details($user_id)
{

    update_user_meta($user_id, 'phone_number', wc_clean($_POST['phone_number']));

}
// Make it required
add_filter('woocommerce_save_account_details_required_fields', 'misha_make_field_required');
function misha_make_field_required($required_fields)
{

    $required_fields['phone_number'] = 'Phone number';
    return $required_fields;

}
add_action('wp_footer', 'misha_init_phone_mask');
function misha_init_phone_mask()
{

    ?>
    <script>
        jQuery( function ( $ ) {
            $( '#phone_number' ).intlTelInput( {
                preferredCountries: [ 'no', 'ge' ],
                nationalMode: false,
                utilsScript: "utils.js" // just for formatting/placeholders etc
            } );
        } );
    </script>
    <?php
}
add_action('woocommerce_register_post', 'misha_validate_fields', 10, 3);
function misha_validate_fields($username, $email, $errors)
{

    if (empty($_POST['phone_number']))
    {
        $errors->add('phone_number_error', 'We really want to know!');
    }

}
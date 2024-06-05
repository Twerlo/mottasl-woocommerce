<?php

  $countries = array(
    '+93' => 'AF (+93)',
    '+355' => 'AL (+355)',
    '+213' => 'DZ (+213)',
    '+1' => 'AS (+1)',
    '+376' => 'AD (+376)',
    '+244' => 'AO (+244)',
    '+1' => 'AI (+1)',
    '+1' => 'AG (+1)',
    '+54' => 'AR (+54)',
    '+374' => 'AM (+374)',
    '+297' => 'AW (+297)',
    '+61' => 'AU (+61)',
    '+43' => 'AT (+43)',
    '+994' => 'AZ (+994)',
    '+1' => 'BS (+1)',
    '+973' => 'BH (+973)',
    '+880' => 'BD (+880)',
    '+1' => 'BB (+1)',
    '+375' => 'BY (+375)',
    '+32' => 'BE (+32)',
    '+501' => 'BZ (+501)',
    '+229' => 'BJ (+229)',
    '+1' => 'BM (+1)',
    '+975' => 'BT (+975)',
    '+591' => 'BO (+591)',
    '+387' => 'BA (+387)',
    '+267' => 'BW (+267)',
    '+55' => 'BR (+55)',
    '+246' => 'IO (+246)',
    '+1' => 'VG (+1)',
    '+673' => 'BN (+673)',
    '+359' => 'BG (+359)',
    '+226' => 'BF (+226)',
    '+257' => 'BI (+257)',
    '+855' => 'KH (+855)',
    '+237' => 'CM (+237)',
    '+1' => 'CA (+1)',
    '+238' => 'CV (+238)',
    '+1' => 'KY (+1)',
    '+236' => 'CF (+236)',
    '+235' => 'TD (+235)',
    '+56' => 'CL (+56)',
    '+86' => 'CN (+86)',
    '+61' => 'CX (+61)',
    '+57' => 'CO (+57)',
    '+269' => 'KM (+269)',
    '+682' => 'CK (+682)',
    '+506' => 'CR (+506)',
    '+385' => 'HR (+385)',
    '+53' => 'CU (+53)',
    '+599' => 'CW (+599)',
    '+357' => 'CY (+357)',
    '+420' => 'CZ (+420)',
    '+243' => 'CD (+243)',
    '+45' => 'DK (+45)',
    '+253' => 'DJ (+253)',
    '+1' => 'DM (+1)',
    '+1' => 'DO (+1)',
    '+670' => 'TL (+670)',
    '+593' => 'EC (+593)',
    '+20' => 'EG (+20)',
    '+503' => 'SV (+503)',
    '+240' => 'GQ (+240)',
    '+291' => 'ER (+291)',
    '+372' => 'EE (+372)',
    '+251' => 'ET (+251)',
    '+500' => 'FK (+500)',
    '+298' => 'FO (+298)',
    '+679' => 'FJ (+679)',
    '+358' => 'FI (+358)',
    '+33' => 'FR (+33)',
    '+689' => 'PF (+689)',
    '+241' => 'GA (+241)',
    '+220' => 'GM (+220)',
    '+995' => 'GE (+995)',
    '+49' => 'DE (+49)',
    '+233' => 'GH (+233)',
    '+350' => 'GI (+350)',
    '+30' => 'GR (+30)',
    '+299' => 'GL (+299)',
    '+1' => 'GD (+1)',
    '+502' => 'GT (+502)',
    '+44' => 'GG (+44)',
    '+224' => 'GN (+224)',
    '+245' => 'GW (+245)',
    '+592' => 'GY (+592)',
    '+509' => 'HT (+509)',
    '+504' => 'HN (+504)',
    '+852' => 'HK (+852)',
    '+36' => 'HU (+36)',
    '+354' => 'IS (+354)',
    '+91' => 'IN (+91)',
    '+62' => 'ID (+62)',
    '+98' => 'IR (+98)',
    '+964' => 'IQ (+964)',
    '+353' => 'IE (+353)',
    '+44' => 'IM (+44)',
    '+972' => 'IL (+972)',
    '+39' => 'IT (+39)',
    '+225' => 'CI (+225)',
    '+1' => 'JM (+1)',
    '+81' => 'JP (+81)',
    '+44' => 'JE (+44)',
    '+962' => 'JO (+962)',
    '+7' => 'KZ (+7)',
    '+254' => 'KE (+254)',
    '+686' => 'KI (+686)',
    '+965' => 'KW (+965)',
    '+996' => 'KG (+996)',
    '+856' => 'LA (+856)',
    '+371' => 'LV (+371)',
    '+961' => 'LB (+961)',
    '+266' => 'LS (+266)',
    '+231' => 'LR (+231)',
    '+218' => 'LY (+218)',
    '+423' => 'LI (+423)',
    '+370' => 'LT (+370)',
    '+352' => 'LU (+352)',
    '+853' => 'MO (+853)',
    '+389' => 'MK (+389)',
    '+261' => 'MG (+261)',
    '+265' => 'MW (+265)',
    '+60' => 'MY (+60)',
    '+960' => 'MV (+960)',
    '+223' => 'ML (+223)',
    '+356' => 'MT (+356)',
    '+692' => 'MH (+692)',
    '+222' => 'MR (+222)',
    '+230' => 'MU (+230)',
    '+262' => 'YT (+262)',
    '+52' => 'MX (+52)',
    '+691' => 'FM (+691)',
    '+373' => 'MD (+373)',
    '+377' => 'MC (+377)',
    '+976' => 'MN (+976)',
    '+382' => 'ME (+382)',
    '+1' => 'MS (+1)',
    '+212' => 'MA (+212)',
    '+258' => 'MZ (+258)',
    '+95' => 'MM (+95)',
    '+264' => 'NA (+264)',
    '+674' => 'NR (+674)',
    '+977' => 'NP (+977)',
    '+31' => 'NL (+31)',
    '+687' => 'NC (+687)',
    '+64' => 'NZ (+64)',
    '+505' => 'NI (+505)',
    '+227' => 'NE (+227)',
    '+234' => 'NG (+234)',
    '+683' => 'NU (+683)',
  );

add_action('woocommerce_register_form', 'misha_add_register_form_field');
function misha_add_register_form_field()
{
   		require_once plugin_dir_path(__FILE__) . 'countries.php';

  woocommerce_form_field(
        'phone_code',
        array(
            'type' => 'select',
            'required' => true, // just adds an "*"
            'label' => 'country code',
            'options'=>$countries
        ),
        (isset($_POST['phone_code']) ? $_POST['phone_code'] : '')
    );

    woocommerce_form_field(
        'phone_number',
        array(
            'type' => 'number',
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
    if (isset($_POST['phone_code']))
    {
        update_user_meta($customer_id, 'phone_code', wc_clean($_POST['phone_code']));
    }

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
  
   		require_once plugin_dir_path(__FILE__) . 'countries.php';


        woocommerce_form_field(
        'phone_code',
        array(
            'type' => 'select',
            'required' => true, // remember, this doesn't make the field required, just adds an "*"
            'label' => 'Country Code',
            'class' => array('form-row-wide'),
            'options' =>$countries
        ),
        get_user_meta(get_current_user_id(), 'phone_code', true) // get the data
    );

    woocommerce_form_field(
        'phone_number',
        array(
            'type' => 'number',
            'required' => true, // remember, this doesn't make the field required, just adds an "*"
            'class' => array('form-row-wide'),
            'label' => 'Phone number',
        ),
        get_user_meta(get_current_user_id(), 'phone_number', true) // get the data
    );


}
// Save field value
add_action('woocommerce_save_account_details', 'misha_save_account_details');
function misha_save_account_details($user_id)
{

    update_user_meta($user_id, 'phone_code', wc_clean($_POST['phone_code']));
    update_user_meta($user_id, 'phone_number', wc_clean($_POST['phone_number']));
}
// Make it required
add_filter('woocommerce_save_account_details_required_fields', 'misha_make_field_required');
function misha_make_field_required($required_fields)
{

    $required_fields['phone_number'] = 'Phone number';
        $required_fields['phone_code'] = 'country code';

    return $required_fields;

}
add_action('wp_footer', 'misha_init_phone_mask');
function misha_init_phone_mask()
{

    ?>
    <script>
        jQuery( function ( $ ) {
            $( '#phone_number' ).intlTelInput( {
                preferredCountries: [ 'eg', 'ge' ],
                nationalMode: yes,
                utilsScript: "utils.js" // just for formatting/placeholders etc
            } );
             $( '#phone_code' ).intlTelInput( {
                preferredCountries: [ 'eg', 'ge' ],
                nationalMode: yes,
                utilsScript: "utils.js" // just for formatting/placeholders etc
            } );
        } );

    </script>
    <?php
}
add_action('woocommerce_register_post', 'misha_validate_fields', 10, 3);
function misha_validate_fields($username, $email, $errors)
{
    if (empty($_POST['phone_code']))
   {
       $errors->add('phone_code_error', 'add a valid country code!');
   }

    if (empty($_POST['phone_number']))
    {
        $errors->add('phone_number_error', 'Please add a valid phone number!');
    }

}
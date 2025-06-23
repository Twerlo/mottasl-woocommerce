<?php

namespace Mottasl\Utils;

/**
 * Helpers class.
 *
 * This class contains utility functions for the Mottasl plugin.
 *
 * @package Mottasl\Utils
 * @since 1.0.0
 */
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}
class Helpers
{
	/**
	 * Get the countries list.
	 *
	 * @return array
	 */
	public static function getCountries()
	{
		return [
			'+44' => 'UK (+44)',
			'+1' => 'USA (+1)',
			'+213' => 'Algeria (+213)',
			'+376' => 'Andorra (+376)',
			'+244' => 'Angola (+244)',
			'+1264' => 'Anguilla (+1264)',
			'+1268' => 'Antigua & Barbuda (+1268)',
			'+54' => 'Argentina (+54)',
			'+374' => 'Armenia (+374)',
			'+297' => 'Aruba (+297)',
			'+61' => 'Australia (+61)',
			'+43' => 'Austria (+43)',
			'+994' => 'Azerbaijan (+994)',
			'+1242' => 'Bahamas (+1242)',
			'+973' => 'Bahrain (+973)',
			'+880' => 'Bangladesh (+880)',
			'+1246' => 'Barbados (+1246)',
			'+375' => 'Belarus (+375)',
			'+32' => 'Belgium (+32)',
			'+501' => 'Belize (+501)',
			'+229' => 'Benin (+229)',
			'+1441' => 'Bermuda (+1441)',
			'+975' => 'Bhutan (+975)',
			'+591' => 'Bolivia (+591)',
			'+387' => 'Bosnia Herzegovina (+387)',
			'+267' => 'Botswana (+267)',
			'+55' => 'Brazil (+55)',
			'+673' => 'Brunei (+673)',
			'+359' => 'Bulgaria (+359)',
			'+226' => 'Burkina Faso (+226)',
			'+257' => 'Burundi (+257)',
			'+855' => 'Cambodia (+855)',
			'+237' => 'Cameroon (+237)',
			'+1' => 'Canada (+1)',
			'+238' => 'Cape Verde Islands (+238)',
			'+1345' => 'Cayman Islands (+1345)',
			'+236' => 'Central African Republic (+236)',
			'+56' => 'Chile (+56)',
			'+86' => 'China (+86)',
			'+57' => 'Colombia (+57)',
			'+269' => 'Comoros (+269)',
			'+242' => 'Congo (+242)',
			'+682' => 'Cook Islands (+682)',
			'+506' => 'Costa Rica (+506)',
			'+385' => 'Croatia (+385)',
			'+53' => 'Cuba (+53)',
			'+90392' => 'Cyprus North (+90392)',
			'+357' => 'Cyprus South (+357)',
			'+42' => 'Czech Republic (+42)',
			'+45' => 'Denmark (+45)',
			'+253' => 'Djibouti (+253)',
			'+1809' => 'Dominica (+1809)',
			'+1809' => 'Dominican Republic (+1809)',
			'+593' => 'Ecuador (+593)',
			'+20' => 'Egypt (+20)',
			'+503' => 'El Salvador (+503)',
			'+240' => 'Equatorial Guinea (+240)',
			'+291' => 'Eritrea (+291)',
			'+372' => 'Estonia (+372)',
			'+251' => 'Ethiopia (+251)',
			'+500' => 'Falkland Islands (+500)',
			'+298' => 'Faroe Islands (+298)',
			'+679' => 'Fiji (+679)',
			'+358' => 'Finland (+358)',
			'+33' => 'France (+33)',
			'+594' => 'French Guiana (+594)',
			'+689' => 'French Polynesia (+689)',
			'+241' => 'Gabon (+241)',
			'+220' => 'Gambia (+220)',
			'+7880' => 'Georgia (+7880)',
			'+49' => 'Germany (+49)',
			'+233' => 'Ghana (+233)',
			'+350' => 'Gibraltar (+350)',
			'+30' => 'Greece (+30)',
			'+299' => 'Greenland (+299)',
			'+1473' => 'Grenada (+1473)',
			'+590' => 'Guadeloupe (+590)',
			'+671' => 'Guam (+671)',
			'+502' => 'Guatemala (+502)',
			'+224' => 'Guinea (+224)',
			'+245' => 'Guinea - Bissau (+245)',
			'+592' => 'Guyana (+592)',
			'+509' => 'Haiti (+509)',
			'+504' => 'Honduras (+504)',
			'+852' => 'Hong Kong (+852)',
			'+36' => 'Hungary (+36)',
			'+354' => 'Iceland (+354)',
			'+91' => 'India (+91)',
			'+62' => 'Indonesia (+62)',
			'+98' => 'Iran (+98)',
			'+964' => 'Iraq (+964)',
			'+353' => 'Ireland (+353)',
			'+972' => 'Israel (+972)',
			'+39' => 'Italy (+39)',
			'+1876' => 'Jamaica (+1876)',
			'+81' => 'Japan (+81)',
			'+962' => 'Jordan (+962)',
			'+7' => 'Kazakhstan (+7)',
			'+254' => 'Kenya (+254)',
			'+686' => 'Kiribati (+686)',
			'+850' => 'Korea North (+850)',
			'+82' => 'Korea South (+82)',
			'+965' => 'Kuwait (+965)',
			'+996' => 'Kyrgyzstan (+996)',
			'+856' => 'Laos (+856)',
			'+371' => 'Latvia (+371)',
			'+961' => 'Lebanon (+961)',
			'+266' => 'Lesotho (+266)',
			'+231' => 'Liberia (+231)',
			'+218' => 'Libya (+218)',
			'+417' => 'Liechtenstein (+417)',
			'+370' => 'Lithuania (+370)',
			'+352' => 'Luxembourg (+352)',
			'+853' => 'Macao (+853)',
			'+389' => 'Macedonia (+389)',
			'+261' => 'Madagascar (+261)',
			'+265' => 'Malawi (+265)',
			'+60' => 'Malaysia (+60)',
			'+960' => 'Maldives (+960)',
			'+223' => 'Mali (+223)',
			'+356' => 'Malta (+356)',
			'+692' => 'Marshall Islands (+692)',
			'+596' => 'Martinique (+596)',
			'+222' => 'Mauritania (+222)',
			'+269' => 'Mayotte (+269)',
			'+52' => 'Mexico (+52)',
			'+691' => 'Micronesia (+691)',
			'+373' => 'Moldova (+373)',
			'+377' => 'Monaco (+377)',
			'+976' => 'Mongolia (+976)',
			'+1664' => 'Montserrat (+1664)',
			'+212' => 'Morocco (+212)',
			'+258' => 'Mozambique (+258)',
			'+95' => 'Myanmar (+95)',
			'+264' => 'Namibia (+264)',
			'+674' => 'Nauru (+674)',
			'+977' => 'Nepal (+977)',
			'+31' => 'Netherlands (+31)',
			'+687' => 'New Caledonia (+687)',
			'+64' => 'New Zealand (+64)',
			'+505' => 'Nicaragua (+505)',
			'+227' => 'Niger (+227)',
			'+234' => 'Nigeria (+234)',
			'+683' => 'Niue (+683)',
			'+672' => 'Norfolk Islands (+672)',
			'+670' => 'Northern Marianas (+670)',
			'+47' => 'Norway (+47)',
			'+968' => 'Oman (+968)',
			'+680' => 'Palau (+680)',
			'+507' => 'Panama (+507)',
			'+675' => 'Papua New Guinea (+675)',
			'+595' => 'Paraguay (+595)',
			'+51' => 'Peru (+51)',
			'+63' => 'Philippines (+63)',
			'+48' => 'Poland (+48)',
			'+351' => 'Portugal (+351)',
			'+1787' => 'Puerto Rico (+1787)',
			'+974' => 'Qatar (+974)',
			'+262' => 'Reunion (+262)',
			'+40' => 'Romania (+40)',
			'+7' => 'Russia (+7)',
			'+250' => 'Rwanda (+250)',
			'+378' => 'San Marino (+378)',
			'+239' => 'Sao Tome & Principe (+239)',
			'+966' => 'Saudi Arabia (+966)',
			'+221' => 'Senegal (+221)',
			'+381' => 'Serbia (+381)',
			'+248' => 'Seychelles (+248)',
			'+232' => 'Sierra Leone (+232)',
			'+65' => 'Singapore (+65)',
			'+421' => 'Slovak Republic (+421)',
			'+386' => 'Slovenia (+386)',
			'+677' => 'Solomon Islands (+677)',
			'+252' => 'Somalia (+252)',
			'+27' => 'South Africa (+27)',
			'+34' => 'Spain (+34)',
			'+94' => 'Sri Lanka (+94)',
			'+290' => 'St'
		];
	}

	// register actions
	public static function registerActions()
	{
		add_action('woocommerce_register_form', 'misha_add_register_form_field');
		add_action('woocommerce_created_customer', 'misha_save_register_fields');
		add_action('wp_enqueue_scripts', 'misha_add_phone_mask');
		add_action('woocommerce_edit_account_form', 'misha_add_field_edit_account_form');
		add_action('woocommerce_save_account_details', 'misha_save_account_details');
		add_filter('woocommerce_save_account_details_required_fields', 'misha_make_field_required');
		add_action('wp_footer', 'misha_init_phone_mask');
		add_action('woocommerce_register_post', 'misha_validate_fields', 10, 3);
	}
	function misha_add_register_form_field()
	{
		require_once plugin_dir_path(__FILE__) . 'countries.php';

		woocommerce_form_field(
			'phone_code',
			array(
				'type' => 'select',
				'required' => true, // just adds an "*"
				'label' => 'country code',
				'options' => $countries
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
	function misha_save_register_fields($customer_id)
	{
		if (isset($_POST['phone_code'])) {
			update_user_meta($customer_id, 'phone_code', wc_clean($_POST['phone_code']));
		}

		if (isset($_POST['phone_number'])) {
			update_user_meta($customer_id, 'phone_number', wc_clean($_POST['phone_number']));
		}
	}
	function misha_add_phone_mask()
	{

		wp_enqueue_style('intltelinput', 'intlTelInput.min.css');
		wp_enqueue_script('intltelinput', 'intlTelInput-jquery.min.js', 'jquery');
	}
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
				'options' => $countries
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
	function misha_save_account_details($user_id)
	{

		update_user_meta($user_id, 'phone_code', wc_clean($_POST['phone_code']));
		update_user_meta($user_id, 'phone_number', wc_clean($_POST['phone_number']));
	}
	function misha_make_field_required($required_fields)
	{

		$required_fields['phone_number'] = 'Phone number';
		$required_fields['phone_code'] = 'country code';

		return $required_fields;
	}
	function misha_init_phone_mask()
	{

?>
		<script>
			jQuery(function($) {
				$('#phone_number').intlTelInput({
					preferredCountries: ['eg', 'ge'],
					nationalMode: yes,
					utilsScript: "utils.js" // just for formatting/placeholders etc
				});
				$('#phone_code').intlTelInput({
					preferredCountries: ['eg', 'ge'],
					nationalMode: yes,
					utilsScript: "utils.js" // just for formatting/placeholders etc
				});
			});
		</script>
<?php
	}

	function misha_validate_fields($username, $email, $errors)
	{
		if (empty($_POST['phone_code'])) {
			$errors->add('phone_code_error', 'add a valid country code!');
		}

		if (empty($_POST['phone_number'])) {
			$errors->add('phone_number_error', 'Please add a valid phone number!');
		}
	}
}

<?php

/**
 * Copyright 2016 - Eighty / 20 Results by Wicked Strong Chicks, LLC (thomas@eighty20results.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

class e20rMyCredPmproIntegrationTests extends WP_UnitTestCase {

	private $user_id;
	/**
	 * @var MemberOrder
	 */
	private $order;

	public function setUp() {

		$this->user_id = $this->factory->user->create( array( 'user_login' => 'pmpro-user' ) );
		error_log("Created user with ID {$this->user_id}");
	}

	function getEmptyMemberOrder()
	{

		//defaults
		$order = new stdClass();
		$order->code = $this->getRandomCode();
		$order->user_id = "";
		$order->membership_id = "";
		$order->subtotal = "";
		$order->tax = "";
		$order->couponamount = "";
		$order->total = "";
		$order->payment_type = "";
		$order->cardtype = "";
		$order->accountnumber = "";
		$order->expirationmonth = "";
		$order->expirationyear = "";
		$order->status = "success";
		$order->gateway = null;
		$order->gateway_environment = 'sandbox';
		$order->payment_transaction_id = "";
		$order->subscription_transaction_id = "";
		$order->affiliate_id = "";
		$order->affiliate_subid = "";
		$order->notes = "";
		$order->checkout_id = 0;

		$order->billing = new stdClass();
		$order->billing->name = "";
		$order->billing->street = "";
		$order->billing->city = "";
		$order->billing->state = "";
		$order->billing->zip = "";
		$order->billing->country = "";
		$order->billing->phone = "";

		return $order;
	}

	public function saveOrder( $order )
	{
		global $current_user, $wpdb;

		//get a random code to use for the public ID
		if(empty($order->code))
			$order->code = $this->getRandomCode();

		//figure out how much we charged
		if(!empty($order->InitialPayment))
			$amount = $order->InitialPayment;
		elseif(!empty($order->subtotal))
			$amount = $order->subtotal;
		else
			$amount = 0;

		//Todo: Tax?!, Coupons, Certificates, affiliates
		if(empty($order->subtotal))
			$order->subtotal = $amount;
		if(isset($order->tax))
			$tax = $order->tax;
		else
			$tax = null;
		$order->certificate_id = "";
		$order->certificateamount = "";

		//calculate total
		if(!empty($order->total))
			$total = $order->total;
		else {
			$total = (float)$amount + (float)$tax;
			$order->total = $total;
		}

		//these fix some warnings/notices
		if(empty($order->billing))
		{
			$order->billing = new stdClass();
			$order->billing->name = $this->billing->street = $order->billing->city = $order->billing->state = $order->billing->zip = $order->billing->country = $order->billing->phone = "";
		}
		if(empty($order->user_id))
			$order->user_id = 0;
		if(empty($order->paypal_token))
			$order->paypal_token = "";
		if(empty($order->couponamount))
			$order->couponamount = "";
		if(empty($order->payment_type))
			$order->payment_type = "";
		if(empty($order->payment_transaction_id))
			$order->payment_transaction_id = "";
		if(empty($order->subscription_transaction_id))
			$order->subscription_transaction_id = "";
		if(empty($order->affiliate_id))
			$order->affiliate_id = "";
		if(empty($order->affiliate_subid))
			$order->affiliate_subid = "";
		if(empty($order->session_id))
			$order->session_id = "";
		if(empty($order->accountnumber))
			$order->accountnumber = "";
		if(empty($order->cardtype))
			$order->cardtype = "";
		if(empty($order->ExpirationDate))
			$order->ExpirationDate = "";
		if (empty($order->status))
			$order->status = "";

		if(empty($order->gateway))
			$order->gateway = null;
		if(empty($order->gateway_environment))
			$order->gateway_environment = 'sandbox';

		if(empty($order->datetime) && empty($order->timestamp))
			$order->datetime = date_i18n("Y-m-d H:i:s", current_time("timestamp"));		//use current time
		elseif(empty($order->datetime) && !empty($order->timestamp) && is_numeric($order->timestamp))
			$order->datetime = date_i18n("Y-m-d H:i:s", $order->timestamp);	//get datetime from timestamp
		elseif(empty($order->datetime) && !empty($order->timestamp))
			$order->datetime = $order->timestamp;		//must have a datetime in it

		if(empty($order->notes))
			$order->notes = "";

		//build query
		if(!empty($order->id))
		{
			//set up actions
			$before_action = "pmpro_update_order";
			$after_action = "pmpro_updated_order";
		}
		else
		{
			//set up actions
			$before_action = "pmpro_add_order";
			$after_action = "pmpro_added_order";
			//insert
		}

		return $order;
	}

	public function generateTestOrder() {

		// Generate a valid pmpro Order to test against.
		$order = $this->getEmptyMemberOrder();

		$order->billing->name    = "Test user";
		$order->billing->street  = "Test Street #123";
		$order->billing->city    = "Testcity";
		$order->billing->state   = "Teststate";
		$order->billing->zip     = "12345";
		$order->billing->country = "US";
		$order->billing->phone   = "1234567890";

		$order->membership_id       = 1;
		$order->user_id             = $this->user_id;
		$order->subtotal            = "10.00";
		$order->total               = "10.00";
		$order->card_type           = "Visa";
		$order->accountnumber       = "XXXX-XXXX-XXXX-1234";
		$order->expirationmonth     = "12";
		$order->expirationyear      = "2021";
		$order->gateway_environment = "live";

		return $order;
	}

	public function getRandomCode()
	{
		if ( !defined('AUTH_KEY')) {
			define('AUTH_KEY', ':k!OF*}xfALJ=a#f`0PZz9X-}3&Wbk1<vAI0fx_lYvVIvvN:){RgYa}O38.4*YYa' );
		}

		if (!defined('SECURE_AUTH_KEY')) {
			define('SECURE_AUTH_KEY', '=:0|qKc#5Xj]&W*ZLw6,xWJ[qGQ,|b.$>W5k<7+Yq>M,RsV{Po=!`tY]0b_Qc*r.');
		}

		$scramble = md5(AUTH_KEY . current_time('timestamp') . SECURE_AUTH_KEY);
		$code = substr($scramble, 0, 10);

		return strtoupper($code);
	}

	public function testRenewal() {

		$this->order = $this->generateTestOrder();

		$class = e20rMyCredPmproIntegration::get_instance();

		$completed = $class->subscriptionPaymentComplete( $this->order );

		$this->assertNotNull( $completed );
	}
}

if ( !function_exists('mycred_exclude_user') ) {
	function mycred_exclude_user( $user_id ) {
		error_log("Testing access to myCred for {$user_id}");
		return true;
	}
}

if (!function_exists('mycred_add')) {
	function mycred_add( $level_message, $user_id, $score ) {
		error_log("User ID: {$user_id}, score: {$score}, Message: {$level_message}");
		return true;
	}
}

if ( !function_exists( 'mycred_add_new_notice' ) ) {
	function mycred_add_new_notice( $args ) {
		error_log("Setting new notice for ID: " . $args['user_id'] . ", message: '" . $args['message'] . "'" );
		return true;
	}
}

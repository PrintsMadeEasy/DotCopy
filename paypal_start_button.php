<?php

	require_once("library/Boot_Session.php");

	$continueUrl = WebUtil::GetInput("continueUrl", FILTER_SANITIZE_URL);
	$cancelUrl = WebUtil::GetInput("cancelUrl", FILTER_SANITIZE_URL);
	$shoppingCartSubtotal = WebUtil::GetInput("shoppingCartSubtotal", FILTER_SANITIZE_FLOAT);
	
	if(empty($continueUrl))
		throw new Exception("Continue URL parameter left blank.");
	if(empty($cancelUrl))
		throw new Exception("Cancel URL parameter left blank.");
	if(empty($shoppingCartSubtotal))
		throw new Exception("Shopping Cart subtotal left blank.");
	
	// Add extra to the shopping cart total for S&H (to be determined later).
	$preScreenAmount = $shoppingCartSubtotal + 100;

	if(Constants::GetDevelopmentServer()) {
		$tokenUrlBase = "http://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=";
		$baseUrl 	  = "http://localhost/dot/";
	} 
	else{
		$tokenUrlBase = "http://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=";
		$baseUrl 	  = "http://" . Domain::getWebsiteURLforDomainID(Domain::getDomainIDfromURL()) . "/";
	}
	
	// We call the register check after returning from paypal, to check the email and decide if we show a new register form or sign in or if logged in checkout directly
	$returnUrl = $baseUrl . "paypal_register_check.php?continueUrl=" . urlencode($continueUrl);		
	$cancelUrl = $baseUrl . $cancelUrl;
	
	$payPalObj = new PayPalApiPro();
	
	$token = $payPalObj->getAuthToken($preScreenAmount, $returnUrl, $cancelUrl);
	
	if(!empty($token)) {
		header("Location: " . $tokenUrlBase . urlencode($token)); // Redirection to customer Paypal login and payment type selection
		exit;
	} 
	else {
		WebUtil::PrintError("Sorry, there was an error communicating with the Paypal System.  Please try again later or ask customer service for assistance.");
	}
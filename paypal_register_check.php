<?php
	require_once("library/Boot_Session.php");

	$paypalToken   = WebUtil::GetInput("token", FILTER_SANITIZE_STRING_ONE_LINE);
	$paypalPayerId = WebUtil::GetInput("PayerID", FILTER_SANITIZE_STRING_ONE_LINE);
	$continueUrl = WebUtil::GetInput("continueUrl", FILTER_SANITIZE_URL);
		
	$dbCmd = new DbCmd();
	
	if(!empty($paypalToken) && !empty($paypalPayerId)) {
	
		$payPalObj = new PayPalApiPro();	
		$customerData = $payPalObj->getExpressCheckoutDetails($paypalToken);
		
		$payerEmail   = $customerData->payerEmail;
		
		WebUtil::SetSessionVar("PaypalToken", $paypalToken);
		WebUtil::SetSessionVar("PaypalPayerID", $paypalPayerId);
		

		// Use the Shipping Address from the Paypal Callback
		$CheckoutParamsObj = new CheckoutParameters();
		$CheckoutParamsObj->SetShippingName($customerData->businessName);
		$CheckoutParamsObj->SetShippingCompany($customerData->business);
		$CheckoutParamsObj->SetShippingAddress($customerData->businessStreet1);
		$CheckoutParamsObj->SetShippingAddressTwo($customerData->businessStreet2);
		$CheckoutParamsObj->SetShippingCity($customerData->businessCityName);
		$CheckoutParamsObj->SetShippingState($customerData->businessStateOrProvince);
		$CheckoutParamsObj->SetShippingZip($customerData->businessZIP);
		$CheckoutParamsObj->SetShippingCountry("US");
		
		
		// If the user is already logged in... then we can transfer them to the checkout screen.
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		if($passiveAuthObj->CheckIfLoggedIn()){
			// Send the user to the checkout screen... or whatever was in the continue URL.
			header("Location: ". WebUtil::FilterURL($continueUrl));
			exit;
		}
		
		
		// If the Paypal address already exisits in our DB, then get the UserID that it belongs to and automatically log the person in.
		$UserControlObj = new UserControl($dbCmd);
		if($UserControlObj->CheckIfEmailExistsInDB($payerEmail)){
			
			$UserControlObj->LoadUserByEmail($payerEmail, Domain::oneDomain());

			Authenticate::SetUserIDLoggedIn($UserControlObj->getUserID());
			
			// Send the user to the checkout screen.
			header("Location: ". WebUtil::FilterURL($continueUrl));
			exit;
			
		}
		else{
			// Otherwise automatically create an account to save the user time.
			header("Location: register.php?registrationtype=paypal&transferaddress=" . urlencode($continueUrl)); 
			exit;
		}


	}
	else{
		WebUtil::PrintError("Sorry, there was an error communicating with Paypal.  Please report this problem to Customer Service.");
	}

	
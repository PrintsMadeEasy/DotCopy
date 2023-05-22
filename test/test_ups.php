
<?

require_once("library/Boot_Session.php");

	

$zip = WebUtil::GetInput("zip", FILTER_SANITIZE_STRING_ONE_LINE);
$city = WebUtil::GetInput("city", FILTER_SANITIZE_STRING_ONE_LINE);
$state = WebUtil::GetInput("state", FILTER_SANITIZE_STRING_ONE_LINE);

$fullname = WebUtil::GetInput("fullname", FILTER_SANITIZE_STRING_ONE_LINE);
$address = WebUtil::GetInput("address", FILTER_SANITIZE_STRING_ONE_LINE);
$city = WebUtil::GetInput("city", FILTER_SANITIZE_STRING_ONE_LINE);



if(!empty($city) && !empty($state) && !empty($zip) ){

	// Check if the address is valid with UPS ---##
	$upsResponseObj = UPS_AV::ValidateShippingAddress($city, $state, $zip);
	
	if($upsResponseObj->CheckIfCommunicationError() || $upsResponseObj->GetErrorCode() != ""){
		WebUtil::WebmasterError("Error with UPS Address Verification Server: " . $upsResponseObj->GetErrorDescription());
		WebUtil::PrintError(UPS_AV::GetUPSerrorMessageForCustomer());
	}


	if(!$upsResponseObj->CheckIfAddressIsOK()){
	
		// There may have been an address validation error but that doesn't necessarily mean that we got back any suggestions --#
		if($upsResponseObj->EmptySuggestions()){

			$t->set_var("UPS_SUGGESTIONS_1", "<font class='SmallBody'>Sorry, no suggestions were found.<br>If you are sure that your shipping address is correct, please contact customer service.</font>");
			$t->set_var("UPS_SUGGESTIONS_2", "");

		}
		else{
		
			// will contain a list of alternate suggestions.
			$ValidationResultsArr = $upsResponseObj->GetValidationResults();
			
			$suggestionHTML = "";

			// We are going to split the reults into 2 columns 
			$column_1 = '<img src="./images/transparent.gif" border="0" width="1" height="5"><br>';
			$column_2 = '<img src="./images/transparent.gif" border="0" width="1" height="5"><br>';
			$ColumnFlag = true;

			// Loop through all of UPS's suggestions 
			foreach($ValidationResultsArr as $ValidationObj){


				$tempStr = "<font class='reallysmallbody'>City:&nbsp;</font>&nbsp;&nbsp;" . $ValidationObj->City . "<br>";
				$tempStr .= "<font class='reallysmallbody'>State:</font> " . $ValidationObj->State . "<br>";

				if($ValidationObj->postalHigh == $ValidationObj->postalLow){
					$tempStr .= "<font class='reallysmallbody'>Zip:</font>&nbsp;&nbsp;&nbsp;" . $ValidationObj->postalHigh;
				}
				else{
					$tempStr .= "<font class='reallysmallbody'>Zip:</font>&nbsp;&nbsp;&nbsp;(" . $ValidationObj->postalLow . " - " . $ValidationObj->postalHigh. ")";
				}


				if($ColumnFlag){
					$column_1 .= $tempStr . "<br><img src='./images/line-soft-dotted-blue.gif' width='162' height='9'><br>";
					$ColumnFlag = false;
				}
				else{
					$column_2 .= $tempStr . "<br><img src='./images/line-soft-dotted-blue.gif' width='162' height='9'><br>";
					$ColumnFlag = true;
				}
			}

			print $column_1 . "<br><br>";
			print $column_2;


		}
	}
	else{
		print "<font color='#000099'>Address OK</font><br><br>";
	}

}

?>

<html>
<head>
<title>Untitled Document</title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>

<body bgcolor="#FFFFFF" text="#000000">
<font color="#990000"><br><b>United Parcel Service Address Validation</b></font>
<form name="form1" method="post" action="./test_ups.php">
<input type="hidden" name="form_sc" value="{FORM_SECURITY_CODE}">
City <input type="text" name="city" value="<? echo $city; ?>" size="30"><br>
State <input type="text" name="state" value="<? echo $state; ?>" size="3" maxlength="2">&nbsp;&nbsp;
ZIP <input type="text" name="zip" value="<? echo $zip; ?>" size="10"><br>
<input type="submit" name="go" value="go">
</form>
</body>
</html>






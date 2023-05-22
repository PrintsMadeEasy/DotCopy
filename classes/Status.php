<?

class Status {

	

	
	
	#-- Get the Single characters and their desciptions --#
	static function GetReprintReasonsHash(){
	
		$retArray = array();
		
		$retArray["C"] = "Customer Mistake";
		$retArray["Y"] = "Artwork Modification";
		$retArray["P"] = "Proofing Error";
		$retArray["Q"] = "Poor Print Quality";
		$retArray["T"] = "Trimming Error";
		$retArray["S"] = "Scratched or Chipped";
		$retArray["K"] = "Color Mismatch";
		$retArray["L"] = "Lost in Transit";
		$retArray["D"] = "Damaged in Transit";
		$retArray["M"] = "Mixed up Shipping Labels";
		$retArray["O"] = "Other";
		$retArray["H"] = "Re-Order From Phone";
		$retArray["O"] = "Card Stock Issue";
	
	
		
		return $retArray;
	}
	
	static function GetReprintReasonString($StatusChar){
	
		$DescHash = Status::GetReprintReasonsHash();
		
		if(!isset($DescHash[$StatusChar])){
			print "Error in function call Status::GetReprintReasonString: $StatusChar";
			return "";
		}
		else{
			return $DescHash[$StatusChar];
		}
	
	}
	
	static function GetCountryByCode($CountryCode){
		$CountryArr = Status::GetUPScountryCodesArr();
	
		if(!array_key_exists($CountryCode, $CountryArr))
			throw new Exception("Illegal country code in function call Status::GetCountryByCode");
		return $CountryArr[$CountryCode];
	}
	
	// Returns a list of UPS country codes
	static function GetUPScountryCodesArr(){
	
		return array("US"=>"United States", "CA"=>"Canada");
	
	/*
		return array(
		"AL"=>"Albania", 
		"DZ"=>"Algeria", 
		"AD"=>"Andorra", 
		"AO"=>"Angola", 
		"AI"=>"Anguilla", 
		"AG"=>"Antigua & Barbuda", 
		"AR"=>"Argentina", 
		"AM"=>"Armenia", 
		"AW"=>"Aruba", 
		"AU"=>"Australia", 
		"AT"=>"Austria", 
		"AZ"=>"Azerbaijan", 
		"BS"=>"Bahamas", 
		"BH"=>"Bahrain", 
		"BD"=>"Bangladesh", 
		"BB"=>"Barbados", 
		"BY"=>"Belarus", 
		"BE"=>"Belgium", 
		"BZ"=>"Belize", 
		"BJ"=>"Benin", 
		"BM"=>"Bermuda", 
		"BO"=>"Bolivia", 
		"BA"=>"Bosnia", 
		"BW"=>"Botswana", 
		"BR"=>"Brazil", 
		"VG"=>"Virgin Isles", 
		"BN"=>"Brunei", 
		"BG"=>"Bulgaria", 
		"BF"=>"Burkina Faso", 
		"BI"=>"Burundi", 
		"KH"=>"Cambodia", 
		"CM"=>"Cameroon", 
		"CA"=>"Canada", 
		"CV"=>"Cape Verde", 
		"KY"=>"Cayman Islands", 
		"CF"=>"Central African Rep", 
		"TD"=>"Chad", 
		"CD"=>"Channel Islands", 
		"CL"=>"Chile", 
		"CN"=>"China", 
		"CO"=>"Colombia", 
		"CG"=>"Congo", 
		"CK"=>"Cook Islands", 
		"CR"=>"Costa Rica", 
		"HR"=>"Croatia", 
		"CY"=>"Cyprus", 
		"CZ"=>"Czech Republic", 
		"ZR"=>"Dem Rep of Congo", 
		"DK"=>"Denmark", 
		"DJ"=>"Djibouti", 
		"DM"=>"Dominica", 
		"DO"=>"Dominican Republic", 
		"EC"=>"Ecuador", 
		"EG"=>"Egypt", 
		"SV"=>"El Salvador", 
		"GQ"=>"Equatorial Guinea", 
		"ER"=>"Eritrea", 
		"EE"=>"Estonia", 
		"ET"=>"Ethiopia", 
		"FO"=>"Faeroe Islands", 
		"FJ"=>"Fiji", 
		"FI"=>"Finland", 
		"FR"=>"France", 
		"GF"=>"French Guiana", 
		"PF"=>"French Polynesia/Tahiti", 
		"GA"=>"Gabon", 
		"GM"=>"Gambia", 
		"GE"=>"Georgia", 
		"DE"=>"Germany", 
		"GH"=>"Ghana", 
		"GI"=>"Gibraltar", 
		"GR"=>"Greece", 
		"GL"=>"Greenland", 
		"GD"=>"Grenada", 
		"GP"=>"Guadeloupe", 
		"GU"=>"Guam", 
		"GT"=>"Guatemala", 
		"GN"=>"Guinea", 
		"GW"=>"Guinea-Bissau", 
		"GY"=>"Guyana", 
		"HT"=>"Haiti", 
		"HN"=>"Honduras", 
		"HK"=>"Hong Kong", 
		"HU"=>"Hungary", 
		"IS"=>"Iceland", 
		"IN"=>"India", 
		"ID"=>"Indonesia", 
		"IR"=>"Iran", 
		"IQ"=>"Iraq", 
		"IE"=>"Ireland", 
		"IL"=>"Israel", 
		"IT"=>"Italy", 
		"CI"=>"Ivory Coast", 
		"JM"=>"Jamaica", 
		"JP"=>"Japan", 
		"JO"=>"Jordan", 
		"KZ"=>"Kazakhstan", 
		"KE"=>"Kenya", 
		"KI"=>"Kiribati", 
		"KW"=>"Kuwait", 
		"KG"=>"Kyrgyzstan", 
		"LA"=>"Laos", 
		"LV"=>"Latvia", 
		"LB"=>"Lebanon", 
		"LS"=>"Lesotho", 
		"LR"=>"Liberia", 
		"LY"=>"Libya", 
		"LI"=>"Liechtenstein", 
		"LT"=>"Lithuania", 
		"LU"=>"Luxembourg", 
		"MO"=>"Macau", 
		"MK"=>"Macedonia", 
		"MG"=>"Madagascar", 
		"MW"=>"Malawi", 
		"MY"=>"Malaysia", 
		"MV"=>"Maldives", 
		"ML"=>"Mali", 
		"MT"=>"Malta", 
		"MH"=>"Marshall Islands", 
		"MQ"=>"Martinique", 
		"MR"=>"Mauritania", 
		"MU"=>"Mauritius", 
		"MX"=>"Mexico", 
		"FM"=>"Micronesia", 
		"MD"=>"Moldova", 
		"MC"=>"Monaco", 
		"MN"=>"Mongolia", 
		"MS"=>"Montserrat", 
		"MA"=>"Morocco", 
		"MZ"=>"Mozambique", 
		"MM"=>"Myanmar", 
		"MP"=>"N. Mariana Islands", 
		"NA"=>"Namibia", 
		"NP"=>"Nepal", 
		"NL"=>"Netherlands", 
		"AN"=>"Netherlands Antilles", 
		"NC"=>"New Caledonia", 
		"NZ"=>"New Zealand", 
		"NI"=>"Nicaragua", 
		"NE"=>"Niger", 
		"NG"=>"Nigeria", 
		"NF"=>"Norfolk Island", 
		"NO"=>"Norway", 
		"OM"=>"Oman", 
		"PK"=>"Pakistan", 
		"PW"=>"Palau", 
		"PA"=>"Panama", 
		"PG"=>"Papua New Guinea", 
		"PY"=>"Paraguay", 
		"PE"=>"Peru", 
		"PH"=>"Philippines", 
		"PL"=>"Poland", 
		"PT"=>"Portugal", 
		"PR"=>"Puerto Rico", 
		"QA"=>"Qatar", 
		"RE"=>"Reunion Isl.", 
		"RO"=>"Romania", 
		"RU"=>"Russia", 
		"RW"=>"Rwanda", 
		"AS"=>"Samoa (Amer.)", 
		"WS"=>"Samoa (Western)", 
		"SM"=>"San Marino", 
		"SA"=>"Saudi Arabia", 
		"SN"=>"Senegal", 
		"CS"=>"Serbia and Montenegro", 
		"SC"=>"Seychelles", 
		"SL"=>"Sierra Leone", 
		"SG"=>"Singapore", 
		"SK"=>"Slovakia", 
		"SI"=>"Slovenia", 
		"SB"=>"Solomon Islands", 
		"ZA"=>"South Africa", 
		"KR"=>"South Korea", 
		"ES"=>"Spain", 
		"LK"=>"Sri Lanka", 
		"KN"=>"St. Christopher", 
		"LC"=>"St. Lucia", 
		"VC"=>"St. Vincent/Grenadines", 
		"SD"=>"Sudan", 
		"SR"=>"Suriname", 
		"SZ"=>"Swaziland", 
		"SE"=>"Sweden", 
		"CH"=>"Switzerland", 
		"SY"=>"Syria", 
		"TW"=>"Taiwan", 
		"TJ"=>"Tajikistan", 
		"TZ"=>"Tanzania", 
		"TH"=>"Thailand", 
		"TG"=>"Togo", 
		"TO"=>"Tonga", 
		"TT"=>"Trinidad & Tobago", 
		"TN"=>"Tunisia", 
		"TR"=>"Turkey", 
		"TM"=>"Turkmenistan", 
		"TC"=>"Turks & Caicos Isl.", 
		"TV"=>"Tuvalu", 
		"UG"=>"Uganda", 
		"UA"=>"Ukraine", 
		"AE"=>"United Arab Emirates", 
		"GB"=>"United Kingdom", 
		"US"=>"United States", 
		"UY"=>"Uruguay", 
		"VI"=>"US Virgin Islands", 
		"UZ"=>"Uzbekistan", 
		"VU"=>"Vanuatu", 
		"VE"=>"Venezuela", 
		"VN"=>"Vietnam", 
		"WK"=>"Wake Island", 
		"WF"=>"Wallis & Futuna Isle", 
		"YE"=>"Yemen", 
		"ZM"=>"Zambia", 
		"ZW"=>"Zimbabwe"
		);
	*/
	
	}

}
?>
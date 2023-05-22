
// Returns Name/Value pairs for various codes of PrintsMadeEasy that may are of interest to us
// Country codes, Status Descriptions, Shipping Character codes and their methods, etc.
class com.pme.CodeDescriptions {

	// Returns an associative array using a generic object
	// Returns in reverse order to make it easy to iterate through the results in correct order
	public static function getCountryCodes ():Array {	
		
		var retArr:Array = new Array();
		
		retArr["US"] = "United States"; 
		
		/*
		retArr["ZM"] = "Zambia"; 
		retArr["YE"] = "Yemen"; 
		retArr["WF"] = "Wallis & Futuna Isle"; 
		retArr["WK"] = "Wake Island"; 
		retArr["VG"] = "Virgin Isles"; 
		retArr["VN"] = "Vietnam"; 
		retArr["VE"] = "Venezuela"; 
		retArr["VU"] = "Vanuatu"; 
		retArr["UZ"] = "Uzbekistan"; 
		retArr["VI"] = "US Virgin Islands"; 
		retArr["UY"] = "Uruguay"; 
		retArr["US"] = "United States"; 
		retArr["GB"] = "United Kingdom"; 
		retArr["AE"] = "United Arab Emirates"; 
		retArr["UA"] = "Ukraine"; 
		retArr["UG"] = "Uganda"; 
		retArr["TV"] = "Tuvalu"; 
		retArr["TC"] = "Turks & Caicos Isl."; 
		retArr["TM"] = "Turkmenistan"; 
		retArr["TR"] = "Turkey"; 
		retArr["TN"] = "Tunisia"; 
		retArr["TT"] = "Trinidad & Tobago"; 
		retArr["TO"] = "Tonga"; 
		retArr["TG"] = "Togo"; 
		retArr["TH"] = "Thailand"; 
		retArr["TZ"] = "Tanzania"; 
		retArr["TJ"] = "Tajikistan"; 
		retArr["TW"] = "Taiwan"; 
		retArr["SY"] = "Syria"; 
		retArr["CH"] = "Switzerland"; 
		retArr["SE"] = "Sweden"; 
		retArr["SZ"] = "Swaziland"; 
		retArr["SR"] = "Suriname"; 
		retArr["SD"] = "Sudan"; 
		retArr["VC"] = "St. Vincent/Grenadines"; 
		retArr["LC"] = "St. Lucia"; 
		retArr["KN"] = "St. Christopher"; 
		retArr["LK"] = "Sri Lanka"; 
		retArr["ES"] = "Spain"; 
		retArr["KR"] = "South Korea"; 
		retArr["ZA"] = "South Africa"; 
		retArr["SB"] = "Solomon Islands"; 
		retArr["SI"] = "Slovenia"; 
		retArr["SK"] = "Slovakia"; 
		retArr["SG"] = "Singapore"; 
		retArr["SL"] = "Sierra Leone"; 
		retArr["SC"] = "Seychelles"; 
		retArr["CS"] = "Serbia and Montenegro"; 
		retArr["SN"] = "Senegal"; 
		retArr["SA"] = "Saudi Arabia"; 
		retArr["SM"] = "San Marino"; 
		retArr["WS"] = "Samoa (Western)"; 
		retArr["AS"] = "Samoa (Amer.)"; 
		retArr["RW"] = "Rwanda"; 
		retArr["RU"] = "Russia"; 
		retArr["RO"] = "Romania"; 
		retArr["RE"] = "Reunion Isl."; 
		retArr["QA"] = "Qatar"; 
		retArr["PR"] = "Puerto Rico"; 
		retArr["PT"] = "Portugal"; 
		retArr["PL"] = "Poland"; 
		retArr["PH"] = "Philippines"; 
		retArr["PE"] = "Peru"; 
		retArr["PY"] = "Paraguay"; 
		retArr["PG"] = "Papua New Guinea"; 
		retArr["PA"] = "Panama"; 
		retArr["PW"] = "Palau"; 
		retArr["PK"] = "Pakistan"; 
		retArr["OM"] = "Oman"; 
		retArr["NO"] = "Norway"; 
		retArr["NF"] = "Norfolk Island"; 
		retArr["NG"] = "Nigeria"; 
		retArr["NE"] = "Niger"; 
		retArr["NI"] = "Nicaragua"; 
		retArr["NZ"] = "New Zealand"; 
		retArr["NC"] = "New Caledonia"; 
		retArr["NL"] = "Netherlands"; 
		retArr["AN"] = "Netherlands Antilles"; 
		retArr["NP"] = "Nepal"; 
		retArr["NA"] = "Namibia"; 
		retArr["MP"] = "N. Mariana Islands"; 
		retArr["MM"] = "Myanmar"; 
		retArr["MZ"] = "Mozambique"; 
		retArr["MA"] = "Morocco"; 
		retArr["MS"] = "Montserrat"; 
		retArr["MN"] = "Mongolia"; 
		retArr["MC"] = "Monaco"; 
		retArr["MD"] = "Moldova"; 
		retArr["FM"] = "Micronesia"; 
		retArr["MX"] = "Mexico"; 
		retArr["MU"] = "Mauritius"; 
		retArr["MR"] = "Mauritania"; 
		retArr["MQ"] = "Martinique"; 
		retArr["MH"] = "Marshall Islands"; 
		retArr["MT"] = "Malta"; 
		retArr["ML"] = "Mali"; 
		retArr["MV"] = "Maldives"; 
		retArr["MY"] = "Malaysia"; 
		retArr["MW"] = "Malawi"; 
		retArr["MG"] = "Madagascar"; 
		retArr["MK"] = "Macedonia"; 
		retArr["MO"] = "Macau"; 
		retArr["LU"] = "Luxembourg"; 
		retArr["LT"] = "Lithuania"; 
		retArr["LI"] = "Liechtenstein"; 
		retArr["LY"] = "Libya"; 
		retArr["LR"] = "Liberia"; 
		retArr["LS"] = "Lesotho"; 
		retArr["LB"] = "Lebanon"; 
		retArr["LV"] = "Latvia"; 
		retArr["LA"] = "Laos"; 
		retArr["KG"] = "Kyrgyzstan"; 
		retArr["KW"] = "Kuwait"; 
		retArr["KI"] = "Kiribati"; 
		retArr["KE"] = "Kenya"; 
		retArr["KZ"] = "Kazakhstan"; 
		retArr["JO"] = "Jordan"; 
		retArr["JP"] = "Japan"; 
		retArr["JM"] = "Jamaica"; 
		retArr["CI"] = "Ivory Coast"; 
		retArr["IT"] = "Italy"; 
		retArr["IL"] = "Israel"; 
		retArr["IE"] = "Ireland"; 
		retArr["IQ"] = "Iraq"; 
		retArr["IR"] = "Iran"; 
		retArr["ID"] = "Indonesia"; 
		retArr["IN"] = "India"; 
		retArr["IS"] = "Iceland"; 
		retArr["HU"] = "Hungary"; 
		retArr["HK"] = "Hong Kong"; 
		retArr["HN"] = "Honduras"; 
		retArr["HT"] = "Haiti"; 
		retArr["GY"] = "Guyana"; 
		retArr["GW"] = "Guinea-Bissau"; 
		retArr["GN"] = "Guinea"; 
		retArr["GT"] = "Guatemala"; 
		retArr["GU"] = "Guam"; 
		retArr["GP"] = "Guadeloupe"; 
		retArr["GD"] = "Grenada"; 
		retArr["GL"] = "Greenland"; 
		retArr["GR"] = "Greece"; 
		retArr["GI"] = "Gibraltar"; 
		retArr["GH"] = "Ghana"; 
		retArr["DE"] = "Germany"; 
		retArr["GE"] = "Georgia"; 
		retArr["GM"] = "Gambia"; 
		retArr["GA"] = "Gabon"; 
		retArr["PF"] = "French Polynesia/Tahiti"; 
		retArr["GF"] = "French Guiana"; 
		retArr["FR"] = "France"; 
		retArr["FI"] = "Finland"; 
		retArr["FJ"] = "Fiji"; 
		retArr["FO"] = "Faeroe Islands"; 
		retArr["ET"] = "Ethiopia"; 
		retArr["EE"] = "Estonia"; 
		retArr["ER"] = "Eritrea"; 
		retArr["GQ"] = "Equatorial Guinea"; 
		retArr["SV"] = "El Salvador"; 
		retArr["EG"] = "Egypt"; 
		retArr["EC"] = "Ecuador"; 
		retArr["DO"] = "Dominican Republic"; 
		retArr["DM"] = "Dominica"; 
		retArr["DJ"] = "Djibouti"; 
		retArr["DK"] = "Denmark"; 
		retArr["ZR"] = "Dem Rep of Congo"; 
		retArr["CZ"] = "Czech Republic"; 
		retArr["CY"] = "Cyprus"; 
		retArr["HR"] = "Croatia"; 
		retArr["CR"] = "Costa Rica"; 
		retArr["CK"] = "Cook Islands"; 
		retArr["CG"] = "Congo"; 
		retArr["CO"] = "Colombia"; 
		retArr["CN"] = "China"; 
		retArr["CL"] = "Chile"; 
		retArr["CD"] = "Channel Islands"; 
		retArr["TD"] = "Chad"; 
		retArr["CF"] = "Central African Rep"; 
		retArr["KY"] = "Cayman Islands"; 
		retArr["CV"] = "Cape Verde"; 
		retArr["CA"] = "Canada"; 
		retArr["CM"] = "Cameroon"; 
		retArr["KH"] = "Cambodia"; 
		retArr["BI"] = "Burundi"; 
		retArr["BF"] = "Burkina Faso"; 
		retArr["BG"] = "Bulgaria"; 
		retArr["BN"] = "Brunei"; 
		retArr["BR"] = "Brazil"; 
		retArr["BW"] = "Botswana"; 
		retArr["BA"] = "Bosnia"; 
		retArr["BO"] = "Bolivia"; 
		retArr["BM"] = "Bermuda"; 
		retArr["BJ"] = "Benin"; 
		retArr["BZ"] = "Belize"; 
		retArr["BE"] = "Belgium"; 
		retArr["BY"] = "Belarus"; 
		retArr["BB"] = "Barbados"; 
		retArr["BD"] = "Bangladesh"; 
		retArr["BH"] = "Bahrain"; 
		retArr["BS"] = "Bahamas"; 
		retArr["AZ"] = "Azerbaijan"; 
		retArr["AT"] = "Austria"; 
		retArr["AU"] = "Australia"; 
		retArr["AW"] = "Aruba"; 
		retArr["AM"] = "Armenia"; 
		retArr["AR"] = "Argentina"; 
		retArr["AG"] = "Antigua & Barbuda"; 
		retArr["AI"] = "Anguilla"; 
		retArr["AO"] = "Angola"; 
		retArr["AD"] = "Andorra"; 
		retArr["DZ"] = "Algeria"; 
		retArr["AL"] = "Albania"; 
		*/
		
		return retArr;
	}






}





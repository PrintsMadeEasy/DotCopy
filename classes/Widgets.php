<?

class Widgets {

	
	
	
	// Will return percentage of the discount, like 20.56%
	// Takes into account the significant digits, so it doesn't give you an unesessary number like 20.3434344343%
	// Will also strip off any trailing Zeros... so 20.000% will come back as just 20%
	static function GetDiscountPercentFormated($totalAmount, $amountOfDiscount){
	
		// prevent division by zero
		if($totalAmount == 0)
			return 0;
	
		$totalAmount = round($totalAmount, 2);
		$amountOfDiscount = round($amountOfDiscount, 2);
	
		// Make sure there are no commas, and the number has exactly 2 decimal places
		$totalAmount = number_format($totalAmount, 2, '.', '');
		$amountOfDiscount = number_format($amountOfDiscount, 2, '.', '');
	
		$sigDigitsInTotal = WebUtil::GetSignificantDigitsInNumber($totalAmount);
		$sigDigitsInDiscount = WebUtil::GetSignificantDigitsInNumber($amountOfDiscount);
		
		// Use whatever has more significant digits
		if($sigDigitsInTotal > $sigDigitsInDiscount)
			$significantDigitsToMatch = $sigDigitsInTotal;
		else
			$significantDigitsToMatch = $sigDigitsInDiscount;
	
	
		// Make sure the discount amount has 9 digits after the decimal, which is a bit excessive because most of them will get truncated
		$discountAmount = $amountOfDiscount/$totalAmount;
	
		$discountAmount = number_format($discountAmount, 9, '.', '');
		
		// We want to use as many numbers after the decimal place, as we have signficant digits in our Discount Amount or Total.
		$discountAmount = round($discountAmount, $significantDigitsToMatch);
		$discountAmount = $discountAmount * 100;
		
		// Get rid of any trailing Zeros from the end
		// If it is all zeros after the decimal, then get rid of the decimal too.
		$discountAmount = preg_replace("/\.0+$/", "", $discountAmount);
		if(preg_match("/\./", $discountAmount))
			$discountAmount = preg_replace("/0+$/", "", $discountAmount);
	
		return $discountAmount;
	}
	
	
	
	
	// Builds HTML Selection Component
	// Pass hash of name/values and array of options (matching key values) that are be selected
	// Selected value can also be a string (for only 1 item selected)
	// If name not specified, return just the options list, else return a full select statement
	// A template field identified by $name + "_ATTRS is also added to to the select element in order
	// to add additional attributes following the build
	static function buildSelect($selectionsArr, $selectedArr, $name=null, $class="AdminDropDown", $onChangeJavascript=null )
	{ 
	
	    if(!is_array($selectedArr))
	    	$selectedArr = array($selectedArr);
	
	    $options = null;
	    foreach($selectionsArr as $Optionkey => $Optionvalue)
	    { 	
	    	$Optionkey = WebUtil::htmlOutput($Optionkey);
	    	$Optionvalue = WebUtil::htmlOutput($Optionvalue);
	    	
	  	 	$sel =  in_array( $Optionkey, $selectedArr ) ? "SELECTED " : null; 
	        $options .=  "<option {$sel}value='$Optionkey'>$Optionvalue</option>\n"; 
	    } 
	    if( !$name )
	    	return $options;
	
	    $attrs = "{" . $name . "_ATTRS}";
	   
	    if($onChangeJavascript)
	    	$javascriptOnChangeHTML = " onChange=\"" . preg_replace("/\"/", "\\\"", $onChangeJavascript) . "\"";
	    else
	    	$javascriptOnChangeHTML = "";
	
	    return "<select name='$name' class='$class' $attrs $javascriptOnChangeHTML>$options</select>"; 
	}
	
	
	
	//Build HTML Month Selection Component
	static function BuildMonthSelect( $selected, $name = "month", $class = "AdminDropDown", $onChangeJavascript=null )
	{
		if( $selected < 1 || $selected > 12 )
			throw new Exception( "BuildMonthSelect' selected value is out of range: '$selected'");
	
		$months = array ( 1=>"January", 2=>"February", 3=>"March", 4=>"April",
			5=>"May", 6=>"June", 7=>"July", 8=>"August", 9=>"September", 10=>"October",
			11=>"November", 12=>"December" );
		
		return Widgets::buildSelect( $months, array( $selected ), $name, $class, $onChangeJavascript );
	}
	
	
	
	
	
	
	
	//Build HTML Month Selection Component with an extra parameter for all.
	static function BuildMonthSelectWithAllChoice( $selected, $name = "month", $class = "AdminDropDown", $onChangeJavascript=null )
	{
	
		if($selected != "ALL"){
			if( $selected < 1 || $selected > 12 )
				throw new Exception( "'BuildMonthSelect' selected value is out of range: '$selected'");
		}
	
		$months = array ( 1=>"January", 2=>"February", 3=>"March", 4=>"April",
			5=>"May", 6=>"June", 7=>"July", 8=>"August", 9=>"September", 10=>"October",
			11=>"November", 12=>"December", "ALL"=>"ALL" );
		
		return Widgets::buildSelect( $months, array( $selected ), $name, $class, $onChangeJavascript );
	}
	
	
	
	//Build HTML Day Selection Component
	static function BuildDaySelect( $selected, $name = "day", $class="AdminDropDown", $onChangeJavascript=null )
	{
		if( $selected < 1 || $selected > 31 )
			throw new Exception( "'BuildDaySelect' selected value is out of range: '$selected'");
	
		$days = null;
		for($i=1; $i<=31; $i++)
			$days[ $i ] = $i;
	
		return Widgets::buildSelect( $days, array( $selected ), $name, $class, $onChangeJavascript );
	}
	
	
	
	
	
	
	//Build HTML Year Selection Component
	static function BuildYearSelect($selected, $name = "year", $class="AdminDropDown", $onChangeJavascript=null)
	{
		$date = getdate();
		$year = $date["year"];	
		if( $selected < 2002 || $selected > $year )	
			throw new Exception( "'BuildYearSelect' selected value is out of range: '$selected'");
	
		$years = null;
		for($i=2002; $i<=$year; $i++)
			$years[ $i ] = $i;
	
		return Widgets::buildSelect( $years, array( $selected ), $name, $class, $onChangeJavascript );
	}
	
	

	//Build HTML Year Selection Component
	static function BuildFutureYearSelectWithAllChoice($selected, $name = "year", $class="AdminDropDown", $onChangeJavascript=null)
	{
		if( $selected != "ALL" && ($selected < 2002 || $selected > 2031) )	
			throw new Exception( "'BuildYearSelect' selected value is out of range: '$selected'");
	
		$years = null;
		for($i=2009; $i<=2031; $i++)
			$years[ $i ] = $i;
	
		$years["ALL"] = "ALL";
		
		return Widgets::buildSelect( $years, array( $selected ), $name, $class, $onChangeJavascript );
	}
	
	
	
	//Build HTML Year Selection Component
	static function BuildYearSelectWithAllChoice($selected, $name = "year", $class="AdminDropDown", $onChangeJavascript=null)
	{
		$date = getdate();
		$year = $date["year"];	
		if( $selected != "ALL" && ($selected < 2002 || $selected > $year) )	
			throw new Exception( "'BuildYearSelect' selected value is out of range: '$selected'");
	
		$years = null;
		for($i=2002; $i<=$year; $i++)
			$years[ $i ] = $i;
	
		$years["ALL"] = "ALL";
		
		return Widgets::buildSelect( $years, array( $selected ), $name, $class, $onChangeJavascript );
	}
	
	
	
	
	//Build HTML time frame selection block
	//Returns option block for date frame selections with passed selection selected
	static function BuildTimeFrameSelect( $selFrame, $name = "TimeFrame" )
	{
		$frames = array(
	
			"TODAY"=>"Today", 
			"YESTERDAY"=>"Yesterday",
			"THISWEEK"=>"This Week",
			"LASTWEEK"=>"Last Week",
			"THISMONTH"=>"This Month",
			"LASTMONTH"=>"Last Month",
			"THISYEAR"=>"This Year",
			"LASTYEAR"=>"Last Year",
			"ALLTIME"=>"From Beginning");
		
		return Widgets::buildSelect( $frames, array( $selFrame ), $name );
	}
	
	
	
	//Build HTML date range selection block
	//Returns full HTML code for both sets of selections
	//if $setType = "D", then day selections will be included
	//selStartDate & selEndDate are timestamps, if value is null, it is set to today
	static function BuildDateRangeSelect( $selStartDate, $selEndDate, $rangeType = "D", $name = "DateRange", $class="AdminDropDown" )
	{
		if( $selStartDate == null )
			$selStartDate = time();
		if( $selEndDate == null )
			$selEndDate = time();
			
		$startdate = getdate( $selStartDate );
		$startmonth = $startdate["mon"];
		$startday = $startdate["mday"];
		$startyear = $startdate["year"];
		
		$enddate = getdate( $selEndDate );
		$endmonth = $enddate["mon"];
		$endday = $enddate["mday"];
		$endyear = $enddate["year"];	
		
		
		if($class == "AdminDropDown" )
			$textInputClass = 'AdminDaySelect';
		else 
			$textInputClass = $class;
		
		$html = Widgets::BuildMonthSelect( $startmonth, $name . "StartMonth", $class ) . " ";
		if( $rangeType == "D" )
			$html .=  " <input type='text' name='".$name."StartDay' value='$startday' class='$textInputClass' style='width:28px; height=22px;' onKeyUp='checkDaySelectInput(this)' maxlength='2'>&nbsp;&nbsp;&nbsp;";	
		
		$html .= Widgets::BuildYearSelect( $startyear, $name . "StartYear", $class ) . "&nbsp;&nbsp;-&nbsp;&nbsp;";
		
		$html .= Widgets::BuildMonthSelect( $endmonth, $name . "EndMonth", $class ) . " ";
		if( $rangeType == "D" )
			$html .=  " <input align='absmiddle' type='text' name='".$name."EndDay' value='$endday' class='$textInputClass' style='width:28px; height=22px;' onKeyUp='checkDaySelectInput(this)' maxlength='2'>&nbsp;&nbsp;&nbsp;";	
		
		$html .= Widgets::BuildYearSelect( $endyear, $name . "EndYear", $class );
		
		// Show Next/Previous Links for the Days (if the date range has days... the the start end end dates are within 1 day of each other.
		if( $rangeType == "D" && ($startmonth == $endmonth && $startday == $endday && $startyear == $endyear)){
			
			$previousDayHash = getdate($selStartDate - 60*60*24);
			$nextDayHash = getdate($selStartDate + 60*60*24);
			
			$html .= "&nbsp;";
			$html .= "<a class='BlueRedLink' href=\"javascript:changeReportDate('".$previousDayHash["mday"]."','".$previousDayHash["mon"]."','".$previousDayHash["year"]."','".$previousDayHash["mday"]."','".$previousDayHash["mon"]."','".$previousDayHash["year"]."');\">&lt; P</a>";
			$html .= "&nbsp;&nbsp;";
			$html .= "<a class='BlueRedLink' href=\"javascript:changeReportDate('".$nextDayHash["mday"]."','".$nextDayHash["mon"]."','".$nextDayHash["year"]."','".$nextDayHash["mday"]."','".$nextDayHash["mon"]."','".$nextDayHash["year"]."');\">N &gt;</a>";
			$html .= "&nbsp;";
		}
		
		$html .= "<script>
		
		function checkDaySelectInput(inputObj){
			inputObj.value = inputObj.value.replace(/[^\d]/, '');
		} 
		
		function changeReportDate(startDay, startMonth, startYear, endDay, endMonth, endYear){
			
			SelectPeriodTypeTimeFrame(false);
			
			document.all.PeriodType[0].checked = false;
			document.all.PeriodType[1].checked = true;
			
			document.all.".$name."StartDay.value = startDay;
			document.all.".$name."StartMonth.value = startMonth;
			document.all.".$name."StartYear.value = startYear;
			document.all.".$name."EndDay.value = endDay;
			document.all.".$name."EndMonth.value = endMonth;
			document.all.".$name."EndYear.value = endYear;
			
			var onSubmitEventCode = document.getElementById('ReportOptions').getAttribute('onsubmit').toString();
			
			// Try to exract a function name out of the Anonomys Javascript function attribute.
			var regEx = new RegExp(\"([^\\n]*;)\", \"g\"); // Looking for a one line statement with a semi colon at the end.
			if(onSubmitEventCode.match(regEx)){
				var resultArr = onSubmitEventCode.match(regEx);
				var functionStr = resultArr[0];

				// Find out if the event handler expects a return.
				if(functionStr.match(/return/)){
					functionStr = functionStr.replace(/return\s+/, '');
					var resultFromEvent = eval(functionStr);
					
					// If the return came back true... then submit the form.
					if(resultFromEvent){
						document.forms['ReportOptions'].submit();
					}
				}
				else{
					document.forms['ReportOptions'].submit();
				}
			}
			else{
				document.forms['ReportOptions'].submit();
			}

		
		}
		</script>";
		return $html;
	}
	
	
	
	
	//Get starting and ending date for date frame
	//Returns hash containing "STARTDATE" and "ENDDATE" of passed date frame.
	//Date values are timestamps
	static function GetTimeFrame( $timeFrame )
	{
		$date = getdate();
		$month = $date["mon"];
		$day = $date["mday"];
		$year = $date["year"];
	
		$startToday = mktime( 0,0,0, $month, $day, $year );
		$lastDay = $endToday = mktime( 23,59,59, $month, $day, $year );
		switch( $timeFrame )
		{
			case "ALLTIME" :
				$firstDay = mktime( 0,0,0, 1,1,2002 );
				$text = "From Beginning";
				break;
			case "TODAY" :
				$firstDay= $startToday;
				$text = "Today";			
				break;		
			case "YESTERDAY" :
				$firstDay = strtotime( "-1 day", $startToday );
				$lastDay = strtotime( "-1 day", $endToday );
				$text = "Yesterday";			
				break;
			case "2DAYSAGO" :
				$firstDay = strtotime( "-2 day", $startToday );
				$lastDay = strtotime( "-2 day", $endToday );
				$text = date("l", $lastDay);			
				break;
			case "3DAYSAGO" :
				$firstDay = strtotime( "-3 day", $startToday );
				$lastDay = strtotime( "-3 day", $endToday );
				$text = date("l", $lastDay);			
				break;
			case "4DAYSAGO" :
				$firstDay = strtotime( "-4 day", $startToday );
				$lastDay = strtotime( "-4 day", $endToday );
				$text = date("l", $lastDay);			
				break;
			case "5DAYSAGO" :
				$firstDay = strtotime( "-5 day", $startToday );
				$lastDay = strtotime( "-5 day", $endToday );
				$text = date("l", $lastDay);			
				break;
			case "6DAYSAGO" :
				$firstDay = strtotime( "-6 day", $startToday );
				$lastDay = strtotime( "-6 day", $endToday );
				$text = date("l", $lastDay);			
				break;
			case "7DAYSAGO" :
				$firstDay = strtotime( "-7 day", $startToday );
				$lastDay = strtotime( "-7 day", $endToday );
				$text = "1 Wk. Ago from Today";			
				break;
			case "8DAYSAGO" :
				$firstDay = strtotime( "-8 day", $startToday );
				$lastDay = strtotime( "-8 day", $endToday );
				$text = "1 Wk. Ago from Yesterday";			
				break;
			case "9DAYSAGO" :
				$firstDay = strtotime( "-9 day", $startToday );
				$lastDay = strtotime( "-9 day", $endToday );
				$text = "1 Wk. Ago from last " . date("D", $lastDay) . ".";			
				break;
			case "10DAYSAGO" :
				$firstDay = strtotime( "-10 day", $startToday );
				$lastDay = strtotime( "-10 day", $endToday );
				$text = "1 Wk. Ago from last " . date("D", $lastDay) . ".";			
				break;
			case "11DAYSAGO" :
				$firstDay = strtotime( "-11 day", $startToday );
				$lastDay = strtotime( "-11 day", $endToday );
				$text = "1 Wk. Ago from last " . date("D", $lastDay) . ".";			
				break;
			case "12DAYSAGO" :
				$firstDay = strtotime( "-12 day", $startToday );
				$lastDay = strtotime( "-12 day", $endToday );
				$text = "1 Wk. Ago from last " . date("D", $lastDay) . ".";			
				break;
			case "13DAYSAGO" :
				$firstDay = strtotime( "-13 day", $startToday );
				$lastDay = strtotime( "-13 day", $endToday );
				$text = "1 Wk. Ago from last " . date("D", $lastDay) . ".";			
				break;
			case "THISWEEK" :
				$firstDay = strtotime( "last sunday", strtotime( "+1 day", $startToday ));
				$text = "This Week";				
				break;
			case "LASTWEEK" :
				$firstDay = strtotime( "last sunday", strtotime( "-6 days", $startToday ));
				$lastDay = strtotime( "last saturday", $endToday );
				$lastDay = strtotime("23 hours 59 minutes 59 seconds", $lastDay);
				$text = "Last Week";	
				break;
			case "THISMONTH" :
				$firstDay = mktime( 0,0,0,$month,1,$year );
				$text = "This Month";				
				break;		
			case "LASTMONTH" :
				$firstDay = mktime( 0,0,0,$month-1,1,$year );
				$lastDay = mktime( 23,59,59,$month,0,$year );
				$text = "Last Month";				
				break;
			case "THISYEAR" :
				$firstDay = mktime( 0,0,0,1,1,$year );
				$text = "This Year";				
				break;
			case "LASTYEAR" :
				$firstDay = mktime( 0,0,0, 1,1, $year-1);
				$lastDay = mktime( 23,59,59,12,31,$year-1);
				$text = "Last Year";				
				break;
			default:
				print "Error: 'GetTimeFrame' function passed unrecognized date frame '$timeFrame'";
				exit;
		}
		return array( "STARTDATE"=>$firstDay, "ENDDATE"=>$lastDay, "FRAMETEXT"=>$text );
	}
	
	static function GetTimeFrameText($timeFrame){
		$timeFrameHash = Widgets::GetTimeFrame($timeFrame);
		return $timeFrameHash["FRAMETEXT"];
	}
	
	
	static function GetColoredPrice($thePrice){
	
		$thePrice = preg_replace("/,/", "", $thePrice);
		
		// Just be careful of XSS attacks becuase we rely on the result of this method to be ready for substution within HTML.
		$thePrice = WebUtil::htmlOutput($thePrice);
		
		if($thePrice < 0){
			$thePrice = "<font color='#990000'>- $" . number_format(-($thePrice), 2) . "</font>";
		}
		else if($thePrice > 0){
			$thePrice = "<font color='#006600'>$" . number_format($thePrice, 2) . "</font>";
		}
		else{
			$thePrice = "$" . number_format($thePrice, 2);
		}
	
		return $thePrice;
	}
	
	
	static function GetPriceFormat($thePrice){
	
		if($thePrice < 0){
			$thePrice = '- $' . number_format(-1 * ($thePrice), 2);
		}
		else if($thePrice > 0){
			$thePrice = '$' . number_format($thePrice, 2);
		}
		else{
			$thePrice = '$' . number_format($thePrice, 2);
		}
	
		return $thePrice;
	
	}

	

	// This function will open the HTML header file off of disk and replace certain variables inside 
	static function GetHeaderHTML(DbCmd $dbCmd, &$AuthObj){
	
		// Get the User's Name
		$userControlObj = new UserControl($dbCmd);
		$userControlObj->LoadUserByID($AuthObj->GetUserID());
		$AdminName = $userControlObj->getName();
		
	
		$filename = "./ad_template_header.html";
	
		if(!file_exists($filename))
			WebUtil::PrintError("Template Header File is missing");
	
	
		$fd = fopen ($filename, "r");
		$HTMLfromFile = fread ($fd, filesize ($filename));
		fclose ($fd);
	
		$HTMLfromFile = preg_replace("/\{DATA:UserID\}/", $AuthObj->GetUserID(), $HTMLfromFile);


		$reg = "/<!--\s+ADMIN_TEMPLATE_START\s+-->(.*)\s*<!--\s+ADMIN_TEMPLATE_END\s+-->/sm";
	
		$m = array();
		preg_match_all($reg, $HTMLfromFile, $m);
		$HTMLcontents = $m[1][0];
		
		
	
		// Extract the HTML for displaying the Time Clock 
		$timeClockReg = "/<!--\s+TIME_CLOCK_START\s+-->(.*)\s*<!--\s+TIME_CLOCK_END\s+-->/sm";
		preg_match_all($timeClockReg, $HTMLfromFile, $m);
		$timeClockHTML = $m[1][0];
		
		
		// Concatenate the Timeclock HTML to the bottom of the Menu
		$HTMLcontents .= $timeClockHTML;
		
		
	
	
		//Define the Javscript menu for different levels of access.
		$superAdminMenu = '<script language="JavaScript1.2" src="library/menu/menu_settings_superadmin.js"></script>';
		$AdminMenu = '<script language="JavaScript1.2" src="library/menu/menu_settings_admin.js"></script>';
		$VendorMenu = '<script language="JavaScript1.2" src="library/menu/menu_settings_vendor.js"></script>';
		$CSMenu = '<script language="JavaScript1.2" src="library/menu/menu_settings_cs.js"></script>';
		$AccountingMenu = '<script language="JavaScript1.2" src="library/menu/menu_settings_accounting.js"></script>';
	
	
		if($AuthObj->CheckForPermission("MENU_SUPERADMIN"))
			$HTMLcontents = preg_replace("/{MENU_JS_SETTINGS}/", $superAdminMenu, $HTMLcontents);
		else if($AuthObj->CheckForPermission("MENU_ADMIN"))
			$HTMLcontents = preg_replace("/{MENU_JS_SETTINGS}/", $AdminMenu, $HTMLcontents);
		else if($AuthObj->CheckForPermission("MENU_CS"))
			$HTMLcontents = preg_replace("/{MENU_JS_SETTINGS}/", $CSMenu, $HTMLcontents);
		else if($AuthObj->CheckForPermission("MENU_ACCOUNTANT"))
			$HTMLcontents = preg_replace("/{MENU_JS_SETTINGS}/", $AccountingMenu, $HTMLcontents);
		else if($AuthObj->CheckForPermission("MENU_VENDOR"))
			$HTMLcontents = preg_replace("/{MENU_JS_SETTINGS}/", $VendorMenu, $HTMLcontents);
		else if($AuthObj->CheckForPermission("MENU_GUEST"))
			$HTMLcontents = preg_replace("/{MENU_JS_SETTINGS}/", "", $HTMLcontents);
		else
			throw new Exception("Error with setting the menu..  No permission was found.");
	
		
		if(!$AuthObj->CheckForPermission("CHAT_SYSTEM")){
			$reg = "/<!--\s+BEGIN ChatSystem\s+-->(.*)\s*<!--\s+END ChatSystem\s+-->/sm";
			$HTMLcontents = preg_replace($reg, "", $HTMLcontents);
		}

			
		// If they are in the Customer Service Group then show a drop down menu which will let them choose a Status
		if($AuthObj->CheckIfBelongsToGroup("CS") || $AuthObj->CheckIfBelongsToGroup("EDITOR")){
			
			// Discard the Login Link
			$reg = "/<!--\s+BEGIN LogoutLinkBL\s+-->(.*)\s*<!--\s+END LogoutLinkBL\s+-->/sm";
			$HTMLcontents = preg_replace($reg, "", $HTMLcontents);
			
			$dropDownMenuChoices = array("K"=>"Working", "A"=>"Away", "L"=>"Lunch", "V"=>"Vacation", "F"=>"Dormant", "logout"=>"Logout");
			
			// Get the Current Status of the User.
			$memberAttendanceObj = new MemberAttendance($dbCmd);
			$currentStatus = $memberAttendanceObj->getCurrentStatusOfUser($AuthObj->GetUserID());
	
			if(empty($currentStatus))
				$currentStatus = "U";
			
			if($currentStatus == "U")
				$dropDownMenuChoices = array_merge($dropDownMenuChoices, array("U"=>"Unknown"));
			else if($currentStatus == "W")
				$dropDownMenuChoices = array_merge($dropDownMenuChoices, array("W"=>"AWOL"));
		
			$HTMLcontents = preg_replace("/{STATUS_CHOICES}/", Widgets::buildSelect($dropDownMenuChoices, $currentStatus), $HTMLcontents);
			
			
			
			$HTMLcontents = preg_replace("/{RETURN_URL}/", ($_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']), $HTMLcontents);
			$HTMLcontents = preg_replace("/{RETURN_URL_ENCODED}/", urlencode($_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']), $HTMLcontents);
			
			
			// Discard the Return Time Block if the Status is already Working
			// Also if someone has an "Offline" Status we don't want to bug them with this message.
			// Also get rid of it with an "U"nknown status or AWOL too.
			if(in_array($currentStatus, array("F", "U", "W")) || $memberAttendanceObj->checkIfStatusMeansWorking($currentStatus)){
				$reg = "/<!--\s+BEGIN ReturnTimeBL\s+-->(.*)\s*<!--\s+END ReturnTimeBL\s+-->/sm";
				$HTMLcontents = preg_replace($reg, "", $HTMLcontents);
			}
			else{
				// Show them the Return Date form
			
				$HTMLcontents = preg_replace("/{STATUS_DESC}/", $memberAttendanceObj->getStatusDescriptionFromChar($currentStatus), $HTMLcontents);
			
				// We need to put the timestamp (at the time the page loaded) on the form... That way we can calculate an accurate timestamp in the future from the data the user enters.
				$HTMLcontents = preg_replace("/{TIMSTAMP_LOADED}/", time(), $HTMLcontents);
				
				
				// If they have scheduled a return date then put the Unix Timestamp in the Form
				// Otherwise get rid of the block for showing the return date.
				$returnTimeStamp = $memberAttendanceObj->getUnixTimeStampOfReturn($AuthObj->GetUserID());
				if($returnTimeStamp){
					// Show how many seconds in the future the return date is.  That way we can use javascript to calculate the return date on the Client's machine
					$HTMLcontents = preg_replace("/{RETURN_FUTURE_SECONDS}/", ($returnTimeStamp - time()), $HTMLcontents);
				}
				else{
					$reg = "/<!--\s+BEGIN ReturnDateSavedBL\s+-->(.*)\s*<!--\s+END ReturnDateSavedBL\s+-->/sm";
					$HTMLcontents = preg_replace($reg, "", $HTMLcontents);
				}
				
				
				// Let the CSR choose whether they want to use the auto reply feature.
				$autoReplyFlag = $memberAttendanceObj->checkForAutoReplyOnUser($AuthObj->GetUserID());
				if($autoReplyFlag){
					$HTMLcontents = preg_replace("/{AUTOREPLY_YES}/", "checked", $HTMLcontents);
					$HTMLcontents = preg_replace("/{AUTOREPLY_NO}/", "", $HTMLcontents);
				}
				else{
					$HTMLcontents = preg_replace("/{AUTOREPLY_YES}/", "", $HTMLcontents);
					$HTMLcontents = preg_replace("/{AUTOREPLY_NO}/", "checked", $HTMLcontents);
				}
		
				
			}
			
			// If they went AWOL on us then show them a message.
			if($currentStatus == "W"){
	
				// The Date of the last page they visited before going AWOL should be the status Date of the AWOL entry 
				$lastActivityTimeStamp = $memberAttendanceObj->getCurrentStatusDate($AuthObj->GetUserID());
				
				$HTMLcontents = preg_replace("/{MOST_RECENT_ACTVITY}/", date("n/j g:i a", $lastActivityTimeStamp), $HTMLcontents);
				$HTMLcontents = preg_replace("/{AWOL_HOURS}/", round(((time() - $lastActivityTimeStamp) / (60 * 60)), 1), $HTMLcontents);
				
				
			}
			else{
				$reg = "/<!--\s+BEGIN AWOLstatusBL\s+-->(.*)\s*<!--\s+END AWOLstatusBL\s+-->/sm";
				$HTMLcontents = preg_replace($reg, "", $HTMLcontents);
			}
			
	
	
	
			
	
		}
		else{
			// Discard the Drop Down Menu
			$reg = "/<!--\s+BEGIN StatusChangeBL\s+-->(.*)\s*<!--\s+END StatusChangeBL\s+-->/sm";
			$HTMLcontents = preg_replace($reg, "", $HTMLcontents);
			
			// Discard the Return Time Block When Customer Service Agents are away
			$reg = "/<!--\s+BEGIN ReturnTimeBL\s+-->(.*)\s*<!--\s+END ReturnTimeBL\s+-->/sm";
			$HTMLcontents = preg_replace($reg, "", $HTMLcontents);
			
			// Discard the AWOL Status Block
			$reg = "/<!--\s+BEGIN AWOLstatusBL\s+-->(.*)\s*<!--\s+END AWOLstatusBL\s+-->/sm";
			$HTMLcontents = preg_replace($reg, "", $HTMLcontents);
			
		}
	
		// Replace variables within the header 
		$HTMLcontents = preg_replace("/{NAME}/", WebUtil::htmlOutput($AdminName), $HTMLcontents);
		
		
		
		// Now build the Logos depending on what domains the user has selected.
		$domainObj = Domain::singleton();
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		// Find out if we want the user to update their password.
		if($userControlObj->getPasswordUpdateRequired() == "N"){
			// Discard the password update block
			$reg = "/<!--\s+BEGIN PasswordUpdateRequiredBL\s+-->(.*)\s*<!--\s+END PasswordUpdateRequiredBL\s+-->/sm";
			$HTMLcontents = preg_replace($reg, "", $HTMLcontents);
		}
		
		
		
		
		$domainIDsthatUserCanSee = $passiveAuthObj->getUserDomainsIDs();
		$selectedDomainIDsArr = $domainObj->getSelectedDomainIDs();
		
		
		if(sizeof($domainIDsthatUserCanSee) == 1){
			
			// If the user is only able to see one Domain ID... then show the only Domain that the user has permission to see.
			
			$domainLogosObj = new DomainLogos($domainIDsthatUserCanSee[0]);
			$HTMLcontents = preg_replace("/{DOMAIN_LOGOS}/", ("<a target='top' href='http://".Domain::getWebsiteURLforDomainID(current($domainIDsthatUserCanSee))."'><img src='./domain_logos/". $domainLogosObj->navBarIcon ."' border='0'></a>"), $HTMLcontents);
			
			$HTMLcontents = preg_replace("/{D}/", "", $HTMLcontents);			
			
		}
		else if(sizeof($selectedDomainIDsArr) == 1){
			
			// If can see multiple domains, but they have only selected one of them
			
			$domainLogosObj = new DomainLogos($selectedDomainIDsArr[0]);
			$HTMLcontents = preg_replace("/{DOMAIN_LOGOS}/", ("<a id='domainLink' href='#'><img border='0' src='./domain_logos/". $domainLogosObj->navBarIcon ."'></a>"), $HTMLcontents);
			
			
			$HTMLcontents = preg_replace("/{D}/", "", $HTMLcontents);	
		}
		else{
			
			

			// Since we have more than 1 domain selected, find out if we have a top Domain Set
			// If so then we should show the logo so that the person viewing the page knows what Domain the Order #, etc. is associated with. 
			if($domainObj->getTopDomainID() != NULL){
				
				$domainLogosObj = new DomainLogos($domainObj->getTopDomainID());
				$HTMLcontents = preg_replace("/{DOMAIN_LOGOS}/", ("<img border='0' src='./domain_logos/". $domainLogosObj->navBarIcon ."'>"), $HTMLcontents);

				// Even if a "Top-Domain" logo is showing, let them see a link to select other domains.
				if(sizeof($selectedDomainIDsArr) == sizeof($domainIDsthatUserCanSee))
					$HTMLcontents = preg_replace("/{D}/", "<a class='AdminTemplateLeftNavLink' id='domainLink' href='#' style='font-size:11px;'><b>ALL</b></a>", $HTMLcontents);
				else
					$HTMLcontents = preg_replace("/{D}/", "<a class='AdminTemplateLeftNavLink' id='domainLink' href='#' style='font-size:11px;'><b>".sizeof($selectedDomainIDsArr) . " of " . sizeof($domainIDsthatUserCanSee)."</b></a>", $HTMLcontents);
			}
			else{
			
				// If we aren't looking at a Top Domain... and the user has selected all of the domains... then show an icon for All.
				if(sizeof($selectedDomainIDsArr) == sizeof($domainIDsthatUserCanSee)){
					$HTMLcontents = preg_replace("/{DOMAIN_LOGOS}/", ("<a id='domainLink' href='#'><img border='0' src='./domain_logos/all-up.png' onMouseOver=\"this.src='./domain_logos/all-down.png'\" onMouseOut=\"this.src='./domain_logos/all-up.png'\"></a>"), $HTMLcontents);
				}
				else{
					// The User has more than 1 domain selected, but no domain is prefered.  
					// Don't show them any logos, just an indicator to tell them what has been selected.
					$HTMLcontents = preg_replace("/{DOMAIN_LOGOS}/", "<a class='AdminTemplateLeftNavLink' id='domainLink' style='font-size:14px; font-weight:bold;' href='#'>".sizeof($selectedDomainIDsArr) . " of " . sizeof($domainIDsthatUserCanSee)."</a>", $HTMLcontents);
				}

				$HTMLcontents = preg_replace("/{D}/", "", $HTMLcontents);	
			}
			
			

				

			
		}
		
		
		$HTMLcontents = preg_replace("/{FORM_SECURITY_CODE}/", WebUtil::getFormSecurityCode(), $HTMLcontents);
		
		if(sizeof($domainIDsthatUserCanSee) <= 2)
			$HTMLcontents = preg_replace("/{DOMAIN_WINDOW_HEIGHT}/", "1
			0", $HTMLcontents);
		else if(sizeof($domainIDsthatUserCanSee) == 3)
			$HTMLcontents = preg_replace("/{DOMAIN_WINDOW_HEIGHT}/", "230", $HTMLcontents);
		else if(sizeof($domainIDsthatUserCanSee) == 4)
			$HTMLcontents = preg_replace("/{DOMAIN_WINDOW_HEIGHT}/", "310", $HTMLcontents);
		else if(sizeof($domainIDsthatUserCanSee) == 5)
			$HTMLcontents = preg_replace("/{DOMAIN_WINDOW_HEIGHT}/", "400", $HTMLcontents);
		else if(sizeof($domainIDsthatUserCanSee) == 6)
			$HTMLcontents = preg_replace("/{DOMAIN_WINDOW_HEIGHT}/", "470", $HTMLcontents);
		else if(sizeof($domainIDsthatUserCanSee) > 6)
			$HTMLcontents = preg_replace("/{DOMAIN_WINDOW_HEIGHT}/", "550", $HTMLcontents);
			
			
		// Figure out if the user has permission to see the "More" link
		if(!$AuthObj->CheckForPermission("VIEW_DOMAIN_TOTALS"))
			$HTMLcontents = preg_replace("/<!-- BEGIN MoreButtonBL -->.*<!-- END MoreButtonBL -->/sm", "", $HTMLcontents);
			
		// If they are a guest user... Maybe just for Tempalte additions... then don't show them all of the other links in the NAV Bar.
		// Also show a different background graphic so it doesn't look like pieces are missing.
		if($AuthObj->CheckForPermission("MENU_GUEST")){
			$HTMLcontents = preg_replace("/<!-- BEGIN SearchForUsersBL -->.*<!-- END SearchForUsersBL -->/sm", "", $HTMLcontents);
			$HTMLcontents = preg_replace("/<!--\s+BEGIN StandardLinksBL\s+-->(.*)\s*<!--\s+END StandardLinksBL\s+-->/sm", "", $HTMLcontents);
			$HTMLcontents = preg_replace("/<!--\s+BEGIN MenuImageBL\s+-->(.*)\s*<!--\s+END MenuImageBL\s+-->/sm", "", $HTMLcontents);
			
			$HTMLcontents = preg_replace("/{TEMPLATE_HEADER_BACKGROUND}/", "template-header-basic.png", $HTMLcontents);
			
		}
		else{
			$HTMLcontents = preg_replace("/{TEMPLATE_HEADER_BACKGROUND}/", "template-header.png", $HTMLcontents);
		}
			
		return $HTMLcontents;
	}

	// Pass in a Tabs Object by reference.
	static function buildMainTabsForProductSetupScreen(&$tabsObj, $productID){
	
		$AuthObj = new Authenticate(Authenticate::login_ADMIN);
		
		if($AuthObj->CheckForPermission("EDIT_PRODUCT")){
			$tabsObj->AddTab("productMain", "Main", './ad_product_setup.php?view=productMain&editProduct=' . $productID);
			$tabsObj->AddTab("vendors", "Vendors", './ad_product_setup.php?view=vendors&editProduct=' . $productID);
			$tabsObj->AddTab("quantityPricing", "Quantities & Prices", './ad_product_setup.php?view=quantityPricing&editProduct=' . $productID);
			$tabsObj->AddTab("optionsPricing", "Options & Prices", './ad_product_setup.php?view=optionsPricing&editProduct=' . $productID);
			$tabsObj->AddTab("schedule", "Schedule", './ad_product_setup.php?view=schedule&editProduct=' . $productID);
			$tabsObj->AddTab("status", "Status Desc.", './ad_product_setup.php?view=status&editProduct=' . $productID);
		}
		if($AuthObj->CheckForPermission("EDIT_PDF_PROFILES")){
			$tabsObj->AddTab("pdfprofile", "PDF Profiles", './ad_pdfprofiles.php?view=start&editProduct=' . $productID);
		}
	}


	static function buildTabsForEmailNotify($t, $view){
	
		// Build the tabs
		$authObj = new Authenticate(Authenticate::login_ADMIN);
		$tabsObj = new Navigation();

		if($authObj->CheckForPermission("EMAIL_NOTIFY_MESSAGE_VIEW"))			
			$tabsObj->AddTab("messages", "Messages", './ad_emailNotifyMessageList.php?view=list');
			
		if($authObj->CheckForPermission("EMAIL_NOTIFY_EMAIL_ADDRESS_BATCHES")){
			$tabsObj->AddTab("batches", "Batches", './ad_emailNotifyCollectionBatch.php?view=start');
			$tabsObj->AddTab("importemail", "Import Email", './ad_emailCollection.php');
			$tabsObj->AddTab("jobreports", "Job Reports", './ad_emailNotifyJobReport.php');
			$tabsObj->AddTab("emailfilters", "Email Address Filters", './ad_emailCollectionPatternsToIgnore.php');
		}
			
		$t->set_var("EMAIL_NOTIFY_TABS", $tabsObj->GetTabsHTML($view));
		$t->allowVariableToContainBrackets("EMAIL_NOTIFY_TABS");
	}
	
}



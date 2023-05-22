<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();



set_time_limit(86400); // 1 day is the limit to send out reminders.  Otherwise we could build up recursive email batches if the list gets too long.

$messageType = WebUtil::GetInput("messageType", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$domainIDforReminders = WebUtil::GetInput("domainID", FILTER_SANITIZE_INT);

// For now we are going to hard code PrintsMadeEasy.com
$domainIDforReminders = 1;

if(!Domain::checkIfDomainIDexists($domainIDforReminders)){
	WebUtil::WebmasterError("Error with Reminders Emails. The DomainID doesn't exist: " . $domainIDforReminders);
	throw new Exception("Error with Domain ID.");
}

$couponObj = new Coupons($dbCmd2, $domainIDforReminders);

$domainURL = Domain::getWebsiteURLforDomainID($domainIDforReminders);
$domainKey = Domain::getDomainKeyFromID($domainIDforReminders);
$fullCompanyNameOfDomain = Domain::getFullCompanyNameForDomainID($domainIDforReminders);
$domainEmailConfigObj = new DomainEmails($domainIDforReminders);

$domainAddressObj = new DomainAddresses($domainIDforReminders);
$customerServiceAddressObj = $domainAddressObj->getCustomerServiceAddressObj();
$customerServicePhone = $customerServiceAddressObj->getPhoneNumber();

if(empty($customerServicePhone))
	$phoneNumberLine = "Please reply to this email if you need assistance";
else
	$phoneNumberLine = "Call us at $customerServicePhone or simply reply to this email if you need assistance.";


if($messageType == "NoOrders"){
	
	$couponName = "BIZ399218";

	$textMessageMaster = "Dear {NAME},\n\nYou registered for an account at $domainURL a while ago but haven't placed an order yet.  Is there anything that we can help you with?";
	$textMessageMaster .= "\n\nUse the following coupon to receive \$5.00 off your first order with us.  Use it for business cards, postcards, letterhead, envelopes, and more.";
	$textMessageMaster .= "\n\nWe also want you to know that we are constantly improving our website and the products that we sell.   We love to hear fresh ideas from our customers, so please send us feedback.";
	$textMessageMaster .= "\n\nRespectfully,\n" . WebUtil::htmlOutput($fullCompanyNameOfDomain) . "\n\n\n\n";
	$textMessageMaster .= "To opt-out of future emails visit the following URL.\nhttp://$domainURL/newsletter_unsubscribe.php?email={EMAIL}";
	
	$htmlMessageMaster = "<html>
	<body>
	<font size=4 face='Times New Roman'><span style='font-size:14.0pt'>Dear {NAME},<br>
	<br>
	You registered for an account at <a href='http://$domainURL/log.php?from=em-NoOrd-{ARTWORK}-Lnk-{PERIOD}&InitializeCoupon={COUPONCODE}'><span style='font-size:14.0pt'>$domainURL</span></a> a while ago but haven't placed an order yet.  Is there anything that we can help you with?<br>&nbsp;
	<table border=0 cellspacing=0 cellpadding=0 width=350>
	<tr>
	<td valign='top'>{THUMBNAIL}</td>
	<td valign='bottom'>{COMPANY_IMAGE}</td>
	</tr>
	</table>
	<br>
	$phoneNumberLine<br>
	<br>
	Use the following coupon to receive \$5.00 off your first order with us.  Use it for business cards, postcards, letterhead, envelopes, and more.<br>
	<b><font color='#993366'><span style='color:#993366;font-weight:bold'>{COUPONCODE}</span></font></b><br>
	<br>
	Respectfully,<br>
	".WebUtil::htmlOutput($fullCompanyNameOfDomain)."<br>
	</span></font>
	<br>	
	<br><br>
	<font size=4 face='Times New Roman'><span style='font-size:14.0pt'>We also want you to know that we are constantly improving our website and the products that we sell.   We love to hear fresh ideas from our customers, so please send us feedback.</span></font><br><br>
	<font size=3 face='Times New Roman'><span style='font-size:12.0pt'>To opt-out of future emails, <a href='http://$domainURL/newsletter_unsubscribe.php?email={EMAIL}'><font size=3 face='Times New Roman'><span style='font-size:12.0pt'>visit this link</span></font></a>.</span></font><br>
	<br>";


	$htmlMessageMaster .= "
	</body>
	</html>
	";

	// Gather a list of people which have registered for an account, but have not ordered yet.
	$userIDsRegisteredInPast = array();
	
	
	// Determine intervals.
	$intervalsByDayBackArr = array(1,3,5,8,11,15, 20, 30, 50, 90, 130, 170, 210, 260, 310, 360, 420, 500, 600, 800, 1000);
	
	foreach($intervalsByDayBackArr as $thisDaysBack){
		$userIDArr = GetUserIDsRegisteredByDaysBack($dbCmd, $thisDaysBack, $domainIDforReminders);
		$userIDsRegisteredInPast = array_merge($userIDsRegisteredInPast, $userIDArr);
	}
	
	// Make a parallel array (to the User Id's) with the domain of every email address
	// We are going to de cluster the email addressed so that Yahoo/Hotmail addresses don't show up next to one another back-to-back.
	$emailDomainNamesArr = array();
	foreach($userIDsRegisteredInPast as $userIdToDeCluster){
		$emailAddress = UserControl::GetEmailByUserID($dbCmd, $userIdToDeCluster);
		
		// Strip off the @ symbol, and everything that comes before it.
		$emailDomainNamesArr[] = strtolower(preg_replace("/^[^@]+@/", "", $emailAddress));
	}
	
	$userIDsRegisteredInPast = WebUtil::arrayDecluster($emailDomainNamesArr, $userIDsRegisteredInPast);

	

	// Create a Random Sample... be sure to Also change the MAIL-TO address below
	/*
	$tempArr = array();
	for($i=100; $i<=150; $i++){
		if(isset($userIDsRegisteredInPast[$i]))
			$tempArr[] = $userIDsRegisteredInPast[$i];
	}
	$userIDsRegisteredInPast = $tempArr;
	*/


	$totlalRegistrationCounts = sizeof($userIDsRegisteredInPast);
	
	print "TotalUserCount = " . $totlalRegistrationCounts . "<br>\n\n";


	$emailCount = 0;
	$emailsWithThumbnailsCount = 0;

	// send out emails from Test Accounts
	/*
	$userIDsRegisteredInPast = array();
	// --- Me -------
	$userIDsRegisteredInPast[] = 99708;
	$userIDsRegisteredInPast[] = 2;
	// --- Brian Whiteman's
	// $userIDsRegisteredInPast[] = 97829;
	// $userIDsRegisteredInPast[] = 67830;
	*/
	


	foreach($userIDsRegisteredInPast as $thisUserID){

		$dbCmd->Query("SELECT COUNT(*) FROM orders WHERE UserID=" . $thisUserID);
		$orderCount = $dbCmd->GetValue();

		$domainIDofUser = UserControl::getDomainIDofUser($thisUserID);

		if($orderCount == 0){

			$emailCount++;

			$userName = UserControl::GetNameByUserID($dbCmd, $thisUserID);
			$userEmail = UserControl::GetEmailByUserID($dbCmd, $thisUserID);
			
			$nameParts = UserControl::GetPartsFromFullName($userName);

			// Get a new message with all of the variables in place.
			$htmlMessage = $htmlMessageMaster;
			$textMessage = $textMessageMaster;
			
			$htmlMessage = preg_replace("/{NAME}/", WebUtil::htmlOutput(ucfirst(strtolower($nameParts["First"]))), $htmlMessage);
			$htmlMessage = preg_replace("/{EMAIL}/", WebUtil::htmlOutput($userEmail), $htmlMessage);
			
			$textMessage = preg_replace("/{NAME}/", WebUtil::htmlOutput(ucfirst(strtolower($nameParts["First"]))), $textMessage);
			$textMessage = preg_replace("/{EMAIL}/", WebUtil::htmlOutput($userEmail), $textMessage);


			$MimeObj = new Mail_mime();

			$thumbNailImageFound = false;
			
			// Find out if the user has a saved project... so we can get the thumbnail image.
			$dbCmd->Query("SELECT ID FROM projectssaved WHERE UserID=" . $thisUserID . " ORDER BY ID ASC LIMIT 1");
			$lastSavedProjectID = $dbCmd->GetValue();
			if($lastSavedProjectID){
			
				$thumbImageData =& ThumbImages::GetProjectThumbnailImage($dbCmd, "projectssaved", $lastSavedProjectID);
				
				if(!empty($thumbImageData)){
					$thumbNailImageFound = true;

					// Create a temporary file on disk 
					$tmpfname = FileUtil::newtempnam(Constants::GetTempDirectory(), "Th", ".jpg", time());


					// Put image data into the temp file 
					$fp = fopen($tmpfname, "w");
					fwrite($fp, $thumbImageData);
					fclose($fp);


					// Get the dimensions of the Thumbnail Image.
					$imageDimHash = ImageLib::GetDimensionsFromImageFile($tmpfname);
					$thumbWidth = $imageDimHash["Width"];
					$thumbHeight = $imageDimHash["Height"];


					$MimeObj->addHTMLImage($tmpfname, 'image/jpg');
					$inlineImageHTML = "cid:" . $MimeObj->getLastHTMLImageCid();


					// Insert the Image HTML instead of just a blank space within the Message Body
					$imageHTML = "<a href='http://$domainURL/log.php?from=em-NoOrd-{ARTWORK}-Thmb-{PERIOD}&dest=SavedProjects.php&InitializeCoupon={COUPONCODE}'><img border=0 width=$thumbWidth height=$thumbHeight alt='The last artwork you saved.' src='$inlineImageHTML'></a>";
					$htmlMessage = preg_replace("/{THUMBNAIL}/", "<font size='2' color='660000'>Remember designing this?</font><br>$imageHTML", $htmlMessage);

					$emailsWithThumbnailsCount++;

					@unlink($tmpfname);
				}
			
			}
			
			// Since they don't have a thumbnail saved, then just show a blank space in its place.
			if(!$thumbNailImageFound){	
				$htmlMessage = preg_replace("/{THUMBNAIL}/", "&nbsp;", $htmlMessage);
			}
			
			
			// Add the company image... If we have a Thumbnail then use the photo of Smudge Staring at the design.
			if($thumbNailImageFound){
				$fileNameOfImage = "Smudge3.jpg";
			}
			else{
				$fileNameOfImage = "Smudge1.jpg";
			}
			
			$companyImage = Constants::GetWebserverBase() . "/newsletters/" . $fileNameOfImage;
				
			if(!file_exists($companyImage)){
				WebUtil::WebmasterError("The newsletter image could not be found: $companyImage");
				throw new Exception("The newsletter image could not be found: $companyImage");
			}
			

			// Get the dimensions of the Company Image.
			$imageDimHash = ImageLib::GetDimensionsFromImageFile($companyImage);
			$imgWidth = $imageDimHash["Width"];
			$imgHeight = $imageDimHash["Height"];
			
			
			$MimeObj->addHTMLImage($companyImage, 'image/jpg');
			$inlineSmudgeImage_JPG = "cid:" . $MimeObj->getLastHTMLImageCid();
			
			$companyImageHTML = "<a href='http://$domainURL/log.php?from=em-NoOrd-{ARTWORK}-Img-{PERIOD}&dest=&InitializeCoupon={COUPONCODE}'><img border=0 width=$imgWidth height=$imgHeight alt='No Monkey Business!' src='$inlineSmudgeImage_JPG'></a>";
			$htmlMessage = preg_replace("/{COMPANY_IMAGE}/", $companyImageHTML, $htmlMessage);
			

		



			// For the tracking code we want to put the Date Created timestamp within a range.
			$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateCreated) AS DateCreated, Address FROM users WHERE ID=$thisUserID");
			$userRow = $dbCmd->GetRow();
			
			$dateCreated = $userRow["DateCreated"];
			$Address = $userRow["Address"];
			
			
			$todayTimeStamp = time();
			$OneWeeksStamp = $todayTimeStamp - 60 * 60 * 24 * 7;
			$TwoWeeksStamp = $todayTimeStamp - 60 * 60 * 24 * 14;
			$OneMonthStamp = $todayTimeStamp - 60 * 60 * 24 * 31;
			$TwoMonthsStamp = $todayTimeStamp - 60 * 60 * 24 * 62;
			$ThreeMonthsStamp = $todayTimeStamp - 60 * 60 * 24 * 93;
			$SixMonthsStamp = $todayTimeStamp - 60 * 60 * 24 * 186;
			$OneYearStamp = $todayTimeStamp - 60 * 60 * 24 * 360;
			$TwoYearsStamp = $todayTimeStamp - 60 * 60 * 24 * 720;

			
			if($dateCreated < $TwoYearsStamp)
				$TimeTrackingCode = "greater2yr";
			else if($dateCreated < $OneYearStamp)
				$TimeTrackingCode = "1to2years";
			else if($dateCreated < $SixMonthsStamp)
				$TimeTrackingCode = "6mnthto1yr";
			else if($dateCreated < $ThreeMonthsStamp)
				$TimeTrackingCode = "3to6mnth";
			else if($dateCreated < $TwoMonthsStamp)
				$TimeTrackingCode = "2to3mnth";
			else if($dateCreated < $OneMonthStamp)
				$TimeTrackingCode = "1to2mnth";
			else if($dateCreated < $TwoWeeksStamp)
				$TimeTrackingCode = "2wkto1mnth";
			else if($dateCreated < $OneWeeksStamp)
				$TimeTrackingCode = "1to2wks";
			else
				$TimeTrackingCode = "less1wk";


			
			$htmlMessage = preg_replace("/{PERIOD}/", $TimeTrackingCode, $htmlMessage);
			$textMessage = preg_replace("/{PERIOD}/", $TimeTrackingCode, $textMessage);

	
	
			if($thumbNailImageFound)
				$htmlMessage = preg_replace("/{ARTWORK}/", "Y", $htmlMessage);
			else
				$htmlMessage = preg_replace("/{ARTWORK}/", "N", $htmlMessage);

				
			$htmlMessage = preg_replace("/{COUPONCODE}/", $couponName, $htmlMessage);
			$textMessage = preg_replace("/{COUPONCODE}/", $couponName, $textMessage);
		
			$domainEmailConfigObj = new DomainEmails($domainIDofUser);
				
			// It is better to have the Text/Plain part come first in case the client can't understand multi-part messages.
			$MimeObj->setTXTBody($textMessage);
			$MimeObj->setHTMLBody($htmlMessage);
			
			$MimeObj->setSubject($Address . " - Ready to Ship From " . Domain::getAbreviatedNameForDomainID($domainIDofUser));
			$MimeObj->setFrom($domainEmailConfigObj->getEmailNameOfType(DomainEmails::CUSTSERV) . " <" . $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::CUSTSERV) . ">");


			$body = $MimeObj->get();
			$hdrs = $MimeObj->headers();
			
			// Change the headers and return envelope information for the SendMail command.
			// We don't want emails from different domains to look like they are coming from the same mail server.
			$hdrs["Message-Id"] =   "<" . substr(md5(uniqid(microtime())), 0, 15) . "@" . Domain::getDomainKeyFromID($domainIDofUser) . ">";
			$additionalSendMailParameters = "-r " . $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::CUSTSERV);

			// Outlook doesn't recognize it as an inline (but as attachemnt) if we have an @domain.com in the Content-ID: !!! mine.php generates cid@domian.com other mailers just cid
			$body = preg_replace("/%40" . preg_quote(Domain::getDomainKeyFromID($domainIDofUser)) . "/i","",$body);
			
			$mailObj = new Mail();
			$mailObj->send(($nameParts["First"] . " <$userEmail>"), $hdrs, $body, $additionalSendMailParameters);
			//$mailObj->send(($nameParts["First"] . " <".Constants::GetAdminEmail().">"), $hdrs, $body, $additionalSendMailParameters);

			//file_put_contents("/home/printsma/reminder_DeadAccountEmails.txt", (date("F j, Y, g:i a") . "\t" . $userEmail . "\n"), FILE_APPEND);

			print "UserID: $thisUserID <br>\n";
			
			unset($MimeObj);
			unset($mailObj);
			
			Constants::FlushBufferOutput();

			sleep(60);

		}
	}


	$AdminSubject = "$domainKey Email: Dead Accounts. $emailCount emails were sent out and $emailsWithThumbnailsCount of them had Thumbnail Images.";
	$AdminBody = "A total of $totlalRegistrationCounts new accounts were scanned giving us a Dead Account ratio of " . round($emailCount / $totlalRegistrationCounts * 100, 1) . "% within this period.";
	
	$emailContactsForReportsArr = Constants::getEmailContactsForServerReports();
	$emailContactsForReportsArr[] = "laurie@printsmadeeasy.com";
	foreach($emailContactsForReportsArr as $thisEmailContact)
		WebUtil::SendEmail("E-Mail Notify", Constants::GetMasterServerEmailAddress(), "", $thisEmailContact, $AdminSubject, $AdminBody, true);

	print "<hr>Done Sending Dead Account Reminder Emails.";
	
}
else if($messageType == "ReOrder"){

	$textMessageMaster = "Dear {NAME},\n\nIt has been a while since you last ordered from " . $domainURL . ". \n\nYou must be running low on supplies by now.   Place another order within the next 7 days and get $5 off with the coupon code {COUPONCODE}.\n\nWe also want you to know that we are constantly improving our website and the products that we sell. We love to hear fresh ideas from our customers, so please send us feedback.";
	$textMessageMaster .= "\n\nRespectfully,\n" . WebUtil::htmlOutput($fullCompanyNameOfDomain) . "\n\n\nTo opt-out of future emails visit this link\nhttp://$domainURL/newsletter_unsubscribe.php?email={EMAIL}";
	
	
	$htmlMessageMaster = "<html>
	<body>
	<font size=4 face='Times New Roman'><span style='font-size:14.0pt'>Dear {NAME},<br>
	<br>
	It has been a while since you last ordered from <a href='http://$domainURL/log.php?from=em-ReOrder-{ARTWORK}-Lnk-{PERIOD}&InitializeCoupon={COUPONCODE}'><span style='font-size:14.0pt'>$domainURL</span></a>.
	You must be running low on supplies by now.   Place another order within the next 7 days and get $5 off with the coupon code <b><font color='#993366'><span style='color:#993366;font-weight:bold'>{COUPONCODE}</span></font></b>.

	<br>&nbsp;
	
	<table border=0 cellspacing=0 cellpadding=0 width=350>
	<tr>
	<td valign='top'>{THUMBNAIL}</td>
	<td valign='bottom'>{COMPANY_IMAGE}</td>
	</tr>
	</table>
	<br>
	$phoneNumberLine<br>
	<br>
	We also want you to know that we are constantly improving our website and the products that we sell. We love to hear fresh ideas from our customers, so please send us feedback.
	<br><br>
	Respectfully,<br>
	".WebUtil::htmlOutput($fullCompanyNameOfDomain)."<br>
	</span></font>
	<br>	
	<br><br>
	<font size=3 face='Times New Roman'><span style='font-size:12.0pt'>To opt-out of future emails, <a href='http://$domainURL/newsletter_unsubscribe.php?email={EMAIL}'><font size=3 face='Times New Roman'><span style='font-size:12.0pt'>visit this link</span></font></a>.</span></font><br>
	<br>";
	

	$htmlMessageMaster .= "
	</body>
	</html>
	";

	
	
	
	// Make sure that we have a coupon for each month (based upon month/year)... that has an expiration 1 month later.
	$couponName = "ReOrder" . substr(date("F"), 0, 1) . "RF" . date("n") . date("y");
	

	if(!$couponObj->CheckIfCouponCodeExists($couponName)){
	
		// Make sure that "Email" category exists... if not then create it.
		$dbCmd->Query("SELECT ID FROM couponcategories WHERE Name LIKE 'Email' AND DomainID=" . intval($domainIDforReminders));
		$couponCategoryID = $dbCmd->GetValue();
		
		if(empty($couponCategoryID))
			$couponCategoryID = $dbCmd->InsertQuery("couponcategories", array("Name"=>"Email", "DomainID"=>$domainIDforReminders));
	
		
		$couponObj->SetCouponCode($couponName);
		$couponObj->SetCouponName("Re-order Reminder");
		$couponObj->SetCouponCategoryID($couponCategoryID);
		$couponObj->SetCouponMaxAmountType("order");
		$couponObj->SetCouponMaxAmount("5.00");
		$couponObj->SetCouponDiscountPercent("100");
		$couponObj->SetCouponUsageLimit("1");
		$couponObj->SetCouponComments("For reminder emails going out in the month of " . date("m") . "/" . date("y"));
		$couponObj->SetCouponExpDate(time() + 60 * 60 * 24 * 60);
		$couponObj->SetCouponCreatorUserID(2);
	
		$couponObj->SaveNewCouponInDB();
	}
	

	// Gather a list of people which have registered for an account, but have not ordered yet.
	$userIDsRegisteredInPast = array();
	
	
	// Determine intervals.
	$intervalsByDayBackArr = array(70, 100, 130, 160, 190, 220, 250, 280, 310, 330, 360, 390, 420, 450, 480, 510, 540, 570, 600, 630, 660, 700, 750, 800, 900, 1000, 1100, 1200, 1300, 1400, 1500, 1600, 1700, 1800, 1900, 2000, 2300, 2600, 3000);
	
	foreach($intervalsByDayBackArr as $thisDaysBack){
		$userIDArr = GetUserIDsRegisteredByDaysBack($dbCmd, $thisDaysBack, $domainIDforReminders);
		$userIDsRegisteredInPast = array_merge($userIDsRegisteredInPast, $userIDArr);
	}
	
	
	// Make a parallel array (to the User Id's) with the domain of every email address
	// We are going to de cluster the email addressed so that Yahoo/Hotmail addresses don't show up next to one another back-to-back.
	$emailDomainNamesArr = array();
	foreach($userIDsRegisteredInPast as $userIdToDeCluster){
		$emailAddress = UserControl::GetEmailByUserID($dbCmd, $userIdToDeCluster);
		
		// Strip off the @ symbol, and everything that comes before it.
		$emailDomainNamesArr[] = strtolower(preg_replace("/^[^@]+@/", "", $emailAddress));
	}
	
	$userIDsRegisteredInPast = WebUtil::arrayDecluster($emailDomainNamesArr, $userIDsRegisteredInPast);

	

	// Create a Random Sample... be sure to Also change the MAIL-TO address below
	/*
	$tempArr = array();
	for($i=600; $i<=625; $i++){
		if(isset($userIDsRegisteredInPast[$i]))
			$tempArr[] = $userIDsRegisteredInPast[$i];
	}
	$userIDsRegisteredInPast = $tempArr;
	*/
	

	$totlalRegistrationCounts = sizeof($userIDsRegisteredInPast);
	
	print "TotalUserCount = " . $totlalRegistrationCounts . "<br>\n\n";


	$emailCount = 0;
	$emailsWithThumbnailsCount = 0;
	
	// How many days since the last order will cause an email to get sent.
	$HowManyDaysSinceLastOrder = 60;

	// send out emails from Test Accounts
	/*
	$userIDsRegisteredInPast = array();
	// --- Me -------
	$userIDsRegisteredInPast[] = 99708;
	$userIDsRegisteredInPast[] = 2;
	// --- Brian Whiteman's
	// $userIDsRegisteredInPast[] = 97829;
	// $userIDsRegisteredInPast[] = 67830;
	*/


	foreach($userIDsRegisteredInPast as $thisUserID){

		$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateOrdered) AS DateOrdered FROM orders WHERE UserID=" . $thisUserID . " ORDER BY ID DESC LIMIT 1");
		
		$lastOrderedTimeStamp = $dbCmd->GetValue();
		
		
		
		if($lastOrderedTimeStamp > $HowManyDaysSinceLastOrder * 60 * 60 * 24){
			
			$domainIDofUser = UserControl::getDomainIDofUser($thisUserID);

			$emailCount++;

			$userName = UserControl::GetNameByUserID($dbCmd, $thisUserID);
			$userEmail = UserControl::GetEmailByUserID($dbCmd, $thisUserID);
			
			$nameParts = UserControl::GetPartsFromFullName($userName);

			// Get a new message with all of the variables in place.
			$htmlMessage = $htmlMessageMaster;
			$textMessage = $textMessageMaster;
			
			$htmlMessage = preg_replace("/{NAME}/", WebUtil::htmlOutput(ucfirst(strtolower($nameParts["First"]))), $htmlMessage);
			$htmlMessage = preg_replace("/{EMAIL}/", WebUtil::htmlOutput($userEmail), $htmlMessage);
			
			$textMessage = preg_replace("/{NAME}/", WebUtil::htmlOutput(ucfirst(strtolower($nameParts["First"]))), $textMessage);
			$textMessage = preg_replace("/{EMAIL}/", WebUtil::htmlOutput($userEmail), $textMessage);
			


			$MimeObj = new Mail_mime();

			$thumbNailImageFound = false;
			
			// Find out if the user has a saved project... so we can get the thumbnail image.
			$dbCmd->Query("SELECT projectssaved.ID FROM projectssaved INNER JOIN projectsordered ON projectssaved.ID = projectsordered.SavedID  WHERE projectssaved.UserID=" . $thisUserID . " ORDER BY projectsordered.ID DESC LIMIT 1");
			$lastSavedProjectID = $dbCmd->GetValue();

			// If they don't have a thumbnail image linked up from their last Project... then get the first design that they saved... get the first instead of the last to avoid the "Make a Copy" thumbnails.
			if(empty($lastSavedProjectID)){
				$dbCmd->Query("SELECT ID FROM projectssaved WHERE UserID=" . $thisUserID . " ORDER BY ID ASC LIMIT 1");
				$lastSavedProjectID = $dbCmd->GetValue();
			}
			
			
			if($lastSavedProjectID){
			
				$thumbImageData =& ThumbImages::GetProjectThumbnailImage($dbCmd, "projectssaved", $lastSavedProjectID);
				
				if(!empty($thumbImageData)){
					$thumbNailImageFound = true;

					// Create a temporary file on disk 
					$tmpfname = FileUtil::newtempnam(Constants::GetTempDirectory(), "Th", ".jpg", time());


					// Put image data into the temp file 
					$fp = fopen($tmpfname, "w");
					fwrite($fp, $thumbImageData);
					fclose($fp);


					// Get the dimensions of the Thumbnail Image.
					$imageDimHash = ImageLib::GetDimensionsFromImageFile($tmpfname);
					$thumbWidth = $imageDimHash["Width"];
					$thumbHeight = $imageDimHash["Height"];


					$MimeObj->addHTMLImage($tmpfname, 'image/jpg');
					$inlineImageHTML = "cid:" . $MimeObj->getLastHTMLImageCid();


					// Insert the Image HTML instead of just a blank space within the Message Body
					$imageHTML = "<a href='http://$domainURL/log.php?from=em-ReOrder-{ARTWORK}-Thmb-{PERIOD}&dest=SavedProjects.php&InitializeCoupon={COUPONCODE}'><img border=0 width=$thumbWidth height=$thumbHeight alt='The last artwork you saved.' src='$inlineImageHTML'></a>";
					$htmlMessage = preg_replace("/{THUMBNAIL}/", "<font size='2' color='660000'>Remember designing this?</font><br>$imageHTML", $htmlMessage);

					$emailsWithThumbnailsCount++;

					@unlink($tmpfname);
				}
			
			}
			
			// Since they don't have a thumbnail saved, then just show a blank space in its place.
			if(!$thumbNailImageFound){	
				$htmlMessage = preg_replace("/{THUMBNAIL}/", "&nbsp;", $htmlMessage);
			}
			
			
			// Add the company image... If we have a Thumbnail then use the photo of Smudge Staring at the design.
			if($thumbNailImageFound){
				$fileNameOfImage = "Smudge3.jpg";
			}
			else{
				$fileNameOfImage = "Smudge1.jpg";
			}
			
			$companyImage = Constants::GetWebserverBase() . "/newsletters/" . $fileNameOfImage;
				
			if(!file_exists($companyImage)){
				WebUtil::WebmasterError("The newsletter image could not be found: $companyImage");
				throw new Exception("The newsletter image could not be found: $companyImage");
			}
			

			// Get the dimensions of the Company Image.
			$imageDimHash = ImageLib::GetDimensionsFromImageFile($companyImage);
			$imgWidth = $imageDimHash["Width"];
			$imgHeight = $imageDimHash["Height"];
			
			$MimeObj->addHTMLImage($companyImage, 'image/jpg');
			$inlineSmudgeImage_JPG = "cid:" . $MimeObj->getLastHTMLImageCid();
			
			$companyImageHTML = "<a href='http://$domainURL/log.php?from=em-ReOrder-{ARTWORK}-Img-{PERIOD}&dest=&InitializeCoupon={COUPONCODE}'><img border=0 width=$imgWidth height=$imgHeight alt='No Monkey Business!' src='$inlineSmudgeImage_JPG'></a>";
			$htmlMessage = preg_replace("/{COMPANY_IMAGE}/", $companyImageHTML, $htmlMessage);
			

			
		


			// For the tracking code we want to put the Date Created timestamp within a range.
			$dbCmd->Query("SELECT Address FROM users WHERE ID=$thisUserID");
			$Address = $dbCmd->GetValue();
			
			
			$todayTimeStamp = time();
			$OneWeeksStamp = $todayTimeStamp - 60 * 60 * 24 * 7;
			$TwoWeeksStamp = $todayTimeStamp - 60 * 60 * 24 * 14;
			$OneMonthStamp = $todayTimeStamp - 60 * 60 * 24 * 31;
			$TwoMonthsStamp = $todayTimeStamp - 60 * 60 * 24 * 62;
			$ThreeMonthsStamp = $todayTimeStamp - 60 * 60 * 24 * 93;
			$SixMonthsStamp = $todayTimeStamp - 60 * 60 * 24 * 186;
			$OneYearStamp = $todayTimeStamp - 60 * 60 * 24 * 360;
			$TwoYearsStamp = $todayTimeStamp - 60 * 60 * 24 * 720;

			
			if($lastOrderedTimeStamp < $TwoYearsStamp)
				$TimeTrackingCode = "greater2yr";
			else if($lastOrderedTimeStamp < $OneYearStamp)
				$TimeTrackingCode = "1to2years";
			else if($lastOrderedTimeStamp < $SixMonthsStamp)
				$TimeTrackingCode = "6mnthto1yr";
			else if($lastOrderedTimeStamp < $ThreeMonthsStamp)
				$TimeTrackingCode = "3to6mnth";
			else if($lastOrderedTimeStamp < $TwoMonthsStamp)
				$TimeTrackingCode = "2to3mnth";
			else if($lastOrderedTimeStamp < $OneMonthStamp)
				$TimeTrackingCode = "1to2mnth";
			else if($lastOrderedTimeStamp < $TwoWeeksStamp)
				$TimeTrackingCode = "2wkto1mnth";
			else if($lastOrderedTimeStamp < $OneWeeksStamp)
				$TimeTrackingCode = "1to2wks";
			else
				$TimeTrackingCode = "less1wk";


			
			$htmlMessage = preg_replace("/{PERIOD}/", $TimeTrackingCode, $htmlMessage);
			$textMessage = preg_replace("/{PERIOD}/", $TimeTrackingCode, $textMessage);

	
			if($thumbNailImageFound)
				$htmlMessage = preg_replace("/{ARTWORK}/", "Y", $htmlMessage);
			else
				$htmlMessage = preg_replace("/{ARTWORK}/", "N", $htmlMessage);

	
			$htmlMessage = preg_replace("/{COUPONCODE}/", $couponName, $htmlMessage);
			$textMessage = preg_replace("/{COUPONCODE}/", $couponName, $textMessage);

			
			// It is better to have the Text/Plain part come first in case the client can't understand multi-part messages.
			$MimeObj->setTXTBody($textMessage);
			$MimeObj->setHTMLBody($htmlMessage);
			
			$MimeObj->setSubject($Address . " - Ready to Ship From " . Domain::getAbreviatedNameForDomainID($domainIDofUser));
			$MimeObj->setFrom($domainEmailConfigObj->getEmailNameOfType(DomainEmails::CUSTSERV) . " <" . $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::CUSTSERV) . ">");


			$body = $MimeObj->get();
			$hdrs = $MimeObj->headers();

			
			// Change the headers and return envelope information for the SendMail command.
			// We don't want emails from different domains to look like they are coming from the same mail server.
			$hdrs["Message-Id"] =  "<" . substr(md5(uniqid(microtime())), 0, 15) . "@" . $domainKey . ">";
			$additionalSendMailParameters = "-r " . $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::CUSTSERV);
			
			// Outlook doesn't recognize it as an inline (but as attachemnt) if we have an @domain.com in the Content-ID: !!! mine.php generates cid@domian.com other mailers just cid
			$body = preg_replace("/%40" . preg_quote($domainKey) . "/i","",$body);
			
			$mailObj = new Mail();
			$mailObj->send(($nameParts["First"] . " <$userEmail>"), $hdrs, $body, $additionalSendMailParameters);
			//$mailObj->send(($nameParts["First"] . " <".Constants::GetAdminEmail().">"), $hdrs, $body, $additionalSendMailParameters);

			file_put_contents("/home/printsma/reminder_ReorderEmails.txt", (date("F j, Y, g:i a") . "\t" . $userEmail . "\n"), FILE_APPEND);

			print "UserID: $thisUserID <br>\n";
			
			unset($MimeObj);
			unset($mailObj);
			
			Constants::FlushBufferOutput();

			sleep(25);

		}
	}


	$AdminSubject = "$domainKey Email: Reorder Reminders. $emailCount emails were sent and $emailsWithThumbnailsCount of them had thumbnail images. ";
	$AdminBody = "A total of $totlalRegistrationCounts accounts were scanned, having no re-orders after $HowManyDaysSinceLastOrder days.   That is giving us a non-reorder ratio of " . round($emailCount / $totlalRegistrationCounts * 100, 1) . "% within the period.";
	
	$emailContactsForReportsArr = Constants::getEmailContactsForServerReports();
	$emailContactsForReportsArr[] = "laurie@printsmadeeasy.com";
	foreach($emailContactsForReportsArr as $thisEmailContact)
		WebUtil::SendEmail("E-Mail Notify", Constants::GetMasterServerEmailAddress(), "", $thisEmailContact, $AdminSubject, $AdminBody, true);


	print "<hr>Done Sending Re-Order Reminder Emails.";
	
}
else{

	WebUtil::WebmasterError("Illegal Message Type in server_reminderemails.php");
	throw new Exception("Illegal Message Type");
}




function GetUserIDsRegisteredByDaysBack(DbCmd $dbCmd, $numberOfDaysBack, $domainID){

	$userIDQuery = "SELECT ID FROM users WHERE DomainID=".intval($domainID)." AND Newsletter='Y' AND DateCreated BETWEEN " . date("YmdHis", mktime (0,0,0,date("n"),(date("j") - $numberOfDaysBack), date("Y")) ) . " AND " . date("YmdHis", mktime (0,0,0,date("n"),(date("j") - ($numberOfDaysBack - 1)), date("Y")) ) ;
	$dbCmd->Query($userIDQuery );
	

	$returnArr = array();

	
	while($thisUserID = $dbCmd->GetValue())
		$returnArr[] = $thisUserID;
	
	return $returnArr;
}

?>
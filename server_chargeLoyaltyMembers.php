<?

require_once("library/Boot_Session.php");


// Make this script be able to run for 5 hours.
set_time_limit(18000);
ini_set("memory_limit", "256M");



$dbCmd = new DbCmd();



$dbCmd->Query("SELECT ID FROM users WHERE LoyaltyProgram='Y'");
$loyaltyMembersUserIDs = $dbCmd->GetValueArr();

$chargeCounter = 0;
$newCounter = 0;
$repeatCounter = 0;
$newFeesSucess = 0;
$newFeesFailed = 0;
$repeatFeesSuccess = 0;
$repeatFeesFailed = 0;
$failedCounter = 0;
$newFailed = 0;
$repeatFailed = 0;

$totalFailedAttemptsFromRepeat = 0;
$totalSuccessfullAttemptsFromRepeat = 0;
$failedExplanationsNewHash = array();
$failedExplanationsRepeatHash = array();

foreach($loyaltyMembersUserIDs as $thisUserID){
	
	$loyaltyObj = new LoyaltyProgram(UserControl::getDomainIDofUser($thisUserID));
	
	if($loyaltyObj->shouldCustomerBeChargedToday($thisUserID)){
		
		$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateOrdered) FROM orders WHERE UserID=" . intval($thisUserID) . " ORDER BY ID ASC LIMIT 1");
		$dateOfFirstOrder = $dbCmd->GetValue();
		
		$skipLog = "";
		// We made a mistake charging a bunch of people too early on the first "repeat day".
		// We are not going to charge those people until the following month.
		// So as late has October 5th, 2010 (to give extra padding)... 
		// ... we want to make sure that the user has not been charged twice already.
		// As long as they have no more than 1 charge by this date, we can attemp a 2nd charge.
		if(time() < mktime(0, 0, 0, 10, 5, 2010)){
			$dbCmd->Query("SELECT COUNT(*) FROM loyaltycharges USE INDEX(loyaltycharges_UserID)
							WHERE UserID=" . intval($thisUserID));	
			$chargeCount = $dbCmd->GetValue();
			if($chargeCount > 1){
				$skipLog .= "Skipping: U" . $thisUserID . " because they were charged too early.\n";
				continue;
			}
		}
		
		$isNewCustomer = true;
		if((time() - $dateOfFirstOrder) > (60*60*24*15))
			$isNewCustomer = false;

		if($isNewCustomer){
			$newCounter++;
		}
		else{
			$repeatCounter++;
		}
		
		if(!$loyaltyObj->chargeCustomerLoyaltyEnrollment($thisUserID)){
			$failedCounter++;
			
			if($isNewCustomer){
				$newFailed++;
				
				$dbCmd->Query("SELECT MissedReasonDesc FROM loyaltymissedcharges WHERE UserID=" . intval($thisUserID));
				while($missedReason = $dbCmd->GetValue()){
					if(!isset($failedExplanationsRepeatHash[$missedReason]))
						$failedExplanationsNewHash[$missedReason] = 1;
					else 
						$failedExplanationsNewHash[$missedReason]++;
				}
			}
			else {
				$repeatFailed++;
				
				$totalSuccessfullAttemptsFromRepeat += $loyaltyObj->getCountOfSuccessfulChargesFromUser($thisUserID);
				$totalFailedAttemptsFromRepeat += $loyaltyObj->getCountOfFailedChargesFromUser($thisUserID);
				
				
				$dbCmd->Query("SELECT MissedReasonDesc FROM loyaltymissedcharges WHERE UserID=" . intval($thisUserID));
				while($missedReason = $dbCmd->GetValue()){
					if(!isset($failedExplanationsRepeatHash[$missedReason]))
						$failedExplanationsRepeatHash[$missedReason] = 1;
					else 
						$failedExplanationsRepeatHash[$missedReason]++;
				}
			}
			
			if($isNewCustomer){
				$newFeesFailed += $loyaltyObj->getMontlyFee($thisUserID);
			}
			else{
				$repeatFeesFailed += $loyaltyObj->getMontlyFee($thisUserID);
			}
			
		}
		else{
			if($isNewCustomer){
				$newFeesSucess += $loyaltyObj->getMontlyFee($thisUserID);
			}
			else{
				$repeatFeesSuccess += $loyaltyObj->getMontlyFee($thisUserID);
			}
		}
			
		
		print "- ";
		flush();
		sleep(6);
		$chargeCounter++;
	}
}

$newFeesSucess = number_format($newFeesSucess, 2);
$newFeesFailed = number_format($newFeesFailed, 2);
$repeatFeesSuccess = number_format($repeatFeesSuccess, 2);
$repeatFeesFailed = number_format($repeatFeesFailed, 2);


$emailContactsForReportsArr = Constants::getEmailContactsForServerReports();
foreach($emailContactsForReportsArr as $thisEmailContact){
	if(preg_match("/bill/i", $thisEmailContact))
		continue;
		
	$failedExplanationsText = "";
		
	if($repeatFailed > 0){
		$failedExplanationsText .= "There were $repeatFailed failed charges on repeat customers.  In the past, these repeat customers have had $totalSuccessfullAttemptsFromRepeat successful charge attempts and $totalFailedAttemptsFromRepeat failed attempts (including today).\n";
		$failedExplanationsText .= "----------------\n";
		foreach($failedExplanationsRepeatHash as $thisMissedReason => $thisMissedCount){
			$failedExplanationsText .= $thisMissedCount . " - " . $thisMissedReason . "\n";
		}
		$failedExplanationsText .= "----------------\n";
	}
	
	if($newFailed > 0){
		$failedExplanationsText .= "\nNew Customer Failures\n";
		$failedExplanationsText .= "----------------\n";
		foreach($failedExplanationsNewHash as $thisMissedReason => $thisMissedCount){
			$failedExplanationsText .= $thisMissedCount . " - " . $thisMissedReason . "\n";
		}
		$failedExplanationsText .= "----------------\n";
	}
	
	WebUtil::SendEmail("Loyalty Charges", Constants::GetMasterServerEmailAddress(), "", $thisEmailContact, "$chargeCounter Loyalty Charges", "New: $newCounter : \$$newFeesSucess - Failed($newFailed) : \$$newFeesFailed\nRepeat: $repeatCounter : \$$repeatFeesSuccess - Failed($repeatFailed) :\$$repeatFeesFailed \n\nTotal Failed: $failedCounter \n  \n$skipLog  \n$failedExplanationsText" );	
}

print "done";


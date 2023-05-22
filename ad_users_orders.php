<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();
$dbCmd3 = new DbCmd();
$dbCmd4 = new DbCmd();




//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("USER_ORDERS"))
	WebUtil::PrintAdminError("Not Available");


$navHistoryObj = new NavigationHistory($dbCmd3);

$byuser = WebUtil::GetInput("byuser", FILTER_SANITIZE_INT);
$offset = WebUtil::GetInput("offset", FILTER_SANITIZE_INT);



// Get the name of the person we are searching for order on
$dbCmd->Query("SELECT * FROM users WHERE ID=$byuser");
$UserInfo = $OrderInfo = $dbCmd->GetRow();
$customername = $UserInfo["Name"];
$customerEmail = $UserInfo["Email"];
$domainIDofCustomer = $UserInfo["DomainID"];


if(!$AuthObj->CheckIfUserCanViewDomainID($domainIDofCustomer))
	throw new Exception("The User ID does not exist.");

	
// This will show the logo of the person that we are looking at.
Domain::enforceTopDomainID($domainIDofCustomer);


$t = new Templatex(".");

$t->set_file("origPage", "ad_users_orders-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


$t->set_var("CUSTOMERID", $byuser);


$t->set_block("origPage","projectSummaryBL","projectSummaryBLout");
$t->set_block("origPage","ordersBL","ordersBLout");




$rowBackgroundColor=false;

$empty_orders=true;
$ordercounter = 0;
$projectcounter = 0;

$resultCounter = 0;

$NumberOfResultsToDisplay = 50;

// Get all of the orders belonging to the user
$dbCmd->Query("SELECT *, UNIX_TIMESTAMP(DateOrdered) AS TheDateOrdered FROM orders WHERE UserID=\"$byuser\" ORDER BY DateOrdered DESC");	
while($OrderInfo = $dbCmd->GetRow()){


	// Make sure that there is at least 1 of project in this order before continuing... otherwise skip to the next order --#
	// This also applies to a vendor's projects
	$query = "SELECT COUNT(*) FROM projectsordered WHERE OrderID=" . $OrderInfo["ID"];
	if($AuthObj->CheckIfBelongsToGroup("VENDOR"))
		$query .= " AND (" . Product::GetVendorIDLimiterSQL($UserID) . ")";

	$dbCmd2->Query($query);

	if($dbCmd2->GetValue() == 0)
		continue;

	
	$ordercounter++;
	$empty_orders = false;
	


	// If we have many matches we only want to display those which are on the current page.  This is figured out by the offset parameter passed in the URL.
	if(($resultCounter >= $offset) && ($resultCounter < ($NumberOfResultsToDisplay + $offset))){



		$date_ordered = date("M j, Y", $OrderInfo["TheDateOrdered"]);
		$time_ordered = date("D g:i a", $OrderInfo["TheDateOrdered"]);


		// Alternate the row colors if we are on a different order
		if($rowBackgroundColor){
			$rowcolor = "#DDDDFF";
			$rowBackgroundColor = false;
		}
		else{
			$rowcolor = "#DDDDDD";
			$rowBackgroundColor = true;
		}

		// Display the shipping method
		$t->set_var(array("SHIPPING_METHOD"=>ShippingChoices::getHtmlChoiceName($OrderInfo["ShippingChoiceID"])));
		$t->allowVariableToContainBrackets("SHIPPING_METHOD");
		
		// Shipping Address
		$t->set_var(array("SHIP_ADDRESS"=>WebUtil::htmlOutput($OrderInfo["ShippingAddress"]) . " " . WebUtil::htmlOutput($OrderInfo["ShippingAddressTwo"]) . " --- " . WebUtil::htmlOutput($OrderInfo["ShippingCity"]) . ", " . WebUtil::htmlOutput($OrderInfo["ShippingState"]) . " " . WebUtil::htmlOutput($OrderInfo["ShippingZip"])));
		$t->allowVariableToContainBrackets("SHIP_ADDRESS");
		
		// clean the slate for inner block.
		$t->set_var(array("projectSummaryBLout"=>""));


		$projectQuery = "SELECT ID,OrderDescription,OptionsAlias,Status,RePrintLink,ProductID  FROM projectsordered WHERE OrderID=" . $OrderInfo["ID"];
		if($AuthObj->CheckIfBelongsToGroup("VENDOR"))
			$query .= " AND (" . Product::GetVendorIDLimiterSQL($UserID) . ")";

		$dbCmd2->Query($projectQuery);	
		while($ProjectDetails = $dbCmd2->GetRow()){
			$projectcounter++;

			$project_id = $ProjectDetails["ID"];
			$OrderDescription = $ProjectDetails["OrderDescription"];
			$OptionsAlias = $ProjectDetails["OptionsAlias"];
			$Status = $ProjectDetails["Status"];
			$RePrintLink = $ProjectDetails["RePrintLink"];
			$productID = $ProjectDetails["ProductID"];
			
			
			// If the database has a flag set to filter certain Option Choices from a long list.
			$OptionsAlias = Product::filterOptionDescriptionForList($dbCmd3, $productID, $OptionsAlias);
			
	
			//Give them a link to proof the artwork
			if($AuthObj->CheckForPermission("PROOF_ARTWORK"))
				$t->set_var("PROOF", " - <a class='BlueRedLinkRecord' href='./ad_proof_artwork.php?projectid=$project_id'>Proof</a>");
			else
				$t->set_var("PROOF", "");
				
			$t->allowVariableToContainBrackets("PROOF");


			//Detect if this is a reprint of another project
			if($RePrintLink <> 0)
				$t->set_var("REPRINT", "<br><img src='./images/transparent.gif' height='5' width='5'><br><font color='#990000'><b>Reprint</b></font> of <a href='./ad_project.php?projectorderid=$RePrintLink' >P$RePrintLink</a>");
			else
				$t->set_var("REPRINT", "");

			$t->allowVariableToContainBrackets("REPRINT");




			if($AuthObj->CheckForPermission("ARTWORK_PREVIEW_POPUPS_USER_ORDERS")){
				$previewSpanHTML = "<span style='visibility:hidden; position:absolute; left:170px; top:60' id='artwPreviewSpan" . $project_id . "'></span>";
				$hrefMouseOver = " onMouseOver='showArtPrev(" . $project_id . ", true);' onMouseOut='showArtPrev(" . $project_id . ", false);'";


				// Find out if we have previewed this email recently
				$lastPreviewedDate = $navHistoryObj->getDateOfLastVisit($UserID, "ArtPreview", $project_id);

				if($lastPreviewedDate){
					$t->set_var("PREVIEWED", "<br>&nbsp;<font class='ReallySmallBody' style='color:333399'><b>Previewed:</b> " . LanguageBase::getTimeDiffDesc($lastPreviewedDate) . " ago.</font>");
					$artworkBoldStart = "";
					$artworkBoldEnd = "";
				}
				else{
					$t->set_var("PREVIEWED", "");
					$artworkBoldStart = "<b><font style='font-size:14px;'>";
					$artworkBoldEnd = "</font></b>";
				}
				
				if($AuthObj->CheckForPermission("PROOF_ARTWORK"))
					$artworkHref = "./ad_proof_artwork.php?projectid=$project_id";
				else
					$artworkHref = "#";

				$artworkLink = " - <a href='$artworkHref' class='BlueRedLink' $hrefMouseOver >" . $artworkBoldStart . "A" . $artworkBoldEnd  . "</a>" . $previewSpanHTML;
				$t->set_var("ART", $artworkLink);

			}
			else{

				$t->set_var("PREVIEWED", "");
				$t->set_var("ART", "");
			}
			
			$t->allowVariableToContainBrackets("PREVIEWED");
			$t->allowVariableToContainBrackets("ART");







			$StatusHistory = "";
			$StatusTimeStamp = 0;
			$LastStatusDate = "";
			$dbCmd3->Query("SELECT *, UNIX_TIMESTAMP(Date) AS StatusDate FROM projecthistory WHERE ProjectID=$project_id ORDER BY ID ASC");
			while($PHistRow = $dbCmd3->GetRow()){
				$StatusTimeStamp = $PHistRow["StatusDate"];
				
				// The last status timestamp should not be based upon Artowrk modifications.
				if(!preg_match("/Artwork/", $PHistRow["Note"]))
					$LastStatusDate = date("D, M jS, y", $StatusTimeStamp);
					
				$status_history_date = date("D, M j, D g:i a", $StatusTimeStamp);
				$StatusHistory .= $PHistRow["Note"] . "  --  " .  WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd4, $PHistRow["UserID"])) . "  --  $status_history_date " . "<br>- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -<br>";
			}


			$t->set_var(array("STATUS_DATE"=>$LastStatusDate, 
						"STATUS_HISTORY"=>$StatusHistory, 
						"ROW_COLOR"=>$rowcolor, 
						"ORDERNO"=>$OrderInfo["ID"], 
						"PROJECTID"=>$project_id, 
						"ORDER_DATE"=>$date_ordered, 
						"ORDER_TIME"=>$time_ordered,
						"ORDER_SUMMARY"=>WebUtil::htmlOutput($OrderDescription) . "<br>" . WebUtil::htmlOutput($OptionsAlias),  "STATUS"=>ProjectStatus::GetProjectStatusDescription($Status, true)));

			$t->allowVariableToContainBrackets("STATUS_HISTORY");
			$t->allowVariableToContainBrackets("ORDER_SUMMARY");
			$t->allowVariableToContainBrackets("STATUS");
			
			
			$t->parse("projectSummaryBLout","projectSummaryBL",true);

		}

		$t->parse("ordersBLout","ordersBL",true);
	}
	
	$resultCounter++;

}





// Set this block so that we can erase the multi-page navigation bar if there aren't multiple pages 
$t->set_block("origPage","MultiPageBL","MultiPageBLout");
$t->set_block("origPage","SecondMultiPageBL","SecondMultiPageBLout");

// This means that we have multiple pages of search results
if($resultCounter > $NumberOfResultsToDisplay){


	// What are the name/value pairs AND URL  for all of the subsequent pages 
	$NV_pairs_URL = "byuser=$byuser&";
	$BaseURL = "./ad_users_orders.php";

	// Get a the navigation of hyperlinks to all of the multiple pages 
	$NavigateHTML = Navigation::GetNavigationForSearchResults($BaseURL, $resultCounter, $NV_pairs_URL, $NumberOfResultsToDisplay, $offset);

	$t->set_var(array("NAVIGATE"=>$NavigateHTML, "RESULT_DESC"=>$resultCounter, "OFFSET"=>$offset));
	$t->parse("MultiPageBLout","MultiPageBL",true);
	$t->parse("SecondMultiPageBLout","SecondMultiPageBL",true);
	
	$t->allowVariableToContainBrackets("NAVIGATE");
}
else{
	$t->set_var(array("NAVIGATE"=>""));
	$t->set_var(array("MultiPageBLout"=>""));
	$t->set_var(array("SecondMultiPageBLout"=>""));
}





if($empty_orders){
	$t->set_var(array("ordersBLout"=>"No orders yet."));
}


if($AuthObj->CheckForPermission("VIEW_CUSTOMER_SERVICE"))
	$CustomerServiceLink = CustService::GetCustServiceLinkForUser($dbCmd, $byuser, false);
else
	$CustomerServiceLink = "";



if(!$AuthObj->CheckForPermission("CREATE_REPRINT")){

	$t->set_block("origPage","CreateRePrintBL","CreateRePrintBLout");
	$t->set_var("CreateRePrintBLout","&nbsp;");
	$t->set_block("origPage","CreateRePrintBL2","CreateRePrintBLout2");
	$t->set_var("CreateRePrintBLout2","&nbsp;");
}
else{
	$ReprintReasonDropDown = Widgets::buildSelect(Status::GetReprintReasonsHash(), array(""));
	$t->set_var("REPRINT_REASON_CHOICES", $ReprintReasonDropDown);
}

$t->allowVariableToContainBrackets("REPRINT_REASON_CHOICES");

$t->set_var(array("RESULT_DESC"=>$ordercounter, 
			"CUSTOMERNAME"=>WebUtil::htmlOutput($customername), 
			"CUSTOMER_SERVICE_LINK"=>$CustomerServiceLink));

$t->set_var("MEMOS", CustomerMemos::getLinkDescription($dbCmd, $byuser, false));

$t->allowVariableToContainBrackets("CUSTOMER_SERVICE_LINK");
$t->allowVariableToContainBrackets("MEMOS");




$taskCollectionObj = new TaskCollection();
$taskCollectionObj->limitShowTasksBeforeReminder(true);
$taskCollectionObj->limitUserID($UserID);
$taskCollectionObj->limitAttachedToName("user");
$taskCollectionObj->limitAttachedToID($byuser);
$taskCollectionObj->limitUncompletedTasksOnly(true);
$taskObjectsArr = $taskCollectionObj->getTaskObjectsArr();

$tasksDisplayObj = new TasksDisplay($taskObjectsArr);
$tasksDisplayObj->setTemplateFileName("tasks-template.html");
$tasksDisplayObj->setReturnURL("ad_users_orders.php?byuser=" . $byuser);
$tasksDisplayObj->displayAsHTML($t);		


$taskCount = $taskCollectionObj->getAttachedToRecordCount();
if($taskCount == 0)
	$t->set_var("T_COUNT", "");
else
	$t->set_var("T_COUNT", "(<b>$taskCount</b>)");

$t->allowVariableToContainBrackets("T_COUNT");


$custUserControlObj = new UserControl($dbCmd2);
$custUserControlObj->LoadUserByID($byuser);

$customerRating = $custUserControlObj->getUserRating();

$custRatingImgHTML = "<img src='./images/star-rating-" . $customerRating . ".gif' width='74' height='13'>";

if($customerRating > 0)
	$customerRating = "<div id='Dcust" . $byuser . "' style='cursor: hand; width:74px;' class='hiddenDHTMLwindow' onMouseOver='CustInf($byuser, ". $byuser .", true, \"".WebUtil::getFormSecurityCode()."\")' onMouseOut='CustInf($byuser, ". $byuser .", false, \"".WebUtil::getFormSecurityCode()."\")'>$custRatingImgHTML<span class='hiddenDHTMLwindow' style='visibility:hidden; left:75px; top:-15' id='Scust" . $byuser . "'></span></div>";
else
	$customerRating = $custRatingImgHTML . "<br>";

$t->set_var("CUST_RATE", $customerRating);

$t->allowVariableToContainBrackets("CUST_RATE");




// Output compressed HTML
WebUtil::print_gzipped_page_start();

$t->pparse("OUT","origPage");

WebUtil::print_gzipped_page_end();




?>
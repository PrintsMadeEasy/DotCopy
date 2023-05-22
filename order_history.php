<?

require_once("library/Boot_Session.php");

WebUtil::RunInSecureModeHTTPS();

$messagetype = WebUtil::GetInput("messagetype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$offset = WebUtil::GetInput("offset", FILTER_SANITIZE_INT);
$message = WebUtil::GetInput("message", FILTER_SANITIZE_STRING);

$offset = intval($offset);

$currentURL = "order_history.php?offset=$offset";

$currentURL = WebUtil::FilterURL($currentURL);

$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();
$dbCmd3 = new DbCmd();

$domainObj = Domain::singleton();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();

// Set this session var to 1... just in case they edit artwork... we check to make sure session variables are being read correctly.
$HTTP_SESSION_VARS['initialized'] = 1;

$t = new Templatex();


$shippingChoiceObj = new ShippingChoices(Domain::oneDomain());


$t->set_file("origPage", "order_history-template.html");


$personsName = UserControl::GetNameByUserID($dbCmd, $UserID);

// How many orders to be displayed to the user
$NumberOfResultsToDisplay = 8;

$empty_orders_flag = true;
$resultCounter = 0;

// Don't itemize thing within an order if there are more than 12 projects
$MaxProjectsPerOrder = 30;

// This hash will hold all of the information from the queries and nested queries 
// The reason to put it in a hash first is so that we can loop through the hash and ALSO have the ability to look ahead at records in front
$OrderInfoArr = array();


// This will hold javascript for showing our "mouse over" on the shipping details.
$ShippingDetailsJS = "";



// Grap the Inner Projects Block out of the OrderItemsBL
$t->set_block("origPage", "projectItemsBL", "projectItemsBLout");
$t->set_block("origPage", "ordersItemsBL", "ordersItemsBLout");

$dbCmd->Query("SELECT ID AS OrderID, UNIX_TIMESTAMP(DateOrdered) AS DateOrdered, ShippingChoiceID, ShippingQuote, 
		ShippingName, ShippingCompany, ShippingAddress, ShippingAddressTwo, 
		ShippingCity, ShippingState, ShippingZip, ShippingCountry 
		FROM orders where UserID=$UserID 
		AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) . 
		" ORDER BY DateOrdered DESC");
		
while($row = $dbCmd->GetRow()){

	// If we have many matches we only want to display those which are on the current page.  This is figured out by the offset parameter passed in the URL.
	if(($resultCounter >= $offset) && ($resultCounter < ($NumberOfResultsToDisplay + $offset))){

		$OrderID = $row["OrderID"];

		// Store the rest of the results from the DB into an Object... so we can pass the information along
		$OrderDetailObj = new OrderDetail();
		$OrderDetailObj->date = FormatDate($row["DateOrdered"]);
		$FreightTotal = $row["ShippingQuote"];
		$ShippingName = $row["ShippingName"];
		$ShippingCompany = $row["ShippingCompany"];
		$ShippingAddress = $row["ShippingAddress"];
		$ShippingAddressTwo = $row["ShippingAddressTwo"];
		$ShippingCity = $row["ShippingCity"];
		$shippingState = $row["ShippingState"];
		$ShippingZip = $row["ShippingZip"];
		$ShippingCountry = $row["ShippingCountry"];
		$shippingChoiceID = $row["ShippingChoiceID"];
		

		$empty_orders_flag = false;


		$shippingAddressObj = Order::getShippingAddressObject($dbCmd2, $OrderID);

		$htmlShippingAddress = WebUtil::htmlOutput($shippingAddressObj->toString());
		$htmlShippingAddress = preg_replace("/\n/", "<br>", $htmlShippingAddress);
		$htmlShippingAddress = preg_replace("/<br>US/", "", $htmlShippingAddress); // Don't need to show the country code in the US.
		
		
		// Fomat the Shipping Address for our mouse over
		$Customer_Shipping_Desc = "Shipping Method: " . ShippingChoices::getHtmlChoiceName($shippingChoiceID) . "<br>-----------------------------------<br>";
		$Customer_Shipping_Desc .= $htmlShippingAddress;
		
		
		$ShippingDetailsJS .= "'ORD$OrderID':wrapShippingDetails(\"$Customer_Shipping_Desc\"),\n";

		// Start filling up the hash with the order information
		$OrderInfoArr[$OrderID] = array();
		$OrderInfoArr[$OrderID]["MainOrderInfo"] = $OrderDetailObj;
		$OrderInfoArr[$OrderID]["LargeOrderFlag"] = false; 	//If we go over a certain amount of projects in an order, then don't itemize everythin.
		$OrderInfoArr[$OrderID]["ProjectDetails"] = array();

		
		// Clean the Slate for the nested block (or inner block) of HTML
		$t->set_var("projectItemsBLout", "");
		
		
		
		// Now we are going to do a nested query to get project information belonging the order that we are on
		$dbCmd2->Query("SELECT ID, Status, CustomerSubtotal, CustomerDiscount, 
				OrderDescription, OptionsDescription, ProductID, UNIX_TIMESTAMP(EstArrivalDate) as  EstArrivalDate
				FROM projectsordered where OrderID=$OrderID");

		$counter = 0;

		while($row2 = $dbCmd2->GetRow()){
			
			$counter++;
			
			$ProjectID = $row2["ID"];
			$Status = $row2["Status"];
			$Subtotal = $row2["CustomerSubtotal"];
			$CustomerDiscount = $row2["CustomerDiscount"];
			$OrderDescription = $row2["OrderDescription"];
			$OptionsDescription = $row2["OptionsDescription"];
			$ProductID = $row2["ProductID"];
			$EstArrivalDate =  $row2["EstArrivalDate"];

			// Depreciate old products
			if(!Product::checkIfProductIDisActive($dbCmd3, $ProductID))
				continue;


			$productStatusObj = new ProductStatus($dbCmd3, $ProductID);
				
			if($CustomerDiscount == "")
				$CustomerDiscount = 0;


			$Subtotal -= $CustomerDiscount * $Subtotal;

			$filteredOptionsForUser = Product::filterOptionDescriptionForCustomer($dbCmd3, $ProductID, $OptionsDescription);

			$t->set_var("PROJECT_ID", $ProjectID);
			$t->set_var("ORDER_DESCRIPTION", WebUtil::htmlOutput($OrderDescription));
			$t->set_var("OPTIONS_DESCRIPTION_JS_SLASHES_AND_HTML_ENCODED", addslashes(WebUtil::htmlOutput($filteredOptionsForUser)));
		
			
			if(in_array($Status, ProjectStatus::getStatusCharactersCanStillEditArtwork()))
				$t->set_var("CAN_EDIT_ARTWORK_FLAG", "true");
			else 
				$t->set_var("CAN_EDIT_ARTWORK_FLAG", "false");
				
				
			$t->set_var("ARRIVAL_DATE", WebUtil::htmlOutput(TimeEstimate::formatTimeStamp($EstArrivalDate)));
			
			if(Product::checkIfProductHasMailingServices($dbCmd3, $ProductID))
				$t->set_var("MAILING_SERVICES_FLAG", "true");
			else
				$t->set_var("MAILING_SERVICES_FLAG", "false");
				
			if(in_array($Status, array("C", "H", "W")))
				$t->set_var("STATUS_ON_HOLD_FLAG", "true");
			else
				$t->set_var("STATUS_ON_HOLD_FLAG", "false");
				
	
			$trackingNumberForProject = Order::BuildTrackingNumbersForProject($dbCmd3, $ProjectID);

			$t->set_var("PROJECT_TRACKING_NUMBERS_JS", addslashes($trackingNumberForProject));
			$t->allowVariableToContainBrackets("PROJECT_TRACKING_NUMBERS_JS");
			
			$t->set_var("STATUS_TITLE", WebUtil::htmlOutput($productStatusObj->getStatusTitle($Status)));
			$t->set_var("STATUS_DESCRIPTION", WebUtil::htmlOutput($productStatusObj->getProductStatusDescriptionForCustomer($Status)));
			
			
			if(Product::checkIfProductHasVariableData($dbCmd3, $ProductID)){
				$t->set_var("VARIABLE_DATA_FLAG", "true");
				$t->set_var("MODIFY_PROJECT_URL", "vardata_modify.php?projectrecord=$ProjectID&editorview=customerservice&returnurl=" . urlencode($currentURL));
			}
			else{ 
				$t->set_var("VARIABLE_DATA_FLAG", "false");
				$t->set_var("MODIFY_PROJECT_URL", "edit_artwork.php?projectrecord=$ProjectID&editorview=customerservice&continueurl=" . urlencode($currentURL) . "&cancelurl=" . urlencode($currentURL));
			}
								
				
			$t->parse("projectItemsBLout","projectItemsBL", true);
			
			// ---------------------------  END of New  System of Project Blocks that rely more on javascript to build HTML -------
			
			// I know this is kind of sloppy... 
			// This is for the older system on PME that requires the HTML to be built without javascript.
			// Set a flag with this order to tell us that this is a large order and we are not itemizing project.
			if($counter > $MaxProjectsPerOrder){
				$OrderInfoArr[$OrderID]["LargeOrderFlag"] = true;
				continue;
			}
			
			// Create a new object to hold the details about this project
			$OrderInfoArr[$OrderID]["ProjectDetails"][$ProjectID] = new ProjectContainerObject();


			$OrderInfoArr[$OrderID]["ProjectDetails"][$ProjectID]->SubTotal = $Subtotal;
			$OrderInfoArr[$OrderID]["ProjectDetails"][$ProjectID]->ProjectID = $ProjectID;
			
			
			if(!in_array($Status, array("C", "H", "W")) && !Product::checkIfProductHasMailingServices($dbCmd3, $ProductID))
				$OrderInfoArr[$OrderID]["ProjectDetails"][$ProjectID]->ArrivalDate = "<img src='./images/admin/calendar_event_line.png'><br>Guaranteed Arrival:<br>" . WebUtil::htmlOutput(TimeEstimate::formatTimeStamp($EstArrivalDate));
			else
				$OrderInfoArr[$OrderID]["ProjectDetails"][$ProjectID]->ArrivalDate = "";
			
			// If this mailing batch is finished... show the date of completion.
			if($Status == "F" && !Product::checkIfProductHasMailingServices($dbCmd3, $ProductID)){
				
				$LastStatusTimeStamp = ProjectHistory::GetLastStatusDate($dbCmd3, $ProjectID);
				
				$OrderInfoArr[$OrderID]["ProjectDetails"][$ProjectID]->ArrivalDate = date("l, \\t\\h\\e jS", $LastStatusTimeStamp);
			}
			
			
			

			$OrderInfoArr[$OrderID]["ProjectDetails"][$ProjectID]->StatusDesc = WebUtil::htmlOutput($productStatusObj->getProductStatusDescriptionForCustomer($Status));
			
			$OrderInfoArr[$OrderID]["ProjectDetails"][$ProjectID]->StatusChar = $Status;
			

			// A list of all tracking numbers
			$OrderInfoArr[$OrderID]["ProjectDetails"][$ProjectID]->TrackingNumber = Order::BuildTrackingNumbersForProject($dbCmd3, $ProjectID);

			$OrderInfoArr[$OrderID]["ProjectDetails"][$ProjectID]->OrderDescription = $OrderDescription;
			$OrderInfoArr[$OrderID]["ProjectDetails"][$ProjectID]->OptionsDescription = $OptionsDescription;

		}
		
		
		

		// $CustomerDiscount = Order::GetTotalFromOrder($dbCmd2, $OrderID, "customerdiscount");
		$CustomerTax = Order::GetTotalFromOrder($dbCmd2, $OrderID, "customertax");
		$CustomerSubtotal = Order::GetTotalFromOrder($dbCmd2, $OrderID, "customersubtotal");
		$CustomerDiscount = Order::GetTotalFromOrder($dbCmd2, $OrderID, "customerdiscount");
		$GrandTotal = Order::GetGrandTotalOfOrder($dbCmd2, $OrderID);

		$OrderInfoArr[$OrderID]["MainOrderInfo"]->freight = Order::GetCustomerShippingQuote($dbCmd2, $OrderID);
		$OrderInfoArr[$OrderID]["MainOrderInfo"]->tax = $CustomerTax;
		$OrderInfoArr[$OrderID]["MainOrderInfo"]->grandtotal = $GrandTotal;
		
		$t->set_var("ORDER_ID", $OrderID);
		$t->set_var("SHIPPING_CHOICE_NAME_HTML", ShippingChoices::getHtmlChoiceName($shippingChoiceID));
		$t->set_var("ORDER_ID_HASHED", Order::GetHashedOrderNo($OrderID));
		$t->set_var("ORDER_DATE", FormatDate($row["DateOrdered"]));
		$t->set_var("SHIPPING_FEE", number_format($FreightTotal, 2));
		$t->set_var("ORDER_SUBTOTAL_WITH_DISCOUNT", number_format(($CustomerSubtotal - $CustomerDiscount), 2));
		$t->set_var("ORDER_TAX", number_format($CustomerTax, 2));
		$t->set_var("GRAND_TOTAL", number_format($GrandTotal, 2));
		$t->set_var("SHIPPING_ADDRESS_HTML", $htmlShippingAddress);

		$loyaltyObj = new LoyaltyProgram(Domain::oneDomain());
		$t->set_var("LOYALTY_DISC_SHIPPING", $loyaltyObj->getShippingDiscountFromOrder($OrderID));
		$t->set_var("LOYALTY_DISC_SUBTOTAL", $loyaltyObj->getSubtotalDiscountFromOrder($OrderID));
		
		$t->allowVariableToContainBrackets("SHIPPING_CHOICE_NAME_HTML");
		$t->allowVariableToContainBrackets("SHIPPING_ADDRESS_HTML");
		
		$t->parse("ordersItemsBLout", "ordersItemsBL", true);
	}

	$resultCounter++;

}


// Since this javascript will be used as an array within the HTML... strip off the last comma from the repetition above.
if(!empty($ShippingDetailsJS))
	$ShippingDetailsJS = substr($ShippingDetailsJS, 0, -2); 


$t->set_var("SHIPPING_DETAILS", $ShippingDetailsJS);
$t->allowVariableToContainBrackets("SHIPPING_DETAILS");




$rowBackgroundColor=false;

$t->set_block("origPage", "ordersBL", "ordersBLout");

// Now loop through the hash that we created above and generate the HTML for the user
foreach($OrderInfoArr as  $OrderNumber=>$OrderDetail){

	// Alternate row colors between orders
	if($rowBackgroundColor){
		$rowcolor = "#FFFFFF";
		$rowBackgroundColor = false;
	}
	else{
		$rowcolor = "#E3FFE3";
		$rowBackgroundColor = true;
	}
	
	
	if(Order::CheckIfOrderCanChangeShipping($dbCmd, $OrderNumber))
		$t->set_var("ORDER_COMPLETE", "false");
	else
		$t->set_var("ORDER_COMPLETE", "true");


	//   Now generate the main projects ordered associated with this Main Order ID
	$projectCounter = 0;

	// Loop through the second dimension in this hash... CREATING THE PROJECTS ORDERED
	if(!$OrderDetail["LargeOrderFlag"]){
		foreach($OrderDetail["ProjectDetails"] as $ProjectNumber=>$ProjectDetailObj){

			$projectCounter++;

			// Find out if this order has multiple projects in it
			if(sizeof($OrderDetail["ProjectDetails"]) > 1){

				// Set the rowspan equal to the number of projects in the order
				$t->set_var("ROWSPAN", "rowspan='" . sizeof($OrderDetail["ProjectDetails"]) . "'");

				// If we are past the first project within this order then we need to erase some Table Data Cells.  This is because the rowspan that we set just above will compensate for it
				if($projectCounter > 1){

					// Instead of erasing the block of HTML we will just put HTML coments around the area that we want to get rid of
					$t->set_var(array("HIDE_COLUMNS_BEGIN"=>"<!-- ", "HIDE_COLUMNS_END"=>"-->"));
				}
				else{
					$t->set_var(array("HIDE_COLUMNS_BEGIN"=>"", "HIDE_COLUMNS_END"=>""));
				}
			}
			else{
				$t->set_var(array("ROWSPAN"=>""));
				$t->set_var(array("HIDE_COLUMNS_BEGIN"=>"", "HIDE_COLUMNS_END"=>""));

			}
			
			$t->allowVariableToContainBrackets("HIDE_COLUMNS_BEGIN");
			$t->allowVariableToContainBrackets("HIDE_COLUMNS_END");

			$t->set_var(array(
				"LARGE_ORDER_START"=>" ",
				"LARGE_ORDER_END"=> ""
			));

			// Create the ROW in HTMl with this function call
			ParseHTMLRow($ProjectDetailObj, Order::GetHashedOrderNo($OrderNumber), $t, $OrderNumber, $ProjectNumber, $OrderDetail["MainOrderInfo"], $rowcolor, $dbCmd);

		}
	}
	else{
	
		// Well, we are dealing with a large order then.  Let's consolidate the Display.
		// We will show a subtotal for every product that is in the shopping cart.  EX: 3 sets of 500 business cards and 2 tshirts .... would show 2 subtotals
		// Get a multi-dimensional hash that will have a breakdown of how all products, quantities, and options are configured.
		$OrderDescHash = Order::GetDescriptionOfLargeOrder($dbCmd, $OrderNumber);

		foreach($OrderDescHash as $ThisProdID => $ThisProdHash){

			$projectCounter++;
			
			// Find out if this order has multiple products
			if(sizeof($OrderDetail["ProjectDetails"]) > 1){
			
				// Set the rowspan equal to the number of products in the order
				$t->set_var(array("ROWSPAN"=>"rowspan='" . sizeof($OrderDescHash) . "'"));

				// If we are past the first project within this order then we need to erase some Table Data Cells.  This is because the rowspan that we set just above will compensate for it
				if($projectCounter > 1){

					// Instead of erasing the block of HTML we will just put HTML coments around the area that we want to get rid of
					$t->set_var(array("HIDE_COLUMNS_BEGIN"=>"<!-- ", "HIDE_COLUMNS_END"=>"-->"));
				}
				else{
					$t->set_var(array("HIDE_COLUMNS_BEGIN"=>"", "HIDE_COLUMNS_END"=>""));
				}
			
			}
			else{
				$t->set_var(array("ROWSPAN"=>""));
				$t->set_var(array("HIDE_COLUMNS_BEGIN"=>"", "HIDE_COLUMNS_END"=>""));
			}
			
			$t->allowVariableToContainBrackets("HIDE_COLUMNS_BEGIN");
			$t->allowVariableToContainBrackets("HIDE_COLUMNS_END");
			
			$OrderDescriptionHTML = "";

			// Now write all of the combinations of product quantities / options
			foreach($OrderDescHash["$ThisProdID"] as $ThisQuan=>$ThisQuanHash){
				
				foreach($ThisQuanHash["OptionsDescription"] as $OptionConfigStr => $OptionConfigHash){
					
					$OrderDescriptionHTML .= ("Project Qty. " . WebUtil::htmlOutput($OptionConfigHash["ProjectQuantity"]) . " of " . WebUtil::htmlOutput($ThisQuanHash["OrderDescription"]) . "<br>");
					$OrderDescriptionHTML .= WebUtil::htmlOutput($OptionConfigStr) . "<br>";
					
				}
			}

			
			ParseCSitemINrow($dbCmd, $t, $OrderNumber);
			
			$TrackingNumbersForOrderHTML = Order::BuildTrackingNumbersForOrder($dbCmd, $OrderNumber);


			// Don't worry about calculating a subtotal for this product group.
			$t->set_var("SUB"," - - - -");

			// We are going to span accross the Status and Options columns... and replace with a description of the bulk order
			$t->set_var(array(
				"LARGE_ORDER_START"=>" <!-- ",
				"LARGE_ORDER_END"=> " --> <td colspan='3' class='SmallBody'>" . $OrderDescriptionHTML . $TrackingNumbersForOrderHTML .  "</td>"
			));

			

			// Replace variables in HTML template
			$t->set_var(array(
				"ORDERNO"=>$OrderNumber,
				"ORD"=>Order::GetHashedOrderNo($OrderNumber),
				"DTE"=>$OrderDetailObj->date, 
				"GT"=>number_format($OrderDetail["MainOrderInfo"]->grandtotal,2), 
				"TAX"=>number_format($OrderDetail["MainOrderInfo"]->tax, 2), 
				"FRT"=>number_format($OrderDetail["MainOrderInfo"]->freight,2), 
				"ROWCOLOR"=>$rowcolor
				));

			$t->allowVariableToContainBrackets("LARGE_ORDER_END");
			$t->allowVariableToContainBrackets("LARGE_ORDER_START");
			
			$t->parse("ordersBLout","ordersBL",true);
		}
	}
}





// Set this block so that we can erase the multi-page navigation bar if there aren't multiple pages
$t->set_block("origPage", "MultiPageBL", "MultiPageBLout");
$t->set_block("origPage", "SecondMultiPageBL", "SecondMultiPageBLout");

// This means that we have multiple pages of search results
if($resultCounter > $NumberOfResultsToDisplay){


	// What are the name/value pairs AND URL  for all of the subsequent pages
	$NV_pairs_URL = "";
	$BaseURL = "./order_history.php";

	// Get a the navigation of hyperlinks to all of the multiple pages
	$NavigateHTML = Navigation::GetNavigationForSearchResults($BaseURL, $resultCounter, $NV_pairs_URL, $NumberOfResultsToDisplay, $offset);



	$t->set_var(array("NAVIGATE"=>$NavigateHTML, "RESULT_DESC"=>$resultCounter, "OFFSET"=>$offset));
	$t->allowVariableToContainBrackets("NAVIGATE");
	
	$t->parse("MultiPageBLout","MultiPageBL",true);
	$t->parse("SecondMultiPageBLout","SecondMultiPageBL",true);
}
else{
	$t->set_var("NAVIGATE", "");
	$t->set_var("MultiPageBLout", "");
	$t->set_var("SecondMultiPageBLout", "");
}



// If the user hasn't placed any orders yet... then redirec them to a message page where they can ask a question.
if($empty_orders_flag){
	header("Location: " . WebUtil::FilterURL("message_send.php?messagetype=emptyOrders") . "&nocaching=" . time());
	exit;
}


$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$t->set_var("PERSON_NAME", htmlspecialchars($personsName));



if($message == "artwork")
	$t->set_var("MESSAGE", "Your artwork changes have been saved.<br>");
else
	$t->set_var("MESSAGE", "");
$t->allowVariableToContainBrackets("MESSAGE");



VisitorPath::addRecord("Customer Order History");


$t->pparse("OUT","origPage");



class ProjectContainerObject {
  public $StatusDesc;
  public $StatusChar;
  public $SubTotal;
  public $ProjectID;
  public $TrackingNumber;
  public $OrderDescription;
  public $OptionsDescription;
  public $ArrivalDate;
}

class OrderDetail {
  public $date;
  public $freight;
  public $tax;
  public $grandtotal;
}


// Pass in the project and template object 
function ParseHTMLRow(&$ProjectDetailObj, $OrderNumberDescription, Template $t, $OrderNumber, $projectID, &$OrderDetailObj, $rowcolor, &$dbCmd){

	global $currentURL;

	$StatusForUser = $ProjectDetailObj->StatusDesc . $ProjectDetailObj->TrackingNumber;

	ParseCSitemINrow($dbCmd, $t, $OrderNumber);

	$t->set_var(array(
		"ORDERNO"=>$OrderNumber,
		"ORD"=>$OrderNumberDescription,
		"DTE"=>$OrderDetailObj->date, 
		"GT"=>number_format($OrderDetailObj->grandtotal,2), 
		"TAX"=>number_format($OrderDetailObj->tax, 2), 
		"FRT"=>number_format($OrderDetailObj->freight,2)
		));
	
	// Get a Project Object.
	// This isn't the most efficient way to build this customer service page... but it is evolving right now.
	$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "ordered", $projectID);

	$OrderDesciptionForProject = WebUtil::htmlOutput($projectObj->getOrderDescription()) .  $projectObj->getProjectDescriptionTable("SmallBody");
	
	$t->set_var(array(
		"DESC"=>$OrderDesciptionForProject, 
		"PROJECTRECORD"=>$projectID,
		"STATUS"=>$StatusForUser,
		"ARRIVAL"=>$ProjectDetailObj->ArrivalDate,
		"SUB"=>'$' . number_format($ProjectDetailObj->SubTotal, 2),
		"ROWCOLOR"=>$rowcolor
		));
		
	$t->allowVariableToContainBrackets("STATUS");

	$t->allowVariableToContainBrackets("DESC");
	$t->allowVariableToContainBrackets("ARRIVAL");

	// If the project is "new" or "proofed" then allow them to edit.
	// It it is too late to edit, then show them a link to re-order, or place it in the shopping cart
	if(in_array($ProjectDetailObj->StatusChar, ProjectStatus::getStatusCharactersCanStillEditArtwork())){
		
		if($projectObj->isVariableData())
			$t->set_var(array("EDIT"=>"<a href='./vardata_modify.php?projectrecord=$projectID&editorview=customerservice&returnurl=" . urlencode($currentURL) .  "' class='CustomerServiceLink'>Modify Data/Artwork</a><br><img src='./images/transparent.gif' border='0' width='1' height='5'><br>"));
		else
			$t->set_var(array("EDIT"=>"<a href='./edit_artwork.php?projectrecord=$projectID&editorview=customerservice&continueurl=" . urlencode($currentURL) . "&cancelurl=" . urlencode($currentURL) . "' class='CustomerServiceLink'>Modify Artwork</a><br><img src='./images/transparent.gif' border='0' width='1' height='5'><br>"));

		$t->set_var("REORDER", "");
	}
	else{
		$t->set_var(array("EDIT"=>""));
		$t->set_var("REORDER", '<br><img src="./images/transparent.gif" border="0" width="1" height="6"><br><a class="CustomerServiceLink" style="font-size:11px" href="javascript:OpenCopy('. $projectID . ')">Add to Shopping Cart<br>(for re-ordering)</a>');
	}
	
	$t->allowVariableToContainBrackets("EDIT");
	$t->allowVariableToContainBrackets("REORDER");

	$t->parse("ordersBLout","ordersBL",true);
}

function ParseCSitemINrow(DbCmd $dbCmd, &$t, $OrderNumber){

	// Find out if this project has any messages with it
	if(CustService::CheckIfOrderHasAnyCSitems($dbCmd, $OrderNumber))
		$CustomerServiceMessages = '<br><img src="./images/transparent.gif" width="4" height="7"><br><a href="javascript:ShowMessages(' . $OrderNumber . ');" class="CustomerServiceLink"><img src="./images/icon-note.gif" align="absmiddle" border="0">&nbsp;&nbsp;Read Messages</a>';
	else
		$CustomerServiceMessages = "";
	
	$t->set_var("CS_MSG", $CustomerServiceMessages);
	$t->allowVariableToContainBrackets("CS_MSG");
	
}





function FormatDate($DateOrdered){

	return date("M j, G:i a", $DateOrdered);
}




?>

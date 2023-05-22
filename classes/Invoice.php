<?
class Invoice {
	
	
	#--- Will generate a PDF invoice and write it to disk... The function will return the file name... without the directory it is in.
	##--  If multiple project ID's are passed in.. then it will generate a new page for each order.
	## - $ShowBorders is a bool.  If set to false... none of the background will be drawn. You could photocopy the background ahead of time to speed up the printing process on an inkjet
	static function GenerateInvoices(DbCmd $dbCmd, $orderid, $projectlistArr, $ShowBorders){

		// filter input.
		$orderid = intval($orderid);
	
		$tempArr = array();
		foreach($projectlistArr as $thisProjectID)
			$tempArr[] = intval($thisProjectID);
		$projectlistArr = $tempArr;
			
		
		
		#-- We the script below is really only set up currently to run off of ProjectIDs.  --#
		#-- So if an order ID comes within the URL, we are just going to create the project ID's, so that the script executes as planed --#
		if(!empty($orderid)){

			$dbCmd->Query("SELECT ID FROM projectsordered WHERE OrderID=$orderid");
			$projectlistArr = $dbCmd->GetValueArr();
		}
		
		if(empty($projectlistArr))
			throw new Exception("Error in method GenerateInvoices. No Project List given.");
	
		
		// Authenticate the Project List.
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		foreach($projectlistArr as $thisProjectID){		
			$domainIDofProject = ProjectBase::getDomainIDofProjectRecord($dbCmd, "ordered", $thisProjectID);
			
			if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofProject))
				throw new Exception("Error generating invoices, the Project ID is invalid: " . $thisProjectID);
		}
			

			
			
		$pdf_coord_conversion = 72;
	
	
		#-------------------------------------------- ###  Setup Variables   ### -------------------------#
	
		$greyShadingValue = 0.7;
	
		$LineHeight = 15;
		$LineHeight_2 = 12;
	
		$CellPadding = 5;
	
		$leftBoundry = $pdf_coord_conversion * 0.7;
		$rightBoundry = $pdf_coord_conversion * 7.8;
	
		$TopBoxesBorderLeft_X = $pdf_coord_conversion * 4.9;
		$TopBoxesBorder_Split_X = $pdf_coord_conversion * 6.35;
	
		$OrderBoxTop_Y = $pdf_coord_conversion * 10.4;
		$OrderBoxMiddle_Y = $pdf_coord_conversion * 10.15;
		$OrderBoxBottom_Y = $pdf_coord_conversion * 9.9;
	
		$ShippingBoxTop_Y = $pdf_coord_conversion * 9.7;
		$ShippingBoxMiddle_Y = $pdf_coord_conversion * 9.45;
		$ShippingBoxBottom_Y = $pdf_coord_conversion * 9.2;
	
		$ShipToBoxTop_Y = $pdf_coord_conversion * 9.0;
		$ShipToBoxMiddle_Y = $pdf_coord_conversion * 8.75;
		$ShipToBoxBottom_Y = $pdf_coord_conversion * 7.6;
	
	
		$InvoiceTop_Y = $pdf_coord_conversion * 7.4;
		$InvoiceTopBar_Y = $pdf_coord_conversion * 7.15;
		$InvoiceBottomBar_Y = $pdf_coord_conversion * 1.5;
		$InvoiceBottom_Y = $pdf_coord_conversion * 0.5;
	
		$InvoiceSplit_1_X = $pdf_coord_conversion * 4.9;
		$InvoiceSplit_2_X = $pdf_coord_conversion * 6.6;
	
		$returnAdress_Y = $pdf_coord_conversion * 9.2;
	
		$ThankYouMessage_Y = $pdf_coord_conversion * 8.1;
	
		$Label_OrderDate_X = $TopBoxesBorderLeft_X + 27;
		$Label_OrderDate_Y = $OrderBoxMiddle_Y + $CellPadding;
	
		$Label_OrderNumber_X = $TopBoxesBorder_Split_X + 17;
		$Label_OrderNumber_Y = $OrderBoxMiddle_Y + $CellPadding;
	
		$Label_ShippingMethod_X = $TopBoxesBorderLeft_X + 12;
		$Label_ShippingMethod_Y = $ShippingBoxMiddle_Y + $CellPadding;
	
		$Label_Carrier_X = $TopBoxesBorder_Split_X + 30;
		$Label_Carrier_Y = $ShippingBoxMiddle_Y + $CellPadding;
	
		$Label_ShipTo_X = $TopBoxesBorderLeft_X + 77;
		$Label_ShipTo_Y = $ShipToBoxMiddle_Y + $CellPadding;
	
		$Label_OrderDesc_X = $leftBoundry + 90;
		$Label_OrderDesc_Y = $InvoiceTopBar_Y + $CellPadding;
	
		$Label_Status_X = $InvoiceSplit_1_X + 43;
		$Label_Status_Y = $InvoiceTopBar_Y + $CellPadding;
	
	
		$Info_OrderNo_X = $TopBoxesBorder_Split_X + $CellPadding;
		$Info_OrderNo_Y = $OrderBoxBottom_Y + $CellPadding;
	
		$Info_DateOrdered_X = $TopBoxesBorderLeft_X + $CellPadding;
		$Info_DateOrdered_Y = $OrderBoxBottom_Y + $CellPadding;
	
		$Info_ShippingMethod_X = $TopBoxesBorderLeft_X + $CellPadding;
		$Info_ShippingMethod_Y = $ShippingBoxBottom_Y + $CellPadding;
	
		//$Info_Carrier_X = $TopBoxesBorder_Split_X + $CellPadding;
		//$Info_Carrier_Y = $ShippingBoxBottom_Y + $CellPadding;
	
		$Info_ShipTo_X = $TopBoxesBorderLeft_X + $CellPadding;
		$Info_ShipTo_Y = $ShipToBoxMiddle_Y - $LineHeight;
	
	
		//Don't itemize projects above this number
		$MaxProjectsPerInvoice = 8;
	
		#--------------------------------------------------- ###  Setup Variables   ### -------------------------#
	
	
	
	
	
	
		$dbCmd2 = new DbCmd();
	

	
	
		####  Now gather information from the database  ######
	
		#-- This hash will be filled with all information from the receipts to be printed --#
		#-- We need to put it in a hash first so that we have the ability to look ahead --#
		$OrderInformationArr = array();

		
		$query = "Select PO.ID AS ProjectID, orders.ID AS OrderID, PO.OrderDescription, 
				orders.ShippingChoiceID, PO.OptionsDescription, 
				UNIX_TIMESTAMP(orders.DateOrdered) AS DateOrdered, orders.ShippingName, orders.ShippingCompany, 
				orders.ShippingAddress, orders.ShippingAddressTwo, orders.ShippingCity, orders.ShippingState, 
				orders.ShippingZip, PO.CustomerDiscount, users.ID AS CustomerID, orders.ShippingQuote, 
				PO.CustomerTax, PO.CustomerSubtotal, orders.InvoiceNote, PO.Status, PO.ProductID, 
				orders.BillingType FROM (users INNER JOIN orders on orders.UserID = users.ID) 
				INNER JOIN projectsordered AS PO on PO.OrderID = orders.ID WHERE " . DbHelper::getOrClauseFromArray("PO.ID", $projectlistArr);

	
		
		$dbCmd->Query($query);
		while($row = $dbCmd->GetRow()){
	
			$ProjectOrderID = $row["ProjectID"];
			$MainOrderID = $row["OrderID"];
			$OrderDescription = $row["OrderDescription"];
			$shippingChoiceID = $row["ShippingChoiceID"];
			$OptionsDescription = $row["OptionsDescription"];
			$DateOrdered = $row["DateOrdered"];
			$ShippingName = $row["ShippingName"];
			$ShippingCompany = $row["ShippingCompany"];
			$ShippingAddress = $row["ShippingAddress"];
			$ShippingAddressTwo = $row["ShippingAddressTwo"];
			$ShippingCity = $row["ShippingCity"];
			$ShippingState = $row["ShippingState"];
			$ShippingZip = $row["ShippingZip"];
			$CustomerDiscount = $row["CustomerDiscount"];
			$CustomerID = $row["CustomerID"];
			$ShippingQuote = $row["ShippingQuote"];
			$CustomerTax = $row["CustomerTax"];
			$CustomerSubtotal = $row["CustomerSubtotal"];
			$InvoiceNote = $row["InvoiceNote"];
			$ProjectStatus = $row["Status"];
			$BillingType = $row["BillingType"];
			$productID = $row["ProductID"];
	
			//Skip Canceled projects
			if($ProjectStatus == "C")
				continue;
	
			if($CustomerDiscount == "")
				$CustomerDiscount = 0;
	
	
			$OptionsDescription = Product::filterOptionDescriptionForCustomer($dbCmd2, $productID, $OptionsDescription);
			
			
			#-- Initalize the 1st and 2nd levels of the hash.  --#
			if(!isset($OrderInformationArr[$MainOrderID]))
				$OrderInformationArr[$MainOrderID] = array();
		
			if(!isset($OrderInformationArr[$MainOrderID][$ProjectOrderID]))
				$OrderInformationArr[$MainOrderID]["$ProjectOrderID"] = array();
		
	
			$OrderInformationArr[$MainOrderID]["ProjectInfo"][$ProjectOrderID]["OrderDescription"] = $OrderDescription;
			$OrderInformationArr[$MainOrderID]["ProjectInfo"][$ProjectOrderID]["OptionsDescription"] = (!empty($OptionsDescription) ? "(" . $OptionsDescription . ")" : "");
			$OrderInformationArr[$MainOrderID]["ProjectInfo"][$ProjectOrderID]["CustomerTax"] = $CustomerTax;
			$OrderInformationArr[$MainOrderID]["ProjectInfo"][$ProjectOrderID]["CustomerSubtotal"] = $CustomerSubtotal;
			$OrderInformationArr[$MainOrderID]["ProjectInfo"][$ProjectOrderID]["CustomerDiscount"] = $CustomerDiscount;
	
			$OrderInformationArr[$MainOrderID]["OrderInfo"]["ShippingQuote"] = $ShippingQuote;
			$OrderInformationArr[$MainOrderID]["OrderInfo"]["ShippingType"] = ShippingChoices::getChoiceName($shippingChoiceID);
			$OrderInformationArr[$MainOrderID]["OrderInfo"]["DateOrdered"] = $DateOrdered;
			$OrderInformationArr[$MainOrderID]["OrderInfo"]["ShippingName"] = $ShippingName;
			$OrderInformationArr[$MainOrderID]["OrderInfo"]["ShippingCompany"] = $ShippingCompany;
			$OrderInformationArr[$MainOrderID]["OrderInfo"]["ShippingAddress"] = $ShippingAddress;
			$OrderInformationArr[$MainOrderID]["OrderInfo"]["ShippingAddressTwo"] = $ShippingAddressTwo;
			$OrderInformationArr[$MainOrderID]["OrderInfo"]["ShippingCityState"] = $ShippingCity . ", " . $ShippingState;
			$OrderInformationArr[$MainOrderID]["OrderInfo"]["ShippingZip"] = $ShippingZip;
			$OrderInformationArr[$MainOrderID]["OrderInfo"]["CustomerID"] = $CustomerID;
			$OrderInformationArr[$MainOrderID]["OrderInfo"]["InvoiceNote"] = $InvoiceNote;
			$OrderInformationArr[$MainOrderID]["OrderInfo"]["BillingType"] = $BillingType;
	
		}
	

		if(sizeof($OrderInformationArr) == 0){
			print "<html><br><br><br><div align=center>No invoices are available.<br><br><a href='javascript:history.back();'>&lt; Go Back</a></div><br><br><br></html>";
			exit;
		}
	
	
		#-- Create the PDF doc with PHP's extension PDFlib --#
		$pdf = pdf_new();
		if(!Constants::GetDevelopmentServer())
			pdf_set_parameter($pdf, "license", Constants::GetPDFlibLicenseKey());
	
	
		pdf_open_file($pdf, "");
		pdf_set_info($pdf, "Title", "Invoice");
		pdf_set_info($pdf, "Subject", "Invoice");
	
		PDF_set_parameter($pdf, "SearchPath", Constants::GetFontBase());
	
	

	
		## -- Loop through the Hash table we created.. using the Database ----##
		foreach($OrderInformationArr as $MainOrderID => $OrderInfoHash){
	
			// Get the user ID of the person that placed this order --#
			$dbCmd->Query("SELECT UserID FROM orders WHERE ID=$MainOrderID");
			$CustomerUserID = $dbCmd->GetValue();
	
			$AccountTypeObj = new AccountType($CustomerUserID, $dbCmd);
	
			$InvoiceLogoBinaryData = $AccountTypeObj->GetInvoiceLogoBinaryData();
	
			#-- Load the Logo Image data into a PDF lib virtual file
			PDF_create_pvf( $pdf, "/pfv/images/Logo", $InvoiceLogoBinaryData, "");
	
			#-- Get an image resource for the PDF doc through the virtual file --#
			$pdfimage = PDF_load_image ($pdf, "jpeg", "/pfv/images/Logo", "");
	
			#-- Now we can release our virtual file since we already have a PDF image resource
			PDF_delete_pvf($pdf, "/pfv/images/Logo");
	
			if(!$pdfimage){
				$errorMsg = "Error generating logo image for PDF Invoice.";
				WebUtil::WebmasterError($errorMsg);
				exit($errorMsg);
			}
	
	
	
			#-- Make a new page 8.5 by 11 --#
			pdf_begin_page($pdf, (8.5 * $pdf_coord_conversion), (11 * $pdf_coord_conversion));
			pdf_add_bookmark($pdf, "Page #" . Order::GetHashedOrderNo($MainOrderID), 0, 0);
	
			#-- Put the logo on the page --#
			PDF_place_image($pdf, $pdfimage, ($leftBoundry - 10), (9.45 * $pdf_coord_conversion), 0.8);
	
	
			pdf_set_parameter( $pdf, "FontOutline", "Eurasia=Eurasia.ttf");
			$fontRes = pdf_findfont($pdf, "Eurasia", "winansi", 1);
			$fontSize = 10;
			pdf_setfont ( $pdf, $fontRes, $fontSize);
	
	
			#-- Write down the return address --#
			$InvoiceAddressHash = $AccountTypeObj->GetInvoiceAddress();
			pdf_show_xy($pdf, $InvoiceAddressHash["Line1"], $leftBoundry, $returnAdress_Y);
			pdf_show_xy($pdf, $InvoiceAddressHash["Line2"], $leftBoundry, ($returnAdress_Y - $LineHeight));
			pdf_show_xy($pdf, $InvoiceAddressHash["Line3"], $leftBoundry, ($returnAdress_Y - $LineHeight*2));
			pdf_show_xy($pdf, $InvoiceAddressHash["Line4"], $leftBoundry, ($returnAdress_Y - $LineHeight*3));
	
			#-- Thank you Message --#
			$InvoiceMessageHash = $AccountTypeObj->GetInvoiceMessage();
			pdf_show_xy($pdf, $InvoiceMessageHash["Line1"], $leftBoundry, $ThankYouMessage_Y);
			pdf_show_xy($pdf, $InvoiceMessageHash["Line2"], $leftBoundry, ($ThankYouMessage_Y - $LineHeight));
			pdf_show_xy($pdf, $InvoiceMessageHash["Line3"], $leftBoundry, ($ThankYouMessage_Y - $LineHeight*2));
	
	
			if($ShowBorders){
				#-- Draw the borders for the Order Date and Order Number --#
				//Put some shading in cells
				pdf_setcolor($pdf, "both", "rgb", $greyShadingValue, $greyShadingValue, $greyShadingValue, 0);
				pdf_rect($pdf, $TopBoxesBorderLeft_X, $OrderBoxMiddle_Y, ($rightBoundry - $TopBoxesBorderLeft_X), ($OrderBoxTop_Y - $OrderBoxMiddle_Y));
				pdf_fill_stroke ($pdf);
	
				pdf_setcolor($pdf, "both", "rgb", 0, 0, 0, 0);
	
				pdf_moveto($pdf,$TopBoxesBorderLeft_X, $OrderBoxTop_Y);
				pdf_lineto($pdf,$rightBoundry, $OrderBoxTop_Y);
				pdf_lineto($pdf,$rightBoundry, $OrderBoxBottom_Y);
				pdf_lineto($pdf,$TopBoxesBorderLeft_X, $OrderBoxBottom_Y);
				pdf_lineto($pdf,$TopBoxesBorderLeft_X, $OrderBoxTop_Y);
	
				pdf_moveto($pdf,$TopBoxesBorderLeft_X, $OrderBoxMiddle_Y);
				pdf_lineto($pdf,$rightBoundry, $OrderBoxMiddle_Y);
	
				pdf_moveto($pdf,$TopBoxesBorder_Split_X, $OrderBoxTop_Y);
				pdf_lineto($pdf,$TopBoxesBorder_Split_X, $OrderBoxBottom_Y);
	
				pdf_stroke($pdf);
	
	

				#-- Draw the borders for the Shipping Info --#
				//Put some shading in cells
				pdf_setcolor($pdf, "both", "rgb", $greyShadingValue, $greyShadingValue, $greyShadingValue, 0);
				pdf_rect($pdf, $TopBoxesBorderLeft_X, $ShippingBoxMiddle_Y, ($rightBoundry - $TopBoxesBorderLeft_X), ($ShippingBoxTop_Y - $ShippingBoxMiddle_Y));
				pdf_fill_stroke ($pdf);
	
				pdf_setcolor($pdf, "both", "rgb", 0, 0, 0, 0);
	
				pdf_moveto($pdf,$TopBoxesBorderLeft_X, $ShippingBoxTop_Y);
				pdf_lineto($pdf,$rightBoundry, $ShippingBoxTop_Y);
				pdf_lineto($pdf,$rightBoundry, $ShippingBoxBottom_Y);
				pdf_lineto($pdf,$TopBoxesBorderLeft_X, $ShippingBoxBottom_Y);
				pdf_lineto($pdf,$TopBoxesBorderLeft_X, $ShippingBoxTop_Y);
	
				pdf_moveto($pdf,$TopBoxesBorderLeft_X, $ShippingBoxMiddle_Y);
				pdf_lineto($pdf,$rightBoundry, $ShippingBoxMiddle_Y);
	
				pdf_moveto($pdf,$TopBoxesBorder_Split_X, $ShippingBoxTop_Y);
				pdf_lineto($pdf,$TopBoxesBorder_Split_X, $ShippingBoxBottom_Y);
	
				pdf_stroke($pdf);
	
	
				#-- Draw the borders for the Shipping Address  --#
				//Put some shading in cells
				pdf_setcolor($pdf, "both", "rgb", $greyShadingValue, $greyShadingValue, $greyShadingValue, 0);
				pdf_rect($pdf, $TopBoxesBorderLeft_X, $ShipToBoxMiddle_Y, ($rightBoundry - $TopBoxesBorderLeft_X), ($ShipToBoxTop_Y - $ShipToBoxMiddle_Y));
				pdf_fill_stroke ($pdf);
	
				pdf_setcolor($pdf, "both", "rgb", 0, 0, 0, 0);
	
				pdf_moveto($pdf,$TopBoxesBorderLeft_X, $ShipToBoxTop_Y);
				pdf_lineto($pdf,$rightBoundry, $ShipToBoxTop_Y);
				pdf_lineto($pdf,$rightBoundry, $ShipToBoxBottom_Y);
				pdf_lineto($pdf,$TopBoxesBorderLeft_X, $ShipToBoxBottom_Y);
				pdf_lineto($pdf,$TopBoxesBorderLeft_X, $ShipToBoxTop_Y);
	
				pdf_moveto($pdf,$TopBoxesBorderLeft_X, $ShipToBoxMiddle_Y);
				pdf_lineto($pdf,$rightBoundry, $ShipToBoxMiddle_Y);
	
				pdf_stroke($pdf);
	
	
				#-- Draw the borders for the Invoice Box  --#
				//Put some shading in cells
				pdf_setcolor($pdf, "both", "rgb", $greyShadingValue, $greyShadingValue, $greyShadingValue, 0);
				pdf_rect($pdf, $leftBoundry, $InvoiceTopBar_Y, ($rightBoundry - $leftBoundry), ($InvoiceTop_Y - $InvoiceTopBar_Y));
				pdf_fill_stroke ($pdf);
	
				pdf_setcolor($pdf, "both", "rgb", 0, 0, 0, 0);
	
				pdf_moveto($pdf,$leftBoundry, $InvoiceTop_Y);
				pdf_lineto($pdf,$rightBoundry, $InvoiceTop_Y);
				pdf_lineto($pdf,$rightBoundry, $InvoiceBottom_Y);
				pdf_lineto($pdf,$leftBoundry, $InvoiceBottom_Y);
				pdf_lineto($pdf,$leftBoundry, $InvoiceTop_Y);
	
				pdf_moveto($pdf,$leftBoundry, $InvoiceTopBar_Y);
				pdf_lineto($pdf,$rightBoundry, $InvoiceTopBar_Y);
	
				pdf_moveto($pdf,$leftBoundry, $InvoiceBottomBar_Y);
				pdf_lineto($pdf,$rightBoundry, $InvoiceBottomBar_Y);
	
				pdf_moveto($pdf,$InvoiceSplit_1_X, $InvoiceTop_Y);
				pdf_lineto($pdf,$InvoiceSplit_1_X, $InvoiceBottom_Y);
	
				pdf_moveto($pdf,$InvoiceSplit_2_X, $InvoiceTop_Y);
				pdf_lineto($pdf,$InvoiceSplit_2_X, $InvoiceBottom_Y);
	
				pdf_stroke($pdf);
	
	
	
				#-- Write all of the Labels onto the document --#
				pdf_show_xy($pdf, "ORDER DATE", $Label_OrderDate_X, $Label_OrderDate_Y);
				pdf_show_xy($pdf, "ORDER NUMBER", $Label_OrderNumber_X, $Label_OrderNumber_Y);
				pdf_show_xy($pdf, "SHIPPING METHOD", $Label_ShippingMethod_X, $Label_ShippingMethod_Y);
				pdf_show_xy($pdf, "CARRIER", $Label_Carrier_X, $Label_Carrier_Y);
				pdf_show_xy($pdf, "SHIP TO", $Label_ShipTo_X, $Label_ShipTo_Y);
				pdf_show_xy($pdf, "ORDER DESCRIPTION", $Label_OrderDesc_X, $Label_OrderDesc_Y);
				pdf_show_xy($pdf, "STATUS", $Label_Status_X, $Label_Status_Y);
			}
			
	

	
			#-- Write the Hased Order number --#
			pdf_show_xy($pdf, Order::GetHashedOrderNo($MainOrderID), $Info_OrderNo_X, $Info_OrderNo_Y);
	
	
	
		//---------------------------
	
	
			pdf_show_xy($pdf, date("M d, Y", $OrderInfoHash["OrderInfo"]["DateOrdered"]), $Info_DateOrdered_X, $Info_DateOrdered_Y);
			pdf_show_xy($pdf, $OrderInfoHash["OrderInfo"]["ShippingType"], $Info_ShippingMethod_X, $Info_ShippingMethod_Y);
			//pdf_show_xy($pdf, "UPS", $Info_Carrier_X, $Info_Carrier_Y);
	
	
			#-- Find out if Customer Service has put in an invoice note --#
			#-- It may be a P.O. Number in which case we don't want it to be shown if they are resellers
			if($AccountTypeObj->DisplayInvoiceAmounts() && !empty($OrderInfoHash["OrderInfo"]["InvoiceNote"])){
				pdf_show_xy($pdf, $OrderInfoHash["OrderInfo"]["InvoiceNote"], ($leftBoundry + $CellPadding), $InvoiceBottomBar_Y - $LineHeight*1);
			}
	
			#-- If there is a company name then we should write the Company first followed by "Attn: Joh Doe"
			if(!empty($OrderInfoHash["OrderInfo"]["ShippingCompany"])){
				pdf_show_xy($pdf, $OrderInfoHash["OrderInfo"]["ShippingCompany"], $Info_ShipTo_X, $Info_ShipTo_Y);
				pdf_show_xy($pdf, "Attn: " . $OrderInfoHash["OrderInfo"]["ShippingName"], $Info_ShipTo_X, $Info_ShipTo_Y - $LineHeight_2);
				pdf_show_xy($pdf, $OrderInfoHash["OrderInfo"]["ShippingAddress"], $Info_ShipTo_X, $Info_ShipTo_Y - $LineHeight_2*2);
	
				#-- Create an extra line if there is a Shipping address 2
				if(!empty($OrderInfoHash["OrderInfo"]["ShippingAddressTwo"])){
					pdf_show_xy($pdf, $OrderInfoHash["OrderInfo"]["ShippingAddressTwo"], $Info_ShipTo_X, $Info_ShipTo_Y - $LineHeight_2*3);
					pdf_show_xy($pdf, $OrderInfoHash["OrderInfo"]["ShippingCityState"], $Info_ShipTo_X, $Info_ShipTo_Y - $LineHeight_2*4);
					pdf_show_xy($pdf, $OrderInfoHash["OrderInfo"]["ShippingZip"], $Info_ShipTo_X, $Info_ShipTo_Y - $LineHeight_2*5);
	
				}
				else{
					pdf_show_xy($pdf, $OrderInfoHash["OrderInfo"]["ShippingCityState"], $Info_ShipTo_X, $Info_ShipTo_Y - $LineHeight_2*3);
					pdf_show_xy($pdf, $OrderInfoHash["OrderInfo"]["ShippingZip"], $Info_ShipTo_X, $Info_ShipTo_Y - $LineHeight_2*4);
				}
			}
			else{
				pdf_show_xy($pdf, $OrderInfoHash["OrderInfo"]["ShippingName"], $Info_ShipTo_X, $Info_ShipTo_Y);
				pdf_show_xy($pdf, $OrderInfoHash["OrderInfo"]["ShippingAddress"], $Info_ShipTo_X, $Info_ShipTo_Y - $LineHeight_2);
	
				#-- Create an extra line if there is a Shipping address 2
				if(!empty($OrderInfoHash["OrderInfo"]["ShippingAddressTwo"])){
					pdf_show_xy($pdf, $OrderInfoHash["OrderInfo"]["ShippingAddressTwo"], $Info_ShipTo_X, $Info_ShipTo_Y - $LineHeight_2*2);
					pdf_show_xy($pdf, $OrderInfoHash["OrderInfo"]["ShippingCityState"], $Info_ShipTo_X, $Info_ShipTo_Y - $LineHeight_2*3);
					pdf_show_xy($pdf, $OrderInfoHash["OrderInfo"]["ShippingZip"], $Info_ShipTo_X, $Info_ShipTo_Y - $LineHeight_2*4);
				}
				else{
					pdf_show_xy($pdf, $OrderInfoHash["OrderInfo"]["ShippingCityState"], $Info_ShipTo_X, $Info_ShipTo_Y - $LineHeight_2*2);
					pdf_show_xy($pdf, $OrderInfoHash["OrderInfo"]["ShippingZip"], $Info_ShipTo_X, $Info_ShipTo_Y - $LineHeight_2*3);
	
				}
	
	
	
			}

			$CustomerShippingQuote = number_format($OrderInfoHash["OrderInfo"]["ShippingQuote"], 2);
	
	
			$MultipOrderCoord_Y = $LineHeight * 2; //Make the Order Descriptions start displaying 2 lines underneath the border for the top --#
	
			#-- If there are too many projects in an order, then we can not display on 1 page.. --#
			if(sizeof($OrderInfoHash) <= $MaxProjectsPerInvoice){
	
				#--- Now loop through all of the projects with the Main order --#
				$ProjectCounter = 0;	
	
				foreach($OrderInfoHash["ProjectInfo"] as $ProjectOrderID => $projectInfoHash){
					$ProjectCounter++;
	
					#-- Write the Project Desription --#
					pdf_show_xy($pdf, $projectInfoHash["OrderDescription"], $leftBoundry + $CellPadding, $InvoiceTopBar_Y - $MultipOrderCoord_Y);
					
					pdf_save($pdf);
					$optionsFontSize = 8.5;
					pdf_setfont ( $pdf, $fontRes, $optionsFontSize);
					pdf_show_xy($pdf, $projectInfoHash["OptionsDescription"], $leftBoundry + $CellPadding, $InvoiceTopBar_Y - $MultipOrderCoord_Y - $LineHeight);
					pdf_restore($pdf);
	
					#-- Write the Project Status --#
					pdf_show_xy($pdf, "Processed", $InvoiceSplit_1_X + $CellPadding, $InvoiceTopBar_Y - $MultipOrderCoord_Y);
	
					#-- We need the current line with so that we can right justify the subotal --#
					$CurrentLineWidth = pdf_stringwidth ( $pdf, '$' . $projectInfoHash["CustomerSubtotal"], $fontRes, $fontSize);
	
					if($AccountTypeObj->DisplayInvoiceAmounts()){
						#-- Write the Project Subtotal --#
						pdf_show_xy($pdf, '$' . $projectInfoHash["CustomerSubtotal"], ($rightBoundry - $CellPadding - $CurrentLineWidth ), $InvoiceTopBar_Y - $MultipOrderCoord_Y);
					}
	
					$MultipOrderCoord_Y += $LineHeight*3;
				}
			}
			else{
				#######-- There are a lot of projects with this order, so lets consolodate the invoice --#
	
				#-- Get a multi-dimensional hash that will have a breakdown of how all products, quantities, and options are configured.
				$OrderDescHash = Order::GetDescriptionOfLargeOrder($dbCmd, $MainOrderID);
	
				#-- Get a unique list of Product ID's in this order
				$ProductIDArr = ProjectGroup::GetProductIDlistFromGroup($dbCmd, $MainOrderID, "order");
	
				#-- Loop through all of the Products in this order --#
				foreach($ProductIDArr as $ThisProdID){
	
					#-- Get a total number of produts... EX: 112 sets of 100 qty. cards would be '11200'
					$QuantityForProdID = ProjectGroup::GetDetailsOfProductInGroup($dbCmd, "quantity", $MainOrderID, $ThisProdID, "order");
	
					//Yes, this will take a bit of overhead to create a new project InfoObj, but I don't expect there will be many "mass project" invoices
					$projectInfoObj = new ProjectInfo($dbCmd, $ThisProdID);
	
					$FirstLineDesc = "Total of " . $projectInfoObj->getOrderDescription($QuantityForProdID);
					pdf_show_xy($pdf, $FirstLineDesc, $leftBoundry + $CellPadding, $InvoiceTopBar_Y - $MultipOrderCoord_Y);
	
					#-- Make sure this account type should display price information --#
					if($AccountTypeObj->DisplayInvoiceAmounts()){
	
						// Get the Customer Subtotal of from the order (for this product only)
						$dbCmd->Query("SELECT SUM(CustomerSubtotal) FROM projectsordered WHERE ProductID=$ThisProdID AND OrderID=$MainOrderID AND Status!='C'");
						$SubtotalOfProduct = $dbCmd->GetValue();
	
						#-- We need the current line with so that we can right justify the subotal --#
						$CurrentLineWidth = pdf_stringwidth ( $pdf, '$' . $SubtotalOfProduct, $fontRes, $fontSize);
	
						#-- Write the Project Subtotal --#
						pdf_show_xy($pdf, '$' . $SubtotalOfProduct, ($rightBoundry - $CellPadding - $CurrentLineWidth ), $InvoiceTopBar_Y - $MultipOrderCoord_Y);
					}
	
					$MultipOrderCoord_Y += $LineHeight;
	
					#-- Now write all of the combinations of product quantities / options
					foreach($OrderDescHash["$ThisProdID"] as $ThisQuanHash){
	
						foreach($ThisQuanHash["OptionsDescription"] as $OptionConfigStr => $OptionConfigHash){
							pdf_show_xy($pdf, ("Project Qty. " . $OptionConfigHash["ProjectQuantity"] . " of " . $ThisQuanHash["OrderDescription"]), $leftBoundry + $CellPadding, $InvoiceTopBar_Y - $MultipOrderCoord_Y);
							$MultipOrderCoord_Y += $LineHeight;
							pdf_show_xy($pdf,  $OptionConfigStr, $leftBoundry + $CellPadding, $InvoiceTopBar_Y - $MultipOrderCoord_Y);
							$MultipOrderCoord_Y += $LineHeight;
						}
	
	
					}
	
					//Make a blank space between each project in the invoice
					$MultipOrderCoord_Y += $LineHeight;
				}
			}
	
	
			$TotalDiscount = Order::GetTotalFromOrder($dbCmd, $MainOrderID, "customerdiscount");
	
			#-- If there is a discount then look into the users table and tell them where the discount is coming from --#
			if($TotalDiscount <> 0 && $AccountTypeObj->DisplayInvoiceAmounts()){
	
				$TotalDiscountDesc = '- $' . number_format($TotalDiscount,2);
	
				$DiscountMessage = "Discount ";
				$CurrentLineWidth = pdf_stringwidth ( $pdf, $DiscountMessage, $fontRes, $fontSize);
				pdf_show_xy($pdf, $DiscountMessage, ($InvoiceSplit_2_X - $CellPadding - $CurrentLineWidth ), $InvoiceBottomBar_Y + $LineHeight*2);
	
				$CurrentLineWidth = pdf_stringwidth ( $pdf, $TotalDiscountDesc, $fontRes, $fontSize);
				pdf_show_xy($pdf, $TotalDiscountDesc, ($rightBoundry - $CellPadding - $CurrentLineWidth ), $InvoiceBottomBar_Y + $LineHeight*2);
			}
	

			if($AccountTypeObj->DisplayInvoiceAmounts()){
				#-- Write the labels at the bottom --#
				#-- We need the current line with so that we can right justify the label --#
				$CurrentLineWidth = pdf_stringwidth ( $pdf, "Subtotal", $fontRes, $fontSize);
				pdf_show_xy($pdf, "Subtotal", ($InvoiceSplit_2_X - $CellPadding - $CurrentLineWidth ), $InvoiceBottomBar_Y - $LineHeight);
				$CurrentLineWidth = pdf_stringwidth ( $pdf, "Tax", $fontRes, $fontSize);
				pdf_show_xy($pdf, "Tax", ($InvoiceSplit_2_X - $CellPadding - $CurrentLineWidth ), $InvoiceBottomBar_Y - $LineHeight*2);
				$CurrentLineWidth = pdf_stringwidth ( $pdf, "S & H", $fontRes, $fontSize);
				pdf_show_xy($pdf, "S & H", ($InvoiceSplit_2_X - $CellPadding - $CurrentLineWidth ), $InvoiceBottomBar_Y - $LineHeight*3);
				$CurrentLineWidth = pdf_stringwidth ( $pdf, "Grand Total", $fontRes, $fontSize);
				pdf_show_xy($pdf, "Grand Total", ($InvoiceSplit_2_X - $CellPadding - $CurrentLineWidth ), $InvoiceBottomBar_Y - $LineHeight*4);
			}
	
	
			$Subtotal = Order::GetTotalFromOrder($dbCmd, $MainOrderID, "customersubtotal");
			$Tax = Order::GetTotalFromOrder($dbCmd, $MainOrderID, "customertax");
			$Subtotal -= $TotalDiscount;
			$GrandTotal = number_format(($Subtotal + $Tax + $CustomerShippingQuote),2);
			$Subtotal = number_format($Subtotal, 2);
	
			if($AccountTypeObj->DisplayInvoiceAmounts()){
				#-- Write the Amounts at the bottom --#
				#-- We need the current line with so that we can right justify the label --#
				$CurrentLineWidth = pdf_stringwidth ( $pdf, '$' . $Subtotal, $fontRes, $fontSize);
				pdf_show_xy($pdf, '$' . $Subtotal, ($rightBoundry - $CellPadding - $CurrentLineWidth ), $InvoiceBottomBar_Y - $LineHeight);
				$CurrentLineWidth = pdf_stringwidth ( $pdf, '$' . $Tax, $fontRes, $fontSize);
				pdf_show_xy($pdf, '$' . $Tax, ($rightBoundry - $CellPadding - $CurrentLineWidth ), $InvoiceBottomBar_Y - $LineHeight*2);
				$CurrentLineWidth = pdf_stringwidth ( $pdf, '$' . $CustomerShippingQuote, $fontRes, $fontSize);
				pdf_show_xy($pdf, '$' . $CustomerShippingQuote, ($rightBoundry - $CellPadding - $CurrentLineWidth ), $InvoiceBottomBar_Y - $LineHeight*3);
				$CurrentLineWidth = pdf_stringwidth ( $pdf, '$' . $GrandTotal, $fontRes, $fontSize);
				pdf_show_xy($pdf, '$' . $GrandTotal, ($rightBoundry - $CellPadding - $CurrentLineWidth ), $InvoiceBottomBar_Y - $LineHeight*4);
			
			
			
				$orderBillingType = $OrderInfoHash["OrderInfo"]["BillingType"];
			
				// If this order was corporate billed... then find out if they own any money at the time of the order... then we can show paid in full or not.
				$corporateBalanceOnOrderDate = 0;
				
				if($orderBillingType == "C"){
				
					$PaymentInvoiceObj = new PaymentInvoice();
					
					$PaymentInvoiceObj->LoadCustomerByID($OrderInfoHash["OrderInfo"]["CustomerID"]);
					
					$orderDateTimeStmp = $OrderInfoHash["OrderInfo"]["DateOrdered"];
					
					$corporateBalanceOnOrderDate = $PaymentInvoiceObj->GetCurrentBalance(date("Y", $orderDateTimeStmp), date("n", $orderDateTimeStmp), date("j", $orderDateTimeStmp));
				}
				
				// Possibly explain a free shipping charge from the loyalty program.
				$userControlObj = new UserControl($dbCmd2);
				$userControlObj->LoadUserByID($CustomerUserID, false);
				
				$dbCmd2->Query("SELECT ShippingDiscount FROM loyaltysavings WHERE OrderID=" . intval($MainOrderID));
				$shippingLoyaltyDiscount = $dbCmd2->GetValue();
				
				if($userControlObj->getLoyaltyProgram() == "Y" && $shippingLoyaltyDiscount > 0){
					$loyaltyProgramSize = 8;
					pdf_save($pdf);
					pdf_setfont ( $pdf, $fontRes, $loyaltyProgramSize);
					pdf_show_xy($pdf, "Free S&H For Super Shipping Members", ($leftBoundry + $CellPadding), $InvoiceBottomBar_Y - $LineHeight*3);
					pdf_restore($pdf);
				}
				
			
				#-- Put a message that says this was "Paid in full" already, if they are paying by credit card  --#
				#-- Otherwise let them know that they will receive an end-of-the-month bill for corporate invoicing
				if($orderBillingType == "N" || $GrandTotal == 0 || ($orderBillingType == "C" && $corporateBalanceOnOrderDate <= 0)){
					pdf_save($pdf);
					pdf_translate($pdf, ($rightBoundry - $CellPadding - 196 ), $InvoiceBottomBar_Y - $LineHeight*4);
					pdf_rotate($pdf, 20);
					pdf_set_parameter( $pdf, "FontOutline", "Godzilla=Godzilla.ttf");
					$fontRes = pdf_findfont($pdf, "Godzilla", "winansi", 1);
					$fontSize = 14;
					pdf_setfont ( $pdf, $fontRes, $fontSize);
					pdf_setcolor($pdf, "both", "RGB", 150/256, 0, 0, 0);
					pdf_show_xy($pdf, 'PAID IN FULL', 0, 0);
					
					pdf_rect($pdf, -3, -3, 81, 17);
					pdf_stroke ($pdf);
					pdf_restore($pdf);
				}
				else if($orderBillingType == "C"){
				
					pdf_save($pdf);
					pdf_translate($pdf, ($leftBoundry + $CellPadding), $InvoiceBottomBar_Y - $LineHeight*3);
					pdf_show_xy($pdf, 'Keep this order receipt for your records.  We will send out an ', 0, 0);
					pdf_show_xy($pdf, 'invoice at the end of this billing cycle to collect payment.', 0, - $LineHeight);
					pdf_restore($pdf);
				
				}
	
			}
	
	
			pdf_end_page($pdf);
	
			pdf_close_image($pdf, $pdfimage);
		}
	
	
		pdf_close($pdf);
	
	
		$data = pdf_get_buffer($pdf);
	
		$PDF_filename = "receipts_" . substr(md5(microtime()), 0, 12) . ".pdf";
	
		##-- Put PDF on disk --##
		$fp = fopen(DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $PDF_filename, "w");
		fwrite($fp, $data);
		fclose($fp);
					
		return $PDF_filename;
		
	}

}



?>
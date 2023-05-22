<?


require_once("library/Boot_Session.php");

// Don't make it look like we are favoring a particular Domain 
Domain::removeTopDomainID();

$projectlist = WebUtil::GetInput("projectlist", FILTER_SANITIZE_STRING_ONE_LINE);
$orderid = WebUtil::GetInput("orderid", FILTER_SANITIZE_INT);
$viewtype = WebUtil::GetInput("viewtype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);






$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


$UserControlObj = new UserControl($dbCmd2);
$PaymentInvoiceObj = new PaymentInvoice();


$monthParameterFromURL = WebUtil::GetInput("month", FILTER_SANITIZE_INT);
$yearParameterFromURL = WebUtil::GetInput("year", FILTER_SANITIZE_INT);


if(!empty($monthParameterFromURL)){
	$PaymentInvoiceObj->SetStopMonth($monthParameterFromURL);
	$PaymentInvoiceObj->SetStopYear($yearParameterFromURL);
}

$month = $PaymentInvoiceObj->GetStopMonth();
$year = $PaymentInvoiceObj->GetStopYear();



//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$UserID = $AuthObj->GetUserID();

$domainObj = Domain::singleton();

if(!$AuthObj->CheckForPermission("CORPORATE_BILLING"))
	WebUtil::PrintAdminError("Not Available");




#-- Make this script be able to run for at least 3 mintuest  --#
set_time_limit(500);




$pdf_coord_conversion = 72;


#-------------------------------------------- ###  Setup Variables   ### -------------------------#
// Most values here indicate inches" on an 8.5 x 11 sheet




$greyShadingValue = 0.7;

$pageWidth = $pdf_coord_conversion * 8.5;
$pageHeight = $pdf_coord_conversion * 11;

$LineHeight = 12;

$CellPadding = 5;

$leftBoundry = $pdf_coord_conversion * 0.7;
$rightBoundry = $pdf_coord_conversion * 7.8;


$MaxiumLineEntries_Page_1 = 20;
$MaxiumLineEntries_RemainingPages = 47;

$headerLogo_X = $rightBoundry - 65;
$headerLogo_Y = $pageHeight - 45;

$headerCustomerService_X = $leftBoundry;
$headerCustomerService_Y = $pdf_coord_conversion * 10.5;



$headerPageNumber_X = $pdf_coord_conversion * 6.9;
$headerPageNumber_Y = $pdf_coord_conversion * 10.1;

$headerLine1_Y = $pdf_coord_conversion * 10.2;
$headerLine2_Y = $pdf_coord_conversion * 9.7;

$headerCol_1_X = $pdf_coord_conversion * 3.0;
$headerCol_2_X = $pdf_coord_conversion * 4.2;
$headerCol_3_X = $pdf_coord_conversion * 5.4;
$headerCol_4_X = $pdf_coord_conversion * 6.6;

$headerStatementClosingDate_X = $headerCol_1_X;
$headerStatementClosingDate_Y = $headerLine1_Y;

$headerCreditLine_X = $headerCol_2_X ;
$headerCreditLine_Y = $headerLine1_Y;

$headerAvailableCredit_X = $headerCol_3_X;
$headerAvailableCredit_Y = $headerLine1_Y;

$headerPreviousBalance_X = $headerCol_1_X;
$headerPreviousBalance_Y = $headerLine2_Y;

$headerNewCharges_X = $headerCol_2_X;
$headerNewCharges_Y = $headerLine2_Y;

$headerPaymentActivity_X = $headerCol_3_X;
$headerPaymentActivity_Y = $headerLine2_Y;

$headerNewBalance_X = $headerCol_4_X;
$headerNewBalance_Y = $headerLine2_Y;


$headerCompany_X = $headerCol_1_X;
$headerCompany_Y = $pdf_coord_conversion * 10.5;

$headerAccountNumber_X = $headerCol_3_X;
$headerAccountNumber_Y = $pdf_coord_conversion * 10.5;


$headerLineY = $pageHeight - $pdf_coord_conversion * 1.7;


$firstEntry_Y = $headerLineY - 45;


$returnDate_X = $pdf_coord_conversion * 2.2;
$returnBalance_X = $pdf_coord_conversion * 3.5;
$returnMinimumDue_X = $pdf_coord_conversion * 4.5;
$returnAmountEnclosed_X = $pdf_coord_conversion * 5.5;

$returnSlipAmounts_Y = $pdf_coord_conversion * 2;

$returnAccountNumber_X = $leftBoundry + 6 * $pdf_coord_conversion;
$returnAccountNumber_Y = $pdf_coord_conversion * 3.0;

$returnBarcode_X = $leftBoundry + 4 * $pdf_coord_conversion;
$returnBarcode_Y = $pdf_coord_conversion * 3.0;

$returnCreditDontPay_X = $leftBoundry + 3.5 * $pdf_coord_conversion;
$returnCreditDontPay_Y = $pdf_coord_conversion * 2.5;

$returnLogo_X = $leftBoundry;
$returnLogo_Y = $pdf_coord_conversion * 2.7;

$returnMailingAddress_X = $leftBoundry + 0.5 * $pdf_coord_conversion;
$returnMailingAddress_Y = $pdf_coord_conversion * 1.0;

$returnCompanyAddress_X = $leftBoundry + 5.0 * $pdf_coord_conversion;
$returnCompanyAddress_Y = $pdf_coord_conversion * 1.0;



$CutLine_Y = $pdf_coord_conversion * 3.5;




#--------------------------------------------------- ###  Setup Variables   ### -------------------------#


$InvoicesGenerated = false;


#-- Create the PDF doc with PHP's extension PDFlib --#
$pdf = pdf_new();
if(!Constants::GetDevelopmentServer()){
	pdf_set_parameter($pdf, "license", Constants::GetPDFlibLicenseKey());
}

pdf_open_file($pdf, "");
pdf_set_info($pdf, "Title", "Corporate Billing: ");
pdf_set_info($pdf, "Subject", "Billing Invoices");

PDF_set_parameter($pdf, "SearchPath", Constants::GetFontBase());

pdf_set_parameter( $pdf, "FontOutline", "Decker=Decker.ttf");
pdf_set_parameter( $pdf, "FontOutline", "C128M=C128M.ttf");
pdf_set_parameter( $pdf, "FontOutline", "Eurasiab=Eurasiab.ttf");

$BarcodeFont = pdf_findfont($pdf, "C128M", "winansi", 1);
$DeckerFont = pdf_findfont($pdf, "Decker", "winansi", 1);
$EurasiaBoldFont = pdf_findfont($pdf, "Eurasiab", "winansi", 1);



$topBackImage = PDF_load_image ($pdf, "jpeg", Constants::GetWebserverBase() . "/images/corporate_invoice_top_back.jpg", "");
if(!$topBackImage)
	exit( "Problem loading the background image logo.");


$passiveAuthObj = Authenticate::getPassiveAuthObject();

// Get a list of any User that has ever placed an order with corporate billling
$dbCmd->Query("SELECT DISTINCT UserID FROM orders WHERE BillingType='C' AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));
while($CustomerID = $dbCmd->GetValue()){
	
	$UserControlObj->LoadUserByID($CustomerID);
	$PaymentInvoiceObj->LoadCustomerByID($CustomerID);
	
	$domaindIDofCustomer = UserControl::getDomainIDofUser($CustomerID);
	$domainWebsiteURL = Domain::getWebsiteURLforDomainID($domaindIDofCustomer);
	
	$CurrentBalance = $PaymentInvoiceObj->GetCurrentBalance($year, $month);
	
	$domainAddressObj = new DomainAddresses($domaindIDofCustomer);
	$billingAddressObj = $domainAddressObj->getBillingDepartmentAddressObj();
	
	$CustomerService_Number = $billingAddressObj->getPhoneNumber();
	$CompanyName = $billingAddressObj->getCompanyName();
	$CompanyAddress = $billingAddressObj->getAddressOne() . " " . $billingAddressObj->getAddressTwo();
	$CompanyCityState = $billingAddressObj->getCity() . ", " . $billingAddressObj->getState();
	$CompanyZip = $billingAddressObj->getZipCode();
	
	
	$domainLogoObj = new DomainLogos($domaindIDofCustomer);
	
	$logoImage = PDF_load_image ($pdf, "jpeg", Constants::GetInvoiceLogoPath() . "/" . $domainLogoObj->printQualtityMediumJPG, "");
	if(!$logoImage)
		exit( "Problem loading the logo image.");

	// Skip over Small (or Positive) Balances.
	if($CurrentBalance <= 5)
		continue;

	$InvoicesGenerated = true;

	// Get a list for all balance adjustments, payments received, and orders
	$OrderInfoArr = $PaymentInvoiceObj->GetMonthOrderHistory($year, $month);
	$PaymentReceivedArr = $PaymentInvoiceObj->GetMonthPaymentHistory($year, $month);
	$AdjustmentHistoryArr = $PaymentInvoiceObj->GetMonthAdjustmentsHistory($year, $month);

	
	// Each entry has for all of the 3 arrays above takes up 1 line
	// Add them all together to figure how how many pages this invoice will take
	$TotalLinesNeeded = sizeof($OrderInfoArr) + sizeof($PaymentReceivedArr) + sizeof($AdjustmentHistoryArr);

	$TotalPages = 1;

	if($TotalLinesNeeded > $MaxiumLineEntries_Page_1)
		$TotalPages += ceil(($TotalLinesNeeded - $MaxiumLineEntries_Page_1) / $MaxiumLineEntries_RemainingPages);
		
	// Will keep track of the order that we are on as we extent into multiple pages.	
	$OrderCounter = 0;
		
	
	for($PageNumber = 1; $PageNumber <= $TotalPages; $PageNumber++){

		
		$dataLinesWritten = 0;  // Will help us to determine when it is time to roll into a new page
		$bufferLinesWritten = 0; // Will keep the space for us between different sections


		#-- Make a new page 8.5 by 11 --#
		pdf_begin_page($pdf, $pageWidth, $pageHeight);
		pdf_add_bookmark($pdf, "CustomerID: " . $CustomerID, 0, 0);

		$fontSize = 10;
		pdf_setfont ( $pdf, $DeckerFont, $fontSize);


		// Put the yellow gradient in the top of the header
		pdf_save($pdf);
		pdf_translate($pdf, $leftBoundry, $headerLineY);
		PDF_scale($pdf, 0.64, 0.6);
		PDF_place_image($pdf, $topBackImage, 0, 0, 1);
		pdf_restore($pdf);

		// Put the logo on upper-right hand of the statement header
		pdf_save($pdf);
		pdf_translate($pdf, $headerLogo_X, $headerLogo_Y);
		PDF_scale($pdf, 0.3, 0.3);
		PDF_place_image($pdf, $logoImage, 0, 0, 1);
		pdf_restore($pdf);
		

		// Show the page number
		pdf_show_xy($pdf, "Page $PageNumber of $TotalPages", $headerPageNumber_X, $headerPageNumber_Y);


		// Show the customer account number at the top
		pdf_save($pdf);
		$fontSize = 7;
		pdf_setfont ( $pdf, $DeckerFont, $fontSize);
		pdf_show_xy($pdf, $UserControlObj->getCompanyOrName(), $headerCompany_X , $headerCompany_Y );
		pdf_show_xy($pdf, "Account #: U" . $CustomerID, $headerAccountNumber_X , $headerAccountNumber_Y );
		pdf_restore($pdf);



		// Draw the customer Service Header
		pdf_save($pdf);
		$fontSize = 11;
		pdf_setfont ( $pdf, $EurasiaBoldFont, $fontSize);
		pdf_setcolor($pdf, "both", "RGB", 0, 0, 150/256, 0);
		pdf_show_xy($pdf, "Customer Service", $headerCustomerService_X , $headerCustomerService_Y );

		// Underline for the Customer Service header
		pdf_setcolor($pdf, "both", "RGB", 0, 0, 0, 0);
		pdf_moveto($pdf, $headerCustomerService_X , $headerCustomerService_Y - 2 );
		pdf_lineto($pdf, $headerCustomerService_X+120 , $headerCustomerService_Y - 2);
		pdf_stroke ($pdf);

		pdf_restore($pdf);



		// Put the Customer Service info and 800 number
		pdf_save($pdf);
		pdf_setfont ( $pdf, $DeckerFont, 8);
		pdf_show_xy($pdf, ("http://" . $domainWebsiteURL), $headerCustomerService_X , $headerCustomerService_Y - $LineHeight * 1);
		pdf_show_xy($pdf, $CustomerService_Number, $headerCustomerService_X , $headerCustomerService_Y - $LineHeight * 2);

		pdf_show_xy($pdf, $CompanyName, $headerCustomerService_X , $headerCustomerService_Y - $LineHeight * 4);
		pdf_show_xy($pdf, $CompanyAddress, $headerCustomerService_X , $headerCustomerService_Y - $LineHeight * 5);
		pdf_show_xy($pdf, ($CompanyCityState . " " . $CompanyZip), $headerCustomerService_X , $headerCustomerService_Y - $LineHeight * 6);		
		pdf_restore($pdf);

		// Draw a bottom line under the Header 
		pdf_save($pdf);
		pdf_setlinewidth($pdf, 2);
		pdf_moveto($pdf, $leftBoundry , $headerLineY );
		pdf_lineto($pdf, $rightBoundry , $headerLineY);
		pdf_stroke ($pdf);
		pdf_restore($pdf);



		// Only show the detachable return slip on the first page.
		// The balance adjustments and Payment received will only show up on the front page as well
		if($PageNumber == 1){

			// Put the logo on the return slip
			pdf_save($pdf);
			pdf_translate($pdf, $returnLogo_X, $returnLogo_Y);
			PDF_scale($pdf, 0.4, 0.4);
			PDF_place_image($pdf, $logoImage, 0, 0, 1);
			pdf_restore($pdf);


			// Draw the dashed line that shows were to tear the return slip 
			pdf_save($pdf);
			pdf_setdash ($pdf, 2.0, 2.0);
			pdf_setlinewidth ( $pdf, 0.3); 
			pdf_moveto($pdf, 0, $CutLine_Y);
			pdf_lineto($pdf, $pageWidth, $CutLine_Y);
			pdf_stroke($pdf);
			
			pdf_setfont ( $pdf, $DeckerFont, 6);
			pdf_show_xy($pdf, "Detach below and return with payment.", $rightBoundry - 100 , $CutLine_Y + 5); 
			
			pdf_restore($pdf);


			pdf_show_xy($pdf, ("Visit: " . $domainWebsiteURL), $leftBoundry, ($CutLine_Y - $LineHeight));

			// Show the address that this invoice will be sent to.
			pdf_show_xy($pdf, $UserControlObj->getCompanyOrName(), $returnMailingAddress_X, $returnMailingAddress_Y);
			pdf_show_xy($pdf, $UserControlObj->getBothAddresses(), $returnMailingAddress_X, $returnMailingAddress_Y - $LineHeight);
			pdf_show_xy($pdf, $UserControlObj->getCity() . ", " . $UserControlObj->getState() . " " . $UserControlObj->getZip() , $returnMailingAddress_X, $returnMailingAddress_Y - $LineHeight * 2);


			// Show the return address
			pdf_show_xy($pdf, $CompanyName, $returnCompanyAddress_X, $returnCompanyAddress_Y);
			pdf_show_xy($pdf, $CompanyAddress, $returnCompanyAddress_X, $returnCompanyAddress_Y - $LineHeight);
			pdf_show_xy($pdf, ($CompanyCityState . " " . $CompanyZip), $returnCompanyAddress_X, $returnCompanyAddress_Y - $LineHeight * 2);



			// Show the User ID (or the Account Number)
			pdf_show_xy($pdf, $CustomerID, $returnAccountNumber_X, $returnAccountNumber_Y);


			// The the amounts on the return slip
			// The due date is 1 month ahead of the current month that we generated this invoice.
			$DueDate = mktime(1,0,0, ($month + 1), $PaymentInvoiceObj->GetDueDay(), $year);
			pdf_show_xy($pdf, date("M j, Y", $DueDate), $returnDate_X, $returnSlipAmounts_Y);

			pdf_show_xy($pdf, Widgets::GetPriceFormat($CurrentBalance), $returnBalance_X, $returnSlipAmounts_Y);
			pdf_show_xy($pdf, Widgets::GetPriceFormat($CurrentBalance), $returnMinimumDue_X, $returnSlipAmounts_Y);


			// Draw all of the boxes and background for the return slip
			$ReturnSlipBoxesHeight = 12;
			pdf_save($pdf);

			pdf_rect($pdf, ($returnBalance_X - $CellPadding), ($returnSlipAmounts_Y - $CellPadding), 60, ($ReturnSlipBoxesHeight + $CellPadding));
			pdf_stroke ($pdf);
			pdf_rect($pdf, ($returnMinimumDue_X - $CellPadding), ($returnSlipAmounts_Y - $CellPadding), 60, ($ReturnSlipBoxesHeight + $CellPadding));
			pdf_stroke ($pdf);
			pdf_rect($pdf, ($returnDate_X - $CellPadding), ($returnSlipAmounts_Y - $CellPadding), 80, ($ReturnSlipBoxesHeight + $CellPadding));
			pdf_stroke ($pdf);

			pdf_rect($pdf, ($returnAmountEnclosed_X - $CellPadding), ($returnSlipAmounts_Y - $CellPadding), 120, ($ReturnSlipBoxesHeight + $CellPadding));
			pdf_stroke ($pdf);
			pdf_rect($pdf, ($returnAccountNumber_X - $CellPadding), ($returnAccountNumber_Y - $CellPadding), 70, ($ReturnSlipBoxesHeight + $CellPadding));
			pdf_stroke ($pdf);

			pdf_restore($pdf);



			// Show little grey Boxes inside of the Amount Enclosed box
			$NumberBoxSize = 12;
			$BoxSpacing = 14;
			pdf_save($pdf);
			pdf_setcolor($pdf, "both", "RGB", 230/256, 230/256, 230/256, 0);
			pdf_rect($pdf, ($returnAmountEnclosed_X + $BoxSpacing*1 - $CellPadding), ($returnSlipAmounts_Y - $CellPadding/2), $NumberBoxSize, $NumberBoxSize);
			pdf_rect($pdf, ($returnAmountEnclosed_X + $BoxSpacing*2 - $CellPadding), ($returnSlipAmounts_Y - $CellPadding/2), $NumberBoxSize, $NumberBoxSize);
			pdf_rect($pdf, ($returnAmountEnclosed_X + $BoxSpacing*3 - $CellPadding + 2), ($returnSlipAmounts_Y - $CellPadding/2), $NumberBoxSize, $NumberBoxSize);
			pdf_rect($pdf, ($returnAmountEnclosed_X + $BoxSpacing*4 - $CellPadding + 2), ($returnSlipAmounts_Y - $CellPadding/2), $NumberBoxSize, $NumberBoxSize);
			pdf_rect($pdf, ($returnAmountEnclosed_X + $BoxSpacing*5 - $CellPadding + 2), ($returnSlipAmounts_Y - $CellPadding/2), $NumberBoxSize, $NumberBoxSize);
			pdf_rect($pdf, ($returnAmountEnclosed_X + $BoxSpacing*6 - $CellPadding + 4), ($returnSlipAmounts_Y - $CellPadding/2), $NumberBoxSize, $NumberBoxSize);
			pdf_rect($pdf, ($returnAmountEnclosed_X + $BoxSpacing*7 - $CellPadding + 4), ($returnSlipAmounts_Y - $CellPadding/2), $NumberBoxSize, $NumberBoxSize);
			pdf_stroke ($pdf);
			pdf_restore($pdf);


			// Draw the background Field Labels that go above the boxes
			pdf_save($pdf);

			$fontSize = 6;
			pdf_setfont ( $pdf, $EurasiaBoldFont, $fontSize);
			pdf_setcolor($pdf, "both", "RGB", 0, 0, 150/256, 0);

			pdf_show_xy($pdf, "Payment Due Date", $returnDate_X, ($returnSlipAmounts_Y + $ReturnSlipBoxesHeight + $CellPadding) );
			pdf_show_xy($pdf, "Your Total Balance", $returnBalance_X, ($returnSlipAmounts_Y + $ReturnSlipBoxesHeight + $CellPadding) );
			pdf_show_xy($pdf, "Minimum Amount Due", $returnMinimumDue_X, ($returnSlipAmounts_Y + $ReturnSlipBoxesHeight + $CellPadding) );
			pdf_show_xy($pdf, "Account Number", $returnAccountNumber_X, ($returnAccountNumber_Y + $ReturnSlipBoxesHeight + $CellPadding) );
			pdf_show_xy($pdf, "Amount of Payment Enclosed", $returnAmountEnclosed_X, ($returnSlipAmounts_Y + $ReturnSlipBoxesHeight + $CellPadding) );
			pdf_show_xy($pdf, "Please remit payment to:", $returnCompanyAddress_X, ($returnCompanyAddress_Y + $LineHeight) );

			pdf_setfont ( $pdf, $EurasiaBoldFont, 10);
			pdf_show_xy($pdf, "$", $returnAmountEnclosed_X , $returnSlipAmounts_Y );
			pdf_setfont ( $pdf, $EurasiaBoldFont, 19);
			pdf_show_xy($pdf, ".", $returnAmountEnclosed_X + 78.3 , $returnSlipAmounts_Y - 3 );


			// Carrot to separate thousands place
			pdf_setfont ( $pdf, $EurasiaBoldFont, 11);
			pdf_setcolor($pdf, "both", "RGB", 190/256, 190/256, 190/256, 0);
			pdf_show_xy($pdf, "^", $returnAmountEnclosed_X + 34.2 , ($returnSlipAmounts_Y - 6) );	
			pdf_restore($pdf);
			
			
			// Put the barcode of the customer ID on the return slip
			pdf_save($pdf);
			pdf_setfont ( $pdf, $BarcodeFont, 14);
			pdf_show_xy($pdf, WebUtil::GetBarCode128($CustomerID), $returnBarcode_X , $returnBarcode_Y );
			pdf_restore($pdf);
			

			// If the balance is negative... let the customer know not to pay us.
			if($CurrentBalance < 0){
				pdf_save($pdf);
				pdf_setfont ( $pdf, $EurasiaBoldFont, 11);
				pdf_setcolor($pdf, "both", "RGB", 190/256, 0, 0, 0);
				pdf_show_xy($pdf, "Credit, Don't Pay", $returnCreditDontPay_X , $returnCreditDontPay_Y );
				pdf_restore($pdf);
			}
			

			// Draw the background Field Labels that go above the boxes
			pdf_save($pdf);

			$fontSize = 6;
			pdf_setfont ( $pdf, $EurasiaBoldFont, $fontSize);
			pdf_setcolor($pdf, "both", "RGB", 0, 0, 150/256, 0);

			pdf_show_xy($pdf, "Statement Closing Date", $headerStatementClosingDate_X, $headerStatementClosingDate_Y );
			pdf_show_xy($pdf, "Credit Line", $headerCreditLine_X, $headerCreditLine_Y );
			pdf_show_xy($pdf, "Available Credit", $headerAvailableCredit_X, $headerAvailableCredit_Y );
			pdf_show_xy($pdf, "Previous Balance", $headerPreviousBalance_X, $headerPreviousBalance_Y );
			pdf_show_xy($pdf, "New Charges", $headerNewCharges_X, $headerNewCharges_Y );
			pdf_show_xy($pdf, "Payment Activity", $headerPaymentActivity_X, $headerPaymentActivity_Y );
			pdf_show_xy($pdf, "New Balance", $headerNewBalance_X, $headerNewBalance_Y );

			pdf_restore($pdf);



			// Put the Amounts and Statment closing date in the header
			pdf_save($pdf);
			pdf_setfont ( $pdf, $DeckerFont, 8);

			pdf_show_xy($pdf, date("M j, Y", mktime(1,0,0, $month, $PaymentInvoiceObj->GetStatementClosingDay(), $year)), $headerStatementClosingDate_X, $headerStatementClosingDate_Y  - $LineHeight );
			pdf_show_xy($pdf, Widgets::GetPriceFormat($UserControlObj->getCreditLimit()), $headerCreditLine_X, $headerCreditLine_Y  - $LineHeight );
			pdf_show_xy($pdf, Widgets::GetPriceFormat($UserControlObj->getCreditLimit() - $PaymentInvoiceObj->GetCurrentCreditUsage()), $headerAvailableCredit_X, $headerAvailableCredit_Y  - $LineHeight);
			pdf_show_xy($pdf, Widgets::GetPriceFormat($PaymentInvoiceObj->GetStartingBalance($year, $month)), $headerPreviousBalance_X, $headerPreviousBalance_Y  - $LineHeight );
			pdf_show_xy($pdf, "(+)  " . Widgets::GetPriceFormat($PaymentInvoiceObj->GetMonthCharges($year, $month)), $headerNewCharges_X, $headerNewCharges_Y  - $LineHeight );
			pdf_show_xy($pdf, "( - )  " . Widgets::GetPriceFormat($PaymentInvoiceObj->GetMonthPaymentsReceived($year, $month)), $headerPaymentActivity_X, $headerPaymentActivity_Y  - $LineHeight);
			pdf_show_xy($pdf, Widgets::GetPriceFormat($CurrentBalance), $headerNewBalance_X, $headerNewBalance_Y  - $LineHeight);		
			pdf_restore($pdf);
			

			
			// Draw all of the Payments Recieved
			// This only happens on the first page.  I don't think we will have that many payments or balance adjustments for a customer
			// If we have a ton then it could be a problem becuase it won't wrap onto the next page.
			if(sizeof($PaymentReceivedArr) <> 0){
				pdf_save($pdf);
				
				
				$fontSize = 10;
				pdf_setfont ( $pdf, $EurasiaBoldFont, $fontSize);
				pdf_setcolor($pdf, "both", "RGB", 150/256, 0, 0, 0);					
				pdf_show_xy($pdf, "Payment Activity", $leftBoundry, $firstEntry_Y + 5 );

				// Shading behind column header
				pdf_setcolor($pdf, "both", "RGB", 235/256, 235/256, 235/256, 0);
				pdf_rect($pdf, $leftBoundry, ($firstEntry_Y - 11), ($rightBoundry -  $leftBoundry), 11);
				pdf_fill_stroke($pdf);
				
				// Lines behing column header
				pdf_setcolor($pdf, "both", "RGB", 0, 0, 0, 0);
				pdf_setlinewidth($pdf, 1);
				pdf_moveto($pdf, $leftBoundry , $firstEntry_Y);
				pdf_lineto($pdf, $rightBoundry , $firstEntry_Y);
				pdf_moveto($pdf, $leftBoundry , $firstEntry_Y - 11);
				pdf_lineto($pdf, $rightBoundry , $firstEntry_Y - 11);
				pdf_stroke ($pdf);


				$Col1 = $leftBoundry;
				$Col2 = $pdf_coord_conversion * 2.5;
				$Col3 = $pdf_coord_conversion * 6.6;
				
				$fontSize = 9;
				pdf_setfont ( $pdf, $EurasiaBoldFont, $fontSize);
				pdf_show_xy($pdf, "Date", $Col1, ($firstEntry_Y - 9) );
				pdf_show_xy($pdf, "Notes", $Col2, ($firstEntry_Y - 9) );
				
				// Right Justify the amount
				$AmountLineWidth = pdf_stringwidth ( $pdf, "Amount", $EurasiaBoldFont, $fontSize);						
				pdf_show_xy($pdf, "Amount", ($Col3 - $AmountLineWidth), ($firstEntry_Y - 9) );
				
				pdf_restore($pdf);


				// The header takes up 2 data lines
				$bufferLinesWritten += 2;
				
				pdf_save($pdf);
				$fontSize = 8;
				pdf_setfont ( $pdf, $DeckerFont, $fontSize);
				foreach($PaymentReceivedArr as $thisPaymentLine){
				
					pdf_show_xy($pdf, date("M j, Y", $thisPaymentLine["UnixDate"]), $Col1, ($firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten) );
					pdf_show_xy($pdf, $thisPaymentLine["Notes"], $Col2, ($firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten) );
					
					// Right Justify the amount
					$AmountLineWidth = pdf_stringwidth ( $pdf, Widgets::GetPriceFormat($thisPaymentLine["Amount"]), $DeckerFont, $fontSize);						
					pdf_show_xy($pdf, Widgets::GetPriceFormat($thisPaymentLine["Amount"]), $Col3 - ($AmountLineWidth), ($firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten) );
					
					$dataLinesWritten++;
				}
				pdf_restore($pdf);
				
				// Blank space for whatever comes next
				$bufferLinesWritten += 2;
			}
			
			
			// Draw all of the Balance Adjustments / Refunds
			// This only happens on the first page.  I don't think we will have that many payments or balance adjustments for a customer
			// If we have a ton then it could be a problem becuase it won't wrap onto the next page.
			if(sizeof($AdjustmentHistoryArr) <> 0){
				pdf_save($pdf);
				
				

				$fontSize = 10;
				pdf_setfont ( $pdf, $EurasiaBoldFont, $fontSize);
				pdf_setcolor($pdf, "both", "RGB", 150/256, 0, 0, 0);					
				pdf_show_xy($pdf, "Adjustments / Refunds", $leftBoundry, $firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten + 5 );

				// Shading behind column header
				pdf_setcolor($pdf, "both", "RGB", 235/256, 235/256, 235/256, 0);
				pdf_rect($pdf, $leftBoundry, ($firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten - 11), ($rightBoundry -  $leftBoundry), 11);
				pdf_fill_stroke($pdf);
				
				// Lines behing column header
				pdf_setcolor($pdf, "both", "RGB", 0, 0, 0, 0);
				pdf_setlinewidth($pdf, 1);
				pdf_moveto($pdf, $leftBoundry , $firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten);
				pdf_lineto($pdf, $rightBoundry , $firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten);
				pdf_moveto($pdf, $leftBoundry , $firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten - 11);
				pdf_lineto($pdf, $rightBoundry , $firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten - 11);
				pdf_stroke ($pdf);


				$Col1 = $leftBoundry;
				$Col2 = $pdf_coord_conversion * 2.0;
				$Col3 = $pdf_coord_conversion * 3.1;
				$Col4 = $pdf_coord_conversion * 7.5;
				
				$fontSize = 9;
				pdf_setfont ( $pdf, $EurasiaBoldFont, $fontSize );
				pdf_show_xy($pdf, "Date", $Col1, ($firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten - 9) );
				pdf_show_xy($pdf, "Order Ref#", $Col2, ($firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten - 9) );
				pdf_show_xy($pdf, "Notes", $Col3, ($firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten - 9) );
				
				// Right Justify the amount
				$AmountLineWidth = pdf_stringwidth ( $pdf, "Amount", $EurasiaBoldFont, $fontSize);						
				pdf_show_xy($pdf, "Amount", ($Col4 - $AmountLineWidth), ($firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten - 9) );
				
				pdf_restore($pdf);

				// The header takes up 2 data lines
				$bufferLinesWritten += 2;

				pdf_save($pdf);
				$fontSize = 8;
				pdf_setfont ( $pdf, $DeckerFont, $fontSize);					
				foreach($AdjustmentHistoryArr as $thisAdjustmentLine){
				
					pdf_show_xy($pdf, date("M j, Y", $thisAdjustmentLine["DateCreated"]), $Col1, ($firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten) );
					pdf_show_xy($pdf, Order::GetHashedOrderNo($thisAdjustmentLine["OrderID"]), $Col2, ($firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten) );
					pdf_show_xy($pdf, $thisAdjustmentLine["Description"], $Col3, ($firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten) );
					
					// Right Justify the amount
					$AmountLineWidth = pdf_stringwidth ( $pdf, Widgets::GetPriceFormat($thisAdjustmentLine["Amount"]), $DeckerFont, $fontSize);
					pdf_show_xy($pdf, Widgets::GetPriceFormat($thisAdjustmentLine["Amount"]), ($Col4 - $AmountLineWidth), ($firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten) );
					
					$dataLinesWritten++;
				}
				pdf_restore($pdf);
				
				// Blank space for whatever comes next
				$bufferLinesWritten += 2;
			}

		}





		// Draw The order history
		// This may wrap onto many pages
		if(sizeof($OrderInfoArr) <> 0){
			
			pdf_save($pdf);

			$fontSize = 10;
			pdf_setfont ( $pdf, $EurasiaBoldFont, $fontSize);
			pdf_setcolor($pdf, "both", "RGB", 150/256, 0, 0, 0);					
			pdf_show_xy($pdf, "Order History", $leftBoundry, $firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten + 5 );

			pdf_setfont ( $pdf, $DeckerFont, 7);
			pdf_setcolor($pdf, "both", "RGB", 0, 0, 0, 0);					
			pdf_show_xy($pdf, "Total Orders This Period: " . sizeof($OrderInfoArr), $leftBoundry + 70, $firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten + 5 );


			// Shading behind column header
			pdf_setcolor($pdf, "both", "RGB", 235/256, 235/256, 235/256, 0);
			pdf_rect($pdf, $leftBoundry, ($firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten - 11), ($rightBoundry -  $leftBoundry), 11);
			pdf_fill_stroke($pdf);

			// Lines behing column header
			pdf_setcolor($pdf, "both", "RGB", 0, 0, 0, 0);
			pdf_setlinewidth($pdf, 1);
			pdf_moveto($pdf, $leftBoundry , $firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten);
			pdf_lineto($pdf, $rightBoundry , $firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten);
			pdf_moveto($pdf, $leftBoundry , $firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten - 11);
			pdf_lineto($pdf, $rightBoundry , $firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten - 11);
			pdf_stroke ($pdf);


			$Col1 = $leftBoundry;
			$Col2 = $pdf_coord_conversion * 2.0;
			$Col3 = $pdf_coord_conversion * 3.1;
			$Col4 = $pdf_coord_conversion * 7.5;

			$fontSize = 9;
			pdf_setfont ( $pdf, $EurasiaBoldFont, $fontSize);
			pdf_show_xy($pdf, "Date", $Col1, ($firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten - 9) );
			pdf_show_xy($pdf, "Order #", $Col2, ($firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten - 9) );
			pdf_show_xy($pdf, "Notes", $Col3, ($firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten - 9) );
			
			// Right Justify the amount
			$AmountLineWidth = pdf_stringwidth ( $pdf, "Amount", $EurasiaBoldFont, $fontSize);				
			pdf_show_xy($pdf, "Amount", ($Col4 - $AmountLineWidth), ($firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten - 9) );

			pdf_restore($pdf);

			// The header takes up 2 data lines
			$bufferLinesWritten += 2;

			
			$fontSize = 8;
			pdf_setfont ( $pdf, $DeckerFont, $fontSize);
			
			$OrderLoopCounter = 0;
			foreach($OrderInfoArr as $thisOrderLine){
			
				
				// If we are on a 2nd 3rd page then we need to skip orders from the previous page(s)
				if( $OrderLoopCounter < $OrderCounter ){
					$OrderLoopCounter++;
					continue;
				}
				
				$OrderCounter++;
				$OrderLoopCounter++;
				

				pdf_show_xy($pdf, date("M j, Y", $thisOrderLine["DateOrdered"]), $Col1, ($firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten) );
				pdf_show_xy($pdf, Order::GetHashedOrderNo($thisOrderLine["OrderID"]), $Col2, ($firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten) );
				pdf_show_xy($pdf, $thisOrderLine["InvoiceNote"], $Col3, ($firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten) );
				
				// Right Justify the amount
				$AmountLineWidth = pdf_stringwidth ( $pdf, Widgets::GetPriceFormat($thisOrderLine["OrderTotal"]), $DeckerFont, $fontSize);
				pdf_show_xy($pdf, Widgets::GetPriceFormat($thisOrderLine["OrderTotal"]), $Col4 - $AmountLineWidth, ($firstEntry_Y - $LineHeight * $dataLinesWritten - $LineHeight * $bufferLinesWritten) );

				$dataLinesWritten++;
				
				// Skip to the next page if we have filled up the amount of orders that can exist on the page
				if($PageNumber == 1 && $dataLinesWritten >= $MaxiumLineEntries_Page_1){
					pdf_end_page($pdf);
					continue 2;
				}
					
				if($PageNumber > 1 && $dataLinesWritten >= $MaxiumLineEntries_RemainingPages){
					pdf_end_page($pdf);
					continue 2;
				}
			}
		

			// Blank space for whatever comes next
			$bufferLinesWritten += 2;
		}


		pdf_end_page($pdf);
	}




	pdf_close_image($pdf, $logoImage);
}



if(!$InvoicesGenerated){
	print "<html><br><br><br><div align=center>No invoices are available.<br><br><a href='javascript:self.close();'>Close</a></div><br><br><br></html>";
	exit;
}







pdf_close_image($pdf, $topBackImage);


pdf_close($pdf);



$data = pdf_get_buffer($pdf);

$PDF_filename = "invoices_" . substr(md5(microtime()), 0, 12) . ".pdf";

##-- Put PDF on disk --##
$fp = fopen(DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $PDF_filename, "w");
fwrite($fp, $data);
fclose($fp);





#-- Redirect to the Temporary PDF document ---#
#-- A cron job will need to delete the proofs every 2 hours or so to keep the disk from getting full --#
header("Location: " . DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $PDF_filename);



?>
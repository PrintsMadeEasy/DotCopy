<?

require_once("library/Boot_Session.php");






$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


$SalesRepObj = new SalesRep($dbCmd2);

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);



$pdf_coord_conversion = 72;


#-------------------------------------------- ###  Setup Variables   ### -------------------------#
// Most values here indicate inches" on an 8.5 x 11 sheet


$pageWidth = $pdf_coord_conversion * 8.5;
$pageHeight = $pdf_coord_conversion * 11;

$LineHeight = 12;

$leftBoundry = $pdf_coord_conversion * 0.7;
$rightBoundry = $pdf_coord_conversion * 7.8;

$headerLogo_X = $leftBoundry;
$headerLogo_Y = $pageHeight - 65;

$headerCustomerService_X = $leftBoundry + $pdf_coord_conversion * 5.4;
$headerCustomerService_Y = $pdf_coord_conversion * 10.5;


$headerPageTitle_X = $leftBoundry + $pdf_coord_conversion * 1.8;
$headerPageTitle_Y = $pdf_coord_conversion * 10.3;


$headerDescription_X = $leftBoundry + $pdf_coord_conversion * 1.8;
$headerDescription_Y = $pdf_coord_conversion * 10.0;


$headerCompany_X = $leftBoundry;
$headerCompany_Y = $pdf_coord_conversion * 9.7;

$headerAccountNumber_X = $leftBoundry;
$headerAccountNumber_Y = $pdf_coord_conversion * 9.5;


$headerLineY = $pageHeight - $pdf_coord_conversion * 1.7;


$salesRepAddressTop_X = $leftBoundry;
$salesRepAddressTop_Y = $pdf_coord_conversion * 8.5;

$InitialBox_X = $leftBoundry + $pdf_coord_conversion * 6.0;
$InitialBox_Y = $pdf_coord_conversion * 8.1;

$Barcode_X = $leftBoundry;
$Barcode_Y = $pdf_coord_conversion * 5.1;


$windowMailingAddress_X = $leftBoundry + 0.5 * $pdf_coord_conversion;
$windowMailingAddress_Y = $pdf_coord_conversion * 1.2;



$domainAddressObj = new DomainAddresses(Domain::oneDomain());
$customerServiceAddressObj = $domainAddressObj->getCustomerServiceAddressObj();

$CompanyName = $customerServiceAddressObj->getCompanyName();
$CompanyAddress = $customerServiceAddressObj->getAddressOne() . " " . $customerServiceAddressObj->getAddressTwo();
$CompanyCityState = $customerServiceAddressObj->getCity() . ", " . $customerServiceAddressObj->getState();
$CompanyZip = $customerServiceAddressObj->getZipCode();
$CustomerService_Number = $customerServiceAddressObj->getPhoneNumber();




#--------------------------------------------------- ###  Setup Variables   ### -------------------------#


$FormsGeneratedFlag = false;


#-- Create the PDF doc with PHP's extension PDFlib --#
$pdf = pdf_new();
if(!Constants::GetDevelopmentServer()){
	pdf_set_parameter($pdf, "license", Constants::GetPDFlibLicenseKey());
}

pdf_open_file($pdf, "");
pdf_set_info($pdf, "Title", "Sales Address Verify");
pdf_set_info($pdf, "Subject", "Sales Address Verify");

PDF_set_parameter($pdf, "SearchPath", Constants::GetFontBase());

pdf_set_parameter( $pdf, "FontOutline", "Decker=Decker.ttf");
pdf_set_parameter( $pdf, "FontOutline", "C128M=C128M.ttf");
pdf_set_parameter( $pdf, "FontOutline", "Eurasiab=Eurasiab.ttf");

$BarcodeFont = pdf_findfont($pdf, "C128M", "winansi", 1);
$DeckerFont = pdf_findfont($pdf, "Decker", "winansi", 1);
$EurasiaBoldFont = pdf_findfont($pdf, "Eurasiab", "winansi", 1);

// Show the yellow gradient and company logo in the bottom left on the return slip
$domainLogoObj = new DomainLogos(Domain::oneDomain());

$logoImage = PDF_load_image ($pdf, "jpeg", Constants::GetInvoiceLogoPath() . "/$domainLogoObj->printQualtityMediumJPG", "");
if(!$logoImage)
	throw new Exception( "Problem loading the invoice logo.");

$topBackImage = PDF_load_image ($pdf, "jpeg", Constants::GetWebserverBase() . "/images/corporate_invoice_top_back.jpg", "");
if(!$topBackImage)
	throw new Exception( "Problem loading the background image logo.");





// We store a session variable for our preferences.... if we want to see Inactive Sales Items over 90 days old
if (!isset($HTTP_SESSION_VARS['ShowInactiveSalesItems']))
	$HTTP_SESSION_VARS['ShowInactiveSalesItems'] = false;
$ShowInactiveItems = $HTTP_SESSION_VARS['ShowInactiveSalesItems'];


// Make a Minimum Mysql Time Stamp... if we are viewing old entries then just set the minimum date to something really far behind to make sure we get everything
if($ShowInactiveItems)
	$MinAddressVerifiedTimeStamp = date("YmdHis", mktime(1, 1, 1, 1, 1, 2002));
else
	$MinAddressVerifiedTimeStamp = date("YmdHis", (time() - (60 * 60 * 24 * 180))); // 130 days




// Get all of the Sales Reps that have not been verified yet.
$dbCmd->Query("SELECT UserID FROM salesreps INNER JOIN users ON users.ID = salesreps.UserID 
				WHERE salesreps.AddressIsVerified='N' AND users.DomainID = ".Domain::oneDomain()." 
				AND salesreps.DateCreated > $MinAddressVerifiedTimeStamp ORDER BY users.DateCreated DESC");
while($SalesRepID = $dbCmd->GetValue()){

	$FormsGeneratedFlag = true;

	$SalesRepObj->LoadSalesRep($SalesRepID);
	
	$domainIDofSalesRep = UserControl::getDomainIDofUser($SalesRepID);
	$websiteURLofDomain = Domain::getWebsiteURLforDomainID($domainIDofSalesRep);


	#-- Make a new page 8.5 by 11 --#
	pdf_begin_page($pdf, $pageWidth, $pageHeight);
	pdf_add_bookmark($pdf, "SalesRepID: " . $SalesRepID, 0, 0);

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
	PDF_scale($pdf, 0.45, 0.45);
	PDF_place_image($pdf, $logoImage, 0, 0, 1);
	pdf_restore($pdf);


	// Page Title
	pdf_save($pdf);
	$fontSize = 15;
	pdf_setfont ( $pdf, $EurasiaBoldFont, $fontSize);
	pdf_show_xy($pdf, "Sales Rep Address Verification", $headerPageTitle_X , $headerPageTitle_Y );
	pdf_restore($pdf);


	// Show the customer account number at the top
	pdf_save($pdf);
	$fontSize = 9;
	pdf_setfont ( $pdf, $DeckerFont, $fontSize);
	pdf_show_xy($pdf, "Simply place your intials below and return this paper ", $headerDescription_X, $headerDescription_Y );
	pdf_show_xy($pdf, "in the provided envelope to fully activate your account.", $headerDescription_X, ($headerDescription_Y - $LineHeight) );
	pdf_restore($pdf);



	// Show the customer account number at the top
	pdf_save($pdf);
	$fontSize = 9;
	pdf_setfont ( $pdf, $DeckerFont, $fontSize);
	pdf_show_xy($pdf, $SalesRepObj->getName(), $headerCompany_X , $headerCompany_Y );
	pdf_show_xy($pdf, "Account # " . $SalesRepID, $headerAccountNumber_X , $headerAccountNumber_Y );
	pdf_restore($pdf);


	// Show Box For their Initials
	pdf_save($pdf);
	pdf_setcolor($pdf, "both", "RGB", 240/256, 240/256, 240/256, 0);
	pdf_rect($pdf, $InitialBox_X, $InitialBox_Y, 55, 35);
	pdf_fill_stroke($pdf);
	pdf_setcolor($pdf, "both", "RGB", 190/256, 190/256, 190/256, 0);
	pdf_rect($pdf, $InitialBox_X, $InitialBox_Y, 55, 35);
	pdf_stroke ($pdf);
	pdf_restore($pdf);

	// Show the description for their initials
	pdf_save($pdf);
	pdf_setcolor($pdf, "both", "RGB", 160/256, 160/256, 160/256, 0);
	$fontSize = 15;
	pdf_setfont ( $pdf, $DeckerFont, $fontSize);
	pdf_show_xy($pdf, "Your Initials >", $InitialBox_X - 90 , $InitialBox_Y + 15 );
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
	pdf_show_xy($pdf, ("http://" . $websiteURLofDomain), $headerCustomerService_X , $headerCustomerService_Y - $LineHeight * 1);
	pdf_show_xy($pdf, $CustomerService_Number, $headerCustomerService_X , $headerCustomerService_Y - $LineHeight * 2);

	pdf_show_xy($pdf, $CompanyName, $headerCustomerService_X , $headerCustomerService_Y - $LineHeight * 3.5);
	pdf_show_xy($pdf, $CompanyAddress, $headerCustomerService_X , $headerCustomerService_Y - $LineHeight * 4.5);
	pdf_show_xy($pdf, $CompanyCityState, $headerCustomerService_X , $headerCustomerService_Y - $LineHeight * 5.5);
	pdf_show_xy($pdf, $CompanyZip, $headerCustomerService_X , $headerCustomerService_Y - $LineHeight * 6.5);		
	pdf_restore($pdf);

	// Draw a bottom line under the Header 
	pdf_save($pdf);
	pdf_setlinewidth($pdf, 2);
	pdf_moveto($pdf, $leftBoundry , $headerLineY );
	pdf_lineto($pdf, $rightBoundry , $headerLineY);
	pdf_stroke ($pdf);
	pdf_restore($pdf);



	// Show the address that this invoice will be sent to.
	pdf_show_xy($pdf, $SalesRepObj->getName(), $salesRepAddressTop_X, $salesRepAddressTop_Y);
	pdf_show_xy($pdf, $SalesRepObj->getBothAddresses(), $salesRepAddressTop_X, $salesRepAddressTop_Y - $LineHeight);
	pdf_show_xy($pdf, $SalesRepObj->getCity() . ", " . $SalesRepObj->getState() , $salesRepAddressTop_X, $salesRepAddressTop_Y - $LineHeight * 2);
	pdf_show_xy($pdf, $SalesRepObj->getZip(), $salesRepAddressTop_X, $salesRepAddressTop_Y - $LineHeight * 3);


	// Show the address that this invoice will be sent to.
	pdf_show_xy($pdf, $SalesRepObj->getName(), $windowMailingAddress_X, $windowMailingAddress_Y);
	pdf_show_xy($pdf, $SalesRepObj->getBothAddresses(), $windowMailingAddress_X, $windowMailingAddress_Y - $LineHeight);
	pdf_show_xy($pdf, $SalesRepObj->getCity() . ", " . $SalesRepObj->getState() , $windowMailingAddress_X, $windowMailingAddress_Y - $LineHeight * 2);
	pdf_show_xy($pdf, $SalesRepObj->getZip(), $windowMailingAddress_X, $windowMailingAddress_Y - $LineHeight * 3);


	// Put the barcode of the Sales Rep ID
	pdf_save($pdf);
	pdf_setfont ( $pdf, $BarcodeFont, 16);
	pdf_show_xy($pdf, WebUtil::GetBarCode128($SalesRepID), $Barcode_X , $Barcode_Y );
	pdf_restore($pdf);
				

	pdf_end_page($pdf);

}



if(!$FormsGeneratedFlag){
	print "<html><br><br><br><div align=center>No Forms are available.<br><br><a href='javascript:self.close();'>Close</a></div><br><br><br></html>";
	exit;
}



pdf_close_image($pdf, $logoImage);
pdf_close_image($pdf, $topBackImage);


pdf_close($pdf);



$data = pdf_get_buffer($pdf);

$PDF_filename = "verify_" . substr(md5(microtime()), 0, 12) . ".pdf";

// Put PDF on disk 
$fp = fopen(DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $PDF_filename, "w");
fwrite($fp, $data);
fclose($fp);



// Redirect to the Temporary PDF document
// A cron job will need to delete the proofs every 2 hours or so to keep the disk from getting full
header("Location: " . DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $PDF_filename);



?>
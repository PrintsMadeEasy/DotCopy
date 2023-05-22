<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();


$savelabel = WebUtil::GetInput("savelabel", FILTER_SANITIZE_STRING_ONE_LINE);

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("RACK_CONTROL"))
	throw new Exception("Permission Denied.");


$RackControlObj = new RackControl($dbCmd, Domain::oneDomain());
	
$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if($action){
	
	WebUtil::checkFormSecurityCode();

	if($action == "clearrackposition"){
	
		$OrderID = WebUtil::GetInput("orderid", FILTER_SANITIZE_INT);
		
		$RackControlObj->ValidateOrderID($OrderID);
		
		// Record project history
		$projectsInOrderArr = Order::getProjectIDsInOrder($dbCmd, $OrderID);
		foreach($projectsInOrderArr as $thisProjectID)
			ProjectHistory::RecordProjectHistory($dbCmd, $thisProjectID, "Removed From Rack (by web interface)", $UserID);

		

		$dbCmd->Query("DELETE FROM productionracks WHERE DomainID=".Domain::oneDomain()." AND OrderID=" . intval($OrderID));

		header("Location: ./" . WebUtil::FilterURL(WebUtil::GetInput("returl", FILTER_SANITIZE_URL)));
		exit;
	}
	else if($action == "clearrackoff"){

		$dbCmd->Query("DELETE FROM productionracks WHERE DomainID=" . Domain::oneDomain());

		header("Location: ./" . WebUtil::FilterURL(WebUtil::GetInput("returl", FILTER_SANITIZE_URL)));
		exit;
	}
	else if($action == "saverack"){
	

		$racks = WebUtil::GetInput("racks", FILTER_SANITIZE_INT);
		$rows = WebUtil::GetInput("rows", FILTER_SANITIZE_INT);
		$cols = WebUtil::GetInput("cols", FILTER_SANITIZE_INT);


		// Find out if the settings for this product have already been saved
		$dbCmd->Query("SELECT COUNT(*) FROM productionsetup WHERE DomainID=" . Domain::oneDomain());
		$ProductRecordCount = $dbCmd->GetValue();

		$InsertArr = array();
		$InsertArr[ "DomainID"] = Domain::oneDomain();
		$InsertArr[ "RacksAmount"] = $racks; 
		$InsertArr[ "RowsPerRack"] = $rows; 
		$InsertArr[ "ColumnsPerRack"] = $cols; 

		// Inset a new record if 1 does not already exist, otherwise, update it
		if($ProductRecordCount == 0)
			$dbCmd->InsertQuery("productionsetup", $InsertArr);
		else
			$dbCmd->UpdateQuery("productionsetup", $InsertArr, "DomainID=" . Domain::oneDomain());

		header("Location: ./" . WebUtil::FilterURL(WebUtil::GetInput("returl", FILTER_SANITIZE_URL)));
		exit;
	}
	else
		throw new Exception("Undefined Action");

}



$t = new Templatex(".");

$t->set_file("origPage", "ad_production_setup-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$SavedMessage = "&nbsp;";



// If this parameter comes in the URL then it means that we are trying to save the information
if(!empty($savelabel)){

	// IF this user has a previous entry for the label setup, then get rid of it
	$dbCmd->Query("DELETE FROM labelsetup WHERE UserID=$UserID");

	$insertArr["UserID"] = $UserID;  
	$insertArr["ReplicationRows"] = WebUtil::GetInput("rows", FILTER_SANITIZE_INT);
	$insertArr["ReplicationColumns"] = WebUtil::GetInput("columns", FILTER_SANITIZE_INT);
	$insertArr["PageWidth"] = WebUtil::GetInput("pagewidth", FILTER_SANITIZE_FLOAT);
	$insertArr["PageHeight"] = WebUtil::GetInput("pageheight", FILTER_SANITIZE_FLOAT);
	$insertArr["SpacingHorizontal"] = WebUtil::GetInput("hspacing", FILTER_SANITIZE_FLOAT);
	$insertArr["SpacingVertical"] = WebUtil::GetInput("vspacing", FILTER_SANITIZE_FLOAT);
	$insertArr["MarginsLeft"] = WebUtil::GetInput("lmargin", FILTER_SANITIZE_FLOAT);
	$insertArr["MarginsTop"] = WebUtil::GetInput("bmargin", FILTER_SANITIZE_FLOAT);
	$insertArr["LabelWidth"] = WebUtil::GetInput("labelw", FILTER_SANITIZE_FLOAT);
	$insertArr["LabelHeight"] = WebUtil::GetInput("labelh", FILTER_SANITIZE_FLOAT);
	$insertArr["QuantitySpill"] = WebUtil::GetInput("quantityspill", FILTER_SANITIZE_INT);

	$dbCmd->InsertQuery("labelsetup",  $insertArr);

	
}





$dbCmd->Query("SELECT ReplicationRows, ReplicationColumns, PageWidth, PageHeight, 
		SpacingHorizontal, SpacingVertical, MarginsLeft, MarginsTop, 
		LabelWidth, LabelHeight, QuantitySpill FROM labelsetup 
		WHERE UserID=$UserID");
$row = $dbCmd->GetRow();

$ReplicationRows = $row["ReplicationRows"];
$ReplicationColumns = $row["ReplicationColumns"];
$PageWidth = $row["PageWidth"];
$PageHeight = $row["PageHeight"];
$SpacingHorizontal = $row["SpacingHorizontal"];
$SpacingVertical = $row["SpacingVertical"];
$MarginsLeft = $row["MarginsLeft"];
$MarginsTop = $row["MarginsTop"];
$LabelWidth = $row["LabelWidth"];
$LabelHeight = $row["LabelHeight"];
$QuantitySpill = $row["QuantitySpill"];

// In Case the values are not set... make some defaults as a starting point 
if($ReplicationRows == ""){
	$ReplicationRows = 20;
	$ReplicationColumns = 4;
	$PageWidth = "8.5";
	$PageHeight = "11.5";
	$SpacingHorizontal = "0";
	$SpacingVertical = "0";
	$MarginsLeft = "0.3";
	$MarginsTop = "0.71";
	$LabelWidth = "2.28";
	$LabelHeight = "0.55";
	$QuantitySpill = "1000";

	$SavedMessage = "You have not saved the label setup parameters yet.";
}
else if(!empty($savelabel)){
	$SavedMessage = "Your changes have been saved.";
}




$t->set_var(array(
	"R_ROWS"=>$ReplicationRows,
	"R_COLS"=>$ReplicationColumns,
	"P_W"=>$PageWidth,
	"P_H"=>$PageHeight,
	"S_H"=>$SpacingHorizontal,
	"S_V"=>$SpacingVertical,
	"M_L"=>$MarginsLeft,
	"M_T"=>$MarginsTop,
	"L_W"=>$LabelWidth,
	"L_H"=>$LabelHeight,
	"Q_S"=>$QuantitySpill

	));




$t->set_block("origPage","RackBL","RackBLout");


// In the future we may want the ability to glance at production racks from other domains at the same time?
$domainIDsArr = array(Domain::oneDomain());

foreach($domainIDsArr as $thisDomainID){
	
	// Now get data from the DB and parse the HTML row
	$dbCmd->Query("SELECT RacksAmount, RowsPerRack, ColumnsPerRack 
			FROM productionsetup WHERE DomainID=" . Domain::oneDomain());
	$row = $dbCmd->GetRow();
	
	$Racks = $row["RacksAmount"];
	$Rows = $row["RowsPerRack"];
	$Cols = $row["ColumnsPerRack"];
	
	
	$dbCmd->Query("SELECT COUNT(*) FROM productionracks WHERE DomainID=" . Domain::oneDomain());
	$RackTotal = $dbCmd->GetValue();
	
	
	// Build an HTML table for every Rack that this product is configured for 
	// We want to show the positions of each order on the rack... along with the count of boxes.

	$RackTablesHTML = "";
	if($RackControlObj->checkIfDomainIsConfiguredForRackControl()){	
		for($i=1; $i<=$Racks; $i++){
			$RackTablesHTML .= "<b>" . $RackControlObj->TranslateRack($i) . "</b><br>";
			
			$RackTablesHTML .= "<table cellpadding='0' cellspacing='0' bgcolor='#003399'><tr><td>";
			$RackTablesHTML .= "<table cellpadding='3' cellspacing='1'>";
			
			for($z=1; $z<=$Rows; $z++){
			
				$RackTablesHTML .= "<tr>";
			
				for($q=1; $q<=$Cols; $q++){
				
					$OrderInSlot = $RackControlObj->GetOrderIDbyPosition($i, $z, $q);
					
					if($OrderInSlot == 0){
						$BackColor = "#FFFFee";
						$OrderDesc = "";
					}
					else{
						$BackColor = "#FFeeee";
						$OrderDesc = "<a href='./ad_order.php?orderno=$OrderInSlot' class='blueredlink'>Order: " . $OrderInSlot . "</a><br><a href='javascript:ClearOrder($OrderInSlot)' class='blueredlink'>Clear</a><br>";
					
					}
				
					$RackTablesHTML .= "<td bgcolor='$BackColor' class='SmallBody' align='center' valign='bottom' width='90'>" . $OrderDesc . "<b>" . WebUtil::htmlOutput($RackControlObj->TranslateRow($z) . $RackControlObj->TranslateColumn($q)) . "</b></td>";
	
				}
				
				$RackTablesHTML .= "</tr>";
			}
			
			$RackTablesHTML .= "</table>";
			$RackTablesHTML .= "</td></tr></table><br>";
		}
	}
	
	
	
	$t->set_var(array(
		"RACKS"=>$Racks,
		"ROWS"=>$Rows,
		"COLS"=>$Cols,
		"RACK_TOTAL"=>$RackTotal,
		"RACK_POSITIONS"=>$RackTablesHTML,
		"DOMAIN_ID"=>$thisDomainID
	));
	
	$t->allowVariableToContainBrackets("RACK_POSITIONS");
	
	
	$t->parse("RackBLout","RackBL",true);
}


$t->set_var("SAVED_MESSAGE", $SavedMessage);

$t->pparse("OUT","origPage");


?>

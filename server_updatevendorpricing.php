<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

set_time_limit(10000);



// --------- Prevent this script from running accidently somehow.
throw new Exception("Must enable the script.");

// *****************************************************************************************

// This is used when you update the Vendor pricing for a Product (or our costs) and want
// to back-date that pricing all the way into the past.   That can give us a better "Customer Worth".

// *****************************************************************************************

/*


$dbCmd->Query("SELECT ID, ProductID FROM projectsordered ORDER BY ID ASC");

while($row = $dbCmd->GetRow()){
	
	$projectID = $row["ID"];
	$productID = $row["ProductID"];

	print "Updating Project ID: $projectID <br>";
	Constants::FlushBufferOutput();
	
	
	// Don't try to update a project for which we no longer support.
	if(!Product::checkIfProductIDisActive($dbCmd2, $productID))
		continue;
	
	
	$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd2, "ordered", $projectID);
	

	$projectInfoObj = $projectObj->getProjectInfoObject();

	$dbUpdateHash = array();
	
	
	for($i=1; $i<= 6; $i++){
		$dbUpdateHash[("VendorSubtotal" . $i)] = $projectInfoObj->getVendorSubTotal($i);
		$dbUpdateHash[("VendorID" . $i)] = $projectInfoObj->getVendorID($i);
	}
	
	
	$dbCmd2->UpdateQuery("projectsordered", $dbUpdateHash, "ID=$projectID" );
	
	

}

print "<hr>Done Updating Pricing.";

*/

?>
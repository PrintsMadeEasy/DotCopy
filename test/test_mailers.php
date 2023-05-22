<?

require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();



//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);


if(!$AuthObj->CheckForPermission("EDIT_CONTENT"))
	throw new Exception("Permission Denied");

$startTimeStamp = mktime(1, 1, 1, 1, 1, 2007);
$endTimeStamp = mktime(1, 1, 1, 5, 1, 2011);

$dbCmd->Query("SELECT Quantity, NotesAdmin FROM projectsordered INNER JOIN orders on projectsordered.OrderID = orders.ID 
					WHERE DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($startTimeStamp)."' AND '".DbCmd::FormatDBDateTime($endTimeStamp)."'
					AND ProductID=93");

$returnArr = array();

while($row = $dbCmd->GetRow()){
	if(preg_match("/^Broke/", $row["NotesAdmin"]))
		continue;
			
	if(!isset($returnArr[$row["NotesAdmin"]]))
		$returnArr[$row["NotesAdmin"]] = 0;
}

$totalsArr = array();

$monthNumber = 1;
while($endTimeStamp < time()){
	
	$startTimeStamp = mktime(1, 1, 1, $monthNumber, 1, 2007);
	$endTimeStamp = mktime(1, 1, 1, ($monthNumber + 1), 1, 2007);
		
	// Wipe out the totals for each month
	foreach($returnArr as $thisNote => $thisQuantity){
		$returnArr[$thisNote] = 0;
	}
	
	$monthNumber++;
	
	$dbCmd->Query("SELECT Quantity, NotesAdmin FROM projectsordered INNER JOIN orders on projectsordered.OrderID = orders.ID 
						WHERE DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($startTimeStamp)."' AND '".DbCmd::FormatDBDateTime($endTimeStamp)."'
						AND ProductID=93");
	
	while($row = $dbCmd->GetRow()){
			if(preg_match("/^Broke/", $row["NotesAdmin"]))
				continue;
			
			$returnArr[$row["NotesAdmin"]] += $row["Quantity"];
	}
	
	$totalsArr[date("M Y", $startTimeStamp)] = unserialize(serialize($returnArr));
	
	
}


$row = 0;
$col = 0;

// Print out the header row with dates.
foreach($returnArr as $thisNotes => $emptyValue){

	foreach($totalsArr as $thisDate => $thisArr){
		
		if($row > 0)
			continue;
		
		if($col == 0)
			print ",";

		print $thisDate . ",";
			
		$col++;
		
	}
	$row++;	
}
print "\n";

$row = 0;
$col = 0;

foreach($returnArr as $thisNotes => $emptyValue){

	foreach($totalsArr as $thisDate => $thisArr){
		
		foreach($thisArr as $notesLoop => $quantityLoop){
			
			if($notesLoop != $thisNotes)
				continue;
		
			if($col == 0){
				print $thisNotes . ",";
			}
			
			print $quantityLoop . ",";

			$col++;
		}
	}
	
	print "\n";
	
	$col = 0;
	$row++;
	


}

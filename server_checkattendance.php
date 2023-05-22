<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();

$AuthObj = Authenticate::getPassiveAuthObject();


$customerServiceIDs = $AuthObj->GetUserIDsInGroup(array("MEMBER", "CS"));

$memberAttendanceObj = new MemberAttendance($dbCmd);


foreach($customerServiceIDs as $CSuserID){

	print "Checking UserID: $CSuserID <br>";

	if($memberAttendanceObj->checkIfUserIsCurrentlyAWOL($CSuserID)){
	
		print "------ This user is AWOL<br><br>";
		
		$memberAttendanceObj->markUserAsAWOL($CSuserID);
	
	}
	
	print "<br>";

}

print "<hr>Done checking Member Attendance.";

?>
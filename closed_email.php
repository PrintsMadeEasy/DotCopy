<?

require_once("library/Boot_Session.php");






?>


<html>

<body bgcolor="#DDEEFF">


<div align="center"><br><br><br><br>
<font face="arial" size="3" color="#666666">Thank you<br><br>Your email address has been <br>put on our notification list.

<br><br>
<font face="arial"><a href="javascript:self.close();">Close Window</a></font>

</font>

</div>

</body>

</html>


<?

$dbCmd = new DbCmd();
$dbCmd->InsertQuery("closedemails",  array("Email"=>WebUtil::GetInput("email", FILTER_SANITIZE_EMAIL)));


?>
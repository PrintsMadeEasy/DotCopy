<?

require_once("library/Boot_Session.php");

$transferaddress = WebUtil::GetInput("transferaddress", FILTER_SANITIZE_STRING_ONE_LINE);


?>
<html>
<body>
<script>
document.location = "<? echo WebUtil::getFullyQualifiedDestinationURL($transferaddress); ?>";
</script>
</body>
</html>



<?

require_once("library/Boot_Session.php");


$t = new Templatex();
$t->set_file("origPage", "privacy-template.html");
$t->pparse("OUT","origPage");


?>
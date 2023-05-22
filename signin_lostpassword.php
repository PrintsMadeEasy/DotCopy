<?php

require_once("library/Boot_Session.php");


$t = new Templatex();

$t->set_file("origPage", "signin_lostpassword-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

VisitorPath::addRecord("Lost Password PopUp");

$t->pparse("OUT","origPage");


?>
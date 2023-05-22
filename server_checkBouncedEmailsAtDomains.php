<?php

require_once("library/Boot_Session.php");

ini_set("memory_limit", "512M");

// Make this script be able to run for a while 
set_time_limit(1000);

$emailObj = new CheckBouncedEmails();
$emailObj->startCheckBounce();
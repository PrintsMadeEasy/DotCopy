<?php

require_once("library/Boot_Session.php");


header("Location: " . WebUtil::getFullyQualifiedDestinationURL("templates.php?" . $_SERVER['QUERY_STRING']), false, 301);

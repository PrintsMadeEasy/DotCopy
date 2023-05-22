<?

require_once("library/Boot_Session.php");


// If people are running a version of Netscape older than 6.2 then we have to redirect them to a software update page
$ua = new UserAgent();


print "Browser: -" . $ua->browser . "-<br>";
print "Version: -" . $ua->version . "-<br>";
print "OS: -" . $ua->platform . "-<br>";
print "UserAgent: -" . $ua->get_user_agent() . "-<br>";


?>


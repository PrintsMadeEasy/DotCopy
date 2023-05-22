<?

require_once("library/Boot_Session.php");

$forwardedSSl = @getenv("X-FORWARDED-SSL");
$forwardedShcheme = @getenv("X-FORWARDED-SCHEME");
$forwardedProto = @getenv("X-FORWARDED-PROTO");
$remoteIpWithProxy = @getenv("HTTP_X_FORWARDED_FOR");

print "--: " . $forwardedSSl . "<br>";
print "--: " . $forwardedShcheme . "<br>";
print "--: " . $forwardedProto . "<br>";
print "--: " . $remoteIpWithProxy . "<br>";
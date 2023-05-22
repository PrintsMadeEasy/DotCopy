<?

// $binaryHash = base64_encode(pack("H*", sha1($body))) ; // Base64 of packed binary SHA-1 hash of body
	

$body = "Hallo Christian wie geht es dir ?";
	


print sha1($body) . "\n<br>";	

print pack("H*", sha1($body)) . "\n<br>";

print base64_encode(pack("H*", sha1($body))) . "\n<br>"


?>
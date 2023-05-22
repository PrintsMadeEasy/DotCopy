<?php

class ChatLauncher {
	
	private $dbCmd;

	function __construct(){
		$this->dbCmd = new DbCmd();
	}
	
	// Call this method if the customer closed the chat window and asked us not to bug him/her.
	// They can request a chat, but we won't force it upon them for the duration of the session.
	function dontBugMe(){
		WebUtil::SetCookie("DontBugChat", "yes", 360);
		WebUtil::SetSessionVar("DontBugChat", "yes");
	}


}


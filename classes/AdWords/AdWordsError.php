<?php

class AdWordsError {

	public $index;
	public $field;
	public $code;
	public $trigger;
	public $isExemptable;
	public $detail;

	
	function getHtmlErrorDesc(){
		$errrorDesc = "<u>Ad Words Error</u><br>";
		$errrorDesc .= "<b>Index: </b> " . $this->index . "<br>";
		$errrorDesc .= "<b>Field: </b> " . $this->field . "<br>";
		$errrorDesc .= "<b>Trigger: </b> " . $this->trigger . "<br>";
		$errrorDesc .= "<b>Code: </b> " . $this->code . "<br>";
		$errrorDesc .= "<b>Is Exemptable: </b> " . $this->isExemptable . "<br>";
		$errrorDesc .= "<b>Error Detail: </b> " . htmlspecialchars($this->detail) . "<br>";
		return $errrorDesc;
	}



}








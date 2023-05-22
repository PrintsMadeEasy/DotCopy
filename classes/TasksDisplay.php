<?
class TasksDisplay {
	
	private $_t;
	private $_templateFileName;
	private $_returnURL;
	private $_rowCSSClass1;
	private $_rowCSSClass2;
	private $_taskObject = array ( );
	
	// Constructor //
	
	function TasksDisplay($taskObject) {
		
		$this->_taskObject = $taskObject;
		
		$this->_rowCSSClass1 = "TaskRowEven";
		$this->_rowCSSClass2 = "TaskRowOdd";
	}
	
	// Setter methods //
	
	function setReturnURL($x) {
		$this->_returnURL = WebUtil::FilterURL($x);
	}
	
	function setTemplateFileName($x) {
		if (empty ( $x ))
			throw new Exception("The TemplateFileName is empty." );
		
		if (! empty ( $this->_templateFileName ))
			throw new Exception("The TemplateFileName can only be set once." );
		
		$this->_t = new Templatex ( ".", "keep" );
		$this->_t->set_file ("tasksHTML","./library/" . $x );
		$this->_templateFileName = $x;
	}
	
	function setRowCSS($CSS1,$CSS2) {
	
		$this->_rowCSSClass1 = $CSS1;
		$this->_rowCSSClass2 = $CSS2;
	}
	
	// Getter methods ///
	
	function getReturnURL() {
		return $this->_returnURL;
	}
	
	function getTemplateFileName() {
		return $this->_templateFileName;
	}

	
	// Implementation //

	function displayAsHTML(&$t, $templateTaskVar = "TASKS") {
	
		if (empty ( $this->_templateFileName ))
			throw new Exception("Error in generateTasksHTML: Set the templateFileName first !" );				

		$this->_t->set_block ( "tasksHTML", "taskBL", "taskBLout" );
		
		// Extract Inner HTML blocks out of the Block we just extracted.
		$this->_t->set_block ( "taskBL", "taskCompletedBL", "taskCompletedBLout" );
		$this->_t->set_block ( "taskBL", "taskUnCompletedBL", "taskUnCompletedBLout" );
		
		$this->_t->set_block ( "taskBL", "taskReminderInPastBL", "taskReminderInPastBLout" );
		$this->_t->set_block ( "taskBL", "taskReminderInFutureBL", "taskReminderInFutureBLout" );
		
		$this->_t->set_block ( "taskBL", "taskHighPriorityBL", "taskHighPriorityBLout" );
		$this->_t->set_block ( "taskBL", "taskNormalPriorityBL", "taskNormalPriorityBLout" );
		
		$this->_t->set_block ( "taskBL", "taskLinkBL", "taskLinkBLout" );
		
		$empty_tasks = true;
		$rowBackgroundColor = false;
		
		foreach ( $this->_taskObject as $singleTask ) {
			
			$empty_tasks = false;
			
			$this->_t->set_var ( "TASK_ID", $singleTask->getTaskID() );	
			$this->_t->set_var ( "TASK_DESC",$singleTask->getDescriptions() );
			$this->_t->set_var ( "TASK_CREATION_DATE", date( "m/d/y - g:i a", $singleTask->getCreationDate() ));
			$this->_t->set_var ( "RETURN_URL_ENCODED", urlencode($this->_returnURL) );	
			
			$this->_t->set_var ( "FORM_SECURITY_CODE", WebUtil::getFormSecurityCode() );	
			
			
			// empty() doesnt accept a direct method call -> use variable
			$linkURL = $singleTask->getLinkURL();
			
			if(empty($linkURL)) {
				$this->_t->set_var("taskLinkBLout", "");
			} else {
				$this->_t->set_var ( "TASK_LINK_URL", $linkURL);
				$this->_t->set_var ( "TASK_LINK_SUBJECT", $singleTask->getLinkSubject());
				$this->_t->parse("taskLinkBLout", "taskLinkBL", false );
			}
			
			
			$reminderDate = $singleTask->getReminderDate() ;
			
			if(!empty($reminderDate)) {
			
				$this->_t->set_var ( "TASK_REMINDER",  $singleTask->getTimeReminderDescription($reminderDate) );
			
				if ($singleTask->isTaskPastDue()) {
				
					$this->_t->parse("taskReminderInFutureBLout", "taskReminderInFutureBL", false );
					$this->_t->set_var("taskReminderInPastBLout", "");		
				} else {
			
					$this->_t->parse("taskReminderInPastBLout", "taskReminderInPastBL", false );
					$this->_t->set_var("taskReminderInFutureBLout", "");	
				}
			} 
			
			
			// remove both if we dont have a Reminder at all
			if(empty($reminderDate)) {
			
				$this->_t->set_var("taskReminderInPastBLout", "");
				$this->_t->set_var("taskReminderInFutureBLout", "");	
			}
			
	
			if ($singleTask->getPriority() == "H") {
			
				$this->_t->parse("taskHighPriorityBLout", "taskHighPriorityBL", false );
				$this->_t->set_var("taskNormalPriorityBLout", "");
			
			} else if ($singleTask->getPriority() == "N") {
			
				$this->_t->parse("taskNormalPriorityBLout", "taskNormalPriorityBL", false );
				$this->_t->set_var("taskHighPriorityBLout", "");
			}
			
	
			
	
			// Discard the inner blocks.
			// Parse the nested block.  Make sure to set the 3rd parameter to FALSE to keep the block from growing inside of the loop.
			// Also, clear the output of the block we aren't using.
			if ($singleTask->getStatusCompleted()){
				
				$this->_t->parse("taskCompletedBLout", "taskCompletedBL", false );
				$this->_t->set_var("taskUnCompletedBLout", "");
				
				$this->_t->set_var ("TASK_DATE_COMPLETED", date( "m/d/y - g:i a", $singleTask->getDateCompleted()));
			}
			else {
				$this->_t->parse ( "taskUnCompletedBLout", "taskUnCompletedBL", false );
				$this->_t->set_var("taskCompletedBLout", "");
			}
			

			if ($rowBackgroundColor) {
				$this->_t->set_var ( "TASK_ROW_CSS", $this->_rowCSSClass1 );
				$rowBackgroundColor = false;
			} else {
				$this->_t->set_var ( "TASK_ROW_CSS", $this->_rowCSSClass2 );
				$rowBackgroundColor = true;
			}
			
			$this->_t->parse ( "taskBLout", "taskBL", true );
		}
		
		$this->_t->set_var ( "RETURLTASKS", $this->_returnURL );
		
		$tasksHTML = "No open Tasks";
		if (! $empty_tasks)
			$tasksHTML = $this->_t->finish ( $this->_t->parse ( "OUT", "tasksHTML" ) );
		
		$t->set_var ( $templateTaskVar, $tasksHTML);
		$t->allowVariableToContainBrackets($templateTaskVar);
	}
	
	//  Simple test prototype XML
	function getTasksXML() {
		
		$XML = "<?xml \"version=1.0\">\n";
		foreach ( $this->_taskObject as $singleTask ) {
			
			$XML .= "<task>\n";
			$XML .= "<taskid>" . $singleTask->getTaskID() . "</taskid>\n";
			$XML .= "<createdate>" . $singleTask->getCreationDate() . "</createdate>\n";
			$XML .= "<reminder>" . $singleTask->getReminderDate() . "</reminder>\n";
			$XML .= "<priority>" . $singleTask->getPriority() . "</priority>\n";
			$XML .= "<attachment>" . $singleTask->getAttachment() . "</attachment>\n";
			$XML .= "<description>" . $singleTask->getDescriptions() . "</decription>\n";
			$XML .= "<datecompleted>" . $singleTask->getDateCompleted() . "</datecompleted>\n";
			$XML .= "<completed>" . $singleTask->getStatusCompleted() . "</completed>\n";
			$XML .= "<referenceid>" . $singleTask->getReferenceID() . "</referenceid>\n";
			$XML .= "</task>\n";
		}
		$XML .= "</xml>\n";
		
		return $XML;
	}
}

?>
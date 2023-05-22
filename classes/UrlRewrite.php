<?php

class UrlRewrite {

	private $domainId;
	
	public function __construct($domainId){
		
		if(!Domain::checkIfDomainIDexists($domainId))
			throw new Exception("Error in URL Rewrite Configuration. The Domain ID does not exist.");

		$this->dbCmd = new DbCmd();
		$this->domainId = $domainId;		
	}
	
	public function loadData() {
		
		$this->dbCmd->Query( "SELECT * FROM urlrewrites WHERE DomainID = $this->domainId ORDER BY ID ASC");
	
		$dataArr = array();
		while ($row = $this->dbCmd->GetRow()){
	
			$record["Request"]       = $row["Request"];
			$record["BackgroundURL"] = $row["BackgroundURL"];
			$record["ID"]	         = $row["ID"];
			
			$dataArr[] = $record;
		}
		return $dataArr;
	}
	
	public function updateRequest($request,$id) {
		
		$id = intval($id);
		$cleanRequest = $this->cleanRequest($request);
		$this->dbCmd->UpdateQuery("urlrewrites", array("Request"=>$cleanRequest), "DomainID=$this->domainId AND ID=$id");
	}
	
	public function updateBackgroundUrl($backgroundUrl,$id) {
		
		$id = intval($id);
		$cleanBackgroundUrl = $this->cleanUpBackgroungUrl($backgroundUrl);
		$this->dbCmd->UpdateQuery("urlrewrites", array("BackgroundURL"=>$cleanBackgroundUrl), "DomainID=$this->domainId AND ID=$id");
	}

	public function deleteRecord($id) {
		
		$id = intval($id);
		$this->dbCmd->Query("DELETE from urlrewrites where DomainID=$this->domainId AND ID=$id");
	}
	
	public function addNewRewrite($request, $backgroundUrl) {
		
		$cleanBackgroundUrl = $this->cleanUpBackgroungUrl($backgroundUrl);
		$cleanRequest = $this->cleanRequest($request);
		
		$insertArr["BackgroundURL"] = $cleanBackgroundUrl;
		$insertArr["Request"]       = $cleanRequest;
		$insertArr["DomainID"]      = $this->domainId;
		
		$this->dbCmd->InsertQuery("urlrewrites",  $insertArr);		
	}
	
	private function cleanRequest($request) {

		$request = stripslashes($request);

		if(substr($request, 0, 1)=="/")
			$request = substr($request, 1);	
		
		$request = preg_replace('/\s+/','',$request);
		 
		return $request;	
	}
	
	private function cleanUpBackgroungUrl($backgroundUrl) {
		
		if(empty($backgroundUrl))
			return "";
		
		$firstLetter =  substr($backgroundUrl, 0, 1);	
		if($firstLetter!="/")
			$backgroundUrl = "/". $backgroundUrl;
			
		$backgroundUrl = preg_replace('/\s+/','',$backgroundUrl);
		
		return $backgroundUrl;	
	}
		
}
	
?>
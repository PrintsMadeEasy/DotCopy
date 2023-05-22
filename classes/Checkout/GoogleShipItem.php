<?php

class GoogleShipItem {
	
	
  public $merchant_item_id;
  public $tracking_data_list;
  public $tracking_no;
  
  function GoogleShipItem($merchant_item_id, $tracking_data_list=array()) {
    $this->merchant_item_id = $merchant_item_id;
    $this->tracking_data_list = $tracking_data_list;
  }
  
  function AddTrackingData($carrier, $tracking_no) {
    if($carrier != "" && $tracking_no != "") {
      $this->tracking_data_list[] = array('carrier' => $carrier, 'tracking-number' => $tracking_no);
    }
  }
  
}

?>
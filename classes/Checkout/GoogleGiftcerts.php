<?php



 /**
  * This is a class used to return the results of gift certificates
  * supplied by the buyer on the place order page
  * 
  * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_gift-certificate-result} <gift-certificate-result>
  */
  
  class GoogleGiftcerts {
  	
    public $gift_valid;
    public $gift_code;
    public $gift_amount;
    public $gift_message;

    function googlegiftcerts($valid, $code, $amount, $message) {
      $this->gift_valid = $valid;
      $this->gift_code = $code;
      $this->gift_amount = $amount;
      $this->gift_message = $message;
    }
  }

?>
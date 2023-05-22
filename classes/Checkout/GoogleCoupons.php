<?php


 /**
  * This is a class used to return the results of coupons the buyer supplied in
  * the order page.
  * 
  * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_coupon-result <coupon-result>}
  */
  class GoogleCoupons {
  	
    public $coupon_valid;
    public $coupon_code;
    public $coupon_amount;
    public $coupon_message;

    function googlecoupons($valid, $code, $amount, $message) {
      $this->coupon_valid   = $valid;
      $this->coupon_code    = $code;
      $this->coupon_amount  = $amount;
      $this->coupon_message = $message;
    } 
  }
  
?>
<?php

/*
 * Copyright (C) 2006 Google Inc.
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *      http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

 /**
  * Used to create a Google Checkout result as a response to a 
  * merchant-calculations feedback structure, i.e shipping, tax, coupons and
  * gift certificates.
  * 
  * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_result <result>}
  */
  // refer to demo/responsehandlerdemo.php for usage of this code
  
  class GoogleResult {
    public $shipping_name;
    public $address_id;
    public $shippable;
    public $ship_price;

    public $tax_amount;

    public $coupon_arr = array();
    public $giftcert_arr = array();

    /**
     * @param integer $address_id the id of the anonymous address sent by 
     *                           Google Checkout.
     */
    function GoogleResult($address_id) {
      $this->address_id = $address_id;
    }

    function SetShippingDetails($name, $price, $shippable = "true") {
      $this->shipping_name = $name;
      $this->ship_price = $price;
      $this->shippable = $shippable;
    }

    function SetTaxDetails($amount) {
      $this->tax_amount = $amount;
    }

    function AddCoupons($coupon) {
      $this->coupon_arr[] = $coupon;
    }

    function AddGiftCertificates($gift) {
      $this->giftcert_arr[] = $gift;
    }
  }

  
?>
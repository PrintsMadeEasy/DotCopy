<?php

  /**
   * Class that represents flat rate shipping
   * 
   * info:
   * {@link http://code.google.com/apis/checkout/developer/index.html#tag_flat-rate-shipping}
   * {@link http://code.google.com/apis/checkout/developer/index.html#shipping_xsd}
   *  
   */
  class GoogleFlatRateShipping {

    public $price;
    public $name;
    public $type = "flat-rate-shipping";
    public $shipping_restrictions;

    /**
     * @param string $name a name for the shipping
     * @param double $price the price for this shipping
     */
    function GoogleFlatRateShipping($name, $price) {
      $this->name = $name;
      $this->price = $price;
    }

    /**
     * Adds a restriction to this shipping.
     * 
     * @param GoogleShippingFilters $restrictions the shipping restrictions
     */
    function AddShippingRestrictions($restrictions) {
      $this->shipping_restrictions = $restrictions;
    }
  }
  
?>
<?php

  /**
   * Represents a merchant calculated shipping
   * 
   * info:
   * {@link http://code.google.com/apis/checkout/developer/index.html#shipping_xsd}
   * {@link http://code.google.com/apis/checkout/developer/index.html#merchant_calculations_specifying}
   */
  class GoogleMerchantCalculatedShipping {

    public $price;
    public $name;
    public $type = "merchant-calculated-shipping";
    public $shipping_restrictions;
    public $address_filters;

    /**
     * @param string $name a name for the shipping
     * @param double $price the default price for this shipping, used if the 
     *                      calculation can't be made for some reason.
     */
    function GoogleMerchantCalculatedShipping($name, $price) {
      $this->price = $price;
      $this->name = $name;
    }

    /**
     * Adds a restriction to this shipping.
     * 
     * @param GoogleShippingFilters $restrictions the shipping restrictions
     */
    function AddShippingRestrictions($restrictions) {
      $this->shipping_restrictions = $restrictions;
    }

    /**
     * Adds an address filter to this shipping.
     * 
     * @param GoogleShippingFilters $filters the address filters
     */
    function AddAddressFilters($filters) {
      $this->address_filters = $filters;
    }
  }
  
?>
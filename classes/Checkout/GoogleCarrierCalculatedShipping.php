<?php


  /**
   * Represents carrier calculated shipping
   */
  class GoogleCarrierCalculatedShipping {

    public $name;
    public $type = "carrier-calculated-shipping";
    
    public $CarrierCalculatedShippingOptions = array();
//  public $ShippingPackages = array();
    public $ShippingPackage;

    /**
     * @param string $name the name of this shipping
     */
    function GoogleCarrierCalculatedShipping($name) {
      $this->name = $name;
    }

    /**
     * @param GoogleCarrierCalculatedShippingOption $option the option to be 
     * added to the carrier calculated shipping
     */
    function addCarrierCalculatedShippingOptions($option){
      $this->CarrierCalculatedShippingOptions[] = $option; 
    }

    /**
     * @param GoogleShippingPackage $package
     */
    function addShippingPackage($package){
//      $this->ShippingPackages[] = $package; 
      $this->ShippingPackage = $package; 
    }
  }

?>
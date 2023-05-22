<?php

  
  /**
   * Represents the location from where packages will be shipped from.
   * Used with {@link GoogleShippingPackage}.
   */
  class GoogleShipFrom {
    public $id;
    public $city;
    public $country_code;
    public $postal_code;
    public $region;
    
    /**
     * @param string $id an id for this address
     * @param string $city the city
     * @param string $country_code a 2-letter iso country code
     * @param string $postal_code the zip
     * @param string $region the region
     */
    function GoogleShipFrom($id, $city, $country_code, $postal_code, $region) {
      $this->id = $id;
      $this->city = $city;
      $this->country_code = $country_code;
      $this->postal_code = $postal_code;
      $this->region = $region;
    }
  }  
  

?>
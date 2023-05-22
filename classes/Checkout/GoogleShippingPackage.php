<?php


  /**
   * Represents an individual package that will be shipped to the buyer.
   */
  class GoogleShippingPackage {

    public $width;
    public $length;
    public $height;
    public $unit;
    public $ship_from;
    public $delivery_address_category;

    /**
     * @param GoogleShipFrom $ship_from where the package ships from
     * @param double $width the width of the package
     * @param double $length the length of the package
     * @param double $height the height of the package
     * @param string $unit the unit used to measure the width/length/height
     *                     of the package, valid values "IN", "CM"
     * @param string $delivery_address_category indicates whether the shipping
     * method should be applied to a residential or commercial address, valid 
     * values are "RESIDENTIAL", "COMMERCIAL"
     */
    function GoogleShippingPackage($ship_from, $width, $length, $height, $unit,
                    $delivery_address_category='RESIDENTIAL') {
      $this->width = (double)$width;
      $this->length = (double)$length;
      $this->height = (double)$height;
      switch(strtoupper($unit)){
        case 'CM':
          $this->unit = strtoupper($unit);
          break;
        case 'IN':
        default:
          $this->unit = 'IN';
      }
      
      $this->ship_from = $ship_from;
      switch(strtoupper($delivery_address_category)){
        case 'COMMERCIAL':
          $this->delivery_address_category = strtoupper($delivery_address_category);
          break;
        case 'RESIDENTIAL':
        default:
          $this->delivery_address_category = 'RESIDENTIAL';
      }
    }
  }
  

?>
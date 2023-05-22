<?php



  /**
   * Represents a shipping method for which Google Checkout will obtain 
   * shipping costs for the order.
   */
  class GoogleCarrierCalculatedShippingOption {

    public $price;
    public $shipping_company;
    public $shipping_type;
    public $carrier_pickup;
    public $additional_fixed_charge;
    public $additional_variable_charge_percent;

    /**
     * @param double $price the default shipping cost to be used if Google is 
     *                      unable to obtain the shipping_company's shipping rate for
     *                      the option
     * @param string $shipping_company the name of the shipping_company
     * @param string $shipping_type the shipping option, valid values are here:
     *   http://code.google.com/apis/checkout/developer/Google_Checkout_XML_API_Carrier_Calculated_Shipping.html#tag_shipping-type
     * @param double $additional_fixed_charge a handling charge that will be
     * added to the total cost of the order if this shipping option is selected.
     * defaults to 0
     * @param double $additional_variable_charge_percent A percentage by which
     * the shipping rate will be adjusted. The value may be positive or
     * negative. defaults to 0.
     * @param string $carrier_pickup Specifies how the package will be 
     * transfered from the merchand to the shipper. Valid values are 
     * "REGULAR_PICKUP", "SPECIAL_PICKUP", "DROP_OFF". Defaults to "DROP_OFF".
     *                
     */
    
    function GoogleCarrierCalculatedShippingOption($price, $shipping_company,
         $shipping_type, $additional_fixed_charge=0,
         $additional_variable_charge_percent=0, $carrier_pickup='DROP_OFF') {
         	
      $this->price = (double)$price;
      $this->shipping_company = $shipping_company;
      $this->shipping_type = trim($shipping_type);
      
      switch(strtoupper($carrier_pickup)){
        case 'DROP_OFF':
        case 'REGULAR_PICKUP':
        case 'SPECIAL_PICKUP':
          $this->carrier_pickup = $carrier_pickup;;
          break;
        default:
          $this->carrier_pickup = 'DROP_OFF';
      }
      if($additional_fixed_charge){
        $this->additional_fixed_charge = (double)$additional_fixed_charge;
      }
      if($additional_variable_charge_percent){
        $this->additional_variable_charge_percent = (double)$additional_variable_charge_percent;
      }
    }


  }

?>
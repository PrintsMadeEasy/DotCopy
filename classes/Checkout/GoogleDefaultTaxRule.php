<?php

  require_once 'GoogleTaxRule.php';	

  /**
   * Represents a default tax rule
   * 
   * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_default-tax-rule <default-tax-rule>}
   */
  class GoogleDefaultTaxRule extends GoogleTaxRule {

    public $shipping_taxed = false;

    // Tns of Rules can be defined. http://code.google.com/intl/de-CH/apis/checkout/developer/Google_Checkout_XML_API_Taxes.html
    
    function GoogleDefaultTaxRule($tax_rate, $shipping_taxed = "false") {
      $this->tax_rate = $tax_rate;
      $this->shipping_taxed= $shipping_taxed;

      // TODO: File State taxes OR get it from a central PME DB source
      
      
      $this->country_codes_arr = array();
      $this->postal_patterns_arr = array();
      $this->state_areas_arr = array();
      $this->zip_patterns_arr = array();
    }
    
  }
?>
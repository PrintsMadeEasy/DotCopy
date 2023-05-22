<?php

/*
 * Copyright (C) 2007 Google Inc.
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
 *
 */

  require 'GoogleTaxRule.php';

  /**
   * Represents an alternate tax table
   * 
   * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_alternate-tax-table <alternate-tax-table>}
   */

  class GoogleAlternateTaxTable {

    public $name;
    public $tax_rules_arr;
    public $standalone;

    function GoogleAlternateTaxTable($name = "", $standalone = "false") {
      if($name != "") {
        $this->name = $name;
        $this->tax_rules_arr = array();
        $this->standalone = $standalone;
      }
    }

    function AddAlternateTaxRules($rules) {
      $this->tax_rules_arr[] = $rules;
    }
    
  }

?>
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
/**
 * Classes used to represent shipping types
 * @version $Id: GooglePickUp.php,v 1.2 2010/01/03 07:47:09 brian_dot Exp $
 */
 
  /**
   * Used as a shipping option in which neither a carrier nor a ship-to 
   * address is specified
   * 
   * GC tag: {@link http://code.google.com/apis/checkout/developer/index.html#tag_pickup} <pickup>
   */
  class GooglePickUp {

    public $price;
    public $name;
    public $type = "pickup";

    /**
     * @param string $name the name of this shipping option
     * @param double $price the handling cost (if there is one)
     */
    function GooglePickUp($name, $price) {
      $this->price = $price;
      $this->name = $name;
    }
  }
  
?>
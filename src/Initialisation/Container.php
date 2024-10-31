<?php

namespace NetDebit\Plugin\WooCommerce\Initialisation;

class Container implements \ArrayAccess {
  
  protected $contents;
  protected $contentThatNeedsInitialisation;

  public function __construct() {
    $this->contents = array();
  }
  
  public function offsetSet($offset, $value) {
    $this->contents[$offset] = $value;
  }

  public function offsetExists($offset) {
    return isset($this->contents[$offset]);
  }

  public function offsetUnset($offset) {
    unset($this->contents[$offset]);
  }

  public function offsetGet($offset) {
    if(is_callable($this->contents[$offset])){
      return call_user_func($this->contents[$offset],$this);
    }
    return isset($this->contents[$offset])?$this->contents[$offset]:null;
  }
  
  public function init(){
    foreach($this->contents as $key => $content){
      if(is_callable($content) ){
        $content=$this[$key];
      }
      if(is_object($content) && $content instanceof NeedsInitialisation){
          $content->init(); 
      }
    }
  }
}
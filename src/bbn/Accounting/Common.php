<?php

namespace bbn\Accounting;


use bbn\Appui\Option;

trait Common
{

  public function getCountry(string $code): string
  {
    $o = Option::getInstance();
    return $o->fromCode($code, 'countries', 'core', 'appui');
  }


  public function getCurrency(string $code): string
  {
    $o = Option::getInstance();
    return $o->fromCode($code, 'currencies', 'core', 'appui');
  }

}
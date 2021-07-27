<?php

namespace bbn\Str;

use bbn\X;

class Payment
{

  private static $regexps =  [
    [
      'mask' => '0000 000000 00000',
      'regex' => '^3[47]\\d[0,13]',
      'type' => 'American Express',
      'icon' => 'payment-icon-americanexpress'
    ], [
      'mask' => '0000 0000 0000 0000',
      'regex' => '^(?:6011|65\\d[0,2]|64[4-9]\\d?)\\d[0,12]',
      'type' => 'Discover',
      'icon' => 'payment-icon-discover'
    ], [
      'mask' => '0000 000000 0000',
      'regex' => '^3(?:0([0-5]|9)|[689]\\d?)\\d[0,11]',
      'type' => 'Diners Club',
      'icon' => 'payment-icon-dinersclub'
    ], [
      'mask' => '0000 0000 0000 0000',
      'regex' => '^(5[1-5]\\d[0,2]|22[2-9]\\d[0,1]|2[3-7]\\d[0,2])\\d[0,12]',
      'type' => 'MasterCard',
      'icon' => 'payment-icon-mastercard'
    ], [
      'mask' => '0000 000000 00000',
      'regex' => '^(?:2131|1800)\\d[0,11]',
      'type' => 'JCB',
      'icon' => 'payment-icon-jcb'
    ], [
      'mask' => '0000 0000 0000 0000',
      'regex' => '^(?:35\\d[0,2])\\d[0,12]',
      'type' => 'JCB',
      'icon' => 'payment-icon-jcb'
    ], [
      'mask' => '0000 0000 0000 0000',
      'regex' => '^(?:5[0678]\\d[0,2]|6304|67\\d[0,2])\\d[0,12]',
      'type' => 'Maestro',
      'icon' => 'payment-icon-maestro'
    ], [
      'mask' => '0000 0000 0000 0000',
      'regex' => '^4\\d[0,15]',
      'type' => 'Visa',
      'icon' => 'payment-icon-visa'
    ], [
      'mask' => '0000 0000 0000 0000',
      'regex' => '^62\\d[0,14]',
      'type' => 'Unionpay',
      'icon' => 'payment-icon-unionpay'
    ]
  ];

  public static function check(string $number, string $type): bool
  {
    if ($row = X::getRow(self::$regexps, ['type' => $type])) {
      return !!preg_match($row['regex'], $number);
    }

    return false;

  }

  public static function detect(string $number): ?string
  {
    foreach (self::$regexps as $regex) {
      if (preg_match($regex['regex'], $number)) {
        return $regex['type'];
      }
    }

    return null;
  }

}
<?php
namespace bbn\Db;

interface Types
{
  public static function isBinaryType(string $type): bool;

  public static function isNumericType(string $type): bool;

  public static function isDateType(string $type): bool;

  public static function isTextType(string $type): bool;

  public static function getBinaryTypes(): array;

  public static function getNumericTypes(): array;

  public static function getDateTypes(): array;

  public static function getTextTypes(): array;

}

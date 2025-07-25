<?php
namespace bbn\Db;

interface Types
{
  public function isBinaryType(string $type): bool;

  public function isNumericType(string $type): bool;

  public function isDateType(string $type): bool;

  public function isTextType(string $type): bool;

  public function getBinaryTypes(): array;

  public function getNumericTypes(): array;

  public function getDateTypes(): array;

  public function getTextTypes(): array;

}

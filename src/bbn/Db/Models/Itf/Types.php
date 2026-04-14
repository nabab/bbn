<?php

namespace bbn\Db\Models\Itf;

interface Types
{
  public function getDateTypes(): array;

  public function getBinaryTypes(): array;

  public function getTextTypes(): array;

  public function isBinaryType(string $type): bool;

  public function isNumericType(string $type): bool;

  public function isDateType(string $type): bool;

  public function isTextType(string $type): bool;

}

<?php

namespace bbn\Db\Internal;

trait Types
{
  /****************************************************************
   *                                                              *
   *                                                              *
   *                          TYPES                               *
   *                                                              *
   *                                                              *
   ****************************************************************/




  public function getDateTypes(): array
  {
    return $this->language->getDateTypes();
  }


  /**
   * Returns the list of date types in the current language
   *
   * @return array
   */
  public function getBinaryTypes(): array
  {
    return $this->language->getBinaryTypes();
  }

  /**
   * Returns the list of text types in the current language
   *
   * @return array
   */
  public function getTextTypes(): array
  {
    return $this->language->getTextTypes();
  }

  public function isBinaryType(string $type): bool
  {
    return $this->language->isBinaryType($type);
  }

  public function isNumericType(string $type): bool
  {
    return $this->language->isNumericType($type);
  }

  public function isDateType(string $type): bool
  {
    return $this->language->isDateType($type);
  }

  public function isTextType(string $type): bool
  {
    return $this->language->isTextType($type);
  }
}

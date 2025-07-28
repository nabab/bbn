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
    $clsName = get_class($this->language);
    return call_user_func([$clsName, 'getDateTypes']);
  }


  /**
   * Returns the list of date types in the current language
   *
   * @return array
   */
  public function getBinaryTypes(): array
  {
    $clsName = get_class($this->language);
    return call_user_func([$clsName, 'getBinaryTypes']);
  }

  /**
   * Returns the list of text types in the current language
   *
   * @return array
   */
  public function getTextTypes(): array
  {
    $clsName = get_class($this->language);
    return call_user_func([$clsName, 'getTextTypes']);
  }

  public function isBinaryType(string $type): bool
  {
    $clsName = get_class($this->language);
    return call_user_func([$clsName, 'isBinaryType'], $type);
  }

  public function isNumericType(string $type): bool
  {
    $clsName = get_class($this->language);
    return call_user_func([$clsName, 'isNumericType'], $type);
  }

  public function isDateType(string $type): bool
  {
    $clsName = get_class($this->language);
    return call_user_func([$clsName, 'isDateType'], $type);
  }

  public function isTextType(string $type): bool
  {
    $clsName = get_class($this->language);
    return call_user_func([$clsName, 'isTextType'], $type);
  }
}

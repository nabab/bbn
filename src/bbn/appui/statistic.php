<?php

namespace bbn\appui;

use bbn;

class statistic extends bbn\models\cls\db
{
  use bbn\models\tts\optional;

  protected const ODATE = '2014-01-01';

  public function __construct(bbn\db $db, array $cfg)
  {
    parent::__construct($db);
    if ($this->check()) {
      self::optional_init('statistics');
    }
  }
}
<?php

namespace bbn\Db\Models\Itf;

interface Query
{
  public function getOne();

  public function getVar();

  public function getKeyVal(): ?array;

  public function getColArray(): array;

}

<?php

namespace bbn\Entities\Models;

interface EntityInterface
{
  public function getId(): ?string;

  public function fnom($id, $full = false): ?string;

  public function fadresse($id): ?string;

  function error($message, $die = true);
}
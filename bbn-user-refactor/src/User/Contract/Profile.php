<?php

declare(strict_types=1);

namespace bbn\User\Contract;

interface Profile
{
  public function getId(): ?string;
  public function getIdGroup(): ?string;
  public function getName(mixed $user = null): ?string;
  public function getEmail(mixed $user = null): ?string;
}

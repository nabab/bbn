<?php

namespace bbn\Models\Itf;

Interface Installer
{
  public function report(string $msg, bool $isTitle = false): void;

  public function has_appui(): bool;
}

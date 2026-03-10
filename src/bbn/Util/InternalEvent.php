<?php

namespace bbn\Util;

use LogicException;
use function array_last;

final class InternalEvent
{

  private $defaultPrevented = false;

  private mixed $responses = [];

  public function __construct(
    private string $name,
    private array $data = []
  )
  {
    
  }

  public function getName(): string
  {
    return $this->name;
  }

  public function getData(): array
  {
    return $this->data;
  }

  public function setResponse(mixed $response): void
  {
    $this->responses[] = $response;
  }

  public function getResponses(): array
  {
    return $this->responses;
  }

  public function getResponse(): mixed
  {
    return array_last($this->responses) ?: null;
  }

  public function preventDefault(): void
  {
    $this->defaultPrevented = true;
  }

  public function isDefaultPrevented(): bool
  {
    return $this->defaultPrevented;
  }
}


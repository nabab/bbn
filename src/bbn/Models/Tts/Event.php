<?php
namespace bbn\Models\Tts;

use bbn\Util\InternalEvent;

use function is_object;

trait Event
{
  /** @var array<string, array<int, callable>> */
  private array $listeners = [];

  /** @var bool $eventsDisabled */
  private bool $eventsDisabled = false;

  public function disableEvents(): void
  {
    $this->eventsDisabled = true;
  }

  public function enableEvents(): void
  {
    $this->eventsDisabled = false;
  }

  public function areEventsDisabled(): bool
  {
    return $this->eventsDisabled;
  }



  public function on(string $event, callable $listener): void
  {
    $this->listeners[$event][] = $listener;
  }

  public function off(string $event, ?callable $listener = null): void
  {
    if (!isset($this->listeners[$event])) {
      return;
    }

    if ($listener === null) {
      unset($this->listeners[$event]);
      return;
    }

    $this->listeners[$event] = array_values(array_filter(
      $this->listeners[$event],
      fn($l) => $l !== $listener
    ));

    if ($this->listeners[$event] === []) {
      unset($this->listeners[$event]);
    }
  }

  public function emit(string $event, mixed ...$args): ?InternalEvent
  {
    if ($this->eventsDisabled) {
      return null;
    }

    $last = array_last($args);
    if (is_object($last) && is_a($last, InternalEvent::class)) {
      $o = array_pop($args);
      $o->setData($args);
    }
    else {
      $o = new InternalEvent($event, $args);
    }
    foreach ($this->listeners[$event] ?? [] as $listener) {
      $listener($o);
    }

    return $o;
  }
}

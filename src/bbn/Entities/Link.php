<?php

namespace bbn\Entities;

class Link
{
  public ?\stdClass $link;

  public ?\stdClass $people;

  public ?\stdClass $address;

  public ?\stdClass $option;

  /**
   * @param array $link
   * @param array|null $people
   * @param array|null $address
   * @param array|null $option
   */
  public function __construct(array $link, ?array $people, ?array $address, ?array $option)
  {
    $this->link    = (object)$this->parseCfg($link);
    $this->people  = $people ? (object)$this->parseCfg($people) : null;
    $this->address = $address ? (object)$this->parseCfg($address) : null;
    $this->option  = $option ? (object)$this->parseCfg($option) : null;
  }

  /**
   * @param array $item
   *
   * @return array
   */
  private function parseCfg(array $item): array
  {
    if (!array_key_exists('cfg', $item)) {
      return $item;
    }

    $cfg = json_decode($item['cfg'], true);

    if (!is_array($cfg)) {
      $cfg = [];
    }

    unset($item['cfg']);

    foreach ($cfg as $k => $v) {
      if (!array_key_exists($k, $item)) {
        $item[$k] = $v;
      }
    }

    return $item;
  }
}
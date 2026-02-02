<?php

namespace bbn\Ai\Lab;

use Exception;
use bbn\Db;
use bbn\X;
use bbn\Appui\Passwords;
use bbn\Models\Tts\Optional;
use Orhanerday\OpenAi\OpenAi;

class Provider
{
  use Optional;

  protected $pass;
  protected $ai;
  public function __construct(protected Db $db)
  {
    self::optionalInit(['providers', 'ai', 'appui']);
    $this->pass = new Passwords($this->db);
  }

  public function add(string $name, string $url, string $pass): ?array
  {
    if (!($idEndpoint = self::getOptionId($url))) {
      if (
        ($idEndpoint = $this->getOptionsObject()->add([
          'id_parent' => $this->getOptionRoot(),
          'text' => $name,
          'code' => $url
        ]))
        && ($this->pass->store($pass, $idEndpoint))
      ) {
        return $this->getOption($idEndpoint);
      }
      else {
        throw new Exception("Models not found");
      }
    }
    else {
      return $this->getOption($idEndpoint);
    }

    return null;
  }

  public function get($id): ?array
  {
    if ($arr = $this->getOption($id)) {
      $arr['password'] = $this->pass->get($id);
    }

    return $arr ?: null;
  }

  public function delete($id): bool
  {
    return $this->getOptionsObject()->remove($id);
  }

  public function findByUrl(string $url): ?array
  {
    return X::getRow($this->getOptions() ?: [], ['url' => $url]);
  }

  public function findById(string $id): ?array
  {
    return $this->getOption($id);
  }

  public function setUp(string $id)
  {
    if ($arr = $this->get($id)) {
      $this->ai = new OpenAi($arr['password']);
      $this->ai->setBaseURL($arr['code']);
    }
  }
}


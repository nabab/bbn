<?php

namespace bbn\Models\Tts;

use Exception;
use bbn\Str;
use bbn\X;
use bbn\Appui\Note as NoteCls;
use function is_string;
use function is_array;

trait Note
{
  protected $noteIsInit = null;

  /** @var NoteCls */
  protected $noteObject;

  private ?string $noteDefaultType = null;

  protected function noteInit(): void
  {
    if (!$this->noteIsInit) {
      try {
        $cfg = $this->getClassCfg();
      }
      catch (Exception $e) {
        throw new Exception('The Note trait requires the class to have a valid class configuration');
      }
      if (isset($this->db) && ($cfg = $this->getClassCfg())) {
        $main = array_flip($cfg['tables'])[$cfg['table']];
        if (isset($cfg['arch'][$main]['id_note'])) {
          $this->noteObject = new NoteCls($this->db);
          $this->noteIsInit = true;
        }
        else {
          throw new Exception('The Note trait requires the class to have an id_note field in its main table '. $main);
        }
      }
      if (!$this->noteIsInit) {
        throw new Exception('The Note trait requires the class to have an id_note field in its main table');
      }
    }
  }

  protected function noteGet(string $id): ?array
  {
    $this->noteInit();
    return $this->noteObject->get($id);
  }

  protected function noteMedias(string $id, int|false $version = false, $type = false): ?array
  {
    $this->noteInit();
    return $this->noteObject->getMedias($id, $version, $type);
  }

  protected function noteInsert(
    string $title,
    string $content = '',
    string|null $id_type = null,
    bool   $private = false,
    bool   $locked = false,
    ?string $id_parent = null,
    ?string $id_alias = null,
    ?string $mime = '',
    ?string $lang = '',
    ?string $id_option = null,
    ?string $excerpt = '',
    bool   $pinned = false,
    bool   $important = false
  ): ?string {
    $this->noteInit();
    return $this->noteObject->insert(
      $title,
      $content,
      $id_type,
      $private,
      $locked,
      $id_parent,
      $id_alias,
      $mime,
      $lang,
      $id_option,
      $excerpt,
      $pinned,
      $important
    );
  }

  protected function noteLatestVersion(string $id): ?int
  {
    $this->noteInit();
    return $this->noteObject->latest($id);
  }

  protected function noteInsertVersion(string $id, string $title, string $content, string $excerpt)
  {
    $this->noteInit();
    return $this->noteObject->insertVersion($id, $title, $content, $excerpt);
  }

  protected function noteUpdate(
    string $id,
    $title,
    string $content = '',
    bool   $private = false,
    bool   $locked = false,
    string $excerpt = '',
    bool   $pinned = false,
    bool   $important = false
  ): bool {
    $this->noteInit();
    return $this->noteObject->update(
      $id,
      $title,
      $content,
      $private,
      $locked,
      $excerpt,
      $pinned,
      $important
    );
  }

  protected function noteDelete(string $id): bool
  {
    $this->noteInit();
    return $this->noteObject->remove($id);
  }

  protected function noteDeleteVersion(string $id, int $version): bool
  {
    $this->noteInit();
    return $this->noteObject->removeVersion($id, $version);
  }

  protected function noteGetDefaultType(): ?string
  {
    $this->noteInit();
    if ($this->noteDefaultType === null) {
      $this->noteDefaultType = false;
      $cfg = $this->getClassCfg();
      if (!empty($cfg['type_note'])) {
        if (Str::isUid($cfg['type_note'])) {
          $this->noteDefaultType = $cfg['type_note'];
        }
  
        if (is_array($cfg['type_note'])) {
          $options = $this->noteObject->getOptionsObject();
          $this->noteDefaultType = $options->fromCode(...$cfg['type_note']);
        }
        elseif (is_string($cfg['type_note'])) {
          $this->noteDefaultType = $this->noteObject->getOptionId($cfg['type_note'], 'types');
        }
      }
    }

    return $this->noteDefaultType ?: null;
  }

}

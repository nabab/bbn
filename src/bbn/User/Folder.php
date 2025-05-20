<?php
/**
 * @package user
 */
namespace bbn\User;

use Exception;
use bbn\X;
use bbn\Models\Tts\Optional;

class Folder
{
  use Optional;

  protected $id_pref;

  public function __construct(
    protected Preferences $prefs
  )
  {
    self::optionalInit(['folders', 'note', 'appui']);
    if ($pref = $this->prefs->getByOption(self::$option_root_id, false)) {
      $this->id_pref = $pref['id'];
    }
    else {
      $this->id_pref = $this->prefs->add(self::$option_root_id, []);
    }
  }


  public function create(string $name, ?string $parent = null, ?string $icon = null): ?string 
  {
    if (empty($name)) {
      throw new Exception(X::_("The name can't be empty"));
    }

    if ($this->exists($name, $parent)) {
      throw new Exception(X::_("The name already exists"));
    }

    $data = [
      'text' => $name,
      'id_parent' => $parent,
      'cfg' => [
        'creation' => date('Y-m-d H:i:s'),
      ]
    ];

    if ($parent) {
      $data['parent'] = $parent;
    }

    if ($icon) {
      $data['icon'] = $icon;
    }

    return $this->prefs->addBit($this->id_pref, $data);
  }

  public function exists(string $name, ?string $parent = null)
  {
    if (empty($name)) {
      return false;
    }

    $all = $this->prefs->getBits($this->id_pref, $parent, false);
    return $all ? !!X::getRow($all, ['text' => $name]) : false;
  }

  public function move(string $id, ?string $parent = null): bool
  {
    if ($this->prefs->moveBit($id, $parent)) {
      return true;
    }

    throw new Exception(X::_("Impossible to move the folder"));
  }

  public function delete(string $id): bool
  {
    if ($this->prefs->deleteBit($id)) {
      return true;
    }

    throw new Exception(X::_("Impossible to move the folder"));
  }

  public function list(?string $parent = null): array
  {
    return array_map(
      fn ($a): array => [
        'id' => $a['id'],
        'text' => $a['text'],
        'numChildren' => $a['numChildren'],
        'creation' => $a['cfg']['creation']
      ],
      $this->prefs->getBits($this->id_pref, $parent, true, true)
    );
  }
}

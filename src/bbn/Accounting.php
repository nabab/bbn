<?php
namespace bbn;

use bbn\X;
use bbn\Str;
use bbn\Models\Cls\Basic;
use bbn\Models\Tts\Cache;
use bbn\Models\Tts\Optional;
use bbn\Appui\Option;
class Accounting extends Basic
{

  use Cache;
  use Optional;

  /** @var int The ID of the option's for the root path / */
  private static $id_public_root;

  /** @var string path where the permissions and real path are */
  protected static $public_root = 'permissions|access';

  /** @var bbn\Appui\Option The options object */
  protected $options;

  /** @var bbn\User\Preferences The preferences object */
  protected $pref;

  /** @var bbn\User\Permissions The permissions object */
  protected $perm;
    


  public function __construct()
  {
    $this->options = Option::getInstance();
    $this->cacheInit();
    self::optionalInit();
  }




}

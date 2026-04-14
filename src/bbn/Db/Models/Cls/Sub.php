<?php

namespace bbn\Db\Models\Cls;

use Exception;
use bbn\Db;
use bbn\X;
use bbn\Db\Languages\Sql;

class Sub
{
  public function __construct(
    protected Db $db,
    protected Sql $language
  )
  {
  }

  protected function ensureLanguageMethodExists(string $method)
  {
    if (!method_exists($this->language, $method)) {
      throw new Exception(X::_('Method %s not found on the language %s class!', $method, $this->db->getEngine()));
    }
  }

}

<?php

declare(strict_types=1);

namespace bbn\User;

use bbn\Appui\Database;
use bbn\Appui\Option;
use bbn\Db;
use bbn\Db\Languages\Sqlite;
use bbn\Str;
use bbn\X;
use Exception;

/** Per-user SQLite database factory. */
final class Locales extends Component
{
  public function database(?string $idUser = null, bool $create = true): ?Db
  {
    $options = Option::getInstance();
    if (!$options) {
      throw new Exception(X::_('Impossible to get the options class instance'));
    }

    $host = $options->fromCode(
      'BBN_USER_PATH', 'connections', 'sqlite', 'engines', 'database', 'appui'
    );
    if (!$host) {
      throw new Exception(X::_('Impossible to find the SQLite host for user database'));
    }

    $current = $this->state->id;
    if (!$current) {
      return null;
    }

    $target = $idUser && Str::isUid($idUser) ? $idUser : $current;
    $name = 'locale_' . $target . '.sqlite';
    if ($target !== $current) {
      $host = str_replace($current, $target, Sqlite::getHostPath($host));
    }

    if (!Sqlite::hasHostDatabase($host, $name) && $create) {
      Sqlite::createDatabaseOnHost($name, $host);
    }

    if (!Sqlite::hasHostDatabase($host, $name)) {
      return null;
    }

    return (new Database($this->db))->connection($host, 'sqlite', $name);
  }
}

<?php

namespace bbn\Models\Tts;

use bbn\X;

use function count;

trait DbStructure
{
  /**
   * @var array Cached relations for the current table.
   */
  private $dbTraitRelations = [];

  /**
   * @var array Cached structure for the current table.
   */
  private $dbTraitStructure = [];

  /**
   * Gets the structure of the specified table.
   *
   * @param string|null $table The table name (optional).
   *
   * @return array The structure of the table.
   */
  protected function dbTraitGetStructure(string|null $table = null): array
  {
    if (!$table) {
      $cfg = $this->getClassCfg();
      $table = $cfg['table'];
    }

    if (!isset($this->dbTraitStructure[$table])) {
      $this->dbTraitStructure[$table] = $this->db->modelize($table);
    }

    return $this->dbTraitStructure[$table];
  }

  /**
   * Retrieves the relations for a given table.
   *
   * @param string|null $table The table name (optional).
   *
   * @return array An array of relations.
   */
  protected function dbTraitGetTableRelations(string|null $table = null): array
  {
    $cfg = $this->getClassCfg();
    if (!$table) {
      $table = $cfg['table'];
    }
    $idx = array_flip($cfg['tables'])[$table];
    if ($idx && !isset($this->dbTraitRelations[$table])) {
      $arc = &$cfg['arch'][$idx];
      $this->dbTraitRelations[$table] = [];
      if (!empty($arc['id'])) {
        $refs = $this->db->findReferences($this->db->cfn($arc['id'], $table));
        foreach ($refs as $ref) {
          [$db, $tab, $col] = X::split($ref, '.');
          $model = $this->db->modelize($tab);
          $this->dbTraitRelations[$table][] = [
            'db' => $db,
            'table' => $tab,
            'primary' => isset($model['keys']['PRIMARY']) && (count($model['keys']['PRIMARY']['columns']) === 1) ? $model['keys']['PRIMARY']['columns'][0] : null,
            'col' => $col,
            'model' => $model
          ];
        }
      }
    }

    return $this->dbTraitRelations[$table];
  }


}
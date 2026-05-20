<?php

namespace bbn\Models\Tts;

use Exception;
use bbn\Str;
use bbn\X;

use function is_string;
use function array_key_exists;

trait DbWrite
{
  /**
   * Inserts a new row into the current table.
   *
   * The input data is first normalized through `dbTraitPrepare()`.
   * If a `cfg` field exists in the table architecture and is present in `$data`,
   * it is JSON-encoded before insertion.
   *
   * Emits:
   * - `beforeinsert`
   * - `afterinsert`
   *
   * @param array<string, mixed> $data   Row data to insert.
   * @param bool                 $ignore Whether to use `insertIgnore()` instead of `insert()`.
   * @return string|null Inserted row ID, or null if insertion failed.
   */
  protected function dbTraitInsert(array $data, bool $ignore = false): ?string
  {
    if ($data = $this->dbTraitPrepare($data)) {
      $ccfg = $this->getClassCfg();

      // Encode "cfg" column as JSON if configured.
      if (!empty($ccfg["arch"][$this->class_table_index]["cfg"])) {
        $col = $ccfg["arch"][$this->class_table_index]["cfg"];
        if (isset($data[$col])) {
          $data[$col] = json_encode($data[$col]);
        }
      }

      $o = $this->emit("beforeinsert", $data);

      if ($o &&
        !$o->isDefaultPrevented() &&
        $this->db->{$ignore ? "insertIgnore" : "insert"}($this->class_table, $data)
      ) {
        $id = $this->db->lastId();
        $this->emit("afterinsert", $id);
        return $id;
      }
    }

    return null;
  }

  /**
   * Deletes row(s) from the current table.
   *
   * Accepts either:
   * - a row ID as string
   * - a filter array
   *
   * When `$cascade` is true, related rows returned by `dbTraitGetTableRelations()`
   * are deleted after the main delete operation.
   *
   * Emits:
   * - `beforedelete`
   * - `afterdelete`
   *
   * @param string|array<string, mixed> $filter  Row ID or filter configuration.
   * @param bool                        $cascade Whether to cascade delete declared related rows.
   * @return int Number of deleted rows.
   */
  protected function dbTraitDelete(string|array $filter, bool $cascade = false): int
  {
    if ($this->dbTraitExists($filter)) {
      $cfg = $this->getClassCfg();
      $f = $cfg["arch"][$this->class_table_index];

      if (!is_array($filter) && !empty($f["id"])) {
        $filter = [$f["id"] => $filter];
      }

      $o = $this->emit("beforedelete", [$filter, $cascade]);

      if ($o && $o->isDefaultPrevented()) {
        return $o->getResponse() ?? 0;
      }
      if ($res = $this->db->delete(
        $this->class_table,
        $this->dbTraitGetFilterCfg($filter)
      )) {
        if ($cascade) {
          foreach ($this->dbTraitGetTableRelations() as $rel) {
            $this->db->delete($rel["table"], [
              $rel["col"] => is_array($filter) ? $filter[$f["id"]] : $filter,
            ]);
          }
        }

        $this->emit("afterdelete", [$filter, $cascade, $o]);
        return $o ? $o->getResponse() : $res;
      }
    }

    return 0;
  }

  /**
   * Updates row(s) in the current table.
   *
   * Accepts either:
   * - a row ID as string
   * - a filter array
   *
   * The input data is normalized through `dbTraitPrepare()`.
   *
   * If the table architecture defines a `cfg` field and corresponding data is
   * provided, a partial JSON update expression is generated with `JSON_SET()`.
   *
   * Emits:
   * - `beforeupdate`
   * - `afterupdate`
   *
   * @param string|array<string, mixed> $filter Row ID or filter configuration.
   * @param array<string, mixed>        $data   Data to update.
   * @return int Number of updated rows.
   * @throws Exception If the target row cannot be found.
   */
  protected function dbTraitUpdate(string|array $filter, array $data): int
  {
    $ccfg = $this->getClassCfg();
    $f = $ccfg["arch"][$this->class_table_index];

    if (!is_array($filter)) {
      $filter = [$f["id"] => $filter];
    }

    if (!$this->dbTraitExists($filter)) {
      throw new Exception(X::_("Impossible to find the given row"));
    }

    if ($data = $this->dbTraitPrepare($data)) {
      // JSON cfg partial update support.
      if (!empty($f["cfg"])) {
        $col = $f["cfg"];
        if (!empty($data[$col])) {
          if (is_string($data[$col]) && Str::isJson($data[$col])) {
            $data[$col] = json_decode($data[$col], true);
          }

          $jsonUpdate = "JSON_SET(IFNULL(" . $this->db->csn($col, true) . ' ,"{}")';
          foreach ($data[$col] as $k => $v) {
            $jsonUpdate .= ', "$.' . $k . '", ' . (
              is_iterable($v)
                ? "JSON_EXTRACT('" . Str::escapeSquotes(json_encode($v)) . "', '$')"
                : '"' . Str::escapeDquotes($v) . '"'
            );
          }

          $jsonUpdate .= ")";
          $data[$col] = [null, $jsonUpdate];
        }
      }

      $f = $this->dbTraitGetFilterCfg($filter);
      $o = $this->emit("beforeupdate", $f, $data);
      if ($o && $o->isDefaultPrevented()) {
        $res = $o->getResponse();
        return \is_int($res) ? $res : 0;
      }

      $res = $this->db->update($this->class_table, $data, $f);
      $this->emit("afterupdate", $f, $data, $res, $o);
      $res2 = $o ? $o->getResponse() : null;
      return \is_int($res2) ? $res2 : $res;
    }

    return 0;
  }

  /**
   * Inserts or updates a row depending on the presence of a matching unique key.
   *
   * The method inspects the table's unique keys. If all columns of one unique
   * key are present and non-null in `$data`, it attempts to locate an existing
   * row and updates it; otherwise it inserts a new row.
   *
   * @param array<string, mixed> $data Row data.
   * @return string|null Existing or newly inserted row ID, or null on failure.
   */
  protected function dbTraitInsertUpdate(array $data): ?string
  {
    $cfg = $this->getClassCfg();
    $keys = $this->db->getUniqueKeys($this->class_table);
    $update = false;

    if (!empty($keys)) {
      foreach ($keys as $columns) {
        $checked = array_filter(
          $columns,
          fn($col) => !array_key_exists($col, $data) || is_null($data[$col]),
        );

        if (empty($checked)) {
          $update = $this->db->selectOne(
            $this->class_table,
            $cfg["arch"][$this->class_table_index]["id"],
            array_intersect_key($data, array_flip($columns)),
          );
          break;
        }
      }
    }

    if ($update) {
      $this->dbTraitUpdate($update, $data);
      return $update;
    }

    return $this->dbTraitInsert($data);
  }

}


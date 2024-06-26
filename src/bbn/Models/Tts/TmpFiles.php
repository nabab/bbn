<?php
namespace bbn\Models\Tts;

use bbn\Str;
use bbn\X;

trait TmpFiles
{

  private $table_links_extrafields = [];

  /**
   * @param array $where
   * @return array|null
   */
  private function _get_file(array $where): ?array
  {
    $file = $this->db->rselect(
      [
      'table' => static::$table_files,
      'fields' => [
        $this->db->colFullName('id', static::$table_files),
        $this->db->colFullName('files', static::$table_files),
        $this->db->colFullName('type_doc', static::$table_files),
        $this->db->colFullName('labels', static::$table_files),
        $this->db->colFullName('date_added', static::$table_files),
        'code' => 'CAST(bbn_options.code AS CHAR)'
      ],
      'join' => [[
        'table' => static::$table_links,
        'on' => [
          'conditions' => [[
            'field' => $this->db->colFullName('id_file', static::$table_links),
            'exp' => $this->db->colFullName('id', static::$table_files)
          ]]
        ]
      ], [
        'table' => static::$table,
        'on' => [
          'conditions' => [[
            'field' => $this->db->colFullName('id', static::$table),
            'exp' => $this->db->colFullName(static::$table_links_field, static::$table_links)
          ]]
        ]
      ], [
        'table' => 'bbn_options',
        'on' => [
          'conditions' => [[
            'field' => 'bbn_options.id',
            'exp' => $this->db->colFullName('type_doc', static::$table_files)
          ]]
        ]
      ]],
      'where' => [
        'conditions' => $where
      ]
      ]
    );
    if (!empty($file['code'])) {
      $file['code'] = (string)$file['code'];
    }

    return $file;
  }


  /**
   * @param array|string $id_type
   * @param bool         $files
   * @param array        $conditions
   * @return null|array
   */
  private function _get_file_by_type($id_type, bool $files = true, array $conditions = []): ?array
  {
    if (\is_array($id_type)) {
      $tmp = [
        'logic' => 'OR',
        'conditions' => []
      ];
      foreach ($id_type as $t) {
        $tmp['conditions'][] = [
          'field' => $this->db->colFullName('type_doc', static::$table_files),
          'value' => !Str::isUid($t) ? $this->options()->fromCode($t, 'documents') : $t
        ];
      }

      $conditions[] = $tmp;
    }
    else {
      $conditions[] = [
        'field' => $this->db->colFullName('type_doc', static::$table_files),
        'value' => !Str::isUid($id_type) ? $this->options()->fromCode($id_type, 'documents') : $id_type
      ];
    }

    if ($files) {
      $conditions[] = [
        'field' => $this->db->colFullName('files', static::$table_files),
        'operator' => 'isnotnull'
      ];
    }

    return $this->_get_file($conditions);
  }


  /**
   * @param string $type
   * @param array  $files
   * @return null|string
   */
  private function insert_file(string $type, array $files = [], string $labels = ''): ?string
  {
    return $this->db->insert(
      static::$table_files, [
      'files' => empty($files) ? null : json_encode($files),
      'type_doc' => Str::isUid($type) ? $type : $this->options()->fromCode($type, 'documents'),
      'labels' => $labels,
      'date_added' => date('Y-m-d H:i:s')
      ]
    ) ? $this->db->lastId() : null;
  }


  /**
   * @param string $id
   * @param array  $data
   * @return bool
   */
  private function update_file(string $id, array $data): bool
  {
    return Str::isUid($id) && $this->db->update(static::$table_files, $data, ['id' => $id]);
  }


  /**
   * @param string $id
   * @return bool
   */
  private function delete_file(string $id): bool
  {
    if (Str::isUid($id) && !$this->has_links($id)) {
      // Can be linked to others
      return !!$this->db->deleteIgnore(static::$table_files, ['id' => $id]);
    }

    return false;
  }


  /**
   * @param string $id_link
   * @param string $id_file
   * @param bool   $mandatory
   * @return nul|int
   */
  private function insert_file_link(string $id_link, string $id_file, bool $mandatory = true): ?int
  {
    if (Str::isUid($id_link) && Str::isUid($id_file)) {
      return $this->db->insertIgnore(
        static::$table_links, [
        static::$table_links_field => $id_link,
        'id_file' => $id_file,
        'mandatory' => empty($mandatory) ? 0 : 1
        ]
      );
    }

    return null;
  }


  /**
   * @param string $id_change
   * @param string $id_file
   * @return bool
   */
  private function delete_file_link(string $id_link, string $id_file): bool
  {
    if (Str::isUid($id_link) && Str::isUid($id_file)) {
      return !!$this->db->delete(
        static::$table_links, [
        static::$table_links_field => $id_link,
        'id_file' => $id_file
        ]
      );
    }

    return false;
  }


  /**
   * @param string $id_link
   * @param string $type
   * @param bool   $mandatory
   * @return string|null
   */
  private function _file_exists_or_insert(string $id_link, string $type, bool $mandatory): ?string
  {
    if (Str::isUid($id_link)) {
      if ($exists = $this->get_file_by_type($type, false)) {
        $id_file = $exists['id'];
      }
      else {
        $id_file = $this->insert_file($type);
      }

      if (Str::isUid($id_file) && !$this->has_file_link($id_link, $id_file)) {
        $this->insert_file_link($id_link, $id_file, $mandatory);
      }

      return $id_file;
    }

    return null;
  }


  /**
   * Gets the files linked
   * @param string $id
   * @return array|null
   */
  private function get_files_link(string $id): ?array
  {
    if (Str::isUid($id)) {
      return $this->db->rselectAll([
        'table' => static::$table_links,
        'fields' => X::mergeArrays(
          [
            $this->db->colFullName('id_file', static::$table_links),
            $this->db->colFullName('mandatory', static::$table_links),
            'other_link' => 'IF(l.' . static::$table_links_field . ' IS NULL, false, true)'
          ],
          array_map(fn($f) => $this->db->colFullName($f, static::$table_links), $this->table_links_extrafields)
        ),
        'join' => [[
          'table' => static::$table_files,
          'on' => [
            'conditions' => [[
              'field' => $this->db->colFullName('id_file', static::$table_links),
              'exp' => $this->db->colFullName('id', static::$table_files),
            ]]
          ]
        ], [
          'table' => static::$table_links,
          'type' => 'left',
          'alias' => 'l',
          'on' => [
            'conditions' => [[
              'field' => $this->db->colFullName('id_file', static::$table_links),
              'exp' => 'l.id_file'
            ], [
              'field' => 'l.' . static::$table_links_field,
              'operator' => '!=',
              'value' => $id
            ]]
          ]
        ]],
        'where' => [
          'conditions' => [[
            'field' => $this->db->colFullName(static::$table_links_field, static::$table_links),
            'value' => $id
          ]]
        ],
        'group_by' => [$this->db->colFullName('id_file', static::$table_links)]
      ]);
    }

    return null;
  }


  /**
   * @param string $id_link
   * @param string $id_file
   * @return null|bool
   */
  private function has_file_link(string $id_link, string $id_file): ?bool
  {
    if (Str::isUid($id_link) && Str::isUid($id_file)) {
      return !!$this->db->selectOne(
        static::$table_links, 'id_file', [
        static::$table_links_field => $id_link,
        'id_file' => $id_file
        ]
      );
    }
  }

  public function has_links(string $id_file){
    if (Str::isUid($id_file)) {
      return !!$this->db->selectAll([
        'table' => static::$table_links,
        'fields' => [],
        'where' => [
          'conditions' => [[
            'field' => 'id_file',
            'value' => $id_file
          ]]
        ]
      ]);
    }
    return false;
  }


  /**
   * Deletes the file and its link.
   * @param string $id
   * @return bool
   */
  public function delete_file_and_link(string $id): bool
  {
    if (Str::isUid($id)) {
      if ($links = $this->get_files_link($id)) {
        foreach ($links as $link){
          if (!$this->delete_file_link($id, $link['id_file'])) {
            return false;
          }
          $this->delete_file($link['id_file']);
        }
      }

      return true;
    }

    return false;
  }


}

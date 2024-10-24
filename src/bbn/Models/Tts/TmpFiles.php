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
    $cCfg = $this->getClassCfg();
    $oCfg = $this->options()->getClassCfg();
    $file = $this->db->rselect(
      [
      'table' => $cCfg['tables']['files'],
      'fields' => [
        $this->db->cfn($cCfg['arch']['files']['id'], $cCfg['tables']['files']),
        $this->db->cfn($cCfg['arch']['files']['files'], $cCfg['tables']['files']),
        $this->db->cfn($cCfg['arch']['files']['type_doc'], $cCfg['tables']['files']),
        $this->db->cfn($cCfg['arch']['files']['labels'], $cCfg['tables']['files']),
        $this->db->cfn($cCfg['arch']['files']['date_added'], $cCfg['tables']['files']),
        'code' => 'CAST('.$this->db->cfn($oCfg['arch']['options']['code'], $oCfg['table']).' AS CHAR)'
      ],
      'join' => [[
        'table' => $cCfg['tables']['links'],
        'on' => [
          'conditions' => [[
            'field' => $this->db->cfn($cCfg['arch']['links']['id_file'], $cCfg['tables']['links']),
            'exp' => $this->db->cfn($cCfg['arch']['files']['id'], $cCfg['tables']['files'])
          ]]
        ]
      ], [
        'table' => $this->class_table,
        'on' => [
          'conditions' => [[
            'field' => $this->db->cfn($this->fields['id'], $this->class_table),
            'exp' => $this->db->cfn($cCfg['arch']['links']['id_link'], $cCfg['tables']['links'])
          ]]
        ]
      ], [
        'table' => $oCfg['table'],
        'on' => [
          'conditions' => [[
            'field' => $this->db->cfn($oCfg['arch']['options']['id'], $oCfg['table']),
            'exp' => $this->db->cfn($cCfg['arch']['files']['type_doc'], $cCfg['tables']['files'])
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
    $cCfg = $this->getClassCfg();
    if (\is_array($id_type)) {
      $tmp = [
        'logic' => 'OR',
        'conditions' => []
      ];
      foreach ($id_type as $t) {
        $tmp['conditions'][] = [
          'field' => $this->db->cfn($cCfg['arch']['files']['type_doc'], $cCfg['tables']['files']),
          'value' => !Str::isUid($t) ? $this->options()->fromCode($t, 'documents') : $t
        ];
      }

      $conditions[] = $tmp;
    }
    else {
      $conditions[] = [
        'field' => $this->db->cfn($cCfg['arch']['files']['type_doc'], $cCfg['tables']['files']),
        'value' => !Str::isUid($id_type) ? $this->options()->fromCode($id_type, 'documents') : $id_type
      ];
    }

    if ($files) {
      $conditions[] = [
        'field' => $this->db->cfn($cCfg['arch']['files']['files'], $cCfg['tables']['files']),
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
    $cCfg = $this->getClassCfg();
    return $this->db->insert(
      $cCfg['tables']['files'], [
        $cCfg['arch']['files']['files'] => empty($files) ? null : json_encode($files),
        $cCfg['arch']['files']['type_doc'] => Str::isUid($type) ? $type : $this->options()->fromCode($type, 'documents'),
        $cCfg['arch']['files']['labels'] => $labels,
        $cCfg['arch']['files']['date_added'] => date('Y-m-d H:i:s')
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
    $cCfg = $this->getClassCfg();
    return Str::isUid($id) && $this->db->update($cCfg['tables']['files'], $data, [$cCfg['arch']['files']['id'] => $id]);
  }


  /**
   * @param string $id
   * @return bool
   */
  private function delete_file(string $id): bool
  {
    if (Str::isUid($id) && !$this->has_links($id)) {
      $cCfg = $this->getClassCfg();
      // Can be linked to others
      return !!$this->db->deleteIgnore($cCfg['tables']['files'], [$cCfg['arch']['files']['id'] => $id]);
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
      $cCfg = $this->getClassCfg();
      return $this->db->insertIgnore(
        $cCfg['tables']['links'], [
          $cCfg['arch']['links']['id_link'] => $id_link,
          $cCfg['arch']['links']['id_file'] => $id_file,
          $cCfg['arch']['links']['mandatory'] => empty($mandatory) ? 0 : 1
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
      $cCfg = $this->getClassCfg();
      return !!$this->db->delete(
        $cCfg['tables']['links'], [
          $cCfg['arch']['links']['id_link'] => $id_link,
          $cCfg['arch']['links']['id_file'] => $id_file
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
        $id_file = $exists[$this->getClassCfg()['arch']['links']['id_link']];
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
      $cCfg = $this->getClassCfg();
      return $this->db->rselectAll([
        'table' => $cCfg['tables']['links'],
        'fields' => X::mergeArrays(
          [
            $this->db->cfn($cCfg['arch']['links']['id_file'], $cCfg['tables']['links']),
            $this->db->cfn($cCfg['arch']['links']['mandatory'], $cCfg['tables']['links']),
            'other_link' => 'IF(l.'.$cCfg['arch']['links']['id_link'].' IS NULL, false, true)'
          ],
          array_map(fn($f) => $this->db->cfn($f, $cCfg['tables']['links']), $this->table_links_extrafields)
        ),
        'join' => [[
          'table' => $cCfg['tables']['files'],
          'on' => [
            'conditions' => [[
              'field' => $this->db->cfn($cCfg['arch']['links']['id_file'], $cCfg['tables']['links']),
              'exp' => $this->db->cfn($cCfg['arch']['files']['id'], $cCfg['tables']['files']),
            ]]
          ]
        ], [
          'table' => $cCfg['tables']['links'],
          'type' => 'left',
          'alias' => 'l',
          'on' => [
            'conditions' => [[
              'field' => $this->db->cfn($cCfg['arch']['links']['id_file'], $cCfg['tables']['links']),
              'exp' => 'l.'.$cCfg['arch']['links']['id_file']
            ], [
              'field' => 'l.'.$cCfg['arch']['links']['id_link'],
              'operator' => '!=',
              'value' => $id
            ]]
          ]
        ]],
        'where' => [
          'conditions' => [[
            'field' => $this->db->cfn($cCfg['arch']['links']['id_link'], $cCfg['tables']['links']),
            'value' => $id
          ]]
        ],
        'group_by' => [$this->db->cfn($cCfg['arch']['links']['id_file'], $cCfg['tables']['links'])]
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
      $cCfg = $this->getClassCfg();
      return !!$this->db->selectOne(
        $cCfg['tables']['links'],
        $cCfg['arch']['links']['id_file'],
        [
          $cCfg['arch']['links']['id_link'] => $id_link,
          $cCfg['arch']['links']['id_file'] => $id_file
        ]
      );
    }
  }

  public function has_links(string $id_file){
    if (Str::isUid($id_file)) {
      $cCfg = $this->getClassCfg();
      return !!$this->db->selectAll([
        'table' => $cCfg['tables']['links'],
        'fields' => [],
        'where' => [
          'conditions' => [[
            'field' => $cCfg['arch']['links']['id_file'],
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
        $cCfg = $this->getClassCfg();
        foreach ($links as $link){
          if (!$this->delete_file_link($id, $link[$cCfg['arch']['links']['id_file']])) {
            return false;
          }

          $this->delete_file($link[$cCfg['arch']['links']['id_file']]);
        }
      }

      return true;
    }

    return false;
  }


}

<?php
namespace bbn\Models\Tts;

use bbn\Str;
use bbn\X;

trait TmpFiles
{

  private $tableLinksExtrafields = [];


  /**
   * @param array $where
   * @return array|null
   */
  private function _getFile(array $where): ?array
  {
    $cCfg = $this->getClassCfg();
    $filesTable = $cCfg['tables']['files'];
    $filesFields = $cCfg['arch']['files'];
    $oCfg = $this->options()->getClassCfg();
    $optTable = $oCfg['table'];
    $optFields = $oCfg['arch']['options'];
    $file = $this->db->rselect(
      [
      'table' => $filesTable,
      'fields' => [
        $this->db->cfn($filesFields['id'], $filesTable),
        $this->db->cfn($filesFields['files'], $filesTable),
        $this->db->cfn($filesFields['type_doc'], $filesTable),
        $this->db->cfn($filesFields['labels'], $filesTable),
        $this->db->cfn($filesFields['date_added'], $filesTable),
        'code' => 'CAST('.$this->db->cfn($optFields['code'], $optTable).' AS CHAR)'
      ],
      'join' => [[
        'table' => $this->class_table,
        'on' => [
          'conditions' => [[
            'field' => $this->db->cfn($this->fields['id_file'], $this->class_table),
            'exp' => $this->db->cfn($filesFields['id'], $filesTable)
          ]]
        ]
      ], [
        'table' => $oCfg['table'],
        'on' => [
          'conditions' => [[
            'field' => $this->db->cfn($optFields['id'], $optTable),
            'exp' => $this->db->cfn($filesFields['type_doc'], $filesTable)
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
   * @param array|string $idType
   * @param bool         $files
   * @param array        $conditions
   * @return null|array
   */
  private function _getFileByType(string|array $idType, bool $files = true, array $conditions = []): ?array
  {
    $cCfg = $this->getClassCfg();
    $table = $cCfg['tables']['files'];
    $fields = $cCfg['arch']['files'];
    if (\is_array($idType)) {
      $tmp = [
        'logic' => 'OR',
        'conditions' => []
      ];
      foreach ($idType as $t) {
        $tmp['conditions'][] = [
          'field' => $this->db->cfn($fields['type_doc'], $table),
          'value' => !Str::isUid($t) ? $this->options()->fromCode($t, 'documents') : $t
        ];
      }

      $conditions[] = $tmp;
    }
    else {
      $conditions[] = [
        'field' => $this->db->cfn($fields['type_doc'], $table),
        'value' => !Str::isUid($idType) ? $this->options()->fromCode($idType, 'documents') : $idType
      ];
    }

    if ($files) {
      $conditions[] = [
        'field' => $this->db->cfn($fields['files'], $table),
        'operator' => 'isnotnull'
      ];
    }

    return $this->_getFile($conditions);
  }


  /**
   * @param string $type
   * @param array  $files
   * @return null|string
   */
  public function insertFile(string $type, array $files = [], string $labels = ''): ?string
  {
    $cCfg = $this->getClassCfg();
    $filesFields = $cCfg['arch']['files'];
    return $this->db->insert(
      $cCfg['tables']['files'], [
        $filesFields['files'] => empty($files) ? null : json_encode($files),
        $filesFields['type_doc'] => Str::isUid($type) ? $type : $this->options()->fromCode($type, 'documents'),
        $filesFields['labels'] => $labels,
        $filesFields['date_added'] => date('Y-m-d H:i:s')
      ]
    ) ? $this->db->lastId() : null;
  }


  /**
   * @param string $id
   * @param array  $data
   * @return bool
   */
  public function updateFile(string $id, array $data): bool
  {
    $cCfg = $this->getClassCfg();
    return Str::isUid($id)
      && $this->db->update($cCfg['tables']['files'], $data, [$cCfg['arch']['files']['id'] => $id]);
  }


  /**
   * @param string $id
   * @return bool
   */
  private function deleteFile(string $id): bool
  {
    if (Str::isUid($id) && !$this->hasLinks($id)) {
      $cCfg = $this->getClassCfg();
      // Can be linked to others
      return !!$this->db->deleteIgnore($cCfg['tables']['files'], [$cCfg['arch']['files']['id'] => $id]);
    }

    return false;
  }


  /**
   * @param string $idLink
   * @param string $idFile
   * @param bool   $mandatory
   * @return null|string
   */
  public function insertFileLink(string $idLink, string $idFile, bool $mandatory = true): ?string
  {
    if (Str::isUid($idLink) && Str::isUid($idFile)) {
      $d = [
        $this->fields['id_link'] => $idLink,
        $this->fields['id_file'] => $idFile,
        $this->fields['mandatory'] => empty($mandatory) ? 0 : 1
      ];
      if (isset($this->fields['id_entity'])) {
        $d[$this->fields['id_entity']] = $this->getId();
      }

      return $this->dbTraitInsert($d);
    }

    return null;
  }


  /**
   * @param string $id
   * @return bool
   */
  private function deleteFileLink(string $id): bool
  {
    if (Str::isUid($id)) {
      return !!$this->dbTraitDelete($id);
    }

    return false;
  }


  /**
   * @param string $idLink
   * @param string $type
   * @param bool   $mandatory
   * @return string|null
   */
  private function _fileExistsOrInsert(string $idLink, string $type, bool $mandatory): ?string
  {
    if (Str::isUid($idLink)) {
      if ($exists = $this->_getFileByType($type, false)) {
        $idFile = $exists[$this->fields['id_file']];
      }
      else {
        $idFile = $this->insertFile($type);
      }

      if (Str::isUid($idFile) && !$this->hasFileLink($idLink, $idFile)) {
        $this->insertFileLink($idLink, $idFile, $mandatory);
      }

      return $idFile;
    }

    return null;
  }


  /**
   * Gets the files linked
   * @param string $id
   * @return array|null
   */
  private function getFilesLink(string $id): ?array
  {
    if (Str::isUid($id)) {
      $cCfg = $this->getClassCfg();
      return $this->db->rselectAll([
        'table' => $this->class_table,
        'fields' => X::mergeArrays(
          [
            $this->db->cfn($this->fields['id'], $this->class_table),
            $this->db->cfn($this->fields['id_file'], $this->class_table),
            $this->db->cfn($this->fields['mandatory'], $this->class_table),
            'other_link' => 'IF(l.'.$this->fields['id_link'].' IS NULL, false, true)'
          ],
          array_map(fn($f) => $this->db->cfn($f, $this->class_table), $this->tableLinksExtrafields)
        ),
        'join' => [[
          'table' => $cCfg['tables']['files'],
          'on' => [
            'conditions' => [[
              'field' => $this->db->cfn($this->fields['id_file'], $this->class_table),
              'exp' => $this->db->cfn($cCfg['arch']['files']['id'], $cCfg['tables']['files']),
            ]]
          ]
        ], [
          'table' => $this->class_table,
          'type' => 'left',
          'alias' => 'l',
          'on' => [
            'conditions' => [[
              'field' => $this->db->cfn($this->fields['id_file'], $this->class_table),
              'exp' => 'l.'.$this->fields['id_file']
            ], [
              'field' => 'l.'.$this->fields['id_link'],
              'operator' => '!=',
              'value' => $id
            ]]
          ]
        ]],
        'where' => [
          'conditions' => [[
            'field' => $this->db->cfn($this->fields['id_link'], $this->class_table),
            'value' => $id
          ]]
        ],
        'group_by' => [$this->db->cfn($this->fields['id_file'], $this->class_table)]
      ]);
    }

    return null;
  }


  /**
   * @param string $idLink
   * @param string $idFile
   * @return null|bool
   */
  public function hasFileLink(string $idLink, string $idFile): ?bool
  {
    if (Str::isUid($idLink) && Str::isUid($idFile)) {
      return $this->dbTraitExists(
        [
          $this->fields['id_link'] => $idLink,
          $this->fields['id_file'] => $idFile
        ]
      );
    }

    return null;
  }

  public function hasLinks(string $idFile): bool
  {
    if (Str::isUid($idFile)) {
      return $this->dbTraitExists([
        $this->fields['id_file'] => $idFile
      ]);
    }

    return false;
  }


  /**
   * Deletes the file and its link.
   * @param string $id
   * @return bool
   */
  public function deleteFileAndLink(string $id): bool
  {
    if (Str::isUid($id)) {
      if ($links = $this->getFilesLink($id)) {
        foreach ($links as $link){
          if (!$this->deleteFileLink($link[$this->fields['id']])) {
            return false;
          }

          $this->deleteFile($link[$this->fields['id_file']]);
        }
      }

      return true;
    }

    return false;
  }


}

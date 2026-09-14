<?php
namespace bbn\Entities\Tables;

use bbn\Entities\Models\EntityTable;
use bbn\Models\Tts\TmpFiles;
use bbn\Str;
use bbn\X;

class ChangesFiles extends EntityTable
{
  use TmpFiles;

  protected static $default_class_cfg = [
    "table" => "bbn_entities_changes_files",
    "tables" => [
      "links" => "bbn_entities_changes_files",
      'files' => 'bbn_tmp_files',
    ],
    "arch" => [
      "links" => [
        "id" => "id",
        "id_link" => "id_link",
        "id_file" => "id_file",
        "id_entity" => "id_entity",
        "mandatory" => "mandatory"
      ],
      'files'  => [
        'id' => 'id',
        'files' => 'files',
        'type_doc' => 'type_doc',
        'labels' => 'labels',
        'date_added' => 'date_added'
      ]
    ],
    "junctions" => [
      [
        "table" => "bbn_tmp_files",
        "field" => "id_file",
        "property" => "file"
      ]
    ],
    "cache" => true,
  ];

  public function attachFile(string $id, string $code, string $file): bool
  {
    if (Str::isUid($id)) {
      $filesLinked = $this->getFilesLink($id);
      $cCfg = $this->getClassCfg();
      $filesTable = $cCfg['tables']['files'];
      $filesFields = $cCfg['arch']['files'];
      foreach ($filesLinked as $fl) {
        $f = $this->_getFile([
          $this->db->cfn($filesFields['id'], $filesTable) => $fl[$this->fields['id_file']]
        ]);
        if (!empty($f)
          && ((string)$f['code'] === $code)
        ) {
          $data = [
            $filesFields['files'] => empty($f['files']) ? [] : \json_decode($f[$filesFields['files']], true),
            $filesFields['date_added'] => $f[$filesFields['date_added']] ?: date('Y-m-d H:i:s')
          ];
          if (!\in_array($file, $data[$filesFields['files']], true)) {
            $data[$filesFields['files']][] = $file;
          }

          $data[$filesFields['files']] = json_encode($data[$filesFields['files']]);
          $this->dbTraitCacheDelete($fl[$this->fields['id']]);
          return $this->updateFile($f[$filesFields['id']], $data)
            && $this->entity->updateRecord($filesTable, $fl[$this->fields['id']]);
        }
      }
    }

    return false;
  }

  /**
   * @param array|string $id_type
   * @param bool         $files
   * @return null|array
   */
  public function getFileByType(string|array $idType, bool $files = true): ?array
  {
    return $this->_getFileByType(
      $idType,
      $files,
      [[
        'field' => $this->db->cfn($this->fields['id_entity'], $this->class_table),
        'value' => $this->getId()
      ]]
    );
  }

  public function resetFileByType(string $idType): bool
  {
    $filesFields = $this->getClassCfg()['arch']['files'];
    if (($file = $this->getFileByType($idType))
      && $this->updateFile(
        $file[$filesFields['id']],
        [
          $filesFields['files'] => null,
          $filesFields['date_added'] => null
        ]
      )
    ) {
      $changes = X::filter(
        $this->getRecords(),
        fn($r) => !empty($r[$this->fields['id_file']])
          && ($r[$this->fields['id_file']] === $file[$filesFields['id']])
      );

      foreach ($changes as $change) {
        $this->dbTraitCacheDelete($change[$this->fields['id']]);
      }

      return true;
    }

    return false;
  }

  /**
   * @param string $id
   * @return array
   */
  public function getIdsByFile(string $id): array
  {
    return Str::isUid($id)
      ? array_map(
        fn($r) => $r[$this->fields['id_link']] ?? null,
        X::filter(
          $this->getRecords($this->class_table),
          fn($r) => !empty($r[$this->fields['id_file']])
            && ($r[$this->fields['id_file']] === $id)
        )
      )
      : [];
  }

  /**
   * @param string $idLink
   * @param string $type
   * @param bool   $mandatory
   * @return string|null
   */
  public function fileInsert(string $idLink, string $type, bool $mandatory): ?string
  {
    if (Str::isUid($idLink)) {
      $idFile = $this->insertFile($type);
      if (Str::isUid($idFile)
        && !$this->hasFileLink($idLink, $idFile)
        && ($idFileLink = $this->insertFileLink($idLink, $idFile, $mandatory))
      ) {
        $this->entity->updateRecord($this->class_table, $idFileLink);
      }

      return $idFile;
    }

    return null;
  }

}

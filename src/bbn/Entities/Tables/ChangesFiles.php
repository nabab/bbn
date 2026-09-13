<?php
namespace bbn\Entities\Tables;

use bbn\Entities\Models\EntityTable;

class ChangesFiles extends EntityTable
{
  protected static $default_class_cfg = [
    "table" => "bbn_entities_changes_files",
    "tables" => [
      "links" => "bbn_entities_changes_files",
    ],
    "arch" => [
      "links" => [
        "id" => "id",
        "id_link" => "id_link",
        "id_file" => "id_file",
        "id_entity" => "id_entity",
        "mandatory" => "mandatory"
      ],
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
}

<?php

namespace bbn\Entities\Tables;

use bbn\X;
use bbn\Str;
use bbn\Db;
use bbn\User;
use bbn\Entities\Models\Entities;
use bbn\Entities\Entity;
use bbn\Entities\Junctions\DocumentRequestTypes;
use bbn\Entities\Models\EntityTable;
use bbn\Models\Tts\DbConfig;

use function is_string;
use function is_array;
use function count;

abstract class DocumentRequest extends EntityTable
{
  use DbConfig;

  protected ?DocumentRequestTypes $docTypesRequest = null;

  protected static $default_class_cfg = [
    'table' => 'bbn_documents_requests',
    'tables' => [
      'requests' => 'bbn_documents_requests',
    ],
    'arch' => [
      "requests" => [
        "id" => "id",
        "id_entity" => "id_entity",
        "sent" => "sent",
        "message" => "message",
        "last_call" => "last_call",
        "num_calls" => "num_calls"
      ]
    ],
    'junctions' => [
      [
        'table' => 'bbn_documents_requests_types',
        'class' => DocumentRequestTypes::class,
      ]
    ]
  ];


  public function getDocTypes(): DocumentRequestTypes
  {
    if (!isset($this->docTypesRequest)) {
      $this->docTypesRequest = new $this->class_cfg['junctions'][0]['class']($this->db, $this->entities, $this->entity);
    }

    return $this->docTypesRequest;
  }
  /**
   * @param int $limit
   * @param string|null $docType
   *
   * @return array
   */
  public function all(int $limit = 0, ?string $docType = null): array
  {
    if ($this->entity->check()) {
      $opt = $this->options();
      $optCfg = $opt->getClassCfg();
      $optTable = $optCfg['table'];
      $optFields = $opt->getFields();
      $fields = $this->class_cfg['arch']['requests'];
      $docRequestTypes = $this->getDocTypes();
      $tcfg = $docRequestTypes->getClassCfg();
      $docFields = $tcfg['arch'][$tcfg['table_index']];
      $fields['days_last_sent'] = "to_days(current_timestamp()) - to_days(ifnull(`$fields[last_call]`,`$fields[sent]`))";
      $docTable = $tcfg['table'];
      if (!empty($docType) && !Str::isUid($docType)) {
        $docType = $opt->fromCode($docType, 'documents');
      }

      $all = $this->getAll([], [$fields['days_last_send'] => 'DESC'], $limit);
      $res = [];
      foreach ($all as $a) {
        $where = [
          'conditions' => [[
            'field' => $this->db->cfn($docFields['id_request'], $docTable),
            'value' => $a[$fields['id']]
          ]]
        ];

        if (!empty($docType)) {
          $where['conditions'][] = [
            'field' => $this->db->cfn($docFields['doc_type'], $docTable),
            'value' => $docType
          ];
        }

        $tmp = $this->db->rselectAll([
          'table' => $docTable,
          'fields' => [
            'type_doc' => $this->db->cfn($docFields['doc_type'], $docTable),
            'code' => $this->db->cfn($optFields['code'], $optTable)
          ],
          'join' => [[
            'table' => $optTable,
            'on' => [
              'conditions' => [[
                'field' => $this->db->cfn($docFields['doc_type'], $docTable),
                'exp' => $this->db->cfn($optFields['id'], $optTable)
              ]],
            ]
          ]],
          'where' => $where
        ]);

        $info = [];
        if (!empty($tmp)) {
          foreach ($tmp as $t) {
            if (!empty($t['code']) && is_string($t['code'])) {
              $t['code'] = (string)$t['code'];
            }

            $info[] = $t;
          }
        }

        if (!empty($info)) {
          $res[$a[$fields['id']]] = $a;
          $res[$a[$fields['id']]]['docs'] = $info;
        }
      }

      return array_values($res);
    }

    return [];
  }


  /**
   * @param array $filter
   * @return int
   */
  public function count(array $filter = []): int
  {
    if ($this->entity->check()) {
      $docRequestTypes = $this->getDocTypes();
      $tcfg = $docRequestTypes->getClassCfg();
      return $this->db->selectOne([
        'table' => $this->class_table,
        'fields' => ['COUNT(DISTINCT '.$this->db->cfn($this->fields['id'], $this->class_table).')'],
        'where' => [
          $this->db->cfn($this->fields['id_entity'], $this->class_table) => $this->entity->getId()
        ],
        'join' => [[
          'table' => $tcfg['table'],
          'on' => [[
            'field' => $this->db->cfn($this->fields['id'], $this->class_table),
            'exp' => $this->db->cfn($tcfg['arch'][$tcfg['table_index']]['id_request'], $tcfg['table'])
          ]]
        ]]
      ]) ?: 0;
    }

    return 0;
  }


  /**
   * @param string $id
   * @return int|null
   */
  public function reminder(string $id): ?int
  {
    if ($this->entity->check()) {
      $numCalls = $this->db->selectOne($this->class_table, $this->fields['num_calls'], [$this->fields['id'] => $id]) ?: 0;
      return $this->db->update($this->class_table, [
        $this->fields['last_call'] => date('Y-m-d H:i:s'),
        $this->fields['num_calls'] => $numCalls + 1
      ], [
        $this->fields['id'] => $id
      ]);
    }

    return null;
  }


  /**
   * @param string $docType
   * @return bool
   */
  public function has(string $docType): bool
  {
    if ($this->entity->check()) {
      $docRequestTypes = $this->getDocTypes();
      $tcfg = $docRequestTypes->getClassCfg();
      $tfields = $tcfg['arch'][$tcfg['table_index']];
      return $this->db->count([
          'table' => $tcfg['table'],
          'join' => [[
            'table' => $this->class_table,
            'on' => [[
              'field' => $this->db->cfn($tfields['id_request'], $tcfg['table']),
              'exp' => $this->db->cfn($this->fields['id'], $this->class_table)
            ]]
          ]],
          'where' => [
            $this->db->cfn($this->fields['id_entity'], $this->class_table) => $this->entity->getId(),
            $this->db->cfn($tfields['doc_type'], $tcfg['table']) => $docType
          ]
        ]) > 0;
    }

    return false;
  }


  /**
   * Add a document request.
   *
   * @param $docType
   * @param string $message
   * @return array|null
   *
   * @todo Envoyer un mail et préparer le template
   */
  public function add($docType, string $message = ''): ?array
  {
    if ($this->entity->check()) {
      $docRequestTypes = $this->getDocTypes();
      $dFields = $docRequestTypes->getFields();
      // Un ou plusieurs types de documents
      if (!is_array($docType)) {
        $docType = [[
          $dFields['doc_type'] => $docType
        ]];
      }

      // Les types de document n'ayant pas déjà déjà été demandés
      $types = [];
      foreach ($docType as $t) {
        if (is_string($t)) {
          $t = [
            $dFields['doc_type'] => $t
          ];
        }

        if (!$this->has($t[$dFields['doc_type']])) {
          $types[] = $t;
        }
      }

      // Si ils ont tous été envoyés on ne fait rien
      if (count($types) > 0) {
        $sentDate = date('Y-m-d H:i:s');
        $data = [
          $this->fields['id_entity'] => $this->entity->getId(),
          $this->fields['message'] => nl2br($message, false),
          $this->fields['sent'] => $sentDate
        ];

        if ($this->db->insert($this->class_table, $data)) {
          $data[$this->fields['id']] = $this->db->lastId();
          $data[$dFields['doc_type']] = [];

          foreach ($types as $i => $t) {
            if ($docRequestTypes->insert([
              $dFields['id_request'] => $data[$this->fields['id']],
              $dFields['doc_type'] => $t[$dFields['doc_type']]
            ])) {
              $data[$dFields['doc_type']][$i] = $t;
            }
          }

          return $data;
        }
      }

      return [];
    }

    return null;
  }


  /**
   * Returns the object of the documents requeste corresponding to the document type
   *
   * @param string $docType
   * @param string|null $idCloture
   * @return array|null
   */
  public function get(string $docType, ?string $idCloture = null): ?array
  {
    $request = null;
    $docRequestTypes = $this->getDocTypes();
    $dFields = $docRequestTypes->getFields();
    if ($this->has($docType)
      && ($requests = $this->all())
    ) {
      foreach ($requests as $r) {
        if (X::search($r['docs'], [$dFields['doc_type'] => $docType]) !== null) {
          $request = $r;
        }
      }
    }

    return $request;
  }


  /**
   * Removes a document type from demande and deletes the demande if it is complete.
   *
   * @param string $docType
   * @param string|null $idCloture
   * @return false|string
   *
   * @todo Envoyer un mail
   */
  public function deleteByType(string $docType, ?string $idCloture = null)
  {
    $res = false;
    $docRequestTypes = $this->getDocTypes();
    $dFields = $docRequestTypes->getFields();
    $dTable = $this->class_cfg['tables']['documents'];
    $dFields = $this->class_cfg['arch']['documents'];
    if ($this->entity->check()
      && ($request = $this->get($docType, $idCloture))
      && ($id = $docRequestTypes->selectOne('id', [
        $dFields['id_request'] => $request[$this->fields['id']],
        $dFields['doc_type'] => $docType
      ]))
      && $docRequestTypes->delete($id)
    ) {
      if (!$docRequestTypes->count([
        $dFields['id_request'] => $request[$this->fields['id']],
      ])) {
        $this->delete($request[$this->fields['id']]);
      }

      $res = $docType;
    }

    return $res;
  }


}


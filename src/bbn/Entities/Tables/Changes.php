<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 21/11/2014
 * Time: 13:51
 */

namespace bbn\Entities\Tables;

use bbn\Db;
use bbn\X;
use bbn\Str;
use bbn\Entities\Models\EntityTable;
use bbn\Entities\Models\Entities;
use bbn\Entities\Entity;
use bbn\Models\Tts\TmpFiles;


class Changes extends EntityTable
{
  use TmpFiles;

  /** @todo is it used?? */
  public static $editable = [
  ];

  public static $states = [
    'unready' => null,
    'email' => 1,
    'untreated' => 2,
    'accepted' => 3,
    'refused' => 4,
    'meeting' => 5
  ];

  protected static $default_class_cfg = [
    'table' => 'bbn_entities_changes',
    'tables' => [
      'entities_changes' => 'bbn_entities_changes',
      'files' => 'bbn_tmp_files',
      'links' => 'bbn_entities_changes_files'
    ],
    'arch' => [
      'entities_changes' => [
        'id' => 'id',
        'id_entity' => 'id_entity',
        'moment' => 'moment',
        'state' => 'state',
        'notified' => 'notified',
        'id_member_auth' => 'id_member_auth',
        'cfg' => 'cfg'
      ],
      'files'  => [
        'id' => 'id',
        'files' => 'files',
        'type_doc' => 'type_doc',
        'labels' => 'labels',
        'date_added' => 'date_added'
      ],
      'links' => [
        'id_link' => 'id_link',
        'id_file' => 'id_file',
        'mandatory' => 'mandatory'
      ]
    ]
  ];

  protected static $eaFields = [];

  protected $tables = [];

  /** Compatibility with the old version */
  protected $tablesOld = [
  ];

  protected $skipEmailVerfication = false;


  public function __construct(Db &$db, Entities $entities, Entity $entity)
  {
    parent::__construct($db, $entities, $entity);
    $this->getTables();
  }


  public static function requiredFiles()
  {
    $res = [];
    $fields = static::getFieldsList();
    foreach ($fields as $fieldName => $field) {
      $cn =& $field['changes'];
      if (!empty($cn['docs'])) {
        if (!isset($res[$cn['table']])) {
          $res[$cn['table']] = [];
        }

        if (!empty($cn['fields'])) {
          foreach ($cn['fields'] as $fn => $fv) {
            $res[$cn['table']][$fn] = $cn['docs'];
          }
        }
        else {
          $res[$cn['table']][$fieldName] = $cn['docs'];
        }
      }
    }

    return $res;
  }


  public static function emailVerification()
  {
    $res = [];
    $fields = static::getFieldsList();
    foreach ($fields as $fieldName => $field) {
      $cn =& $field['changes'];
      if (!empty($cn['emailVerification'])) {
        if (!isset($res[$cn['table']])) {
          $res[$cn['table']] = [];
        }
        $res[$cn['table']][] = $fieldName;
      }
      else if (!empty($cn['fields'])) {
        foreach ($cn['fields'] as $fName => $f) {
          if (!empty($f['emailVerification'])) {
            if (!isset($res[$cn['table']])) {
              $res[$cn['table']] = [];
            }
            $res[$cn['table']][] = $fName;
          }
        }
      }
    }

    return $res;
  }


  /**
   * @param string $code
   * @return string
   */
  public static function cryptCode(string $code): string
  {
    return rtrim(strtr(base64_encode(\bbn\Util\Enc::crypt($code)), '+/', '-_'), '=');
  }


  /**
   * @param string $code
   * @return string
   */
  public static function decryptCode(string $code): string
  {
    return \bbn\Util\Enc::decrypt(base64_decode(strtr($code, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($code)) % 4)));
  }


  /**
   * @param string $id
   * @param string $code
   * @param string $file
   * @return null|bool
   */
  public function attachFile(string $id, string $code, string $file): bool
  {
    if (Str::isUid($id)) {
      $filesLinked = $this->getFilesLink($id);
      $cCfg = $this->getClassCfg();
      $filesFields = $cCfg['arch']['files'];
      foreach ($filesLinked as $fl) {
        $f = $this->_getFile([
          $this->db->cfn($filesFields['id'], $cCfg['tables']['files']) => $fl[$filesFields['id_file']]
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
          return $this->updateFile($f[$filesFields['id']], $data);
        }
      }
    }

    return false;
  }


  /**
   * @param string $id_type
   * @return bool
   */
  public function resetFileByType(string $id_type): bool
  {
    $filesFields = $this->getClassCfg()['arch']['files'];
    if (($file = $this->getFileByType($id_type))
      && $this->updateFile(
        $file[$filesFields['id']], [
          $filesFields['files'] => null,
          $filesFields['date_added'] => null
        ]
      )
    ) {
      foreach ($this->getIdsByFile($file[$filesFields['id']]) as $id){
        $this->_setState($id);
      }

      return true;
    }

    return false;
  }


  /**
   * @param string $email
   * @param string $code
   * @return bool
   */
  public function sendEmailVerification(string $email, string $code): bool
  {
    return Str::isEmail($email)
      && !!$this->db->insert('bbn_emails', [
        'email' => $email,
        'subject' => 'Confirming your email',
        'text' => _('Click the following link to confirm your email address:').' '.BBN_URL.(\str_ends_with(BBN_URL, '/') ? '' : '/').'confirm_email/'.static::cryptCode($code)
      ]);
  }


  /**
   * @param string $table
   * @param string $type
   * @param array  $todata
   * @param string $id
   * @param array  $subdata
   * @return null|int
   */
  public function create(
    string $table,
    string $type,
    array $todata,
    string $id = '',
    array $subdata = [],
    string $subid = ''
  ): ?array
  {
    $is_insert = $type === 'insert';
    $is_update = $type === 'update';
    $is_delete = $type === 'delete';
    if ($this->check()
      && (!empty($is_insert)
        || !empty($is_update)
        || !empty($is_delete))
      && ($moment = date('Y-m-d H:i:s'))
    ) {
      $tocfg = [
        'table' => $table,
        'type' => $type,
        'data' => []
      ];
      if (!empty($id)) {
        if (str_starts_with($id, '_bbn_')) {
          $tocfg['tmpId'] = $id;
          $id = '';
        }
        else {
          $tocfg['id'] = $id;
        }
      }

      if (!empty($subid)) {
        if (str_starts_with($subid, '_bbn_')) {
          $tocfg['tmpSubId'] = $subid;
          $subid = '';
        }
        else {
          $tocfg['subId'] = $subid;
        }
      }

      if (!empty($subdata)
        && !empty($subdata['table'])
        && !empty($subdata['data'])
      ) {
        foreach ($subdata['data'] as $i => $v){
          $subdata['data'][$i] = $this->checkEmailRequired($subdata['table'], $v);
        }

        $tocfg['subdata'] = $subdata;
      }

      if (X::isAssoc($todata)) {
        if ((!empty($is_update) || !empty($is_delete))
          && ($id_change = $this->checkExists($table, $id, $todata, $type))
        ) {
          if (!empty($is_update)) {
            if ($this->_update($id_change, $todata, $moment, $subdata)) {
              return [$id_change];
            }
          }
          elseif ($this->_setMoment($id_change, $moment)) {
            return [$id_change];
          }
        }
        else {
          $tocfg['data'][] = $this->checkEmailRequired($table, $todata);
          if ($id_change = $this->_insert($moment, $tocfg)) {
            return [$id_change];
          }
        }

      }
      elseif (!empty($is_insert) || !empty($is_delete)) {
        foreach ($todata as $i => $t){
          $todata[$i] = !empty($is_insert) ? $this->checkEmailRequired($table, $t) : $t;
        }

        $tocfg['data'] = $todata;
        if (!empty($is_delete)
          && ($id_change = $this->checkExists($table, $id, $todata, $type))
          && $this->_setMoment($id_change, $moment)
        ) {
          return [$id_change];
        }

        if ($id_change = $this->_insert($moment, $tocfg)) {
          return [$id_change];
        }

      }
      else {
        $ret = [];
        if (!empty($todata)) {
          foreach ($todata as $t){
            if (!empty($is_update)
              && ($id_change = $this->checkExists($table, $id, $t))
              && $this->_update($id_change, $t, $moment, $subdata)
            ) {
              $ret[] = $id_change;
            }
            else {
              $tocfg['data'] = [$this->checkEmailRequired($table, $t)];
              if ($id_change = $this->_insert($moment, $tocfg)) {
                $ret[] = $id_change;
              }
            }
          }
        }
        elseif (!empty($subdata)) {
          if (!empty($is_update)
            && ($id_change = $this->checkExists($table, $id, $todata))
            && $this->_update($id_change, $todata, $moment, $subdata)
          ) {
            $ret[] = $id_change;
          }
          else if ($id_change = $this->_insert($moment, $tocfg)) {
            $ret[] = $id_change;
          }
        }

        return $ret;
      }
    }

    return null;
  }


  /**
   * @param string $id
   * @return null|int
   */
  public function delete(string $id): ?int
  {
    if (Str::isUid($id) && $this->deleteFileAndLink($id)) {
      return $this->dbTraitDelete($id);
    }

    return null;
  }


  /**
   * @param string $id
   * @return null|int
   */
  public function accept(string $id): ?int
  {
    if (Str::isUid($id)
      && $this->check()
      && ($change = $this->get($id, [
        $this->fields['id_entity'] => $this->getId(),
        $this->fields['state'] => static::$states['untreated']
      ]))
      && !empty($change[$this->fields['cfg']])
      && ($cfg = json_decode($change[$this->fields['cfg']], true))
      && !empty($cfg['table'])
      && !empty($cfg['type'])
    ) {
      $error     = false;
      $id_sub    = false;
      $field_sub = false;
      // Subdata
      if (!empty($cfg['subdata'])
        && !empty($cfg['subdata']['table'])
        && !empty($cfg['subdata']['data'])
      ) {
        $subdata = [];
        foreach ($cfg['subdata']['data'] as $d){
          $subdata[$d['field']] = $d['value'];
        }

        $id_sub = !empty($cfg['subdata']['id']) ? $cfg['subdata']['id'] : (!empty($subdata['id']) ? $subdata['id'] : false );
        switch ($cfg['subdata']['table']){
          case 'identities':
            $field_sub = 'id_identity';
            if (!$id_sub) {
              $id_sub = $this->identity()->add($subdata, true);
              $cfg['data'][] = [
                'field' => 'id_identity',
                'value' => $id_sub
              ];
            }
            $id_sub2   = $this->_identity($id_sub, $subdata, $cfg['type'], true);
            break;
          case 'addresses':
            $field_sub = 'id_address';
            if (!$id_sub) {
              $id_sub = $this->address()->add($subdata, true);
              $cfg['data'][] = [
                'field' => 'id_address',
                'value' => $id_sub
              ];
            }

            $id_sub2 = $this->_address($id_sub, $subdata, $cfg['type'], true);
            break;
        }

        if (!empty($id_sub2) && ($id_sub2 !== $id_sub)) {
          if (isset($cfg['subdata']['id'])) {
            $cfg['subdata']['id'] = $id_sub2;
          }
          elseif (($idx_id = X::find($cfg['subdata']['data'], ['field' => 'id'])) !== null) {
            $cfg['subdata']['data'][$idx_id]['value'] = $id_sub2;
          }

          if (isset($field_sub)
            && (($idx_sub = X::find($cfg['data'], ['field' => $field_sub])) !== null)
          ) {
            $cfg['data'][$idx_sub]['value'] = $id_sub2;
          }
        }
      }

      // Data
      $table  = $this->tables[$cfg['table']];
      $data   = [];
      foreach ($cfg['data'] as $d){
        $data[$d['field']] = $d['value'];
      }

      switch ($cfg['table']){
        case 'identities':
          $id_tier = $cfg['id'] ?? $data['id'];
          if ($id_new = $this->_identity($id_tier, $data, $cfg['type'])) {
            if (Str::isUid($id_new) && ($id_new !== $id_tier)) {
              if (isset($cfg['id'])) {
                $cfg['id'] = $id_new;
              }
              elseif (($idx_id = X::find($cfg['data'], ['field' => 'id'])) !== null) {
                $cfg['data'][$idx_id]['value'] = $id_new;
              }
            }
          }
          else {
            $error = _('Error during the identity') . ' ';
            switch ($cfg['type']){
              case 'insert';
                $error .= _('inserting.');
                break;
              case 'update';
                $error .= _('updating.');
                break;
              case 'delete';
                $error .= _('deleting.');
                break;
            }
          }
          break;

        case 'addresses':
          $id_address = $cfg['id'] ?? $data['id'];
          if ($id_new = $this->_address($id_address, $data, $cfg['type'])) {
            if ($id_new !== $id_address) {
              if (isset($cfg['id'])) {
                $cfg['id'] = $id_new;
              }
              elseif (($idx_id = X::find($cfg['data'], ['field' => 'id'])) !== null) {
                $cfg['data'][$idx_id]['value'] = $id_new;
              }
            }
          }
          else {
            $error = _('Error during the address') . ' ';
            switch ($cfg['type']){
              case 'insert';
                $error .= _('inserting.');
                break;
              case 'update';
                $error .= _('updating.');
                break;
              case 'delete';
                $error .= _('deleting.');
                break;
            }
          }
          break;
          case 'admin':
            $idAdmin = !empty($data['id_admin']) ? $data['id_admin'] : (!empty($data['id_identity']) ? $data['id_identity'] : false);
            if (empty($idAdmin) || !$this->entity->update(['id_admin' => $idAdmin])) {
              $error = _('Error during the admin updating.');
            }
            break;
      }

      return empty($error)
        && $this->deleteFileAndLink($id)
        && $this->db->update($this->class_table, [
          $this->fields['state'] => static::$states['accepted'],
          $this->fields['cfg'] => json_encode($cfg)
        ], [
          $this->fields['id'] => $id
        ]);
    }

    return null;
  }


  /**
   * @param string   $id
   * @param null|int
   */
  public function refuse(string $id): ?int
  {
    if (Str::isUid($id)
      && $this->check()
      && $this->get($id, [$this->fields['id_entity'] => $this->getId()])
      && $this->deleteFileAndLink($id)
    ) {
      return $this->db->update($this->class_table, [
        $this->fields['state'] => static::$states['refused']
      ], [
        $this->fields['id'] => $id
      ]);
    }

    return null;
  }


  public function forceState(string $id, $state): bool
  {
    if (\in_array($state, array_values(static::$states), true)) {
      return !!$this->db->update($this->class_table, [$this->fields['state'] => $state], [$this->fields['id'] => $id]);
    }

    return false;
  }


  /**
   * @param string $id
   * @return bool|null|int
   */
  public function getState(string $id)
  {
    return Str::isUid($id) ? $this->db->selectOne($this->class_table, $this->fields['state'], [$this->fields['id'] => $id]) : false;
  }


  /**
   * @param string $id
   * @param array  $cfg
   * @return null|int
   */
  public function getCurrentState(string $id, array $cfg): ?int
  {
    $state = static::$states['untreated'];
    if (!empty($cfg['subdata'])
      && !empty($cfg['subdata']['data'])
      && ($cfg['type'] !== 'delete')
    ) {
      foreach ($cfg['subdata']['data'] as $data) {
        if (\array_key_exists('email', $data)
          && ($data['email'] !== true)
        ) {
          return static::$states['email'];
        }
      }
    }

    if (!empty($cfg['data'])) {
      foreach ($cfg['data'] as $data){
        if (\array_key_exists('email', $data) && ($data['email'] !== true)) {
          $state = static::$states['email'];
          break;
        }
      }
    }

    /* if (($files = $this->getRequiredFiles($id, $cfg['type'])) && \array_filter(
        $files, function ($file) {
        return empty($file['files']) && !empty($file['mandatory']);
      }
      )
    ) {
      $state = static::$states['unready'];
    } */

    return $state;
  }


  public function get(string $id, array $filters = []): ?array
  {
    return $this->db->rselect($this->class_table, [], X::mergeArrays([
      $this->fields['id'] => $id
    ], $filters));
  }


  /**
   * @return null|array
   */
  public function getUntreated(): ?array
  {
    return $this->_get(
      [
        'conditions' => [[
          'field' => $this->fields['id_entity'],
          'value' => $this->getId()
        ], [
          'field' => $this->fields['state'],
          'value' => static::$states['untreated']
        ]]
      ]
    );
  }


  /**
   * @return null|array
   */
  public function getUnready(): ?array
  {
    return $this->_get(
      [
        'conditions' => [[
          'field' => $this->fields['id_entity'],
          'value' => $this->getId()
        ], [
          'field' => $this->fields['state'],
          'operator' => 'isnull'
        ]]
      ]
    );
  }


  /**
   * @return null|array
   */
  public function getUnreadyUntreated(): ?array
  {
    $cfgField = $this->fields['cfg'];
    return array_map(
      function ($change) use($cfgField) {
        if (!empty($change[$cfgField]) && ($cfg = json_decode($change[$cfgField], true))) {
          if (!empty($cfg['data'])) {
            $cfg['data'] = array_map(
              function ($d) {
                if (array_key_exists('email', $d) && ($d['email'] !== true)) {
                  $d['email'] = static::cryptCode($d['email']);
                }

                return $d;
              }, $cfg['data']
            );
          }

          if (!empty($cfg['subdata']) && !empty($cfg['subdata']['data'])) {
            $cfg['subdata']['data'] = array_map(
              function ($d) {
                if (array_key_exists('email', $d) && ($d['email'] !== true)) {
                  $d['email'] = static::cryptCode($d['email']);
                }

                return $d;
              }, $cfg['subdata']['data']
            );
          }

          $change[$cfgField] = json_encode($cfg);
        }

        return $change;
      }, $this->_get([
        'conditions' => [[
          'field' => $this->fields['id_entity'],
          'value' => $this->getId()
        ], [
          'logic' => 'OR',
          'conditions' => [[
            'field' => $this->fields['state'],
            'operator' => 'isnull'
          ], [
            'field' => $this->fields['state'],
            'value' => static::$states['untreated']
          ], [
            'field' => $this->fields['state'],
            'value' => static::$states['email']
          ]]
        ]]
      ])
    );
  }


  /**
   * @return null|array
   */
  public function getNotAcceptedRefused(): ?array
  {
    return $this->_get(
      [
        'conditions' => [[
          'field' => $this->fields['id_entity'],
          'value' => $this->getId()
        ], [
          'logic' => 'OR',
          'conditions' => [[
            'field' => $this->fields['state'],
            'operator' => 'isnull'
          ], [
            'conditions' => [[
              'field' => $this->fields['state'],
              'operator' => '!=',
              'value' => static::$states['accepted']
            ], [
              'field' => $this->fields['state'],
              'operator' => '!=',
              'value' => static::$states['refused']
            ]]
          ]]
        ]]
      ]
    );
  }


  /**
   * @return null|array
   */
  public function getAccepted(): ?array
  {
    return $this->_get(
      [
        'conditions' => [[
          'field' => $this->fields['id_entity'],
          'value' => $this->getId()
        ], [
          'field' => $this->fields['state'],
          'value' => static::$states['accepted']
        ]]
      ]
    );
  }


  /**
   * @return null|array
   */
  public function getRefused(): ?array
  {
    return $this->_get(
      [
        'conditions' => [[
          'field' => $this->fields['id_entity'],
          'value' => $this->getId()
        ], [
          'field' => $this->fields['state'],
          'value' => static::$states['refused']
        ]]
      ]
    );
  }


  /**
   * @return null|array
   */
  public function getAll(array $filter = [], array $order = [], int $limit = 0, int $start = 0, $fields = []): array
  {
    $cfgField = $this->fields['cfg'];
    $all = parent::getAll(...func_get_args());
    return array_map(
      function ($change) use($cfgField) {
        if (!empty($change[$cfgField])
          && ($cfg = json_decode($change[$cfgField], true))
        ) {
          if (!empty($cfg['data'])) {
            $cfg['data'] = array_map(
              function ($d) {
                if (array_key_exists('email', $d) && ($d['email'] !== true)) {
                  $d['email'] = base64_encode(\bbn\Util\Enc::crypt($d['email']));
                }

                return $d;
              }, $cfg['data']
            );
          }

          if (!empty($cfg['subdata']) && !empty($cfg['subdata']['data'])) {
            $cfg['subdata']['data'] = array_map(
              function ($d) {
                if (array_key_exists('email', $d) && ($d['email'] !== true)) {
                  $d['email'] = base64_encode(\bbn\Util\Enc::crypt($d['email']));
                }

                return $d;
              }, $cfg['subdata']['data']
            );
          }

          $change[$cfgField] = json_encode($cfg);
        }

        return $change;
      }, $all
    );
  }


  public function setSkipEmailVerification(bool $skip = true)
  {
    $this->skipEmailVerfication = $skip;
  }


  protected static function getFieldsList()
  {
    return static::getEAFields();
  }


  /**
   * @param array $where
   * @param bool  $withFiles
   * @return null|array
   */
  protected function _get(array $where, bool $withFiles = true): ?array
  {
    if ($this->check()) {
      $t =& $this;
      return array_map(
        function ($e) use ($t, $withFiles) {
          if ($withFiles) {
            $cfg = json_decode($e[$t->fields['cfg']], true);
            $e['files'] = $t->getRequiredFiles($e[$t->fields['id']], $cfg['type']);
          }

          return $e;
        }, $this->db->rselectAll([
          'table' => $this->class_table,
          'fields' => [],
          'where' => $where,
          'order' => [$this->fields['moment'] => 'DESC']
        ])
      );
    }

    return null;
  }


  /**
   * @param string $moment
   * @param array  $cfg
   * @return int
   */
  protected function _insert(string $moment, array $cfg): ?string
  {
    if ($id_adh = $this->getId()) {
      if ($this->db->insert($this->class_table, [
          $this->fields['id_entity'] => $id_adh,
          $this->fields['moment'] => $moment,
          $this->fields['state'] => null,
          $this->fields['cfg'] => \json_encode($cfg)
        ])
        && ($id = $this->db->lastId())
      ) {
        $this->setRequiredFiles($id);
        $this->_setState($id, $this->getCurrentState($id, $cfg));
        return $id;
      }
    }

    return null;
  }


  /**
   * @param string        $id
   * @param bool|null|int $state
   * @return bool
   */
  protected function _setState(string $id, $state = false): bool
  {
    if (Str::isUid($id)) {
      if ($state === false) {
        if ($c = $this->_get([$this->fields['id'] => $id], false)) {
          $cfg = json_decode($c[0][$this->fields['cfg']], true);
        }

        if (isset($cfg) && is_array($cfg)) {
          $state = $this->getCurrentState($id, $cfg);
        }
        else{
          return false;
        }
      }

      return !!$this->db->update(
        $this->class_table,
        [
          $this->fields['state'] => $state
        ],
        [
          $this->fields['id'] => $id
        ]
      );
    }

    return false;
  }


  /**
   * @param string $id
   * @param string $moment
   * @return bool
   */
  protected function _setMoment(string $id, string $moment = ''): bool
  {
    if (Str::isUid($id)) {
      return !!$this->db->update(
        $this->class_table,
        [
          $this->fields['moment'] => $moment ?: date('Y-m-d H:i:s')
        ],
        [
          $this->fields['id'] => $id
        ]
      );
    }

    return false;
  }


  /**
   * @param string $id
   * @param array  $todata
   * @param string $moment
   * @param array  $subdata
   * @return null|int
   */
  protected function _update(string $id, array $todata, string $moment = '', array $subdata = []): ?int
  {
    if (Str::isUid($id)
      && ($old = $this->get($id))
      && (($old[$this->fields['state']] === static::$states['unready'])
        || ($old[$this->fields['state']] === static::$states['untreated'])
        || ($old[$this->fields['state']] === static::$states['email']))
      && ($cfg = \json_decode($old[$this->fields['cfg']], true))
    ) {
      if (($idx = X::find($cfg['data'], ['field' => $todata['field']])) !== null) {
        $cfg['data'][$idx] = X::mergeArrays($cfg['data'][$idx], $this->checkEmailRequired($cfg['table'], $todata));
        $cfg['subdata']    = $subdata;
        if ($this->db->update(
          $this->class_table,
          [
            $this->fields['moment'] => $moment ?: date('Y-m-d H:i:s'),
            $this->fields['cfg'] => \json_encode($cfg)
          ],
          [
            $this->fields['id'] => $id
          ]
        )) {
          $this->setRequiredFiles($id);
          $this->_setState($id, $this->getCurrentState($id, $cfg));
          return 1;
        }
      }

      return 0;
    }

    return null;
  }


  /**
   * @param string $table
   * @param string $field
   * @param string $type
   * @return null|array
   */
  protected function fieldRequiresFile(string $type, string $table, $field = null): ?array
  {
    if (($arr = static::requiredFiles())
      && !empty($arr[$table])
    ) {
      if (empty($field)) {
        $f = \array_values($arr[$table])[0];
        if (!empty($f[$type])) {
          return \array_values($f[$type]);
        }
      }
      elseif (!empty($arr[$table][$field])) {
        $f = $arr[$table][$field];
        if (!empty($f[$type])) {
          return \array_values($f[$type]);
        }
      }
    }
    return null;
  }


  /**
   * @param array|string $id_type
   * @param bool         $files
   * @return null|array
   */
  protected function getFileByType($id_type, bool $files = true): ?array
  {
    return $this->_getFileByType(
      $id_type,
      $files,
      [[
        'field' => $this->db->cfn($this->fields['id_entity'], $this->class_table),
        'value' => $this->getId()
      ]]
    );
  }


  /**
   * @param string $id_change
   * @param string $type
   * @return null|array
   */
  protected function getRequiredFiles(string $id_change, string $type): ?array
  {
    if ($change = $this->get($id_change)) {
      $cCfg = $this->getClassCfg();
      $oCfg = $this->options()->getClassCfg();
      $filesFields = $cCfg['arch']['files'];
      $filesTable = $cCfg['tables']['files'];
      $linksFields = $cCfg['arch']['links'];
      $linksTable = $cCfg['tables']['links'];
      $res = [];
      $cfg = json_decode($change[$this->fields['cfg']], true);
      $all = array_map(function ($f) {
          if (!empty($f['code'])) {
            $f['code'] = (string)$f['code'];
          }

          return $f;
      }, $this->db->rselectAll([
          'table' => $filesTable,
          'fields' => [
            $this->db->cfn($filesFields['id'], $filesTable),
            $this->db->cfn($filesFields['files'], $filesTable),
            $this->db->cfn($filesFields['type_doc'], $filesTable),
            $this->db->cfn($filesFields['date_added'], $filesTable),
            $this->db->cfn($linksFields['mandatory'], $linksTable),
            $this->db->cfn($oCfg['arch']['options']['code'], $oCfg['table']),
          ],
          'join' => [[
            'table' => $linksTable,
            'on' => [
              'conditions' => [[
                'field' => $this->db->cfn($linksFields['id_file'], $linksTable),
                'exp' => $this->db->cfn($filesFields['id'], $filesTable),
              ]]
            ]
          ], [
            'table' => 'bbn_options',
            'on' => [
              'conditions' => [[
                'field' => $this->db->cfn($oCfg['arch']['options']['id'], $oCfg['table']),
                'exp' => $this->db->cfn($filesFields['type_doc'], $filesTable)
              ]]
            ]
          ]],
          'where' => [
            'conditions' => [[
              'field' => $this->db->cfn($linksFields['id_link'], $linksTable),
              'value' => $id_change
            ], [
              'field' => $this->db->cfn($filesFields['files'], $filesTable),
              'operator' => 'isnotnull'
            ]]
          ]
      ]));
      $codes = [];
      if (empty($cfg['data'])) {
        if ($c = $this->fieldRequiresFile($type, $cfg['table'])) {
          \array_push($codes, ...$c);
        }
      }
      else {
        foreach ($cfg['data'] as $d){
          if ($c = $this->fieldRequiresFile($type, $cfg['table'], $d['field'])) {
            \array_push($codes, ...$c);
          }
        }
      }

      if (!empty($codes)) {
        foreach ($codes as $code){
          if (\is_array($code)) {
            $found     = false;
            $mandatory = !empty($code[0]);
            if (!$mandatory) {
              array_shift($code);
            }

            foreach ($code as $c){
              if ((($idx = X::find($all, ['code' => $c])) !== null)
                && (X::find($res, ['code' => (string)$c]) === null)
              ) {
                $res[] = [
                  'code' => (string)$c,
                  'files' => json_decode($all[$idx][$filesFields['files']]),
                  'mandatory' => !!$all[$idx][$linksFields['mandatory']]
                ];
                $found = true;
                //break;
              }
            }

            if (!$found) {
              $tmp = [
                'code' => '',
                'files' => [],
                'mandatory' => $mandatory,
                'codes' => $code
              ];
              if (count($code) === 1) {
                $tmp['code'] = $code[0];
                unset($tmp['codes']);
              }

              $res[] = $tmp;
            }
          }
          else {
            if (X::find($res, ['code' => (string)$code]) === null) {
              if (($idx = X::find($all, ['code' => (string)$code])) !== null) {
                $res[] = [
                  'code' => (string)$code,
                  'files' => json_decode($all[$idx][$filesFields['files']]),
                  'mandatory' => !!$all[$idx][$linksFields['mandatory']]
                ];
              }
              else {
                $res[] = [
                  'code' => (string)$code,
                  'files' => [],
                  'mandatory' => true
                ];
              }
            }
          }
        }
      }

      return $res;
    }

    return null;
  }


  /**
   * @param string $id_change
   * @param string $type
   */
  protected function setRequiredFiles(string $idChange)
  {
    if ($change = $this->get($idChange)) {
      $cfg    = json_decode($change[$this->fields['cfg']], true);
      $codes = [];
      if (empty($cfg['data'])) {
        if ($c = $this->fieldRequiresFile($cfg['type'], $cfg['table'])) {
          \array_push($codes, ...$c);
        }
      }
      else {
        foreach ($cfg['data'] as $d) {
          if ($c = $this->fieldRequiresFile($cfg['type'], $cfg['table'], $d['field'])) {
            \array_push($codes, ...$c);
          }
        }
      }
      if (!empty($codes)) {
        foreach ($codes as $code){
          $mandatory = !(\is_array($code) && ($code[0] === ''));
          if (!$mandatory) {
            array_shift($code);
          }

          if (\is_array($code)) {
            foreach ($code as $c){
              $this->_fileInsert($idChange, $c, $mandatory);
            }
          }
          else {
            $this->_fileInsert($idChange, $code, $mandatory);
          }
        }
      }
    }
  }

  /**
   * @param string $id_link
   * @param string $type
   * @param bool   $mandatory
   * @return string|null
   */
  protected function _fileInsert(string $id_link, string $type, bool $mandatory): ?string
  {
    if (Str::isUid($id_link)) {
      $id_file = $this->insertFile($type);
      if (Str::isUid($id_file)
        && !$this->hasFileLink($id_link, $id_file)
      ) {
        $this->insertFileLink($id_link, $id_file, $mandatory);
      }

      return $id_file;
    }

    return null;
  }


  /**
   * @param string $id
   * @return array
   */
  protected function getIdsByFile(string $id): array
  {
    if (Str::isUid($id)) {
      $cCfg = $this->getClassCfg();
      return $this->db->getColumnValues(
        $cCfg['tables']['links'],
        $this->db->cfn($cCfg['arch']['links']['id_link'], $cCfg['tables']['links']),
        [
          $this->db->cfn($cCfg['arch']['links']['id_file'], $cCfg['tables']['links']) => $id
        ]
      );
    }

    return [];
  }


  /**
   * @param string $table
   * @param string $id
   * @param array  $data
   * @return string
   */
  protected function checkExists(string $table, string $id, array $data, string $type = 'update')
  {
    switch ($type){
      case 'update':
        if (!empty($data['field'])) {
        $conditions = [[
          'field' => $this->fields['id_entity'],
          'value' => $this->getId()
        ], [
          'field' => 'JSON_UNQUOTE(JSON_EXTRACT('.$this->fields['cfg'].', "$.type"))',
          'value' => 'update'
        ], [
          'field' => 'JSON_UNQUOTE(JSON_EXTRACT('.$this->fields['cfg'].', "$.table"))',
          'value' => $table
        ], [
          'field' => 'JSON_UNQUOTE(JSON_EXTRACT('.$this->fields['cfg'].', "$.id"))',
          empty($id) ? 'operator' : 'value' => $id ?: 'isnull'
        ], [
          'field' => "JSON_SEARCH(".$this->fields['cfg'].", 'all', '$data[field]', null, '$.data[*].field')",
          'operator' => 'isnotnull'
        ], [
          'logic' => 'OR',
          'conditions' => [[
            'field' => $this->fields['state'],
            'value' => static::$states['untreated']
          ], [
            'field' => $this->fields['state'],
            'value' => static::$states['email']
          ], [
            'field' => $this->fields['state'],
            'operator' => 'isnull'
          ]]
        ]];
        }
        break;

      case 'delete':
        $conditions = [[
          'field' => $this->fields['id_entity'],
          'value' => $this->getId()
        ], [
          'field' => 'JSON_UNQUOTE(JSON_EXTRACT('.$this->fields['cfg'].', "$.type"))',
          'value' => 'delete'
        ], [
          'field' => 'JSON_UNQUOTE(JSON_EXTRACT('.$this->fields['cfg'].', "$.table"))',
          'value' => $table
        ], [
          'field' => 'JSON_UNQUOTE(JSON_EXTRACT('.$this->fields['cfg'].', "$.id"))',
          'value' => $id
        ], [
          'logic' => 'OR',
          'conditions' => [[
            'field' => $this->fields['state'],
            'value' => static::$states['untreated']
          ], [
            'field' => $this->fields['state'],
            'value' => static::$states['email']
          ], [
            'field' => $this->fields['state'],
            'operator' => 'isnull'
          ]]
        ]];
        break;
    }

    return isset($conditions) ? $this->db->selectOne(
      [
        'table' => $this->class_table,
        'fields' => [$this->fields['id']],
        'where' => [
          'conditions' => $conditions
        ]
      ]
    ) : false;
  }


  /**
   * @param string $table
   * @param string $field
   * @return bool
   */
  protected function emailRequired(string $table, string $field): bool
  {
    return ($em = static::emailVerification())
      && !empty($em[$table])
      && \in_array($field, $em[$table], true);
  }


  /**
   * @param string $table
   * @param array  $data
   * @return array
   */
  protected function checkEmailRequired(string $table, array $data): array
  {
    if (!$this->skipEmailVerfication
      && !empty($data['value'])
      && $this->emailRequired($table, $data['field'])
    ) {
      $data['email'] = Str::genpwd();
      $this->sendEmailVerification($data['value'], $data['email']);
    }

    return $data;
  }


  /**
   * @param string $id
   * @param array  $data
   * @param string $action
   * @param bool   $is_sub
   * @return string|null
   */
  protected function _identity(string $id, array $data, string $action, bool $is_sub = false): ?string
  {
    $exists = $this->db->rselect($this->tables['identities'], [], ['id' => $id]);
    if (!empty($exists)
      && ($action === 'insert')
      && !empty($is_sub)
    ) {
      $action = 'update';
      $is_sub = false;
    }

    $ret = false;
    switch ($action){
      case 'insert':
        if (empty($exists) && isset($data['id'])) {
          unset($data['id']);
        }

        if ($id = $this->identity()->add($data, true)) {
          $ret = true;
        }

        break;
      case 'update':
        if (!isset($data['id_entity'])) {
          $data['id_entity'] = $this->getId();
        }

        if (!empty($exists)) {
          if (!empty($exists['cfg'])) {
            $exists = X::mergeArrays(\json_decode($exists['cfg'], true), $exists);
          }

          unset($exists['cfg']);
        }

        $data = X::mergeArrays($exists, $data);
        $ret  = !!$this->identity()->update($id, $data);
        break;
      case 'delete':
        if (!$is_sub) {
          $ret = !!$this->identity()->delete($id);
        }

        break;
    }

    return $ret ? $id : null;
  }


  /**
   * @param string $id
   * @param array  $data
   * @param string $action
   * @param bool   $is_sub
   * @return string|null
   */
  protected function _address(string $id, array $data, string $action, bool $is_sub = false): ?string
  {
    $exists = $this->db->rselect($this->tables['addresses'], [], ['id' => $id]);
    if (($action === 'update') && empty($exists)) {
      $action = 'insert';
    }

    $ret = false;
    switch ($action){
      case 'insert':
        if (empty($exists) && isset($data['id'])) {
          unset($data['id']);
        }

        if ($id = $this->address()->add($data, true)) {
          $ret = true;
        }

        break;
      case 'update':
        $ret = !!$this->address()->update($id, $data);
        break;
      case 'delete':
        if (!$is_sub) {
          $ret = !!$this->address()->delete($id);
        }

        break;
    }

    return $ret ? $id : null;
  }


  protected function getTables()
  {
    if (empty($this->tables)) {
      $fields = static::getFieldsList();
      foreach ($fields as $field) {
        $cn =& $field['changes'];
        if (!empty($field['table'])
          && !empty($cn['table'])
          && empty($this->tables[$cn['table']])
        ) {
          $this->tables[$cn['table']] = $field['table'];
        }
      }
    }

    return X::mergeArrays($this->tablesOld, $this->tables);
  }

}

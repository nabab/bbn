<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 21/11/2014
 * Time: 13:51
 */

namespace bbn\Entities\Tables;

use bbn\Db;
use bbn\Entities\Address;
use bbn\X;
use bbn\Str;
use bbn\User;
use bbn\Entities\Options as EntityOptions;
use bbn\Entities\Models\EntityTable;
use bbn\Entities\Models\Entities;
use bbn\Entities\Entity;
use bbn\Models\Tts\TmpFiles;


class Changes extends EntityTable
{
  use TmpFiles;

  protected static $default_class_cfg = [
    'table' => 'bbn_entities_changes',
    'tables' => [
      'entities_changes' => 'bbn_entities_changes'
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
      ]
    ]
  ];

  protected static $eaFields = [];

  protected static $table = 'bbn_entities_changes';

  protected static $table_files = 'bbn_tmp_files';

  protected static $table_links = 'bbn_changes_files';

  protected static $table_links_field = 'id_change';

  protected $tables = [];

  /** Compatibility with the old version */
  protected $tablesOld = [
  ];

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
  public static function crypt_code(string $code): string
  {
    return rtrim(strtr(base64_encode(\bbn\Util\Enc::crypt($code)), '+/', '-_'), '=');
  }


  /**
   * @param string $code
   * @return string
   */
  public static function decrypt_code(string $code): string
  {
    return \bbn\Util\Enc::decrypt(base64_decode(strtr($code, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($code)) % 4)));
  }


  /**
   * @param string $id
   * @param string $code
   * @param string $file
   * @return null|bool
   */
  public function attach_file(string $id, string $code, string $file): bool
  {
    if (Str::isUid($id)) {
      $filesLinked = $this->get_files_link($id);
      foreach ($filesLinked as $fl) {
        if (($f = $this->_get_file([$this->db->cfn('id', static::$table_files) => $fl['id_file']]))
          && ((string)$f['code'] === $code)
        ) {
          $data = [
            'files' => empty($f['files']) ? [] : \json_decode($f['files'], true),
            'date_added' => $f['date_added'] ?: date('Y-m-d H:i:s')
          ];
          if (!\in_array($file, $data['files'], true)) {
            $data['files'][] = $file;
          }

          $data['files'] = json_encode($data['files']);
          return $this->update_file($f['id'], $data);
        }
      }
    }

    return false;
  }


  /**
   * @param string $id_type
   * @return bool
   */
  public function reset_file_by_type(string $id_type): bool
  {
    if (($file = $this->get_file_by_type($id_type))
      && $this->update_file(
        $file['id'], [
          'files' => null,
          'date_added' => null
        ]
      )
    ) {
      foreach ($this->get_ids_by_file($file['id']) as $id){
        $this->_set_state($id);
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
  public function send_conf_email(string $email, string $code): bool
  {
    if (Str::isEmail($email)
      && ($user = User::getUser())
      && $this->db->insert(
        'apst_emails4app', [
          'id_people' => $this->db->selectOne(
            'bbn_members', 'id_people', [
              'id' => $user->getId(),
              'active' => 1
            ]
          ) ?: null,
          'id_entity' => $this->getId(),
          'email' => $email,
          'type' => 'verification',
          'text' => BBN_URL . (\str_ends_with(BBN_URL, '/') ? '' : '/') . 'ea/confirm_email/' . $code,
          'moment' => date('Y-m-d H:i:s')
        ]
      )
    ) {
      return true;
    }

    return false;
  }


  /**
   * @param string $table
   * @param string $type
   * @param array  $todata
   * @param string $id
   * @param array  $subdata
   * @return null|int
   */
  public function create(string $table, string $type, array $todata, string $id = '', array $subdata = []): ?array
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
        $tocfg['id'] = $id;
      }

      if (!empty($subdata)
        && !empty($subdata['table'])
        && !empty($subdata['data'])
      ) {
        foreach ($subdata['data'] as $i => $v){
          $subdata['data'][$i] = $this->check_email_required($subdata['table'], $v);
        }

        $tocfg['subdata'] = $subdata;
      }

      if (X::isAssoc($todata)) {
        if ((!empty($is_update) || !empty($is_delete))
          && ($id_change = $this->check_exists($table, $id, $todata, $type))
        ) {
          if (!empty($is_update)) {
            if ($this->_update($id_change, $todata, $moment, $subdata)) {
              return [$id_change];
            }
          }
          elseif ($this->_set_moment($id_change, $moment)) {
            return [$id_change];
          }
        }
        else {
          $tocfg['data'][] = $this->check_email_required($table, $todata);
          if ($id_change = $this->_insert($moment, $tocfg)) {
            return [$id_change];
          }
        }

      }
      elseif (!empty($is_insert) || !empty($is_delete)) {
        foreach ($todata as $i => $t){
          $todata[$i] = !empty($is_insert) ? $this->check_email_required($table, $t) : $t;
        }

        $tocfg['data'] = $todata;
        if (!empty($is_delete)
          && ($id_change = $this->check_exists($table, $id, $todata, $type))
          && $this->_set_moment($id_change, $moment)
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
              && ($id_change = $this->check_exists($table, $id, $t))
              && $this->_update($id_change, $t, $moment, $subdata)
            ) {
            $ret[] = $id_change;
          }
          else {
            $tocfg['data'] = [$this->check_email_required($table, $t)];
              if ($id_change = $this->_insert($moment, $tocfg))
              $ret[] = $id_change;
            }
          }
        }
        elseif (!empty($subdata)) {
          if (!empty($is_update)
            && ($id_change = $this->check_exists($table, $id, $todata))
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
    if (Str::isUid($id) && $this->delete_file_and_link($id)) {
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
      && ($change = $this->db->rselect(
        static::$table, [], [
          'id' => $id,
          'state' => static::$states['untreated']
        ]
      ))
      && ($change['id_entity'] == $this->getId())
      && !empty($change['cfg'])
      && ($cfg = json_decode($change['cfg'], true))
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
          case 'tiers':
            $field_sub = 'id_people';
            if (!$id_sub) {
              $id_sub = $this->tiersMgr()->add($subdata, true);
              $cfg['data'][] = [
                'field' => 'id_people',
                'value' => $id_sub
              ];
            }
            $id_sub2   = $this->tiers($id_sub, $subdata, $cfg['type'], true);
            break;
          case 'lieux':
            $field_sub = 'id_address';
            if (!$id_sub) {
              $id_sub = $this->lieuxMgr()->add($subdata, true);
              $cfg['data'][] = [
                'field' => 'id_address',
                'value' => $id_sub
              ];
            }

            $id_sub2 = $this->lieu($id_sub, $subdata, $cfg['type'], true);
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
        case 'adherents':
        case 'finances':
        case 'formation':
          if (!$this->entity->update($data)) {
            $error = _('Error during the adherent updating.');
          }
          break;

        case 'clotures':
          $nextCloture = $this->entity->getNextCloture();
          if ($nextCloture !== $data['next_cloture']) {
            $y = substr($data['next_cloture'], 0, 4);
            $m = substr($data['next_cloture'], 5, 2);
            if (!($idCloture = $this->entity->getNextClotureId())
                || ($nextCloture <= date('Y-m-d'))
                || !($this->entity->updateCloture($idCloture, $y, $m))
            ) {
              $error = _("Impossible de mettre à jour la clôture");
            }
          }
          break;

        case 'marques':
          switch ($cfg['type']){
            case 'insert':
              if (!$this->entity->marques()->update($data)) {
                $error = _('Error during the marque inserting.');
              }
              break;
            case 'update':
              if (!$this->db->update($table, $data, ['id' => $cfg['id']])) {
                $error = _('Error during the marque updating.');
              }
              break;
            case 'delete':
              if (!$this->entity->marques()->delete($cfg['id'])) {
                $error = _('Error during the marque deleting.');
              }
              break;
          }

          break;

        case 'actionnaires':
          if (empty($data['id_people']) && !empty($cfg['id'])) {
            $data['id_people'] = $this->db->selectOne('bbn_entities_links', 'id_people', ['id' => $cfg['id']]);
          }

          switch ($cfg['type']){
            case 'insert':
              if (!empty($data['id_people'])
                && !empty($data['parts'])
              ) {
                if (!$this->entity->actionnaire()->insert($data)) {
                  $error = _('Error during the actionnaire inserting.');
                }
              }
              else {
                $error = _('Error during the actionnaire inserting.');
              }
              break;
            case 'update':
              if (!empty($cfg['id']) && !empty($data['id_people'])) {
                $toUpd = X::mergeArrays([
                  'id' => $cfg['id']
                ], $data);
                if (!isset($toUpd['parts'])) {
                  $toUpd['parts'] = $this->db->selectOne(
                    'bbn_entities_links',
                    'JSON_UNQUOTE(JSON_EXTRACT(cfg, "$.parts"))',
                    ['id' => $cfg['id']]
                  );
                }
                if (!$this->entity->actionnaire()->update($toUpd)) {
                  $error = _('Error during the representant updating.');
                }
              }
              else {
                $error = _('Error during the representant updating.');
              }
              break;
            case 'delete':
              if (!empty($data['id_people'])
                && !$this->entity->links()->actionnaireDelete($data['id_people'])
              ) {
                $error = _('Error during the actionnaire deleting.');
              }
              break;
          }

          break;

        case 'representants':
          switch ($cfg['type']){
            case 'insert':
              if (!empty($data['id_people'])) {
                $toIns = [
                  'id_people' => $data['id_people']
                ];
                if (!empty($data['representant'])) {
                  $toIns['representant'] = $data['representant'];
                }

                if (!$this->entity->representant()->insert($toIns)) {
                  $error = _('Error during the representant inserting.');
                }
              }
              else {
                $error = _('Error during the representant inserting.');
              }
              break;
            case 'update':
              if (!empty($cfg['id']) && !empty($id_sub)) {
                $toUpd = X::mergeArrays([
                  'id' => $cfg['id'],
                  'id_people' => $id_sub
                ], $data);
                if (!$this->entity->representant()->update($toUpd)) {
                  $error = _('Error during the representant updating.');
                }
              }
              else {
                $error = _('Error during the representant updating.');
              }
              break;
            case 'delete':
              if (!empty($cfg['id'])
                && !$this->entity->representant()->delete($cfg['id'])
              ) {
                $error = _('Error during the representant deleting.');
              }
          }
          break;

        case 'succursales':
          switch ($cfg['type']){
            case 'insert':
              if (!$this->entity->links()->succursaleUpdateOrInsert($data)) {
                $error = _('Error during the succursale inserting.');
              }
              break;
            case 'update':
              if (!empty($cfg['id'])
                && ($old = $this->entity->succursale()->get($cfg['id']))
                && ($data = X::mergeArrays((array)$old->link, $data))
                && !$this->entity->links()->succursaleUpdateOrInsert($data)
              ) {
                $error = _('Error during the succursale updating.');
              }
              break;
            case 'delete':
              if (!empty($cfg['id'])
                && !empty($data['date_radiation'])
                && ($data['id_address'] = $this->db->selectOne('bbn_entities_links', 'id_address', ['id' => $cfg['id']]))
                && !$this->entity->links()->succursaleDelete($data['id_address'], $data['date_radiation'])
              ) {
                $error = _('Error during the succursale deleting.');
              }
              break;
          }
          break;

        case 'siege':
          switch ($cfg['type']){
            case 'insert':
            case 'update':
              if (!empty($data['id_address'])
                && !$this->entity->links()->setSiege($data['id_address'])
              ) {
                $error = _('Error during the siege') . ' ' . $cfg['type'] === 'insert' ? _('inserting.') : _('updating.');
              }
              break;
            case 'delete':
              if (!$this->entity->links()->unsetSiege()) {
                $error = _('Error during the siege deleting.');
              }
          }
          break;

        case 'courrier':
          switch ($cfg['type']){
            case 'insert':
            case 'update':
              if (!empty($data['id_address'])
                && !$this->entity->links()->setCourrier($data['id_address'])
              ) {
                $error = _('Error during the courrier') . ' ' . $cfg['type'] === 'insert' ? _('inserting.') : _('updating.');
              }
              break;
            case 'delete':
              if (!$this->entity->links()->unsetCourrier()) {
                $error = _('Error during the courrier deleting.');
              }
          }
          break;

        case 'tiers':
          $id_tier = $cfg['id'] ?? $data['id'];
          if ($id_new = $this->tiers($id_tier, $data, $cfg['type'])) {
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
            $error = _('Error during the tiers') . ' ';
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

        case 'lieux':
          $id_address = $cfg['id'] ?? $data['id'];
          if ($id_new = $this->lieu($id_address, $data, $cfg['type'])) {
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
            $error = _('Error during the lieu') . ' ';
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

        case 'infos_complementaires':
          $id_opt = array_keys($data)[0];
          if (Str::isUid($id_opt)
            && ($code = $this->options()->code($id_opt))
            && !$this->entity->updateAdditionalInfo([$code => $data[$id_opt]], true)
          ) {
            $error = _('Error during the infos complementaires') . ' ';
            switch ($cfg['type']){
              case 'insert';
                $error .= _('inserting.');
                break;
              case 'update';
                $error .= _('updating.');
                break;
            }
          }
          break;

        case 'mandataires':
          $eo = new EntityOptions($this->db);
          $eoTypes = $eo->getTypes();
          if (!empty($eoTypes['mandataires'])) {
            $currentMandataires = $eo->get($this->entity->getId(), $eoTypes['mandataires']);
            $newMandataires = $currentMandataires;
            switch ($cfg['type']) {
              case 'insert':
                if (!\in_array($data['id_option'], $newMandataires)) {
                  $newMandataires[] = $data['id_option'];
                }
                $e2 = _('inserting.');
                break;
              case 'delete':
                if (\in_array($data['id_option'], $newMandataires)) {
                  \array_splice($newMandataires, X::indexOf($newMandataires, $data['id_option']) , 1);
                }
                $e2 = _('deleting.');
                break;
            }
            if (($currentMandataires === $newMandataires)
              || !$this->entity->update(['mandataires' => $newMandataires])
            ) {
              $error = _('Error during the mandataire') . ' ' . $e2;
            }
          }
          break;

        case 'reseaux':
          $eo = new EntityOptions($this->db);
          $eoTypes = $eo->getTypes();
          if (!empty($eoTypes['reseaux'])) {
            $currentReseaux = $eo->get($this->entity->getId(), $eoTypes['reseaux']);
            $newReseaux = $currentReseaux;
            switch ($cfg['type']) {
              case 'insert':
                if (!\in_array($data['id_option'], $newReseaux)) {
                  $newReseaux[] = $data['id_option'];
                }
                $e2 = _('inserting.');
                break;
              case 'delete':
                if (\in_array($data['id_option'], $newReseaux)) {
                  \array_splice($newReseaux, X::indexOf($newReseaux, $data['id_option']) , 1);
                }
                $e2 = _('deleting.');
                break;
            }
            if (($currentReseaux === $newReseaux)
              || !$this->entity->update(['reseaux' => $newReseaux])
            ) {
              $error = _('Error during the reseau') . ' ' . $e2;
            }
          }
          break;
          case 'admin':
            $idAdmin = !empty($data['id_admin']) ? $data['id_admin'] : (!empty($data['id_people']) ? $data['id_people'] : false);
            if (empty($idAdmin) || !$this->entity->update(['id_admin' => $idAdmin])) {
              $error = _('Error during the admin updating.');
            }
            break;
      }

      return empty($error) && $this->delete_file_and_link($id) && $this->db->update(
          static::$table, [
          'state' => static::$states['accepted'],
          'cfg' => json_encode($cfg)
        ], ['id' => $id]
        );
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
      && ($this->db->selectOne(static::$table, ['id_entity'], ['id' => $id]) == $this->getId())
      && $this->delete_file_and_link($id)
    ) {
      return $this->db->update(static::$table, ['state' => static::$states['refused']], ['id' => $id]);
    }

    return null;
  }


  public function force_state(string $id, $state): bool
  {
    if (\in_array($state, array_values(static::$states), true)) {
      return !!$this->db->update(static::$table, ['state' => $state], ['id' => $id]);
    }

    return false;
  }


  /**
   * @param string $id
   * @return bool|null|int
   */
  public function getState(string $id)
  {
    return Str::isUid($id) ? $this->db->selectOne(static::$table, 'state', ['id' => $id]) : false;
  }


  /**
   * @param string $id
   * @param array  $cfg
   * @return null|int
   */
  public function get_current_state(string $id, array $cfg): ?int
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

    /* if (($files = $this->get_required_files($id, $cfg['type'])) && \array_filter(
        $files, function ($file) {
        return empty($file['files']) && !empty($file['mandatory']);
      }
      )
    ) {
      $state = static::$states['unready'];
    } */

    return $state;
  }


  /**
   * @return null|array
   */
  public function get_untreated(): ?array
  {
    return $this->_get(
      [
        'conditions' => [[
          'field' => 'id_entity',
          'value' => $this->getId()
        ], [
          'field' => 'state',
          'value' => static::$states['untreated']
        ]]
      ]
    );
  }


  /**
   * @return null|array
   */
  public function get_unready(): ?array
  {
    return $this->_get(
      [
        'conditions' => [[
          'field' => 'id_entity',
          'value' => $this->getId()
        ], [
          'field' => 'state',
          'operator' => 'isnull'
        ]]
      ]
    );
  }


  /**
   * @return null|array
   */
  public function get_unready_untreated(): ?array
  {
    return array_map(
      function ($change) {
        if (!empty($change['cfg']) && ($cfg = json_decode($change['cfg'], true))) {
          if (!empty($cfg['data'])) {
            $cfg['data'] = array_map(
              function ($d) {
                if (array_key_exists('email', $d) && ($d['email'] !== true)) {
                  $d['email'] = static::crypt_code($d['email']);
                }

                return $d;
              }, $cfg['data']
            );
          }

          if (!empty($cfg['subdata']) && !empty($cfg['subdata']['data'])) {
            $cfg['subdata']['data'] = array_map(
              function ($d) {
                if (array_key_exists('email', $d) && ($d['email'] !== true)) {
                  $d['email'] = static::crypt_code($d['email']);
                }

                return $d;
              }, $cfg['subdata']['data']
            );
          }

          $change['cfg'] = json_encode($cfg);
        }

        return $change;
      }, $this->_get([
        'conditions' => [[
          'field' => 'id_entity',
          'value' => $this->getId()
        ], [
          'logic' => 'OR',
          'conditions' => [[
            'field' => 'state',
            'operator' => 'isnull'
          ], [
            'field' => 'state',
            'value' => static::$states['untreated']
          ], [
            'field' => 'state',
            'value' => static::$states['email']
          ]]
        ]]
      ])
    );
  }


  /**
   * @return null|array
   */
  public function get_not_accepted_refused(): ?array
  {
    return $this->_get(
      [
        'conditions' => [[
          'field' => 'id_entity',
          'value' => $this->getId()
        ], [
          'logic' => 'OR',
          'conditions' => [[
            'field' => 'state',
            'operator' => 'isnull'
          ], [
            'conditions' => [[
              'field' => 'state',
              'operator' => '!=',
              'value' => static::$states['accepted']
            ], [
              'field' => 'state',
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
  public function get_accepted(): ?array
  {
    return $this->_get(
      [
        'conditions' => [[
          'field' => 'id_entity',
          'value' => $this->getId()
        ], [
          'field' => 'state',
          'value' => static::$states['accepted']
        ]]
      ]
    );
  }


  /**
   * @return null|array
   */
  public function get_refused(): ?array
  {
    return $this->_get(
      [
        'conditions' => [[
          'field' => 'id_entity',
          'value' => $this->getId()
        ], [
          'field' => 'state',
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
    $all = parent::getAll(...func_get_args());
    return array_map(
      function ($change) {
        if (!empty($change['cfg']) && ($cfg = json_decode($change['cfg'], true))) {
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

          $change['cfg'] = json_encode($cfg);
        }

        return $change;
      }, $all
    );
  }


  public function setAuth(string $id, string $idAuth): bool
  {
    return (bool)$this->db->update(static::$table, ['id_member_auth' => $idAuth], ['id' => $id]);
  }


  private static function getFieldsList()
  {
    return static::getEAFields();
  }


  /**
   * @param array $where
   * @param bool  $with_files
   * @return null|array
   */
  private function _get(array $where, bool $with_files = true): ?array
  {
    if ($this->check()) {
      $t =& $this;
      $a = new \apst\Auth($this->db);
      return array_map(
        function ($e) use ($t, $a, $with_files) {
          if ($with_files) {
            $cfg        = json_decode($e['cfg'], true);
            $e['files'] = $t->get_required_files($e['id'], $cfg['type']);
          }

          if (!empty($e['id_member_auth'])) {
            $auth = $a->get($e['id_member_auth']);
            if (!empty($auth['info']['declarant'])) {
              $e['declarant'] = $auth['info']['declarant'];
              unset($auth['info']);
              $e['declarant']['verification'] = $auth;
            }
          }

          return $e;
        }, $this->db->rselectAll([
          'table' => static::$table,
          'fields' => [],
          'where' => $where,
          'order' => ['moment' => 'DESC']
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
  private function _insert(string $moment, array $cfg): ?string
  {
    if ($id_adh = $this->getId()) {
      if ($this->db->insert(
          static::$table, [
            'id_entity' => $id_adh,
            'moment' => $moment,
            'state' => null,
            'cfg' => \json_encode($cfg)
          ]
        )
        && ($id = $this->db->lastId())
      ) {
        $this->set_required_files($id);
        $this->_set_state($id, $this->get_current_state($id, $cfg));
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
  private function _set_state(string $id, $state = false): bool
  {
    if (Str::isUid($id)) {
      if ($state === false) {
        if ($c = $this->_get(['id' => $id], false)) {
          $cfg = json_decode($c[0]['cfg'], true);
        }

        if (isset($cfg) && is_array($cfg)) {
          $state = $this->get_current_state($id, $cfg);
        }
        else{
          return false;
        }
      }

      return !!$this->db->update(static::$table, ['state' => $state], ['id' => $id]);
    }

    return false;
  }


  /**
   * @param string $id
   * @param string $moment
   * @return bool
   */
  private function _set_moment(string $id, string $moment = ''): bool
  {
    if (Str::isUid($id)) {
      return !!$this->db->update(static::$table, ['moment' => $moment ?: date('Y-m-d H:i:s')], ['id' => $id]);
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
  private function _update(string $id, array $todata, string $moment = '', array $subdata = []): ?int
  {
    if (Str::isUid($id)
      && ($old = $this->db->rselect(static::$table, [], ['id' => $id]))
      && (    ($old['state'] === static::$states['unready'])
        || ($old['state'] === static::$states['untreated'])
        || ($old['state'] === static::$states['email']))
      && ($cfg = \json_decode($old['cfg'], true))
    ) {
      if (($idx = X::find($cfg['data'], ['field' => $todata['field']])) !== null) {
        $cfg['data'][$idx] = X::mergeArrays($cfg['data'][$idx], $this->check_email_required($cfg['table'], $todata));
        $cfg['subdata']    = $subdata;
        if ($this->db->update(
          static::$table, [
          'moment' => $moment ?: date('Y-m-d H:i:s'),
          'cfg' => \json_encode($cfg)
        ], ['id' => $id]
        )
        ) {
          $this->set_required_files($id);
          $this->_set_state($id, $this->get_current_state($id, $cfg));
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
  private function field_requires_file(string $type, string $table, $field = null): ?array
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
  private function get_file_by_type($id_type, bool $files = true): ?array
  {
    return $this->_get_file_by_type(
      $id_type, $files, [[
        'field' => $this->db->colFullName('id_entity', static::$table),
        'value' => $this->getId()
      ]]
    );
  }


  /**
   * @param string $id_change
   * @param string $type
   * @return null|array
   */
  private function get_required_files(string $id_change, string $type): ?array
  {
    if ($change = $this->_get(['id' => $id_change], false)) {
      $res = [];
      $change = $change[0];
      $cfg = json_decode($change['cfg'], true);
      $all = array_map(function ($f) {
          if (!empty($f['code'])) {
            $f['code'] = (string)$f['code'];
          }

          return $f;
      }, $this->db->rselectAll([
          'table' => static::$table_files,
          'fields' => [
            $this->db->colFullName('id', static::$table_files),
            $this->db->colFullName('files', static::$table_files),
            $this->db->colFullName('type_doc', static::$table_files),
            $this->db->colFullName('date_added', static::$table_files),
            $this->db->colFullName('mandatory', static::$table_links),
            $this->db->colFullName('code', 'bbn_options'),
          ],
          'join' => [[
            'table' => static::$table_links,
            'on' => [
              'conditions' => [[
                'field' => $this->db->colFullName('id_file', static::$table_links),
                'exp' => $this->db->colFullName('id', static::$table_files),
              ]]
            ]
          ], [
            'table' => 'bbn_options',
            'on' => [
              'conditions' => [[
                'field' => $this->db->colFullName('id', 'bbn_options'),
                'exp' => $this->db->colFullName('type_doc', static::$table_files)
              ]]
            ]
          ]],
          'where' => [
            'conditions' => [[
              'field' => $this->db->colFullName('id_change', static::$table_links),
              'value' => $id_change
            ], [
              'field' => $this->db->colFullName('files', static::$table_files),
              'operator' => 'isnotnull'
            ]]
          ]
      ]));
      $codes = [];
      if (empty($cfg['data'])) {
        if ($c = $this->field_requires_file($type, $cfg['table'])) {
          \array_push($codes, ...$c);
        }
      }
      else {
        foreach ($cfg['data'] as $d){
          if ($c = $this->field_requires_file($type, $cfg['table'], $d['field'])) {
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
                  'files' => json_decode($all[$idx]['files']),
                  'mandatory' => !!$all[$idx]['mandatory']
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
                  'files' => json_decode($all[$idx]['files']),
                  'mandatory' => !!$all[$idx]['mandatory']
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
  private function set_required_files(string $idChange)
  {
    if ($change = $this->_get(['id' => $idChange], false)) {
      $change = $change[0];
      $cfg    = json_decode($change['cfg'], true);
      $codes = [];
      if (empty($cfg['data'])) {
        if ($c = $this->field_requires_file($cfg['type'], $cfg['table'])) {
          \array_push($codes, ...$c);
        }
      }
      else {
        foreach ($cfg['data'] as $d) {
          if ($c = $this->field_requires_file($cfg['type'], $cfg['table'], $d['field'])) {
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
              $this->_file_insert($idChange, $c, $mandatory);
            }
          }
          else {
            $this->_file_insert($idChange, $code, $mandatory);
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
  private function _file_insert(string $id_link, string $type, bool $mandatory): ?string
  {
    if (Str::isUid($id_link)) {
      $id_file = $this->insert_file($type);
      if (Str::isUid($id_file)
        && !$this->has_file_link($id_link, $id_file)
      ) {
        $this->insert_file_link($id_link, $id_file, $mandatory);
      }

      return $id_file;
    }

    return null;
  }


  /*  END FILES PRIVATE */


  /**
   * @param string $id
   * @return array
   */
  private function get_ids_by_file(string $id): array
  {
    if (Str::isUid($id)) {
      return $this->db->getColumnValues(
        static::$table_links, $this->db->colFullName('id_change', static::$table_links), [
          $this->db->colFullName('id_file', static::$table_links) => $id
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
  private function check_exists(string $table, string $id, array $data, string $type = 'update')
  {
    switch ($type){
      case 'update':
        if (!empty($data['field'])) {
        $conditions = [[
          'field' => 'id_entity',
          'value' => $this->getId()
        ], [
          'field' => 'JSON_UNQUOTE(JSON_EXTRACT(cfg, "$.type"))',
          'value' => 'update'
        ], [
          'field' => 'JSON_UNQUOTE(JSON_EXTRACT(cfg, "$.table"))',
          'value' => $table
        ], [
          'field' => 'JSON_UNQUOTE(JSON_EXTRACT(cfg, "$.id"))',
          empty($id) ? 'operator' : 'value' => $id ?: 'isnull'
        ], [
          'field' => "JSON_SEARCH(cfg, 'all', '$data[field]', null, '$.data[*].field')",
          'operator' => 'isnotnull'
        ], [
          'logic' => 'OR',
          'conditions' => [[
            'field' => 'state',
            'value' => static::$states['untreated']
          ], [
            'field' => 'state',
            'value' => static::$states['email']
          ], [
            'field' => 'state',
            'operator' => 'isnull'
          ]]
        ]];
        }
        break;

      case 'delete':
        $conditions = [[
          'field' => 'id_entity',
          'value' => $this->getId()
        ], [
          'field' => 'JSON_UNQUOTE(JSON_EXTRACT(cfg, "$.type"))',
          'value' => 'delete'
        ], [
          'field' => 'JSON_UNQUOTE(JSON_EXTRACT(cfg, "$.table"))',
          'value' => $table
        ], [
          'field' => 'JSON_UNQUOTE(JSON_EXTRACT(cfg, "$.id"))',
          'value' => $id
        ], [
          'logic' => 'OR',
          'conditions' => [[
            'field' => 'state',
            'value' => static::$states['untreated']
          ], [
            'field' => 'state',
            'value' => static::$states['email']
          ], [
            'field' => 'state',
            'operator' => 'isnull'
          ]]
        ]];
        break;
    }

    return isset($conditions) ? $this->db->selectOne(
      [
        'table' => static::$table,
        'fields' => ['id'],
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
  private function email_required(string $table, string $field): bool
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
  private function check_email_required(string $table, array $data): array
  {
    if (!empty($data['value'])
      && $this->email_required($table, $data['field'])
    ) {
      $data['email'] = Str::genpwd();
      $this->send_conf_email($data['value'], static::crypt_code($data['email']));
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
  private function tiers(string $id, array $data, string $action, bool $is_sub = false): ?string
  {
    $tiers = new \apst\tiers($this->db);
    $exists = $this->db->rselect($this->tables['tiers'], [], ['id' => $id]);
    if (!empty($exists)
      && ($action === 'insert')
      && !empty($is_sub)
    ) {
      $action = 'update';
      $is_sub = false;
    }

    $ret       = false;
    $fonction = $data['fonction'] ?? ($data['id_option'] ?? null);
    switch ($action){
      case 'insert':
        if (empty($exists) && isset($data['id'])) {
          unset($data['id']);
        }

        if ($id = $tiers->add($data, true)) {
          // Fonctions
          if (!empty($fonction)) {
            $this->entity->fonction()->insert(
              [
                'id_people' => $id,
                'id_option' => $fonction
              ]
            );
          }

          $ret = true;
        }
        break;
      case 'update':
        $ok1 = false;
        $ok2 = false;
        // Fonctions
        if (!empty($fonction)) {
          $id_lien = $this->entity->fonction()->_id_by_tiers($id);
          if (empty($id_lien)) {
            $ok1 = $this->entity->fonction()->insert(
              [
                'id_people' => $id,
                'id_option' => $fonction
              ]
            );
          }
          else {
            $ok1 = $this->entity->fonction()->update(
              [
                'id' => $id_lien,
                'id_people' => $id,
                'id_option' => $fonction
              ]
            );
          }
        }

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
        $ok2  = $tiers->update($id, $data);
        $ret  = !!$ok1 || !!$ok2;
        break;
      case 'delete':
        if (!$is_sub) {
          // Fonctions
          if ($id_lien = $this->entity->fonction()->_id_by_tiers($id)) {
            $this->entity->fonction()->delete($id_lien);
          }

          $ret = !!$tiers->delete($id);
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
  private function lieu(string $id, array $data, string $action, bool $is_sub = false): ?string
  {
    $lieux  = new Address($this->db);
    $exists = $this->db->rselect($this->tables['lieux'], [], ['id' => $id]);
    if (($action === 'update') && empty($exists)) {
      $action = 'insert';
    }
    $ret    = false;
    switch ($action){
      case 'insert':
        if (empty($exists) && isset($data['id'])) {
          unset($data['id']);
        }

        if ($id = $lieux->add($data, true)) {
          $ret = true;
        }
        break;
      case 'update':
        $ret = !!$lieux->update($id, $data);
        break;
      case 'delete':
        if (!$is_sub) {
          $ret = !!$lieux->delete($id);
        }
        break;
    }

    return $ret ? $id : null;
  }


  private function getTables()
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

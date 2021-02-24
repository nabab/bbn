<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 15/06/2018
 * Time: 12:02
 */

namespace bbn\Appui;

use bbn;
use bbn\X;
use Error;

class Mailing extends bbn\Models\Cls\Db
{
  use bbn\Models\Tts\Optional;

  private $_test_emails;

  private static $_cfgs;

  private static $_mailers = [];

  protected $notes;
  protected $medias;
  protected $mailer;


  /**
   * Gets a notes instance by constructing one if needed.
   *
   * @return Note
   */
  private function _note(): Note
  {
    if (!$this->notes) {
      $this->notes = new Note($this->db);
    }
    return $this->notes;
  }

  /**
   * Gets a medias instance by constructing one if needed.
   *
   * @return Medias
   */
  private function _medias(): Medias
  {
    if (!$this->medias) {
      $this->medias = new Medias($this->db);
    }
    return $this->medias;
  }

  private static function _get_cfgs()
  {
    if (is_null(self::$_cfgs)) {
      $cfgs = self::getOptions('sender');
      self::$_cfgs = [];
      foreach ($cfgs as $cfg) {
        if (bbn\X::hasProps($cfg, ['host', 'from'])) {
          self::$_cfgs[] = $cfg;
        }
      }
    }
    return self::$_cfgs;
  }

  public static function _get_cfg(string $id)
  {
    return bbn\X::getRow(
      self::_get_cfgs() ?: [],
      bbn\Str::isUid($id) ? ['id' => $id] : ['code' => $id]
    );
  }

  private static function _get_mailer(string $id = null): ?bbn\Mail
  {
    if (!$id) {
      if ($cfgs = self::_get_cfgs()) {
        $cfg = $cfgs[0];
      }
    }
    else{
      $cfg = self::_get_cfg($id);
    }
    if (!empty($cfg)) {
      if (!isset(self::$_mailers[$cfg['id']])) {
        self::$_mailers[$cfg['id']] = new bbn\Mail($cfg);
      }
      return self::$_mailers[$cfg['id']];
    }
    return null;
  }

  public function __construct(bbn\Db $db, array $cfg = null)
  {
    if ($db->check()) {
      self::optionalInit();
      $this->getOptionsTextValue('text');
      $this->db = $db;
    }
  }

  /**
   * Returns an array of email addresses used for testing purposes.
   *
   * @return array
   */
  public function getTestEmails(): array
  {
    if (!is_array($this->_test_emails)) {
      $emails = $this->getOptionsTextValue('test');
      $this->_test_emails = [];
      foreach ($emails as $em) {
        $this->_test_emails[] = $em['text'];
      }
    }
    return $this->_test_emails;
  }

  /**
   * Checks if the object is in error state.
   *
   * @return boolean
   */
  public function check(): bool
  {
    return $this->db && $this->db->check();
  }

  /**
   * Checks if the given mailing is being sent.
   *
   * @param string $id
   * @return boolean
   */
  public function isSending(string $id = null): bool
  {
    $cond = ['state' => 'sending'];
    if ($id) {
      $cond['id'] = $id;
    }
    return $this->db->count('bbn_emailings', $cond) > 0;
  }

  /**
   * Checks if the given mailing is in suspended state.
   *
   * @param string $id
   * @return boolean
   */
  public function isSuspended(string $id = null): bool
  {
    $cond = ['state' => 'suspended'];
    if ($id) {
      $cond['id'] = $id;
    }
    return $this->db->count('bbn_emailings', $cond) > 0;
  }

  /*
  public function get_recipients($id_recipients): ?array
  {
    if ( $this->check() && ($opt = Option::getInstance()) && ($cfg = $opt->option($id_recipients)) ){
      if ( isset($cfg['model'], $cfg['grid'], $cfg['column']) && ($m = $this->mvc->getModel($cfg['model'], $cfg['grid'])) ){
        $m['column'] = $cfg['column'];
        return $m;
      }
    }
    return [];
  }
  */

  /**
   * Returns the next mailing to process if any.
   *
   * @return array|null
   */
  public function getNextMailing(): ?array
  {
    if ($this->check() 
        && ($id = $this->db->selectOne(
          'bbn_emailings', 'id', [
          ['state', 'LIKE', 'ready'],
          ['sent', '<', Date('Y-m-d H:i:s')],
          ['sent', 'isnotnull']
          ], ['sent' => 'ASC']
        ))
    ) {
      return $this->getMailing($id);
    }
    return null;
  }

  /**
   * Changes the state of the given mailing.
   *
   * @param string $id
   * @param string $new_state
   * @return boolean
   */
  public function changeState(string $id, string $new_state): bool
  {
    if ($this->check()) {
      $cur = $this->db->selectOne("bbn_emailings", 'state', ['id' => $id]);
      if (($cur === 'sent') || ($cur === 'cancelled')) {
        return false;
      }
      return (bool)$this->db->update("bbn_emailings", ['state' => $new_state], ['id' => $id]);
    }
    return false;
  }

  /**
   * Returns the attachments for the given mailing.
   *
   * @param string $id
   * @return array|null
   */
  public function getMedias(string $id): ?array
  {
    if ($this->check() && ($row = $this->db->select('bbn_emailings', ['id_note', 'version'], ['id' => $id]))) {
      return $this->_note()->getMedias($row->id_note, $row->version);
    }
    return null;
  }

  /**
   * Returns all the information about the given mailing.
   *
   * @param string $id
   * @return array|null
   */
  public function getMailing(string $id): ?array
  {
    if ($this->check()) {
      $notes = $this->_note();
      if (($row = $this->db->rselect('bbn_emailings', [], ['id' => $id])) 
          && ($note = $notes->get($row['id_note']))
      ) {
        return array_merge($note, $row);
      }
    }
    return null;
  }

  /**
   * Sends the emails to be sent in the limit provided.
   *
   * @param int $limit
   * @return int
   */
  public function process(int $limit = 10): ?int
  {
    if ($this->check()) {
      $sent = 0;
      $successes = 0;
      $mailings = [];
      foreach ($this->db->rselectAll(
        [
        'table' => 'bbn_emails',
        'fields' => ['bbn_emails.id', 'email', 'id_mailing', 'subject', 'text', 'cfg', 'status', 'delivery', 'read', 'priority'],
        'join' => [[
          'table' => 'bbn_emailings',
          'type' => 'left',
          'on' => [
            'conditions' => [
              [
                'field' => 'id_mailing',
                'exp' => 'bbn_emailings.id'
              ]
            ]
          ]
        ]],
        'where' => [
          'conditions' => [
            'status' => 'ready',
            [
              'logic' => 'OR',
              'conditions' => [
                [
                  'field' => 'delivery',
                  'operator' => 'isnull'
                ], [
                  'field' => 'delivery',
                  'operator' => '<',
                  'exp' => 'NOW()'
                ]
              ]
            ], [
              'logic' => 'OR',
              'conditions' => [
                [
                  'field' => 'bbn_emailings.state',
                  'operator' => 'isnull'
                ], [
                  'field' => 'bbn_emailings.state',
                  'value' => 'ready'
                ], [
                  'field' => 'bbn_emailings.state',
                  'value' => 'sending'
                ]
              ]
            ]
          ]
        ],
        'order' => ['priority'],
        'limit' => $limit
        ]
      ) as $r) {
        $sent++;
        $ok = false;
        $att = [];
        if (!empty($r['id_mailing'])) {
          if (!isset($mailings[$r['id_mailing']])) {
            $mailings[$r['id_mailing']] = $this->getMailing($r['id_mailing']);
          }
          $mailing = &$mailings[$r['id_mailing']];
          if ($mailing['state'] === 'ready') {
            $this->changeState($r['id_mailing'], 'sending');
            $mailing['state'] = 'sending';
          }
          $text = $mailing['content'];
          $subject = $mailing['title'];
          $sender = $mailing['sender'];
          if (!empty($mailing['medias'])) {
            foreach ($mailing['medias'] as $a){
              if (!empty($a['file']) && file_exists($a['file'])) {
                $att[] = $a['file'];
              }
            }
          }
        }
        else{
          $text = $r['text'];
          $subject = $r['subject'];
          $sender = null;
          if ($r['cfg']) {
            $r['cfg'] = json_decode($r['cfg'], true);
            if (!empty($r['cfg']['attachments'])) {
              foreach ($r['cfg']['attachments'] as $a){
                $f = \bbn\X::indexOf($a, '/') === 0 ? $a : \bbn\Mvc::getContentPath().$a;
                if (file_exists($f)) {
                  $att[] = $f;
                }
              }
            }
          }
        }
        if ($subject && $text && \bbn\Str::isEmail($r['email'])) {
          $params = [
            'to' => $r['email'],
            'subject' => $subject,
            'text' => $text
          ];
          if (count($att)) {
            $params['attachments'] = $att;
          }
          if ($ok = $this->send($params, $sender)) {
            $successes++;
          }
        }
        $this->db->update(
          'bbn_emails', [
          'status' => $ok ? 'success' : 'failure',
          'delivery' => date('Y-m-d H:i:s')
          ], ['id' => $r['id']]
        );
      }
      foreach ($mailings as $id => $m) {
        if (($m['state'] === 'sending')
            && !$this->db->count(
              'bbn_emails', [
              'id_mailing' => $id,
              'status'=> 'ready'
              ]
            )
        ) {
          $this->changeState($id, 'sent');
        }
      }
      return $successes;
    }
    $this->setError(X::_("No mailer defined"));
    return null;
  }

  public function send(array $cfg, string $sender = null): bool
  {
    if (!empty($cfg['to']) && ($mailer = $this->_get_mailer($sender))) {
      return $mailer->send($cfg);
    }
    return false;
  }

  /**
   * Adds a new mailing and returns its ID.
   *
   * @param array $cfg
   * @return null|string
   */
  public function add(array $cfg): ?array
  {
    $notes = $this->_note();
    if ($this->check()
        && $notes
        && bbn\X::hasProps($cfg, ['title', 'content', 'sender'], true)
        && ($id_type = Note::getOptionId('mailings','types'))
        && ($id_note = $notes->insert($cfg['title'], $cfg['content'], $id_type))
        // Cannot give a date if no recipients selected
        && (!empty($cfg['recipients']) || empty($cfg['sent']))
    ) {
      if (empty($cfg['sent'])) {
        $cfg['sent'] = null;
      }
      if ($this->db->insert(
        'bbn_emailings', [
        'id_note' => $id_note,
        'version' => 1,
        'sender' => $cfg['sender'],
        'recipients' => $cfg['recipients'] ?: null,
        'sent' => $cfg['sent']
        ]
      )
      ) {
        $cfg['id'] = $this->db->lastId();
        $cfg['id_note'] = $id_note;
        $cfg['version'] = 1;
        if (!empty($cfg['attachments'])) {
          foreach ($cfg['attachments'] as $f){
            if (is_array($f)) {
              $notes->addMediaToNote($f['id_media'], $id_note, 1);
            }
            elseif (is_file($f)) {
              // Add media
              $notes->addMedia($id_note, $f);
            }
          }
        }
        if (bbn\X::hasProps($cfg, ['recipients', 'sent', 'emails'], true)) {
          $cfg['res'] = $this->insertEmails($cfg['id'], $cfg['sent'], $cfg['emails'], $cfg['priority'] ?? 5);
        }
        return $cfg;
      }
    }
    return null;
  }

  public function insertEmails(string $id_mailing, string $date, array $emails, int $priority = 5): ?array
  {
    if (!empty($date) && \bbn\Date::validateSQL($date)) {
      $res = [];
      foreach ($emails as $item) {
        if ($this->db->insertIgnore(
          'bbn_emails', [
            'email' => $item['email'],
            'id_mailing' => $id_mailing,
            'priority' => $priority,
            'status' => 'ready',
            'delivery' => $date
          ]
        )
        ) {
          $item['id'] = $this->db->lastId();
          $res[] = $item;
        }
      }
      return $res;
    }
    return null;
  }

  public function edit($id, $cfg): ?array
  {
    $notes = $this->_note();
    $user = bbn\User::getInstance();
    $medias = $this->_medias();
    $res = 0;
    
    if ($this->check() 
        && $user && $notes 
        && bbn\X::hasProps($cfg, ['title', 'content', 'sender']) 
        && ($mailing = $this->getMailing($id))
    ) {
      $cfg['id'] = $id;
      if ($this->countSent($id)) {
        throw new Error(X::_("Impossible to edit a message already sent or partially sent, you need to duplicate it."));
      }
      $version = $mailing['version'];
      if (($cfg['title'] !== $mailing['title']) || ($cfg['content'] !== $mailing['content'])) {
        $version = $notes->insertVersion($mailing['id_note'], $cfg['title'], $cfg['content']);
      }
      $this->db->update(
        'bbn_emailings', [
        'version' => $version,
        'sender' => $cfg['sender'],
        'recipients' => $cfg['recipients'] ?: null,
        'sent' => $cfg['sent']
        ], [
        'id' => $id
        ]
      );
      foreach ($cfg['attachments'] as $f){
        // It exists already, the file is not sent
        if (is_array($f)) {
          $idx = empty($mailing['medias']) ? false : \bbn\X::find($mailing['medias'], ['name' => $f['name']]);
          if ($idx !== null) {
            if ($version === $mailing['version']) {
              // If file found in attachments when note is not modified, it is removed from the original array which can then be used for deleting all remaining attachments
              array_splice($mailing['medias'], $idx, 1);
            }
            elseif ($notes->addMediaToNote($mailing['medias'][$idx]['id'], $mailing['id_note'], $version)) {
              $res++;
            }
          }
        }
        // The pure path to the file is sent
        elseif ($notes->addMedia($mailing['id_note'], $f)) {
          $res++;
        }
      }
      if ($version === $mailing['version']) {
        foreach ($mailing['medias'] as $med) {
          if ($medias->delete($med['id'])) {
            $res++;
          }
        }
      }
      else{
        $res++;
      }
      if (!$cfg['sent'] || ($mailing['recipients'] !== $cfg['recipients'])) {
        $this->deleteAllEmails($id);
      }
      if (bbn\X::hasProps($cfg, ['recipients', 'sent', 'emails'], true)) {
        $cfg['res'] = $this->insertEmails($cfg['id'], $cfg['sent'], $cfg['emails'], $cfg['priority'] ?? 5);
      }
      return $cfg;
    }
    return null;
  }
  /**
   * Deletes the mailing and relative emails.
   *
   * @param string $id 
   * @return integer|null
   */
  public function delete(string $id):? int
  {
    // We do not delete mailings which have been sent
    if ($this->countSent($id)) {
      //return 0;
    }
    $success = null;
    $mailing = $this->getMailing($id);
    if (!empty($mailing['id_note'])) {
      $notes = $this->_note();
      //if the notes has media removes media before to remove the note
      if ($medias = $notes->getMedias($mailing['id_note'])) {
        foreach ($medias as $media){
          $notes->removeMedia($media['id'], $mailing['id_note']);
        }
      }

      // if there are emails with the given id_mailing
      if ($this->db->count('bbn_emails', ['id_mailing' => $id])) {
        //it removes the emails ready or cancelled relative to this id_mailing
        $this->deleteAllEmails($id);
      }
      if (!$this->db->count('bbn_emails', ['id_mailing' => $id])) {
        //deletes the row
        $success = $this->db->delete("bbn_emailings", ['id' => $id]);
        //$notes->remove($mailing['id_note']);
      }
    }
    return $success;
  }

  /**
   * Deletes a sent mailing. If $history is true completely delete the row from history.
   *
   * @param string  $id
   * @param boolean $history
   * @return integer|null
   */
  public function deleteSent(string $id, $history = false):? int
  {
    $success = false;
    if ($mailing = $this->getMailing($id)) {
      if (!empty($mailing['id_note']) && ($mailing['state'] === 'sent')) {
        if (!empty($history)) {
          $notes = $this->_note();
          if (($medias = $notes->getMedias($mailing['id_note']) )) {
            foreach ($medias as $media){
              $notes->removeMedia($media['id'],$mailing['id_note']);
            }
          }
          $notes->remove($mailing['id_note']);  
          $success = $this->db->delete('bbn_$_uids', ['bbn_uid' => $id]);
        }
        else {
          $success = $this->db->delete('bbn_emailings', ['id' => $id]);
        }
      }
      
    }
    
    return $success;
  }

  /**
   * Undocumented function
   *
   * @param string $id_email
   * @return integer|null
   */
  public function deleteEmail(string $id_email):? int
  {
    if (!empty($id_email)) {
      return $this->db->delete('bbn_emails', ['id' => $id_email]);
    }
  }

  /**
   * Deletes all the emails ready or cancelled relative to the given id_mailing.
   *
   * @param string $id_mailing
   * @return integer|null
   */
  public function deleteAllEmails(string $id_mailing):? int
  {
    $success = null;
    $emails = $this->db->rselectAll('bbn_emails', [], ['id_mailing' => $id_mailing]);
    if (!empty($emails)) {
      $n = 0;
      foreach ($emails as $e){
        if (($e['status'] === 'ready') || ($e['status'] === 'cancelled')) {
          if ($this->db->delete('bbn_emails', ['id' => $e['id']])) {
            $n++;
          }
        }
      }
      $success = $n;
    }
    return $success;
  }


  /**
   * Changes the status of the given id email.
   *
   * @param string $id_email
   * @param string $state
   * @return bool
   */
  public function changeEmailStatus(string $id_email, string $state): bool
  {
    return $this->db->update(
      'bbn_emails', ['status' => $state], [
      'id' => $id_email,
      ]
    );
  }

  /**
   * Returns the array containing all the emails relative to an id_mailing.
   *
   * @param string $id
   * @return array
   */
  public function getEmails(string $id):? array
  {
    return $this->db->rselectAll(
      'bbn_emails', [], [
      'id_mailing' => $id
      ]
    );
  }

  /**
   * Returns the number of emails sent for the given mailing.
   *
   * @param string $id
   * @return int|null
   */
  public function countSent(string $id): ?int
  {
    if ($this->check()) {
      return $this->db->count(
        'bbn_emails', [
        'id_mailing' => $id,
        ['status', '!=', 'ready'],
        ['email', '!=', $this->getTestEmails()]
        ]
      );
    }
    return null;
  }

  /**
   * Changes the status of the emails relative to the given mailing.
   *
   * @param string $id
   * @param string $status
   * @return boolean
   */
  public function changeEmailsStatus(string $id_mailing, string $status): bool
  { 
    $count = 0;
    if (($emails = $this->getEmails($id_mailing))) {
      foreach ($emails as $e){
        //here I've to check if ready or cancelled??
        if ($this->changeEmailStatus($e['id'], $status)) {
          $count ++;
        };
      }
      return $count;
    }
  } 

  /**
   * Copies the email 
   *
   * @param string $id
   * @return string|null
   */
  public function copy(string $id):? string
  {
    if ($row = $this->getMailing($id)) {
      $id_mailing = $this->add(
        [
        'title' => $row['title'],
        'content' => $row['content'],
        'sender' => $row['sender'],
        'recipients' => $row['recipients']
        ]
      );
      if (!empty($row['medias']) && ($row2 = $this->getMailing($id_mailing))) {
        foreach ($row['medias'] as $r){
          $this->notes->addMediaToNote($r['id'], $row2['id_note'], 1);
        }
      }
      return $id_mailing;
    }
  }

  /**
   * Get the last mailings rows which aree not sending
   *
   * @param int $limit
   * @return array
   */
  public function getLasts(int $limit = 10)
  {
    return $this->db->rselectAll(
      [
      'table' => 'bbn_emailings',
      'fields' => ['id', 'title', 'sent', 'state'],
      'join' => [
        [
          'table' => 'bbn_notes_versions',
          'on' => [
            'conditions' => [
              [
                'field' => 'bbn_notes_versions.id_note',
                'exp' => 'bbn_emailings.id_note'
              ], [
                'field' => 'bbn_notes_versions.version',
                'exp' => 'bbn_emailings.version'
              ]
            ]
          ]
        ]
      ],
      'where' => [
        ['state', '!=', 'sending'],
        ['sent', 'isnotnull'],
        ['sent', '<', Date('Y-m-d H:i:s')]
      ],
      'order' => [
        'sent' => 'DESC'
      ],
      'limit' => $limit
      ]
    );
  }

  /**
   * Get the next mailings rows to be sent
   *
   * @param int $limit
   * @return array
   */
  public function getNexts(int $limit = 10)
  {
    return $this->db->rselectAll(
      [
        'table' => 'bbn_emailings',
        'fields' => ['id', 'title', 'sent', 'state'],
        'join' => [
          [
            'table' => 'bbn_notes_versions',
            'on' => [
              'conditions' => [
                [
                  'field' => 'bbn_notes_versions.id_note',
                  'exp' => 'bbn_emailings.id_note'
                ], [
                  'field' => 'bbn_notes_versions.version',
                  'exp' => 'bbn_emailings.version'
                ]
              ]
            ]
          ]
        ],
        'where' => [
          ['state', '=', 'ready'],
          ['sent', 'isnotnull'],
        ],
        'order' => [
          'sent' => 'ASC'
        ],
        'limit' => $limit
      ]
    );
  }

  /**
   * Get the next mailings rows to be sent
   *
   * @param int $limit
   * @return array
   */
  public function getSendings()
  {
    return $this->db->rselectAll(
      [
        'table' => 'bbn_emailings',
        'fields' => ['id', 'title', 'sent', 'state'],
        'join' => [
          [
            'table' => 'bbn_notes_versions',
            'on' => [
              'conditions' => [
                [
                  'field' => 'bbn_notes_versions.id_note',
                  'exp' => 'bbn_emailings.id_note'
                ], [
                  'field' => 'bbn_notes_versions.version',
                  'exp' => 'bbn_emailings.version'
                ]
              ]
            ]
          ]
        ],
        'where' => [
          ['state', '=', 'sending']
        ],
        'order' => [
          'sent' => 'DESC'
        ]
      ]
    );
  }
}

<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 15/06/2018
 * Time: 12:02
 */

namespace bbn\Appui;

use Exception;
use bbn\X;
use bbn\Str;
use bbn\Db;
use bbn\Mvc;
use bbn\Mail;
use bbn\Date;
use bbn\User;
use bbn\Models\Tts\Optional;
use bbn\Models\Tts\Dbconfig;
use bbn\Models\Cls\Db as ClassDb;

class Mailing extends ClassDb
{
  use Optional;
  use Dbconfig;

  private $_test_emails;

  private static $_cfgs;

  private static $_mailers = [];

  protected $notes;
  protected $medias;
  protected $mailer;

  /** @var array */
  protected static $default_class_cfg = [
    'table' => 'bbn_emailings',
    'tables' => [
      'emailings' => 'bbn_emailings',
      'emails' => 'bbn_emails'
    ],
    'arch' => [
      'emailings' => [
        'id' => 'id',
        'id_note' => 'id_note',
        'version' => 'version',
        'state' => 'state',
        'sender' => 'sender',
        'recipients' => 'recipients',
        'sent' => 'sent'
      ],
      'emails' => [
        'id' => 'id',
        'email' => 'email',
        'id_mailing' => 'id_mailing',
        'subject' => 'subject',
        'text' => 'text',
        'cfg' => 'cfg',
        'status' => 'status',
        'delivery' => 'delivery',
        'read' => 'read',
        'priority' => 'priority'
      ]
    ]
  ];

  public function __construct(Db $db, array $cfg = null)
  {
    if ($db->check()) {
      self::optionalInit();
      $this->_init_class_cfg();
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
    $cfg = $this->class_cfg['arch']['emailings'];
    $table = $this->class_cfg['tables']['emailings'];
    $cond = [$cfg['state'] => 'sending'];
    if ($id) {
      $cond[$cfg['id']] = $id;
    }
    return $this->db->count($table, $cond) > 0;
  }

  /**
   * Checks if the given mailing is in suspended state.
   *
   * @param string $id
   * @return boolean
   */
  public function isSuspended(string $id = null): bool
  {
    $cfg = $this->class_cfg['arch']['emailings'];
    $table = $this->class_cfg['tables']['emailings'];
    $cond = [$cfg['state'] => 'suspended'];
    if ($id) {
      $cond[$cfg['id']] = $id;
    }
    return $this->db->count($table, $cond) > 0;
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
    $cfg = $this->class_cfg['arch']['emailings'];
    $table = $this->class_cfg['tables']['emailings'];
    if ($this->check() 
        && ($id = $this->db->selectOne(
          $table, $cfg['id'], [
          [$cfg['state'], 'LIKE', 'ready'],
          [$cfg['sent'], '<', Date('Y-m-d H:i:s')],
          [$cfg['sent'], 'isnotnull']
          ], [$cfg['sent'] => 'ASC']
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
    $cfg = $this->class_cfg['arch']['emailings'];
    $table = $this->class_cfg['tables']['emailings'];
    if ($this->check()) {
      $cur = $this->db->selectOne($table, $cfg['state'], ['id' => $id]);
      if (($cur === 'sent') || ($cur === 'cancelled')) {
        return false;
      }

      return (bool)$this->db->update($table, [$cfg['state'] => $new_state], [$cfg['id'] => $id]);
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
    $cfg = $this->class_cfg['arch']['emailings'];
    $table = $this->class_cfg['tables']['emailings'];
    if ($this->check() && ($row = $this->db->select($table, [$cfg['id_note'], $cfg['version']], [$cfg['id'] => $id]))) {
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
      $cfg = $this->class_cfg['arch']['emailings'];
      $table = $this->class_cfg['tables']['emailings'];
      $notes = $this->_note();
      if (($row = $this->db->rselect($table, [], [$cfg['id'] => $id])) 
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
      $cfgEmailings = $this->class_cfg['arch']['emailings'];
      $tableEmailings = $this->class_cfg['tables']['emailings'];
      $cfgEmails = $this->class_cfg['arch']['emails'];
      $tableEmails = $this->class_cfg['tables']['emails'];
      $sent = 0;
      $successes = 0;
      $mailings = [];
      $cfg = [
        'table' => $tableEmails,
        'fields' => [
          'id' => $tableEmails . '.' . $cfgEmails['id'],
          'email' => $cfgEmails['email'],
          'id_mailing' => $cfgEmails['id_mailing'],
          'subject' => $cfgEmails['subject'],
          'text' => $cfgEmails['text'],
          'cfg' => $cfgEmails['cfg'],
          'status' => $cfgEmails['status'],
          'delivery' => $cfgEmails['delivery'],
          'read' => $cfgEmails['read'],
          'priority' => $cfgEmails['priority']
        ],
        'join' => [[
          'table' => $tableEmailings,
          'type' => 'left',
          'on' => [
            'conditions' => [
              [
                'field' => $cfgEmails['id_mailing'],
                'exp' => $tableEmailings . '.' . $cfgEmailings['id']
              ]
            ]
          ]
        ]],
        'where' => [
          'conditions' => [[
            'field' => $cfgEmails['status'],
            'value' => 'ready'
          ], [
            'logic' => 'OR',
            'conditions' => [[
              'field' => $cfgEmails['delivery'],
              'operator' => 'isnull'
            ], [
              'field' => $cfgEmails['delivery'],
              'operator' => '<',
              'exp' => 'NOW()'
            ]]
          ], [
            'logic' => 'OR',
            'conditions' => [[
              'field' => $tableEmailings . '.' . $cfgEmailings['state'],
              'operator' => 'isnull'
            ], [
              'conditions' => [[
                'field' => $tableEmailings . '.' . $cfgEmailings['sent'],
                'operator' => '<=',
                'exp' => 'NOW()'
              ], [
                'logic' => 'OR',
                'conditions' => [[
                  'field' => $tableEmailings . '.' . $cfgEmailings['state'],
                  'value' => 'ready'
                ], [
                  'field' => $tableEmailings . '.' . $cfgEmailings['state'],
                  'value' => 'sending'
                ]]
              ]]
            ]]
          ]]
        ],
        'order' => [$cfgEmails['priority']],
        'limit' => $limit
      ];

      foreach ($this->db->rselectAll($cfg) as $r) {
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
              foreach ($r['cfg']['attachments'] as $filename => $a){
                $f = X::indexOf($a, '/') === 0 ? $a : Mvc::getContentPath().$a;
                if (file_exists($f)) {
                  if (!empty($filename) && is_string($filename)) {
                    $att[$filename] = $f;
                  }
                  else {
                    $att[] = $f;
                  }
                }
              }
            }
          }
        }

        if ($subject && $text && Str::isEmail($r['email'])) {
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
          $tableEmails, [
          $cfgEmails['status'] => $ok ? 'success' : 'failure',
          $cfgEmails['delivery'] => date('Y-m-d H:i:s')
          ], [$cfgEmails['id'] => $r['id']]
        );
      }

      foreach ($mailings as $id => $m) {
        if (($m['state'] === 'sending')
            && !$this->db->count(
              $tableEmails, [
                $cfgEmails['id_mailing'] => $id,
                $cfgEmails['status'] => 'ready'
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
        && X::hasProps($cfg, ['title', 'content', 'sender'], true)
        && ($id_type = Note::getOptionId('mailings','types'))
        && ($id_note = $notes->insert($cfg['title'], $cfg['content'], $id_type))
        // Cannot give a date if no recipients selected
        && (!empty($cfg['recipients']) || empty($cfg['sent']))
    ) {
      if (empty($cfg['sent'])) {
        $cfg['sent'] = null;
      }

      $cfgEmailings = $this->class_cfg['arch']['emailings'];
      $tableEmailings = $this->class_cfg['tables']['emailings'];
      if ($this->db->insert(
        $tableEmailings, [
          $cfgEmailings['id_note'] => $id_note,
          $cfgEmailings['version'] => 1,
          $cfgEmailings['sender'] => $cfg['sender'],
          $cfgEmailings['recipients'] => $cfg['recipients'] ?: null,
          $cfgEmailings['sent'] => $cfg['sent']
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
        if (X::hasProps($cfg, ['recipients', 'sent', 'emails'], true)) {
          $cfg['res'] = $this->insertEmails($cfg['id'], $cfg['sent'], $cfg['emails'], $cfg['priority'] ?? 5);
        }
        return $cfg;
      }
    }
    return null;
  }

  public function insertEmail(string $to, string $subject, string $text, array $cfg = []): bool
  {
    if (Str::isEmail($to)) {
      $cfgEmails = $this->class_cfg['arch']['emails'];
      $tableEmails = $this->class_cfg['tables']['emails'];
      if ($this->db->insert(
        $tableEmails, [
          $cfgEmails['email'] => $to,
          $cfgEmails['subject'] => $subject,
          $cfgEmails['text'] => $text,
          $cfgEmails['cfg'] => $cfg ? json_encode($cfg) : null
        ])
      ) {
        return true;
      }
    }

    return false;
  }

  public function insertEmails(string $id_mailing, string $date, array $emails, int $priority = 5): ?array
  {
    if (!empty($date) && Date::validateSQL($date)) {
      $res = [];
      $cfgEmails = $this->class_cfg['arch']['emails'];
      $tableEmails = $this->class_cfg['tables']['emails'];
      foreach ($emails as $item) {
        if ($itemID = $this->db->selectOne([
          'table' => $tableEmails,
          'fields' => [$cfgEmails['id']],
          'where' => [[
            'field' => $cfgEmails['email'],
            'value' => $item['email']
          ], [
            'field' => $cfgEmails['id_mailing'],
            'value' => $id_mailing
          ], [
            'field' => $cfgEmails['status'],
            'operator' => '!=',
            'value' => 'success'
          ]]
        ])) {
          if ($this->db->update($tableEmails, [
            $cfgEmails['priority'] => $priority,
            $cfgEmails['status'] => 'ready',
            $cfgEmails['delivery'] => $date
          ], [$cfgEmails['id'] => $itemID])) {
            $item['id'] = $itemID;
            $res[] = $item;
          }
        }
        else if ($this->db->insertIgnore(
          $tableEmails, [
            $cfgEmails['email'] => $item['email'],
            $cfgEmails['id_mailing'] => $id_mailing,
            $cfgEmails['priority'] => $priority,
            $cfgEmails['status'] => 'ready',
            $cfgEmails['delivery'] => $date
          ]
        )) {
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
    $user = User::getInstance();
    $medias = $this->_medias();
    $res = 0;
    
    if ($this->check() 
        && $user && $notes 
        && X::hasProps($cfg, ['title', 'content', 'sender']) 
        && ($mailing = $this->getMailing($id))
    ) {
      $cfg['id'] = $id;
      if ($this->countSent($id)) {
        throw new Exception(X::_("Impossible to edit a message already sent or partially sent, you need to duplicate it."));
      }
      $version = $mailing['version'];
      if (($cfg['title'] !== $mailing['title']) || ($cfg['content'] !== $mailing['content'])) {
        $version = $notes->insertVersion($mailing['id_note'], $cfg['title'], $cfg['content']);
      }
      $cfgEmailings = $this->class_cfg['arch']['emailings'];
      $tableEmailings = $this->class_cfg['tables']['emailings'];
      $this->db->update(
        $tableEmailings, [
          $cfgEmailings['version'] => $version,
          $cfgEmailings['sender'] => $cfg['sender'],
          $cfgEmailings['recipients'] => $cfg['recipients'] ?: null,
          $cfgEmailings['sent'] => $cfg['sent']
        ], [
          $cfgEmailings['id'] => $id
        ]
      );
      foreach ($cfg['attachments'] as $f){
        // It exists already, the file is not sent
        if (is_array($f)) {
          $idx = empty($mailing['medias']) ? false : X::find($mailing['medias'], ['name' => $f['name']]);
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
      if (!empty($mailing['medias']) && ($version === $mailing['version'])) {
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
      if (X::hasProps($cfg, ['recipients', 'sent', 'emails'], true)) {
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
    $success = null;
    $mailing = $this->getMailing($id);
    if (!empty($mailing['id_note'])) {
      $cfgEmailings = $this->class_cfg['arch']['emailings'];
      $tableEmailings = $this->class_cfg['tables']['emailings'];
      $cfgEmails = $this->class_cfg['arch']['emails'];
      $tableEmails = $this->class_cfg['tables']['emails'];
      $notes = $this->_note();
      //if the notes has media removes media before to remove the note
      if ($medias = $notes->getMedias($mailing['id_note'])) {
        foreach ($medias as $media){
          $notes->removeMedia($media['id'], $mailing['id_note']);
        }
      }

      // if there are emails with the given id_mailing
      if ($this->db->count($tableEmails, [$cfgEmails['id_mailing'] => $id])) {
        //it removes the emails ready or cancelled relative to this id_mailing
        $this->deleteAllEmails($id);
      }

      if (!$this->db->count($tableEmails, [$cfgEmails['id_mailing'] => $id])) {
        //deletes the row
        $success = $this->db->delete($tableEmailings, [$cfgEmailings['id'] => $id]);
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
          if (History::isEnabled()) {
            $success = $this->db->delete('bbn_history_uids', ['bbn_uid' => $id]);
          }
        }

        $cfgEmailings = $this->class_cfg['arch']['emailings'];
        $tableEmailings = $this->class_cfg['tables']['emailings'];
        if (!$success) {
          $success = $this->db->delete($tableEmailings, [$cfgEmailings['id'] => $id]);
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
      $cfgEmails = $this->class_cfg['arch']['emails'];
      $tableEmails = $this->class_cfg['tables']['emails'];
      return $this->db->delete($tableEmails, [$cfgEmails['id'] => $id_email]);
    }

    return 0;
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
    $cfgEmails = $this->class_cfg['arch']['emails'];
    $tableEmails = $this->class_cfg['tables']['emails'];
    $emails = $this->db->rselectAll($tableEmails, [], [$cfgEmails['id_mailing'] => $id_mailing]);
    if (!empty($emails)) {
      $n = 0;
      foreach ($emails as $e){
        if (($e[$cfgEmails['status']] === 'ready') || ($e[$cfgEmails['status']] === 'cancelled')) {
          if ($this->db->delete($tableEmails, [$cfgEmails['id'] => $e[$cfgEmails['id']]])) {
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
    $cfgEmails = $this->class_cfg['arch']['emails'];
    $tableEmails = $this->class_cfg['tables']['emails'];
    return $this->db->update(
      $tableEmails, [$cfgEmails['status'] => $state], [
        $cfgEmails['id'] => $id_email,
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
    $cfgEmails = $this->class_cfg['arch']['emails'];
    $tableEmails = $this->class_cfg['tables']['emails'];
    return $this->db->rselectAll(
      $tableEmails, $cfgEmails, [
        $cfgEmails['id_mailing'] => $id
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
    $cfgEmails = $this->class_cfg['arch']['emails'];
    $tableEmails = $this->class_cfg['tables']['emails'];
    if ($this->check()) {
      return $this->db->count(
        $tableEmails, [
          $cfgEmails['id_mailing'] => $id,
          [$cfgEmails['status'], '!=', 'ready'],
          [$cfgEmails['email'], '!=', $this->getTestEmails()]
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
          $count++;
        };
      }
    }

    return $count;
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
    $cfgEmailings = $this->class_cfg['arch']['emailings'];
    $tableEmailings = $this->class_cfg['tables']['emailings'];
    return $this->db->rselectAll(
      [
      'table' => $tableEmailings,
      'fields' => [
        'id' => $cfgEmailings['id'],
        'title' => $cfgEmailings['title'],
        'sent' => $cfgEmailings['sent'],
        'state' => $cfgEmailings['state']
      ],
      'join' => [
        [
          'table' => 'bbn_notes_versions',
          'on' => [
            'conditions' => [
              [
                'field' => 'bbn_notes_versions.id_note',
                'exp' => $tableEmailings . '.' . $cfgEmailings['id_note']
              ], [
                'field' => 'bbn_notes_versions.version',
                'exp' => $tableEmailings . '.' . $cfgEmailings['version']
              ]
            ]
          ]
        ]
      ],
      'where' => [
        [$cfgEmailings['state'], '!=', 'sending'],
        [$cfgEmailings['sent'], 'isnotnull'],
        [$cfgEmailings['sent'], '<', Date('Y-m-d H:i:s')]
      ],
      'order' => [
        $cfgEmailings['sent'] => 'DESC'
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
    $cfgEmailings = $this->class_cfg['arch']['emailings'];
    $tableEmailings = $this->class_cfg['tables']['emailings'];
    return $this->db->rselectAll(
      [
        'table' => $tableEmailings,
        'fields' => [
          'id' => $cfgEmailings['id'],
          'title' => $cfgEmailings['title'],
          'sent' => $cfgEmailings['sent'],
          'state' => $cfgEmailings['state']
        ],
        'join' => [
          [
            'table' => 'bbn_notes_versions',
            'on' => [
              'conditions' => [
                [
                  'field' => 'bbn_notes_versions.id_note',
                  'exp' => $tableEmailings . '.' . $cfgEmailings['id_note']
                ], [
                  'field' => 'bbn_notes_versions.version',
                  'exp' => $tableEmailings . '.' . $cfgEmailings['version']
                ]
              ]
            ]
          ]
        ],
        'where' => [
          [$cfgEmailings['state'], '=', 'ready'],
          [$cfgEmailings['sent'], 'isnotnull'],
        ],
        'order' => [
          $cfgEmailings['sent'] => 'ASC'
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
    $cfgEmailings = $this->class_cfg['arch']['emailings'];
    $tableEmailings = $this->class_cfg['tables']['emailings'];
    return $this->db->rselectAll(
      [
        'table' => $tableEmailings,
        'fields' => [
          'id' => $cfgEmailings['id'],
          'title' => $cfgEmailings['title'],
          'sent' => $cfgEmailings['sent'],
          'state' => $cfgEmailings['state']
        ],
        'join' => [
          [
            'table' => 'bbn_notes_versions',
            'on' => [
              'conditions' => [
                [
                  'field' => 'bbn_notes_versions.id_note',
                  'exp' => $tableEmailings . '.' . $cfgEmailings['id_note']
                ], [
                  'field' => 'bbn_notes_versions.version',
                  'exp' => $tableEmailings . '.' . $cfgEmailings['version']
                ]
              ]
            ]
          ]
        ],
        'where' => [
          [$cfgEmailings['state'], '=', 'sending']
        ],
        'order' => [
          $cfgEmailings['sent'] => 'DESC'
        ]
      ]
    );
  }


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
        if (X::hasProps($cfg, ['host', 'from'])) {
          self::$_cfgs[] = $cfg;
        }
      }
    }
    return self::$_cfgs;
  }

  public static function _get_cfg(string $id)
  {
    return X::getRow(
      self::_get_cfgs() ?: [],
      Str::isUid($id) ? ['id' => $id] : ['code' => $id]
    );
  }


  private static function _get_default_cfg(): ?array
  {
    if ($cfgs = self::_get_cfgs()) {
      return $cfgs[0];
    }   
    
    return null;
  }

  private static function _get_mailer(string $id = null): ?Mail
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
        self::$_mailers[$cfg['id']] = new Mail($cfg);
      }
      return self::$_mailers[$cfg['id']];
    }
    return null;
  }

}

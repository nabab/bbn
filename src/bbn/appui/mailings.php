<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 15/06/2018
 * Time: 12:02
 */

namespace bbn\appui;
use bbn;

class mailings extends bbn\models\cls\db
{
  use bbn\models\tts\optional;

  private $_test_emails;

  protected $notes;
  protected $medias;
  protected $mailer;


  /**
   * Gets a notes instance by constructing one if needed.
   *
   * @return notes
   */
  private function _note(): notes
  {
    if ( !$this->notes ){
      $this->notes = new notes($this->db);
    }
    return $this->notes;
  }

  /**
   * Gets a medias instance by constructing one if needed.
   *
   * @return notes
   */
  private function _medias(): medias
  {
    if ( !$this->medias ){
      $this->medias = new medias($this->db);
    }
    return $this->medias;
  }

  public function __construct(bbn\db $db, bbn\mail $mailer = null)
  {
    if ( $db->check() ){
      self::optional_init();
      $this->get_options_text_value('text');
      $this->db = $db;
      if ( $mailer ){
        $this->mailer = $mailer;
      }
    }
  }

  /**
   * Returns an array of email addresses used for testing purposes.
   *
   * @return array
   */
  public function get_test_emails(): array
  {
    if (!is_array($this->_test_emails)) {
      $emails = $this->get_options_text_value('test');
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
  public function is_sending(string $id = null): bool
  {
    $cond = ['state' => 'sending'];
    if ( $id ){
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
  public function is_suspended(string $id = null): bool
  {
    $cond = ['state' => 'suspended'];
    if ( $id ){
      $cond['id'] = $id;
    }
    return $this->db->count('bbn_emailings', $cond) > 0;
  }

  /*
  public function get_recipients($id_recipients): ?array
  {
    if ( $this->check() && ($opt = options::get_instance()) && ($cfg = $opt->option($id_recipients)) ){
      if ( isset($cfg['model'], $cfg['grid'], $cfg['column']) && ($m = $this->mvc->get_model($cfg['model'], $cfg['grid'])) ){
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
  public function get_next_mailing(): ?array
  {
    if (
      $this->check() &&
      ($id = $this->db->select_one('bbn_emailings', 'id', [
        ['state', 'LIKE', 'ready'],
        ['sent', '<', date('Y-m-d H:i:s')],
        ['sent', '>', 0]
      ]))
    ){
      return $this->get_mailing($id);
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
  public function change_state(string $id, string $new_state): bool
  {
    if ( $this->check() ){
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
  public function get_medias(string $id): ?array
  {
    if ($this->check() && ($row = $this->db->select('bbn_emailings', ['id_note', 'version'], ['id' => $id]))) {
      return $this->_note()->get_medias($row->id_note, $row->version);
    }
    return null;
  }

  /**
   * Returns all the information about the given mailing.
   *
   * @param string $id
   * @return array|null
   */
  public function get_mailing(string $id): ?array
  {
    if ( $this->check() ){
      $notes = $this->_note();
      if (
        ($row = $this->db->rselect('bbn_emailings', [], ['id' => $id])) &&
        ($note = $notes->get($row['id_note']))
      ){
        return bbn\x::merge_arrays($row, $note);
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
    if ($this->check() && $this->mailer) {
      $sent = 0;
      $successes = 0;
      foreach ( $this->db->rselect_all([
        'table' => 'bbn_emails',
        'fields' => [],
        'where' => [
          'conditions' => [
            [
              'field' => 'status',
              'value' => 'ready'
            ],
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
                ],
              ]
            ]
          ]
        ],
        'order' => ['priority'],
        'limit' => $limit
      ]) as $r ){
        $sent++;
        $ok = false;
        if ( !empty($r['id_mailing']) ){
          $mailing = $this->get_mailing($r['id_mailing']);
          $text = $mailing['content'];
          $subject = $mailing['title'];
        }
        else{
          $text = $r['text'];
          $subject = $r['subject'];
        }
        if ( $subject && $text && \bbn\str::is_email($r['email']) ){
          $params = [
            'to' => $r['email'],
            'subject' => $subject,
            'text' => $text
          ];
          if ( $r['cfg'] ){
            $r['cfg'] = json_decode($r['cfg'], true);
            if ( !empty($r['cfg']['attachments']) ){
              $att = [];
              foreach ( $r['cfg']['attachments'] as $i => $a ){
                /** @todo Check out the path! */
                /*
                if ( file_exists($ctrl->content_path().$a) ){
                  $att[$i] = $ctrl->content_path().$a;
                }
                */
              }
              if ( count($att) ){
                $params['attachments'] = $att;
              }
            }
          }
          if ( $ok = $this->mailer->send($params) ){
            $successes++;
          }
        }
        $this->db->update('bbn_emails', [
          'status' => $ok ? 'success' : 'failure',
          'delivery' => date('Y-m-d H:i:s')
        ], ['id' => $r['id']]);
      }
      return $successes;
    }
    $this->set_error(_("No mailer defined"));
    return null;
  }

  /**
   * Adds a new mailing and returns its ID.
   *
   * @param array $cfg
   * @return null|string
   */
  public function add(array $cfg): ?string
  {
    $notes = $this->_note();
    $res = ['success' => false];
    if (
      $this->check() &&
      $notes &&
      bbn\x::has_props($cfg, ['title', 'content', 'sender', 'recipients'], true) &&
      ($id_type = notes::get_option_id('mailings','types')) &&
      ($id_note = $notes->insert($cfg['title'], $cfg['content'], $id_type))
    ){
      if (empty($cfg['sent'])) {
        $cfg['sent'] = null;
      }
      if ($this->db->insert('bbn_emailings', [
        'id_note' => $id_note,
        'version' => 1,
        'sender' => $cfg['sender'],
        'recipients' => $cfg['recipients'] ?: null,
        'sent' => $cfg['sent']
      ])){
        $id_mailing = $this->db->last_id();
        if (!empty($cfg['attachments'])) {
          foreach ( $cfg['attachments'] as $f ){
            if (is_array($f)) {
              $notes->add_media_to_note($f['id_media'], $id_note, 1);
            }
            else if ( is_file($f) ){
              // Add media
              $notes->add_media($id_note, $f);
            }
          }
        }
        if (bbn\x::has_props($cfg, ['sent', 'emails'], true)) {
          $this->insert_emails($id_mailing, $cfg['sent'], $cfg['emails']);
        }
        return $id_mailing;
      }
    }
    return null;
  }

  public function insert_emails(string $id_mailing, string $date, array $emails, int $priority = 5): ?array
  {
    if (!empty($date) && \bbn\date::validateSQL($date)) {
      $num_undone = 0;
      $num_done = 0;
      $num_undone = 0;
      $num_emails = 0;
      $res = [];
      if (!empty($emails)) {
        foreach ($emails as $item) {
          $num_emails++;
          if ($id_email = $this->db->select_one('bbn_emails', 'id', [
            'id_mailing' => $id_mailing,
            'email' => $item['email']
          ])) {
            $num_undone++;
          }
          else {
            $num_done++;
            $this->db->insert('bbn_emails', [
              'email' => $item['email'],
              'id_mailing' => $id_mailing,
              'priority' => $priority,
              'status' => 'ready',
              'delivery' => $date
            ]);
            $id_email = $this->db->last_id();
          }
          $item['id'] = $id_email;
          $res[] = $item;
        }
      }
      return [
        'done' => $num_done,
        'undone' => $num_undone,
        'total' => $num_emails
      ];
    }
    return null;
  }

  public function edit($id, $cfg): ?int
  {
    $notes = $this->_note();
    $user = bbn\user::get_instance();
    $medias = $this->_medias();
    $res = 0;
    if (
      $this->check() &&
      $user && $notes &&
      bbn\x::has_props($cfg, ['title', 'recipients', 'content', 'sender']) &&
      ($mailing = $this->get_mailing($id)) &&
      !$this->count_sent($id) &&
      ($version = $notes->insert_version($mailing['id_note'], $cfg['title'], $cfg['content']))
    ){
      foreach ( $cfg['attachments'] as $f ){
        // It exists already, the file is not sent
        if (is_array($f)) {
          $idx = empty($mailing['medias']) ? false : \bbn\x::find($mailing['medias'], ['name' => $f['name']]);
          if ($idx !== false) {
            if ($version === $mailing['version']) {
              // If file found in attachments when note is not modified, it is removed from the original array which can then be used for deleting all remaining attachments
              array_splice($mailing['medias'], $idx, 1);
            }
            else if ($notes->add_media_to_note($mailing['medias'][$idx]['id'], $mailing['id_note'], $version)) {
              $res++;
            }
          }
        }
        // The pure path to the file is sent
        else if ($notes->add_media($mailing['id_note'], $f)) {
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
      if ( empty($cfg['sent']) ){
        $cfg['sent'] = null;
      }
      if ($mailing['sent'] && !$cfg['sent']) {
        $this->delete_all_emails($id);
      }
      else if (!$mailing['sent'] && $cfg['sent']) {
        if (!empty($cfg['emails'])) {
          $this->insert_emails($id, $cfg['sent'], $cfg['emails'], $cfg['priority']);
        }
      }
      else if ($mailing['sent'] !== $cfg['sent']) {
        $this->delete_all_emails($id);
        if (!empty($cfg['emails'])) {
          $this->insert_emails($id, $cfg['sent'], $cfg['emails'], $cfg['priority']);
        }
      }
      //if ($mailing['priority'] !== )
      $res += (int)$this->db->update('bbn_emailings', [
        'version' => $version,
        'sender' => $cfg['sender'],
        'recipients' => $cfg['recipients'],
        'sent' => $cfg['sent']
      ], [
        'id' => $id
      ]);
      return $res;
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
    if ($this->count_sent($id)) {
      return 0;
    }
    $success = null;
    $mailing = $this->get_mailing($id);
    
    if ( !empty($mailing['id_note']) ){
      $notes = $this->_note();
      //if the notes has media removes media before to remove the note
      if ($medias = $notes->get_medias($mailing['id_note'])) {
        foreach ($medias as $media){
          $notes->remove_media($media['id'], $mailing['id_note']);
        }
      }

      // if there are emails with the given id_mailing
      if ($this->db->count('bbn_emails', ['id_mailing' => $id])){
        //it removes the emails ready or cancelled relative to this id_mailing
        $this->delete_all_emails($id);
      }
      if (!$this->db->count('bbn_emails', ['id_mailing' => $id])){
        //deletes the row
        $success = $this->db->delete("bbn_emailings", ['id' => $id]);
        $notes->remove($mailing['id_note']);
      }
    }
    return $success;
  }

  /**
   * Deletes a sent mailing. If $history is true completely delete the row from history.
   *
   * @param string $id
   * @param boolean $history
   * @return integer|null
   */
  public function delete_sent(string $id, $history = false):? int
  {
    $success = false;
    if ( $mailing = $this->get_mailing($id) ){
      if ( !empty($mailing['id_note']) && ($mailing['state'] === 'sent') ){
        if ( !empty($history) ){
          $notes = $this->_note();
          if ( ($medias = $notes->get_medias($mailing['id_note']) )){
            foreach ( $medias as $media ){
              $notes->remove_media($media['id'],$mailing['id_note']);
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
  public function delete_email(string $id_email):? int
  {
    if ( !empty($id_email) ){
      return $this->db->delete('bbn_emails', ['id' => $id_email]);
    }
  }

  /**
   * Deletes all the emails ready or cancelled relative to the given id_mailing.
   *
   * @param string $id_mailing
   * @return integer|null
   */
  public function delete_all_emails(string $id_mailing):? int
  {
    $success = null;
    $emails = $this->db->rselect_all('bbn_emails', [], ['id_mailing' => $id_mailing]);
    if ( !empty($emails) ){
      $n = 0;
      foreach ( $emails as $e ){
        if ( ($e['status'] === 'ready') || ($e['status'] === 'cancelled') ){
          if (  $this->db->delete('bbn_emails', ['id' => $e['id']]) ){
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
  public function change_email_status(string $id_email, string $state): bool
  {
    return $this->db->update('bbn_emails', ['status' => $state], [
      'id' => $id_email,
    ]);
  }

  /**
   * Returns the array containing all the emails relative to an id_mailing.
   *
   * @param string $id
   * @return array
   */
  public function get_emails(string $id):? array
  {
    return $this->db->rselect_all('bbn_emails', [], [
      'id_mailing' => $id
    ]);
  }

  /**
   * Returns the number of emails sent for the given mailing.
   *
   * @param string $id
   * @return int|null
   */
  public function count_sent(string $id): ?int
  {
    if ($this->check()) {
      return $this->db->count('bbn_emails', [
        'id_mailing' => $id,
        ['email', '!=', $this->get_test_emails()]
      ]);
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
  public function change_emails_status(string $id_mailing, string $status): bool
  { 
    $count = 0;
    if ( ($emails = $this->get_emails($id_mailing)) ){
      foreach ( $emails as $e ){
        //here I've to check if ready or cancelled??
        if ( $this->change_email_status($e['id'], $status) ){
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
    if ( $row = $this->get_mailing($id) ){
      $id_mailing = $this->add([
        'title' => $row['title'],
        'content' => $row['content'],
        'sender' => $row['sender'],
        'recipients' => $row['recipients']
      ]);
      if ( !empty($row['medias']) && ($row2 = $this->get_mailing($id_mailing)) ){
        foreach ( $row['medias'] as $r ){
          $this->notes->add_media_to_note($r['id'], $row2['id_note'], 1);
        }
      }
      return $id_mailing;
    }
  }
}
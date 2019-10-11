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

  private
    $mailer,
    $notes;

  private function _note(): notes
  {
    if ( !$this->notes ){
      $this->notes = new notes($this->db);
    }
    return $this->notes;
  }

  public function __construct(bbn\db $db, bbn\mail $mailer = null)
  {
    if ( $db->check() ){
      self::optional_init();
      $this->db = $db;
      if ( $mailer ){
        $this->mailer = $mailer;
      }
    }
  }

  public function check(): bool
  {
    return $this->db && $this->db->check();
  }

  public function is_sending($id = null): bool
  {
    $cond = ['state' => 'sending'];
    if ( $id ){
      $cond['id'] = $id;
    }
    return $this->db->count('bbn_emailings', $cond) > 0;
  }

  public function is_suspended($id = null): bool
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

  public function change_state($id_mailing, $new_state): ?bool
  {
    if ( $this->check() ){
      return (bool)$this->db->update("bbn_emailings", ['state' => $new_state], ['id' => $id_mailing]);
    }
    return false;
  }

  public function get_medias($id, $version){
    $med = [];
    if ( $files = $this->_note()->get_medias($id, $version) ){
      foreach ( $files as $f ){
        $t = $f['title'] ?: $f['name'];
        if ( is_file(BBN_DATA_PATH.$f['file']) && !\array_key_exists($t, $med) ){
          $med[$t] = BBN_DATA_PATH.$f['file'];
        }
      }
    }
    return $med;
  }

  public function get_mailing($id):? array
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

  public function process(int $limit = 10, object $mailer = null){
    if (!$this->check() || !$this->mailer) {
      die("No mailer defined");
    }
    $sent = 0;
    $successes = 0;
    foreach ( $this->db->rselect_all([
      'table' => 'bbn_emails',
      'fields' => [],
      'where' => ['status' => 'ready'],
      'order' => ['priority'],
      'limit' => 30
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
  }

  /**
   * Adds a new mailing
   *
   * @param [Object] $cfg
   * @return void
   */
  public function add($cfg){
    $notes = $this->_note();
    if (
      isset($cfg['title'], $cfg['recipients'], $cfg['content'], $cfg['sender']) &&
      ($id_type = notes::get_option_id('mailings','types')) &&
      ($id_note = $notes->insert($cfg['title'], $cfg['content'], $id_type))
    ){
      if ( empty($cfg['sent']) ){
        $cfg['sent'] = null;
      }
      if ( $this->db->insert('bbn_emailings', [
        'id_note' => $id_note,
        'version' => 1,
        'sender' => $cfg['sender'],
        'recipients' => $cfg['recipients'],
        'sent' => $cfg['sent']
      ]) ){
        $id_mailing = $this->db->last_id();
        if ( !empty($cfg['attachments']) ){
          $temp_path = BBN_USER_PATH.'tmp/'.$model->data['ref'].'/';
          foreach ( $model->data['fichiers'] as $f ){
            if ( is_file($temp_path.$f['attachments']) ){
              // Add media
              $notes->add_media($id_note, $f);
            }
          }
        }
        return $id_mailing;
      }
    }
    return null;
  }

  /**
   * Deletes the mailing and relative emails
   *
   * @param string $id 
   * @return integer|null
   */
  public function delete(string $id):? int
  {
    // We do not cancel mailings which have been sent
    if ( $this->db->rselect_all('bbn_emails', [],[
      'id_mailing' => $id,
      'status' => 'sent'
    ]) ){
      return 0;
    }
    $success = null;
    $mailing = $this->get_mailing($id);
    
    if ( !empty($mailing['id_note']) ){
      $notes = $this->_note();
      //if the notes has media removes media before to remove the note
      if ( ($medias = $notes->get_medias($mailing['id_note']) )){
        foreach ( $medias as $media ){
          $notes->remove_media($media['id'],$mailing['id_note']);
        }
      }

      // if there are emails with the given id_mailing
      if ( !empty($this->db->rselect_all('bbn_emails', [],[
      'id_mailing' => $id]))){
        //it removes the emails ready or cancelled relative to this id_mailing
        $this->delete_all_emails($id);
      }
      

      //updates the row giving id_note and version null
      $this->db->update("bbn_emailings", [
        'id_note' => null, 
        'version' => null
      ], [ 'id' => $id ]);

      //deletes the row
      $success = $this->db->delete("bbn_emailings", ['id' => $id]);
      
      //removes the note -- without the second argument true I always have a db error, in this way the note is not deleted but passed on active 0
      $notes->remove($mailing['id_note']);
      
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
   * Deletes all the emails ready or cancelled relative to the given id_mailing
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
   * Changes the status of the given id email
   *
   * @param string $id_email
   * @param string $state
   * @return boolean
   */
  public function change_email_status(string $id_email, string $state): bool
  {
    return $this->db->update('bbn_emails', ['status' => $state], [
      'id' => $id_email,
    ]);
  }

  /**
   * Returns the array containing all the emails relative to an id_mailing
   *
   * @param string $id_mailing
   * @return void
   */
  public function get_emails($id_mailing):? array
  {
    return $this->db->rselect_all('bbn_emails', [],[
      'id_mailing' => $id_mailing
    ]);
  }

  /**
   * Changes the status of the emails relative to the given id_mailing
   *
   * @param string $id_mailing
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
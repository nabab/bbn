<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 15/06/2018
 * Time: 12:02
 */

namespace bbn\appui;
use bbn;

class mailings
{
  private
    $db,
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
    $cond = ['statut' => 'sending'];
    if ( $id ){
      $cond['id'] = $id;
    }
    return $this->db->count('bbn_emailings', $cond) > 0;
  }

  public function is_suspended($id = null): bool
  {
    $cond = ['statut' => 'suspended'];
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

}
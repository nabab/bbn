<?php
namespace bbn\Appui;

use bbn\Str;

class Mailbox2
{

  /**
   * @var string The host address
   */
  protected $host;

  /**
   * @var string The connection type
   */
  protected $type = 'imap';

  /**
   * @var string The login
   */
  protected $login;

  /**
   * @var string The password
   */
  protected $pass;

  /**
   * @var string The current directory
   */
  protected $directory = '';

  /**
   * @var int The remote port
   */
  protected $port;

  /**
   * @var string The mailbox parameters
   */
  protected $mbParam;

  /**
   * @var string The status of the connection (should be ok)
   */
  protected $status = '';

  /**
   * @var int The last UID in the mailbox
   */
  protected $last_uid;

  /**
   * @var int The number of messages ion the current mailbox
   */
  protected $num_msg;

  /**
   * @var stream The stream object
   */
  protected $stream;



  public function __construct(string $user, string $pass, string $type, string $host = null)
  {
    $this->host  = $host;
    $this->type  = $type;
    $this->login = $user;
    $this->pass  = $pass;
    $this->directory = '';
    $this->account   = 0;

    switch ($this->type) {
      case 'hotmail':
        $this->port    = 993;
        $this->mbParam = '{imap.live.com:' . $this->port . '/imap/ssl}';
        break;
      case 'gmail':
        $this->port    = 993;
        $this->mbParam = '{imap.googlemail.com:' . $this->port . '/imap/ssl}';
        break;
      case 'pop':
        $this->port    = 110;
        $this->mbParam = '{' . $this->host . ':' . $this->port . '/pop3}';
        break;
      case 'imap':
        $this->port    = 143;
        $this->mbParam = '{' . $this->host . ':' . $this->port . '/imap/tls/novalidate-cert}';
        break;
      case 'local':
        if ($_SERVER['REMOTE_PORT'] != 1975) {
          $this->host = 'localhost';
        }

        $this->port    = 143;
        $this->mbParam = '{' . $this->host . ':' . $this->port . '/imap/tls/novalidate-cert}';
        break;
    }

    if (isset($this->mbParam)) {
      try {
        $this->stream = @imap_open($this->mbParam . $this->directory, $this->login, $this->pass);
      }
      catch (\Exception $e) {
        //throw new \Exception($e->getMessage());
      }
      if ($this->stream) {
        $this->status = 'ok';
      }
      else {
        $this->status = imap_last_error();
      }
    }
  }

  public function getStatus(): string
  {
    return $this->status;
  }


  public function getHost(): string
  {
    return $this->host;
  }


  public function getLogin(): string
  {
    return $this->login;
  }


  public function getPort(): int
  {
    return $this->port;
  }


  public function getParams(): string
  {
    return $this->mbParam;
  }


  public function getLastUid(): int
  {
    return $this->last_uid;
  }


  public function getNumMsg(): int
  {
    return $this->num_msg;
  }


  public function getStream()
  {
    return $this->stream;
  }


  public function getImap()
  {
    $this->imap = imap_check($this->stream);
    if ($this->imap->Nmsgs > 0) {
      $this->last_uid = $this->msguid($this->imap->Nmsgs);
      $this->num_msg  = $this->imap->Nmsgs;
    }
    else {
      $this->last_uid = 0;
      $this->num_msg  = 0;
    }
  }


  // are we connected?
  public function ifok(): bool
  {
    return (bool)$this->stream;
  }


  // create a mbox
  public function crtmbox($mbox)
  {
    if (!$this->ifok()) {
      return false;
    }

    return imap_createmailbox($this->stream, $this->mbParam . $mbox);
  }


  // delete a mbox
  public function delmbox($mbox)
  {
    if (!$this->ifok()) {
      return false;
    }

    return imap_deletemailbox($this->stream, $this->mbParam . $mbox);
  }


  // rename a mbox
  public function renmbox($old, $new)
  {
    if (!$this->ifok()) {
      return false;
    }

    return imap_renamemailbox(
      $this->stream,
      $this->mbParam . $old,
      $this->mbParam . $new
    );
  }


  // list the subscribed mboxes in a dir (cyrus/courier)
  public function lstscrbed($dir)
  {
    if (!$this->ifok()) {
      return false;
    }

    return imap_listsubscribed($this->stream, $this->mbParam, $dir);
  }


  // list the mboxes in a dir
  public function lstmbox($dir = '')
  {
    if (!$this->ifok()) {
      return false;
    }

    return imap_listmailbox($this->stream, $this->mbParam, $dir);
  }


  public function getmailboxes($dir)
  {
    if (!$this->ifok()) {
      return false;
    }

    return imap_getmailboxes($this->stream, $this->mbParam, $dir);
  }


  public function getmboxes($dir)
  {
    $mboxes = imap_getmailboxes($this->stream, $this->mbParam, $dir);
    $i      = 0;
    $ret    = array();
    while (list($key, $val) = each($mboxes)) {
      $name      = imap_utf7_decode($val->name);
      $name_arr  = explode('}', $name);
      $j         = count($name_arr) - 1;
      $mbox_name = $name_arr[$j];
      if ($mbox_name == "") {continue; // the DIRECTORY itself
      }

      $ret[$i++] = $mbox_name;
    }

    sort($ret);
    return $ret;
  }


  // reopen the desired mbox (just the name of the mbox)
  public function reopbox($mbox)
  {
    if (!$this->ifok()) {
      return false;
    }

    return imap_reopen($this->stream, $this->mbParam . $mbox);
  }


  // reopen the desired mbox (full mbox name should be given as $mbox)
  public function reopbox2($mbox)
  {
    if (!$this->ifok()) {
      return false;
    }

    return imap_reopen($this->stream, $mbox);
  }


  // mailbox info
  public function mboxinfo()
  {
    if (!$this->ifok()) {
      return false;
    }

    return imap_mailboxmsginfo($this->stream);
  }


  // sort the mbox
  public function mboxsrt($criteria, $reverse)
  {
    if (!$this->ifok()) {
      return false;
    }

    return imap_sort($this->stream, $criteria, $reverse, SE_NOPREFETCH);
  }


  // retrieve the header of the message
  public function msghdr($msgnum)
  {
    if (!$this->ifok()) {
      return false;
    }

    return imap_header($this->stream, $msgnum);
  }


  // get the UID of the message
  public function msguid($msgnum)
  {
    if (!$this->ifok()) {
      return false;
    }

    return imap_uid($this->stream, $msgnum);
  }


  // get the NO of the message
  public function msgno($msguid)
  {
    if (!$this->ifok()) {
      return false;
    }

    return imap_msgno($this->stream, $msguid);
  }


  // fetch the structure
  public function ftchstr($msgnum)
  {
    if (!$this->ifok()) {
      return false;
    }

    return imap_fetchstructure($this->stream, $msgnum);
  }


  // fetch the header of the message
  public function ftchhdr($msgnum)
  {
    if (!$this->ifok()) {
      return false;
    }

    return imap_fetchheader($this->stream, $msgnum);
  }


  // delete the specified message
  public function rmmail($uid)
  {
    if (!$this->ifok()) {
      return false;
    }

    $msgno = $this->msgno($uid);
    return imap_delete($this->stream, $msgno);
  }


  // move the specifed msg to mbox B
  public function mvmail($uid, $tombox)
  {
    if (!$this->ifok()) {
      return false;
    }

    return imap_mail_move($this->stream, $uid, $tombox, CP_UID);
  }


  // expunge the mailbox
  public function expng()
  {
    if (!$this->ifok()) {
      return false;
    }

    return imap_expunge($this->stream);
  }


  // fetch the body of the message
  public function ftchbody($msgno, $part)
  {
    if (!$this->ifok()) {
      return false;
    }

    return imap_fetchbody($this->stream, $msgno, $part, NONE);
  }


  // set the flags
  public function setflg($seq, $flg, $remove = false)
  {
    if (!$this->ifok()) {
      return false;
    }

    if ($remove) {
      return imap_clearflag_full($this->stream, $seq, $flg);
    } else {
      return imap_setflag_full($this->stream, $seq, $flg);
    }
  }


  // search messages
  public function srch($q)
  {
    if (!$this->ifok()) {
      return false;
    }

    return imap_search($this->stream, $q, SE_UID);
  }


  // append to sent mail
  public function apnd($m, $b)
  {
    if (!$this->ifok()) {
      return false;
    }

    return @imap_append($this->stream, $this->mbParam . $m, $b);
  }


  public function getdecodevalue($message, $coding)
  {
    if ($coding == 0) {
      $message = imap_8bit($message);
    } elseif ($coding == 1) {
      $message = imap_8bit($message);
    } elseif ($coding == 2) {
      $message = imap_binary($message);
    } elseif ($coding == 3) {
      $message = imap_base64($message);
    } elseif ($coding == 4) {
      $message = imap_qprint($message);
    } elseif ($coding == 5) {
      $message = imap_base64($message);
    }

    return $message;
  }


  public function getmsg($mid)
  {
    // input $mbox = IMAP stream, $mid = message id
    // output all the following:
    global $charset, $htmlmsg, $plainmsg, $attachments;
    $htmlmsg     = $plainmsg     = $charset     = '';
    $attachments = array();
    bbnf_delete_dir($bbng_data_path . 'users/' . $_SESSION['bbn_user']['id'] . '/tmp_mail', false);
    // HEADER
    $h = imap_header($this->stream, $mid);
    // add code here to get date, from, to, cc, subject...
    // BODY
    $s = imap_fetchstructure($this->stream, $mid);
    if (!$s->parts) { // simple
      $this->getpart($mid, $s, 0); // pass 0 as part-number
    } else { // multipart: cycle through each part
      foreach ($s->parts as $partno0 => $p) {
        $this->getpart($mid, $p, $partno0 + 1);
      }
    }
  }


  public function getpart($mid, $p, $partno)
  {
    // $partno = '1', '2', '2.1', '2.1.3', etc for multipart, 0 if simple
    global $htmlmsg, $htmlmsg_noimg, $plainmsg, $charset, $attachments;
    // DECODE DATA
    $data = ($partno) ? imap_fetchbody($this->stream, $mid, $partno) : // multipart
    imap_body($this->stream, $mid); // simple
    // Any part may be encoded, even plain text messages, so check everything.
    if ($p->encoding == 4) {
      $data = quoted_printable_decode($data);
    } elseif ($p->encoding == 3) {
      $data = base64_decode($data);
    }

    // PARAMETERS
    // get all parameters, like charset, Filenames of attachments, etc.
    $params = array();
    if ($p->parameters) {
      foreach ($p->parameters as $x) {
        $params[strtolower($x->attribute)] = $x->value;
      }
    }

    if ($p->dparameters) {
      foreach ($p->dparameters as $x) {
        $params[strtolower($x->attribute)] = $x->value;
      }
    }

    // ATTACHMENT
    // Any part with a filename is an attachment,
    // so an attached text file (type 0) is not mistaken as the message.
    if ($params['filename'] || $params['name']) {
      // filename may be given as 'Filename' or 'Name' or both
      $filename = ($params['filename']) ? $params['filename'] : $params['name'];
      // filename may be encoded, so see imap_mime_header_decode()
      array_push($attachments, $filename);
      file_put_contents($bbng_data_path . 'users/' . $_SESSION['bbn_user']['id'] . '/tmp_mail/' . $filename, $data);
      // this is a problem if two files have same name
    }

    // TEXT
    if ($p->type == 0 && $data) {
      // Messages may be split in different parts because of inline attachments,
      // so append parts together with blank row.
      $charset = $params['charset']; // assume all parts are same charset
      if (strtolower($p->subtype) == 'plain') {
        $plainmsg .= trim(Str::toUtf8($data)) . PHP_EOL;
      }
      else {
        $htmlmsg .= trim(Str::toUtf8($data)) . '<br><br>';

        if (!empty($htmlmsg)) {
          $body_pattern = "/<body([^>]*)>(.*)<\/body>/smi";
          preg_match($body_pattern, $htmlmsg, $body);
          if (!empty($body[2])) {
            $htmlmsg = $body[2];
          }

          $img_pattern   = "/<img([^>]+)>/smi";
          $htmlmsg_noimg = preg_replace($img_pattern, '', $htmlmsg);
        }
      }
    }
    // EMBEDDED MESSAGE
    // Many bounce notifications embed the original message as type 2,
    // but AOL uses type 1 (multipart), which is not handled here.
    // There are no PHP functions to parse embedded messages,
    // so this just appends the raw source to the main message.
    elseif ($p->type == 2 && $data && strtolower($p->subtype) == 'plain') {
      $plainmsg .= trim(Str::toUtf8($data)) . PHP_EOL;
    }

    // SUBPART RECURSION
    if ($p->parts) {
      foreach ($p->parts as $partno0 => $p2) {
        $this->getpart($mid, $p2, $partno . '.' . ($partno0 + 1)); // 1.2, 1.2.1, etc.
      }
    }
  }


  public function __destruct()
  {
    if ($this->imap) {
      imap_close($this->stream);
    }
  }


}

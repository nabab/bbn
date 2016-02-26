<?php

namespace bbn\appui;

class mailbox{

  private
    // Ex globals variables
    $htmlmsg = '',
    $htmlmsg_noimg = '',
    $plainmsg = '',
    $charset = '',
    $attachments = [];

  function __construct($cfg){
    if ( is_array($cfg) ){
      $this->type = empty($cfg['type']) ? '' : $cfg['type'];
      $this->host = $cfg['host'];
      $this->login = $cfg['login'];
      $this->pass = $cfg['pass'];
      $this->directory = empty($cfg['dir']) ? '' : $cfg['dir'];

      if ( !empty($cfg['type']) ) {
        switch ( $this->type ) {
          case 'hotmail':
            $this->port = 995;
            $this->mbParam = '{pop3.live.com:' . $this->port . '/pop3/ssl}';
            break;
          case 'gmail':
            $this->port = 993;
            $this->mbParam = '{imap.googlemail.com:' . $this->port . '/imap/ssl}';
            break;
          case 'pop':
            $this->port = 110;
            $this->mbParam = '{' . $this->host . ':' . $this->port . '/pop3}';
            break;
          case 'imap':
            $this->port = 143;
            $this->mbParam = '{' . $this->host . ':' . $this->port . '/imap/tls/novalidate-cert}';
            break;
          case 'local':
            $this->host = 'localhost';
            $this->port = 143;
            $this->mbParam = '{' . $this->host . ':' . $this->port . '/imap/tls/novalidate-cert}';
            break;
        }
      }
      else if ( !empty($cfg['port']) && !empty($cfg['param']) ){
        $this->port = $cfg['port'];
        $this->mbParam = '{' . $this->host . ':' . $this->port . '/' . $cfg['param'] . '}';
      }

      if ( isset($this->mbParam) ){
        if ( $this->stream = @imap_open($this->mbParam.$this->directory, $this->login, $this->pass) ) {
          $this->status = 'ok';
        }
        else {
          $this->status = imap_last_error();
          $msg = [];
          array_push($msg,'#################################################################');
          array_push($msg,date('H:i:s d-m-Y').' - Error in the script!');
          array_push($msg,'User: '.$this->login);
          array_push($msg,'Parameters: '.$this->mbParam.$this->directory);
          array_push($msg,'#################################################################');
          array_push($msg,' ');
          array_push($msg,'Error message: ');
          array_push($msg,$this->status);
          \bbn\tools::log(implode("\n",$msg),'imap');
        }
      }
    }
  }

  /**
   * Checks if we are connected  (Test: ok)
   *
   * @return bool
   */
  private function is_connected(){
    return !empty($this->stream);
  }

  /**
   * Returns an array containing the names of the mailboxes that you have subscribed. (Test: ok)
   *
   * @param string $dir Mailbox directory
   * @return bool|array
   */
  private function list_subscribed($dir){
    if ( $this->is_connected() ){
      return imap_lsub($this->stream, $this->mbParam, $dir);
    }
    return false;
  }

  /**
   * Returns an array containing the full names of the mailboxes.  (Test: ok)
   *
   * @param string $dir Mailbox directory
   * @return bool|array
   */
  private function list_mboxes($dir){
    if ( $this->is_connected() ) {
      return imap_list($this->stream, $this->mbParam, $dir);
    }
    return false;
  }

  /**
   * Returns an array of objects containing detailed mailboxes information. (Test: ok)
   *
   * @param string $dir Mailbox directory
   * @return array|bool
   */
  private function get_mboxes($dir){
    if ( $this->is_connected() ){
      return imap_getmailboxes($this->stream, $this->mbParam, $dir);
    }
    return false;
  }

  /**
   * Returns a sorted array containing the simple names of the mailboxes. (Test: ok)
   *
   * @param string $dir Mailbox directory
   * @return array
   */
  private function get_names_mboxes($dir){
    if ( $mboxes = $this->get_mboxes($dir) ){
      $i = 0;
      $ret = [];
      while( list($key, $val) = each($mboxes) ){
        $name = imap_utf7_decode($val->name);
        $name_arr = explode('}', $name);
        $j = count($name_arr) - 1;
        $mbox_name = $name_arr[$j];
        if( $mbox_name == "" ) {
          continue; // the DIRECTORY itself
        }
        $ret[$i++] = $mbox_name;
      }
      sort($ret);
      return $ret;
    }
    return false;
  }

  /**
   * Decodes message. (Test: ok)
   *
   * @param string $message Messate to decode
   * @param int $coding Type of encoding
   * @return string
   */
  private function get_decode_value($message, $coding){
    if ( $coding === 0 ){
      $message = imap_8bit($message);
    }
    else if ( $coding === 1 ){
      $message = imap_8bit($message);
    }
    else if ( $coding === 2 ){
      $message = imap_binary($message);
    }
    else if ( $coding === 3 ){
      $message=imap_base64($message);
    }
    else if ( $coding === 4 ){
      $message = imap_qprint($message);
    }
    else if ( $coding === 5 ){
      $message = imap_base64($message);
    }
    return $message;
  }

  /**
   * Gets IMAP essential info (Test: ok)
   *
   * @return object|bool
   */
  public function get_imap(){
    if ( $this->imap = imap_check($this->stream) ){
      if ( $this->imap->Nmsgs > 0 ){
        $this->last_uid = $this->get_msg_uid($this->imap->Nmsgs);
        $this->num_msg = $this->imap->Nmsgs;
      }
      else {
        $this->last_uid = 0;
        $this->num_msg = 0;
      }
      return $this->imap;
    }
    return false;
  }

  /**
   *  Closes the imap stream.
   *
   */
  public function __destruct(){
    if ( $this->stream ){
      imap_close($this->stream);
    }
  }

  /**
   * Creates a mailbox (Test: ok)
   *
   * @param string $mbox Mailbox name
   * @return bool
   */
  public function create_mbox($mbox){
    if ( $this->is_connected() ) {
      return imap_createmailbox($this->stream, $this->mbParam. $mbox);
    }
    return false;
  }

  /**
   * Deletes a mailbox (Test: ok)
   *
   * @param string $mbox Mailbox
   * @return bool
   */
  public function delete_mbox($mbox){
    if ( $this->is_connected() ) {
      return imap_deletemailbox($this->stream, $this->mbParam . $mbox);
    }
    return false;
  }

  /**
   * Renames a mailbox (Test: ok)
   *
   * @param string $old Old mailbox name
   * @param string $new New mailbox name
   * @return bool
   */
  public function rename_mbox($old, $new){
    if ( $this->is_connected() ){
      return imap_renamemailbox($this->stream, $this->mbParam. $old, $this->mbParam. $new);
    }
    return false;
  }

  /**
   * Returns an array of all the mailboxes that you have subscribed. (Test: ok)
   *
   * @return bool|array
   */
  public function list_all_subscribed(){
    return $this->list_subscribed('*');
  }

  /**
   * Returns an array containing the names of the current level mailboxes that you have subscribed. (Test: ok)
   *
   * @param string $dir Current mailbox directory
   * @return bool|array
   */
  public function list_curlev_subscribed($dir=''){
    return $this->list_subscribed($dir . '%');
  }

  /**
   * Returns an array containing the full names of the all mailboxes.  (Test: ok)
   *
   * @return bool|array
   */
  public function list_all_mboxes(){
    return $this->list_mboxes('*');
  }

  /**
   * Returns an array containing the full names of the current level mailboxes.  (Test: ok)
   *
   * @param string $dir Current mailbox directory
   * @return bool|array
   */
  public function list_curlev_mboxes($dir=''){
    return $this->list_mboxes($dir . '%');
  }

  /**
   * Returns an array of objects for all mailboxes containing detailed mailbox information. (Test: ok)
   *
   * @return array|bool
   */
  public function get_all_mboxes(){
    return $this->get_mboxes('*');
  }

  /**
   * Returns an array of objects for each current level mailboxes containing detailed mailbox information. (Test: ok)
   *
   * @param string $dir Mailbox directory
   * @return array|bool
   */
  public function get_curlev_mboxes($dir=''){
    return $this->get_mboxes($dir . '%');
  }

  /**
   * Returns a sorted array containing the simple names of the all mailboxes. (Test: ok)
   *
   * @return array
   */
  public function get_all_names_mboxes(){
    return $this->get_names_mboxes('*');
  }

  /**
   * Returns a sorted array containing the simple names of the current level mailboxes. (Test: ok)
   *
   * @param string $dir Current mailbox directory
   * @return array
   */
  public function get_curlev_names_mboxes($dir=''){
    return $this->get_names_mboxes($dir . '%');
  }

  /**
   * Reopens the desired mailbox (you can give it the simple name or the full name). (Test: ok)
   * If the given name is not existing it opens the default inbox.
   *
   * @param string $mbox Simple/full mailbox name
   * @return bool
   */
  public function reopen_mbox($mbox){
    if ( $this->is_connected() ) {
      if ( in_array($mbox, $this->get_all_names_mboxes()) ){
        return imap_reopen($this->stream, $this->mbParam . $mbox);
      }
      else {
        return imap_reopen($this->stream, $mbox);
      }
    }
    return false;
  }

  /**
   * Returns an object containing the current mailbox info. (Test: ok)
   *
   * @return bool|object
   */
  public function get_info_mbox(){
    if ( $this->is_connected() ){
      return imap_mailboxmsginfo($this->stream);
    }
    return false;
  }

  /**
   * Sorts the mailbox. (Test: ok)
   * Criteria can be one (and only one) of the following:
   * SORTDATE - message Date
   * SORTARRIVAL - arrival date
   * SORTFROM - mailbox in first From address
   * SORTSUBJECT - message subject
   * SORTTO - mailbox in first To address
   * SORTCC - mailbox in first cc address
   * SORTSIZE - size of message in octets
   *
   * @param static $criteria Pass it without quote or double quote
   * @param string $reverse Set this to 1 for reverse sorting
   * @return array|bool
   */
  public function sort_mbox($criteria, $reverse){
    if ( $this->is_connected() ){
      return imap_sort($this->stream, $criteria, $reverse, SE_NOPREFETCH);
    }
    return false;
  }

  /**
   * Retrieves the header's message info. (Test: ok)
   *
   * @param int $msgnum
   * @return bool|object
   */
  public function get_msg_headerinfo($msgnum){
    if ( $this->is_connected() ){
      return imap_header($this->stream, $msgnum);
    }
    return false;
  }

  /**
   * Gets the UID of the message. (Test: ok)
   *
   * @param int $msgnum
   * @return bool|int
   */
  public function get_msg_uid($msgnum){
    if ( $this->is_connected() ){
      return imap_uid($this->stream, $msgnum);
    }
    return false;
  }

  /**
   * Gets the NO of the message. (Test: ok)
   *
   * @param int $msguid UID of the message
   * @return bool|int
   */
  public function get_msg_no($msguid){
    if ( $this->is_connected() ){
      return imap_msgno($this->stream, $msguid);
    }
    return false;
  }

  /**
   * Fetches the message structure. (Test: ok)
   *
   * @param int $msgnum No of the message
   * @return bool|object
   */
  public function get_msg_structure($msgnum){
    if ( $this->is_connected() ){
      return imap_fetchstructure($this->stream, $msgnum);
    }
    return false;
  }

  /**
   * Fetches the header of the message. (Test: ok)
   *
   * @param int $msgnum No of the message
   * @return bool|string
   */
  public function get_msg_header($msgnum){
    if ( $this->is_connected() ){
      return imap_fetchheader($this->stream, $msgnum);
    }
    return false;
  }

  /**
   * Mark the specified message for deletion from current mailbox. (Text: ok)
   *
   * @param int $uid UID of the message
   * @return bool
   */
  public function delete_msg($uid){
    if ( $this->is_connected() ){
      return imap_delete($this->stream, $this->get_msg_no($uid));
    }
    return false;
  }

  /**
   * Move the specified message to specified mailbox. (Test: ok)
   *
   * @param int $uid UID of the message
   * @param string $tombox Destination mailbox name
   * @return bool
   */
  public function move_msg($uid, $tombox){
    if ( $this->is_connected() ){
      return imap_mail_move($this->stream, $uid, $tombox, CP_UID);
    }
    return false;
  }

  /**
   * Deletes all messages marked for deletion by delete_msg(), move_msg() or set_flag().  (Test: ok)
   *
   * @return bool
   */
  public function expunge(){
    if ( $this->is_connected() ){
      return imap_expunge($this->stream);
    }
    return false;
  }

  /**
   * Fetches the body of the message. (Test: ok)
   *
   * @param int $msgno No of the message
   * @param string|false $part The part number
   * @return bool|string
   */
  public function get_msg_body($msgno, $part){
    if ( $this->is_connected() ){
      if ( empty($part) ){
        return imap_body($this->stream, $msgno);
      }
      return imap_fetchbody($this->stream, $msgno, $part);
    }
    return false;
  }

  /**
   * Sets or removes flag/s on message/s. (Test: ok)
   * The flags which you can set are \\Seen, \\Answered, \\Flagged, \\Deleted, and \\Draft. (Test: ok)
   *
   * @param string $seq A sequence of message numbers. Ex. "2,5,6" or "2:5:6"
   * @param string $flg The flag/s. Ex. "\\Seen \\Flagged"
   * @param bool $remove Set this to true to remove flag/s
   * @return bool
   */
  public function set_msg_flag($seq, $flg, $remove=false){
    if ( $this->is_connected() ){
      return $remove ? imap_clearflag_full($this->stream, $seq, $flg) : imap_setflag_full($this->stream, $seq, $flg);
    }
    return false;
  }

  /**
   * Search messages. (Test: ok)
   * Returns an array of UIDs.
   *
   * Arguments:
   * ALL - return all messages matching the rest of the criteria
   * ANSWERED - match messages with the \\ANSWERED flag set
   * BCC "string" - match messages with "string" in the Bcc: field
   * BEFORE "date" - match messages with Date: before "date"
   * BODY "string" - match messages with "string" in the body of the message
   * CC "string" - match messages with "string" in the Cc: field
   * DELETED - match deleted messages
   * FLAGGED - match messages with the \\FLAGGED (sometimes referred to as Important or Urgent) flag set
   * FROM "string" - match messages with "string" in the From: field
   * KEYWORD "string" - match messages with "string" as a keyword
   * NEW - match new messages
   * OLD - match old messages
   * ON "date" - match messages with Date: matching "date"
   * RECENT - match messages with the \\RECENT flag set
   * SEEN - match messages that have been read (the \\SEEN flag is set)
   * SINCE "date" - match messages with Date: after "date"
   * SUBJECT "string" - match messages with "string" in the Subject:
   * TEXT "string" - match messages with text "string"
   * TO "string" - match messages with "string" in the To:
   * UNANSWERED - match messages that have not been answered
   * UNDELETED - match messages that are not deleted
   * UNFLAGGED - match messages that are not flagged
   * UNKEYWORD "string" - match messages that do not have the keyword "string"
   * UNSEEN - match messages which have not been read yet
   *
   * @param string $criteria
   * @return array|bool
   */
  public function search($criteria){
    if ( $this->is_connected() ){
      return imap_search($this->stream, $criteria, SE_UID);
    }
    return false;
  }

  /**
   *  Appends a string message to a specified mailbox. (Test: ok)
   *
   * @param string $mbox Destination mailbox name
   * @param string $msg Message
   * @return bool
   */
  public function append($mbox, $msg){
    if ( $this->is_connected() ){
      return @imap_append($this->stream, $this->mbParam. $mbox, $msg);
    }
    return false;
  }

  /**
   *
   *
   * @param int $msgno
   */
  public function get_msg($msgno){
    // input $mbox = IMAP stream, $msgno = message id
    // output all the following:
    $this->htmlmsg = '';
    $this->plainmsg = '';
    $this->charset = '';
    $this->attachments = [];
    if ( is_dir(BBN_USER_PATH . 'tmp_mail') ){
      \bbn\file\dir::delete(BBN_USER_PATH . 'tmp_mail');
    }
    // HEADER
    $header = $this->get_msg_headerinfo($msgno);
    // add code here to get date, from, to, cc, subject...
    // BODY STRUCTURE
    $structure = $this->get_msg_structure($msgno);
    \bbn\tools::log($header,'imap');
    \bbn\tools::log($structure,'imap');
    if ( !$structure->parts ) {  // simple
      $this->get_msg_part($msgno, $structure, 0);
    }
    else {  // multipart: cycle through each part
      foreach ( $structure->parts as $partno0 => $p ) {
        $this->get_msg_part($msgno, $p, $partno0 + 1);
      }
    }
  }

  /**
   *
   *
   * @param $msgno
   * @param $structure
   * @param string|false $partno '1', '2', '2.1', '2.1.3', etc for multipart, 0 if simple
   */
  private function get_msg_part($msgno, $structure, $partno){
    // DECODE DATA
    $data = $this->get_msg_body($msgno, $partno);
    // Any part may be encoded, even plain text messages, so check everything.
    $data = $this->get_decode_value($data, $structure->encoding);
    // PARAMETERS
    // get all parameters, like charset, filenames of attachments, etc.
    $params = [];
    if ( $structure->parameters ){
      foreach ( $structure->parameters as $x ){
        $params[strtolower($x->attribute)] = $x->value;
      }
    }
    if ( $structure->dparameters ){
      foreach ( $structure->dparameters as $x ){
        $params[strtolower($x->attribute)] = $x->value;
      }
    }
    // ATTACHMENT
    // Any part with a filename is an attachment,
    // so an attached text file (type 0) is not mistaken as the message.
    if ( $params['filename'] || $params['name'] ) {
      // filename may be given as 'Filename' or 'Name' or both
      $filename = $params['filename'] ? $params['filename'] : $params['name'];
      // filename may be encoded, so see imap_mime_header_decode()
      array_push($this->attachments, $filename);
      file_put_contents(BBN_USER_PATH . 'tmp_mail/' . $filename, $data);
      // this is a problem if two files have same name
    }
    // TEXT
    if ( ($structure->type === 0) && !empty($data) ){
      // Messages may be split in different parts because of inline attachments,
      // so append parts together with blank row.
      $this->charset = $params['charset'];  // assume all parts are same charset
      if ( strtolower($structure->subtype) === 'plain' ){
        if ( stripos($this->charset, 'ISO') !== false ){
          $utfConverter = new utf8($this->charset);
          $this->plainmsg .= $utfConverter->loadCharset($this->charset) ?
            $utfConverter->strToUtf8(trim($data)) . PHP_EOL :
            trim($data) . PHP_EOL;
        }
        else
          $this->plainmsg .= trim($data).PHP_EOL;
      }
      else {
        if ( stripos($this->charset,'ISO') !== false ) {

          if ( $utfConverter = new utf8($this->charset) ) {
            $this->htmlmsg .= $utfConverter->strToUtf8(trim($data)).'<br><br>';
          }
          else {
            $this->htmlmsg .= trim($data).'<br><br>';
          }
        }
        else {
          $this->htmlmsg .= trim($data).'<br><br>';
        }
        if ( !empty($this->htmlmsg) ) {
          $body_pattern = "/<body([^>]*)>(.*)<\/body>/smi";
          preg_match($body_pattern, $this->htmlmsg, $body);
          if ( !empty($body[2]) ){
            $this->htmlmsg = $body[2];
          }
          $img_pattern = "/<img([^>]+)>/smi";
          $this->htmlmsg_noimg = preg_replace($img_pattern, '', $this->htmlmsg);
        }
      }
    }
    // EMBEDDED MESSAGE
    // Many bounce notifications embed the original message as type 2,
    // but AOL uses type 1 (multipart), which is not handled here.
    // There are no PHP functions to parse embedded messages,
    // so this just appends the raw source to the main message.
    else if ( ($structure->type === 2) && !empty($data) && strtolower($structure->subtype) === 'plain') {
      if ( stripos($this->charset, 'ISO') !== false ) {
        $utfConverter = new utf8($this->charset);
        $this->plainmsg .= $utfConverter->loadCharset($this->charset) ?
          $utfConverter->strToUtf8(trim($data)) . PHP_EOL :
          $this->plainmsg .= trim($data) . PHP_EOL;
      }
      else {
        $this->plainmsg .= trim($data) . PHP_EOL;
      }
    }
    // SUBPART RECURSION
    if ( $structure->parts ) {
      foreach ( $structure->parts as $partno0 => $p2 ){
        $this->get_msg_part($msgno, $p2, $partno . '.' . ($partno0+1));  // 1.2, 1.2.1, etc.
      }
    }
  }

}
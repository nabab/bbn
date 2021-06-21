<?php
/**
 * A class for use Firebase Cloud Messaging (FCM).
 *
 * @category Api
 * @author Mirko Argentino <mirko@bbn.solutions>
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 *
 */

namespace bbn\Api;

use bbn\X;
use stdClass;

class FCM {

  const API_URL = 'https://fcm.googleapis.com/fcm/send';

  private
    string $key = '';
  
  public function __construct(string $key)
  {
    $this->key = $key;
  }

  public function sendMessage(string $deviceToken, string $title, string $body, array $data = []): ?string
  {
    if (($res = $this->_send([
        'to' => $deviceToken,
        'notification' => [
          'title' => $title,
          'body' => $body,
          'vibrate' => 1,
          'sound' => 'default',
        ],
        'data' => $data
      ]))
      && !empty($res->success)
      && !empty($res->results)
      && \is_array($res->results)
      && !empty($res->results[0]->message_id)
    ) {
      return $res->results[0]->message_id;
    }
    return null;
  }

  private function _send(array $data): ?stdClass
  {
    if (($res = X::curl(self::API_URL, json_encode($data), [
        'post' => true,
        'httpheader' => [
          'Content-Type: application/json',
          'Authorization: key=' . $this->key
        ]
      ])) 
      && ($res = json_decode($res))
    ) {
      return $res;
    }
    return null;
  }

}
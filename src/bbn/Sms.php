<?php

namespace bbn;

use bbn\X;
use Twilio\Rest\Client;

class Sms
{
  private static $url = 'https://api.smspartner.fr/v1/send';
  protected static $key = '';
  protected static $sender = 'BBN';
  protected static $backupSid = '';
  protected static $backupSender = '';

  public static function send(string $phoneNumber, string $message): ?string
  {
    $ret = null;
    if (!empty($phoneNumber)
      && !empty($message)
    ) {
      if (!empty(self::$url)
        && !empty(static::$key)
        && !empty(static::$sender)
      ) {
        if ($res = X::curl(self::$url, [
          'apiKey' => static::$key,
          'phoneNumbers' => $phoneNumber,
          'message' => $message,
          'sender' => static::$sender,
          'isStopSms' => 0
        ], [
          'returntransfer' => true,
          'timeout' => 10,
          'post' => 1
        ])) {
          $res = json_decode($res);
          X::log([
            'provider' => 'Smspartner',
            'phoneNumber' => $phoneNumber,
            'message' => $message,
            'response' => X::toArray($res)
          ], 'sms');
          if ($res->success) {
            $ret = $res->message_id;
          }
        }
      }

      if (empty($ret)
        && !empty(static::$backupSid)
        && !empty(static::$backupSender)
      ) {
        $client = new Client(static::$backupSid);
        $resp = $client->messages->create($phoneNumber, [
          'from' => static::$backupSender,
          'body' => $message
        ]);
        X::log([
          'provider' => 'Twilio',
          'phoneNumber' => $phoneNumber,
          'message' => $message,
          'response' => X::toArray($res)
        ], 'sms');
        if ($resp->sid) {
          $ret = $resp->sid;
        }
      }
    }

    return $ret;
  }
}

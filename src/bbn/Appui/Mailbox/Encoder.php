<?php
namespace bbn\Appui\Mailbox;

use stdClass;
use bbn\Models\Cls\Basic;
use bbn\Str;

/**
 * Class providing functionality for encoding mailbox data.
 */
class Encoder extends Basic
{

  public function getEncodeCode(string $encoding): int
  {
    return match (strtolower(trim($encoding))) {
      '7bit' => 0,
      '8bit' => 1,
      'binary' => 2,
      'base64' => 3,
      'quoted-printable' => 4,
      default => 0,
    };
  }

  /**
   * Decodes a message based on the specified coding.
   * @param string $message The message to decode.
   * @param int $coding The coding type (0-5).
   * @return string The decoded message.
   */
  public function getDecodedValue(string $message, int $coding): string
  {
    return match ($coding) {
      0 => $message,
      1 => imap_8bit($message),
      2 => imap_binary($message),
      3 => imap_base64($message),
      4 => imap_qprint($message),
      5 => imap_base64($message),
      default => $message,
    };
  }

  public function decodeBodyByEncoding(string $body, string $encoding): string
  {
    $enc = strtolower(trim($encoding));
    return match ($enc) {
      'base64' => base64_decode($body) ?: '',
      'quoted-printable' => quoted_printable_decode($body),
      default => $body,
    };
  }

  public function decodeMimeHeaderValue(string $value): string
  {
    if (function_exists('iconv_mime_decode')) {
      $decoded = @iconv_mime_decode(
        $value,
        ICONV_MIME_DECODE_CONTINUE_ON_ERROR,
        'UTF-8'
      );
      if ($decoded !== false) {
        return $decoded;
      }
    }

    return $this->decodeEncodedWords($value);
  }

  public function decodeEncodedWords(string $raw): string
  {
    preg_match_all("/=\?([^?]+)\?([QqBb])\?([^?]+)\?=/", $raw, $matches);
    if (!empty($matches)) {
      for ($i = 0; $i < count($matches[0]); $i++) {
        $encoding = $matches[2][$i];
        $encodedText = $matches[3][$i];
        if (strtolower($encoding) == "q") {
          $decodedText = quoted_printable_decode(Str::replace("_", " ", $encodedText));
        }
        else {
          $decodedText = base64_decode($encodedText);
        }

        $raw = Str::replace($matches[0][$i], $decodedText, $raw);
      }
    }

    return $raw;
  }

  public function decodeEncodedWordsArray(array $array): array
  {
    for ($i = 0; $i < count($array); $i++) {
      $array[$i] = $this->decodeEncodedWords($array[$i]);
    }

    return $array;
  }

  public function decodeEncodedWordsDeep(null|string|object|array $obj)
  {
    if (is_string($obj)) {
      $obj = $this->decodeEncodedWords($obj);
    }
    elseif (is_object($obj)) {
      foreach ($obj as $idx => $val) {
        $obj->$idx = $this->decodeEncodedWordsDeep($val);
      }
    }
    elseif (is_array($obj)) {
      foreach ($obj as $idx => $val) {
        $obj[$idx] = $this->decodeEncodedWordsDeep($val);
      }
    }

    return $obj;
  }
}
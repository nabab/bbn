<?php
namespace bbn\Appui\Mailbox;

use stdClass;
use bbn\Models\Cls\Basic;
use bbn\Str;
use bbn\Appui\Mailbox\Encoder;

/**
 * Class providing functionality for parsing mailbox data.
 */
class Parser extends Basic
{
  private Encoder $encoder;

  public function __construct()
  {
    $this->encoder = new Encoder();
  }

  public function headers(string $raw): array
  {
    $raw = str_replace("\r\n", "\n", $raw);
    $lines = explode("\n", $raw);
    $headers = [];
    $current = null;

    foreach ($lines as $line) {
      if (preg_match('/^\s+/', $line) && $current !== null) {
        $headers[$current] .= ' ' . trim($line);
        continue;
      }

      if (preg_match('/^([^:]+):(.*)$/', $line, $m)) {
        $current = trim($m[1]);
        $headers[$current] = trim($m[2]);
      }
    }

    return $headers;
  }

  public function headerInfo(string $raw): object
  {
    $headers = $this->headers($raw);
    $res = new stdClass();

    foreach ($headers as $k => $v) {
      $lk = strtolower($k);
      switch ($lk) {
        case 'subject':
          $res->subject = $this->encoder->decodeMimeHeaderValue($v);
          break;
        case 'date':
          $res->date = $v;
          $res->Date = $v;
          break;
        case 'message-id':
          $res->message_id = $v;
          break;
        case 'references':
          $res->references = $v;
          break;
        case 'in-reply-to':
          $res->in_reply_to = $v;
          break;
        case 'from':
          $res->from = $this->addressList($v);
          $res->fromaddress = $v;
          break;
        case 'to':
          $res->to = $this->addressList($v);
          $res->toaddress = $v;
          break;
        case 'cc':
          $res->cc = $this->addressList($v);
          break;
        case 'bcc':
          $res->bcc = $this->addressList($v);
          break;
        case 'reply-to':
          $res->reply_to = $this->addressList($v);
          $res->reply_toaddress = $v;
          break;
        default:
          $res->{$lk} = $v;
      }
    }

    return $res;
  }

  public function addressList(string $value): array
  {
    $res = [];
    $parts = preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $value) ?: [];
    foreach ($parts as $p) {
      $p = trim($p);
      if (preg_match('/^(.*?)<([^>]+)>$/', $p, $m)) {
        $name = trim($m[1], " \t\n\r\0\x0B\"");
        $email = trim($m[2]);
        $res[] = [
          'name' => $name ? $this->encoder->decodeMimeHeaderValue($name) : null,
          'email' => $email
        ];
      }
      elseif (filter_var($p, FILTER_VALIDATE_EMAIL)) {
        $res[] = [
          'name' => null,
          'email' => $p
        ];
      }
    }

    return $res;
  }

  public function bodyStructureFromFetch(string $raw): ?object
  {
    if (preg_match('/BODYSTRUCTURE\s+(.+)\)$/is', $raw, $m)) {
      $o = new stdClass();
      $o->raw = trim($m[1]);
      return $o;
    }

    return null;
  }

  public function splitMultipartBody(string $body, string $boundary): array
  {
    $boundaryLine = '--' . $boundary;
    $endBoundaryLine = '--' . $boundary . '--';
    $lines = preg_split("/\R/", $body) ?: [];
    $parts = [];
    $current = [];
    $inside = false;

    foreach ($lines as $line) {
      if ($line === $boundaryLine) {
        if ($inside && $current) {
          $parts[] = implode("\r\n", $current);
          $current = [];
        }
        $inside = true;
        continue;
      }

      if ($line === $endBoundaryLine) {
        if ($inside && $current) {
          $parts[] = implode("\r\n", $current);
        }
        break;
      }

      if ($inside) {
        $current[] = $line;
      }
    }

    return $parts;
  }

  public function flags(string $raw): array
  {
    if (preg_match('/FLAGS\s+\(([^)]*)\)/i', $raw, $m)) {
      return preg_split('/\s+/', trim($m[1])) ?: [];
    }

    return [];
  }

  public function size(string $raw): int
  {
    if (preg_match('/RFC822\.SIZE\s+(\d+)/i', $raw, $m)) {
      return (int)$m[1];
    }

    return 0;
  }

  public function uid(string $raw): ?int
  {
    if (preg_match('/UID\s+(\d+)/i', $raw, $m)) {
      return (int)$m[1];
    }

    return null;
  }

  public function references(?string $raw): array
  {
    return empty($raw)
      ? []
      : (preg_split('/\s+/', trim(str_replace(['<', '>'], '', $raw))) ?: []);
  }

  public function contentType(string $raw): array
  {
    $parts = array_map('trim', explode(';', $raw));
    $mime = strtolower(array_shift($parts) ?: 'text/plain');
    $res = ['mime' => $mime];
    foreach ($parts as $p) {
      if (preg_match('/^([^=]+)=(.*)$/', $p, $m)) {
        $k = strtolower(trim($m[1]));
        $v = trim($m[2], " \t\n\r\0\x0B\"");
        $res[$k] = $v;
      }
    }

    return $res;
  }

  public function mimeMessage(?string $raw): array
  {
    if (!$raw) {
      return [
        'html' => '',
        'plain' => '',
        'charset' => '',
        'attachments' => [],
        'inline' => []
      ];
    }

    [$headerText, $body] = preg_split("/\R\R/", $raw, 2) + [null, ''];
    $headers = $this->headers($headerText ?? '');
    $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? 'text/plain';
    $encoding = $headers['Content-Transfer-Encoding'] ?? $headers['content-transfer-encoding'] ?? '';
    $disposition = $headers['Content-Disposition'] ?? $headers['content-disposition'] ?? '';
    $typeInfo = $this->contentType($contentType);
    $charset = $typeInfo['charset'] ?? '';
    $boundary = $typeInfo['boundary'] ?? null;
    $mime = strtolower($typeInfo['mime'] ?? 'text/plain');
    $result = [
      'html' => '',
      'plain' => '',
      'charset' => $charset,
      'attachments' => [],
      'inline' => []
    ];

    if ($boundary && str_starts_with($mime, 'multipart/')) {
      $parts = $this->splitMultipartBody($body, $boundary);
      foreach ($parts as $idx => $partRaw) {
        $part = $this->mimeMessage($partRaw);

        if (!empty($part['html'])) {
          $result['html'] .= $part['html'];
        }

        if (!empty($part['plain'])) {
          $result['plain'] .= $part['plain'];
        }

        if (!empty($part['charset']) && empty($result['charset'])) {
          $result['charset'] = $part['charset'];
        }

        if (!empty($part['attachments'])) {
          $result['attachments'] = array_merge($result['attachments'], $part['attachments']);
        }

        if (!empty($part['inline'])) {
          $result['inline'] = array_merge($result['inline'], $part['inline']);
        }
      }

      return $result;
    }

    $decodedBody = $this->encoder->decodeBodyByEncoding($body, $encoding);
    $filename = $this->extractFilenameFromHeaders($headers);
    $cid = $headers['Content-ID'] ?? $headers['content-id'] ?? null;
    if ($filename) {
      $fileType = Str::fileExt($filename);
      if (empty($fileType)) {
        $mimeParts = explode('/', $mime);
        $fileType = strtolower($mimeParts[1] ?? 'bin');
      }

      $att = [
        'id' => $cid ? trim($cid, '<>') : null,
        'type' => $fileType,
        'name' => $filename,
        'size' => strlen($decodedBody),
        'data' => $decodedBody,
        'encoding' => $this->encoder->getEncodeCode($encoding),
        'part' => null
      ];
      $result['attachments'][] = $att;
      if (stripos($disposition, 'inline') !== false) {
        $result['inline'][] = $att;
      }

      return $result;
    }

    if ($mime === 'text/html') {
      $result['html'] = Str::toUtf8($decodedBody);
    }
    elseif ($mime === 'text/plain') {
      $result['plain'] = Str::toUtf8($decodedBody);
    }

    return $result;
  }

  public function extractLiteralBlock(array $lines): ?string
  {
    $capture = false;
    $buffer = [];

    foreach ($lines as $line) {
      if ($capture) {
        if (preg_match('/^BBN_\d+\s+(OK|NO|BAD)/i', $line)) {
          break;
        }

        if ($line === ')') {
          continue;
        }

        $buffer[] = $line;
      }
      elseif (preg_match('/\{(\d+)\}$/', $line)) {
        $capture = true;
      }
    }

    if (!$buffer) {
      return null;
    }

    return implode("\n", $buffer);
  }

  public function extractFilenameFromHeaders(array $headers): ?string
  {
    foreach (['Content-Disposition', 'content-disposition', 'Content-Type', 'content-type'] as $key) {
      if (!empty($headers[$key])) {
        if (preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $headers[$key], $m)) {
          return rawurldecode($m[1]);
        }

        if (preg_match('/name="?([^";]+)"?/i', $headers[$key], $m)) {
          return $this->decodeMimeHeaderValue($m[1]);
        }
      }
    }

    return null;
  }

}
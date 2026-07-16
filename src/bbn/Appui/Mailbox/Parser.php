<?php
namespace bbn\Appui\Mailbox;

use stdClass;
use bbn\Models\Cls\Basic;
use bbn\Str;
use bbn\Appui\Mailbox\Encoder;
use DOMDocument;
use DOMXPath;
use DOMNode;

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
      $mailbox = null;
      $host = null;
      if (preg_match('/^(.*?)<([^>]+)>$/', $p, $m)) {
        $name = trim($m[1], " \t\n\r\0\x0B\"");
        $email = trim($m[2]);
        if (Str::isEmail($email)) {
          $email = strtolower($email);
          [$mailbox, $host] = explode('@', $email, 2);
        }

        $res[] = [
          'name' => !empty($name) ? $this->encoder->decodeMimeHeaderValue($name) : null,
          'email' => $email,
          'mailbox' => $mailbox ?: null,
          'host' => $host ?: null
        ];
      }
      elseif (Str::isEmail($p)) {
        $p = strtolower($p);
        [$mailbox, $host] = explode('@', $p, 2);
        $res[] = [
          'name' => null,
          'email' => $p,
          'mailbox' => $mailbox ?: null,
          'host' => $host ?: null
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
          return $this->encoder->decodeMimeHeaderValue($m[1]);
        }
      }
    }

    return null;
  }

  public function quote(string $html): ?array
  {
    $html = trim($html);
    if (empty($html)) {
      return null;
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($dom);
    $outerHTML = fn(DOMNode $node) => $dom->saveHTML($node);

    /** -------------------------------
     *  1) Strong HTML markers
     *  ------------------------------- */
    $queries = [[
      '//div[contains(concat(" ", normalize-space(@class), " "), " __bbn__quote ")]/blockquote',
      'bbn_quote_div'
    ], [
      '//div[contains(concat(" ", normalize-space(@class), " "), " gmail_quote ")]',
      'gmail_quote_div'
    ], [
      '//blockquote[contains(concat(" ", normalize-space(@class), " "), " gmail_quote ")]',
      'gmail_quote_blockquote'
    ], [
      '//blockquote[translate(@type,"CITE","cite")="cite"]',
      'blockquote_type_cite'
    ], [
      '//div[contains(concat(" ", normalize-space(@class), " "), " moz-cite-prefix ")]',
      'moz_cite_prefix'
    ]];

    foreach ($queries as [$q, $method]) {
      $nodes = $xpath->query($q);
      if ($nodes && $nodes->length > 0) {
        $replyDom = $this->cloneDomDocument($dom);
        $quoteNode = $nodes->item(0);
        if ($method === 'bbn_quote_div') {
          $this->removeFirstNodeByOuterHTML($replyDom, $outerHTML($quoteNode->parentNode));
          $quoteHtml = '';
          if ($quoteNode->hasChildNodes()) {
            foreach ($quoteNode->childNodes as $child) {
              $quoteHtml .= $outerHTML($child);
            }
          }
        }
        else {
          $quoteHtml = $outerHTML($quoteNode);
          $this->removeFirstNodeByOuterHTML($replyDom, $quoteHtml);
        }

        return [
          'text' => trim($replyDom->saveHTML()),
          'quote' => $quoteHtml,
          'method' => $method,
        ];
      }
    }

    /** -------------------------------
     *  2) Textual fallback (multi-lang)
     *  ------------------------------- */
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $patterns = [
      // ---------- English ----------
      '/\ROn\s.+?\swrote:\R/i',
      '/\R-{2,}\s*Original Message\s*-{2,}\R/i',
      '/\RFrom:\s.+\R/i',
      '/\RSent:\s.+\R/i',
      '/\RSubject:\s.+\R/i',

      // ---------- Italian ----------
      '/\RIl\s.+?\sha scritto:\R/i',
      '/\R-{2,}\s*Messaggio originale\s*-{2,}\R/i',
      '/\RDa:\s.+\R/i',
      '/\RInviato:\s.+\R/i',
      '/\ROggetto:\s.+\R/i',

      // ---------- French ----------
      '/\RLe\s.+?\sa écrit\s*:\R/i',
      '/\R-{2,}\s*Message d\'origine\s*-{2,}\R/i',
      '/\RDe\s*:\s.+\R/i',
      '/\REnvoyé\s*:\s.+\R/i',
      '/\RObjet\s*:\s.+\R/i',

      // ---------- Spanish ----------
      '/\REl\s.+?\sescribió\s*:\R/i',
      '/\R-{2,}\s*Mensaje original\s*-{2,}\R/i',
      '/\RDe\s*:\s.+\R/i',
      '/\REnviado\s*:\s.+\R/i',
      '/\RAsunto\s*:\s.+\R/i',

      // ---------- German ----------
      '/\RAm\s.+?\schrieb\s.+?\s*:\R/i',
      '/\R-{2,}\s*Ursprüngliche Nachricht\s*-{2,}\R/i',
      '/\RVon\s*:\s.+\R/i',
      '/\RGesendet\s*:\s.+\R/i',
      '/\RBetreff\s*:\s.+\R/i',

      // ---------- Russian ----------
      '/\R.+?\sнаписал\(а\)\s*:\R/iu',
      '/\R-{2,}\s*Исходное сообщение\s*-{2,}\R/iu',
      '/\RОт\s*:\s.+\R/iu',
      '/\RОтправлено\s*:\s.+\R/iu',
      '/\RТема\s*:\s.+\R/iu',
    ];

    foreach ($patterns as $p) {
      if (preg_match($p, $text, $m, PREG_OFFSET_CAPTURE)) {
        $needle = trim($m[0][0]);
        $htmlPos = mb_stripos($html, $needle);
        if ($htmlPos !== false) {
          return [
            'text' => trim(mb_substr($html, 0, $htmlPos)),
            'quote' => trim(mb_substr($html, $htmlPos)),
            'method' => 'text_separator',
            'separator_regex' => $p,
          ];
        }

        return [
          'text' => $html,
          'quote' => '',
          'method' => 'text_separator_found_but_not_split_in_html',
          'separator_regex' => $p,
        ];
      }
    }

    /** -------------------------------
     *  3) Last resort: first blockquote
     *  ------------------------------- */
    $bq = $xpath->query('//blockquote');
    if ($bq && $bq->length > 0) {
      $quoteHtml = $outerHTML($bq->item(0));
      $replyDom = $this->cloneDomDocument($dom);
      $this->removeFirstNodeByOuterHTML($replyDom, $quoteHtml);

      return [
        'text' => trim($replyDom->saveHTML()),
        'quote' => $quoteHtml,
        'method' => 'first_blockquote',
      ];
    }

    return [
      'text' => $html,
      'quote' => '',
      'method' => 'no_quote_detected',
    ];
  }

  /**
   * Clones a DOMDocument
   */
  private function cloneDomDocument(DOMDocument $dom): DOMDocument
  {
    $clone = new DOMDocument();
    $clone->loadHTML($dom->saveHTML(), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    return $clone;
  }

  /**
   * Removes the first node matching the given outer HTML from the DOMDocument
   */
  private function removeFirstNodeByOuterHTML(DOMDocument $dom, string $targetOuterHtml): void
  {
    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query('//*');
    if (!$nodes) {
      return;
    }

    foreach ($nodes as $node) {
      if ($dom->saveHTML($node) === $targetOuterHtml) {
        $node->parentNode?->removeChild($node);
        return;
      }
    }
  }

}
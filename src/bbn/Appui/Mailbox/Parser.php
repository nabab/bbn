<?php
namespace bbn\Appui\Mailbox;

use stdClass;
use bbn\Models\Cls\Basic;
use bbn\Str;
use bbn\Appui\Mailbox\Encoder;
use DOMDocument;
use DOMXPath;
use DOMNode;
use Exception;

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

  public function msgnum(string $raw): ?int
  {
    if (preg_match('/^\*\s+(\d+)\s+FETCH\b/i', $raw, $m)) {
      return (int)$m[1];
    }

    return null;
  }

  public function uid(string $raw): ?int
  {
    if (preg_match('/\bUID\s+(\d+)/i', $raw, $m)) {
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
        //'data' => $decodedBody,
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

  /**
   * Extracts all BODY[...] parts from a FETCH IMAP response.
   * @param string $raw
   * @return array
   */
  public function bodyParts(string $raw): array
  {
    $parts = [];
    $offset = 0;
    $rawLength = strlen($raw);
    $pattern = '/\bBODY(?:\.PEEK)?\[([^\]]*)\](?:<(\d+)>)?\s+\{(\d+)\}\r?\n/i';
    while (
      ($offset < $rawLength)
      && preg_match(
        $pattern,
        $raw,
        $matches,
        PREG_OFFSET_CAPTURE,
        $offset
      )
    ) {
      $fullMatch = $matches[0][0];
      $matchOffset = $matches[0][1];
      $section = $matches[1][0];
      $partialOffset = isset($matches[2][0]) && $matches[2][0] !== ''
        ? (int) $matches[2][0]
        : null;
      $literalLength = (int)$matches[3][0];
      $literalStart = $matchOffset + strlen($fullMatch);
      $literalEnd = $literalStart + $literalLength;
      if ($literalEnd > $rawLength) {
        throw new Exception(
          sprintf(
            'Literal IMAP incomplete for BODY[%s]: %d bytes expected, %d available.',
            $section,
            $literalLength,
            max(0, $rawLength - $literalStart)
          )
        );
      }

      $content = substr($raw, $literalStart, $literalLength);
      $key = $section !== '' ? $section : 'FULL';
      if ($partialOffset !== null) {
        $key .= '<' . $partialOffset . '>';
      }

      $parts[$key] = $content;
      $offset = $literalEnd;
    }

    return [
      'msgnum' => $this->msgnum($raw),
      'uid' => $this->uid($raw),
      'parts' => $parts,
    ];
  }

  /**
   * Parses a FETCH BODYSTRUCTURE response.
   *
   * @param string $raw
   * @return array
   */
  public function bodyStructure(string $raw): array
  {
    $bodyStructure = $this->extractBodyStructure($raw);
    $tokens = $this->tokenizeBodyStructure($bodyStructure);
    $position = 0;
    $parsed = $this->bodyStructureValue($tokens, $position);
    if (!is_array($parsed)) {
      throw new Exception('BODYSTRUCTURE IMAP not valid.');
    }

    if ($position !== count($tokens)) {
      throw new Exception('Unexpected tokens found after BODYSTRUCTURE.');
    }

    $structure = $this->interpretBodyStructurePart($parsed, null);
    $parts = [];
    $this->flattenBodyStructureParts($structure, $parts);
    return [
      'msgnum' => $this->msgnum($raw),
      'uid' => $this->uid($raw),
      'structure' => $structure,
      'parts' => $parts,
    ];
  }

  /**
   * Returns only the MIME parts of BODYSTRUCTURE indexed by IMAP section
   * @param string $raw
   * @return array
   */
  public function bodyStructureParts(string $raw): array
  {
    return $this->bodyStructure($raw)['parts'];
  }

  /**
   * Returns only the attachments found in the BODYSTRUCTURE.
   * @param string $raw
   * @return array
   */
  public function bodyStructureAttachments(string $raw): array
  {
    return array_filter(
      $this->bodyStructureParts($raw),
      static fn (array $part): bool => ($part['isAttachment'] ?? false) === true
    );
  }

  /**
   * Extracts the BODYSTRUCTURE part from the raw IMAP response.
   * @param string $raw
   * @return string
   */
  private function extractBodyStructure(string $raw): string
  {
    if (!preg_match(
      '/\bBODYSTRUCTURE\b/i',
      $raw,
      $matches,
      PREG_OFFSET_CAPTURE
    )) {
      throw new Exception(sprintf('The IMAP response does not contain BODYSTRUCTURE: %s', $raw));
    }

    $offset = $matches[0][1] + strlen($matches[0][0]);
    $length = strlen($raw);
    while ($offset < $length && ctype_space($raw[$offset])) {
      $offset++;
    }

    if ($offset >= $length || $raw[$offset] !== '(') {
      throw new Exception(sprintf('The BODYSTRUCTURE does not start with a parenthesis: %s', $raw));
    }

    $depth = 0;
    $inQuotedString = false;
    $escaped = false;
    for ($i = $offset; $i < $length; $i++) {
      $char = $raw[$i];
      if ($inQuotedString) {
        if ($escaped) {
          $escaped = false;
          continue;
        }

        if ($char === '\\') {
          $escaped = true;
          continue;
        }

        if ($char === '"') {
          $inQuotedString = false;
        }

        continue;
      }

      if ($char === '"') {
        $inQuotedString = true;
        continue;
      }

      if ($char === '(') {
        $depth++;
        continue;
      }

      if ($char === ')') {
        $depth--;
        if ($depth === 0) {
          return substr($raw, $offset, $i - $offset + 1);
        }
      }
    }

    throw new Exception(sprintf('Incomplete BODYSTRUCTURE (unbalanced parentheses): %s', $raw));
  }

  /**
   * Tokenizes the BODYSTRUCTURE string into a list of tokens.
   * @param string $raw
   * @return array
   */
  private function tokenizeBodyStructure(string $raw): array
  {
    $tokens = [];
    $length = strlen($raw);
    $position = 0;
    while ($position < $length) {
      $char = $raw[$position];
      if (ctype_space($char)) {
        $position++;
        continue;
      }

      if ($char === '(') {
        $tokens[] = ['type' => 'open'];
        $position++;
        continue;
      }

      if ($char === ')') {
        $tokens[] = ['type' => 'close'];
        $position++;
        continue;
      }

      if ($char === '"') {
        $position++;
        $value = '';
        $closed = false;
        while ($position < $length) {
          $char = $raw[$position];
          if ($char === '\\') {
            $position++;
            if ($position >= $length) {
              throw new Exception('Incomplete escape in an IMAP string.');
            }

            $value .= $raw[$position];
            $position++;
            continue;
          }

          if ($char === '"') {
            $position++;
            $closed = true;
            break;
          }

          $value .= $char;
          $position++;
        }

        if (!$closed) {
          throw new Exception('Unterminated IMAP quoted string.');
        }

        $tokens[] = [
          'type' => 'string',
          'value' => $value,
        ];
        continue;
      }

      if ($char === '{') {
        throw new Exception('IMAP literals are not supported in this BODYSTRUCTURE.');
      }

      $start = $position;
      while (
        $position < $length
        && !ctype_space($raw[$position])
        && $raw[$position] !== '('
        && $raw[$position] !== ')'
      ) {
        $position++;
      }

      $value = substr($raw, $start, $position - $start);
      $tokens[] = [
        'type' => 'atom',
        'value' => $value,
      ];
    }

    return $tokens;
  }

  /**
   * Parses a value in the BODYSTRUCTURE token list, which can be either a single value or a nested list.
   * @param array $tokens
   * @param int $position
   * @return mixed
   */
  private function bodyStructureValue(array $tokens, int &$position)
  {
    if (!isset($tokens[$position])) {
      throw new Exception('Unexpected end while parsing the BODYSTRUCTURE.');
    }

    $token = $tokens[$position];
    $position++;
    if ($token['type'] === 'open') {
      $values = [];
      while (true) {
        if (!isset($tokens[$position])) {
          throw new Exception('Unterminated BODYSTRUCTURE list.');
        }

        if ($tokens[$position]['type'] === 'close') {
          $position++;
          break;
        }

        $values[] = $this->bodyStructureValue($tokens, $position);
      }

      return $values;
    }

    if ($token['type'] === 'close') {
      throw new Exception('Unexpected closing parenthesis in BODYSTRUCTURE.');
    }

    $value = $token['value'] ?? '';
    if ($token['type'] === 'string') {
      return $value;
    }

    if (strcasecmp($value, 'NIL') === 0) {
      return null;
    }

    if (preg_match('/^\d+$/', $value)) {
      return (int)$value;
    }

    return $value;
  }

  /**
   * Interprets a BODYSTRUCTURE part, which can be either a single part or a multipart.
   * @param array $data
   * @param string|null $section
   * @return array
   */
  private function interpretBodyStructurePart(array $data, ?string $section): array
  {
    if ($data === []) {
      throw new Exception('Empty MIME part.');
    }

    if (is_array($data[0] ?? null)) {
      return $this->interpretMultipartBodyStructure($data, $section);
    }

    return $this->interpretSingleBodyStructurePart($data, $section);
  }

  /**
   * Interprets a multipart BODYSTRUCTURE part.
   * @param array $data
   * @param string|null $section
   * @return array
   */
  private function interpretMultipartBodyStructure(array $data, ?string $section): array
  {
    $index = 0;
    $children = [];
    while (isset($data[$index]) && is_array($data[$index])) {
      $childNumber = (string) ($index + 1);
      $childSection = $section === null
        ? $childNumber
        : $section . '.' . $childNumber;
      $children[] = $this->interpretBodyStructurePart($data[$index], $childSection);
      $index++;
    }

    $subtype = strtolower((string) ($data[$index] ?? 'mixed'));
    $parameters = $this->bodyStructureParameters($data[$index + 1] ?? null);
    $extensions = array_slice($data, $index + 2);
    $disposition = $this->findBodyStructureDisposition($extensions);
    return [
      'section' => $section,
      'multipart' => true,
      'type' => 'multipart',
      'subtype' => $subtype,
      'mimeType' => 'multipart/' . $subtype,
      'parameters' => $parameters,
      'boundary' => $parameters['boundary'] ?? null,
      'contentId' => null,
      'description' => null,
      'encoding' => null,
      'size' => null,
      'lines' => null,
      'disposition' => $disposition['type'],
      'dispositionParameters' => $disposition['parameters'],
      'filename' => null,
      'isAttachment' => false,
      'children' => $children,
    ];
  }

  /**
   * Interprets a single BODYSTRUCTURE part.
   * @param array $data
   * @param string|null $section
   * @return array
   */
  private function interpretSingleBodyStructurePart(array $data, ?string $section): array
  {
    $type = strtolower((string) ($data[0] ?? 'application'));
    $subtype = strtolower((string) ($data[1] ?? 'octet-stream'));
    $parameters = $this->bodyStructureParameters($data[2] ?? null);
    $contentId = is_string($data[3] ?? null)
      ? $data[3]
      : null;
    $description = is_string($data[4] ?? null)
      ? $data[4]
      : null;
    $encoding = isset($data[5])
      ? strtolower((string) $data[5])
      : null;
    $size = is_int($data[6] ?? null)
      ? $data[6]
      : null;
    $lines = null;
    $extensionOffset = 7;
    $embeddedMessage = null;
    if ($type === 'text') {
      $lines = is_int($data[7] ?? null)
        ? $data[7]
        : null;
      $extensionOffset = 8;
    }
    elseif ($type === 'message' && $subtype === 'rfc822') {
      if (is_array($data[8] ?? null)) {
        $embeddedSection = $section === null
          ? '1'
          : $section . '.1';
        $embeddedMessage = $this->interpretBodyStructurePart($data[8], $embeddedSection);
      }

      $lines = is_int($data[9] ?? null)
        ? $data[9]
        : null;
      $extensionOffset = 10;
    }

    $extensions = array_slice($data, $extensionOffset);
    $disposition = $this->findBodyStructureDisposition($extensions);
    $filename = $this->findBodyStructureFilename($parameters, $disposition['parameters']);
    $children = $embeddedMessage === null
      ? []
      : [$embeddedMessage];
    return [
      'section' => $section,
      'multipart' => false,
      'type' => $type,
      'subtype' => $subtype,
      'mimeType' => $type . '/' . $subtype,
      'parameters' => $parameters,
      'boundary' => null,
      'contentId' => trim($contentId, "<>"),
      'description' => $description,
      'encoding' => $encoding,
      'size' => $size,
      'lines' => $lines,
      'disposition' => $disposition['type'],
      'dispositionParameters' => $disposition['parameters'],
      'filename' => $filename,
      'isAttachment' => $this->isBodyStructureAttachment($disposition['type'], $filename),
      'children' => $children,
    ];
  }

  /**
   * Parses the parameters of a BODYSTRUCTURE part.
   * @param mixed $value
   * @return array
   */
  private function bodyStructureParameters(mixed $value): array
  {
    if (!is_array($value)) {
      return [];
    }

    $parameters = [];
    $count = count($value);
    for ($index = 0; $index + 1 < $count; $index += 2) {
      if (!is_scalar($value[$index])) {
        continue;
      }

      $name = strtolower((string)$value[$index]);
      $parameterValue = $value[$index + 1];
      if ($parameterValue === null || !is_scalar($parameterValue)) {
        continue;
      }

      $parameters[$name] = (string)$parameterValue;
    }

    return $parameters;
  }

  /**
   * Finds the disposition of a BODYSTRUCTURE part.
   * @param array $values
   * @return array
   */
  private function findBodyStructureDisposition(array $values): array
  {
    foreach ($values as $value) {
      if (
        !is_array($value)
        || !isset($value[0])
        || !is_string($value[0])
      ) {
        continue;
      }

      $type = strtolower($value[0]);
      if (!in_array($type, ['attachment', 'inline'], true)) {
        continue;
      }

      return [
        'type' => $type,
        'parameters' => $this->bodyStructureParameters($value[1] ?? null)
      ];
    }

    return [
      'type' => null,
      'parameters' => [],
    ];
  }

  /**
   * Finds the filename of a BODYSTRUCTURE part.
   * @param array $contentTypeParameters
   * @param array $dispositionParameters
   */
  private function findBodyStructureFilename(array $contentTypeParameters, array $dispositionParameters): ?string
  {
    return $dispositionParameters['filename']
      ?? $dispositionParameters['filename*']
      ?? $contentTypeParameters['name']
      ?? $contentTypeParameters['name*']
      ?? null;
  }

  /**
   * Determines if a BODYSTRUCTURE part is an attachment based on its disposition and filename.
   * @param string|null $disposition
   * @param string|null $filename
   * @return bool
   */
  private function isBodyStructureAttachment(?string $disposition, ?string $filename): bool
  {
    if ($disposition === 'attachment') {
      return true;
    }

    return $filename !== null && $filename !== '';
  }

  /**
   * Flattens the BODYSTRUCTURE parts into a single associative array indexed by section.
   * @param array $part
   * @param array $parts
   */
  private function flattenBodyStructureParts(array $part, array &$parts)
  {
    $section = $part['section'] ?? null;
    if (
      is_string($section)
      && $section !== ''
      && ($part['multipart'] ?? false) === false
    ) {
      $parts[$section] = $part;
    }

    foreach ($part['children'] ?? [] as $child) {
      if (is_array($child)) {
        $this->flattenBodyStructureParts($child, $parts);
      }
    }
  }

}
<?php

namespace bbn\Appui\I18n;

use Exception;
use bbn\X;
use bbn\Appui\I18n\Service;

class LibreTranslate extends Service
{

  public function translate(
    string|array $string,
    ?string $sourceLang = null,
    ?string $targetLang = null,
    ?int $alternatives = null
  ): ?array
  {
    $multi = is_array($string);
    $data = [
      'q' => $multi ? json_encode($string) : $string,
      'source' => $sourceLang ?: $this->sourceLang,
      'target' => $targetLang ?: $this->targetLang,
      'format' => 'text',
      'alternatives' => $alternatives ?? $this->alternatives,
    ];
    if ($req = $this->request('translate', $data)) {
      $translated = $req['translatedText'] ?: '';
      if ($multi) {
        $translated = !empty($translated) ? urldecode($translated) : [];
      }

      if ($err = X::lastCurlError()) {
        throw new Exception($err);
      }

      return [
        'original' => $string,
        'translated' => $translated,
        'source' => $data['source'],
        'target' => $data['target'],
        'alternatives' => !empty($data['alternatives']) && !empty($req['alternatives']) ? $req['alternatives'] : []
      ];
    }

    return null;
  }
}
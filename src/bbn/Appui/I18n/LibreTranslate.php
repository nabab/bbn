<?php
namespace bbn\Appui\I18n;

use bbn\X;
use bbn\Str;
use bbn\Appui\I18n\Service;

class LibreTranslate extends Service
{

  protected $apiUrl = 'localhost';
  protected $apiPort = '';
  protected $sourceLang = 'en';
  protected $targetLang = 'fr';
  protected $alternatives = 0;

  public function __construct(array $cfg)
  {
    
  }

  public function translate(
    string|array $string,
    ?string $sourceLang = null,
    ?string $targetLang = null,
    ?int $alternatives = null
  ): ?array
  {
    $data = [
      'q' => is_array($string) ? urlencode(json_encode($string)) : $string,
      'source' => $sourceLang ?: $this->sourceLang,
      'target' => $targetLang ?: $this->targetLang,
      'format' => 'text',
      'alternatives' => $alternatives ?? $this->alternatives,
    ];

    if ($req = $this->request('translate', $data)) {
      $translated = $req['translatedText'] ?: '';
      if (is_array($string)) {
        $translated = !empty($translated) ? json_decode(urldecode($translated), true) : [];
      }

      return [
        'original' => $string,
        'translated' => $translated,
        'source' => $data['source'],
        'target' => $data['target'],
        'alternatives' => !empty($data['alternatives']) && !empty($req['alternatives']) ? json_decode(urldecode($req['alternatives']), true) : []
      ];
    }

    return null;
  }

  private function request(string $param, ?array $data = null): ?array
  {
    $response = X::curl(
      $this->apiUrl.(!empty($this->apiPort) ? ':'.$this->apiPort : '').'/'.$param,
      $data
    );
    if (!empty($response) && Str::isJson($response)) {
      return json_decode($response, true);
    }

    return null;
  }
}
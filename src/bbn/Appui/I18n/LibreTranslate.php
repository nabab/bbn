<?php

namespace bbn\Appui\I18n;

use Exception;
use bbn\X;
use bbn\Str;
use bbn\Appui\I18n\Service;

class LibreTranslate implements Service
{

  protected $url;
  protected $port;
  protected $sourceLang;
  protected $targetLang;
  protected $alternatives;

  public function __construct(
    string $url,
    string $port,
    string $sourceLang,
    string $targetLang,
    int $alternatives
  )
  {
    $this->url = $url;
    $this->port = $port;
    $this->sourceLang = $sourceLang;
    $this->targetLang = $targetLang;
    $this->alternatives = $alternatives;
    if (empty($this->url)) {
      throw new \Exception('URL is not set');
    }

    if (empty($this->sourceLang)) {
      throw new \Exception('Source language is not set');
    }

    if (empty($this->targetLang)) {
      throw new \Exception('Target language is not set');
    }
  }

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

  public function setSourceLang(string $lang): self
  {
    $this->sourceLang = $lang;

    return $this;
  }

  public function setTargetLang(string $lang): self
  {
    $this->targetLang = $lang;

    return $this;
  }

  public function setAlternatives(int $num): self
  {
    $this->alternatives = $num;

    return $this;
  }

  private function request(string $param, ?array $data = null): ?array
  {
    $response = X::curl(
      $this->url.(!empty($this->port) ? ':'.$this->port : '').'/'.$param,
      $data
    );
    if (!empty($response) && Str::isJson($response)) {
      return json_decode($response, true);
    }

    return null;
  }
}
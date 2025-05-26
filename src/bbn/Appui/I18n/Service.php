<?php

namespace bbn\Appui\I18n;

use Exception;
use bbn\X;
use bbn\Str;
use bbn\Appui\I18n\ServiceInterface;

class Service implements ServiceInterface
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
      'string' => $multi ? json_encode($string) : $string,
      'source' => $sourceLang ?: $this->sourceLang,
      'target' => $targetLang ?: $this->targetLang,
      'alternatives' => $alternatives ?? $this->alternatives,
    ];
    if ($req = $this->request('translate', $data)) {
      $translated = $req['translated'] ?: '';
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

  protected function request(string $endpoint, ?array $data = null): ?array
  {
    $url = str_ends_with($this->url, '/') ? substr($this->url, 0, -1) : $this->url;
    $response = X::curl(
      $url.(!empty($this->port) ? ':'.$this->port : '').'/'.$endpoint,
      $data
    );
    if (!empty($response) && Str::isJson($response)) {
      return json_decode($response, true);
    }

    return null;
  }
}
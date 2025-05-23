<?php

namespace bbn\Appui\I18n;

use Exception;
use bbn\X;

trait Api {

  protected $api;

  protected static $apis = [
    'libretranslate' => 'LibreTranslate'
  ];

  protected $apiUrl = 'httsp://localhost';

  protected $apiPort = '';

  protected $apiSourceLang = 'en';

  protected $apiTargetLang = 'fr';

  protected $apiAlternatives = 0;


  public function apiTranslate(
    string|array $string,
    ?string $sourceLang = null,
    ?string $targetLang = null,
    ?int $alternatives = null
  ): ?array
  {
    if (empty($this->api)) {
      throw new Exception(_("No API initialized"));
    }

    return $this->api->translate($string, $sourceLang, $targetLang, $alternatives);
  }


  public function apiSetSourceLang(string $lang){
    $this->apiSourceLang = $lang;
    if (!empty($this->api)) {
      $this->api->setSourceLang($lang);
    }

    return $this;
  }


  public function apiSetTargetLang(string $lang){
    $this->apiTargetLang = $lang;
    if (!empty($this->api)) {
      $this->api->setTargetLang($lang);
    }

    return $this;
  }


  public function apiSetAlternatives(int $num){
    $this->apiAlternatives = $num;
    if (!empty($this->api)) {
      $this->api->setAlternatives($num);
    }

    return $this;
  }


  public function initApi(array $cfg): self
  {
    if (empty($cfg['service'])
      || empty(static::$apis[$cfg['service']])
      || !class_exists("\\bbn\\Appui\\I18n\\".static::$apis[$cfg['service']])
    ) {
      throw new Exception(_("API service not found"));
    }

    $this->apiUrl = $cfg['url'] ?? $this->apiUrl;
    $this->apiPort = $cfg['port'] ?? $this->apiPort;
    $this->apiSourceLang = $cfg['source'] ?? $this->apiSourceLang;
    $this->apiTargetLang = $cfg['target'] ?? $this->apiTargetLang;
    $this->apiAlternatives = $cfg['alternatives'] ?? $this->apiAlternatives;
    $className = "\\bbn\\Appui\\I18n\\".static::$apis[$cfg['service']];

    if (empty($this->apiUrl)) {
      throw new \Exception(_('API URL is not set'));
    }

    if (empty($this->apiSourceLang)) {
      throw new \Exception(_('API source language is not set'));
    }

    if (empty($this->apiTargetLang)) {
      throw new \Exception(_('API target language is not set'));
    }

    $this->api = new $className(
      $this->apiUrl,
      $this->apiPort,
      $this->apiSourceLang,
      $this->apiTargetLang,
      $this->apiAlternatives
    );

    return $this;
  }
}
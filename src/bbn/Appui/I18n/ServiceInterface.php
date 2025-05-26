<?php

namespace bbn\Appui\I18n;

interface ServiceInterface {

  function __construct(
    string $url,
    string $port,
    string $sourceLang,
    string $targetLang,
    int $alternatives);
  function translate(
    string|array $string,
    ?string $sourceLang = null,
    ?string $targetLang = null,
    ?int $alternatives = null
  ): ?array;
  function setSourceLang(string $lang): self;
  function setTargetLang(string $lang): self;
  function setAlternatives(int $num): self;
}
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
  function setSourceLang(string $lang): static;
  function setTargetLang(string $lang): static;
  function setAlternatives(int $num): static;
}
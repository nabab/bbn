<?php
namespace bbn\Appui\I18n;

interface Service {
  function __construct(array $cfg);
  function translate(string|array $string, ?string $sourceLang = null, ?string $targetLang = null, ?int $alternatives = null): ?array;
  function request(string $param, ?array $data = null): ?array;
}
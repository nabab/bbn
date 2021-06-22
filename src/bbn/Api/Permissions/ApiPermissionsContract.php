<?php

namespace bbn\Api\Permissions;

interface ApiPermissionsContract
{
  public function getAuthorizationUrl(): string;

  public function getTokens(string $authorization_code): array;

  public function refreshAccessToken(string $refresh_token);

  public function getProvider();
}
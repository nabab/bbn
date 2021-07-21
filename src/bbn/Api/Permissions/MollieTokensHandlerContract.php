<?php

namespace bbn\Api\Permissions;

interface MollieTokensHandlerContract
{
  public function saveNewPermissionTokens(string $access_token, string $refresh_token, int $expires_in, string $account_name);

  public function updatePermissionTokens(string $access_token, string $refresh_token, int $expires_in, string $account_name);
}
<?php

namespace bbn\User;

use Exception;
use bbn\X;
use bbn\Db;
use bbn\Db\Languages\Sqlite;
use bbn\Str;
use bbn\User;
use bbn\User\Preferences;
use bbn\Appui\Mailbox;
use bbn\Appui\Passwords;
use bbn\Appui\Grid;
use bbn\Models\Cls\Basic;
use bbn\Models\Tts\DbOps;
use bbn\Models\Tts\Optional;
use bbn\Models\Tts\LocaleDatabase;
use bbn\Mvc\Controller;
use bbn\Mvc;
use bbn\File\System;
use Generator;
use stdClass;

class Email extends Basic
{
  use DbOps;
  use Optional;
  use LocaleDatabase;

  /** @var array */
  protected static $default_class_cfg = [
    "table" => "bbn_users_emails",
    "tables" => [
      "users_emails" => "bbn_users_emails",
      "users_emails_aliases" => "bbn_users_emails_aliases",
      "users_emails_recipients" => "bbn_users_emails_recipients",
      "users_contacts" => "bbn_users_contacts",
      "users_contacts_links" => "bbn_users_contacts_links",
    ],
    "arch" => [
      "users_emails" => [
        "id" => "id",
        "id_user" => "id_user",
        "id_folder" => "id_folder",
        "msg_uid" => "msg_uid",
        "msg_unique_id" => "msg_unique_id",
        "date" => "date",
        "id_sender" => "id_sender",
        "subject" => "subject",
        "excerpt" => "excerpt",
        "size" => "size",
        "attachments" => "attachments",
        "priority" => "priority",
        "flags" => "flags",
        "is_read" => "is_read",
        "is_draft" => "is_draft",
        "id_parent" => "id_parent",
        "id_thread" => "id_thread",
        "external_uids" => "external_uids",
      ],
      "users_emails_aliases" => [
        "id_account" => "id_account",
        "id_link" => "id_link",
        "main" => "main",
      ],
      "users_emails_recipients" => [
        "id_email" => "id_email",
        "id_contact_link" => "id_contact_link",
        "type" => "type",
      ],
      "users_contacts" => [
        "id" => "id",
        "id_user" => "id_user",
        "name" => "name",
        "blacklist" => "blacklist",
        "cfg" => "cfg",
      ],
      "users_contacts_links" => [
        "id" => "id",
        "id_contact" => "id_contact",
        "type" => "type",
        "value" => "value",
        "num_sent" => "num_sent",
        "last_sent" => "last_sent",
      ],
    ],
  ];

  /** @var array */
  protected static $folderTypes = [];

  /** @var array */
  protected static $folderTypesCodes = [];

  /** @var array An array of connection objects */
  protected $mboxes = [];

  /** @var Passwords The passwords object */
  protected $pw;

  /** @var array */
  protected $folderTypesNotUnique = ["folders"];

  /** @var string */
  protected $cachePrefix = "emails/";

  /** @var int The frequency for idle synchronization */
  protected $idleSyncFrequency = 180;

  /** @var int The timestamp of the last idle synchronization */
  protected $idleLastSync = 0;

  /** @var bool Whether the idle synchronization is currently running */
  protected $idleSyncing = false;

  /** @var bool Whether the processing queue is currently running */
  protected $processingQueue = false;

  /** @var int The timestamp of the last queue callback ping */
  protected $queueCallbackLastPing = 0;

  /** @var int The frequency for the queue callback ping */
  protected $queueCallbackPingFrequency = 5;

  /** @var callable|null The queue callback */
  protected $queueCallback = null;

  /**
   * Returns a list typical folder types as they are recorded in the options
   *
   * @return array
   */
  public static function getFolderTypes(): array
  {
    if ( empty(self::$folderTypes) ) {
      self::$folderTypes = self::getOptions("folders") ?: [];
    }

    return self::$folderTypes;
  }

  /**
   * Returns a list of typical folder types codes as they are recorded in the options
   *
   * @return array
   */
  public static function getFolderTypesCodes(): array
  {
    if ( empty(self::$folderTypesCodes) ) {
      self::$folderTypesCodes = self::getOptionsObject()->getCodes(self::getOptionId("folders")) ?: [];
    }

    return self::$folderTypesCodes;
  }

  /**
   * Returns a list of typical email accounts types as they are recorded in the options
   *
   * @return array
   */
  public static function getAccountTypes(): array
  {
    return self::getOptions("types");
  }

  public function __construct(
    private Db $db,
    /** @var user The user object */
    protected ?User $user = null,
    /** @var preferences The preferences object */
    protected ?Preferences $pref = null,
  ) {
    $this->initClassCfg();
    self::optionalInit();
    if (!$this->user) {
      $this->user = User::getInstance();
    }

    if (!$this->pref) {
      $this->pref = Preferences::getInstance();
    }
  }

  public function getMailbox(string $id_account): ?Mailbox
  {
    if (!isset($this->mboxes[$id_account])) {
      $this->getAccount($id_account);
    }

    if (isset($this->mboxes[$id_account])) {
      $mb = &$this->mboxes[$id_account];
      if (!isset($mb["mailbox"])) {
        $cfg = $this->mboxes[$id_account];
        $cfg["pass"] = $this->_get_password()->userGet(
          $id_account,
          $this->user,
        );
        if (!empty($cfg["type"]) && Str::isUid($cfg["type"])) {
          $cfg["type"] = self::getOptionsObject()->code($cfg["type"]);
        }

        $mb["mailbox"] = new Mailbox($cfg);
      }

      if (isset($mb["mailbox"])) {
        return $mb["mailbox"];
      }
    }
    return null;
  }

  /**
   * Returns the list of the accounts' IDs of the current user.
   *
   * @return array|null
   */
  public function getAccountsIds(): ?array
  {
    if ($id_accounts = self::getOptionId("accounts")) {
      return $this->pref->retrieveIds($id_accounts);
    }

    return null;
  }

  /**
   * Returns the list of the accounts of the current user.
   *
   * @param bool $force
   * @return array|null
   */
  public function getAccounts(bool $force = false): array
  {
    $res = [];
    if ($ids = $this->getAccountsIds()) {
      foreach ($ids as $id) {
        $res[] = $this->getAccount($id, $force);
      }
    }

    return $res;
  }

  public function setAccountStage(string $id_account, int $stage): bool
  {
    if ($a = $this->pref->get($id_account)) {
      $a["stage"] = $stage;
      return $this->pref->set($id_account, $a);
    }

    return false;
  }

  public function getAccount(string $id_account, bool $force = false): ?array
  {
    if ($force || !isset($this->mboxes[$id_account])) {
      if ($a = $this->pref->get($id_account)) {
        $this->mboxes[$id_account] = [
          "id" => $a["id"],
          "text" => $a["text"],
          "host" => $a["host"],
          "email" => $a["email"],
          "login" => $a["login"],
          "type" => self::getOptionId($a["type"], "types"),
          "port" => $a["port"],
          "encryption" => !empty($a["encryption"]) ? 1 : 0,
          "validatecert" => !empty($a["validatecert"]) ? 1 : 0,
          "folders" => $this->getFolders($id_account),
          "last_uid" => $a["last_uid"] ?? null,
          "last_check" => $a["last_check"] ?? null,
          "id_account" => $id_account,
          "smtp" => $a["id_alias"] ?? null,
          "autosync" => !empty($a["autosync"]) ? 1 : 0,
          "rules" => $this->getFoldersRules($id_account),
          $this->localeField => !empty($a[$this->localeField]),
        ];
      }
    }

    return $this->mboxes[$id_account] ?? null;
  }

  public function checkConfig(array $cfg): bool
  {
    if (X::hasProps($cfg, ["login", "pass", "type"], true)) {
      if (Str::isUid($cfg["type"])) {
        $cfg["type"] = self::getOptionsObject()->code($cfg["type"]);
      }

      $mb = new Mailbox($cfg);
      return $mb->check();
    }
    return false;
  }

  public function updateAccount(string $id_account, array $cfg): bool
  {
    if (
      empty($cfg["type"]) ||
      !($types = self::getAccountTypes()) ||
      !X::getRow($types, ["code" => $cfg["type"]])
    ) {
      throw new \Exception(_("The account type is not valid"));
    }

    if (
      !X::hasProps($cfg, ["text", "login", "pass", "type", "email"], true) ||
      !X::hasProps($cfg, ["host", "port", "encryption", "validatecert", "smtp", "autosync"]) ||
      (in_array($cfg["type"], ["imap", "local"]) &&
        !X::hasProps($cfg, ["host", "port"], true))
    ) {
      throw new \Exception(_("Missing arguments"));
    }

    if (!$this->getAccount($id_account)) {
      throw new \Exception("The account doesn't exist");
    }

    $text = $cfg['text'];
    unset($cfg['text']);
    $d = X::mergeArrays($this->pref->getCfg($id_account) ?: [], [
      "host" => $cfg["host"] ?: null,
      "email" => $cfg["email"],
      "login" => $cfg["login"],
      "type" => Str::isUid($cfg["type"])
        ? self::getOptionsObject()->code($cfg["type"])
        : $cfg["type"],
      "port" => $cfg["port"] ?: null,
      "encryption" => !empty($cfg["encryption"]) ? 1 : 0,
      "validatecert" => !empty($cfg["validatecert"]) ? 1 : 0,
      "id_alias" => $cfg["smtp"] ?: null,
      "last_uid" => $cfg["last_uid"] ?? null,
      "last_check" => $cfg["last_check"] ?? null,
      "autosync" => !empty($cfg["autosync"]) ? 1 : 0
    ]);

    if (!empty($cfg["pass"])) {
      $p = $this->_get_password()->userGet($id_account, $this->user);
      if (
        (empty($p) || $cfg["pass"] !== $p) &&
        !$this->_get_password()->userStore($cfg["pass"], $id_account, $this->user)
      ) {
        throw new \Exception(_("Impossible to update the password"));
      }
    }

    if (!empty($cfg["folders"])) {
      $this->checkFolderSubscriptions($id_account, $cfg["folders"]);
      $this->syncFolders($id_account, $cfg["folders"], $cfg["rules"] ?? []);
    }

    $ok1 = (bool)$this->pref->setText($id_account, $text);
    $ok2 = (bool)$this->pref->setCfg($id_account, $d);
    return !empty($ok1) || !empty($ok2);
  }

  public function deleteAccount(string $id_account): bool
  {
    return (bool) $this->pref->delete($id_account);
  }

  public function addAccount(array $cfg): string
  {
    if (
      empty($cfg["type"]) ||
      !($types = self::getAccountTypes()) ||
      !X::getRow($types, ["code" => $cfg["type"]])
    ) {
      throw new \Exception(_("The account type is not valid"));
    }

    if (
      !X::hasProps($cfg, ["text", "login", "pass", "type", "email"], true) ||
      !X::hasProps($cfg, ["host", "port", "encryption", "validatecert", "smtp", "autosync"]) ||
      (in_array($cfg["type"], ["imap", "local"]) &&
        !X::hasProps($cfg, ["host", "port"], true))
    ) {
      throw new \Exception(_("Missing arguments"));
    }

    if (!($id_accounts = self::getOptionId("accounts"))) {
      throw new \Exception(_("Impossible to find the account option"));
    }

    // toGroup as this option will use different user options
    if (
      !($id_pref = $this->pref->addToGroup($id_accounts, [
        "id_user" => $this->user->getId(),
        "text" => $cfg["text"],
        "email" => $cfg["email"],
        "login" => $cfg["login"],
        "type" => Str::isUid($cfg["type"])
          ? self::getOptionsObject()->code($cfg["type"])
          : $cfg["type"],
        "host" => $cfg["host"] ?: null,
        "port" => $cfg["port"] ?: null,
        "encryption" => !empty($cfg["encryption"]) ? 1 : 0,
        "validatecert" => !empty($cfg["validatecert"]) ? 1 : 0,
        "id_alias" => $cfg["smtp"] ?: null,
        "autosync" => !empty($cfg["autosync"]) ? 1 : 0,
        $this->localeField => !empty($cfg[$this->localeField]),
      ]))
    ) {
      throw new \Exception(_("Impossible to add the preference"));
    }

    if (!$this->_get_password()->userStore($cfg["pass"], $id_pref, $this->user)) {
      throw new \Exception(_("Impossible to set the password"));
    }

    $this->getAccount($id_pref, true);
    if (!empty($cfg["folders"])) {
      $this->checkFolderSubscriptions($id_pref, $cfg["folders"]);
      $this->syncFolders($id_pref, $cfg["folders"], $cfg["rules"] ?? []);
    }

    return $id_pref;
  }

  public function reset(string $id_account): bool
  {
    if (
      ($account = $this->getAccount($id_account)) &&
      ($num = $this->pref->deleteBits($id_account))
    ) {
      return true;
    }

    return false;
  }

  /**
   * Returns the list of the SMTPs' IDs of the current user.
   *
   * @return array|null
   */
  public function getSmtpsIds(): ?array
  {
    if ($idSmtps = self::getOptionId("smtps")) {
      return $this->pref->retrieveIds($idSmtps);
    }

    return null;
  }

  /**
   * Returns the list of the SMTPs of the current user.
   *
   * @return array
   */
  public function getSmtps(): array
  {
    $res = [];
    if ($ids = $this->getSmtpsIds()) {
      foreach ($ids as $id) {
        $res[] = $this->getSmtp($id);
      }
    }

    return $res;
  }

  /**
   * Returns a SMTP by its ID.
   *
   * @param string $id
   * @return array|null
   */
  public function getSmtp(string $id): ?array
  {
    if ($smtp = $this->pref->get($id)) {
      return [
        "id" => $smtp["id"],
        "name" => $smtp["name"],
        "host" => $smtp["host"],
        "login" => $smtp["login"],
        "encryption" => $smtp["encryption"],
        "port" => $smtp["port"],
        "validatecert" => !empty($smtp["validatecert"]) ? 1 : 0,
        $this->localeField => !empty($smtp[$this->localeField]),
      ];
    }

    return null;
  }

  public function addSmtp(array $cfg): string
  {
    if (
      !X::hasProps($cfg, ["name", "host", "login", "pass", "encryption"], true) ||
      !X::hasProps($cfg, ["port", "validatecert"])
    ) {
      throw new \Exception(_("Missing arguments"));
    }

    if (!($idSmtps = self::getOptionId("smtps"))) {
      throw new \Exception(_("Impossible to find the smtps option"));
    }

    if (
      !($idSmtp = $this->pref->addToGroup($idSmtps, [
        "id_user" => $this->user->getId(),
        "name" => $cfg["name"],
        "login" => $cfg["login"],
        "host" => $cfg["host"],
        "port" => $cfg["port"] ?? null,
        "encryption" => $cfg["encryption"],
        "validatecert" => !empty($cfg["validatecert"]) ? 1 : 0,
        $this->localeField => !empty($cfg[$this->localeField]),
      ]))
    ) {
      throw new \Exception(_("Impossible to add the preference"));
    }

    if (
      !$this->_get_password()->userStore($cfg["pass"], $idSmtp, $this->user)
    ) {
      throw new \Exception(_("Impossible to set the password"));
    }

    return $idSmtp;
  }

  public function updateSmtp(string $idSmtp, array $cfg): bool
  {
    if (
      !X::hasProps($cfg, ["name", "host", "login", "pass"], true) ||
      !X::hasProps($cfg, ["encryption", "port", "validatecert"])
    ) {
      throw new \Exception(_("Missing arguments"));
    }

    $d = X::mergeArrays($this->pref->getCfg($idSmtp) ?: [], [
      "name" => $cfg["name"],
      "login" => $cfg["login"],
      "host" => $cfg["host"],
      "port" => $cfg["port"] ?? null,
      "encryption" => $cfg["encryption"] ?? "none",
      "validatecert" => !empty($cfg["validatecert"]) ? 1 : 0,
    ]);

    if (!empty($cfg["pass"])) {
      $p = $this->_get_password()->userGet($idSmtp, $this->user);
      if (
        (empty($p) || $cfg["pass"] !== $p) &&
        !$this->_get_password()->userStore($cfg["pass"], $idSmtp, $this->user)
      ) {
        throw new \Exception(_("Impossible to update the password"));
      }
    }

    return (bool) $this->pref->setCfg($idSmtp, $d);
  }

  public function deleteSmtp(string $idSmtp): bool
  {
    return (bool) $this->pref->delete($idSmtp);
  }

  public function createFolder(
    string $id_account,
    string $name,
    string|null $id_parent = null,
  ): bool {
    $mb = $this->getMailbox($id_account);
    $uid_parent = "";
    if ($id_parent) {
      $uid_parent = $this->getFolder($id_parent, true)["uid"];
    }
    $mboxName = $id_parent ? $uid_parent . "." . $name : $name;
    if ($mb
      && $mb->createMbox($mboxName)
      && $mb->subscribeFolder($mboxName)
    ) {
      if ($this->createFolderDb($id_account, $name, $id_parent)) {
        $this->mboxes[$id_account]["folders"] = $this->getFolders($id_account);
        return true;
      }
    }
    return false;
  }

  public function createFolderDb(
    string $id_account,
    string $name,
    string|null $id_parent = null,
  ): bool {
    $types = self::getFolderTypes();

    $a = [
      "id_option" => X::getField($types, ["code" => "folders"], "id"),
      "text" => $name,
      "uid" => $name,
      "subscribed" => true,
    ];

    if ($id_parent) {
      $uid_parent = $this->getFolder($id_parent, true)["uid"];
      $a["uid"] = $uid_parent . "." . $name;
      $a["id_parent"] = $id_parent;
    }

    return (bool) $this->pref->addBit($id_account, $a);
  }

  public function renameFolder(
    string $id,
    string $name,
    ?string $idParent = null,
  ): bool {
    if (
      ($folder = $this->getFolder($id, true)) &&
      !empty($folder["uid"]) &&
      !empty($folder["id_account"]) &&
      ($mb = $this->getMailbox($folder["id_account"]))
    ) {
      if (!empty($idParent)) {
        if (!($parentFolder = $this->getFolder($idParent, true))) {
          return false;
        }

        if ($folder["id_account"] !== $parentFolder["id_account"]) {
          return false;
        }

        $newUid = explode(".", $parentFolder["uid"]);
      } else {
        $newUid = explode(".", $folder["uid"]);
        array_pop($newUid);
      }

      $newUid[] = $name;
      $newUid = implode(".", $newUid);
      if ($mb->renameMbox($folder["uid"], $newUid)) {
        return $this->renameFolderDb($id, $name, $idParent);
      }
    }

    return false;
  }

  public function renameFolderDb(
    string $id,
    string $name,
    ?string $idParent = null,
  ): bool {
    if (
      ($folder = $this->getFolder($id, true)) &&
      !empty($folder["uid"]) &&
      !empty($folder["id_account"])
    ) {
      $prefCfg = $this->pref->getClassCfg();
      $bitsFields = $prefCfg["arch"]["user_options_bits"];
      $idAccount = $folder["id_account"];
      $a = [
        $bitsFields["text"] => $name,
      ];
      if (!empty($idParent)
        && ($idParent !== $folder["id_parent"])
      ) {
        if (!($parentFolder = $this->getFolder($idParent, true))) {
          return false;
        }

        if ($idAccount !== $parentFolder["id_account"]) {
          return false;
        }

        $a[$bitsFields["id_parent"]] = $idParent;
        $newUid = explode(".", $parentFolder["uid"]);
      } else {
        $newUid = explode(".", $folder["uid"]);
        array_pop($newUid);
      }

      $newUid[] = $name;
      $newUid = implode(".", $newUid);
      $a["uid"] = $newUid;
      if ($this->pref->updateBit($id, $a)) {
        function updateChildren(
          string $idp,
          string $ouid,
          string $nuid,
          Preferences $pref,
          string $idAccount
        ) {
          if ($items = $pref->getBits($idAccount, $idp, true, true)) {
            foreach ($items as $it) {
              if (!empty($it["uid"])) {
                $uid = $it["uid"];
                if (str_starts_with($uid, $ouid . ".")) {
                  $newUid = $nuid . Str::sub($uid, Str::len($ouid));
                  $pref->updateBit($it["id"], ["uid" => $newUid]);
                  if (!empty($it["numChildren"])) {
                    updateChildren($it["id"], $uid, $newUid, $pref, $idAccount);
                  }
                }
              }
            }
          }
        }
        updateChildren($id, $folder["uid"], $newUid, $this->pref, $idAccount);
        $this->mboxes[$idAccount]["folders"] = $this->getFolders($idAccount);
        return true;
      }
    }

    return false;
  }

  public function deleteFolder(string $id, string $id_account): bool
  {
    $mb = $this->getMailbox($id_account);
    $folder = $this->getFolder($id, true);
    if ($folder && $mb->deleteMbox($folder["uid"])) {
      if ($this->deleteFolderDb($id)) {
        $this->mboxes[$id_account]["folders"] = $this->getFolders($id_account);
        return true;
      }
    }
    return false;
  }

  public function deleteFolderDb(string $id): bool
  {
    return (bool) $this->pref->deleteBit($id);
  }

  public function checkFolder(array|string $folder): ?array
  {
    if (Str::isUid($folder)) {
      $folder = $this->getFolder($folder);
    }

    if (X::hasProp($folder, "uid")
      && ($mb = $this->getMailbox($folder["id_account"]))
      && $mb->check()
      && $mb->update($folder["uid"])
      && ($folders = $mb->getFolders())
      && ($res = $folders[$folder["uid"]])
    ) {
      $res["hash"] = $this->makeFolderHash($folder["id"], $res["num_msg"], $res["last_uid"]);
      if (($res["num_msg"] && !$folder["last_uid"])
        || ($folder["hash"] !== $res["hash"])
      ) {
        $this->pref->updateBit($folder["id"], $res, true);
        $this->getAccount($folder["id_account"], true);
      }

      return $res;
    }

    return null;
  }

  public function getInfoFolder(string $id): ?array
  {
    if (($folder = $this->getFolder($id, true))
      && !empty($folder["uid"])
      && !empty($folder["id_account"])
      && ($mb = $this->getMailbox($folder["id_account"]))
    ) {
      return $mb->getInfoFolder($folder["uid"]);
    }

    return null;
  }

  public function getFolders(string $idAccount, bool $force = false): ?array
  {
    if (Str::isUid($idAccount)) {
      if ($force) {
        $this->syncFolders($idAccount);
      }

      $folders = array_map(
        fn($f) => $this->normalizeFolder($f, false, true),
        $this->pref->getFullBits($idAccount) ?: []
      );
      X::sortBy($folders, "text");
      return $folders;
    }

    return null;
  }

  public function getFoldersRules(string $idAccount): array
  {
    $res = [];
    $folders = $this->pref->getFullBits($idAccount);
    $ftc = self::getFolderTypesCodes();
    $field = $this->pref->getClassCfg()["arch"]["user_options_bits"]['id_option'];
    if (!empty($folders)) {
      foreach ($folders as $f) {
        if (!empty($ftc[$f[$field]])
          && !in_array(
            $ftc[$f[$field]],
            $this->folderTypesNotUnique,
            true,
          )
        ) {
          $res[$ftc[$f[$field]]] = $f["uid"];
        }
      }
    }

    return $res;
  }


  public function getInboxUid(string $idAccount): ?string
  {
    if ($rules = $this->getFoldersRules($idAccount)) {
      return !empty($rules["inbox"]) ? $rules["inbox"] : null;
    }

    return null;
  }


  public function flattenFolders(array $folders): array
  {
    $res = [];
    foreach ($folders as $f) {
      if (!empty($f["items"]) && is_array($f["items"]) && count($f["items"])) {
        $res = array_merge($res, $this->flattenFolders($f["items"]));
      }

      $res[] = $f;
    }

    return $res;
  }

  public function getHashes(): ?array
  {
    $res = [];
    $account_hash = "";
    foreach ($this->getAccounts() as $a) {
      $res[$a["id"]] = [
        "hash" => "",
        "folders" => []
      ];
      $folders = $this->flattenFolders($this->getFolders($a["id"]));
      foreach ($folders as $f) {
        $res[$a["id"]]["folders"][$f["id"]] = $f["hash"];
        if ($f["hash"]) {
          $account_hash .= $f["hash"];
        } else {
          $account_hash .= $f["id"];
        }
      }

      $res[$a["id"]]["hash"] = md5($account_hash);
    }

    return $res;
  }

  public function getFolder(string $id, bool $full = true): ?array
  {
    if ($f = $this->pref->getBit($id)) {
      return $this->normalizeFolder($f, !$full);
    }

    return null;
  }

  /**
   * Returns a folder by its UID.
   */
  public function getFolderByUid(
    string $id_account,
    string $uid,
    bool $force = false,
  ): ?array {
    return X::getRow(
      $this->getFolders($id_account, $force) ?: [],
      ["uid" => $uid]
    );
  }

  public function getNextUid(array $folder, int $uid): ?int
  {
    $mb = $this->getMailbox($folder["id_account"]);
    $mb->selectFolder($folder["uid"]);
    return $mb->getNextUid($uid);
  }

  public function syncEmails(
    string|array $folder,
    int $limit = 0
  ): Generator
  {
    if (Str::isUid($folder)) {
      $folder = $this->getFolder($folder);
    }

    if (X::hasProps($folder, ["id", "id_account", "last_uid", "uid"])) {
      try {
        $check = $this->checkFolder($folder);
      }
      catch (\Exception $e) {
        X::log($e->getMessage(), "user_email_error");
        $check = false;
      }

      if ($check) {
        $isLocale = $this->pref->isLocale(
          $folder["id"],
          $this->pref->getClassCfg()["tables"]["user_options_bits"]
        );
        $db = $isLocale ? $this->getLocaleDb() : $this->db;
        $ret = [
          "added" => 0,
          "deleted" => 0,
          "flagged" => 0
        ];
        $mb = $this->getMailbox($folder["id_account"]);
        $info = $mb->getInfoFolder($folder["uid"]);
        if ($info['nmsgs'] === 0) {
          if (!empty($folder["db_num_msg"])) {
            $this->setFolderSync($folder["id"]);
            $ret['deleted'] += $db->delete($this->class_table, [$this->fields["id_folder"] => $folder["id"]]);
            $this->setFolderSync($folder["id"], false);
          }

          return $ret;
        }

        $first_uid = $mb->getFirstUid();
        $last_uid = $mb->getLastUid();
        $start = null;
        $real_end = null;

        if (isset($folder["db_uid_min"], $folder["db_uid_max"])) {
          if (($folder["db_uid_min"] == $first_uid)
            && ($folder["db_uid_max"] == $last_uid)
          ) {
            if ($info['nmsgs'] != $folder["db_num_msg"]) {
              $this->setFolderSync($folder["id"]);
              $ret['deleted'] = count($this->checkAndDeleteEmails($folder["id"])) ?: 0;
              $this->setFolderSync($folder["id"], false);
            }

            $ret['flagged'] = $this->syncFlags($folder["id"]);
            return $ret;
          }

          if ($folder["db_uid_max"] != $last_uid) {
            $start = $last_uid;
            $real_end = $mb->getNextUid($folder["db_uid_max"]);
          }
          elseif ($folder["db_uid_min"] != $first_uid) {
            $start = $folder["db_uid_min"];
            if (!empty($limit)) {
              $nstart = $mb->getMsgNo($start);
              $nstart -= $limit;
              if ($nstart < 1) {
                $real_end = $first_uid;
              }
              else {
                $real_end = $mb->getMsgUid($nstart);
              }
            }
            else {
              $real_end = $first_uid;
            }
          }
        }
        else {
          $start = $last_uid;
          if (!empty($limit)) {
            $nstart = $mb->getMsgNo($start);
            $nstart -= $limit;
            if ($nstart < 1) {
              $real_end = $first_uid;
            }
            else {
              $real_end = $mb->getMsgUid($nstart);
            }
          }
          else {
            $real_end = $first_uid;
          }
        }

        try {
          $start = $mb->getMsgNo($start);
          $real_end = $mb->getMsgNo($real_end < 1 ? $first_uid : $real_end);
        }
        catch (\Exception $e) {
          $start = $mb->getMsgNo($last_uid);
          $real_end = $mb->getMsgNo($first_uid);
          if ($folder["db_uid_min"]
            && ($folder["db_uid_max"] == $last_uid)
          ) {
            $start = $folder["db_uid_min"];
          }
          elseif ($folder["db_uid_max"] != $last_uid) {
            $start = $last_uid;
            $real_end = $mb->getNextUid($folder["db_uid_max"]);
          }
        }

        if (!$start || !$real_end) {
          if ($info['nmsgs'] != $folder["db_num_msg"]) {
            $this->setFolderSync($folder["id"]);
            $ret['deleted'] = count($this->checkAndDeleteEmails($folder["id"]));
            $this->setFolderSync($folder["id"], false);
          }

          $ret['flagged'] = $this->syncFlags($folder["id"]);
          return $ret;
        }

        $end = $start;
        $this->setFolderSync($folder['id']);
        $all = $mb->getEmailsList($folder['uid'], $start, $real_end);
        if ($all) {
          foreach ($all as $i => $a) {
            if ($this->insertEmail($folder, $a)) {
              $ret['added']++;
              yield 'added' => 1;
            }
            else {
              //throw new \Exception(X::_("Impossible to insert the email with ID").' '.$a['message_id']);
              $this->log(X::_("Impossible to insert the email with ID %s", $a["message_id"]));
            }
          }
        }
        else {
          $err = X::_(
            "Impossible to get the emails for folder %s from %s to %s (%s)",
            $folder["uid"],
            $start,
            $end,
            $real_end
          );
          X::log($err, "user_email_error");
          throw new \Exception($err);
        }

        $this->setFolderSync($folder["id"]);
        $deleted = count($this->checkAndDeleteEmails($folder["id"]));
        $this->setFolderSync($folder["id"], false);
        yield 'deleted' => $deleted;
        $ret['deleted'] += $deleted;
        $flags = $this->syncFlags($folder["id"]);
        yield 'flagged' => $flags;
        $ret['flagged'] += $flags;
        $this->setFolderSync($folder["id"], false);
        if (!empty($ret['added'])) {
          $this->syncThreads($folder["id_account"]);
        }

        return $ret;
      }
    }

    return null;
  }

  public function syncEmails2(
    string|array $folder,
    int $limit = 0
  ): Generator
  {
    if (Str::isUid($folder)) {
      $folder = $this->getFolder($folder);
    }

    if (X::hasProps($folder, ["id", "id_account", "last_uid", "uid"])) {
      $this->setFolderSync($folder["id"]);
      try {
        $check = $this->checkFolder($folder);
      }
      catch (Exception $e) {
        X::log($e->getMessage(), "user_email_error");
        $check = false;
      }

      if ($check) {
        $isLocale = $this->pref->isLocale(
          $folder["id"],
          $this->pref->getClassCfg()["tables"]["user_options_bits"]
        );
        $db = $isLocale ? $this->getLocaleDb() : $this->db;
        $ret = [
          "added" => 0,
          "deleted" => 0,
          "flagged" => 0
        ];
        $mb = false;
        $info = false;
        try {
          $mb = $this->getMailbox($folder["id_account"]);
          $info = $mb->getInfoFolder($folder["uid"]);
          if (empty($info)) {
            throw new Exception(X::_(
              "Impossible to get the info for folder %s account %s user %s",
              $folder["uid"],
              $folder["id_account"],
              $this->user->getId()
            ));
          }
        }
        catch (Exception $e) {
          X::log($e->getMessage(), "user_email_error");
        }

        if (($info['uidvalidity'] !== $folder['uidvalidity'])
          || ($info['nmsgs'] === 0)
        ) {
          if (!empty($folder["db_num_msg"])) {
            $ret['deleted'] += $db->delete($this->class_table, [$this->fields["id_folder"] => $folder["id"]]);
          }

          if ($info['uidvalidity'] !== $folder['uidvalidity']) {
            $this->setUidValidity($folder["id"], $info['uidvalidity']);
          }

          if ($info['nmsgs'] === 0) {
            $this->setFolderSync($folder["id"], false);
            return $ret;
          }
        }

        // Force a full sync if the last sync start is empty
        if (empty($folder["last_sync_start"])) {
          $first_uid = $mb->getFirstUid();
          $last_uid = $mb->getLastUid();
          $start = $last_uid;
          $limit = !empty($limit) && ($limit > $info['nmsgs']) ? $info['nmsgs'] : $limit;
          $real_end = !empty($limit) && ($info['nmsgs'] > $limit) ? $mb->getMsgUid($mb->getMsgNo($last_uid) - $limit) : $first_uid;
        }
        else if (!empty($folder['last_sync_start']) && empty($folder['last_sync_end'])) {
          
        }
        // If the last sync start is not empty, we can use the modseq to get the changed messages
        else if (!empty($folder['highestmodseq'])) {
          if ($folder['highestmodseq'] === $info['highestmodseq']) {
            $this->setFolderSync($folder["id"], false);
            return $ret;
          }

          if ($msgs = $mb->getMsgChangedSinceModseq($folder['uid'], $folder['highestmodseq'])) {
            foreach ($msgs as $msg) {
              $existingId = $db->selectOne($this->class_table, $this->fields["id"], [
                $this->fields["id_user"] => $this->user->getId(),
                $this->fields["msg_uid"] => $msg["uid"],
                $this->fields["id_folder"] => $folder["id"],
              ]);
              if (empty($existingId)) {
                if (($m = $mb->getMsg($msg["uid"], true))
                  && $this->insertEmail($folder, $m)
                ) {
                  $ret['added']++;
                  yield 'added' => 1;
                }
                else {
                  $this->log(X::_("Impossible to insert the email with ID %s", $m["message_id"]));
                }
              }
              else if ($this->setFlags($existingId, $msg["flags"])) {
                $this->setSeen($existingId, in_array("\\Seen", $msg["flags"]));
                $this->setDraft($existingId, in_array("\\Draft", $msg["flags"]));
                $ret['flagged']++;
                yield 'flagged' => 1;
              }
            }
          }

          $this->setHighestmodseq($folder["id"], $info['highestmodseq']);
          $deleted = count($this->checkAndDeleteEmails($folder["id"]));
          yield 'deleted' => $deleted;
          $ret['deleted'] += $deleted;
          $this->setFolderSync($folder["id"], false);
          return $ret;
        }
        else {
          $first_uid = $mb->getFirstUid();
          $last_uid = $mb->getLastUid();
          $start = null;
          $real_end = null;
          if (isset($folder["db_uid_min"], $folder["db_uid_max"])) {
            if (($folder["db_uid_min"] == $first_uid)
              && ($folder["db_uid_max"] == $last_uid)
            ) {
              if ($info['nmsgs'] != $folder["db_num_msg"]) {
                $ret['deleted'] = count($this->checkAndDeleteEmails($folder["id"])) ?: 0;
              }

              $ret['flagged'] = $this->syncFlags($folder["id"]);
              return $ret;
            }

            if ($folder["db_uid_max"] != $last_uid) {
              $start = $last_uid;
              $real_end = $mb->getNextUid($folder["db_uid_max"]);
            }
            elseif ($folder["db_uid_min"] != $first_uid) {
              $start = $folder["db_uid_min"];
              if (!empty($limit)) {
                $nstart = $mb->getMsgNo($start);
                $nstart -= $limit;
                if ($nstart < 1) {
                  $real_end = $first_uid;
                }
                else {
                  $real_end = $mb->getMsgUid($nstart);
                }
              }
              else {
                $real_end = $first_uid;
              }
            }
          }
          else {
            $start = $last_uid;
            if (!empty($limit)) {
              $nstart = $mb->getMsgNo($start);
              $nstart -= $limit;
              if ($nstart < 1) {
                $real_end = $first_uid;
              }
              else {
                $real_end = $mb->getMsgUid($nstart);
              }
            }
            else {
              $real_end = $first_uid;
            }
          }
        }

        try {
          $start = $mb->getMsgNo($start);
          $real_end = $mb->getMsgNo($real_end < 1 ? $first_uid : $real_end);
        }
        catch (\Exception $e) {
          $start = $mb->getMsgNo($last_uid);
          $real_end = $mb->getMsgNo($first_uid);
          if ($folder["db_uid_min"]
            && ($folder["db_uid_max"] == $last_uid)
          ) {
            $start = $folder["db_uid_min"];
          }
          elseif ($folder["db_uid_max"] != $last_uid) {
            $start = $last_uid;
            $real_end = $mb->getNextUid($folder["db_uid_max"]);
          }
        }

        if (!$start || !$real_end) {
          if ($info['nmsgs'] != $folder["db_num_msg"]) {
            $ret['deleted'] = count($this->checkAndDeleteEmails($folder["id"]));
          }

          $ret['flagged'] = $this->syncFlags($folder["id"]);
          return $ret;
        }

        $end = $start;
        $all = $mb->getEmailsList($folder['uid'], $start, $real_end);
        if ($all) {
          foreach ($all as $i => $a) {
            if ($this->insertEmail($folder, $a)) {
              $ret['added']++;
              yield 'added' => 1;
            }
            else {
              //throw new \Exception(X::_("Impossible to insert the email with ID").' '.$a['message_id']);
              $this->log(X::_("Impossible to insert the email with ID %s", $a["message_id"]));
            }
          }
        }
        else {
          $err = X::_(
            "Impossible to get the emails for folder %s from %s to %s (%s)",
            $folder["uid"],
            $start,
            $end,
            $real_end
          );
          X::log($err, "user_email_error");
          throw new \Exception($err);
        }

        if (!empty($folder["last_sync_start"])) {
          $deleted = count($this->checkAndDeleteEmails($folder["id"]));
          yield 'deleted' => $deleted;
          $ret['deleted'] += $deleted;
          $flags = $this->syncFlags($folder["id"]);
          yield 'flagged' => $flags;
          $ret['flagged'] += $flags;
        }

        if (!empty($ret['added'])) {
          $this->syncThreads($folder["id_account"]);
        }

        $this->setFolderSync($folder["id"], false);
        return $ret;
      }
    }

    return null;
  }

  public function getLastUid(array $folder)
  {
    $mb = $this->getMailbox($folder["id_account"]);
    $mb->selectFolder($folder["uid"]);
    return $mb->getLastUid();
  }

  public function getFirstUid(array $folder)
  {
    $mb = $this->getMailbox($folder["id_account"]);
    $mb->selectFolder($folder["uid"]);
    return $mb->getFirstUid();
  }

  /**
   * Returns a list of emails based on their folder.
   *
   * @param string|array $id_folder
   * @param array $post
   * @param bool $checkFolder
   *
   * @return array|null
   */
  public function getList(
    string|array $id_folder,
    array $post,
    bool $checkFolder = true
  ): ?array
  {
    $ids = [];
    if ($checkFolder) {
      if (is_array($id_folder)) {
        $ids = [];
        foreach ($id_folder as $i) {
          $ids = array_merge($ids, $this->idsFromFolder($i));
        }
      }
      else {
        $ids = $this->idsFromFolder($id_folder);
      }
    }
    else {
      $ids = is_array($id_folder) ? $id_folder : [$id_folder];
    }

    if (!empty($ids)) {
      $filters = [];
      if (count($ids) > 1) {
        $filters = [
          "logic" => "OR",
          "conditions" => [],
        ];
        foreach ($ids as $i) {
          $filters["conditions"][] = [
            "field" => $this->fields["id_folder"],
            "value" => $i,
          ];
        }
      } else {
        $filters[] = [
          "field" => $this->fields["id_folder"],
          "value" => $ids[0],
        ];
      }

      $contactsTable = $this->class_cfg["tables"]["users_contacts"];
      $contactsFields = $this->class_cfg["arch"]["users_contacts"];
      $linksTable = $this->class_cfg["tables"]["users_contacts_links"];
      $linksFields = $this->class_cfg["arch"]["users_contacts_links"];
      $recTable = $this->class_cfg["tables"]["users_emails_recipients"];
      $recFields = $this->class_cfg["arch"]["users_emails_recipients"];
      $prefCgf = $this->pref->getClassCfg();
      $prefOptTable = $prefCgf["tables"]["user_options"];
      $prefOptFields = $prefCgf["arch"]["user_options"];
      $prefOptBitsTable = $prefCgf["tables"]["user_options_bits"];
      $prefOptBitsFields = $prefCgf["arch"]["user_options_bits"];
      $db = $this->pref->isLocale($ids[0], $this->pref->getClassCfg()["tables"]["user_options_bits"])
        ? $this->getLocaleDb()
        : $this->db;
      $grid = new Grid($db, $post, [
        "table" => $this->class_table,
        "fields" => X::mergeArrays(
          array_map(fn($f) => $db->cfn($f, $this->class_table), $this->fields),
          [
            "from" =>
              "CONCAT(" .
              $db->cfn($contactsFields["name"], "fromname") .
              ', " <", ' .
              $db->cfn($linksFields["value"], "fromlink") .
              ', ">")',
            "from_email" => $db->cfn($linksFields["value"], "fromlink"),
            "from_name" => $db->cfn($contactsFields["name"], "fromname"),
            "to" =>
              "GROUP_CONCAT(IFNULL(CONCAT(" .
              $db->cfn($contactsFields["name"], "toname") .
              ', " <", ' .
              $db->cfn($linksFields["value"], "tolink") .
              ', ">"), ' .
              $db->cfn($linksFields["value"], "tolink") .
              '), ", ")',
            "to_email" =>
              "GROUP_CONCAT(" .
              $db->cfn($linksFields["value"], "tolink") .
              ", ', ')",
            "to_name" =>
              "GROUP_CONCAT(" .
              $db->cfn($contactsFields["name"], "toname") .
              ", ', ')",
            "id_account" => $db->cfn($prefOptFields["id"], $prefOptTable)
          ]
        ),
        "join" => [
          [
            "table" => $linksTable,
            "alias" => "fromlink",
            "on" => [
              [
                "field" => $db->cfn($linksFields["id"], "fromlink"),
                "exp" => $db->cfn($this->fields["id_sender"], $this->class_table)
              ]
            ]
          ],
          [
            "table" => $contactsTable,
            "alias" => "fromname",
            "on" => [
              [
                "field" => $db->cfn($contactsFields["id"], "fromname"),
                "exp" => $db->cfn($linksFields["id_contact"], "fromlink")
              ]
            ]
          ],
          [
            "table" => $recTable,
            "alias" => "rec",
            "on" => [
              [
                "field" => $db->cfn($recFields["id_email"], "rec"),
                "exp" => $db->cfn($this->fields["id"], $this->class_table)
              ],
              [
                "field" => $db->cfn($recFields["type"], "rec"),
                "value" => "to"
              ]
            ]
          ],
          [
            "table" => $linksTable,
            "alias" => "tolink",
            "on" => [
              [
                "field" => $db->cfn($linksFields["id"], "tolink"),
                "exp" => $db->cfn($recFields["id_contact_link"], "rec")
              ]
            ]
          ],
          [
            "table" => $contactsTable,
            "alias" => "toname",
            "on" => [
              [
                "field" => $db->cfn($contactsFields["id"], "toname"),
                "exp" => $db->cfn($linksFields["id_contact"], "tolink")
              ]
            ]
          ],
          [
            "table" => $prefOptBitsTable,
            "on" => [
              [
                "field" => $db->cfn($prefOptBitsFields["id"], $prefOptBitsTable),
                "exp" => $db->cfn($this->fields["id_folder"], $this->class_table)
              ]
            ]
          ],
          [
            "table" => $prefOptTable,
            "on" => [
              [
                "field" => $db->cfn($prefOptBitsFields["id_user_option"], $prefOptBitsTable),
                "exp" => $db->cfn($prefOptFields["id"], $prefOptTable)
              ]
            ]
          ]
        ],
        "filters" => $filters,
        "group_by" => [$db->cfn($this->fields["id"], $this->class_table)]
      ]);

      if ($grid->check()) {
        $dataTable = $grid->getDatatable();
        if (!empty($dataTable["data"])) {
          foreach ($dataTable["data"] as $i => $d) {
            $dataTable["data"][$i]["attachments"] = !empty($d["attachments"])
              ? json_decode($d["attachments"], true)
              : [];
            $dataTable["data"][$i]["external_uids"] = !empty($d["external_uids"])
              ? json_decode($d["external_uids"], true)
              : new stdClass();
          }
        }

        return $dataTable;
      }
    }

    return null;
  }

  public function getListAsThreads(string|array $idFolder, array $cfg): ?array
  {
    $res = $this->getList($idFolder, $cfg);
    if (!empty($res["data"])) {
      $grouped = [];
      foreach ($res["data"] as $d) {
        $threadId = $d["id_thread"] ?: $d["id"];
        if (!isset($grouped[$threadId])) {
          $grouped[$threadId] = [];
        }

        $grouped[$threadId][] = $d;
      }

      $numData = count($res["data"]);
      $t = $this;
      function extractIds(array $folders, array $types)
      {
        $res = [];
        foreach ($folders as $f) {
          if (in_array($f["type"], $types)) {
            $res[] = $f["id"];
          }

          if (!empty($f["items"])) {
            $res = array_merge($res, extractIds($f["items"], $types));
          }
        }

        return $res;
      }

      $foldersIds = [];
      $res["data"] = array_values(
        array_map(
          function ($d) use ($t, &$foldersIds) {
            X::sortBy($d, "date", "desc");
            $d = $d[0];
            if (!isset($foldersIds[$d["id_account"]])) {
              $foldersIds[$d["id_account"]] = extractIds(
                $t->getFolders($d["id_account"]),
                ["inbox", "sent", "folders"],
              );
            }

            $threadId = $d["id_thread"] ?: $d["id"];
            $d["thread"] = $t->getThread(
              $threadId,
              $foldersIds[$d["id_account"]]
            );
            return $d;
          },
          $grouped
        )
      );
      $res["total"] -= $numData - count($res["data"]);
    }

    return $res;
  }

  public function getLoginByEmailId(string $id)
  {
    $cfg = $this->class_cfg["arch"]["users_emails"];
    $table = $this->class_cfg["tables"]["users_emails"];
    $db = $this->getRightDb($id, $table);
    $em = $db->rselect($table, $cfg, [$cfg["id"] => $id]);
    if ($em) {
      $folder = $this->getFolder($em["id_folder"], true);
      if ($folder && ($mb = $this->getAccount($folder["id_account"]))) {
        return $mb;
      }
    }
    return null;
  }

  public function getEmail(string $id, bool $force = false): ?array
  {
    $r = null;
    $db = $this->getRightDb($id, $this->class_table);
    $em = $db->rselect(
      $this->class_table,
      [
        $this->fields['id_folder'],
        $this->fields['msg_uid'],
        $this->fields['msg_unique_id'],
        $this->fields['is_draft'],
      ],
      [
        $this->fields["id"] => $id
      ]
    );
    if (!empty($em['id_folder'])
      && ($folder = $this->getFolder($em["id_folder"], true))
    ) {
      if (($force
          || !($r = $this->user->getCache($this->cachePrefix . $id))
          || ($this->getFolderType($folder) === 'drafts'))
        && (($mb = $this->getMailbox($folder["id_account"]))
          && $mb->selectFolder($folder["uid"]))
      ) {
        $r = $mb->getMsg($em["msg_uid"], true);
        if (empty($r)) {
          $db->delete($this->class_table, [$this->fields["id"] => $id]);
          return null;
        }

        $r["id"] = $id;
        $r["id_account"] = $folder["id_account"];
        $r["msg_unique_id"] = Str::toUtf8($em["msg_unique_id"]);
        $r['is_draft'] = $em['is_draft'] ?? 0;
        $r["quote"] = "";
        if (!empty($r["html"])) {
          if (!empty($r['inline'])) {
            $inlineParts = [];
            foreach ($r['inline'] as $p) {
              if (!empty($p['section'])) {
                $inlineParts[$p['section']] = $p['encoding'];
              }
            }

            if (!empty($inlineParts)
              && ($partsContent = $mb->getMsgBodyPartData($em["msg_uid"], array_keys($inlineParts), $inlineParts))
            ) {
              foreach ($r['inline'] as $p) {
                if (!empty($p['section']) && !empty($partsContent[$p['section']])) {
                  $r['html'] = str_replace(
                    'cid:' . $p['id'],
                    'data:' . $p['type'] . ';base64,' . base64_encode($partsContent[$p['section']]),
                    $r['html']
                  );
                }
              }
            }
          }

          $splitQuote = $mb->splitQuoteFromEmail($r["html"]);
          if (!empty($splitQuote["quote"])) {
            $r["html"] = $splitQuote["text"];
            $r["quote"] = $splitQuote["quote"];
          }
        }

        $this->user->setCache($this->cachePrefix . $id, $r, 86400);
        $this->partialClearUserCache();
      }
    }

    return $r;
  }


  public function getEmailIdByUid(string $uid, string $idFolder): ?string
  {
    $isLocale = $this->pref->isLocale($idFolder, $this->pref->getClassCfg()["tables"]["user_options_bits"]);
    $db = $isLocale ? $this->getLocaleDb() : $this->db;
    $where = [
      $this->fields["msg_uid"] => $uid,
      $this->fields["id_folder"] => $idFolder,
    ];
    if (!$isLocale) {
      $where[$this->fields["id_user"]] = $this->user->getId();
    }

    return $db->selectOne($this->class_table, $this->fields["id"], $where);
  }


  public function getEmailIdByUniqueId(string $uid, string $idFolder): ?string
  {
    if (str_starts_with($uid, "<") && str_ends_with($uid, ">")) {
      $uid = Str::sub($uid, 1, Str::len($uid) - 2);
    }

    $isLocale = $this->pref->isLocale(
      $idFolder,
      $this->pref->getClassCfg()["tables"]["user_options_bits"],
    );
    $db = $isLocale ? $this->getLocaleDb() : $this->db;
    $where = [
      $this->fields["msg_unique_id"] => $uid,
      $this->fields["id_folder"] => $idFolder,
    ];
    if (!$isLocale) {
      $where[$this->fields["id_user"]] = $this->user->getId();
    }

    return $db->selectOne($this->class_table, $this->fields["id"], $where);
  }

  public function getEmailByUid(array $post): ?array
  {
    $cfg = $this->class_cfg["arch"]["users_emails"];
    $table = $this->class_cfg["tables"]["users_emails"];
    $grid = new Grid($this->db, $post, [
      "table" => $table,
      "fields" => $cfg,
    ]);

    if ($grid->check()) {
      $res = $grid->getDatatable();
      if ($this->hasLocaleDb()) {
        $grid = new Grid($this->getLocaleDb(), $post, [
          "table" => $table,
          "fields" => $cfg,
        ]);
        if ($grid->check()) {
          $res2 = $grid->getDatatable();
          if (!empty($res2["data"])) {
            $res["data"] = array_merge($res["data"], $res2["data"]);
            $res["total"] += $res2["total"];
          }
        }
      }

      return $res;
    }

    return null;
  }

  public function getEmailFolderId(string $id)
  {
    return $this->getRightDb($id, $this->class_table)->selectOne(
      $this->class_table,
      $this->fields["id_folder"],
      [$this->fields["id"] => $id],
    );
  }

  public function getThreadId(string $id): ?string
  {
    $db = $this->getRightDb($id, $this->class_table);
    $email = $db->rselect(
      $this->class_table,
      [
        $this->fields["id"],
        $this->fields["id_parent"]
      ], [
        $this->fields["id"] => $id
      ]
    );
    $threadId = null;
    while (!empty($email[$this->fields["id_parent"]])) {
      $email = $db->rselect(
        $this->class_table,
        [
          $this->fields["id"],
          $this->fields["id_parent"]
        ],
        [
          $this->fields["id"] => $email[$this->fields["id_parent"]]
        ]
      );
      if (!empty($email) && ($email[$this->fields["id"]] !== $id)) {
        $threadId = $email[$this->fields["id"]];
      }
    }

    return $threadId;
  }

  public function getThread(string $idThread, array $foldersIds): array
  {
    return $this->getList(
      $foldersIds,
      [
        "filters" => [
          "logic" => "OR",
          "conditions" => [
            [
              "field" => $this->fields["id"],
              "value" => $idThread,
            ],
            [
              "field" => $this->fields["id_thread"],
              "value" => $idThread,
            ],
          ],
        ],
        "order" => [
          $this->fields["date"] => "DESC",
        ],
      ],
      false
    )["data"] ?? [];
  }

  public function setSeen(string $id, bool $seen = true): bool
  {
    $cfg = $this->class_cfg["arch"]["users_emails"];
    $table = $this->class_cfg["tables"]["users_emails"];
    $db = $this->getRightDb($id, $table);
    return (bool)$db->update($table, [$cfg["is_read"] => $seen ? 1 : 0], [$cfg["id"] => $id]);
  }


  public function setDraft(string $id, bool $draft = true): bool
  {
    $cfg = $this->class_cfg["arch"]["users_emails"];
    $table = $this->class_cfg["tables"]["users_emails"];
    $db = $this->getRightDb($id, $table);
    return (bool)$db->update($table, [$cfg["is_draft"] => $draft ? 1 : 0], [$cfg["id"] => $id]);
  }


  public function setFlags(string $id, string|array $flags = []): bool
  {
    $cfg = $this->class_cfg["arch"]["users_emails"];
    $table = $this->class_cfg["tables"]["users_emails"];
    $db = $this->getRightDb($id, $table);
    if (empty($flags)) {
      $flags = null;
    }
    else if (is_array($flags)) {
      $flags = implode(' ', $flags);
    }

    return (bool)$db->update($table, [$cfg["flags"] => $flags], [$cfg["id"] => $id]);
  }


  public function syncThreads(string $idAccount, int $limit = 0): int
  {
    $accountsTable = $this->pref->getClassCfg()["tables"]["user_options"];
    $foldersTable = $this->pref->getClassCfg()["tables"]["user_options_bits"];
    $foldersFields = $this->pref->getClassCfg()["arch"]["user_options_bits"];
    $isLocale = $this->pref->isLocale($idAccount, $accountsTable);
    $db = $isLocale ? $this->getLocaleDb() : $this->db;
    $did = 0;
    // select all emails of the user where id_thread is null and external_id is not null
    if (
      $emails = $db->rselectAll([
        "table" => $this->class_table,
        "fields" => [
          $this->fields['id'],
          $this->fields['id_parent'],
          $this->fields["id_thread"],
          $this->fields['external_uids']
        ],
        "join" => [[
          "table" => $foldersTable,
          "on" => [[
            "field" => $db->cfn($foldersFields["id"], $foldersTable),
            "exp" => $db->cfn($this->fields["id_folder"], $this->class_table)
          ], [
            "field" => $db->cfn($foldersFields["id_user_option"], $foldersTable),
            "value" => $idAccount
          ]]
        ]],
        "where" => [[
          "field" => $db->cfn($this->fields["id_user"], $this->class_table),
          "value" => $this->user->getId(),
        ], [
          "field" => $db->cfn($this->fields["external_uids"], $this->class_table),
          "operator" => "isnotnull",
        ], [
          "field" => $db->cfn($this->fields["id_thread"], $this->class_table),
          "operator" => "isnull",
        ]],
        "order" => [
          $db->cfn($this->fields["date"], $this->class_table) => "DESC",
        ],
        "limit" => $limit,
      ])
    ) {
      foreach ($emails as $email) {
        if (!empty($email[$this->fields["id_parent"]])) {
          continue;
        }

        $toUpd = [];
        $external_uids = !empty($email[$this->fields["external_uids"]])
          ? json_decode($email[$this->fields["external_uids"]], true)
          : [];
        if (!empty($external_uids["in_reply_to"])
          && ($parentId = $db->selectOne($this->class_table, $this->fields["id"], [
            $this->fields["msg_unique_id"] => $external_uids["in_reply_to"],
            $this->fields["id_user"] => $this->user->getId()
          ]))
        ) {
          $toUpd[$this->fields["id_parent"]] = $parentId;
        }

        if (!empty($toUpd)) {
          $did += $db->update(
            $this->class_table,
            $toUpd,
            [
              $this->fields["id"] => $email[$this->fields["id"]]
            ]
          );
        }
      }

      foreach ($emails as $email) {
        if (($threadId = $this->getThreadId($email[$this->fields["id"]]))
          && ($email[$this->fields["id_thread"]] !== $threadId)
        ) {
          $db->update(
            $this->class_table,
            [
              $this->fields["id_thread"] => $threadId
            ],
            [
              $this->fields["id"] => $email[$this->fields["id"]]
            ]
          );
        }
      }
    }

    return $did;
  }

  public function insertEmail(array $folder, array $email)
  {
    $id = false;
    if (X::hasProps($email, ["from", "uid"])) {
      $isLocale = $this->pref->isLocale(
        $folder["id"],
        $this->pref->getClassCfg()["tables"]["user_options_bits"]
      );
      $db = $isLocale ? $this->getLocaleDb() : $this->db;
      $cfg = $this->class_cfg["arch"]["users_emails"];
      $table = $this->class_cfg["tables"]["users_emails"];
      $existing = $db->selectOne($table, $cfg["id"], [
        $cfg["id_user"] => $this->user->getId(),
        $cfg["msg_uid"] => $email["uid"],
        $cfg["id_folder"] => $folder["id"],
      ]);
      $uid = $email["uid"];
      foreach (Mailbox::getDestFields() as $df) {
        if (!empty($email[$df])) {
          foreach ($email[$df] as &$dest) {
            if ($id = $this->retrieveEmail($dest["email"], $isLocale)) {
              $sent_opt = X::getField(self::getFolderTypes(), ["code" => "sent"], "id");
              if ($sent_opt === $folder["id_option"]) {
                $this->addSentToLink($id, date("Y-m-d H:i:s", strtotime($email["date"])));
              }
            } elseif (
              !($id = $this->addContactFromMail($dest, false, $isLocale))
            ) {
              X::log(X::_("Impossible to add contact from mail %s", $dest["email"]), "user_email_error");
            }

            $dest["id"] = $id;
          }

          if ($df === "from") {
            $id_sender = $id;
          }
        }
      }

      if (!empty($id_sender)) {
        $id_parent = null;
        $id_thread = null;
        $external = null;
        if (!empty($email["in_reply_to"]) || !empty($email["references"])) {
          $external = [
            "in_reply_to" => $email["in_reply_to"] ?? null,
            "references" => $email["references"] ?? null,
          ];
        }

        $excerpt = trim(
          normalizer_normalize(
            Str::toUtf8(
              $email["plain"] ?:
              (!empty($email["html"])
                ? Str::html2text(quoted_printable_decode($email["html"]))
                : "")
            )
          )
        );
        if (Str::len($excerpt) > 65500) {
          $excerpt = Str::sub($excerpt, 0, 65500);
        }

        $flags =empty($email['flags'])
          ? []
          : (is_array($email['flags'])
            ? $email['flags']
            : explode(' ', $email['flags']));
        $ar = [
          $cfg["id_user"] => $this->user->getId(),
          $cfg["id_folder"] => $folder["id"],
          $cfg["msg_uid"] => $email["uid"],
          $cfg["msg_unique_id"] => Str::toUtf8($email["message_id"]),
          $cfg["date"] => date("Y-m-d H:i:s", strtotime($email["date"])),
          $cfg["id_sender"] => $id_sender,
          $cfg["subject"] => $email["subject"] ?: "",
          $cfg["size"] => $email["size"] ?? 0,
          $cfg["attachments"] => empty($email["attachments"])
            ? null
            : json_encode($email["attachments"]),
          $cfg['flags'] => !empty($flags) ? implode(' ', $flags) : null,
          $cfg['is_read'] => in_array("\\Seen", $flags) ? 1 : 0,
          $cfg["is_draft"] => in_array("\\Draft", $flags) ? 1 : 0,
          $cfg['priority'] => $email['priority'] ?? 3,
          $cfg["id_parent"] => $id_parent,
          $cfg["id_thread"] => $id_thread,
          $cfg["external_uids"] => $external ? json_encode($external) : null,
          $cfg["excerpt"] => $excerpt
        ];
        if ($existing) {
          $id = $existing;
        }
        elseif ($db->insert($table, $ar)) {
          $id = $db->lastId();
          foreach (Mailbox::getDestFields() as $df) {
            if (in_array($df, ["to", "cc", "bcc"]) && !empty($email[$df])) {
              foreach ($email[$df] as $dest) {
                if (!empty($dest["id"])) {
                  $this->addLinkToMail($id, $dest["id"], $df);
                }
              }
            }
          }
        }
      }
    }

    return $id;
  }

  public function deleteEmail(string $id): bool
  {
    $db = $this->getRightDb($id, $this->class_table);
    $msgUid = $db->selectOne($this->class_table, $this->fields["msg_uid"], [
      $this->fields["id"] => $id,
    ]);
    $folderId = $this->getEmailFolderId($id);
    if (
      !empty($msgUid) &&
      !empty($folderId) &&
      ($folder = $this->getFolder($folderId, true)) &&
      !empty($folder["id_account"]) &&
      ($mb = $this->getMailbox($folder["id_account"]))
    ) {
      $mb->selectFolder($folder["uid"]);
      $d1 = $mb->deleteMsg($msgUid) && $mb->expunge();
      $d2 = $db->delete($this->class_table, [$this->fields["id"] => $id]);
      if ($this->user->hasCache($this->cachePrefix . $id)) {
        $this->user->deleteCache($this->cachePrefix . $id);
      }

      return $d1 && $d2;
    }

    return false;
  }

  public function addContactFromMail(
    array $dest,
    bool $blacklist = false,
    bool $isLocale = false,
  ): ?string {
    if (X::hasProp($dest, "email", true)) {
      if (!Str::isEmail($dest["email"])) {
        return null;
      }

      $cfg_contacts = $this->class_cfg["arch"]["users_contacts"];
      $table_contacts = $this->class_cfg["tables"]["users_contacts"];
      $table_links = $this->class_cfg["tables"]["users_contacts_links"];
      $db = $isLocale ? $this->getLocaleDb() : $this->db;
      if (
        $db->insert($table_contacts, [
          $cfg_contacts["id_user"] => $this->user->getId(),
          $cfg_contacts["name"] => empty($dest["name"])
            ? null
            : Str::sub($dest["name"], 0, 100),
          $cfg_contacts["blacklist"] => $blacklist ? 1 : 0,
        ])
      ) {
        $id_contact = $db->lastId();
        if (
          $db->insert($table_links, [
            "id_contact" => $id_contact,
            "type" => "email",
            "value" => $dest["email"],
          ])
        ) {
          return $db->lastId();
        }
      }
    }

    return null;
  }

  public function getLink(string $id): ?array
  {
    $cfg = $this->class_cfg["arch"]["users_contacts_links"];
    $table = $this->class_cfg["tables"]["users_contacts_links"];
    $db = $this->getRightDb($id, $table);
    return $db->rselect($table, $cfg, [$cfg["id"] => $id]) ?: null;
  }

  public function addLinkToMail(
    string $id_email,
    string $id_link,
    string $type,
  ): bool {
    $cfg = $this->class_cfg["arch"]["users_emails_recipients"];
    $table = $this->class_cfg["tables"]["users_emails_recipients"];
    $db = $this->getRightDb(
      $id_email,
      $this->class_cfg["tables"]["users_emails"],
    );
    return (bool) $db->insertIgnore($table, [
      $cfg["id_email"] => $id_email,
      $cfg["id_contact_link"] => $id_link,
      $cfg["type"] => $type,
    ]);
  }

  public function addSentToLink(string $id_link, string|null $date = null): bool
  {
    if ($link = $this->getLink($id_link)) {
      $cfg = $this->class_cfg["arch"]["users_contacts_links"];
      $table = $this->class_cfg["tables"]["users_contacts_links"];
      $db = $this->getRightDb($id_link, $table);
      if (!$date) {
        $date = date("Y-m-d H:i:s");
      }
      if ($link["last_sent"] && $link["last_sent"] > $date) {
        $date = $link["last_sent"];
      }

      return (bool) $db->update(
        $table,
        [
          $cfg["num_sent"] => $link[$cfg["num_sent"]] + 1,
          $cfg["last_sent"] => $date,
        ],
        [
          "id" => $id_link,
        ],
      );
    }

    return false;
  }

  public function retrieveEmail(string $email, bool $isLocale = false): ?string
  {
    if (Str::isEmail($email)) {
      $contacts = $this->class_cfg["tables"]["users_contacts"];
      $contactsFields = $this->class_cfg["arch"]["users_contacts"];
      $links = $this->class_cfg["tables"]["users_contacts_links"];
      $linksFields = $this->class_cfg["arch"]["users_contacts_links"];
      $db = $isLocale ? $this->getLocaleDb() : $this->db;
      return $db->selectOne([
        "table" => $links,
        "fields" => [$db->cfn($linksFields["id"], $links)],
        "join" => [
          [
            "table" => $contacts,
            "on" => [
              [
                "field" => $linksFields["id_contact"],
                "exp" => $db->cfn($contactsFields["id"], $contacts),
              ],
            ],
          ],
        ],
        "where" => [
          "value" => $email,
          "id_user" => $this->user->getId(),
          "type" => "email",
        ],
      ]);
    }

    return null;
  }

  public function getContact(string $email, string $name, $force) {}

  public function getContacts(bool $onlyLocale = false): array
  {
    if ($onlyLocale && !$this->hasLocaleDb()) {
      return [];
    }

    $contacts = $this->class_cfg["tables"]["users_contacts"];
    $cfg_c = $this->class_cfg["arch"]["users_contacts"];
    $links = $this->class_cfg["tables"]["users_contacts_links"];
    $cfg_l = $this->class_cfg["arch"]["users_contacts_links"];
    $db = $onlyLocale ? $this->getLocaleDb() : $this->db;
    $rows = $db->rselectAll([
      "tables" => [$links],
      "fields" => [
        $db->cfn($cfg_l["id"], $links),
        $db->cfn($cfg_l["value"], $links),
        $db->cfn($cfg_l["id_contact"], $links),
        $db->cfn($cfg_l["num_sent"], $links),
        $db->cfn($cfg_l["last_sent"], $links),
        $db->cfn($cfg_c["name"], $contacts),
        $db->cfn($cfg_c["cfg"], $contacts),
        $db->cfn($cfg_c["blacklist"], $contacts),
        "sortIndex" =>
          "IFNULL(" .
          $db->cfn($cfg_c["name"], $contacts, true) .
          "," .
          $db->cfn($cfg_l["value"], $links) .
          ")"
      ],
      "join" => [
        [
          "table" => $contacts,
          "on" => [
            [
              "field" => $cfg_l["id_contact"],
              "exp" => $db->cfn($cfg_c["id"], $contacts)
            ]
          ]
        ]
      ],
      "where" => [
        "id_user" => $this->user->getId(),
        "type" => "email"
      ],
      "order" => [
        "sortIndex" => "ASC"
      ]
    ]);
    $res = [];
    if ($rows) {
      foreach ($rows as $r) {
        $res[] = [
          "value" => $r["id"],
          "text" => (empty($r["name"]) ? "" : $r["name"] . " - ") . $r["value"],
          "cfg" => empty($r["cfg"]) ? [] : json_decode($r["cfg"], true),
          "id_contact" => $r["id_contact"],
          "num_sent" => $r["num_sent"],
          "last_sent" => $r["last_sent"],
          "blacklist" => $r["blacklist"],
        ];
      }
    }

    if (!$onlyLocale) {
      $res = array_merge($res, $this->getContacts(true));
      X::sortBy($res, "sortIndex", "asc");
    }

    return $res;
  }

  public function syncFolders(
    string $id_account,
    array $subscribed = [],
    array $rules = [],
  ) {
    // get Mailbox account
    if ($mb = $this->getMailbox($id_account)) {
      // get the parameter (host and port)
      $mbParams = $mb->getParams();
      // get the option 'folders'
      $types = self::getFolderTypes();
      if (empty($subscribed)) {
        $subscribed = array_map(
          fn($f) => Str::sub($f, Str::len($mbParams)),
          $mb->listAllSubscribed()
        );
      }

      if (empty($rules)) {
        $rules = $this->getFoldersRules($id_account);
      }

      $put_in_res = function (array $a, &$res, $prefix = "") use (
        &$put_in_res,
        $subscribed,
        $mb,
      ): void {
        // set the first value of $a in $ele and remove it in the array
        $ele = array_shift($a);
        // search if res contain an array with 'text' => $ele and return the index or null instead
        $idx = X::search($res, ["text" => $ele]);

        if (null === $idx) {
          // count number of element in array (useless ?)
          $idx = count($res);
          $info = $mb->getInfoFolder($prefix . $ele);
          $mb->selectFolder($prefix . $ele);
          // add $ele in the res array
          $res[] = [
            "text" => $ele,
            "uid" => $prefix . $ele,
            "items" => [],
            "subscribed" => in_array($prefix . $ele, $subscribed),
            "num_msg" => $info['nmsgs'],
            "last_uid" => !empty($info['nmsgs'])
              ? $mb->getMsgUid($info['nmsgs'])
              : null,
            "uidvalidity" => $info['uidvalidity'] ?? null,
            "uidnext" => $info['uidnext'] ?? null,
            "highestmodseq" => $info['highestmodseq'] ?? null
          ];
        }

        if (count($a)) {
          $put_in_res($a, $res[$idx]["items"], $prefix . $ele . ".");
        }
      };

      $compare = function (
        array $real,
        array $db,
        array|null &$res = null,
        $id_parent = null,
      ) use (&$compare, $rules, $types): array {
        if (!$res) {
          $res = [
            "add" => [],
            "update" => [],
            "delete" => [],
          ];
        }

        foreach ($real as $r) {
          $idx = X::search($db, ["text" => $r["text"]]);
          if (is_null($idx) && !empty($r["subscribed"])) {
            if ($id_parent) {
              $r["id_parent"] = $id_parent;
            }

            $res["add"][] = $r;
          } elseif (!is_null($idx) && empty($r["subscribed"])) {
            $res["delete"][] = $db[$idx];
          } elseif (!is_null($idx)) {
            $u = [
              "id" => $db[$idx]["id"],
            ];
            if (
              !array_key_exists("num_msg", $db[$idx]) ||
              !array_key_exists("last_uid", $db[$idx]) ||
              $r["num_msg"] !== $db[$idx]["num_msg"] ||
              $r["last_uid"] !== $db[$idx]["last_uid"] ||
              !array_key_exists("subscribed", $db[$idx]) ||
              $r["subscribed"] !== $db[$idx]["subscribed"]
            ) {
              $u = X::mergeArrays($u, [
                "num_msg" => $r["num_msg"],
                "last_uid" => $r["last_uid"],
                "subscribed" => $r["subscribed"],
              ]);
            }

            if (!empty($rules)) {
              $typeCode = X::getField($types, ["id" => $db[$idx]["id_option"]], "code");
              if (
                ($c = array_search($db[$idx]["uid"], $rules)) &&
                $c !== $typeCode
              ) {
                $u["id_option"] = X::getField($types, ["code" => $c], "id");
              } elseif (
                !empty($rules[$typeCode]) &&
                $rules[$typeCode] !== $db[$idx]["uid"]
              ) {
                $u["id_option"] = X::getField($types, ["code" => "folders"], "id");
              }
            }

            if (count($u) > 1) {
              $res["update"][] = $u;
            }

            if ($r["items"] && $db[$idx]["items"]) {
              $compare($r["items"], $db[$idx]["items"], $res, $db[$idx]["id"]);
            }
          }
        }

        foreach ($db as $r) {
          $idx = X::search($real, ["text" => $r["text"]]);
          if (is_null($idx)) {
            $res["delete"][] = $r;
          }
        }

        return $res;
      };

      $pref = $this->pref;

      $import = function (array $to_add, $id_parent = null) use (
        $id_account,
        &$pref,
        &$import,
        &$types,
        $rules
      ): void {
        foreach ($to_add as $a) {
          if ($id_parent) {
            $a["id_parent"] = $id_parent;
            $a["id_option"] = X::getField($types, ["code" => "folders"], "id");
            $a["num"] = $pref->getMaxBitNum($id_account, $id_parent) + 1;
          } else {
            if (!empty($rules) && ($rule = array_search($a["uid"], $rules))) {
              $tmpRule = X::getRow($types, ["code" => $rule]);
              $a["id_option"] = $tmpRule["id"];
              $a["num"] = $tmpRule["num"] ?: null;
            } else {
              foreach ($types as $type) {
                if (!empty($type["names"])) {
                  if (
                    in_array(
                      strtolower($a["text"]),
                      array_map(fn($n) => strtolower($n), $type["names"]),
                      true,
                    )
                  ) {
                    if (empty($rules) || empty($rules[$type["code"]])) {
                      $a["id_option"] = $type["id"];
                      $a["num"] = $type["num"] ?: null;
                    }

                    break;
                  }
                }
              }
            }

            if (!isset($a["id_option"])) {
              $tmpType = X::getRow($types, ["code" => "folders"]);
              $a["id_option"] = $tmpType["id"];
              if (empty($a["num"])) {
                $a["num"] = $tmpType["num"] ?: null;
                $tmpMax = $pref->getMaxBitNum($id_account, $a["id_parent"] ?? null);
                if ($tmpMax >= $a["num"]) {
                  $a['num'] = $tmpMax + 1;
                }
              }
            }
          }

          if ($id_bit = $pref->addBit($id_account, $a)) {
            if (!empty($a["items"])) {
              $import($a["items"], $id_bit);
            }
          }
        }
      };

      $update = function (array $toUpdate) use ($pref): void {
        foreach ($toUpdate as $u) {
          $pref->updateBit($u["id"], $u);
        }
      };

      $remove = function (array $toRemove) use ($pref): void {
        foreach ($toRemove as $r) {
          $pref->deleteBit($r["id"]);
        }
      };

      $res = [];
      $all = $mb->listAllFolders();
      foreach ($all as $dir) {
        $tmp = Str::replace($mbParams, "", $dir);
        $bits = X::split($tmp, ".");
        $put_in_res($bits, $res);
      }

      $db_tree = $this->pref->getFullBits($id_account);
      $result = $compare($res, $db_tree);
      $import($result["add"]);
      $update($result["update"]);
      $remove($result["delete"]);
      return [
        "real" => $res,
        "db" => $this->pref->getFullBits($id_account),
        "compare" => $result,
      ];
    }

    return null;
  }

  public function send(string $idAccount, array $cfg): int
  {
    if (
      ($mb = $this->getMailbox($idAccount)) &&
      ($mailerCfg = $this->getMailerCfg($idAccount, $cfg)) &&
      ($mailer = $mb->getMailer($mailerCfg)) &&
      !empty($cfg["to"]) &&
      (!empty($cfg["title"]) || !empty($cfg["text"]))
    ) {
      return $mailer->send($cfg);
    }

    throw new \Exception(X::_("Impossible to find the mailbox"));
  }

  public function getStructure($id_account, $force) {}

  public function getAttachments(string $id, ?string $filename = null): ?array
  {
    if (
      ($db = $this->getRightDb($id, $this->class_table))
      && ($em = $db->rselect($this->class_table, [
        $this->fields['id_folder'],
        $this->fields['msg_uid']
      ], [
        $this->fields["id"] => $id,
      ]))
      && ($folder = $this->getFolder($em["id_folder"], true))
      && ($mb = $this->getMailbox($folder["id_account"]))
      && $mb->selectFolder($folder["uid"])
    ) {
      return $mb->getAttachments($em["msg_uid"], $filename, true);
    }

    return null;
  }

  public function moveEmailToFolder(string $idEmail, string $idFolder): bool
  {
    $db = $this->getRightDb($idEmail, $this->class_table);
    $email = $db->rselect(
      $this->class_table,
      [$this->fields["id_folder"], $this->fields["msg_uid"]],
      [
        $this->fields["id"] => $idEmail,
      ],
    );
    if (
      empty($email) ||
      empty($email[$this->fields["id_folder"]]) ||
      !isset($email[$this->fields["msg_uid"]]) ||
      !Str::isInteger($email[$this->fields["msg_uid"]])
    ) {
      throw new \Exception(
        X::_(
          "Impossible to find the email or the folder ID or the message UID for the email ID: %s",
          $idEmail,
        ),
      );
    }

    if ($email[$this->fields["id_folder"]] === $idFolder) {
      throw new \Exception(
        X::_("The email is already in the selected folder."),
      );
    }

    if (!($folderSrc = $this->getFolder($email[$this->fields["id_folder"]]))) {
      throw new \Exception(
        X::_(
          "Impossible to find the source folder for the email ID: %s",
          $idEmail,
        ),
      );
    }

    if (!($folderDest = $this->getFolder($idFolder))) {
      throw new \Exception(
        X::_("Impossible to find the destination folder ID: %s", $idFolder),
      );
    }

    if ($folderSrc["id_account"] !== $folderDest["id_account"]) {
      throw new \Exception(
        X::_(
          "The source and destination folders must belong to the same account.",
        ),
      );
    }

    if (!($mb = $this->getMailbox($folderSrc["id_account"]))) {
      throw new \Exception(
        X::_(
          "Impossible to find the mailbox for the account ID: %s",
          $folderSrc["id_account"],
        ),
      );
    }

    if (!$mb->selectFolder($folderSrc["uid"])) {
      throw new \Exception(
        X::_(
          "Impossible to select the source folder '%s' in the mailbox.",
          $folderSrc["text"],
        ),
      );
    }

    if (!$mb->moveMsg($email[$this->fields["msg_uid"]], $folderDest["uid"])) {
      throw new \Exception(
        X::_(
          "Impossible to move the email ID: %s to the folder '%s'.",
          $idEmail,
          $folderDest["text"],
        ),
      );
    }

    if (!$mb->expunge()) {
      throw new \Exception(
        X::_(
          "Impossible to expunge the mailbox after moving the email ID: %s to the folder '%s'.",
          $idEmail,
          $folderDest["text"],
        ),
      );
    }

    if (
      !$db->update(
        $this->class_table,
        [
          $this->fields["id_folder"] => $idFolder,
        ],
        [
          $this->fields["id"] => $idEmail,
        ],
      )
    ) {
      throw new \Exception(
        X::_(
          "Impossible to update the database after moving the email ID: %s to the folder '%s'.",
          $idEmail,
          $folderDest["text"],
        ),
      );
    }

    return (bool) $this->syncFolders($folderSrc["id_account"]);
  }

  public function saveDraft(string $idAccount, array $mail): ?string
  {
    if (
      !($rules = $this->getFoldersRules($idAccount)) ||
      empty($rules["drafts"])
    ) {
      throw new \Exception(
        X::_("No drafts folder defined for the account ID: %s", $idAccount),
      );
    }

    $draftFolder = $rules["drafts"];
    if (!($mb = $this->getMailbox($idAccount))) {
      throw new \Exception(
        X::_(
          "Impossible to find the mailbox for the account ID: %s",
          $idAccount,
        ),
      );
    }

    if (!$mb->selectFolder($draftFolder)) {
      throw new \Exception(
        X::_(
          "Impossible to select the drafts folder '%s' in the mailbox.",
          $draftFolder,
        ),
      );
    }

    if (!($mailerCfg = $this->getMailerCfg($idAccount, $mail))) {
      throw new \Exception(
        X::_(
          "Impossible to get the mailer configuration for the account ID: %s",
          $idAccount,
        ),
      );
    }

    if (!($mailer = $mb->getMailer($mailerCfg))) {
      throw new \Exception(
        X::_("Impossible to get the mailer for the account ID: %s", $idAccount),
      );
    }

    try {
      if ($id = $mailer->draft($mail)) {
        if (str_starts_with($id, "<") && str_ends_with($id, ">")) {
          $id = Str::sub($id, 1, Str::len($id) - 2);
        }

        return $id;
      }
    } catch (\Exception $e) {
      throw new \Exception(
        X::_(
          "Impossible to save draft for the account ID: %s: %s",
          $idAccount,
          $e->getMessage(),
        ),
      );
    }

    return null;
  }


  public function idle(
    string $idAccount,
    callable $callback,
    ?Controller $ctrl = null,
    ?int $timeout = null
  )
  {
    if (($mailbox = $this->getMailbox($idAccount))
      && ($inboxUid = $this->getInboxUid($idAccount))
    ) {
      $this->idleLastSync = 0;
      $mailbox->idle(
        $inboxUid,
        fn($d) => $this->idleCallback($idAccount, $callback, $d, $ctrl),
        $timeout
      );
    }
  }


  public function stopIdle(string $idAccount): bool
  {
    if (($mailbox = $this->getMailbox($idAccount))
      && ($inboxUid = $this->getInboxUid($idAccount))
    ) {
      if ($mailbox->isIdleRunning($inboxUid)) {
        $mailbox->stopIdle($inboxUid);
      }

      return !$mailbox->isIdleRunning($inboxUid);
    }

    return false;
  }


  protected function idleCallback(
    string $idAccount,
    callable $callback,
    array $data,
    ?Controller $ctrl = null
  )
  {
    if (!empty($data['action'])) {
      $action = $data['action'];
      $subdata = $data['data'] ?? [];
      if ($action === 'ping') {
        return !empty($ctrl) ? $ctrl->pingStream() : true;
      }
      else if ($action === 'idleStarted') {
        return $callback(['action' => $action]);
      }
      else if ($mb = $this->getMailbox($idAccount)) {
        switch ($action) {
          case 'newMail':
          case 'mailDeleted':
          case 'mailFlagged':
            if (($inboxUid = $this->getInboxUid($idAccount))
              && ($folder = $this->getFolderByUid($idAccount, $inboxUid))
              && !empty($folder['last_sync_end'])
            ) {
              $msgn = !empty($subdata['msgn']) ? $subdata['msgn'] : null;
              $toCallbackData = [];
              switch ($action) {
                case 'newMail':
                  $t = null;
                  if ($this->checkFolder($folder)
                    && !empty($msgn)
                  ) {
                    foreach ($mb->getEmailsList($folder['uid'], $msgn, $msgn) as $e) {
                      if ($newId = $this->insertEmail($folder, $e)) {
                        $t = $newId;
                      }
                    }
                  }

                  if (!empty($t)) {
                    $toCallbackData = [
                      'id' => $t,
                      'folder' => $this->getFolder($folder['id'])
                    ];
                  }

                  break;
                case 'mailDeleted':
                  $this->setFolderSync($folder['id']);
                  if ($deleted = $this->checkAndDeleteEmails($folder['id'])) {
                    $toCallbackData = [
                      'ids' => $deleted,
                      'folder' => $this->getFolder($folder['id'])
                    ];
                  }

                  $this->setFolderSync($folder['id'], false);
                  break;
                case 'mailFlagged':
                  if (isset($subdata['flags'])
                    && !empty($msgn)
                    && ($emailUid = $mb->getMsgUid($msgn))
                    && ($emailId = $this->getEmailIdByUid($emailUid, $folder['id']))
                  ) {
                    if ($this->setSeen($emailId, in_array('\\Seen', $subdata['flags']))) {
                      $toCallbackData['id'] = $emailId;
                      $toCallbackData[in_array('\\Seen', $subdata['flags']) ? 'seen' : 'unseen'] = true;
                    }

                    if ($this->setDraft($emailId, in_array('\\Draft', $subdata['flags']))) {
                      $toCallbackData['id'] = $emailId;
                      $toCallbackData[in_array('\\Draft', $subdata['flags']) ? 'draft' : 'undraft'] = true;
                    }

                    $flags = array_values(
                      array_filter(
                        $subdata['flags'],
                        fn($flag) => !in_array($flag, ['\\Seen', '\\Deleted', '\\Draft', '\\Recent'])
                      )
                    );
                    if ($this->setFlags($emailId, $flags)) {
                      $toCallbackData['id'] = $emailId;
                      $toCallbackData['flags'] = $flags;
                    }
                  }

                  $toCallbackData['folder'] = $this->getFolder($folder['id']);
                  break;
              }

              if (!empty($toCallbackData)) {
                $callback([
                  'action' => $action,
                  'data' => $toCallbackData
                ]);
              }
            }

            break;
          case 'syncSubscribedFolders':
            if (($this->idleLastSync + $this->idleSyncFrequency) <= time()) {
              $this->idleLastSync = time();
              $mbParams = $mb->getParams();
              if ($subscribed = array_map(
                fn($f) => Str::sub($f, Str::len($mbParams)),
                $mb->listAllSubscribed()
              )) {
                $this->idleSyncing = true;
                foreach ($subscribed as $folderUid) {
                  if (($folder = $this->getFolderByUid($idAccount, $folderUid))
                    && !empty($folder['last_sync_end'])
                  ) {
                    $sync = $this->syncEmails($folder);
                    foreach ($sync as $s) {}
                    $syncRes = $sync->getReturn();
                    if (array_filter($syncRes, fn($r) => !empty($r))) {
                      $callback([
                        'action' => $action,
                        'data' => [
                          'folder' => $folder
                        ]
                      ]);
                    }
                  }
                }

                $this->idleSyncing = false;
              }
            }

            break;
        }
      }
    }
  }


  protected function idsFromFolder(string $id_folder): ?array
  {
    $types = self::getFolderTypes();
    if ($common_folder = X::getRow($types, ["id" => $id_folder])) {
      $ids = [];
      $accounts = $this->getAccounts();
      foreach ($accounts as $a) {
        foreach ($this->getFolders($a["id"]) as $f) {
          if ($f["id_option"] === $common_folder["id"]) {
            $ids[] = $f["id"];
          }
        }
      }
    } elseif (Str::isUid($id_folder)) {
      $bit = $this->pref->getBit($id_folder);
      if (!$bit) {
        // It's not a folder but an account
        //if ($pref = $this->pref->get($id_folder)) {
          // we look for inbox
        //}
      } else {
        $ids = [$id_folder];
      }
    } elseif ($id_folder === "conversations") {
      $inbox = X::getRow($types, ["code" => "inbox"]);
      $sent = X::getRow($types, ["code" => "sent"]);
      $ids = [];
      $accounts = $this->getAccounts();
      foreach ($accounts as $a) {
        foreach ($this->getFolders($a["id"]) as $f) {
          if (
            $f["id_option"] === $inbox["id"] ||
            $f["id_option"] === $sent["id"]
          ) {
            $ids[] = $f["id"];
          }
        }
      }
    }

    if (!empty($ids)) {
      return $ids;
    }

    return null;
  }

  protected function setAccountSync(
    string $idAccount,
    string $idFolder,
    string $folderName,
    int $numMsg
  ): bool {
    if ($account = $this->pref->get($idAccount)) {
      if (!isset($account["sync"])) {
        $account["sync"] = [];
      }

      if (!isset($account["sync"]["folders"])) {
        $account["sync"]["folders"] = [];
      }

      $db = empty($account[$this->localeField])
        ? $this->db
        : $this->getLocaleDb();
      $account["sync"]["folders"][$idFolder] = [
        "id" => $idFolder,
        "name" => $folderName,
        "db_msg" => $db->count($this->class_table, [
          $this->fields["id_folder"] => $idFolder,
        ]),
        "msg" => $numMsg,
      ];
      return (bool) $this->pref->set($idAccount, $account);
    }

    return false;
  }


  protected function setFolderSync(string $idFolder, bool $synchronizing = true): bool
  {
    if ($bit = $this->pref->getBit($idFolder)) {
      $bit['synchronizing'] = $synchronizing;
      $bit['last_sync_' . ($synchronizing ? 'start' : 'end')] = microtime(true);
      $bit['id_parent'] = !empty($bit['id_parent']) ? $bit['id_parent'] :  null;
      return (bool)$this->pref->updateBit($idFolder, $bit);
    }

    return false;
  }


  protected function setHighestmodseq(string $idFolder, int $highestmodseq): bool
  {
    if ($bit = $this->pref->getBit($idFolder)) {
      $bit['highestmodseq'] = $highestmodseq;
      $bit['id_parent'] = !empty($bit['id_parent']) ? $bit['id_parent'] :  null;
      return (bool)$this->pref->updateBit($idFolder, $bit);
    }

    return false;
  }


  protected function getHighestmodseq(string $idFolder): ?int
  {
    if ($bitCfg = $this->pref->getBitCfg($idFolder)) {
      return $bitCfg['highestmodseq'] ?? null;
    }

    return null;
  }


  protected function setUidValidity(string $idFolder, int $uidvalidity): bool
  {
    if ($bit = $this->pref->getBit($idFolder)) {
      $bit['uidvalidity'] = $uidvalidity;
      $bit['id_parent'] = !empty($bit['id_parent']) ? $bit['id_parent'] :  null;
      return (bool)$this->pref->updateBit($idFolder, $bit);
    }

    return false;
  }


  protected function getUidValidity(string $idFolder): ?int
  {
    if ($bitCfg = $this->pref->getBitCfg($idFolder)) {
      return $bitCfg['uidvalidity'] ?? null;
    }

    return null;
  }


  public function checkAndDeleteEmails(string $idFolder): array
  {
    $deleted = [];
    if (($folder = $this->getFolder($idFolder))
      && !empty($folder['id_account'])
      && !empty($folder['uid'])
      && !empty($folder['hash'])
      && ($mb = $this->getMailbox($folder['id_account']))
      && $mb->update($folder['uid'])
      && ($folders = $mb->getFolders())
      && ($folderInfo = $folders[$folder["uid"]])
      && ($hash = $this->makeFolderHash($idFolder, $folderInfo["num_msg"], $folderInfo["last_uid"]))
      && ($hash !== $folder['hash'])
    ) {
      $db = $this->pref->isLocale(
        $folder['id'],
        $this->pref->getClassCfg()['tables']['user_options_bits']
      )
        ? $this->getLocaleDb()
        : $this->db;
      $emailsTable = $this->class_cfg['tables']['users_emails'];
      $emailsFields = $this->class_cfg['arch']['users_emails'];
      $emailsUids = $db->getKeyVal("
        SELECT $emailsFields[msg_uid], $emailsFields[id]
        FROM $emailsTable
        WHERE $emailsFields[id_folder] = ?
      ", hex2bin($folder['id']));
      if (empty($emailsUids)) {
        return [];
      }

      $emailsFound = $mb->search('UID ' . implode(',', array_keys($emailsUids))) ?: [];
      foreach ($emailsUids as $uid => $id) {
        if (!in_array($uid, $emailsFound)
          && $db->delete($emailsTable, [$emailsFields['id'] => $id])
        ) {
          $deleted[] = $id;
        }
      }

      if (!empty($deleted)) {
        $this->checkFolder($folder);
      }
    }

    return $deleted;
  }


  public function syncFlags(string $idFolder): int
  {
    $synced = 0;
    if (($folder = $this->getFolder($idFolder, false))
      && !empty($folder['id_account'])
      && !empty($folder['uid'])
      && ($mb = $this->getMailbox($folder['id_account']))
      && ($hms = $mb->getFolderHighestmodseq($folder['uid']))
    ) {
      $last = $folder['highestmodseq'] ?? null;
      if (is_null($last) || ($hms > $last)) {
        if (($uids = $mb->getMsgUidsChangedSinceModseq($folder['uid'], $last ?: 0))
          && ($allFlags = $mb->getMsgFlags($uids, true))
        ) {
          $fields = $this->class_cfg["arch"]["users_emails"];
          $table = $this->class_cfg["tables"]["users_emails"];
          $isLocale = $this->pref->isLocale(
            $folder['id'],
            $this->pref->getClassCfg()["tables"]["user_options_bits"]
          );
          $db = $isLocale ? $this->getLocaleDb() : $this->db;
          foreach ($uids as $uid) {
            if ($emailId = $this->getEmailIdByUid($uid, $folder['id'])) {
              $flags = $allFlags[$uid] ?? [];
              $u = [
                $fields['is_read'] => in_array('\\Seen', $flags) ? 1 : 0,
                $fields['is_draft'] => in_array('\\Draft', $flags) ? 1 : 0,
                $fields['flags'] => array_values(
                  array_filter(
                    $flags,
                    fn($flag) => !in_array($flag, ['\\Seen', '\\Deleted', '\\Draft', '\\Recent'])
                  )
                )
              ];
              $u[$fields['flags']] = !empty($u[$fields['flags']]) ? implode(',', $u[$fields['flags']]) : null;
              $synced += $db->update($table, $u, [$fields["id"] => $emailId]);
            }
          }
        }

        $this->setHighestmodseq($idFolder, $hms);
      }
    }

    return $synced;
  }


  protected function normalizeFolder(
    array $folder,
    bool $lite = false,
    bool $depth = false
  ): array
  {
    $folderType = $this->getFolderType($folder, false);
    $res = [
      "id" => $folder["id"],
      "id_account" => $folder["id_user_option"] ?? $folder["id_account"] ?? null,
      "text" => $folder["text"],
      "uid" => $folder["uid"],
      "id_option" => $folder["id_option"],
      "id_parent" => $folder["id_parent"] ?? null,
      "type" => $folderType['code'] ?? null,
      "num_msg" => $folder["num_msg"] ?? 0,
      "last_uid" => $folder["last_uid"] ?? null,
      "last_check" => $folder["last_check"] ?? null,
      "hash" => $folder["hash"] ?? null,
      "subscribed" => $folder["subscribed"] ?? false,
      "icon" => $folderType['icon'] ?? null,
      "synchronizing" => $folder["synchronizing"] ?? false,
      "last_sync_start" => $folder["last_sync_start"] ?? null,
      "last_sync_end" => $folder["last_sync_end"] ?? null,
      "highestmodseq" => $folder["highestmodseq"] ?? null,
      "uidvalidity" => $folder["uidvalidity"] ?? null,
      "uidnext" => $folder["uidnext"] ?? null,
    ];
    if (!$lite) {
      $res += [
        "db_uid_max" => $this->getDbUidMax($folder["id"]),
        "db_uid_min" => $this->getDbUidMin($folder["id"]),
        "db_num_msg" => $this->getNumMsg($folder["id"]),
        "db_num_unseen_msg" => $this->getNumUnseenMsg($folder["id"]),
        $this->localeField => $this->pref->isLocale(
          $folder["id"],
          $this->pref->getClassCfg()["tables"]["user_options_bits"],
        )
      ];
    }

    if ($depth && !empty($folder["items"])) {
      $res["items"] = array_map(
        fn($f) => $this->normalizeFolder($f, $lite, $depth),
        $folder["items"],
      );
    }

    return $res;
  }

  protected function getFolderType(string|array $folder, bool $onlyCode = true): string|array|null
  {
    $types = self::getFolderTypes();
    if (Str::isUid($folder)) {
      $folder = $this->pref->getBit($folder);
    }

    if (!empty($folder['id_option'])) {
      $type = X::getRow($types, ["id" => $folder["id_option"]]);
      return $onlyCode ? ($type["code"] ?? null) : $type;
    }

    return null;
  }

  /**
   * Check and update the folder subscriptions on the mail server
   * @param string $id_account
   * @param array $folders
   * @return void
   */
  protected function checkFolderSubscriptions(
    string $id_account,
    array $folders,
  ): void {
    if ($mb = $this->getMailbox($id_account)) {
      $params = $mb->getParams();
      $subscribed = array_map(
        fn($f) => Str::sub($f, Str::len($params) - 1),
        $mb->listAllSubscribed(),
      );
      if (!empty($folders) && is_array($folders[0])) {
        $folders = array_map(
          fn($f) => $f["uid"],
          array_filter($folders, fn($f) => !empty($f["uid"])),
        );
      }

      foreach ($folders as $folder) {
        if (!in_array($folder, $subscribed)) {
          $mb->subscribeFolder($folder);
        }
      }

      foreach ($subscribed as $folder) {
        if (!in_array($folder, $folders)) {
          $mb->unsubscribeFolder($folder);
        }
      }
    }
  }

  protected function getMailerCfg(string $idAccount, array $cfg): ?array
  {
    if ($account = $this->getAccount($idAccount)) {
      $smtp = null;
      if (!empty($account["smtp"])) {
        $smtp = $this->getSmtp($account["smtp"]);
      }

      $mailerCfg = [
        "imap" => true,
        "from" => $cfg["from"] ?? null,
        "name" => $cfg["name"] ?? ($cfg["from"] ?? null),
        "template" => <<<HTML
          <!DOCTYPE html>
          <html>
            <head>
              <title>{{title}}</title>
              <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
            </head>
            <body style="background-color:#FFF; color:#333; font-family:Arial; margin:20px; padding:0; font-size:14px">
              <div>{{{text}}}</div>
            </body>
          </html>
        HTML
      ,
      ];
      $folders = $this->getFolders($idAccount) ?: [];
      if ($sentFolder = X::getField($folders, ["type" => "sent"], "uid")) {
        $mailerCfg["imap_sent"] = $sentFolder;
      }

      if ($draftsFolder = X::getField($folders, ["type" => "drafts"], "uid")) {
        $mailerCfg["imap_drafts"] = $draftsFolder;
      }

      if ($smtp) {
        $mailerCfg = X::mergeArrays($mailerCfg, [
          "host" => $smtp["host"],
          "port" => $smtp["port"],
          "user" => $smtp["login"],
          "pass" => $this->_get_password()->userGet($smtp["id"], $this->user),
        ]);
        if (
          !empty($smtp["encryption"]) &&
          in_array($smtp["encryption"], ["starttls", "tls"])
        ) {
          $mailerCfg["encryption"] = [
            $smtp["encryption"] === "tls" ? "ssl" : "tls" => [
              "verify_peer" => !empty($smtp["validatecert"]),
              "verify_peer_name" => false,
              "verify_host" => false,
              "allow_self_signed" => true,
            ],
          ];
        }
      }

      return $mailerCfg;
    }

    return null;
  }

  private function _get_password(): Passwords
  {
    if (!$this->pw) {
      $this->pw = new Passwords($this->db);
    }

    return $this->pw;
  }

  private function getDbUidMax(string $idFolder): ?int
  {
    $db = $this->pref->isLocale(
      $idFolder,
      $this->pref->getClassCfg()["tables"]["user_options_bits"],
    )
      ? $this->getLocaleDb()
      : $this->db;
    return $db->selectOne(
      $this->class_table,
      "MAX(" . $db->csn($this->fields["msg_uid"], true) . ")",
      [
        $this->fields["id_folder"] => $idFolder,
        $this->fields["id_user"] => $this->user->getId(),
      ],
    );
  }

  private function getDbUidMin(string $idFolder): ?int
  {
    $db = $this->pref->isLocale(
      $idFolder,
      $this->pref->getClassCfg()["tables"]["user_options_bits"],
    )
      ? $this->getLocaleDb()
      : $this->db;
    return $db->selectOne(
      $this->class_table,
      "MIN(" . $db->csn($this->fields["msg_uid"], true) . ")",
      [
        $this->fields["id_folder"] => $idFolder,
        $this->fields["id_user"] => $this->user->getId(),
      ],
    );
  }

  private function getNumMsg(string $idFolder): int
  {
    $db = $this->pref->isLocale(
      $idFolder,
      $this->pref->getClassCfg()["tables"]["user_options_bits"],
    )
      ? $this->getLocaleDb()
      : $this->db;
    return $db->count($this->class_table, [
      $this->fields["id_folder"] => $idFolder,
      $this->fields["id_user"] => $this->user->getId(),
    ]);
  }

  private function getNumUnseenMsg(string $idFolder): int
  {
    $db = $this->pref->isLocale($idFolder, $this->pref->getClassCfg()["tables"]["user_options_bits"]) ?
      $this->getLocaleDb() :
      $this->db;
    return $db->count(
      $this->class_table,
      [
        $this->fields["id_folder"] => $idFolder,
        $this->fields["id_user"] => $this->user->getId(),
        $this->fields["is_read"] => 0
      ]
    );
  }

  private function makeFolderHash(
    string $idFolder,
    int $numMsg = 0,
    int $lastUid = 0,
  ): ?string {
    return md5(
      json_encode([
        "idFolder" => $idFolder,
        "lastUid" => $lastUid,
        "numMsg" => $numMsg,
      ]),
    );
  }

  private function partialClearUserCache()
  {
    $fs = new System();
    $path = $this->user->getCachePath() . $this->cachePrefix;
    if ($fs->getNumFiles($path) > 50) {
      $files = $fs->getFiles($path, false, false, null, "m");
      X::sortBy($files, "mtime", "desc");
      array_splice($files, 0, 50);
      foreach ($files as $f) {
        $fs->delete($f["name"]);
      }
    }
  }




  public function getQueueDb(){
    if (($user = User::getInstance())
      && ($mainDataPath = $user->getDataPath('appui-email'))
    ) {
      $path = $mainDataPath . 'webmail/';
      $dbName = 'queue.sqlite';
      if (!is_dir($path)) {
        $fs = new System();
        $fs->createPath($path);
      }

      if (is_dir($path)) {
        if (is_file($path . $dbName)) {
          $db = new Db([
            'engine' => 'sqlite',
            'host' => $path,
            'db' => $dbName,
          ]);
        }
        else if (($cfg = Mvc::getPluginPath('appui-email') . 'cfg/webmailqueue.json')
          && is_file($cfg)
          && ($cfg = json_decode(file_get_contents($cfg), true))
          && Sqlite::createDatabaseOnHost($dbName, $path)
        ) {
          $db = new Db([
            'engine' => 'sqlite',
            'host' => $path,
            'db' => $dbName,
          ]);
          foreach ($cfg as $table => $c) {
            $c = $this->db->convert($c, 'sqlite');
            $db->createTable($table, $c);
          }
        }
      }
    }

    return $db ?? null;
  }

  public function addQueue(string $idFolder, string $action = 'sync', int $priority = 1): ?string
  {
    if (Str::isUid($idFolder)
      && in_array($action, ['sync'])
      && ($db = $this->getQueueDb())
    ) {
      $exists = $db->select(
        'queue',
        ['id', 'priority'],
        [
          'id_folder' => $idFolder,
          'action' => $action
        ]
      );
      if (empty($exists)) {
        if ($db->insert('queue', [
          'id_folder' => $idFolder,
          'action' => $action,
          'date_added' => X::microtime(),
          'date_started' => null,
          'date_ping' => null,
          'priority' => $priority
        ])) {
          return $db->lastId();
        }
      }
      else if ($exists->priority !== $priority) {
        $db->update('queue', ['priority' => $priority], ['id' => $exists->id]);
      }
    }

    return null;
  }

  public function getQueue(array $where = []): ?array
  {
    if ($db = $this->getQueueDb()) {
      return $db->rselectAll('queue',
        [],
        $where,
        [
          'priority' => 'DESC',
          'date_added' => 'ASC'
        ]
      );
    }

    return null;
  }

  public function getQueueFirst(): ?array
  {
    if ($db = $this->getQueueDb()) {
      return $db->rselect(
        'queue',
        [],
        [
          'date_started' => null
        ],
        [
          'priority' => 'DESC',
          'date_added' => 'ASC'
        ]
      );
    }

    return null;
  }

  public function removeQueue(string $id): bool
  {
    if ($db = $this->getQueueDb()) {
      return (bool)$db->delete('queue', ['id' => $id]);
    }

    return false;
  }

  public function updateQueue(string $id, array $data): bool
  {
    if ($db = $this->getQueueDb()) {
      return (bool)$db->update('queue', $data, ['id' => $id]);
    }

    return false;
  }

  public function startProcessQueue(string|array $action, callable $callback)
  {
    if ($this->processingQueue) {
      return;
    }

    $action = is_array($action) ? $action : [$action];
    $this->processingQueue = true;
    set_time_limit(0);
    ignore_user_abort(true);
    $this->queueCallback = $callback;
    $this->queueCallbackLastPing = time();
    $currentQueue = [];
    $queue = [];
    while ($this->processingQueue) {
      $dbQueue = $this->getQueue(['action' => $action]) ?: [];
      foreach ($dbQueue as $item) {
        if (!isset($currentQueue[$item['id']])) {
          $currentQueue[$item['id']] = [
            'generator' => $this->processQueueItem($item),
            'started' => false,
            'priority' => $item['priority']
          ];
          $queue[$item['id']] = [
            'id' => $item['id'],
            'started' => false,
            'completed' => false,
            'data' => $item
          ];
        }
      }

      ($this->queueCallback)([
        'action' => 'queue',
        'data' => array_values($queue)
      ]);

      if (empty($currentQueue)) {
        $this->stopProcessQueue();
        break;
      }

      //X::sortBy($currentQueue, 'priority', 'desc');
      //$maxPriority = $currentQueue[array_key_first($currentQueue)]['priority'];
      //$currentQueue = array_filter($currentQueue, fn($w) => $w['priority'] === $maxPriority);
      foreach ($currentQueue as $id => &$worker) {
        if (((time() - $this->queueCallbackLastPing) > $this->queueCallbackPingFrequency)
          && !$this->pingQueueCallback()
        ) {
          $this->stopProcessQueue();
          break;
        }

        if (!$this->processingQueue) {
          break;
        }

        /** @var Generator $generator */
        $generator = $worker['generator'];
        if ($worker['started']) {
          $generator->next();
        }
        else {
          $generator->current();
          $worker['started'] = true;
          $queue[$id]['started'] = true;
        }

        if (!$generator->valid()) {
          $queue[$id]['completed'] = true;
          ($this->queueCallback)([
            'action' => 'queue',
            'data' => array_values($queue)
          ]);
          unset($currentQueue[$id]);
        }
      }

      unset($worker);
    }

    $this->stopProcessQueue();
  }

  public function stopProcessQueue(){
    $this->processingQueue = false;
  }

  protected function pingQueueCallback(): bool
  {
    if (empty($this->queueCallback)) {
      $this->stopProcessQueue();
      return false;
    }

    $this->queueCallbackLastPing = time();
    $ping = ($this->queueCallback)(['action' => 'ping']);
    if (empty($ping)) {
      $this->stopProcessQueue();
      return false;
    }

    return true;
  }

  protected function processQueueItem(array $item): Generator
  {
    $this->updateQueue($item['id'], ['date_started' => X::microtime()]);
    if (!empty($item['action'])
      && !empty($item['id_folder'])
    ) {
      switch ($item['action']) {
        case 'sync':
          $count = 0;
          $sync = $this->syncEmails2($item['id_folder']);
          foreach ($sync as $i => $s) {
            $count++;
            if ($this->queueCallback) {
              ($this->queueCallback)([
                'action' => 'sync',
                'synchronizing' => true,
                'folder' => $item['id_folder'],
                'data' => [$i => $s]
              ]);
            }

            if ($count >= 5) {
              $count = 0;
              yield;
            }
          }

          $this->removeQueue($item['id']);
          $ret = $sync->getReturn();
          if ($this->queueCallback) {
            ($this->queueCallback)([
              'action' => 'sync',
              'completed' => true,
              'folder' => $item['id_folder'],
              'data' => $ret
            ]);
          }

          return $ret;
      }
    }
  }
}

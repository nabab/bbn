<?php

namespace bbn\Appui;

use Exception;
use bbn\Db;
use bbn\X;
use bbn\Str;
use bbn\User;
use bbn\User\Preferences;
use bbn\File\System;
use bbn\Models\Tts\DbOps;
use bbn\Models\Tts\Optional;
use bbn\Models\Cls\Db as DbCls;
use Orhanerday\OpenAi\OpenAi;
use Gioni06\Gpt3Tokenizer\Gpt3TokenizerConfig;
use Gioni06\Gpt3Tokenizer\Gpt3Tokenizer;

class Ai extends DbCls
{
  use DbOps;
  use Optional;

  /**
   * Default Dbconfig configuration for the class
   *
   * @var array $default_class_cfg
   */
  protected static $default_class_cfg = [
    "table" => "bbn_ai_prompt",
    "tables" => [
      "prompt" => "bbn_ai_prompt",
      "prompt_items" => "bbn_ai_prompt_items",
      "prompt_settings" => "bbn_ai_prompt_settings",
    ],
    "arch" => [
      "prompt" => [
        "id" => "id",
        "id_note" => "id_note",
        "input_format" => "input_format",
        "output_format" => "output_format",
        "output_language" => "output_language",
        "creation_date" => "creation_date",
        "usage_count" => "usage_count",
        "shortcode" => "shortcode",
      ],
      "prompt_items" => [
        "id" => "id",
        "id_prompt" => "id_prompt",
        "id_setting" => "id_setting",
        "text" => "text",
        "author" => "author",
        "creation_date" => "creation_date",
        "mime" => "mime",
        "ai" => "ai",
      ],
      "prompt_settings" => [
        "id" => "id",
        "id_prompt" => "id_prompt",
        "id_model" => "id_model",
        "def" => "def",
        "last_use" => "last_use",
        "hash" => "hash",
        "cfg" => "cfg",
      ],
    ],
  ];

  /** @var array $responseFormats Response formats for the AI */
  private static array $responseFormats;

  private static array $promptMandatoryFields = [
    "title",
    "content",
    "language",
    "input_format",
    "output_format",
    "id_model"
  ];

  /** @var OpenAi $ai OpenAI API instance */
  protected OpenAi $ai;

  /** @var Preferences $prefs Preferences instance */
  protected Preferences $prefs;

  /** @var int MAX_TOKENS Maximum number of tokens for the AI */
  protected const MAX_TOKENS = 4000;

  /** @var string $endpoint The current endpoint */
  protected string $endpoint;

  /** @var string|null $model The current model */
  protected ?string $model;

  /** @var string $baseUrl The base URL for the API */
  protected string $baseUrl;

  /** @var array $cfg */
  protected array $cfg;

  /** @var Note $note Note instance */
  private Note $note;

  /** @var Passwords $pass Passwords instance */
  private Passwords $pass;

  /** @var System $fs File system instance */
  private System $fs;

  /** @var User $user User instance */
  private User $user;

  /** @var string $key */
  private string $key;

  private static function init()
  {
    if (!self::$optional_is_init) {
      self::optionalInit();
      self::$responseFormats = self::getOptions("formats");
    }
  }

  /**
   * Ai constructor.
   *
   * @param Db $db The database connection
   * @param array $cfg Configuration array for the class
   *
   * @throws Exception If OpenAI key is not defined
   */
  public function __construct(Db $db)
  {
    $this->initClassCfg();
    parent::__construct($db);
    self::init();
    if (!defined("BBN_OPENAI_KEY")) {
      throw new Exception("The OpenAI key is not defined");
    }

    $this->user = User::getInstance();
    $this->prefs = Preferences::getInstance();
    $this->note = new Note($this->db);
    $this->pass = new Passwords($this->db);
    $this->fs = new System();
  }

  /**
   * Connects to the specified endpoint, sets it as the current endpoint, and optionally selects the provided model.
   * @param string|null $id
   * @param mixed $model
   * @throws \Exception
   * @return void
   */
  public function setEndpoint(string|null $id = null, $model = null)
  {
    if ($endpoint = $this->getEndpoint($id)) {
      $pass = $this->pass->userGet($endpoint["data"]["id"], $this->user);
      if (!$pass) {
        throw new Exception("Password not found");
      }
    }

    if (!$endpoint) {
      throw new Exception("Endpoint not found");
    }

    $this->ai = new OpenAi($pass);
    $this->ai->setBaseURL($endpoint["data"]["url"]);
    if (!$model && !empty($endpoint["models"])) {
      $model = $endpoint["models"][0]["text"];
    }

    $this->endpoint = $id;
    if ($model) {
      $this->setModel($model);
    }
  }

  /**
   * Sets the active model used for subsequent AI requests.
   *
   * @param string $modelName The model identifier (e.g. "gpt-4").
   * @return void
   */
  public function setModel(string $modelName): void
  {
    $this->model = Str::isUid($modelName) ? ($this->prefs->getBit($modelName)["text"] ?? null) : $modelName;
  }

  /**
   * Retrieves the list of available models from the current endpoint.
   *
   * @return array|null An array of models if the endpoint is set; otherwise, null.
   */
  public function getModels(): ?array
  {
    if ($this->endpoint) {
      $res = $this->ai->listModels();
      if ($res && is_string($res)) {
        $res = json_decode($res, true);
      }

      return $res;
    }

    return null;
  }

  /**
   * Retrieves the name of a model by its ID.
   *
   * @param string $id The ID of the model.
   * @return string|null The name of the model, or null if not found.
   */
  public function getModelName(string $id): ?string
  {
    if ($p = $this->prefs->getBit($id)) {
      return $p["text"];
    }

    return null;
  }

  /**
   * Synchronizes the models from the current endpoint with the stored preferences.
   *
   * @return bool True if synchronization was successful; otherwise, false.
   */
  public function syncModels(): bool
  {
    if ($this->endpoint && ($models = $this->getModels())) {
      $endpoint = $this->getEndpoint($this->endpoint);
      $currentModels = $endpoint["models"] ?? [];
      $idModels = self::getOptionId("models");
      foreach ($models["data"] as $model) {
        if (!X::getRow($currentModels, ["text" => $model["id"]])) {
          $this->prefs->addBit($this->endpoint, [
            "text" => $model["id"],
            "id_option" => $idModels,
          ]);
        }
      }

      return true;
    }

    return false;
  }

  /**
   * Retrieves the list of available endpoints.
   *
   * @return array An array of endpoints.
   * @throws Exception If endpoints are not found.
   */
  public function getEndpoints()
  {
    if ($idEndpoint = self::getOptionId("endpoints")) {
      return $this->prefs->getAll($idEndpoint);
    }

    throw new Exception(X::_("Endpoints not found"));
  }

  /**
   * Retrieves the details of a specific endpoint by its ID.
   *
   * @param string $id The ID of the endpoint.
   * @return array|null An array containing endpoint data and models, or null if not found.
   */
  public function getEndpoint(string $id): ?array
  {
    if ($endpoint = $this->prefs->get($id)) {
      $models = $this->prefs->getBits($id);
      X::sortBy($models, [
        [
          "field" => "last_used",
          "dir" => "DESC",
        ],
        [
          "field" => "text",
          "dir" => "ASC",
        ],
      ]);
      return [
        "data" => $endpoint,
        "models" => $models,
      ];
    }

    return null;
  }

  public function getEndpointByModel(string $idModel): ?array
  {
    if ($p = $this->prefs->getBit($idModel)) {
      return $this->getEndpoint($p["id_user_option"]);
    }

    return null;
  }

  /**
   * Adds a new AI endpoint with the specified parameters.
   *
   * @param string $name The name of the endpoint.
   * @param string $url The URL of the endpoint.
   * @param string $pass The password or API key for the endpoint.
   * @param bool $public Whether the endpoint is public or not.
   * @return array|null An array containing the newly added endpoint data, or null on failure.
   * @throws Exception If models are not found.
   */
  public function addEndpoint(
    string $name,
    string $url,
    string $pass,
    bool $public = false,
  ): ?array {
    if ($idEndpoint = self::getOptionId("endpoints")) {
      $this->ai = new OpenAi($pass);
      $this->ai->setBaseURL($url);
      if (
        ($idPref = $this->prefs->addToGroup($idEndpoint, [
          "text" => $name,
          "url" => $url,
          "public" => $public ? 1 : 0,
        ])) &&
        $this->pass->userStore($pass, $idPref, $this->user)
      ) {
        $this->setEndpoint($idPref);
        $this->syncModels();

        return $this->getEndpoint($idPref);
      } else {
        throw new Exception("Models not found");
      }
    }

    return null;
  }

  /**
   * Gets the AI prompt response based on input, response type, and prompt ID
   *
   * @param string $id_prompt ID of the AI prompt
   * @param string $input Input string
   * @param string $response_type Response type
   * @return array Response array containing success flag and result or error message
   */
  public function getPromptResponseFromId(
    string $id_prompt,
    string $input,
    bool $insert = true,
    ?array $cfg = null,
  ): array {
    // check if input and id_prompt are not empty and not null
    if (empty($input) || empty($id_prompt)) {
      return [
        "success" => false,
        "error" => "Input and prompt ID cannot be empty",
      ];
    }

    $prompt = $this->dbTraitRselect($id_prompt);

    if (empty($prompt)) {
      return [
        "success" => false,
        "error" => "Prompt not found",
      ];
    }

    $response = $this->getPromptResponse($prompt, $input, $cfg);
    if (!empty($response) && !empty($response["success"]) && $insert) {
      $this->insertItem($id_prompt, $input, $cfg, false);
      $this->insertItem(
        $id_prompt,
        $response["result"]["content"] ?? $response["error"]["message"],
        $cfg,
        true,
      );
    }

    return $response;
  }

  /**
   * Gets the AI prompt response based on input, response type, and prompt data
   *
   * @param array $prompt Prompt data array
   * @param string $input Input string
   * @param string $response_type Response type
   * @return array Response array containing success flag and result or error message
   */
  public function getPromptResponse(
    array $prompt,
    string $input,
    ?array $cfg = null,
  ): array {
    // check if input and id_prompt are not empty and not null
    if (empty($input) || empty($prompt)) {
      return [
        "success" => false,
        "error" => "Input and prompt cannot be empty",
      ];
    }

    $built_prompt = $this->buildPromptFromRow($prompt);
    $messages = $this->createMessages($input, $built_prompt);
    $request = $this->createRequest($messages, $cfg);

    X::log($request, "ai_logs");

    $response = $this->request($request);
    $res = [
      "success" => !isset($response["error"]),
      "input" => $built_prompt,
      "result" => $response["result"],
    ];

    if (!empty($response["error"])) {
      $res["error"] = $response["error"];
    }

    return $res;
  }

  /**
   * Creates an array of messages for the AI request
   *
   * @param string $input User input
   * @param string $prompt System prompt
   * @return array Array of messages
   */
  public function createMessages(string $input, string $prompt = ""): array
  {
    $messages = [];

    if ($prompt) {
      $messages[] = [
        "role" => "system",
        "content" => $prompt,
      ];
    }

    $messages[] = [
      "role" => "user",
      "content" => $input,
    ];

    return $messages;
  }

  /**
   * Creates the request array for the OpenAI API
   *
   * @param array $messages Array of messages
   * @param array $cfg Configuration array
   * @return array Request array
   */
  public function createRequest(array $messages, array $cfg = []): array
  {
    $config = new Gpt3TokenizerConfig();
    $tokenizer = new Gpt3Tokenizer($config);
    $max_tokens = self::MAX_TOKENS;
    foreach ($messages as $message) {
      $max_tokens -= $tokenizer->count($message["content"]);
    }

    $model = $this->model;
    if (!empty($cfg["model"])) {
      $model = Str::isUid($cfg["model"])
        ? $this->getModelName($cfg["model"])
        : $cfg["model"];
    }

    $request = [
      "model" => $model,
      "messages" => $messages,
      "max_tokens" => $max_tokens,
    ];
    if (!empty($cfg['cfg'])) {
      if (array_key_exists("temperature", $cfg["cfg"])) {
        $request["temperature"] = $cfg["cfg"]["temperature"];
      }
      if (array_key_exists("top_p", $cfg["cfg"])) {
        $request["top_p"] = $cfg["cfg"]["top_p"];
      }
      if (array_key_exists("frequency", $cfg["cfg"])) {
        $request["frequency_penalty"] = $cfg["cfg"]["frequency"];
      }
      if (array_key_exists("presence", $cfg["cfg"])) {
        $request["presence_penalty"] = $cfg["cfg"]["presence"];
      }
    }

    return $request;
  }

  /**
   * Sends a request to the OpenAI API and returns the response
   *
   * @param string $prompt Prompt string to send to the API
   * @return array Response array containing success flag and result or error message
   */
  public function request(array $query): array
  {
    if ($complete = $this->ai->chat($query)) {
      $complete = json_decode($complete, true);
    }

    X::log(["request" => $query, "complete" => $complete], "ai_logs");

    if (!$complete || !empty($complete["error"])) {
      return [
        "success" => false,
        "error" => $complete["error"] ?? "Error in the request",
      ];
    }

    $content = $complete["choices"][0]["message"]["content"];
    X::log($content, "ai_logs");

    return [
      "success" => true,
      "result" => $complete["choices"][0]["message"],
      "response" => $complete,
      "request" => $query,
    ];
  }

  public function getChatTitle(?string $text = null, ?array $cfg = null): string
  {
    if (empty($text) || empty($cfg)) {
      return Str::genpwd(10);
    }

    $text = "Suggest me a very concise title for the following chat, the title must make me understand the topic of the chat, the title must be on a single line:" . PHP_EOL . PHP_EOL . $text;
    $messages = $this->createMessages($text);
    $query = $this->createRequest($messages, $cfg);
    $result = $this->request($query);
    if (empty($result["result"]["content"])) {
      return Str::genpwd(10);
    }

    return trim($result["result"]["content"], "\n\r\t *");
  }

  public function getTags(string $text): array
  {
    // This method should return an array of tags based on the input text
    // For now, we will return an empty array
    return [];
  }

  /**
   * Handles a chat interaction with the AI, managing conversation history and storage.
   *
   * @param string $input User input string
   * @param array $cfg Configuration array for the chat
   * @param string $id Optional conversation ID
   * @return array Response array containing success flag and result or error message
   */
  public function chat(string $input, array $cfg, string $id = ""): array
  {
    $startTime = time();
    $messages = $this->createMessages($input);
    $query = $this->createRequest($messages, $cfg);
    $result = $this->request($query);
    if ($result["success"]) {
      $responseTime = time();
      $result["text"] = $result["result"]["content"];
      $fullText = "";
      $messages[] = [
        "role" => "assistant",
        "content" => $result["text"],
      ];

      foreach ($messages as $message) {
        $fullText .=
          "**Message by $message[role]**" .
          PHP_EOL .
          $message["content"] .
          PHP_EOL .
          PHP_EOL .
          PHP_EOL;
      }

      $result["id"] = $id ?: Str::genpwd(10);
      $result["date"] = $startTime;
      $result["rdate"] = $responseTime;
      $result["input"] = $input;
      $result["request"] = $result;
      $path = $this->user->getDataPath("appui-ai") . "chat";
      $summaryFile = "conversations.json";
      $this->fs->cd($path);
      $this->fs->createPath("conversations");
      $summary = $this->fs->exists($summaryFile)
        ? $this->fs->decodeContents($summaryFile, "json", true)
        : [];
      $summaryHash = md5(serialize($summary));
      $index = X::search($summary, ["id" => $id]);
      $row = $summary[$index] ?? null;
      if ($row) {
        if ($index !== 0) {
          array_splice($summary, $index, 1);
        }

        $conversation = $this->fs->decodeContents($row["file"], "json", true);
        $result["title"] = $this->getChatTitle();
      } else {
        $subpath = Str::sub(
          X::makeStoragePath($path . "/conversations", "Y", 100),
          Str::len($path) + 1,
        );
        $result["file"] = $subpath . $result["id"] . ".json";
        $result["title"] = $this->getChatTitle($fullText, $cfg);
        $row = [
          "title" => $result["title"],
          "id" => $result["id"],
          "file" => $result["file"],
          "num" => 0,
          "tags" => [],
          "creation" => $startTime,
          "last" => $responseTime,
        ];

        $conversation = [
          "id" => $result["id"],
          "title" => $result["title"],
          "num" => 0,
          "tags" => [],
          "creation" => $startTime,
          "last" => $responseTime,
          "conversation" => [],
        ];
      }

      $conversation["num"]++;
      $conversation["last"] = $responseTime;
      $row["num"]++;
      $row["last"] = $responseTime;
      $lastMessage = [
        "messages" => [
          ...$query["messages"],
          [
            "role" => "assistant",
            "content" => $result["text"],
          ],
        ],
        "title" => $result["title"],
        "asked" => $startTime,
        "responded" => $responseTime,
        "id" => $result["id"],
        "tags" => $this->getTags($fullText),
        "cfg" => $cfg,
      ];

      $conversation["conversation"][] = $lastMessage;
      $result["conversation"] = $id
        ? end($conversation["conversation"])
        : $conversation;

      $this->fs->encodeContents($conversation, $row["file"], "json");

      if ($index !== 0) {
        array_unshift($summary, $row);
      }

      if (md5(serialize($summary)) !== $summaryHash) {
        $this->fs->encodeContents($summary, $summaryFile, "json");
      }
    } elseif (empty($result["error"])) {
      $result["error"] = X::_("Unknown error");
    }

    return $result;
  }

  /**
   * Saves a conversation to a specified path.
   * @param string $path
   * @param string $date
   * @param string $userFormat
   * @param string $prompt
   * @param mixed $response
   * @param mixed $cfg
   * @return array<array|array{ai: int, creation_date: int, id: string, text: string|null|array{ai: int, creation_date: string, format: string, id: string, text: string}>}
   */
  public function saveConversation(
    string $path,
    string $date,
    string $userFormat,
    string $prompt,
    ?string $response,
    ?array $cfg = null,
  ): array {
    $timestamp = time();
    $fs = new System();
    if ($fs->exists($path)) {
      $jsonData = $fs->decodeContents($path, "json", true);
    } else {
      $jsonData = [];
    }

    $d = [
      [
        "ai" => 0,
        "creation_date" => $date,
        "text" => $prompt,
        "id" => bin2hex(random_bytes(10)),
        "format" => $userFormat ?? "textarea",
      ],
      [
        "ai" => 1,
        "creation_date" => $timestamp,
        "text" => $response,
        "id" => bin2hex(random_bytes(10)),
      ],
    ];

    if ($cfg) {
      $d[1]["cfg"] = $cfg;
    }
    if (isset($jsonData["conversation"])) {
      array_push($jsonData["conversation"], ...$d);
    } else {
      array_push($jsonData, ...$d);
    }

    $fs->encodeContents($jsonData, $path, "json");
    return $d;
  }

  /**
   * Clears the conversation history for a given prompt ID.
   *
   * @param string $id The ID of the prompt whose conversation history is to be cleared.
   * @return bool True if the conversation history was successfully cleared, otherwise false.
   */
  public function clearConversation(string $id): bool
  {
    if (empty($id)) {
      return false;
    }

    if (!$this->getPromptById($id)) {
      return false;
    }

    $ccfg = $this->getClassCfg();
    $this->db->delete($ccfg["tables"]["prompt_items"], [
      $ccfg["arch"]["prompt_items"]["id_prompt"] => $id,
    ]);

    return true;
  }

  /**
   * Retrieves prompts based on the specified conditions.
   *
   * @param bool $private Determines if private prompts should be included.
   * @return array An array of prompts matching the conditions.
   */
  public function getPrompts(bool $private = true): array
  {
    $where = [];

    /*if ($private) {
      // If private, search only for prompts where shortcode is null
      $where[] = [
        'field' => $this->class_cfg['arch']['ai_prompt']['shortcode'],
        'operator' => 'isnull'
      ];
    }*/

    $ccfg = $this->getClassCfg();
    $prompts = $this->db->getColumnValues([
      "tables" => [$ccfg["tables"]["prompt"]],
      "fields" => [$ccfg["arch"]["prompt"]["id"]],
      "where" => $where,
      "order" => [
        [
          "field" => $ccfg["arch"]["prompt"]["creation_date"],
          "dir" => "DESC",
        ],
      ],
    ]);
    return array_map(fn ($id) => $this->getPromptById($id), $prompts);
  }

  /**
   * Retrieves a prompt based on the specified shortcode.
   *
   * @param string $shortcode The shortcode of the prompt.
   * @return array|null The prompt data if found, otherwise null.
   */
  public function getPromptByShortcode(string $shortcode): ?array
  {
    $ccfg = $this->getClassCfg();
    if ($prompt = $this->dbTraitSelectOne('id', [
      $ccfg["arch"]["prompt"]["shortcode"] => $shortcode,
    ])) {
      return $this->getPromptById($prompt);
    }

    return null;
  }

  /**
   * Retrieves a prompt based on the specified ID.
   *
   * @param string $id The ID of the prompt.
   * @return array|null The prompt data if found, otherwise null.
   */
  public function getPromptById(string $id): ?array
  {
    if ($prompt = $this->dbTraitRselect($id)) {
      $note = $this->note->get($prompt["id_note"]);
      $prompt["title"] = $note["title"];
      $prompt["content"] = $note["content"];
      $prompt["language"] = !empty($note["lang"]) ? self::getOptionId($note["lang"], 'languages') : null;
      $prompt["items"] = [];
      $prompt['settings'] = [];
      if ($settings = $this->getPromptDefSettings($id)) {
        $prompt['settings'] = $settings;
      }

      return $prompt;
    }

    return null;
  }

  /**
   * Inserts a new prompt into the database.
   *
   * @param array $data The data of the prompt.
   * @return null|string The ID of the inserted prompt if successful, otherwise null.
   */
  public function insertPrompt(array $data): ?string
  {
    $option = Option::getInstance();
    if (!X::hasProps($data, self::$promptMandatoryFields, true)) {
      throw new Exception("Missing required data");
    }

    $id_option = $option->fromCode("prompt", "types", "note", "appui");
    if (!empty($data["language"])
      && Str::isUid($data["language"])
    ) {
      $data["language"] = self::getOptionsObject()->code($data["language"]);
    }

    $id_note = $this->note->insert(
      $data["title"],
      $data["content"],
      $id_option,
      true,
      false,
      null,
      null,
      "text/plain",
      !empty($data["language"]) ? $data["language"] : null
    );
    $ccfg = $this->getClassCfg();
    if (!empty($data["output_language"])
      && !Str::isUid($data["output_language"])
    ) {
      $data["output_language"] = self::getOptionId($data["output_language"], "languages") ?: null;
    }

    if (
      $this->dbTraitInsert([
        $ccfg["arch"]["prompt"]["id_note"] => $id_note,
        $ccfg["arch"]["prompt"]["input_format"] => $data["input_format"],
        $ccfg["arch"]["prompt"]["output_format"] => $data["output_format"],
        $ccfg["arch"]["prompt"]["output_language"] => $data["output_language"] ?: null,
        $ccfg["arch"]["prompt"]["shortcode"] => $data["shortcode"] ?: null,
      ])
    ) {
      $idPrompt = $this->db->lastId();
      $cfg = $data["cfg"] ?? [];
      ksort($cfg);
      if ($cfg) {
        $this->insertSettings($idPrompt, $data["id_model"], $cfg);
      }

      return $idPrompt;
    }

    return null;
  }

  /**
   * Updates an existing prompt in the database.
   *
   * @param string $id The ID of the prompt to update.
   * @param array $data The updated data of the prompt.
   * @return bool True if the update was successful, false otherwise.
   */
  public function updatePrompt(string $id, array $data): bool
  {
    if (!X::hasProps($data, self::$promptMandatoryFields, true)) {
      throw new Exception("Missing required data");
    }

    $prompt = $this->getPromptById($id);
    if (empty($prompt)) {
      throw new Exception("Unrecognized prompt ID");
    }

    $ccfg = $this->getClassCfg();
    $fields = $ccfg["arch"]["prompt"];
    $res1 = false;
    $res2 = false;
    $res3 = false;
    $note = $this->note->get($prompt[$fields["id_note"]]);
    if (empty($note)) {
      throw new Exception("The corresponding notedoes not exist");
    }

    if (!empty($data["language"])
      && Str::isUid($data["language"])
    ) {
      $data["language"] = self::getOptionsObject()->code($data["language"]);
    }

    // Update the title and content of the associated note
    if (($data["title"] !== $note["title"])
      || ($data["content"] !== $note["content"])
      || (!empty($data["language"]) && ($data["language"] !== $note["lang"]))
    ) {
      $res1 = $this->note->update($note["id"], [
        "title" => $data["title"],
        "content" => $data["content"],
        "lang" => !empty($data["language"]) ? $data["language"] : $note["lang"]
      ]);
    }

    if (!empty($data["output_language"])
      && !Str::isUid($data["output_language"])
    ) {
      $data["output_language"] = self::getOptionId($data["output_language"], "languages") ?: null;
    }

    // Update the prompt with the provided ID, input, and output values
    if (($data["input_format"] !== $prompt["input_format"])
      || ($data["output_format"] !== $prompt["output_format"])
      || ($data["output_language"] !== $prompt["output_language"])
      || ($data["shortcode"] !== $prompt["shortcode"])
    ) {
      $res2 = $this->dbTraitUpdate($id, [
        $ccfg["arch"]["prompt"]["input_format"] => $data["input_format"],
        $ccfg["arch"]["prompt"]["output_format"] => $data["output_format"],
        $ccfg["arch"]["prompt"]["output_language"] => $data["output_language"] ?: null,
        $ccfg["arch"]["prompt"]["shortcode"] => $data["shortcode"],
      ]);
    }

    if (!empty($prompt["settings"]['id'])) {
      $res3 = $this->updateSettings(
        $prompt["settings"]["id"],
        $data["id_model"],
        $data["cfg"],
      );
    } else {
      $res3 = $this->insertSettings($id, $data["model"], $data["cfg"]);
    }

    return (bool) ($res1 || $res2 || $res3);
  }

  /**
   * Deletes a prompt from the database.
   *
   * @param string $id The ID of the prompt to delete.
   * @return bool True if the deletion was successful, false otherwise.
   */
  public function deletePrompt(string $id)
  {
    $prompt = $this->getPromptById($id);

    if (empty($prompt)) {
      // If the prompt does not exist, return false to indicate the failure
      return false;
    }

    $note = $this->note->get($prompt["id_note"]);

    if (empty($note)) {
      // If the associated note does not exist, return false to indicate the failure
      return false;
    }

    $ccfg = $this->getClassCfg();
    $this->db->delete($ccfg["tables"]["prompt_items"], [
      $ccfg["arch"]["prompt_items"]["id_prompt"] => $id,
    ]);

    $this->dbTraitDelete($id);

    return true;
  }

  public function getPromptItems(string $id_prompt): array
  {
    $ccfg = $this->getClassCfg();
    return $this->db->rselectAll(
      $ccfg["tables"]["prompt_items"],
      [],
      [
        $ccfg["arch"]["prompt_items"]["id_prompt"] => $id_prompt,
      ],
      [
        $ccfg["arch"]["prompt_items"]["creation_date"] => "DESC",
      ],
    );
  }

  public function insertSettings(
    string $idPrompt,
    string $idModel,
    ?array $cfg = null,
  ): ?string {
    if (empty($idPrompt) || empty($idModel)) {
      return null;
    }

    ksort($cfg);
    $json = json_encode($cfg);
    $hash = md5($idModel . "|" . $json);
    $ccfg = $this->getClassCfg();
    $id = $this->db->selectOne(
      $ccfg["tables"]["prompt_settings"],
      $ccfg["arch"]["prompt_settings"]["id"],
      [
        $ccfg["arch"]["prompt_settings"]["id_prompt"] => $idPrompt,
        $ccfg["arch"]["prompt_settings"]["hash"] => $hash,
      ],
    );
    if ($id) {
      return $id;
    }

    $data = [
      $ccfg["arch"]["prompt_settings"]["id_prompt"] => $idPrompt,
      $ccfg["arch"]["prompt_settings"]["id_model"] => $idModel,
      $ccfg["arch"]["prompt_settings"]["hash"] => $hash,
      $ccfg["arch"]["prompt_settings"]["cfg"] => $json,
    ];
    if (!$this->getPromptDefSettings($idPrompt)) {
      $data[$ccfg["arch"]["prompt_settings"]["def"]] = 1;
    }

    if ($this->db->insert($ccfg["tables"]["prompt_settings"], $data)) {
      return $this->db->lastId();
    }

    return null;
  }

  public function updateSettings(
    string $id,
    string $idModel,
    ?array $cfg = null,
  ): int
  {
    if (empty($id) || empty($idModel)) {
      return 0;
    }

    ksort($cfg);
    $json = json_encode($cfg);
    $hash = md5($idModel . "|" . $json);
    $ccfg = $this->getClassCfg();
    $table = $ccfg["tables"]["prompt_settings"];
    $fields = $ccfg["arch"]["prompt_settings"];
    $idPrompt = $this->db->selectOne(
      $table,
      $fields["id_prompt"],
      [
        $fields["id"] => $id,
      ],
    );
    $exists = $this->db->rselect([
      'table' => $table,
      'fields' => [
        $fields["id"],
        $fields["def"],
      ],
      'where' => [[
        'field' => $fields["id_prompt"],
        'value' => $idPrompt,
      ], [
        'field' => $fields["hash"],
        'value' => $hash,
      ], [
        'field' => $fields["id"],
        'operator' => '!=',
        'value' => $id,
      ]]
    ]);
    if ($exists) {
      $this->deleteSettings($exists[$fields["id"]]);
    }

    $data = [
      $fields["id_prompt"] => $idPrompt,
      $fields["id_model"] => $idModel,
      $fields["hash"] => $hash,
      $fields["cfg"] => $json,
    ];
    if (!empty($exists[$fields["def"]])) {
      $data[$fields["def"]] = 1;
    }

    return $this->db->update($table, $data, [
      $fields["id"] => $id,
    ]);
  }

  public function deleteSettings(string $id): bool
  {
    $ccfg = $this->getClassCfg();
    return (bool)$this->db->delete($ccfg["tables"]["prompt_settings"], [
      $ccfg["arch"]["prompt_settings"]["id"] => $id,
    ]);
  }

  public function getPromptSettings(string $idPrompt): array
  {
    $ccfg = $this->getClassCfg();
    $fields = $ccfg["arch"]["prompt_settings"];
    $res = array_map(
      function($r) use ($fields) {
        if (!empty($r[$fields["cfg"]])) {
          $r[$fields["cfg"]] = json_decode($r[$fields["cfg"]], true);
        }

        return $r;
      },
      $this->db->rselectAll(
        $ccfg["tables"]["prompt_settings"],
        [],
        [
          $fields["id_prompt"] => $idPrompt,
        ]
      )
    );
    return $res;
  }

  public function getPromptDefSettings(string $idPrompt): ?array
  {
    $ccfg = $this->getClassCfg();
    $fields = $ccfg["arch"]["prompt_settings"];
    $res = $this->db->rselect(
      $ccfg["tables"]["prompt_settings"],
      [],
      [
        $fields["id_prompt"] => $idPrompt,
        $fields["def"] => 1
      ]
    );
    if (!empty($res[$fields['cfg']])){
      $res[$fields['cfg']] = json_decode($res[$fields['cfg']], true);
    }

    return $res;
  }

  /**
   * Inserts an AI prompt item into the database
   *
   * @param string $id_prompt ID of the AI prompt to insert the item into
   * @param string $text Text of the AI prompt item
   * @param array  $cfg Configuration array for the prompt item
   * @param string $ai AI response of the prompt item
   *
   * @return void
   */
  private function insertItem(
    string $id_prompt,
    string $text,
    array $cfg,
    ?string $ai = null,
  ) {
    $ccfg = $this->getClassCfg();
    $tf = $ccfg["arch"]["prompt_items"];
    $user = User::getInstance();
    $id_setting = $this->insertSettings($id_prompt, $cfg["model"], $cfg["cfg"]);
    return $this->db->insert($ccfg["tables"]["prompt_items"], [
      $tf["id_prompt"] => $id_prompt,
      $tf["text"] => $text,
      $tf["id_setting"] => $id_setting,
      $tf["author"] => $user->getId(),
      $tf["ai"] => $ai ? 1 : 0,
    ]);
  }

  /**
   * Builds the AI prompt
   *
   * @param array $prompt
   * @return string Built prompt string
   */
  private function buildPromptFromRow(array $prompt): string|null
  {
    $content = $prompt["content"] ?? "";
    if (empty($content) && !empty($prompt["id_note"])) {
      $note = $this->note->get($prompt["id_note"]);
      if (empty($note) || empty($note["content"])) {
        return null;
      }

      $content = $note["content"];
    }

    return $this->buildPrompt(
      $content,
      !empty($prompt["output_format"]) ? $prompt["output_format"] : "textarea",
      $prompt['output_language'] ?? null,
    );
  }

  /**
   * Builds the AI prompt based on input, content, prompt, and separator
   *
   * @param string $prompt Prompt string
   * @param string $format Response format
   * @param string|null $lang Language of the response
   * @param array|null $cfg Configuration array
   * @return string Built prompt string
   */
  private function buildPrompt(
    string $prompt,
    string $format = "textarea",
    string|null $lang = null,
    array|null $cfg = null,
  ): string|null {
    $output = "";
    if (!empty($format)
      && ($fo = X::getField(self::$responseFormats, [Str::isUid($format) ? "id" : "code" => $format], "prompt"))
    ) {
      $output .= $fo . "\n";
    }

    if (!empty($lang)
      && ($idLang = Str::isUid($lang) ? $lang : self::getOptionId($lang, "languages"))
    ) {
      if ($ot = self::getOptionsObject()->text($idLang)) {
        $output .= "The language of the response must be in " . $ot . "\n";
      }
    }

    $output .= $prompt;

    return $output;
  }
}

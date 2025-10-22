<?php

namespace bbn\Appui;

use Exception;
use bbn\Db;
use bbn\X;
use bbn\Str;
use bbn\User;
use bbn\User\Preferences;
use bbn\File\System;
use bbn\Models\Tts\DbActions;
use bbn\Models\Tts\Optional;
use bbn\Models\Cls\Db as DbCls;
use Orhanerday\OpenAi\OpenAi;
use Gioni06\Gpt3Tokenizer\Gpt3TokenizerConfig;
use Gioni06\Gpt3Tokenizer\Gpt3Tokenizer;


class Ai extends DbCls
{
  use DbActions;
  use Optional;

  protected const MAX_TOKENS = 4000;
  
  /**
   * Default Dbconfig configuration for the class
   *
   * @var array $default_class_cfg
   */
  protected static $default_class_cfg = [
    'table' => 'bbn_ai_prompt',
    'tables' => [
      'prompt' => 'bbn_ai_prompt',
      'prompt_items' => 'bbn_ai_prompt_items',
      'prompt_settings' => 'bbn_ai_prompt_settings',
    ],
    'arch' => [
      'prompt' => [
        'id' => 'id',
        'id_note' => 'id_note',
        'input' => 'input',
        'output' => 'output',
        'creation_date' => 'creation_date',
        'usage_count' => 'usage_count',
        'shortcode' => 'shortcode'
      ],
      'prompt_items' => [
        'id' => 'id',
        'id_prompt' => 'id_prompt',
        'text' => 'text',
        'author' => 'author',
        'creation_date' => 'creation_date',
        'mime' => 'mime',
        'ai' => 'ai',
      ],
      'prompt_settings' => [
        'id' => 'id',
        'id_prompt' => 'id_prompt',
        'model' => 'model',
        'def' => 'def',
        'last_use' => 'last_use',
        'hash' => 'hash',
        'cfg' => 'cfg',
      ],
    ]
  ];
  
  /**
   * Response formats for the AI
   *
   * @var array $responseFormats
   */
  private static array $responseFormats;
  
  /**
   * OpenAI API instance
   *
   * @var OpenAi $ai
   */
  protected OpenAi $ai;

  protected Preferences $prefs;
  
  /** @var Note $note Note instance */
  private Note $note;

  /** @var Passwords $pass */
  private Passwords $pass;

  private System $fs;
  private User $user;

  protected string $endpoint;

  protected ?string $model;

  protected string $baseUrl;

  private string $key;
  protected array $cfg;
  
  /**
   * Ai constructor.
   *
   * @param Db $db The database connection
   * @param array $cfg Configuration array for the class
   *
   * @throws Exception If OpenAI key is not defined
   */
  public function __construct(Db $db, array $cfg = [])
  {
    parent::__construct($db);
    $this->initClassCfg($cfg);
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

  private static function init()
  {
    if (!self::$optional_is_init) {
      self::optionalInit();
      self::$responseFormats = self::getOptions('formats');
    }
  } 

  public function setEndpoint(string|null $id = null, $model = null) {
    if ($endpoint = $this->getEndpoint($id)) {
      $pass = $this->pass->userGet($endpoint['data']['id'], $this->user);
      if (!$pass) {
        throw new Exception("Password not found");
      }
    }

    if (!$endpoint) {
      throw new Exception("Endpoint not found");
    }

    $this->ai = new OpenAi($pass);
    $this->ai->setBaseURL($endpoint['data']['url']);
    if (!$model) {
      $model = $endpoint['models'][0]['text'];
    }

    $this->endpoint = $id;
    $this->setModel($model);
  }

  public function setModel(string|null $modelName = null) {
    $this->model = $modelName;
  }

  public function setCfg(array $cfg): void
  {

  }


  public function getModels()
  {
    $res = $this->ai->listModels();
    if ($res && is_string($res)) {
      $res = json_decode($res, true);
    }

    return $res;
  }

  

  public function syncModels(): bool
  {
    if (isset($this->endpoint) && ($models = $this->getModels())) {
      $endpoint = $this->getEndpoint($this->endpoint);
      $currentModels = $endpoint['models'] ?? [];
      $idModels = self::getOptionId('models');
      foreach ($models['data'] as $model) {
        if (!X::getRow($currentModels, ['text' => $model['id']])) {
          $this->prefs->addBit($this->endpoint, [
            'text' => $model['id'],
            'id_option' => $idModels
          ]);
        }
      }

      return true;
    }

    return false;
  }

  public function getEndpoints()
  {
    if ($idEndpoint = self::getOptionId('endpoints')) {
      return $this->prefs->getAll($idEndpoint);
    }

    throw new Exception(X::_("Endpoints not found"));
  }

  public function getEndpoint(string $id): ?array
  {
    if ($endpoint = $this->prefs->get($id)) {
      $models = $this->prefs->getBits($id);
      X::sortBy($models, [
        [
          'field' => 'last_used',
          'dir' => 'DESC'
        ], [
          'field' => 'text',
          'dir' => 'ASC'
        ]
      ]);
      return [
        'data' => $endpoint,
        'models' => $models
      ];
    }

    return null;
  }

  public function addEndpoint(string $name, string $url, string $pass, bool $public = false): ?array
  {
    if ($idEndpoint = self::getOptionId('endpoints')) {
      $this->ai = new OpenAi($pass);
      $this->ai->setBaseURL($url);
      if (
        ($idPref = $this->prefs->addToGroup($idEndpoint, [
          'text' => $name,
          'url' => $url,
          'public' => $public ? 1 : 0
        ]))
        && ($this->pass->userStore($pass, $idPref, $this->user))
      ) {
        $this->setEndpoint($idPref);
        $this->syncModels();

        return $this->getEndpoint($idPref);
      }
      else {
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
  public function getPromptResponseFromId(string $id_prompt, string $input, bool $insert = true, ?array $cfg = null): array
  {
    // check if input and id_prompt are not empty and not null
    if (empty($input) || empty($id_prompt)) {
      return [
        'success' => false,
        'error' => 'Input and prompt ID cannot be empty',
      ];
    }


    $prompt = $this->dbTraitRselect($id_prompt);
    
    if (empty($prompt)) {
      return [
        'success' => false,
        'error' => 'Prompt not found',
      ];
    }
    
    //$format = self::$responseFormats[array_search($prompt['output'], array_column($this->responseFormats, 'value'))];
    $response = $this->getPromptResponse($prompt, $input, $cfg);
    if (!empty($response) && !empty($response['success']) && $insert) {
      $this->insertItem($id_prompt, $input, false);
      $this->insertItem($id_prompt, $response['result']['content'] ?? $response['error']['message'], true);
    }
    
    return $response;
    
  }
  
  public function getPromptResponse(array $prompt, string $input, ?array $cfg = null): array
  {
    // check if input and id_prompt are not empty and not null
    if (empty($input) || empty($prompt)) {
      return [
        'success' => false,
        'error' => 'Input and prompt cannot be empty',
      ];
    }
    
    //$format = self::$responseFormats[array_search($prompt['output'], array_column($this->responseFormats, 'value'))];
    $built_prompt = $this->buildPromptFromRow($prompt);
    
    X::log($built_prompt, 'ai_logs');
    
    $response = $this->request($built_prompt, $input, $cfg);

    $res = [
      'success' => !isset($response['error']),
      'input' => $built_prompt,
      'result' => $response['result'],
    ];

    if (!empty($response['error'])) {
      $res['error'] = $response['error'];
    }

    return $res;
  }
  
  public function createMessages(string $input, string $prompt = ''): array
  {
    $messages = [];
    
    if ($prompt) {
      $messages[] = [
        'role' => 'system',
        'content' => $prompt
      ];
    }
    
    $messages[] = [
      'role' => 'user',
      'content' => $input
    ];
    
    return $messages;

  }

  public function createRequest(array $messages, array $cfg): array
  {
    $config = new Gpt3TokenizerConfig();
    $tokenizer = new Gpt3Tokenizer($config);
    $max_tokens = self::MAX_TOKENS;
    foreach ($messages as $message) {
      $max_tokens -= $tokenizer->count($message['content']);
    }
    $request = [
      "model" => $this->model,
      "messages" => $messages,
      "max_tokens" => $max_tokens,
    ];

    if (array_key_exists('temperature', $cfg)) {
      $request['temperature'] = $cfg['temperature'];
    }
    if (array_key_exists('top_p', $cfg)) {
      $request['top_p'] = $cfg['top_p'];
    }
    if (array_key_exists('frequency', $cfg)) {
      $request['frequency_penalty'] = $cfg['frequency'];
    }
    if (array_key_exists('presence', $cfg)) {
      $request['presence_penalty'] = $cfg['presence'];
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

    X::log(['request' => $query, 'complete' => $complete], 'ai_logs');
    
    if (!$complete || !empty($complete['error'])) {
      return [
        'success' => false,
        'error' => $complete['error'] ?? 'Error in the request'
      ];
    }
    
    $content = $complete['choices'][0]['message']['content'];
    X::log($content, 'ai_logs');
    
    return [
      'success' => true,
      'result' => $complete['choices'][0]['message'],
      'response' => $complete,
      'request' => $query
    ];
  }
  
  public function getChatTitle(string $text) : string
  {
    return Str::genpwd(10);
  }

  public function getTags(string $text): array
  {
    // This method should return an array of tags based on the input text
    // For now, we will return an empty array
    return [];
  }
  
  public function chat(string $input, array $cfg, string $id = ''): array
  {
    $startTime = time();
    $messages = $this->createMessages($input);
    $query = $this->createRequest($messages, $cfg);
    $result = $this->request($query);
    if ($result['success']) {
      $responseTime = time();
      $result['text']    = $result['result']['content'];
      $fullText = '';
      $messages[] = [
        'role' => 'assistant',
        'content' => $result['text']
      ];

      foreach ($messages as $message) {
        $fullText .= "**Message by $message[role]**" . PHP_EOL . $message['content'] . PHP_EOL . PHP_EOL . PHP_EOL;
      }

      $result['id']      = $id ?: Str::genpwd(10);
      $result['date']    = $startTime;
      $result['rdate']   = $responseTime;
      $result['input']   = $input;
      $result['request'] = $result;
      $result['title']   = $this->getChatTitle($fullText);
      $path           = $this->user->getDataPath('appui-ai') . 'chat';
      $summaryFile    = 'conversations.json';
      $this->fs->cd($path);
      $this->fs->createPath('conversations');
      $summary     = $this->fs->exists($summaryFile) ? $this->fs->decodeContents($summaryFile, 'json', true) : [];
      $summaryHash = md5(serialize($summary));
      $index       = X::search($summary, ['id' => $id]);
      $row         = $summary[$index] ?? null;
      if ($row) {
        if ($index !== 0) {
          array_splice($summary, $index, 1);
        }

        $conversation = $this->fs->decodeContents($row['file'], 'json', true);
      }
      else {
        $subpath = substr(X::makeStoragePath('conversations', 'Y', 100, $this->fs), strlen($path) + 1);
        $result['file'] = $subpath . $result['id'] . '.json';
        $row = [
          'title' => $result['title'],
          'id'    => $result['id'],
          'file'  => $result['file'],
          'num' => 0,
          'tags' => [],
          'creation' => $startTime,
          'last' => $responseTime,
        ];

        $conversation = [
          'id' => $result['id'],
          'title' => $result['title'],
          'num' => 0,
          'tags' => [],
          'creation' => $startTime,
          'last' => $responseTime,
          'conversation' => []
        ];
      }

      $conversation['num']++;
      $conversation['last'] = $responseTime;
      $row['num']++;
      $row['last'] = $responseTime;
      $lastMessage = [
        'messages' => [
          ...$query['messages'], 
          [
            'role' => 'assistant',
            'content' => $result['text']
          ]
        ],
        'title'        => $result['title'],
        'asked'        => $startTime,
        'responded'    => $responseTime,
        'id' => $result['id'],
        'tags' => $this->getTags($fullText),
        'cfg' => $cfg
      ];

      $conversation['conversation'][] = $lastMessage;
      $result['conversation'] = $id ? end($conversation['conversation']) : $conversation;

      $this->fs->encodeContents($conversation, $row['file'], 'json');

      if ($index !== 0) {
        array_unshift($summary, $row);
      }

      if (md5(serialize($summary)) !== $summaryHash) {
        $this->fs->encodeContents($summary, $summaryFile, 'json');
      }
    }
    elseif (empty($result['error'])) {
      $result['error'] = X::_("Unknown error");
    }



    return $result;
  }
  
  /**
   * @throws Exception
   */
  public function saveConversation(
    string $path,
    string $date,
    string $userFormat,
    string $prompt,
    ?string $response,
    ?array $cfg = null
  ): array {
    $timestamp = time();
    $fs = new System();
    if ($fs->exists($path)) {
      $jsonData = $fs->decodeContents($path, 'json', true);
  
    } else {
      $jsonData = [];
    }

    $d = [[
      "ai" => 0,
      "creation_date" => $date,
      "text" => $prompt,
      'id' => bin2hex(random_bytes(10)),
      'format' => $userFormat ?? 'textarea'
    ], [
      "ai" => 1,
      "creation_date" => $timestamp,
      "text" => $response,
      'id' => bin2hex(random_bytes(10)),
    ]];

    if ($cfg) {
      $d[1]['cfg'] = $cfg;
    }
    if (isset($jsonData['conversation'])) {
      array_push($jsonData['conversation'], ...$d);
    } else {
      array_push($jsonData, ...$d);
    }

    $fs->encodeContents($jsonData, $path, 'json');
    return $d;
  }
  
  
  public function clearConversation(string $id) : bool {
    if (empty($id)) {
      return false;
    }
    
    if (!$this->getPromptById($id)) {
      return false;
    }
    
    $ccfg = $this->getClassCfg();
    $this->db->delete($ccfg['tables']['prompt_items'], [
      $ccfg['arch']['prompt_items']['id_prompt'] => $id
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
    $where = [
    ];
    
    /*if ($private) {
      // If private, search only for prompts where shortcode is null
      $where[] = [
        'field' => $this->class_cfg['arch']['ai_prompt']['shortcode'],
        'operator' => 'isnull'
      ];
    }*/
    
    $ccfg = $this->getClassCfg();
    $prompts = $this->db->rselectAll([
      'tables' => [$ccfg['tables']['prompt']],
      'where' => $where,
      'order' => [[
        'field' => $ccfg['arch']['prompt']['creation_date'],
        'dir' => 'DESC'
      ]],
    ]);
    
    foreach ($prompts as &$p) {
      $note = $this->note->get($p['id_note']);
      $p['title'] = $note['title'];
      $p['content'] = $note['content'];
      $p['lang'] = $note['lang'];
    }
    
    return $prompts;
  }
  
  /**
   * Retrieves a prompt based on the specified shortcode.
   *
   * @param string $shortcode The shortcode of the prompt.
   * @return mixed The prompt data if found, otherwise null.
   */
  public function getPromptByShortcode(string $shortcode)
  {
    $ccfg = $this->getClassCfg();
    $prompt = $this->dbTraitRselect([
      'tables' => [$ccfg['tables']['prompt']],
      'where' => [
        $ccfg['arch']['prompt']['shortcode'] => $shortcode
]
    ]);
    
    if (!empty($prompt)) {
      $note = $this->note->get($prompt['id_note']);
      $prompt['title'] = $note['title'];
      $prompt['content'] = $note['content'];
      $prompt['lang'] = $note['lang'];
    }
    
    return $prompt;
  }
  
  /**
   * Retrieves a prompt based on the specified ID.
   *
   * @param string $id The ID of the prompt.
   * @return mixed The prompt data if found, otherwise null.
   */
  public function getPromptById(string $id)
  {
    $prompt = $this->dbTraitRselect($id);
    if (!empty($prompt)) {
      $note = $this->note->get($prompt['id_note']);
      $prompt['title'] = $note['title'];
      $prompt['content'] = $note['content'];
      $prompt['lang'] = $note['lang'];
      $prompt['items'] = [];
    }
    
    return $prompt;
  }
  
  /**
   * Inserts a new prompt into the database.
   *
   * @param string $title The title of the prompt.
   * @param string $content The content of the prompt.
   * @param string $lang The language of the prompt.
   * @param string $input The input of the prompt.
   * @param string $output The output of the prompt.
   * @param string|null $shortcode The shortcode of the prompt (optional).
   * @return mixed The ID of the inserted prompt if successful, otherwise null.
   */
  public function insertPrompt(array $data): ?string
  {
    $option = Option::getInstance();
    if (!X::hasProps($data, ['title', 'content', 'input', 'output', 'model'])) {
      throw new Exception("Missing required data");
    }

    $id_option = $option->fromCode('prompt', 'types', 'note', 'appui');
    $id_note = $this->note->insert($data['title'], $data['content'], $id_option, true, false, NULL, NULL, 'text/plain', $data['lang']);
    $ccfg = $this->getClassCfg();

    if ($this->dbTraitInsert([
      $ccfg['arch']['prompt']['id_note'] => $id_note,
      $ccfg['arch']['prompt']['input'] => $data['input'],
      $ccfg['arch']['prompt']['output'] => $data['output'],
      $ccfg['arch']['prompt']['shortcode'] => $data['shortcode'] ?: null
    ])) {
      $idPrompt = $this->db->lastId();
      $cfg = $data['cfg'];
      ksort($cfg);
      if ($cfg) {
        $this->insertSettings($idPrompt, $data['model'], $cfg);
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
    if (!X::hasProps($data, ['title', 'content', 'input', 'output', 'model', 'cfg'])) {
      throw new Exception("Missing required data");
    }

    $prompt = $this->getPromptById($id);
    if (empty($prompt)) {
      throw new Exception("Unrecognized prompt ID");
    }
    
    $note = $this->note->get($prompt['id_note']);
    if (empty($note)) {
      throw new Exception("The corresponding notedoes not exist");
    }
    
    // Update the title and content of the associated note
    $noteUpdate = [];
    if (isset($data['lang']) && ($data['lang'] !== $note['lang'])) {
      $noteUpdate['lang'] = $data['lang'];
    }

    if ($data['title'] !== $note['title']) {
      $noteUpdate['title'] = $data['title'];
    }

    if ($data['content'] !== $note['content']) {
      $noteUpdate['content'] = $data['content'];
    }

    $res1 = false;
    if (!empty($noteUpdate)) {
      $res1 = $this->note->update($note['id'], $noteUpdate);
    }

    $ccfg = $this->getClassCfg();
    // Update the prompt with the provided ID, input, and output values
    $res2 = $this->dbTraitUpdate($id, [
      $ccfg['arch']['prompt']['input'] => $data['input'],
      $ccfg['arch']['prompt']['output'] => $data['output'],
      $ccfg['arch']['prompt']['shortcode'] => $data['shortcode'],
    ]);

    $res3 = $this->insertSettings($id, $data['model'], $data['cfg']);
    
    return (bool)($res1 || $res2 || $res3);
  }
  
  public function deletePrompt(string $id) {
    $prompt = $this->getPromptById($id);
  
    if (empty($prompt)) {
      // If the prompt does not exist, return false to indicate the failure
      return false;
    }
    
    $note = $this->note->get($prompt['id_note']);
    
    if (empty($note)) {
      // If the associated note does not exist, return false to indicate the failure
      return false;
    }

    $ccfg = $this->getClassCfg();
    $this->db->delete($ccfg['tables']['prompt_items'], [
      $ccfg['arch']['prompt_items']['id_prompt'] => $id
    ]);
  
    $this->dbTraitDelete($id);
    
    return true;
  }


  public function insertSettings(string $id_prompt, string $model, ?array $cfg = null): ?string
  {
    if (empty($id_prompt) || empty($model)) {
      return false;
    }

    ksort($cfg);
    $json = json_encode($cfg);
    $hash = md5($model .'|' . $json);
    $ccfg = $this->getClassCfg();
    $id = $this->db->selectOne(
      $ccfg['tables']['prompt_settings'],
      $ccfg['arch']['prompt_settings']['id'],
      [
        $ccfg['arch']['prompt_settings']['id_prompt'] => $id_prompt,
        $ccfg['arch']['prompt_settings']['hash'] => $hash,
      ]
    );
    if ($id) {
      return $id;
    }

    $data = [
      $ccfg['arch']['prompt_settings']['id_prompt'] => $id_prompt,
      $ccfg['arch']['prompt_settings']['model'] => $model,
      $ccfg['arch']['prompt_settings']['hash'] => $hash,
      $ccfg['arch']['prompt_settings']['cfg'] => $json,
    ];
    if (!$this->db->selectOne(
      $ccfg['tables']['prompt_settings'],
      $ccfg['arch']['prompt_settings']['id'],
      [
        $ccfg['arch']['prompt_settings']['id_prompt'] => $id_prompt,
        $ccfg['arch']['prompt_settings']['def'] => 1
      ]
    )) {
      $data[$ccfg['arch']['prompt_settings']['def']] = 1;
    }

    if ($this->db->insert($ccfg['tables']['prompt_settings'], $data)) {
      return $this->db->lastId();
    }

    return null;
  }
  
  /**
   * Inserts an AI prompt item into the database
   *
   * @param string $id_prompt ID of the AI prompt to insert the item into
   * @param string $text Text of the AI prompt item
   * @param string $ai AI response of the prompt item
   *
   * @return void
   */
  private function insertItem(string $id_prompt, string $text, string $ai)
  {
    $ccfg = $this->getClassCfg();
    $tf = $ccfg['arch']['prompt_items'];
    $user = User::getInstance();
    $this->db->insert($ccfg['tables']['prompt_items'], [
      $tf['id_prompt'] => $id_prompt,
      $tf['text'] => $text,
      $tf['author'] => $user->getId(),
      $tf['ai'] => $ai ? 1 : 0,
    ]);
  }
  
  
  /**
   * Builds the AI prompt based on input, content, prompt, and separator
   *
   * @param string $input Input string
   * @param string $prompt Prompt string
   * @param string $responseFormat Response format
   * @param string $separator Separator string
   * @return string Built prompt string
   */
  private function buildPromptFromRow(array $prompt): string | null
  {
    $content = $prompt['content'] ?? '';
    if (empty($content) && !empty($prompt['id_note'])) {
      $note = $this->note->get($prompt['id_note']);
      if (empty($note) || empty($note['content'])) {
        return null;
      }

      $content = $note['content'];
    }

    return $this->buildPrompt($content, $prompt['output'], $note['lang']);
  }
  
  
  /**
   * Builds the AI prompt based on input, content, prompt, and separator
   *
   * @param string $prompt Prompt string
   * @param string $responseFormat Response format
   * @param string $separator Separator string
   * @return string Built prompt string
   */
  private function buildPrompt(string $prompt, string $format = 'textarea', string|null $lang = null, array|null $cfg = null): string | null
  {
    $output = $prompt;
    $output .= "\n\n";
    
    if ($format) {
      $output .= X::getField(self::$responseFormats, ['value' => $format], 'prompt') . "\n\n";
    }

    if ($lang) {
      $option = Option::getInstance();
      $output .= "The language of the response must be in " . $option->text($lang, 'languages', 'core', 'appui');
    }
    
    return $output;
  }
}

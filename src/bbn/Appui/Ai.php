<?php

namespace bbn\Appui;

use bbn\Models\Tts\Dbconfig;
use bbn\Models\Cls\Db as DbCls;
use bbn\Db;
use Orhanerday\OpenAi\OpenAi;
use Exception;
use bbn\X;
use bbn\User;
use Gioni06\Gpt3Tokenizer\Gpt3TokenizerConfig;
use Gioni06\Gpt3Tokenizer\Gpt3Tokenizer;


class Ai extends DbCls
{
  
  use Dbconfig;
  
  /**
   * Default Dbconfig configuration for the class
   *
   * @var array $default_class_cfg
   */
  protected static $default_class_cfg = [
    'table' => 'bbn_ai_prompt',
    'tables' => [
      'ai_prompt' => 'bbn_ai_prompt',
      'ai_prompt_items' => 'bbn_ai_prompt_items',
      'notes_versions' => 'bbn_notes_versions',
      'notes' => 'bbn_notes'
    ],
    'arch' => [
      'ai_prompt' => [
        'id' => 'id',
        'id_note' => 'id_note',
        'input' => 'input',
        'output' => 'output',
        'creation_date' => 'creation_date',
        'usage_count' => 'usage_count',
        'shortcode' => 'shortcode',
      ],
      'ai_prompt_items' => [
        'id' => 'id',
        'id_prompt' => 'id_prompt',
        'text' => 'text',
        'author' => 'author',
        'creation_date' => 'creation_date',
        'mime' => 'mime',
        'ai' => 'ai',
      ],
      'notes_versions' => [
        'id_note' => 'id_note',
        'version' => 'version',
        'latest' => 'latest',
        'title' => 'title',
        'content' => 'content',
        'excerpt' => 'excerpt',
        'id_user' => 'id_user',
        'creation' => 'creation',
      ],
      'notes' => [
        'id' => 'id',
        'id_parent' => 'id_parent',
        'id_alias' => 'id_alias',
        'id_type' => 'id_type',
        'id_option' => 'id_option',
        'mime' => 'mime',
        'lang' => 'lang',
        'private' => 'private',
        'locked' => 'locked',
        'pinned' => 'pinned',
        'important' => 'important',
        'creator' => 'creator',
        'active' => 'active',
      ]
    ]
  ];
  
  /**
   * Response formats for the AI
   *
   * @var array $responseFormats
   */
  private static $responseFormats = [
    [
      'value' => 'rte',
      'text' => 'Rich Text Editor',
      'prompt' => 'Your response needs to be in rich text format',
      'component' => 'bbn-rte',
    ],
    [
      'value' => 'markdown',
      'text' => 'Markdown',
      'prompt' => 'Your response needs to be in Markdown format',
      'component' => 'bbn-markdown',
    ],
    [
      'value' => 'textarea',
      'text' => 'Text Multiline',
      'prompt' => 'Your response needs to be returned as multiple lines of text',
      'component' => 'bbn-textarea',
    ],
    [
      'value' => 'code-php',
      'text' => 'Code PHP',
      'prompt' => 'Your response needs to be pure code in PHP',
      'component' => 'bbn-code',
    ],
    [
      'value' => 'code-js',
      'text' => 'Code JS',
      'prompt' => 'Your response needs to be pure code in Javascript',
      'component' => 'bbn-code',
    ],
    [
      'value' => 'single-line',
      'text' => 'Single Line',
      'prompt' => 'Your response needs to be entered as a single line of text',
      'component' => 'bbn-input',
    ],
    [
      'value' => 'json-editor',
      'text' => 'JSON',
      'prompt' => 'Your response needs to be a valid JSON object',
      'component' => 'bbn-json-editor',
    ]
  ];
  
  /**
   * OpenAI API instance
   *
   * @var OpenAi $ai
   */
  private $ai;
  
  /**
   * Note instance
   *
   * @var Note $note
   */
  private $note;
  
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
    $this->_init_class_cfg($cfg);
    if (!defined("BBN_OPENAI_KEY")) {
      throw new Exception("The OpenAI key is not defined");
    }
    $this->ai = new OpenAi(BBN_OPENAI_KEY);
    
    $this->note = new Note($this->db);
  }
  
  /**
   * Gets the AI prompt response based on input, response type, and prompt ID
   *
   * @param string $id_prompt ID of the AI prompt
   * @param string $input Input string
   * @param string $response_type Response type
   * @return array Response array containing success flag and result or error message
   */
  public function getPromptResponse(string $id_prompt, string $input, bool $insert = true): array
  {
    // check if input and id_prompt are not empty and not null
    if (empty($input) || empty($id_prompt)) {
      return [
        'success' => false,
        'error' => 'Input and prompt ID cannot be empty',
      ];
    }
    
    $prompt = $this->rselect($id_prompt);

    if (empty($prompt)) {
      return [
        'success' => false,
        'error' => 'Prompt not found',
      ];
    }
    
    //$format = self::$responseFormats[array_search($prompt['output'], array_column($this->responseFormats, 'value'))];
    $build_prompt = $this->buildPrompt($prompt);
    
    $response = $this->request($build_prompt, $input);
    if (!empty($response) && $insert) {
      $this->insertItem($id_prompt, $input, false);
      $this->insertItem($id_prompt, $response['result']['content'] ?? $response['error']['message'], true);
    }
    
    return [
      'success' => !isset($response['error']),
      'input' => $build_prompt,
      'result' => $response['result'] ?? $response['error'],
    ];

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
    $tf = $this->class_cfg['arch']['ai_prompt_items'];
    $user = User::getInstance();
    $this->db->insert($this->class_cfg['tables']['ai_prompt_items'], [
      $tf['id_prompt'] => $id_prompt,
      $tf['text'] => $text,
      $tf['author'] => $user->getId(),
      $tf['ai'] => $ai,
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
  private function buildPrompt(array $prompt): string | null
  {
    $note = $this->note->get($prompt['id_note']);
    
    if (empty($note) || empty($note['content'])) {
      return null;
    }
    
    $option = Option::getInstance();
    $build_prompt = $note['content'];
    $build_prompt .= "\n";
    
    $format = X::getField(self::$responseFormats, ['value' => $prompt['output']], 'prompt');
    
    if ($format) {
      $build_prompt .= $format . "\n";
    }
    
    if (!empty($note['lang'])) {
      $build_prompt .= "The language of the response must be in " . $option->text($note['lang'], 'languages', 'i18n', 'appui');
    }
    
    return $build_prompt;
  }
  
  /**
   * Sends a request to the OpenAI API and returns the response
   *
   * @param string $prompt Prompt string to send to the API
   * @return array Response array containing success flag and result or error message
   */
  public function request(string | null $prompt , string $input): array
  {
    // check if prompt is not empty but can be null
    if (empty($prompt) && !is_null($prompt)) {
      return [
        'success' => false,
        'error' => 'Prompt cannot be empty',
      ];
    }
  
    $config = new Gpt3TokenizerConfig();
    $tokenizer = new Gpt3Tokenizer($config);
    
    
    if (!$prompt) {
      
      $max_tokens = 4000 - $tokenizer->count($input);
      
      $complete = $this->ai->chat(
        [
          "model" => "gpt-3.5-turbo",
          "messages" => [
            [
              "role" => "user",
              "content" => $input
            ]
          ],
          "max_tokens" => $max_tokens,
        ],
      );
      
    } else {
  
      $max_tokens = 4000 - $tokenizer->count($input . $prompt);
  
      $complete = $this->ai->chat(
        [
          "model" => "gpt-3.5-turbo",
          "messages" => [
            [
              "role" => "system",
              "content" => $prompt
            ],
            [
              "role" => "user",
              "content" => $input
            ]
          ],
          "max_tokens" => $max_tokens,
        ]
      );
    }

    
    $complete = json_decode($complete, true);
    
    if (!$complete || !empty($complete['error'])) {
      return [
        'success' => false,
        'error' => $complete['error'] ?? 'Error in the request'
      ];
    }
    
    return [
      'success' => true,
      'result' => $complete['choices'][0]['message']
    ];
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
    
    if ($private) {
      // If private, search only for prompts where shortcode is null
      $where[] = [
        'field' => $this->class_cfg['arch']['ai_prompt']['shortcode'],
        'operator' => 'isnull'
      ];
    }
    
    $prompts = $this->db->rselectAll([
      'tables' => [$this->class_cfg['tables']['ai_prompt']],
      'where' => $where,
      'order' => [[
        'field' => $this->class_cfg['arch']['ai_prompt']['creation_date'],
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
    $prompt = $this->rselect([
      'tables' => [$this->default_class_cfg['tables']['ai_prompt']],
      'where' => [
        $this->default_class_cfg['arch']['ai_prompt']['shortcode'] => $shortcode
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
    $prompt = $this->rselect([
      'tables' => [$this->default_class_cfg['tables']['ai_prompt']],
      'where' => [
        $this->default_class_cfg['arch']['ai_prompt']['id'] => $id
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
  public function insertPrompt(string $title, string $content, string $lang, string $input, string $output, string $shortcode = null)
  {
    $option = Option::getInstance();
    
    // Get the option ID for 'prompt' type from the 'types' table in the 'note' application UI
    $id_option = $option->fromCode('prompt', 'types', 'note', 'appui');
    
    // Insert a new note with the provided title, content, option ID, plain text format, and language
    $id_note = $this->note->insert($title, $content, $id_option, true, false, NULL, NULL, 'text/plain', $lang);
    
    // Insert the prompt into the database with the note ID, input, output, and shortcode
    return $this->insert([
      $this->class_cfg['arch']['ai_prompt']['id_note'] => $id_note,
      $this->class_cfg['arch']['ai_prompt']['input'] => $input,
      $this->class_cfg['arch']['ai_prompt']['output'] => $output,
      $this->class_cfg['arch']['ai_prompt']['shortcode'] => $shortcode,
    ]);
  }
  
  /**
   * Updates an existing prompt in the database.
   *
   * @param string $id The ID of the prompt to update.
   * @param string $title The updated title of the prompt.
   * @param string $content The updated content of the prompt.
   * @param string $input The updated input of the prompt.
   * @param string $output The updated output of the prompt.
   * @return bool True if the update was successful, false otherwise.
   */
  public function updatePrompt(string $id, string $title, string $content, string $input, string $output): bool
  {
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
    
    // Update the title and content of the associated note
    $this->note->update($note['id'], $title, $content);
    
    // Update the prompt with the provided ID, input, and output values
    $this->update($id, [
      $this->class_cfg['arch']['ai_prompt']['input'] => $input,
      $this->class_cfg['arch']['ai_prompt']['output'] => $output,
    ]);
    
    return true;
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

    
    $this->db->delete($this->class_cfg['tables']['ai_prompt_items'], [
      $this->class_cfg['arch']['ai_prompt_items']['id_prompt'] => $id
    ]);
  
    $this->db->delete($this->class_cfg['tables']['notes_versions'], [
      $this->class_cfg['arch']['notes_versions']['id_note'] => $note['id']
    ]);
  
    // Delete the prompt
    $this->delete($id);
  
    // Delete the associated note
    $this->note->remove($note['id']);
    
    return true;
  }
  
  
  public function clearConversation(string $id) : bool {
    if (empty($id)) {
      return false;
    }
    
    if (!$this->getPromptById($id)) {
      return false;
    }
    
    $this->db->delete($this->class_cfg['tables']['ai_prompt_items'], [
      $this->class_cfg['arch']['ai_prompt_items']['id_prompt'] => $id
    ]);
  }
}
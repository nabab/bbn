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
      'ai_prompt_items' => 'bbn_ai_prompt_items'
    ],
    'arch' => [
      'ai_prompt' => [
        'id' => 'id',
        'id_note' => 'id_note',
        'input' => 'input',
        'output' => 'output',
        'creation_date' => 'creation_date',
        'usage_count' => 'usage_count',
      ],
      'ai_prompt_items' => [
        'id' => 'id',
        'id_prompt' => 'id_prompt',
        'text' => 'text',
        'author' => 'author',
        'creation_date' => 'creation_date',
        'mime' => 'mime',
        'ai' => 'ai',
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
}
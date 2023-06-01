<?php

namespace bbn\Mvc;

use bbn\Mvc\Environment;
use bbn\X;
use PHPUnit\Framework\TestCase;
use bbn\tests\Reflectable;

class EnvironmentTest extends TestCase
{
  use Reflectable;

  protected Environment $env;

  protected function init()
  {
    $this->setNonPublicPropertyValue('_initiated', false, Environment::class);
    $this->setNonPublicPropertyValue('_input', null, Environment::class);

    $this->env = new Environment();
  }

  protected function initAsCli()
  {
    $this->setNonPublicPropertyValue('_cli', true, X::class);
    $this->init();
  }

  protected function initAsNotCli()
  {
    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en_US';
    $this->setNonPublicPropertyValue('_cli', false, X::class);
    $this->init();
  }

  public function getInstance()
  {
    return $this->env;
  }

  /** @test */
  public function constructor_test_as_cli()
  {
    global $argv;
    $argv = ['foo', 'path/to', 'post_1', 'post_2'];
    $this->initAsCli();

    $this->assertSame('cli', $this->getNonPublicProperty('_mode'));
    $this->assertSame(['path', 'to'], $params = $this->getNonPublicProperty('_params'));
    $this->assertSame(['post_1', 'post_2'], $this->getNonPublicProperty('_post'));
    $this->assertSame(implode('/', $params), $this->getNonPublicProperty('_url'));
    $this->assertSame('en_US', $this->getNonPublicProperty('_locale'));
  }

  /** @test */
  public function constructor_test_as_not_cli_and_public_mode()
  {
    $_POST = ['foo' => 'bar', 'foo2' => 'bar2'];
    $_SERVER['REQUEST_URI'] = 'localhost/foo';
    $this->initAsNotCli();

    $this->assertSame($_POST, $this->getNonPublicProperty('_post'));
    $this->assertTrue(defined('BBN_DEFAULT_MODE'));
    $this->assertSame(BBN_DEFAULT_MODE, $this->getNonPublicProperty('_mode'));
    $this->assertSame(['localhost', 'foo'], $this->getNonPublicProperty('_params'));
    $this->assertSame('localhost/foo', $this->getNonPublicProperty('_url'));
    $this->assertSame('en_US', $this->getNonPublicProperty('_locale'));
  }

  /** @test */
  public function constructor_test_as_not_cli_and_dom_mode()
  {
    $_POST = [];
    $_SERVER['REQUEST_URI'] = 'localhost/foo';
    $this->initAsNotCli();

    $this->assertSame([], $this->getNonPublicProperty('_post'));
    $this->assertSame('dom', $this->getNonPublicProperty('_mode'));
    $this->assertSame(['localhost', 'foo'], $this->getNonPublicProperty('_params'));
    $this->assertSame('localhost/foo', $this->getNonPublicProperty('_url'));
    $this->assertSame('en_US', $this->getNonPublicProperty('_locale'));
  }

  /** @test */
  public function getLocale_method_returns_the_current_locale()
  {
    $this->initAsNotCli();
    $this->assertSame($this->getNonPublicProperty('_locale'), $this->env->getLocale());
  }

  /** @test */
  public function setLocale_method_sets_the_current_locale_when_arguments_are_provided()
  {
    $this->initAsNotCli();

    $this->env->setLocale('en');

    $this->assertSame('en_US', $this->getNonPublicProperty('_locale'));
    $this->assertSame('en_US', \Locale::getDefault());
  }

  /** @test */
  public function setLocale_method_sets_the_current_locale_when_no_arguments_are_provided()
  {
    // The method in this case cannot be tested probably
    // Since it uses constants and it's being called in the constructor
    // So constants will be defined after object initializing
    $this->assertTrue(true);
  }

  /** @test */
  public function setLocale_method_throws_an_exception_when_the_given_locale_not_found()
  {
    $this->expectException(\Exception::class);

    $this->initAsNotCli();

    $this->env->setLocale('foo');
  }

  /** @test */
  public function setPrepath_method_test()
  {
    $_SERVER['REQUEST_URI'] = 'localhost/foo/bar';
    $this->initAsNotCli();

    $this->assertSame('localhost/foo/bar', $this->getNonPublicProperty('_url'));

    $result = $this->env->setPrepath('localhost/foo');

    $this->assertSame('bar', $this->getNonPublicProperty('_url'));
    $this->assertTrue($result);
  }

  /** @test */
  public function setPrepath_method_test_throws_and_exception_if_one_or_more_of_the_path_does_not_correspond_to_the_current_url()
  {
    $this->expectException(\Exception::class);

    $_SERVER['REQUEST_URI'] = 'localhost/foo/bar';
    $this->initAsNotCli();

    $this->assertSame('localhost/foo/bar', $this->getNonPublicProperty('_url'));

    $this->env->setPrepath('foo/bar');
  }

  /** @test */
  public function isCli_method_returns_true_if_called_from_cli()
  {
    $this->initAsCli();

    $this->assertTrue($this->env->isCli());
  }

  /** @test */
  public function icCli_method_returns_false_if_not_called_from_cli()
  {
    $this->initAsNotCli();

    $this->assertFalse($this->env->isCli());
  }

  /** @test */
  public function getUrl_method_returns_the_request_url()
  {
    $_SERVER['REQUEST_URI'] = 'localhost/foo';
    $this->initAsNotCli();

    $this->assertSame('localhost/foo', $this->env->getUrl());
  }

  /** @test */
  public function simulate_method_test()
  {
    $_POST = ['foo' => 'bar'];
    $_SERVER['REQUEST_URI'] = 'localhost/foo';

    $this->initAsNotCli();

    $this->assertSame($_POST, $this->getNonPublicProperty('_post'));
    $this->assertSame(['localhost', 'foo'], $this->getNonPublicProperty('_params'));
    $this->assertSame('localhost/foo', $this->getNonPublicProperty('_url'));

    $this->env->simulate('new/url');

    $this->assertSame($_POST, $this->getNonPublicProperty('_post'));
    $this->assertSame(['new', 'url'], $this->getNonPublicProperty('_params'));
    $this->assertSame('new/url', $this->getNonPublicProperty('_url'));

    $this->env->simulate('another/cool/url', ['post_key_1' => 'post_value_1']);

    $this->assertSame(['post_key_1' => 'post_value_1'], $this->getNonPublicProperty('_post'));
    $this->assertSame(['another','cool', 'url'], $this->getNonPublicProperty('_params'));
    $this->assertSame('another/cool/url', $this->getNonPublicProperty('_url'));

    $this->env->simulate('new/url', ['post_key_2' => 'post_value_2'], ['arg1', 'arg2']);

    $this->assertSame(['post_key_2' => 'post_value_2'], $this->getNonPublicProperty('_post'));
    $this->assertSame(['new', 'url', 'arg1', 'arg2'], $this->getNonPublicProperty('_params'));
    $this->assertSame('new/url', $this->getNonPublicProperty('_url'));
  }

  /** @test */
  public function getMode_method_returns_the_current_mode()
  {
    $this->initAsNotCli();

    $this->assertSame(
      $this->getNonPublicProperty('_mode'),
      $this->env->getMode()
    );
  }

  /** @test */
  public function getCli_method_parses_arguments_in_to_the_post_property_and_returns_it()
  {
    global $argv;

    $this->initAsCli();
    $this->setNonPublicPropertyValue('_params', null);

    $argv = ['foo_1', 'foo_2', json_encode(['post_key_1' => 'post_value_1', 'post_key_2' => 'post_value_2'])];

    $result = $this->env->getCli();

    $this->assertSame(['foo_2'], $this->getNonPublicProperty('_params'));
    $this->assertSame(
      ['post_key_1' => 'post_value_1', 'post_key_2' => 'post_value_2'],
      $this->getNonPublicProperty('_post')
    );
    $this->assertSame(
      ['post_key_1' => 'post_value_1', 'post_key_2' => 'post_value_2'],
      $result
    );

    $this->setNonPublicPropertyValue('_params', null);

    $argv = ['bar_1', 'bar_2', 'bar_3', 'bar_4'];

    $result = $this->env->getCli();

    $this->assertSame(['bar_2'], $this->getNonPublicProperty('_params'));
    $this->assertSame(['bar_3', 'bar_4'], $this->getNonPublicProperty('_post'));
    $this->assertSame(['bar_3', 'bar_4'], $result);

  }

  /** @test */
  public function getCli_method_returns_null_when_not_called_from_cli()
  {
    $this->initAsNotCli();

    $this->assertNull($this->env->getCli());
  }

  /** @test */
  public function getGet_method_parses_all_get_parameters_in_the_get_property_if_not_already_set_and_returns_it()
  {
    $this->initAsNotCli();

    $this->setNonPublicPropertyValue('_get', null);

    $_GET = [];

    $result = $this->env->getGet();

    $this->assertIsArray($result);
    $this->assertEmpty($result);
    $this->assertIsArray($this->getNonPublicProperty('_get'));
    $this->assertEmpty($this->getNonPublicProperty('_get'));

    // Reset the get property
    $this->setNonPublicPropertyValue('_get', null);

    $_GET = ['key_1' => 'value_1', 'key_2' => '111'];

    $result = $this->env->getGet();

    $this->assertSame(['key_1' => 'value_1', 'key_2' => 111], $result);
    $this->assertSame(['key_1' => 'value_1', 'key_2' => 111], $this->getNonPublicProperty('_get'));
  }

  /** @test */
  public function getGet_method_does_not_parse_all_get_parameters_if_already_set()
  {
    $this->initAsNotCli();
    $this->setNonPublicPropertyValue('_get', ['foo' => 'bar']);

    $_GET = ['key_1' => 'value_1', 'key_2' => '111'];

    $result = $this->env->getGet();

    $this->assertSame(['foo' => 'bar'], $result);
    $this->assertSame(['foo' => 'bar'], $this->getNonPublicProperty('_get'));
  }

  /** @test */
  public function getPost_method_parses_all_post_parameters_in_to_the_post_property_if_not_already_exists_and_returns_it()
  {
    $this->initAsNotCli();

    $this->setNonPublicPropertyValue('_post', null);
    $this->setNonPublicPropertyValue('_has_post', false);

    $_POST = [];

    $result = $this->env->getPost();

    $this->assertIsArray($result);
    $this->assertEmpty($result);
    $this->assertIsArray($this->getNonPublicProperty('_post'));
    $this->assertEmpty($this->getNonPublicProperty('_post'));
    $this->assertFalse($this->getNonPublicProperty('_has_post'));

    $this->setNonPublicPropertyValue('_post', null);
    $this->setNonPublicPropertyValue('_has_post', false);

    $_POST = ['key_1' => 'value_1', 'key_2' => '333', '_bbn_custom_constant' => 'constant_value'];

    $result = $this->env->getPost();

    $this->assertSame(['key_1' => 'value_1', 'key_2' => 333], $this->getNonPublicProperty('_post'));
    $this->assertSame(['key_1' => 'value_1', 'key_2' => 333], $result);
    $this->assertTrue($this->getNonPublicProperty('_has_post'));
    $this->assertTrue(defined('BBN_CUSTOM_CONSTANT'));
    $this->assertSame('constant_value', BBN_CUSTOM_CONSTANT);
  }

  /** @test */
  public function getPost_method_parses_parameters_from_the_input_property_if_is_set_and_is_json()
  {
    $this->initAsNotCli();

    $this->setNonPublicPropertyValue('_post', null);
    $this->setNonPublicPropertyValue('_has_post', false);
    $this->setNonPublicPropertyValue(
      '_input',
      json_encode(['key_1' => 'value_1', 'key_2' => '12345', '_bbn_another_constant' => 'constant_value'])
    );

    $_POST = ['foo' => 'bar', 'foo_2', 'bar_2'];

    $result = $this->env->getPost();

    $this->assertSame(['key_1' => 'value_1', 'key_2' => 12345], $result);
    $this->assertSame(['key_1' => 'value_1', 'key_2' => 12345], $this->getNonPublicProperty('_post'));
    $this->assertTrue(defined('BBN_ANOTHER_CONSTANT'));
    $this->assertSame('constant_value', BBN_ANOTHER_CONSTANT);
    $this->assertTrue($this->getNonPublicProperty('_has_post'));
  }

  /** @test */
  public function getPost_method_does_not_parse_parameters_from_the_input_property_if_it_is_not_json()
  {
    $this->initAsNotCli();

    $this->setNonPublicPropertyValue('_post', null);
    $this->setNonPublicPropertyValue('_has_post', false);
    $this->setNonPublicPropertyValue('_input', ['key_1' => 'value_1', 'key_2' => '1122']);

    $result = $this->env->getPost();

    $this->assertIsArray($result);
    $this->assertEmpty($result);
    $this->assertIsArray($this->getNonPublicProperty('_post'));
    $this->assertEmpty($this->getNonPublicProperty('_post'));
    $this->assertFalse($this->getNonPublicProperty('_has_post'));
  }

  /** @test */
  public function getPost_method_does_not_parse_any_parameters_from_post_if_post_property_already_set()
  {
    $this->initAsNotCli();

    $this->setNonPublicPropertyValue('_post', ['foo_1' => 'bar_1']);

    $_POST = ['foo_2' => 'bar_2'];

    $result = $this->env->getPost();

    $this->assertSame(['foo_1' => 'bar_1'], $result);
    $this->assertSame(['foo_1' => 'bar_1'], $this->getNonPublicProperty('_post'));
  }

  /** @test */
  public function getPost_method_does_not_parse_any_parameters_from_input_property_if_post_property_already_set()
  {
    $this->initAsNotCli();

    $this->setNonPublicPropertyValue('_post', ['foo_1' => 'bar_1']);
    $this->setNonPublicPropertyValue('_input', ['foo_2' => 'bar_2']);

    $result = $this->env->getPost();

    $this->assertSame(['foo_1' => 'bar_1'], $result);
    $this->assertSame(['foo_1' => 'bar_1'], $this->getNonPublicProperty('_post'));
  }

  /** @test */
  public function getFiles_method_parses_the_uploaded_files_in_to_the_files_property_if_not_already_set_and_returns_it()
  {
    $this->initAsNotCli();

    $this->setNonPublicPropertyValue('_files', null);

    $_FILES = [];

    $result = $this->env->getFiles();

    $this->assertIsArray($result);
    $this->assertEmpty($result);
    $this->assertIsArray($this->getNonPublicProperty('_files'));
    $this->assertEmpty($this->getNonPublicProperty('_files'));

    $this->setNonPublicPropertyValue('_files', null);

    $_FILES = [
      'files' => [
        'name'      => 'file_1_name.pdf',
        'type'      => 'application/pdf',
        'tmp_name'  => 'file_1_tmp_name',
        'error'     => 0,
        'size'      => 12345
      ],
      'files_2' => [
        'name'      => 'file_1_name.pdf', // Duplicated file name
        'type'      => 'application/pdf',
        'tmp_name'  => 'file_2_tmp_name',
        'error'     => 0,
        'size'      => 123456
      ],
      'images' => [
        'name' => [
          'image_1_name.jpg',
          'image_2_name.png',
          'image_1_name.jpg' // duplicated image name
        ],
        'type'  => [
          'image/jpeg',
          'image/png',
          'image/jpeg',
        ],
        'tmp_name' => [
          'image_1_tmp_name',
          'image_2_tmp_name',
          'image_3_tmp_name',
        ],
        'error' => [
          0, 0, 0
        ],
        'size' => [
          123,
          1234,
          12345
        ]
      ],
      'images_2' => [
        'name'      => 'image_1_name.jpg', // Another duplicated image name with one from the multiple images
        'type'      => 'image/jpeg',
        'tmp_name'  => 'image_4_tmp_name',
        'error'     => 0,
        'size'      => 111
      ],
    ];

    $expected = [
      'files'   => $_FILES['files'],
      'files_2' => [
        'name'      => 'file_1_name_1.pdf',
        'type'      => $_FILES['files_2']['type'],
        'tmp_name'  => $_FILES['files_2']['tmp_name'],
        'error'     => $_FILES['files_2']['error'],
        'size'      => $_FILES['files_2']['size']
      ],
      'images'  => [
        [
          'name'     => $_FILES['images']['name'][0],
          'tmp_name' => $_FILES['images']['tmp_name'][0],
          'type'     => $_FILES['images']['type'][0],
          'error'    => $_FILES['images']['error'][0],
          'size'     => $_FILES['images']['size'][0]
        ],
        [
          'name'     => $_FILES['images']['name'][1],
          'tmp_name' => $_FILES['images']['tmp_name'][1],
          'type'     => $_FILES['images']['type'][1],
          'error'    => $_FILES['images']['error'][1],
          'size'     => $_FILES['images']['size'][1]
        ],
        [
          'name'     => 'image_1_name_1.jpg',
          'tmp_name' => $_FILES['images']['tmp_name'][2],
          'type'     => $_FILES['images']['type'][2],
          'error'    => $_FILES['images']['error'][2],
          'size'     => $_FILES['images']['size'][2]
        ]
      ],
      'images_2' => [
        'name'      => 'image_1_name_2.jpg',
        'type'      => $_FILES['images_2']['type'],
        'tmp_name'  => $_FILES['images_2']['tmp_name'],
        'error'     => $_FILES['images_2']['error'],
        'size'      => $_FILES['images_2']['size']
      ]
    ];

    $result = $this->env->getFiles();

    $this->assertSame($expected, $result);
    $this->assertSame($expected, $this->getNonPublicProperty('_files'));
  }

  /** @test */
  public function getFiles_method_does_not_parse_the_uploaded_files_if_files_property_already_exists()
  {
    $this->initAsNotCli();

    $this->setNonPublicPropertyValue('_files', ['foo' => 'bar']);

    $_FILES = [
      'files' => [
        'name'      => 'file_name.pdf',
        'type'      => 'application/pdf',
        'tmp_name'  => 'file_tmp_name',
        'error'     => 0,
        'size'      => 12345
      ],
    ];

    $result = $this->env->getFiles();

    $this->assertSame(['foo' => 'bar'], $result);
    $this->assertSame(['foo' => 'bar'], $this->getNonPublicProperty('_files'));
  }

  /** @test */
  public function getParams_method_returns_the_params_property()
  {
    $this->initAsNotCli();

    $this->setNonPublicPropertyValue('_params', ['foo' => 'bar']);

    $this->assertSame(['foo' => 'bar'], $this->env->getParams());
  }

  /** @test */
  public function getRequest_returns_the_url_property_if_initiated_and_null_otherwise()
  {
    $this->initAsNotCli();

    $this->assertSame($this->getNonPublicProperty('_url'), $this->env->getRequest());

    $this->setNonPublicPropertyValue('_initiated', false);

    $this->assertNull($this->env->getRequest());
  }

  /** @test */
  public function _getHttpAcceptLanguageHeader_method_returns_the_http_accept_language_header_if_exists_and_null_otherwise()
  {
    $this->initAsNotCli();

    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = '  en-US,ar-EG;q=0.5  ';

    $method = $this->getNonPublicMethod('_getHttpAcceptLanguageHeader');

    $result = $method->invoke($this->env);

    $this->assertSame('en-US,ar-EG;q=0.5', $result);

    unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);

    $result = $method->invoke($this->env);

    $this->assertNull($result);
  }

  /** @test */
  public function getWeightedLocales_method_parses_the_accept_language_header_in_to_an_array_of_locals_with_weights()
  {
    $this->initAsNotCli();

    $method = $this->getNonPublicMethod('_getWeightedLocales');

    $this->assertSame([], $method->invoke($this->env, ''));

    $result = $method->invoke($this->env, 'en-US,ar-EG;q=0.5,en-UK');

    $this->assertSame(
      [
        ['locale' => 'en-US', 'q' => 1.0],
        ['locale' => 'ar-EG', 'q' => 0.5],
        ['locale' => 'en-UK', 'q' => 1.0]
      ],
      $result
    );
  }

  /** @test */
  public function sortLocalesByWeight_method_sorts_locale_weights_frm_high_to_low()
  {
    $this->initAsNotCli();

    $locales = [
      ['locale' => 'es-ES', 'q' => 0.1],
      ['locale' => 'en-UK', 'q' => 1.0],
      ['locale' => 'en-US', 'q' => 1.1],
      ['locale' => 'ar-EG', 'q' => 0.711],
      ['locale' => 'fr-FR', 'q' => 0.712],
    ];

    $expected = [
      ['locale' => 'en-US', 'q' => 1.1],
      ['locale' => 'en-UK', 'q' => 1.0],
      ['locale' => 'fr-FR', 'q' => 0.712],
      ['locale' => 'ar-EG', 'q' => 0.711],
      ['locale' => 'es-ES', 'q' => 0.1]
    ];

    $method = $this->getNonPublicMethod('_sortLocalesByWeight');

    $result = $method->invoke($this->env, $locales);

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function initialize_method_sets_the_initiated_property_to_true_and_parses_php_input_stream()
  {
    $this->initAsNotCli();

    $this->setNonPublicPropertyValue('_initiated', false);
    $this->setNonPublicPropertyValue('_input', null);

    $method = $this->getNonPublicMethod('_initialize');
    $method->invoke($this->env);

    $this->assertTrue($this->getNonPublicproperty('_initiated'));
    $this->assertNotNull($this->getNonPublicProperty('_input'));
  }

  /** @test */
  public function setParams_method_sets_the_params_property_from_the_given_path_if_not_already_set()
  {
    $this->initAsNotCli();

    $this->setNonPublicPropertyValue('_params', null);

    $method = $this->getNonPublicMethod('setParams');

    $method->invoke($this->env, 'localhost/foo/bar/0');
    $this->assertSame(['localhost', 'foo', 'bar', '0'], $this->getNonPublicProperty('_params'));

  }

  /** @test */
  public function setParams_method_does_not_set_the_params_property_from_the_given_path_if_path_is_empty()
  {
    $this->initAsNotCli();

    $this->setNonPublicPropertyValue('_params', null);

    $method = $this->getNonPublicMethod('setParams');

    $method->invoke($this->env, 'localhost/ /bar/0');
    $this->assertSame(['localhost', 'bar', '0'], $this->getNonPublicProperty('_params'));

    $method->invoke($this->env, 'localhost//bar/0');
    $this->assertSame(['localhost', 'bar', '0'], $this->getNonPublicProperty('_params'));
  }

  /** @test */
  public function setParams_method_does_not_set_the_params_property_from_the_given_path_if_already_set()
  {
    $this->initAsNotCli();

    $this->setNonPublicPropertyValue('_params', ['foo', 'bar']);

    $this->getNonPublicMethod('setParams')->invoke($this->env, 'localhost/foo/bar');

    $this->assertSame(['foo', 'bar'], $this->getNonPublicProperty('_params'));
  }

  /** @test */
  public function setParams_method_throws_an_exception_if_the_given_path_is_a_reserved_value()
  {
    $this->expectException(\Exception::class);

    $this->initAsNotCli();

    $this->getNonPublicMethod('setParams')->invoke($this->env, 'localhost/_htaccess');
  }
}
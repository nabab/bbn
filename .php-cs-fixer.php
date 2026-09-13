<?php

$finder = PhpCsFixer\Finder::create()
  ->in([
    __DIR__ . '/src',
    __DIR__ . '/tests'
  ])
  ->exclude([
    'vendor'
  ])
  ->name('*.php');

return (new PhpCsFixer\Config())
  ->setRiskyAllowed(true)
  ->setIndent('  ') // 2 spaces
  ->setLineEnding("\n")
  ->setRules([
    '@PSR12' => true,

    // indentation
    'indentation_type' => true,

    // ensure else starts on a new line
    'control_structure_continuation_position' => [
      'position' => 'next_line'
    ],

    // formatting of control structures
    'braces' => [
      'position_after_control_structures' => 'next'
    ],

    // spacing rules
    'no_extra_blank_lines' => true,
    'no_trailing_whitespace' => true,
    'array_syntax' => ['syntax' => 'short'],

    // optimize native function calls
    'native_function_invocation' => [
      'include' => ['@compiler_optimized'],
      'scope' => 'namespaced'
    ],

    // consistent if formatting
    'elseif' => true,

    // clean imports
    'no_unused_imports' => true,
    'ordered_imports' => true,
  ])
  ->setFinder($finder);
<?php

declare(strict_types=1);

require("vendor/autoload.php");

$cfg = new \PhpCsFixer\Config();

$cfg->registerCustomFixers([
    new \CommonGround\PhpCsFixer\WrapBlockCommentFixer(),
]);

$cfg->setRules([
    "@PSR12"                                      => true,
    "@PER-CS2.0"                                  => true,
    "@PHP84Migration"                             => true,

    "CommonGround/wrap_block_comments"            => ["width" => 80],
    "array_syntax"                                => true,
    "binary_operator_spaces"                      => [
        "default"   => "single_space",
        "operators" => [
            "=>" => "align_single_space_minimal_by_scope",
        ],
    ],
    "declare_parentheses"                         => true,
    "method_argument_space"                       => ["on_multiline" => "ensure_fully_multiline"],
    "method_chaining_indentation"                 => true,
    "multiline_comment_opening_closing"           => true,
    "multiline_whitespace_before_semicolons"      => ["strategy" => "new_line_for_chained_calls"],
    "no_extra_blank_lines"                        => true,
    "no_multiline_whitespace_around_double_arrow" => true,
    "no_trailing_comma_in_singleline"             => true,
    "no_unneeded_control_parentheses"             => true,
    "no_useless_return"                           => true,
    "no_whitespace_before_comma_in_array"         => true,
    "ordered_imports"                             => ["sort_algorithm" => "alpha"],
    "ordered_class_elements"                      => [
        "order"          => [
            "use_trait",
            "case",
            "constant",
            "property",
            "construct",
            "destruct",
            "magic",
            "method",
        ],
        "sort_algorithm" => "none",
    ],
    "single_line_comment_spacing"                 => true,
    "single_line_empty_body"                      => true,
    "single_space_around_construct"               => true,
    "string_implicit_backslashes"                 => true,
    "trailing_comma_in_multiline"                 => [
        "after_heredoc" => true,
        "elements"      => [
            "arguments",
            "arrays",
            "match",
            "parameters",
        ],
    ],
    "trim_array_spaces"                           => true,
    "unary_operator_spaces"                       => true,
    "whitespace_after_comma_in_array"             => ["ensure_single_space" => true],

    // Risky rules
    "declare_strict_types"                        => true,
    "non_printable_character"                     => true,
    "psr_autoloading"                             => true,
    "native_function_invocation"                  => true,
    "strict_param"                                => true,
]);

return $cfg;

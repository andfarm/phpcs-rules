<?php

declare(strict_types=1);

namespace CommonGround\PhpCsFixer;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\ConfigurableFixerInterface;
use PhpCsFixer\Fixer\ConfigurableFixerTrait;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolverInterface;
use PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Utils;

/**
 * WrapBlockCommentFixer wraps multi-line block comments to a configurable
 * maximum line length - 80 columns by default, configurable as "width".
 *
 * To be formatted, the first and last lines of the comment must consist only
 * of the comment opening and closing sequences. Lines within the comment must
 * begin with a "*" indented to the same level as the first asterisk in the
 * opening/closing comments. Comments not matching this pattern will be ignored.
 *
 * Comments in multibyte languages, including double-width characters, are
 * supported.
 *
 * Paragraphs of text are defined as consecutive lines of non-blank text within
 * the block comment. Each paragraph is reflowed to fill the maximum line
 * length. A single blank line is inserted between each consecutive paragraph.
 * (Multiple blank lines will be normalized to a single one.)
 *
 * Paragraphs beginning with an indent, bullet, and/or number will have that
 * indent preserved, and a hanging indent inserted for subsequent lines within
 * the paragraph. For example:
 *
 *  - This paragraph has an added indent of one space, and a leading bullet
 *    symbol. Its second line gets a hanging indent to match the first line.
 *
 *  2) Similarly, this paragraph is numbered "2", and its second line is
 *     indented as well.
 *
 * Bullets can consist of any of the characters `-`, `+`, `*`, and `#`.
 *
 * Numbers can be any length, but must be followed by any one of the characters
 * `.`, `:`, `)`, or `]`.
 */
class WrapBlockCommentFixer extends AbstractFixer implements ConfigurableFixerInterface, WhitespacesAwareFixerInterface
{
    use ConfigurableFixerTrait;

    public function getName(): string
    {
        return "CommonGround/wrap_block_comments";
    }

    public function getDefinition(): FixerDefinition
    {
        return new FixerDefinition(
            "Multi-line block comments must be wrapped to a maximum line length.",
            [
                new CodeSample(
                    "<?php\nif (true) {\n\t/*\n\t * This block comment is longer than 80 characters, so it will be wrapped to a more appropriate line width by CommonGround/wrap_block_comments.\n\t */\n}\n",
                ),
            ],
        );
    }

    public function createConfigurationDefinition(): FixerConfigurationResolverInterface
    {
        return new FixerConfigurationResolver([
            (new FixerOptionBuilder("width", "Width to wrap comments to"))
                ->setAllowedTypes(["int"])
                ->setDefault(80)
                ->getOption(),
        ]);
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isAnyTokenKindsFound([T_COMMENT, T_DOC_COMMENT]);
    }

    public function applyFix(\SplFileInfo $file, Tokens $tokens): void
    {
        $newline = $this->whitespacesConfig->getLineEnding();
        $width = (int) $this->configuration["width"];

        foreach ($tokens as $index => $token) {
            if (!$token->isComment()) {
                continue;
            }

            $content = $token->getContent();

            if (str_starts_with($content, "/*") && str_contains($content, $newline)) {
                $prevToken = $tokens[$index - 1];
                if ($prevToken->isWhitespace()) {
                    $indent = ltrim($prevToken->getContent(), $newline);
                } else {
                    $indent = "";
                }

                $wrapped = $this->rewrapBlockComment($content, $width, $indent, $newline);
                if ($wrapped !== null) {
                    $tokens[$index] = new Token([$token->getId(), $wrapped]);
                }
            }
        }
    }

    /**
     * Rewrap a block or docblock comment.
     *
     * Returns null on failure.
     */
    private function rewrapBlockComment(string $content, int $width, string $indent, string $newline): ?string
    {
        $lines = [];
        $indent_star = $indent . " *";

        foreach (explode($newline, $content) as $line) {
            if ($line === "/*" || $line === "/**" || $line === "") {
                continue;
            } elseif (!str_starts_with($line, $indent_star)) {
                return null;
            }

            $sub = substr($line, \strlen($indent_star));
            if ($sub === "/") { // end of comment
                continue;
            } elseif ($sub === "") {
                $lines[] = "";
            } elseif ($sub[0] === " ") {
                $lines[] = substr($sub, 1);
            } else {
                // Missing space, let's fix it
                $lines[] = $sub;
            }
        }

        // Compute available space as indent + space + asterisk + space, but
        // don't use less than 1/4 the default (e.g. 20 for 80 cols).
        $wrap_width = max($width - 3 - \strlen($indent), $width / 4);
        $lines = $this->rewrap($lines, $wrap_width);

        $wrapped = str_starts_with($content, "/**") ? "/**" : "/*";
        foreach ($lines as $line) {
            $wrapped .= $newline . $indent_star;
            if ($line !== "") {
                $wrapped .= " " . $line;
            }
        }
        $wrapped .= $newline . $indent_star . "/";
        return $wrapped;
    }

    /**
     * Rewrap a sequence of lines to fit within $width.
     *
     * @param list<string> $lines
     * @return list<string>
     */
    private function rewrap(array $lines, int $width): array
    {
        $output = [];
        $buf = [];
        $in_annotation = false;

        foreach ($lines as $line) {
            $break = false;
            if (str_starts_with($line, "@")) {
                // Don't wrap annotations, but keep them separate from paragraphs
                if (\count($buf)) {
                    $this->rewrapParagraph($output, $buf, $width);
                    $output[] = "";
                } elseif (\count($output) > 0 && !str_starts_with(end($output), "@")) {
                    $output[] = "";
                }
                $buf = [];
                $output[] = $line;
                $in_annotation = true;
            } elseif ($line === "") {
                $this->rewrapParagraph($output, $buf, $width);
                $buf = [];
                $in_annotation = false;
            } elseif ($in_annotation) {
                $output[] = $line;
            } else {
                $buf[] = $line;
            }
        }

        $this->rewrapParagraph($output, $buf, $width);
        return $output;
    }

    /**
     * @param list<string> $paragraph
     */
    private function rewrapParagraph(array &$output, array $paragraph, int $width): void
    {
        // Don't output empty "paragraphs"
        if (empty($paragraph)) {
            return;
        }

        // Add a blank line before each paragraph, except the first one
        if (!empty($output)) {
            $output[] = "";
        }

        // Check for paragraphs with an bullet, number, or other indent
        if (preg_match('/^\s*([-+*#]+|\d+[.:)\]])\s+/', $paragraph[0], $m)) {
            $first_indent = $m[0];
            $indent_width = \strlen($first_indent);
            $next_indent = str_repeat(" ", $indent_width);
            $width -= $indent_width;
            $paragraph[0] = substr($paragraph[0], $indent_width);
        } else {
            $first_indent = $next_indent = "";
        }

        // Strip all extra spaces and combine input to a single line
        $input_line = implode(" ", array_map(trim(...), $paragraph));

        // Word-wrap.
        $wrapped_lines = static::wordwrap($input_line, $width);

        $output[] = $first_indent . $wrapped_lines[0];
        for ($i = 1; $i < \count($wrapped_lines); $i++) {
            $output[] = $next_indent . $wrapped_lines[$i];
        }
    }

    /**
     * Similar to the core wordwrap(), function but multibyte-safe, and returns
     * an array of lines rather than a newline-separated string.
     */
    private static function wordwrap(string $string, int $width): array
    {
        $out = [];
        $pos = 0;

        $iter = \IntlBreakIterator::createLineInstance();
        $iter->setText($string);

        while ($pos < \strlen($string)) {
            // Unfortunately, the $start argument to mb_strimwidth counts in
            // characters, not bytes, so we have to use substr here.
            $line = mb_strimwidth(substr($string, $pos), 0, $width);
            $end = $pos + \strlen($line);

            if ($iter->isBoundary($end)) {
                // The line ended on a word boundary - lucky us!
                $out[] = trim($line);
                $pos = $end;
            } else {
                // Search for the previous word boundary and cut there
                $end = $iter->preceding($end);
                if ($end < $pos) {
                    // ... uh oh, this line must have a really long word in it.
                    // Break at the next candidate location, wherever that is.
                    $end = $iter->following($pos);
                }
                $out[] = trim(substr($string, $pos, $end - $pos));
                $pos = $end;
            }
        }

        return $out;
    }
}

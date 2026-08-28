<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Tests\unit;

use Huoxin\FilterRuleManager\Service\RuleEvaluator;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionClass;
use Symfony\Contracts\Translation\TranslatorInterface;

class RuleEvaluatorTest extends TestCase
{
    protected RuleEvaluator $evaluator;
    protected $translator;

    protected function setUp(): void
    {
        $container = new Container();

        // Mock translator
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnCallback(function ($key, $tokens) {
            if ($key === 'test.namespace.key') {
                return 'Translated: {{matched_word}}';
            }

            return $key;
        });

        // We use a singleton binding in container so resolve('translator') works if needed,
        // though it's technically a global helper we emulate it here.
        if (! function_exists('resolve')) {
            // Emulate Laravel's resolve() if missing in raw unit tests
            require_once __DIR__.'/setup.php'; // or just rely on Flarum's bootstrap if it runs
        }

        $container->instance('translator', $this->translator);

        $this->evaluator = new class ($container, new NullLogger(), $this->translator) extends RuleEvaluator
        {
            public function __construct($container, $logger, $translator)
            {
                parent::__construct($container, $logger, $translator);
            }

            // Override interpolate to use our injected translator instead of resolve()
            // to avoid global dependency issues in pure unit tests.
            public function interpolate(?string $template, array $tokens): string
            {
                if ($template === null || $template === '') {
                    return '';
                }

                if (preg_match('/^[a-zA-Z0-9\-_]+(?:\.[a-zA-Z0-9\-_]+)+$/', $template)) {
                    $trans = $this->translator->trans($template, $tokens);
                    if ($trans !== $template && $trans !== '') {
                        $template = is_array($trans) ? $trans[0] : $trans;
                    }
                }

                return parent::interpolate($template, $tokens);
            }
        };
    }

    #[Test]
    public function interpolate_escapes_html_in_tokens()
    {
        $result = $this->evaluator->interpolate('Found: {{matched_word}}', [
            'matched_word' => '<script>alert("xss")</script>'
        ]);

        $this->assertEquals('Found: &lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $result);
    }

    #[Test]
    public function interpolate_resolves_translation_keys()
    {
        // 'test.namespace.key' should be translated to 'Translated: {{matched_word}}'
        $result = $this->evaluator->interpolate('test.namespace.key', [
            'matched_word' => 'apple'
        ]);

        $this->assertEquals('Translated: apple', $result);
    }

    #[Test]
    public function interpolate_flattens_arrays_from_third_party_providers()
    {
        $result = $this->evaluator->interpolate('Blocked: {{matched_word}}', [
            'matched_word' => ['apple', ['banana', 'orange'], 'apple'] // nested array with duplicate
        ]);

        $this->assertEquals('Blocked: apple, banana, orange', $result);
    }

    #[Test]
    public function merge_results_deduplicates_comma_separated_strings()
    {
        $left = ['matched_word' => 'apple, banana'];
        $right = ['matched_word' => 'banana, orange', 'other_token' => 'cat'];

        $reflection = new ReflectionClass(RuleEvaluator::class);
        $method = $reflection->getMethod('mergeResults');

        $merged = $method->invoke($this->evaluator, [$left, $right]);

        $this->assertEquals('apple, banana, orange', $merged['matched_word']);
        $this->assertEquals('cat', $merged['other_token']);
    }

    #[Test]
    public function interpolate_renders_positive_conditional_blocks_when_token_is_present()
    {
        $template = 'Flagged: {{#matched_word}}Word: "{{matched_word}}"{{/matched_word}}';
        $result = $this->evaluator->interpolate($template, ['matched_word' => 'spam']);

        $this->assertEquals('Flagged: Word: "spam"', $result);
    }

    #[Test]
    public function interpolate_omits_positive_conditional_blocks_when_token_is_missing_or_empty()
    {
        $template = 'Flagged: {{#matched_word}}Word: "{{matched_word}}"{{/matched_word}}';
        $result1 = $this->evaluator->interpolate($template, []);
        $result2 = $this->evaluator->interpolate($template, ['matched_word' => '']);
        $result3 = $this->evaluator->interpolate($template, ['matched_word' => null]);

        $this->assertEquals('Flagged: ', $result1);
        $this->assertEquals('Flagged: ', $result2);
        $this->assertEquals('Flagged: ', $result3);
    }

    #[Test]
    public function interpolate_renders_inverted_conditional_blocks_when_token_is_missing()
    {
        $template = 'Status: {{^matched_word}}Clean{{/matched_word}}{{#matched_word}}Blocked: {{matched_word}}{{/matched_word}}';

        $cleanResult1 = $this->evaluator->interpolate($template, []);
        $this->assertEquals('Status: Clean', $cleanResult1);

        $cleanResult2 = $this->evaluator->interpolate($template, ['matched_word' => '']);
        $this->assertEquals('Status: Clean', $cleanResult2);
    }

    #[Test]
    public function interpolate_omits_inverted_conditional_blocks_when_token_is_present()
    {
        $template = 'Status: {{^matched_word}}Clean{{/matched_word}}{{#matched_word}}Blocked: {{matched_word}}{{/matched_word}}';

        $blockedResult = $this->evaluator->interpolate($template, ['matched_word' => 'bad']);
        $this->assertEquals('Status: Blocked: bad', $blockedResult);
    }

    #[Test]
    public function interpolate_handles_multiple_independent_conditional_blocks()
    {
        $template = 'Summary: {{#matched_word}}[Word: {{matched_word}}] {{/matched_word}}{{#matched_string}}[Regex: {{matched_string}}]{{/matched_string}}';

        // Only regex matched
        $regexOnly = $this->evaluator->interpolate($template, ['matched_string' => '555-1234']);
        $this->assertEquals('Summary: [Regex: 555-1234]', $regexOnly);

        // Only word matched
        $wordOnly = $this->evaluator->interpolate($template, ['matched_word' => 'prohibited']);
        $this->assertEquals('Summary: [Word: prohibited] ', $wordOnly);

        // Both matched
        $both = $this->evaluator->interpolate($template, [
            'matched_word' => 'prohibited',
            'matched_string' => '555-1234'
        ]);
        $this->assertEquals('Summary: [Word: prohibited] [Regex: 555-1234]', $both);
    }

    #[Test]
    public function interpolate_supports_arbitrary_nested_conditional_blocks()
    {
        $template = '{{#l1}}L1[{{#l2}}L2[{{#l3}}L3[{{#l4}}L4: {{token}}{{/l4}}]{{/l3}}]{{/l2}}]{{/l1}}';

        $result = $this->evaluator->interpolate($template, [
            'l1' => '1',
            'l2' => '1',
            'l3' => '1',
            'l4' => '1',
            'token' => 'nested_value'
        ]);

        $this->assertEquals('L1[L2[L3[L4: nested_value]]]', $result);
    }

    #[Test]
    public function interpolate_supports_mixed_positive_and_inverted_nesting()
    {
        $template = '{{#has_flag}}Flag: {{#matched_word}}Word({{matched_word}}){{/matched_word}}{{^matched_word}}Generic{{/matched_word}}{{/has_flag}}';

        // has_flag true, matched_word present
        $res1 = $this->evaluator->interpolate($template, [
            'has_flag' => '1',
            'matched_word' => 'spam'
        ]);
        $this->assertEquals('Flag: Word(spam)', $res1);

        // has_flag true, matched_word missing (triggers inverted inner block)
        $res2 = $this->evaluator->interpolate($template, [
            'has_flag' => '1'
        ]);
        $this->assertEquals('Flag: Generic', $res2);

        // has_flag false (entire block omitted)
        $res3 = $this->evaluator->interpolate($template, [
            'matched_word' => 'spam'
        ]);
        $this->assertEquals('', $res3);
    }

    #[Test]
    public function interpolate_safely_terminates_on_malformed_unclosed_tags()
    {
        $template = 'Prefix {{#unclosed_tag}} Content without closing tag';
        $result = $this->evaluator->interpolate($template, ['unclosed_tag' => 'val']);

        // Safely terminates and keeps unclosed string without hanging
        $this->assertEquals('Prefix {{#unclosed_tag}} Content without closing tag', $result);
    }

    #[Test]
    public function interpolate_safely_handles_mismatched_and_empty_tags()
    {
        $template = 'Mismatch: {{#tagA}}content{{/tagB}} Empty: {{#}}empty{{/}}';
        $result = $this->evaluator->interpolate($template, ['tagA' => '1', 'tagB' => '1']);

        $this->assertEquals('Mismatch: {{#tagA}}content{{/tagB}} Empty: {{#}}empty{{/}}', $result);
    }

    #[Test]
    public function interpolate_handles_same_tag_nested_blocks()
    {
        $template = '{{#tag}}outer {{#tag}}inner{{/tag}} outer_end{{/tag}}';
        $result = $this->evaluator->interpolate($template, ['tag' => '1']);

        $this->assertEquals('outer inner outer_end', $result);
    }

    #[Test]
    public function interpolate_handles_multiline_conditional_blocks()
    {
        $template = "Header\n{{#matched_word}}\nLine 1: {{matched_word}}\nLine 2\n{{/matched_word}}\nFooter";
        $result = $this->evaluator->interpolate($template, ['matched_word' => 'test']);

        $this->assertEquals("Header\n\nLine 1: test\nLine 2\n\nFooter", $result);
    }

    #[Test]
    public function interpolate_handles_repeated_blocks_for_same_token()
    {
        $template = 'First: {{#tag}}A({{tag}}){{/tag}} Second: {{#tag}}B({{tag}}){{/tag}}';
        $result = $this->evaluator->interpolate($template, ['tag' => 'val']);

        $this->assertEquals('First: A(val) Second: B(val)', $result);
    }

    #[Test]
    public function interpolate_handles_zero_string_and_numeric_tokens()
    {
        $template = 'Count: {{#count}}{{count}}{{/count}}';
        $result1 = $this->evaluator->interpolate($template, ['count' => '0']);
        $result2 = $this->evaluator->interpolate($template, ['count' => '10']);

        $this->assertEquals('Count: 0', $result1);
        $this->assertEquals('Count: 10', $result2);
    }

    #[Test]
    public function interpolate_handles_empty_and_null_templates()
    {
        $this->assertEquals('', $this->evaluator->interpolate('', ['matched_word' => 'test']));
        $this->assertEquals('', $this->evaluator->interpolate(null, ['matched_word' => 'test']));
    }

    #[Test]
    public function interpolate_preserves_plain_text_without_tokens()
    {
        $template = 'Simple message with no tokens.';
        $result = $this->evaluator->interpolate($template, ['matched_word' => 'unused']);

        $this->assertEquals('Simple message with no tokens.', $result);
    }
}

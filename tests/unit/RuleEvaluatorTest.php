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

use Flarum\Discussion\Discussion;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Huoxin\FilterRuleManager\Model\Ruleset;
use Huoxin\FilterRuleManager\Service\RuleEvaluator;
use Illuminate\Container\Container;
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

    /** @test */
    public function interpolate_escapes_html_in_tokens()
    {
        $result = $this->evaluator->interpolate('Found: {{matched_word}}', [
            'matched_word' => '<script>alert("xss")</script>'
        ]);

        $this->assertEquals('Found: &lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $result);
    }

    /** @test */
    public function interpolate_resolves_translation_keys()
    {
        // 'test.namespace.key' should be translated to 'Translated: {{matched_word}}'
        $result = $this->evaluator->interpolate('test.namespace.key', [
            'matched_word' => 'apple'
        ]);

        $this->assertEquals('Translated: apple', $result);
    }

    /** @test */
    public function interpolate_flattens_arrays_from_third_party_providers()
    {
        $result = $this->evaluator->interpolate('Blocked: {{matched_word}}', [
            'matched_word' => ['apple', ['banana', 'orange'], 'apple'] // nested array with duplicate
        ]);

        $this->assertEquals('Blocked: apple, banana, orange', $result);
    }

    /** @test */
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

    /** @test */
    public function interpolate_renders_positive_conditional_blocks_when_token_is_present()
    {
        $template = 'Flagged: {{#matched_word}}Word: "{{matched_word}}"{{/matched_word}}';
        $result = $this->evaluator->interpolate($template, ['matched_word' => 'spam']);

        $this->assertEquals('Flagged: Word: "spam"', $result);
    }

    /** @test */
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

    /** @test */
    public function interpolate_renders_inverted_conditional_blocks_when_token_is_missing()
    {
        $template = 'Status: {{^matched_word}}Clean{{/matched_word}}{{#matched_word}}Blocked: {{matched_word}}{{/matched_word}}';

        $cleanResult1 = $this->evaluator->interpolate($template, []);
        $this->assertEquals('Status: Clean', $cleanResult1);

        $cleanResult2 = $this->evaluator->interpolate($template, ['matched_word' => '']);
        $this->assertEquals('Status: Clean', $cleanResult2);
    }

    /** @test */
    public function interpolate_omits_inverted_conditional_blocks_when_token_is_present()
    {
        $template = 'Status: {{^matched_word}}Clean{{/matched_word}}{{#matched_word}}Blocked: {{matched_word}}{{/matched_word}}';

        $blockedResult = $this->evaluator->interpolate($template, ['matched_word' => 'bad']);
        $this->assertEquals('Status: Blocked: bad', $blockedResult);
    }

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
    public function interpolate_safely_terminates_on_malformed_unclosed_tags()
    {
        $template = 'Prefix {{#unclosed_tag}} Content without closing tag';
        $result = $this->evaluator->interpolate($template, ['unclosed_tag' => 'val']);

        // Safely terminates and keeps unclosed string without hanging
        $this->assertEquals('Prefix {{#unclosed_tag}} Content without closing tag', $result);
    }

    /** @test */
    public function interpolate_safely_handles_mismatched_and_empty_tags()
    {
        $template = 'Mismatch: {{#tagA}}content{{/tagB}} Empty: {{#}}empty{{/}}';
        $result = $this->evaluator->interpolate($template, ['tagA' => '1', 'tagB' => '1']);

        $this->assertEquals('Mismatch: {{#tagA}}content{{/tagB}} Empty: {{#}}empty{{/}}', $result);
    }

    /** @test */
    public function interpolate_handles_same_tag_nested_blocks()
    {
        $template = '{{#tag}}outer {{#tag}}inner{{/tag}} outer_end{{/tag}}';
        $result = $this->evaluator->interpolate($template, ['tag' => '1']);

        $this->assertEquals('outer inner outer_end', $result);
    }

    /** @test */
    public function interpolate_handles_multiline_conditional_blocks()
    {
        $template = "Header\n{{#matched_word}}\nLine 1: {{matched_word}}\nLine 2\n{{/matched_word}}\nFooter";
        $result = $this->evaluator->interpolate($template, ['matched_word' => 'test']);

        $this->assertEquals("Header\n\nLine 1: test\nLine 2\n\nFooter", $result);
    }

    /** @test */
    public function interpolate_handles_repeated_blocks_for_same_token()
    {
        $template = 'First: {{#tag}}A({{tag}}){{/tag}} Second: {{#tag}}B({{tag}}){{/tag}}';
        $result = $this->evaluator->interpolate($template, ['tag' => 'val']);

        $this->assertEquals('First: A(val) Second: B(val)', $result);
    }

    /** @test */
    public function interpolate_handles_zero_string_and_numeric_tokens()
    {
        $template = 'Count: {{#count}}{{count}}{{/count}}';
        $result1 = $this->evaluator->interpolate($template, ['count' => '0']);
        $result2 = $this->evaluator->interpolate($template, ['count' => '10']);

        $this->assertEquals('Count: 0', $result1);
        $this->assertEquals('Count: 10', $result2);
    }

    /** @test */
    public function interpolate_handles_empty_and_null_templates()
    {
        $this->assertEquals('', $this->evaluator->interpolate('', ['matched_word' => 'test']));
        $this->assertEquals('', $this->evaluator->interpolate(null, ['matched_word' => 'test']));
    }

    /** @test */
    public function interpolate_preserves_plain_text_without_tokens()
    {
        $template = 'Simple message with no tokens.';
        $result = $this->evaluator->interpolate($template, ['matched_word' => 'unused']);

        $this->assertEquals('Simple message with no tokens.', $result);
    }

    /** @test */
    public function merge_results_aggregates_universal_matched_text_token()
    {
        // Rule A (word match): apple, banana
        $wordResult = [
            'matched_word' => 'apple, banana',
            'matched_text' => 'apple, banana',
        ];

        // Rule B (regex match): 555-1234, banana
        $regexResult = [
            'matched_string' => '555-1234, banana',
            'matched_pattern' => '[0-9]{3}-[0-9]{4}, banana',
            'matched_text' => '555-1234, banana',
        ];

        $reflection = new ReflectionClass(RuleEvaluator::class);
        $method = $reflection->getMethod('mergeResults');

        $merged = $method->invoke($this->evaluator, [$wordResult, $regexResult]);

        // Specific tokens stay distinct
        $this->assertEquals('apple, banana', $merged['matched_word']);
        $this->assertEquals('555-1234, banana', $merged['matched_string']);

        // Universal token aggregates & deduplicates across both rules seamlessly
        $this->assertEquals('apple, banana, 555-1234', $merged['matched_text']);

        // Interpolation with universal token works with a single placeholder
        $message = $this->evaluator->interpolate('Violation detected: {{matched_text}}', $merged);
        $this->assertEquals('Violation detected: apple, banana, 555-1234', $message);
    }

    /** @test */
    public function interpolate_renders_conditional_with_universal_matched_text_and_html_escaping()
    {
        $template = 'Result: {{#matched_text}}<b>{{matched_text}}</b>{{/matched_text}}{{^matched_text}}<i>Clean</i>{{/matched_text}}';

        // When universal token is present with special characters
        $resPresent = $this->evaluator->interpolate($template, ['matched_text' => '<b>bold & dangerous</b>']);
        $this->assertEquals('Result: <b>&lt;b&gt;bold &amp; dangerous&lt;/b&gt;</b>', $resPresent);

        // When universal token is empty
        $resEmpty = $this->evaluator->interpolate($template, ['matched_text' => '']);
        $this->assertEquals('Result: <i>Clean</i>', $resEmpty);
    }

    /** @test */
    public function interpolate_recognizes_non_empty_arrays_in_conditional_blocks()
    {
        $template = '{{#items}}Found: {{items}}{{/items}}{{^items}}None{{/items}}';

        // Non-empty array from third party
        $res1 = $this->evaluator->interpolate($template, ['items' => ['first', 'second']]);
        $this->assertEquals('Found: first, second', $res1);

        // Empty array
        $res2 = $this->evaluator->interpolate($template, ['items' => []]);
        $this->assertEquals('None', $res2);
    }

    /** @test */
    public function scope_matches_respects_discussion_start_post_context()
    {
        $ruleset = new Ruleset();
        $ruleset->scope_type = 'global';
        $ruleset->post_context = 'discussion_start';

        $discussion = new Discussion();
        $discussion->exists = true;
        $discussion->first_post_id = 10;

        // Post 1: First post (number = 1) -> matches
        $firstPost = new CommentPost();
        $firstPost->number = 1;
        $firstPost->setRelation('discussion', $discussion);
        $this->assertTrue($this->evaluator->scopeMatches($ruleset, $discussion, $firstPost));

        // Post 2: Reply post (number = 2) -> rejected
        $replyPost = new CommentPost();
        $replyPost->number = 2;
        $replyPost->setRelation('discussion', $discussion);
        $this->assertFalse($this->evaluator->scopeMatches($ruleset, $discussion, $replyPost));

        // Post 3: Brand new discussion creation (number = null, discussion unpersisted) -> matches
        $newDisc = new Discussion();
        $newDisc->exists = false;
        $newPost = new CommentPost();
        $newPost->number = null;
        $newPost->setRelation('discussion', $newDisc);
        $this->assertTrue($this->evaluator->scopeMatches($ruleset, $newDisc, $newPost));
    }

    /** @test */
    public function scope_matches_respects_reply_post_context()
    {
        $ruleset = new Ruleset();
        $ruleset->scope_type = 'global';
        $ruleset->post_context = 'reply';

        $discussion = new Discussion();
        $discussion->exists = true;
        $discussion->first_post_id = 10;

        // First post (number = 1) -> rejected
        $firstPost = new CommentPost();
        $firstPost->number = 1;
        $firstPost->setRelation('discussion', $discussion);
        $this->assertFalse($this->evaluator->scopeMatches($ruleset, $discussion, $firstPost));

        // Reply post (number = 2) -> matches
        $replyPost = new CommentPost();
        $replyPost->number = 2;
        $replyPost->setRelation('discussion', $discussion);
        $this->assertTrue($this->evaluator->scopeMatches($ruleset, $discussion, $replyPost));
    }

    /** @test */
    public function scope_matches_defaults_to_all_posts()
    {
        $ruleset = new Ruleset();
        $ruleset->scope_type = 'global';
        $ruleset->post_context = 'all';

        $discussion = new Discussion();
        $firstPost = new CommentPost();
        $firstPost->number = 1;
        $replyPost = new CommentPost();
        $replyPost->number = 2;

        $this->assertTrue($this->evaluator->scopeMatches($ruleset, $discussion, $firstPost));
        $this->assertTrue($this->evaluator->scopeMatches($ruleset, $discussion, $replyPost));
    }
}

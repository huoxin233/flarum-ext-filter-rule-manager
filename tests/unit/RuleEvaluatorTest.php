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

        $this->evaluator = new class($container, new NullLogger(), $this->translator) extends RuleEvaluator {
            public function __construct($container, $logger, $translator)
            {
                parent::__construct($container, $logger, $translator);
            }

            // Override interpolate to use our injected translator instead of resolve()
            // to avoid global dependency issues in pure unit tests.
            public function interpolate(string $template, array $tokens): string
            {
                if (preg_match('/^[a-zA-Z0-9\-_]+(?:\.[a-zA-Z0-9\-_]+)+$/', $template)) {
                    $trans = $this->translator->trans($template, $tokens);
                    if ($trans !== $template && $trans !== '') {
                        $template = is_array($trans) ? $trans[0] : $trans;
                    }
                }

                return parent::interpolate($template, $tokens);
            }

            // Expose protected/private methods for testing
            public function testMergeResults(array $results): array
            {
                // We use reflection since it's private
                $reflection = new \ReflectionClass(RuleEvaluator::class);
                $method = $reflection->getMethod('mergeResults');
                $method->setAccessible(true);

                return $method->invoke($this, $results);
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

        $merged = $this->evaluator->testMergeResults([$left, $right]);

        $this->assertEquals('apple, banana, orange', $merged['matched_word']);
        $this->assertEquals('cat', $merged['other_token']);
    }
}

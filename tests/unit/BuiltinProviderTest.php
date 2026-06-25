<?php

namespace Huoxin\FilterRuleManager\Tests\unit;

use Huoxin\FilterRuleManager\Model\EvaluationContext;
use Huoxin\FilterRuleManager\Provider\BuiltinProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use PHPUnit\Framework\Attributes\Test;

class BuiltinProviderTest extends TestCase
{
    /** @var BuiltinProvider */
    protected $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(function ($id) {
            return $id;
        });

        $this->provider = new BuiltinProvider($translator);
    }

    private function evaluate(string $type, string $content, array $config): ?array
    {
        $context = new EvaluationContext($content);
        return $this->provider->evaluate($type, $config, $context);
    }

    #[Test]
    public function it_evaluates_contains_word_correctly()
    {
        $config = ['words' => ['apple', 'banana']];

        $this->assertNull($this->evaluate('contains_word', 'I like oranges.', $config));

        $result = $this->evaluate('contains_word', 'I like apples and bananas.', $config);
        $this->assertIsArray($result);
        $this->assertEquals('apple', $result['matched_word']);
    }

    #[Test]
    public function it_evaluates_regex_correctly()
    {
        // Notice we don't include delimiters, the provider should add them automatically
        $config = ['patterns' => ['[0-9]{3}-[0-9]{4}']];

        $this->assertNull($this->evaluate('regex', 'My number is hidden.', $config));

        $result = $this->evaluate('regex', 'Call me at 555-1234 please.', $config);
        $this->assertIsArray($result);
        $this->assertEquals('[0-9]{3}-[0-9]{4}', $result['matched_pattern']);
        $this->assertEquals('555-1234', $result['matched_string']);
    }

    #[Test]
    public function it_handles_explicit_regex_delimiters()
    {
        // Provider shouldn't add delimiters if they already start with /
        $config = ['patterns' => ['/b[a-z]+d/i']];

        $result = $this->evaluate('regex', 'That is BAD.', $config);
        $this->assertIsArray($result);
        $this->assertEquals('/b[a-z]+d/i', $result['matched_pattern']);
        $this->assertEquals('BAD', $result['matched_string']);
    }

    #[Test]
    public function it_handles_legacy_config_structures()
    {
        // Old structure used 'word' instead of 'words'
        $configContains = ['word' => 'legacy'];
        $result1 = $this->evaluate('contains_word', 'This is a legacy config test.', $configContains);
        $this->assertIsArray($result1);
        $this->assertEquals('legacy', $result1['matched_word']);

        // Old structure used 'pattern' instead of 'patterns'
        $configRegex = ['pattern' => '\d+'];
        $result2 = $this->evaluate('regex', 'I have 99 problems.', $configRegex);
        $this->assertIsArray($result2);
        $this->assertEquals('\d+', $result2['matched_pattern']);
        $this->assertEquals('99', $result2['matched_string']);
    }

    #[Test]
    public function scan_all_aggregates_multiple_matches()
    {
        $config = [
            'words' => ['apple', 'banana', 'cherry'],
            'scan_all' => true
        ];

        $result = $this->evaluate('contains_word', 'I have an apple and a cherry pie.', $config);

        $this->assertIsArray($result);
        // It matches in the order of the configuration array, not the string order
        $this->assertEquals('apple, cherry', $result['matched_word']);
    }

    #[Test]
    public function scan_all_aggregates_multiple_regex_matches()
    {
        $config = [
            'patterns' => ['foo', 'bar'],
            'scan_all' => true
        ];

        $result = $this->evaluate('regex', 'foo bar baz', $config);

        $this->assertIsArray($result);
        $this->assertEquals('foo, bar', $result['matched_pattern']);
        $this->assertEquals('foo, bar', $result['matched_string']);
    }

    #[Test]
    public function it_evaluates_word_count_correctly()
    {
        $config = ['min' => 2, 'max' => 5];

        // Should return null (not block) since 3 words is between 2 and 5
        $this->assertNull($this->evaluate('word_count', 'One two three.', $config));

        // Less than min
        $resultMin = $this->evaluate('word_count', 'One.', $config);
        $this->assertIsArray($resultMin);
        $this->assertEquals('1', $resultMin['word_count']);

        // Greater than max
        $resultMax = $this->evaluate('word_count', 'One two three four five six.', $config);
        $this->assertIsArray($resultMax);
        $this->assertEquals('6', $resultMax['word_count']);
    }

    #[Test]
    public function it_evaluates_cjk_character_count_correctly()
    {
        $config = ['max' => 4];

        // "你好" is 2 chars + "world" is 1 word = 3
        $this->assertNull($this->evaluate('word_count', '你好 world', $config));

        // 5 CJK chars > 4
        $resultMax = $this->evaluate('word_count', '一二三四五', $config);
        $this->assertIsArray($resultMax);
        $this->assertEquals('5', $resultMax['word_count']);
    }

    #[Test]
    public function it_strips_mentions_if_configured()
    {
        $config = ['min' => 2, 'exclude_mentions' => true];

        // "Hello @"User Name"#123" -> without mention it's just "Hello" (1 word) < 2
        $result = $this->evaluate('word_count', 'Hello @"User Name"#123', $config);
        $this->assertIsArray($result);
        $this->assertEquals('1', $result['word_count']);

        // With mentions NOT excluded
        $config2 = ['min' => 2];
        $this->assertNull($this->evaluate('word_count', 'Hello @"User Name"#123', $config2));
    }
}

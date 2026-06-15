<?php

namespace Huoxin\FilterRuleManager\Tests\integration;

use Huoxin\FilterRuleManager\Provider\BuiltinProvider;
use PHPUnit\Framework\TestCase;

class BuiltinProviderTest extends TestCase
{
    /** @var BuiltinProvider */
    protected $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new BuiltinProvider();
    }

    /**
     * @test
     */
    public function it_evaluates_contains_word_correctly()
    {
        $config = ['words' => ['apple', 'banana']];
        
        $this->assertNull($this->provider->evaluate('contains_word', 'I like oranges.', $config));
        
        $result = $this->provider->evaluate('contains_word', 'I like apples and bananas.', $config);
        $this->assertIsArray($result);
        $this->assertEquals('apple', $result['matched_word']);
    }

    /**
     * @test
     */
    public function it_evaluates_regex_correctly()
    {
        // Notice we don't include delimiters, the provider should add them automatically
        $config = ['patterns' => ['[0-9]{3}-[0-9]{4}']];
        
        $this->assertNull($this->provider->evaluate('regex', 'My number is hidden.', $config));
        
        $result = $this->provider->evaluate('regex', 'Call me at 555-1234 please.', $config);
        $this->assertIsArray($result);
        $this->assertEquals('[0-9]{3}-[0-9]{4}', $result['matched_pattern']);
        $this->assertEquals('555-1234', $result['matched_string']);
    }

    /**
     * @test
     */
    public function it_handles_explicit_regex_delimiters()
    {
        // Provider shouldn't add delimiters if they already start with /
        $config = ['patterns' => ['/b[a-z]+d/i']];
        
        $result = $this->provider->evaluate('regex', 'That is BAD.', $config);
        $this->assertIsArray($result);
        $this->assertEquals('/b[a-z]+d/i', $result['matched_pattern']);
        $this->assertEquals('BAD', $result['matched_string']);
    }

    /**
     * @test
     */
    public function it_handles_legacy_config_structures()
    {
        // Old structure used 'word' instead of 'words'
        $configContains = ['word' => 'legacy'];
        $result1 = $this->provider->evaluate('contains_word', 'This is a legacy config test.', $configContains);
        $this->assertIsArray($result1);
        $this->assertEquals('legacy', $result1['matched_word']);

        // Old structure used 'pattern' instead of 'patterns'
        $configRegex = ['pattern' => '\d+'];
        $result2 = $this->provider->evaluate('regex', 'I have 99 problems.', $configRegex);
        $this->assertIsArray($result2);
        $this->assertEquals('\d+', $result2['matched_pattern']);
        $this->assertEquals('99', $result2['matched_string']);
    }

    /**
     * @test
     */
    public function scan_all_aggregates_multiple_matches()
    {
        $config = [
            'words' => ['apple', 'banana', 'cherry'],
            'scan_all' => true
        ];
        
        $result = $this->provider->evaluate('contains_word', 'I have an apple and a cherry pie.', $config);
        
        $this->assertIsArray($result);
        // It matches in the order of the configuration array, not the string order
        $this->assertEquals('apple, cherry', $result['matched_word']);
    }

    /**
     * @test
     */
    public function scan_all_aggregates_multiple_regex_matches()
    {
        $config = [
            'patterns' => ['foo', 'bar'],
            'scan_all' => true
        ];
        
        $result = $this->provider->evaluate('regex', 'foo bar baz', $config);
        
        $this->assertIsArray($result);
        $this->assertEquals('foo, bar', $result['matched_pattern']);
        $this->assertEquals('foo, bar', $result['matched_string']);
    }
}

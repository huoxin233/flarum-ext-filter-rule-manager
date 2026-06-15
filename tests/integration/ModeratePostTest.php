<?php

namespace Huoxin\FilterRuleManager\Tests\integration;

use Flarum\Testing\integration\TestCase;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class ModeratePostTest extends FilterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareDatabase([
            'users' => [
                ['id' => 8, 'username' => 'user8', 'email' => 'user8@machine.local', 'is_email_confirmed' => 1],
                ['id' => 9, 'username' => 'user9', 'email' => 'user9@machine.local', 'is_email_confirmed' => 1],
            ],
            'posts' => [
                // Existing post to test edits
                ['id' => 2, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Clean post</p></t>', 'is_approved' => 1, 'number' => 2, 'created_at' => Carbon::now()->toDateTimeString()],
            ],
            'filter_rulesets' => [
                [
                    'id' => 1,
                    'name' => 'Both Enabled',
                    'priority' => 0,
                    'compiled_ast' => json_encode([
                        'type' => 'logical',
                        'operator' => 'OR',
                        'left' => [
                            'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['word_both'], 'scan_all' => true]
                        ],
                        'right' => [
                            'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['apple'], 'scan_all' => true]
                        ]
                    ]),
                    'effect_type' => 'warning',
                    'display_mode' => 'banner',
                    'flag_message' => 'Matched custom: {{matched_word}}',
                    'evaluate_all_rules' => 1,
                    'scope_type' => 'global',
                    'is_active' => 1,
                    'auto_flag' => 1,
                    'require_approval' => 1,
                    'evasion_active' => 1,
                    'evasion_threshold' => 1,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ],
                [
                    'id' => 2,
                    'name' => 'Flag Only',
                    'priority' => 1,
                    'compiled_ast' => json_encode([
                        'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['word_flag']]
                    ]),
                    'effect_type' => 'warning',
                    'display_mode' => 'banner',
                    'scope_type' => 'global',
                    'is_active' => 1,
                    'auto_flag' => 1,
                    'require_approval' => 0,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ],
                [
                    'id' => 3,
                    'name' => 'Approval Only',
                    'priority' => 2,
                    'compiled_ast' => json_encode([
                        'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['words' => ['word_approval']]
                    ]),
                    'effect_type' => 'warning',
                    'display_mode' => 'banner',
                    'scope_type' => 'global',
                    'is_active' => 1,
                    'auto_flag' => 0,
                    'require_approval' => 1,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ],
                [
                    'id' => 4,
                    'name' => 'Inactive Evasion',
                    'priority' => 3,
                    'compiled_ast' => json_encode([
                        'type' => 'rule', 'provider' => 'builtin', 'ruleType' => 'contains_word', 'operator' => 'EQUALS', 'value' => ['inactive_word']
                    ]),
                    'effect_type' => 'block',
                    'display_mode' => 'banner',
                    'scope_type' => 'global',
                    'is_active' => 0, // INACTIVE!
                    'auto_flag' => 1,
                    'require_approval' => 1,
                    'evasion_active' => 1, // BUT EVASION ACTIVE
                    'evasion_timeout' => 15,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ]
            ],
            // For evasion tests
            'filter_rule_block_logs' => [
                [
                    'id' => 1,
                    'user_id' => 7,
                    'ruleset_id' => 1,
                    'created_at' => Carbon::now()->subMinutes(2)->toDateTimeString(), // 2 mins ago (within default 5 min window)
                ],
                [
                    'id' => 2,
                    'user_id' => 8,
                    'ruleset_id' => 1,
                    'created_at' => Carbon::now()->subMinutes(10)->toDateTimeString(), // 10 mins ago (outside default 5 min window)
                ],
                [
                    'id' => 3,
                    'user_id' => 9,
                    'ruleset_id' => 4, // Inactive ruleset
                    'created_at' => Carbon::now()->subMinutes(2)->toDateTimeString(), // 2 mins ago
                ]
            ]
        ]);
    }

    /**
     * @test
     */
    public function posting_clean_content_publishes_normally()
    {
        $response = $this->submitReply('This is a completely clean post.', 8);

        $this->assertEquals(201, $response->getStatusCode());

        $postId = Arr::get(json_decode($response->getBody()->getContents(), true), 'data.id');
        $post = $this->database()->table('posts')->where('id', $postId)->first();

        $this->assertEquals(1, $post->is_approved, 'Clean post should be approved');

        $flag = $this->database()->table('flags')->where('post_id', $postId)->first();
        $this->assertNull($flag, 'Clean post should not have any flags');
    }

    /**
     * @test
     */
    public function posting_triggers_both_approval_and_flag()
    {
        // Notice we include both words from the OR ruleset 1
        $response = $this->submitReply('This contains word_both and apple.', 3);

        $this->assertEquals(201, $response->getStatusCode());

        $postId = Arr::get(json_decode($response->getBody()->getContents(), true), 'data.id');
        $post = $this->database()->table('posts')->where('id', $postId)->first();

        // Assert is_approved is 0
        $this->assertEquals(0, $post->is_approved, 'Post should be unapproved (0)');

        // Assert flag is created and type is 'autoMod'
        $flag = $this->database()->table('flags')->where('post_id', $postId)->where('type', 'autoMod')->first();
        $this->assertNotNull($flag, 'autoMod flag should be created');

        // Assert the custom aggregated flag message!
        $this->assertEquals('Matched custom: word_both, apple', $flag->reason_detail);
    }

    /**
     * @test
     */
    public function posting_triggers_flag_only()
    {
        $response = $this->submitReply('This contains word_flag which only flags.', 4);

        $this->assertEquals(201, $response->getStatusCode());

        $postId = Arr::get(json_decode($response->getBody()->getContents(), true), 'data.id');
        $post = $this->database()->table('posts')->where('id', $postId)->first();

        // Post should NOT be held for approval
        $this->assertEquals(1, $post->is_approved, 'Post should remain approved');

        // Flag should be created as 'autoMod'
        $flag = $this->database()->table('flags')->where('post_id', $postId)->where('type', 'autoMod')->first();
        $this->assertNotNull($flag, 'autoMod flag should be created');
    }

    /**
     * @test
     */
    public function posting_triggers_approval_only_without_flag()
    {
        $response = $this->submitReply('This contains word_approval which only approves.', 5);

        $this->assertEquals(201, $response->getStatusCode());

        $postId = Arr::get(json_decode($response->getBody()->getContents(), true), 'data.id');
        $post = $this->database()->table('posts')->where('id', $postId)->first();

        // Assert is_approved is 0
        $this->assertEquals(0, $post->is_approved, 'Post should be unapproved (0)');

        // Assert NO flag is created
        $flag = $this->database()->table('flags')->where('post_id', $postId)->first();
        $this->assertNull($flag, 'No flag should be created for approval-only ruleset');
    }

    /**
     * @test
     */
    public function editing_clean_post_to_restricted_word_triggers_moderation()
    {
        $response = $this->send(
            $this->request('PATCH', '/api/posts/2', [
                'authenticatedAs' => 2, // It's okay to reuse 2 here, it's a PATCH
                'json' => [
                    'data' => [
                        'attributes' => [
                            'content' => 'I edited this to include word_both.'
                        ]
                    ]
                ]
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $post = $this->database()->table('posts')->where('id', 2)->first();
        $this->assertEquals(0, $post->is_approved, 'Edited post should become unapproved');

        $flag = $this->database()->table('flags')->where('post_id', 2)->where('type', 'autoMod')->first();
        $this->assertNotNull($flag, 'Flag should be created for edited post');
    }

    /**
     * @test
     */
    public function starting_discussion_with_restricted_word_hides_discussion()
    {
        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 6,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'title' => 'New Discussion',
                            'content' => 'This contains word_both.'
                        ],
                        'relationships' => [
                            'tags' => ['data' => [['type' => 'tags', 'id' => '1']]]
                        ]
                    ]
                ]
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $discussionId = Arr::get($body, 'data.id');
        $postId = Arr::get($body, 'included.0.id');

        $discussion = $this->database()->table('discussions')->where('id', $discussionId)->first();
        $post = $this->database()->table('posts')->where('id', $postId)->first();

        $this->assertEquals(0, $post->is_approved, 'First post should be unapproved');
        $this->assertEquals(0, $discussion->is_approved, 'Discussion should be unapproved');
    }

    /**
     * @test
     */
    public function evasion_detection_forces_flag_and_approval_despite_clean_content()
    {
        // Notice we are sending completely CLEAN content, but the user is in the filter_rule_block_logs (within 15 mins)
        $response = $this->submitReply('I promise this is a completely clean and nice post without any bad words.', 7);

        $this->assertEquals(201, $response->getStatusCode());

        $postId = Arr::get(json_decode($response->getBody()->getContents(), true), 'data.id');
        $post = $this->database()->table('posts')->where('id', $postId)->first();

        // Evasion should force the post into approval queue
        $this->assertEquals(0, $post->is_approved, 'Post should be unapproved due to evasion');

        // Evasion should force the creation of an autoMod flag
        $flag = $this->database()->table('flags')->where('post_id', $postId)->where('type', 'autoMod')->first();
        $this->assertNotNull($flag, 'autoMod flag should be created due to evasion');

        $this->assertStringContainsString('evasion', $flag->reason_detail, 'Flag reason should mention filter evasion');
    }

    /**
     * @test
     */
    public function evasion_detection_ignores_expired_timeout()
    {
        // User 8 was blocked 10 mins ago, but default global timeout is 5 mins.
        $response = $this->submitReply('I promise this is a clean post.', 8);

        $this->assertEquals(201, $response->getStatusCode());

        $postId = Arr::get(json_decode($response->getBody()->getContents(), true), 'data.id');
        $post = $this->database()->table('posts')->where('id', $postId)->first();

        $this->assertEquals(1, $post->is_approved, 'Post should be approved because evasion timeout expired');
    }

    /**
     * @test
     */
    public function evasion_detection_ignores_inactive_rulesets()
    {
        // User 9 was blocked 2 mins ago by Ruleset 4. Ruleset 4 is inactive, so evasion should not trigger.
        $response = $this->submitReply('I promise this is a clean post.', 9);

        $this->assertEquals(201, $response->getStatusCode());

        $postId = Arr::get(json_decode($response->getBody()->getContents(), true), 'data.id');
        $post = $this->database()->table('posts')->where('id', $postId)->first();

        $this->assertEquals(1, $post->is_approved, 'Post should be approved because evasion ruleset is inactive');
    }
}

<?php

namespace Huoxin\FilterRuleManager\Tests\integration;

use Flarum\Testing\integration\TestCase;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class ModeratePostTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-flags');
        $this->extension('flarum-approval');
        $this->extension('huoxin-filter-rule-manager');

        $this->prepareDatabase([
            'users' => [
                ['id' => 1, 'username' => 'admin', 'email' => 'admin@machine.local', 'is_email_confirmed' => 1],
                ['id' => 2, 'username' => 'normalUser1', 'email' => 'normal1@machine.local', 'is_email_confirmed' => 1],
                ['id' => 3, 'username' => 'normalUser2', 'email' => 'normal2@machine.local', 'is_email_confirmed' => 1],
                ['id' => 4, 'username' => 'normalUser3', 'email' => 'normal3@machine.local', 'is_email_confirmed' => 1],
                ['id' => 5, 'username' => 'normalUser4', 'email' => 'normal4@machine.local', 'is_email_confirmed' => 1],
                ['id' => 6, 'username' => 'normalUser5', 'email' => 'normal5@machine.local', 'is_email_confirmed' => 1],
                ['id' => 7, 'username' => 'normalUser6', 'email' => 'normal6@machine.local', 'is_email_confirmed' => 1],
                ['id' => 8, 'username' => 'normalUser7', 'email' => 'normal7@machine.local', 'is_email_confirmed' => 1],
            ],
            'discussions' => [
                ['id' => 1, 'title' => 'Test Discussion', 'created_at' => Carbon::now()->toDateTimeString(), 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'is_approved' => 1],
            ],
            'posts' => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>First post</p></t>', 'is_approved' => 1, 'number' => 1, 'created_at' => Carbon::now()->toDateTimeString()],
                // Existing post to test edits
                ['id' => 2, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Clean post</p></t>', 'is_approved' => 1, 'number' => 2, 'created_at' => Carbon::now()->toDateTimeString()],
            ],
            'filter_rulesets' => [
                [
                    'id' => 1,
                    'name' => 'Both Enabled',
                    'priority' => 0,
                    'rule_operator' => 'AND',
                    'effect_type' => 'warning',
                    'display_mode' => 'banner',
                    'scope_type' => 'global',
                    'is_active' => 1,
                    'auto_flag' => 1,
                    'require_approval' => 1,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ],
                [
                    'id' => 2,
                    'name' => 'Flag Only',
                    'priority' => 1,
                    'rule_operator' => 'AND',
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
                    'rule_operator' => 'AND',
                    'effect_type' => 'warning',
                    'display_mode' => 'banner',
                    'scope_type' => 'global',
                    'is_active' => 1,
                    'auto_flag' => 0,
                    'require_approval' => 1,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ]
            ],
            'filter_rules' => [
                [
                    'id' => 1,
                    'ruleset_id' => 1,
                    'provider' => 'builtin',
                    'type' => 'contains_word',
                    'config' => json_encode(['words' => ['word_both']]),
                    'sort_order' => 0
                ],
                [
                    'id' => 2,
                    'ruleset_id' => 2,
                    'provider' => 'builtin',
                    'type' => 'contains_word',
                    'config' => json_encode(['words' => ['word_flag']]),
                    'sort_order' => 0
                ],
                [
                    'id' => 3,
                    'ruleset_id' => 3,
                    'provider' => 'builtin',
                    'type' => 'contains_word',
                    'config' => json_encode(['words' => ['word_approval']]),
                    'sort_order' => 0
                ]
            ],
            // For evasion tests
            'filter_rule_block_logs' => [
                [
                    'id' => 1,
                    'user_id' => 7,
                    'ruleset_id' => 1,
                    'created_at' => Carbon::now()->subMinutes(5)->toDateTimeString(), // 5 mins ago (within 15 min window)
                ]
            ]
        ]);
    }

    protected function submitReply(string $content, int $userId = 2)
    {
        return $this->send(
            $this->request('POST', '/api/posts', [
                'authenticatedAs' => $userId,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'content' => $content
                        ],
                        'relationships' => [
                            'discussion' => ['data' => ['type' => 'discussions', 'id' => '1']]
                        ]
                    ]
                ]
            ])
        );
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
        $response = $this->submitReply('This contains word_both which triggers everything.', 3);

        $this->assertEquals(201, $response->getStatusCode());

        $postId = Arr::get(json_decode($response->getBody()->getContents(), true), 'data.id');
        $post = $this->database()->table('posts')->where('id', $postId)->first();

        // Assert is_approved is 0 (This will likely fail due to Flarum Core race conditions, proving the bug)
        $this->assertEquals(0, $post->is_approved, 'Post should be unapproved (0)');

        // Assert flag is created and type is 'approval'
        $flag = $this->database()->table('flags')->where('post_id', $postId)->where('type', 'approval')->first();
        $this->assertNotNull($flag, 'Approval flag should be created');
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

        $flag = $this->database()->table('flags')->where('post_id', 2)->where('type', 'approval')->first();
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

        // Evasion should force the creation of an approval flag
        $flag = $this->database()->table('flags')->where('post_id', $postId)->where('type', 'approval')->first();
        $this->assertNotNull($flag, 'Approval flag should be created due to evasion');

        $this->assertStringContainsString('evasion', $flag->reason_detail, 'Flag reason should mention filter evasion');
    }
}

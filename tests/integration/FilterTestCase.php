<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Tests\integration;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;

abstract class FilterTestCase extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Load common extensions for all Filter Rule Manager tests
        $this->extension('flarum-flags');
        $this->extension('flarum-approval');
        $this->extension('flarum-tags');
        $this->extension('fof-byobu');
        $this->extension('huoxin-filter-rule-manager');

        // 2. Prepare common database state (Users)
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
            'tags' => [
                ['id' => 1, 'name' => 'General', 'slug' => 'general', 'position' => 0],
                ['id' => 2, 'name' => 'Gaming', 'slug' => 'gaming', 'position' => 1],
            ],
            'discussions' => [
                ['id' => 1, 'title' => 'Test Discussion', 'created_at' => Carbon::now()->toDateTimeString(), 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'is_approved' => 1],
            ],
            'posts' => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>First post</p></t>', 'is_approved' => 1, 'number' => 1, 'created_at' => Carbon::now()->toDateTimeString()],
            ],
        ]);
    }

    /**
     * Helper to submit a reply and return the response.
     */
    protected function submitReply(string $content, int $userId = 2, int $discussionId = 1)
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
                            'discussion' => ['data' => ['type' => 'discussions', 'id' => (string) $discussionId]]
                        ]
                    ]
                ]
            ])
        );
    }
}

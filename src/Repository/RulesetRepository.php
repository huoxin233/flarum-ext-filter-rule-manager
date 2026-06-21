<?php

namespace Huoxin\FilterRuleManager\Repository;

use Huoxin\FilterRuleManager\Model\Ruleset;
use Illuminate\Database\ConnectionInterface;

class RulesetRepository
{
    /**
     * @var \Illuminate\Database\Eloquent\Collection|null
     */
    protected $activeRulesets;

    public function __construct(protected ConnectionInterface $db)
    {
    }

    public function getActiveRulesets()
    {
        if ($this->activeRulesets === null) {
            $this->activeRulesets = Ruleset::active()->ordered()->get();
        }

        return $this->activeRulesets;
    }

    public function flush(): void
    {
        $this->activeRulesets = null;
    }

    public function reorder(array $values): void
    {
        $this->db->transaction(function () use ($values) {
            Ruleset::upsert($values, ['id'], ['priority']);
        });
    }
}

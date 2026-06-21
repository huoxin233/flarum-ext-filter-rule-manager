<?php

namespace Huoxin\FilterRuleManager\Repository;

use Huoxin\FilterRuleManager\Model\Ruleset;

class RulesetRepository
{
    /**
     * @var \Illuminate\Database\Eloquent\Collection|null
     */
    protected $activeRulesets;

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
}

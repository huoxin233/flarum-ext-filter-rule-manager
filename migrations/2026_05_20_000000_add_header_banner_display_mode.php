<?php

use Illuminate\Database\Schema\Builder;

/**
 * Rename the historical `banner` display_mode (which used to render inside
 * the ComposerBody header) to `header_banner`, freeing the `banner` name for
 * the new container-level banner that sits above the whole composer.
 *
 * Defensive: skips silently when the rulesets table isn't there yet —
 * happens when the extension is being re-installed and the create migration
 * hasn't run again.
 */
return [
    'up' => function (Builder $schema) {
        if (!$schema->hasTable('filter_rulesets')) {
            return;
        }

        // For installs that were created with the original ENUM column we
        // also need to widen the column so it accepts `header_banner`. This
        // is a no-op on installs already using VARCHAR.
        try {
            $schema->getConnection()->statement(
                "ALTER TABLE filter_rulesets MODIFY display_mode VARCHAR(32) NOT NULL DEFAULT 'banner'"
            );
        } catch (\Throwable $e) {
            // Non-MySQL drivers may not support MODIFY; ignore and continue.
        }

        $schema->getConnection()
            ->table('filter_rulesets')
            ->where('display_mode', 'banner')
            ->update(['display_mode' => 'header_banner']);
    },
    'down' => function (Builder $schema) {
        if (!$schema->hasTable('filter_rulesets')) {
            return;
        }

        $schema->getConnection()
            ->table('filter_rulesets')
            ->where('display_mode', 'header_banner')
            ->update(['display_mode' => 'banner']);
    },
];

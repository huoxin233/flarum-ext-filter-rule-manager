# Extending Filter Rule Manager

Filter Rule Manager uses an Abstract Syntax Tree (AST) engine to evaluate forum posts. It is built to be highly extensible. As a developer, you can inject **Rule Providers** into the engine.

A Rule Provider evaluates a specific type of logic (e.g., calling an external AI API, performing complex database queries, or scanning images). When you build a Rule Provider, it immediately becomes available in the visual Ruleset Editor for forum administrators to configure.

This guide provides a comprehensive blueprint for building an extension that adds a new `is_toxic` rule to Filter Rule Manager.

---

## 1. Project Architecture

If you are creating a new Flarum extension (e.g., `yourname/flarum-ext-toxicity-filter`), your project structure should minimally look something like this:

```text
flarum-ext-toxicity-filter/
├── composer.json
├── extend.php
├── src/
│   └── Provider/
│       └── ToxicityProvider.php
└── js/
    ├── package.json
    ├── webpack.config.js
    ├── tsconfig.json
    └── src/
        └── admin/
            ├── index.tsx
            ├── providers/
            │   └── ToxicityProvider.ts
            └── components/
                └── ToxicityConfigComponent.tsx
```

### Critical Dependency Requirement

In your `composer.json`, you **must** require Filter Rule Manager to ensure Flarum boots the extensions in the correct order:

```json
"require": {
    "flarum/core": "^1.8.0",
    "huoxin/filter-rule-manager": "*"
}
```

---

## 2. Backend Implementation (PHP)

The backend is responsible for actually evaluating the post content against the rule.

### `src/Provider/ToxicityProvider.php`

Create a class implementing `Huoxin\FilterRuleManager\Provider\RuleProviderInterface`.

```php
<?php

namespace YourNamespace\ToxicityFilter\Provider;

use Exception;
use Flarum\Foundation\ValidationException;
use Huoxin\FilterRuleManager\Model\EvaluationContext;
use Huoxin\FilterRuleManager\Provider\RuleProviderInterface;
use Huoxin\FilterRuleManager\Provider\ValidatesConfigInterface;

class ToxicityProvider implements RuleProviderInterface, ValidatesConfigInterface
{
    /**
     * The rule type strings this provider handles on the backend.
     * Return an empty array if this provider only has frontend checks.
     *
     * @return string[]
     */
    public function getSupportedBackendTypes(): array
    {
        return ['is_toxic'];
    }

    /**
     * Human-readable labels for each supported type (shown in admin rule builder).
     *
     * @return array<string, string>  ['type_string' => 'Human Label']
     */
    public function getBackendTypeLabels(): array
    {
        return [
            'is_toxic' => 'AI Toxicity Check',
        ];
    }

    /**
     * Evaluate a single rule against the evaluation context.
     *
     * @param string $type    The rule type string (one of getSupportedBackendTypes())
     * @param array  $config  Rule config JSON decoded to array
     * @param EvaluationContext $context The context object containing content, actor, and post
     *
     * @return array|null  null = not triggered; array (may be empty) = triggered with tokens
     */
    public function evaluate(string $type, array $config, EvaluationContext $context): ?array
    {
        if ($type === 'is_toxic') {
            // Retrieve the threshold configured by the admin (defaults to 0.8)
            $threshold = $config['threshold'] ?? 0.8;

            // NOTE: Be mindful of performance! This runs synchronously during Post Saving.
            // If the API call fails, catch the exception and return null so users aren't blocked.
            try {
                $score = $this->callExternalToxicityApi($context->content);
            } catch (Exception $e) {
                return null;
            }

            // If the content is toxic, return an array of data strings.
            // These strings are dynamically injected into the `{{matched_word}}`
            // placeholder in the admin's Flag or Block message!
            if ($score >= $threshold) {
                return ["Score: {$score} (Threshold: {$threshold})"];
            }
        }

        // Return null if the post passes the check
        return null;
    }

    private function callExternalToxicityApi(string $content): float
    {
        // ... external API logic ...
        return 0.9;
    }

    /**
     * Tokens this provider exposes per rule type, for use in ruleset messages.
     *
     * @param string $type The rule type
     * @return array  A list of associative arrays with 'name' and 'description' keys
     */
    public function getProvidedTokens(string $type): array
    {
        if ($type === 'is_toxic') {
            return [
                ['name' => 'matched_word', 'description' => 'Outputs the actual toxicity score returned by the API.'],
            ];
        }

        return [];
    }

    /**
     * Validate the given config for the specified rule type.
     * Must throw Flarum\Foundation\ValidationException if the config is malformed.
     *
     * @param string $type
     * @param array $config
     * @return void
     * @throws ValidationException
     */
    public function validateConfig(string $type, array $config): void
    {
        if ($type === 'is_toxic') {
            $threshold = $config['threshold'] ?? 0.8;

            if ($threshold < 0 || $threshold > 1) {
                throw new ValidationException([
                    'expression' => 'Toxicity threshold must be between 0.0 and 1.0.',
                ]);
            }
        }
    }
}
```

### `extend.php`

Register the provider into the Filter Rule Manager ecosystem.

```php
<?php

use Flarum\Extend;
use Huoxin\FilterRuleManager\Extend\FilterRuleProvider;
use YourNamespace\ToxicityFilter\Provider\ToxicityProvider;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    // Register our custom rule provider!
    (new FilterRuleProvider())
        ->registerProvider('toxicity', ToxicityProvider::class),
];
```

---

## 3. Frontend Implementation (TypeScript / Mithril)

The frontend is responsible for showing your rule in the Visual Ruleset Editor, and providing a UI for the administrator to configure it.

### `js/src/admin/components/ToxicityConfigComponent.tsx`

Create a standard Flarum Mithril component. This component receives the `vnode.attrs.config` object (which is eventually sent to PHP as `$config`) and a `vnode.attrs.onchange` callback to save changes.

```typescript
import Component from 'flarum/common/Component';

export default class ToxicityConfigComponent extends Component {
  view(vnode: any) {
    // Access the current configuration or set default values
    const config = vnode.attrs.config || { threshold: 0.8 };
    const onchange = vnode.attrs.onchange;

    return (
      <div className="ToxicityRule-Config">
        <label>Minimum Toxicity Threshold (0.0 to 1.0)</label>
        <input
          type="number"
          className="FormControl"
          value={config.threshold}
          onchange={(e: any) => {
            // Propagate the updated config back to the visual AST editor
            const newConfig = { ...config, threshold: parseFloat(e.target.value) };
            onchange(newConfig);
          }}
          step="0.1"
          min="0"
          max="1"
        />
      </div>
    );
  }
}
```

### `js/src/admin/providers/ToxicityProvider.ts`

This class defines the frontend blueprint for your provider. It dictates what the rule is called, what configuration component it uses, and what tokens it exposes.

```typescript
import app from "flarum/admin/app";
import ToxicityConfigComponent from "../components/ToxicityConfigComponent";

export default class ToxicityProvider {
  /**
   * The rule types this provider handles on the frontend.
   */
  getSupportedTypes(): string[] {
    return ["is_toxic"];
  }

  /**
   * Human-readable labels shown in the dropdown menu of the Visual Editor.
   */
  getTypeLabels(): Record<string, string> {
    return {
      is_toxic: app.translator.trans("your-ext.admin.type_is_toxic") as string,
    };
  }

  /**
   * Maps a rule type to its respective configuration UI component.
   * If you return null, it falls back to a generic JSON text area.
   */
  getConfigComponent(type: string): any {
    if (type === "is_toxic") return ToxicityConfigComponent;
    return null;
  }

  /**
   * Documents the tokens this rule injects into the message interpolator.
   * This populates the "Available Variables" hint panel in the UI.
   */
  getProvidedTokens(type: string): { name: string; description: string }[] {
    if (type === "is_toxic") {
      return [
        {
          name: "matched_word",
          description: "Outputs the actual toxicity score returned by the API.",
        },
      ];
    }
    return [];
  }
}
```

### `js/src/admin/index.tsx`

Finally, register the frontend provider class into the global `FilterEngine` when Flarum boots.

```typescript
import app from "flarum/admin/app";
import ToxicityProvider from "./providers/ToxicityProvider";

app.initializers.add("your-namespace/toxicity-rules", () => {
  // Wait for Filter Rule Manager to boot.
  // This is why adding it to your composer.json is mandatory!
  if (!app.filterRuleManager) {
    console.error("Filter Rule Manager is not installed or booted.");
    return;
  }

  // Register the provider instance
  app.filterRuleManager.registerProvider("toxicity", new ToxicityProvider());
});
```

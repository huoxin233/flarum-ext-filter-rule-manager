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
            // These strings are dynamically injected into the `{matched_word}`
            // placeholder in the admin's Flag or Block message!
            if ($score >= $threshold) {
                return [
                    'matched_word' => "Score: {$score} (Threshold: {$threshold})"
                ];
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

### Differential Evaluation & State Hashing

Filter Rule Manager features a **Differential Evaluation Engine** that allows users to edit posts that have previously been flagged and approved by a moderator ("grandfathering"), as long as they do not _increase_ the violation severity.

When a user edits a post, the engine runs your `evaluate()` method twice: once on the new text, and once on the old text. It then strictly compares the returned arrays using `===`.

**Critical Requirement:** If your rule evaluates _quantities_ (e.g. counting the number of bad words, or the number of spam links), you **MUST** include a state indicator in your returned array (such as `'__count' => $totalMatches`).
If you fail to do this, the engine will assume the new text and old text are identical, and users will be able to inject an infinite amount of additional spam into their grandfathered posts!

If your rule is simply binary (e.g. "Is the user in Group X?"), you do not need to worry about this. Furthermore, administrators can optionally bypass this entirely by enabling **"Strict Edit Evaluation"** on the ruleset, which ruthlessly re-evaluates all edits.

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

---

## 4. Content Preprocessors (Modifiers)

If you want to strip out or extract specific parts of the text _before_ the rule providers evaluate it, you can register a **Content Modifier** (also known as a preprocessor).

For example, if you want a rule to strictly only evaluate text after ignoring quotes or ignoring spoiler blocks. Note that users can chain multiple modifiers together sequentially in the UI (e.g., stripping quotes, then stripping spoiler).

### 4.1 Backend Implementation (PHP)

First, implement the modifier logic on the backend by implementing `Huoxin\FilterRuleManager\Modifier\ModifierInterface`:

```php
<?php
namespace YourNamespace\Modifier;

use Huoxin\FilterRuleManager\Modifier\ModifierInterface;
use Huoxin\FilterRuleManager\Model\EvaluationContext;

class StripSpoilersModifier implements ModifierInterface
{
    public function key(): string
    {
        return 'no_spoilers';
    }

    public function name(): string
    {
        return 'Ignore Spoilers';
    }

    public function description(): string
    {
        return 'Removes spoiler tags before evaluation.';
    }

    public function modify(string $content, ?EvaluationContext $context = null): string
    {
        // Strip out spoiler tags
        return preg_replace('/>!.*?!</s', '', $content);
    }
}
```

Then, register it in your `extend.php`:

```php
<?php
use Huoxin\FilterRuleManager\Extend\FilterContentModifier;
use YourNamespace\Modifier\StripSpoilersModifier;

return [
    (new FilterContentModifier())
        ->register(StripSpoilersModifier::class),
];
```

The modifier will now automatically appear as an option in the "Target Text Preprocessors" dropdown in the visual rule builder!

### 4.2 Frontend Implementation (Optional)

If your rulesets use **Info** or **Warning** interventions, they are evaluated directly in the browser via JavaScript to provide real-time feedback.

To make your modifier work in real-time on the frontend, you must register a JavaScript equivalent in your `forum` payload:

```typescript
import app from "flarum/forum/app";

app.initializers.add("your-namespace/modifiers", () => {
  if (!app.filterRuleManager) return;

  app.filterRuleManager.registerModifier("no_spoilers", (content: string, context?: any) => {
    // Context contains { composer, application } during typing
    // Strip out spoiler tags using JS regex
    return content.replace(/>![\s\S]*?!</g, "");
  });
});
```
## 5. Global Variables (Tokens)

Global Tokens are unconditional variables that are injected into the context of *every* evaluated ruleset (such as {{rule_name}}). These are different from Rule Provider tokens which are only exposed if a specific condition is met.

If your extension injects system-wide variables into the evaluation context (via Flarum's Event dispatcher or method overrides), you should register them in the Filter Engine so they appear in the UI's Token Hints.

### `js/src/admin/index.tsx`
```typescript
import app from "flarum/admin/app";
import { FilterEngine } from "huoxin/filter-rule-manager/common/FilterEngine";

app.initializers.add("my-extension", () => {
  const filterEngine: FilterEngine = app.filterRuleManager;
  
  // Register a global token (name, translation_key_for_description)
  filterEngine.registerGlobalToken('discord_channel', 'my-extension.admin.token_discord_channel_desc');
});
```

Because Global Tokens are inherently system-level, they do not require a Rule Provider class and are not assigned a Type or Label in the Registry tab.

# Filter Rule Manager

![License](https://img.shields.io/badge/license-GPL-3.0-or-later-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/huoxin/filter-rule-manager.svg)](https://packagist.org/packages/huoxin/filter-rule-manager) [![Total Downloads](https://img.shields.io/packagist/dt/huoxin/filter-rule-manager.svg)](https://packagist.org/packages/huoxin/filter-rule-manager)

A powerful, AST-based moderation and filtering engine for [Flarum](https://flarum.org).

Filter Rule Manager goes beyond simple word replacements. It allows forum administrators to build complex, logical rulesets that can dynamically evaluate post content, automatically flag suspicious behavior, require manual approval, or block posts entirely.

## Features

Filter Rule Manager is built to give Flarum administrators fine-grained control over their community's content. It covers the following features out-of-the-box:

- **Visual Rule Builder**: Construct complex logic using an intuitive drag-and-drop interface. Group conditions using `AND` / `OR` / `NOT` logic to create highly specific content filters.
- **Priority-based Execution**: Order your rulesets. Higher priority rules execute first, efficiently preventing unnecessary processing on lower rulesets.
- **Intervention Types**: Choose exactly how the system reacts to a violation:
  - **Info**: Displays a real-time hint while the user is typing, but does not block them from submitting the post.
  - **Warning**: Displays a real-time hint and explicitly requires user confirmation (via a modal) before they can submit the post.
  - **Block**: Evaluated server-side upon submission. Prevents the post from being submitted entirely and displays an error message.
  - **Silent**: Does not display anything to the user. Evaluated silently on the server-side.
    _(Note: You can configure any of the above to also automatically Flag the post or hold it for Approval.)_
- **Dynamic Scopes**: Apply filtering rules globally, or restrict them to specific **Tags** or specific **Discussions**.
- **Evasion Detection**: Define strict timeout windows and strike thresholds. If a user repeatedly hits block rules (e.g., 3 times within 15 minutes), the system automatically escalates penalties, flagging their next _clean_ post for moderator review.
- **Bypass Groups**: Exempt specific User Groups (e.g., Moderators, Admins) from individual rulesets.
- **Customizable Messaging**: Define dynamic flag reasons and block messages using variable interpolation (e.g., `Matched word: {{matched_word}}` or `Triggered ruleset: {{ruleset}}`). Messages support **HTML**.
- **Extensible API**: Other extensions can securely inject their own custom Rule Providers into the AST engine.

## Installation

Install with composer:

```sh
composer require huoxin/filter-rule-manager:"*"
```

## Updating

```sh
composer update huoxin/filter-rule-manager:"*"
php flarum migrate
php flarum cache:clear
```

## For Developers

Filter Rule Manager is built to be extended! If you are an extension developer and want to register custom Rule Providers (e.g., AI toxicity checks, image scanning, custom regex engines), please read the [Extending Guide](EXTENDING.md).

## Links

- [Packagist](https://packagist.org/packages/huoxin/filter-rule-manager)
- [GitHub](https://github.com/huoxin233/filter-rule-manager)
- [Discuss](https://discuss.flarum.org/d/PUT_DISCUSS_SLUG_HERE)

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import type CommentPost from 'flarum/forum/components/CommentPost';
import type Composer from 'flarum/forum/components/Composer';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
import type { Ruleset } from '../common/FilterEngine';

import filterEngine from '../common/FilterEngine';
import FilterRulePopupDispatcher from './FilterRulePopupDispatcher';
import FilterRuleInlineDisplay from './components/FilterRuleInlineDisplay';
import FilterRuleWarningModal from './components/FilterRuleWarningModal';
import BuiltinProvider from './providers/BuiltinProvider';
import BuiltinTemplate from '../common/components/BuiltinTemplate';

app.initializers.add(
  'huoxin/filter-rule-manager',
  () => {
    app.filterRuleManager = filterEngine;

    filterEngine.registerDisplayMode('banner', 'huoxin-filter-rule-manager.admin.displays.banner');
    filterEngine.registerDisplayMode('header_banner', 'huoxin-filter-rule-manager.admin.displays.header_banner');
    filterEngine.registerDisplayMode('sidebar', 'huoxin-filter-rule-manager.admin.displays.sidebar');
    filterEngine.registerDisplayMode('toast', 'huoxin-filter-rule-manager.admin.displays.toast');
    filterEngine.registerDisplayMode('modal', 'huoxin-filter-rule-manager.admin.displays.modal');

    filterEngine.registerProvider('builtin', new BuiltinProvider() as any);
    filterEngine.registerTemplate('builtin', BuiltinTemplate as any);

    let rulesets: Ruleset[] = [];
    try {
      const payload = app.data.filterRuleRulesets;

      if (payload) {
        // Extract forum attributes
        let forumAttrs: Record<string, any> = {};
        if (app.forum) {
          forumAttrs = app.forum.data.attributes || {};
        } else if (app.data.resources) {
          const forumPayload = app.data.resources.find((r: any) => r.type === 'forums' && r.id === '1');
          if (forumPayload && forumPayload.attributes) {
            forumAttrs = forumPayload.attributes;
          }
        }

        const obfuscateActive = forumAttrs.filterRuleObfuscateActive;

        if (obfuscateActive !== false && typeof payload === 'string') {
          const decoded = atob(payload);
          const key = String(forumAttrs.filterRuleObfuscateKey || 'HuoxinFilterRuleManager');
          const keyLen = key.length;
          const bytes = new Uint8Array(decoded.length);
          for (let i = 0, len = decoded.length; i < len; i++) {
            bytes[i] = decoded.charCodeAt(i) ^ key.charCodeAt(i % keyLen);
          }
          const out = new TextDecoder('utf-8').decode(bytes);
          rulesets = JSON.parse(out);
        } else if (Array.isArray(payload)) {
          rulesets = payload;
        }
      }
    } catch (e) {
      console.warn('[FilterRuleManager] Failed to decode rulesets payload', e);
    }
    filterEngine.loadRulesets(rulesets);

    app.filterRulePopupDispatcher = new FilterRulePopupDispatcher(filterEngine);

    let composerStreamDependency: any = null;

    // ── Evaluate on composer mount and listen to stream updates ───────────────
    extend('flarum/forum/components/ComposerBody', 'oncreate', function () {
      filterEngine.start();

      // Hook into the reactive Mithril stream to catch all content changes instantly.
      // This catches normal typing, extension insertions, and raw stream mutations.
      const stream = app.composer && app.composer.fields && app.composer.fields.content;
      if (stream && typeof stream.map === 'function') {
        composerStreamDependency = stream.map(() => {
          filterEngine.debouncedEvaluate();
        });
      }
    });

    extend('flarum/forum/components/ComposerBody', 'onremove', function () {
      filterEngine.stop();
      if (composerStreamDependency && typeof composerStreamDependency.end === 'function') {
        composerStreamDependency.end(true);
        composerStreamDependency = null;
      }
      if (app.filterRulePopupDispatcher) app.filterRulePopupDispatcher.dismissAll();
    });

    // ── `header_banner` and `sidebar` mode: injected via ComposerBody.headerItems ──────────
    extend('flarum/forum/components/ComposerBody', 'headerItems', function (items: ItemList<Mithril.Children>) {
      if (!filterEngine.hasAlerts) return;
      items.add('filter-rule-header-banner', <FilterRuleInlineDisplay variant="header_banner" />, -10);
      items.add('filter-rule-sidebar', <FilterRuleInlineDisplay variant="sidebar" />, -20);
    });

    // ── `banner` mode: injected at the top of #composer ─────────────────────
    extend('flarum/forum/components/Composer', 'oncreate', function (this: Composer & { alertBannerHost?: HTMLElement | null }) {
      const composerEl = document.getElementById('composer');
      if (!composerEl || this.alertBannerHost) return;

      this.alertBannerHost = document.createElement('div');
      this.alertBannerHost.className = 'FilterRuleManager-host';
      composerEl.insertBefore(this.alertBannerHost, composerEl.firstChild);

      m.mount(this.alertBannerHost, {
        view: () => m(FilterRuleInlineDisplay, { variant: 'banner' }),
      });
    });

    extend('flarum/forum/components/Composer', 'onremove', function (this: Composer & { alertBannerHost?: HTMLElement | null }) {
      if (!this.alertBannerHost) return;
      try {
        m.mount(this.alertBannerHost, null);
      } catch (e) {
        /* ignore */
      }
      if (this.alertBannerHost.parentNode) {
        this.alertBannerHost.parentNode.removeChild(this.alertBannerHost);
      }
      this.alertBannerHost = null;
    });

    // ── Warning confirmation on submit ───────────────────────────────────────
    ['flarum/forum/components/DiscussionComposer', 'flarum/forum/components/ReplyComposer', 'flarum/forum/components/EditPostComposer'].forEach(
      (moduleName) => {
        override(moduleName, 'onsubmit', function (this: any, original: Function) {
          filterEngine.clearBlockResults();

          const warnings = filterEngine.activeAlerts.filter((a) => a.ruleset.interventionType === 'warning');

          if (warnings.length === 0) {
            return original();
          }

          const self = this;
          app.modal.show(FilterRuleWarningModal, {
            alerts: warnings,
            onconfirm() {
              app.modal.close();
              original.call(self);
            },
            oncancel() {
              app.modal.close();
              self.loaded();
            },
          });
        });
      }
    );

    // ── Intercept filter_rule_block errors via the documented hook ──────────────
    override(app, 'requestErrorCatch' as any, function (this: any, original: Function, error: any) {
      const errors = error && error.response && error.response.errors;
      if (Array.isArray(errors) && errors[0] && errors[0].code === 'filter_rule_block') {
        const filterRules = errors[0].filterRules || (errors[0].meta && errors[0].meta.filterRules);
        if (filterRules) {
          filterEngine.setBlockResults(filterRules);
          throw error;
        }
      }
      return original(error);
    });

    if (app.initializers.has('flarum-flags')) {
      override('flarum/forum/components/CommentPost', 'flagReason', function (this: CommentPost, original: Function, flag: any) {
        if (flag.type() === 'autoMod') {
          const detail = flag.reasonDetail();
          return [
            app.translator.trans('huoxin-filter-rule-manager.forum.flagger_name'),
            detail ? <span className="Post-flagged-detail">{detail}</span> : '',
          ];
        }
        return original(flag);
      });
    }
  },
  -20
);

import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import Composer from 'flarum/forum/components/Composer';
import ComposerBody from 'flarum/forum/components/ComposerBody';
import DiscussionComposer from 'flarum/forum/components/DiscussionComposer';
import ReplyComposer from 'flarum/forum/components/ReplyComposer';
import EditPostComposer from 'flarum/forum/components/EditPostComposer';
import CommentPost from 'flarum/forum/components/CommentPost';

import filterEngine from '../common/FilterEngine';
import FilterRulePopupDispatcher from './FilterRulePopupDispatcher';
import FilterRuleInlineDisplay from './components/FilterRuleInlineDisplay';
import FilterRuleWarningModal from './components/FilterRuleWarningModal';
import BuiltinProvider from './providers/BuiltinProvider';
import BuiltinTemplate from '../common/components/BuiltinTemplate';

app.initializers.add('huoxin/filter-rule-manager', () => {
  app.filterRuleManager = filterEngine;

  filterEngine.registerDisplayMode('banner', 'huoxin-filter-rule-manager.admin.displays.banner');
  filterEngine.registerDisplayMode('header_banner', 'huoxin-filter-rule-manager.admin.displays.header_banner');
  filterEngine.registerDisplayMode('sidebar', 'huoxin-filter-rule-manager.admin.displays.sidebar');
  filterEngine.registerDisplayMode('toast', 'huoxin-filter-rule-manager.admin.displays.toast');
  filterEngine.registerDisplayMode('modal', 'huoxin-filter-rule-manager.admin.displays.modal');

  filterEngine.registerProvider('builtin', new BuiltinProvider());
  filterEngine.registerTemplate('builtin', BuiltinTemplate);
  filterEngine.loadRulesets(app.data.filterRuleRulesets || []);

  app.filterRulePopupDispatcher = new FilterRulePopupDispatcher(filterEngine);

  // ── Start/stop polling on composer mount/unmount ─────────────────────────
  extend(ComposerBody.prototype, 'oncreate', function () {
    filterEngine.start();
  });

  extend(ComposerBody.prototype, 'onremove', function () {
    filterEngine.stop();
    if (app.filterRulePopupDispatcher) app.filterRulePopupDispatcher.dismissAll();
  });

  // ── `header_banner` and `sidebar` mode: injected via ComposerBody.headerItems ──────────
  extend(ComposerBody.prototype, 'headerItems', function (items) {
    if (!filterEngine.hasAlerts) return;
    items.add('filter-rule-header-banner', <FilterRuleInlineDisplay variant="header_banner" />, -10);
    items.add('filter-rule-sidebar',       <FilterRuleInlineDisplay variant="sidebar"       />, -20);
  });

  // ── `banner` mode: injected at the top of #composer ─────────────────────
  extend(Composer.prototype, 'oncreate', function () {
    const composerEl = document.getElementById('composer');
    if (!composerEl || this.alertBannerHost) return;

    this.alertBannerHost = document.createElement('div');
    this.alertBannerHost.className = 'FilterRuleManager-host';
    // Insert at the very beginning of #composer, above the .Composer card
    composerEl.insertBefore(this.alertBannerHost, composerEl.firstChild);

    m.mount(this.alertBannerHost, {
      view: () => m(FilterRuleInlineDisplay, { variant: 'banner' }),
    });
  });

  extend(Composer.prototype, 'onremove', function () {
    if (!this.alertBannerHost) return;
    try { m.mount(this.alertBannerHost, null); } catch (e) { /* ignore */ }
    if (this.alertBannerHost.parentNode) {
      this.alertBannerHost.parentNode.removeChild(this.alertBannerHost);
    }
    this.alertBannerHost = null;
  });

  // ── Warning confirmation on submit ───────────────────────────────────────
  [DiscussionComposer, ReplyComposer, EditPostComposer].forEach((Cls) => {
    override(Cls.prototype, 'onsubmit', function (original) {
      // Clear OUR previous block result so a stale 422 from a prior attempt
      // doesn't linger. The textarea content is never touched here.
      filterEngine.clearBlockResults();

      const warnings = filterEngine.activeAlerts.filter(
        (a) => a.ruleset.effectType === 'warning'
      );

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
  });

  // ── Intercept filter_rule_block errors via the documented hook ──────────────
  override(app, 'requestErrorCatch', function (original, error) {
    const errors = error && error.response && error.response.errors;
    if (Array.isArray(errors) && errors[0] && errors[0].code === 'filter_rule_block') {
      const filterRules =
        errors[0].filterRules ||
        (errors[0].meta && errors[0].meta.filterRules);
      if (filterRules) {
        filterEngine.setBlockResults(filterRules);
        throw error;
      }
    }
    return original(error);
  });

  if ('flarum-flags' in flarum.extensions) {
    override(CommentPost.prototype, 'flagReason', function (original, flag) {
      if (flag.type() === 'autoMod') {
        const detail = flag.reasonDetail();
        return [app.translator.trans('huoxin-filter-rule-manager.forum.flagger_name'), detail ? <span className="Post-flagged-detail">{detail}</span> : ''];
      }
      return original(flag);
    });
  }
}, -20);

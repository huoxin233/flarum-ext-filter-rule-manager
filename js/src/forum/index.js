import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import Composer from 'flarum/forum/components/Composer';
import ComposerBody from 'flarum/forum/components/ComposerBody';
import DiscussionComposer from 'flarum/forum/components/DiscussionComposer';
import ReplyComposer from 'flarum/forum/components/ReplyComposer';
import EditPostComposer from 'flarum/forum/components/EditPostComposer';
import CommentPost from 'flarum/forum/components/CommentPost';

import filterEngine from '../common/FilterEngine';
import RuleDispatcher from './RuleDispatcher';
import FilterRuleBanner from './components/FilterRuleBanner';
import FilterRuleWarningModal from './components/FilterRuleWarningModal';
import BuiltinProvider from './providers/BuiltinProvider';

app.initializers.add('huoxin/filter-rule-manager', () => {
  app.filterRuleManager = filterEngine;

  filterEngine.registerProvider('builtin', new BuiltinProvider());
  filterEngine.loadRulesets(app.data.filterRuleRulesets || []);

  app.filterRuleDispatcher = new RuleDispatcher(filterEngine);

  // ── Start/stop polling on composer mount/unmount ─────────────────────────
  extend(ComposerBody.prototype, 'oncreate', function () {
    filterEngine.start();
  });

  extend(ComposerBody.prototype, 'onremove', function () {
    filterEngine.stop();
    if (app.filterRuleDispatcher) app.filterRuleDispatcher.dismissAll();
  });

  // ── `header_banner` mode: injected via ComposerBody.headerItems ──────────
  // Sits inside <ul.ComposerBody-header>, narrower than the composer width
  // (aligned with the editor). Sidebar shares this hook because its
  // position:absolute lifts it out anyway.
  extend(ComposerBody.prototype, 'headerItems', function (items) {
    if (!filterEngine.hasAlerts) return;
    items.add('filter-rule-header-banner', <FilterRuleBanner variant="header_banner" />, 100);
    items.add('filter-rule-sidebar',       <FilterRuleBanner variant="sidebar"       />, 90);
  });

  // ── `banner` mode: injected at .App-composer > .container level ──────────
  // This sits ABOVE the entire #composer mount, full container width with the
  // negative left / positive right margin to align visually with the editor.
  // We can't render it from Composer.view() because the .Composer root only
  // returns one vnode — instead we DOM-mount a sibling node before #composer
  // and let Mithril keep it in sync via m.mount + FilterEngine.subscribe.
  extend(Composer.prototype, 'oncreate', function () {
    const composerEl = document.getElementById('composer');
    if (!composerEl || !composerEl.parentElement) return;
    if (this.alertBannerHost) return; // defensive: oncreate fires once but be safe

    this.alertBannerHost = document.createElement('div');
    this.alertBannerHost.className = 'FilterRuleManager-host';
    composerEl.parentElement.insertBefore(this.alertBannerHost, composerEl);

    m.mount(this.alertBannerHost, {
      view: () => m(FilterRuleBanner, { variant: 'banner' }),
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

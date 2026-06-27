/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import app from 'flarum/admin/app';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import Stream from 'flarum/common/utils/Stream';
import GroupBadge from 'flarum/common/components/GroupBadge';
import type Mithril from 'mithril';

export interface GroupListConfigAttrs extends ComponentAttrs {
  config?: Record<string, unknown>;
  type: string;
  onchange: (newConfig: Record<string, unknown>) => void;
}

/**
 * Config UI for the builtin `group` rule type.
 *
 * Rule config shape: { groupIds: number[] }
 */
export default class GroupListConfig extends Component<GroupListConfigAttrs> {
  groupIds!: Stream<number[]>;

  oninit(vnode: Mithril.Vnode<GroupListConfigAttrs, this>) {
    super.oninit(vnode);

    const cfg = this.attrs.config || {};
    let initial: number[] = [];
    if (Array.isArray(cfg.groupIds)) initial = cfg.groupIds.map((id: any) => parseInt(String(id), 10));

    this.groupIds = Stream(initial);
  }

  view(): Mithril.Children {
    return (
      <div className="FilterRuleManager-ConfigForm">
        <div className="Form-group">
          <label>{app.translator.trans('huoxin-filter-rule-manager.admin.config_groups_label')}</label>
          <div className="FilterRuleManager-RulesetEditor-groupSelection">
            {app.store.all('groups').map((group: any) => {
              const id = parseInt(String(group.id()), 10);
              const isActive = (this.groupIds() || []).includes(id);
              return (
                <label className={`FilterRuleManager-RulesetEditor-groupOption ${isActive ? 'active' : ''}`} key={id}>
                  <input
                    type="checkbox"
                    checked={isActive}
                    onchange={(e: Event) => {
                      const checked = (e.target as HTMLInputElement).checked;
                      let current = this.groupIds() || [];
                      if (checked) {
                        current.push(id);
                      } else {
                        current = current.filter((g: number) => g !== id);
                      }
                      this.groupIds(current);
                      this.attrs.onchange({ ...(this.attrs.config || {}), groupIds: current });
                    }}
                  />
                  <div className="FilterRuleManager-RulesetEditor-groupOption-content">
                    <GroupBadge group={group} label="" />
                    <span className="FilterRuleManager-RulesetEditor-groupOption-name">{String(group.namePlural() || group.name())}</span>
                  </div>
                </label>
              );
            })}
          </div>
          <div className="helpText">{app.translator.trans('huoxin-filter-rule-manager.admin.config_groups_help')}</div>
        </div>
      </div>
    );
  }
}

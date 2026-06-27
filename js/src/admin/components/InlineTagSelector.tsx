/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import Component, { ComponentAttrs } from 'flarum/common/Component';
import app from 'flarum/admin/app';
import icon from 'flarum/common/helpers/icon';
import classList from 'flarum/common/utils/classList';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';

export interface Tag {
  id: () => string | number;
  name: () => string;
  icon: () => string | null;
  color: () => string | null;
  position: () => number | null | undefined;
  isChild: () => boolean;
  parent: () => Tag | null;
}

export interface InlineTagSelectorAttrs extends ComponentAttrs {
  tags?: Tag[];
  selectedIds: Stream<number[]>;
}

export default class InlineTagSelector extends Component<InlineTagSelectorAttrs> {
  tags: Tag[] = [];
  selectedIds!: Stream<number[]>;

  oninit(vnode: Mithril.Vnode<InlineTagSelectorAttrs, this>) {
    super.oninit(vnode);
    this.tags = this.attrs.tags || [];
    this.selectedIds = this.attrs.selectedIds;
  }

  view(): Mithril.Children {
    const tags = this.attrs.tags || [];
    const primaryTags = tags
      .filter((t) => t.position() !== null && t.position() !== undefined && !t.isChild())
      .sort((a, b) => (a.position() || 0) - (b.position() || 0));
    const secondaryTags = tags.filter((t) => t.position() === null || t.position() === undefined).sort((a, b) => a.name().localeCompare(b.name()));
    const childTags = tags.filter((t) => t.isChild());

    return (
      <div className="FilterRuleManager-InlineTagSelector">
        {primaryTags.length > 0 && (
          <div className="FilterRuleManager-InlineTagSelector-group">
            <label className="FilterRuleManager-InlineTagSelector-groupLabel">
              {app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_primary_tags')}
            </label>
            <div className="FilterRuleManager-InlineTagSelector-list">
              {primaryTags.map((tag) =>
                this.renderTag(
                  tag,
                  childTags.filter((c) => c.parent() === tag)
                )
              )}
            </div>
          </div>
        )}
        {secondaryTags.length > 0 && (
          <div className="FilterRuleManager-InlineTagSelector-group">
            <label className="FilterRuleManager-InlineTagSelector-groupLabel">
              {app.translator.trans('huoxin-filter-rule-manager.admin.ruleset_secondary_tags')}
            </label>
            <div className="FilterRuleManager-InlineTagSelector-list">{secondaryTags.map((tag) => this.renderTag(tag, []))}</div>
          </div>
        )}
      </div>
    );
  }

  renderTag(tag: Tag, children: Tag[]): Mithril.Children {
    const id = parseInt(String(tag.id()), 10);
    const selected = (this.selectedIds() || []).includes(id);

    return (
      <div className="FilterRuleManager-InlineTagSelector-itemContainer" key={id}>
        <label className={classList('FilterRuleManager-InlineTagSelector-item', { active: selected })}>
          <input type="checkbox" checked={selected} onchange={(e: Event) => this.toggleTag(id, (e.target as HTMLInputElement).checked)} />
          <span className="FilterRuleManager-InlineTagSelector-icon" style={{ backgroundColor: tag.color() }}>
            {tag.icon() && icon(tag.icon() as string)}
          </span>
          <span className="FilterRuleManager-InlineTagSelector-name">{tag.name()}</span>
        </label>
        {children && children.length > 0 && (
          <div className="FilterRuleManager-InlineTagSelector-children">{children.map((child) => this.renderTag(child, []))}</div>
        )}
      </div>
    );
  }

  toggleTag(id: number, checked: boolean) {
    let ids = this.selectedIds() || [];
    ids = [...ids];
    if (checked) {
      if (!ids.includes(id)) ids.push(id);
    } else {
      ids = ids.filter((i: number) => i !== id);
    }
    this.selectedIds(ids);
  }
}

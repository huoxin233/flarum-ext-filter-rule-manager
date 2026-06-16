/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

import Component, { ComponentAttrs } from 'flarum/common/Component';
import icon from 'flarum/common/helpers/icon';
import classList from 'flarum/common/utils/classList';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';

export interface InlineTagSelectorAttrs extends ComponentAttrs {
  tags?: any[];
  selectedIds: Stream<number[]>;
}

export default class InlineTagSelector extends Component<InlineTagSelectorAttrs> {
  tags: any[] = [];
  selectedIds!: Stream<number[]>;

  oninit(vnode: Mithril.Vnode<InlineTagSelectorAttrs, this>) {
    super.oninit(vnode);
    this.tags = this.attrs.tags || [];
    this.selectedIds = this.attrs.selectedIds;
  }

  view(): Mithril.Children {
    const tags = this.attrs.tags || [];
    const primaryTags = tags.filter(t => t.position() !== null && t.position() !== undefined && !t.isChild()).sort((a, b) => a.position() - b.position());
    const secondaryTags = tags.filter(t => t.position() === null || t.position() === undefined).sort((a, b) => a.name().localeCompare(b.name()));
    const childTags = tags.filter(t => t.isChild());

    return (
      <div className="InlineTagSelector">
        {primaryTags.length > 0 && (
          <div className="InlineTagSelector-group">
            <label className="InlineTagSelector-groupLabel">Primary Tags</label>
            <div className="InlineTagSelector-list">
              {primaryTags.map(tag => this.renderTag(tag, childTags.filter(c => c.parent() === tag)))}
            </div>
          </div>
        )}
        {secondaryTags.length > 0 && (
          <div className="InlineTagSelector-group">
            <label className="InlineTagSelector-groupLabel">Secondary Tags</label>
            <div className="InlineTagSelector-list">
              {secondaryTags.map(tag => this.renderTag(tag, []))}
            </div>
          </div>
        )}
      </div>
    );
  }

  renderTag(tag: any, children: any[]): Mithril.Children {
    const id = parseInt(tag.id(), 10);
    const selected = (this.selectedIds() || []).includes(id);

    return (
      <div className="InlineTagSelector-itemContainer" key={id}>
        <label className={classList('InlineTagSelector-item', { active: selected })}>
          <input 
            type="checkbox" 
            checked={selected}
            onchange={(e: any) => this.toggleTag(id, e.target.checked)}
          />
          <span className="InlineTagSelector-icon" style={{ backgroundColor: tag.color() }}>
            {tag.icon() && icon(tag.icon())}
          </span>
          <span className="InlineTagSelector-name">{tag.name()}</span>
        </label>
        {children && children.length > 0 && (
          <div className="InlineTagSelector-children">
            {children.map(child => this.renderTag(child, []))}
          </div>
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
      ids = ids.filter(i => i !== id);
    }
    this.selectedIds(ids);
  }
}

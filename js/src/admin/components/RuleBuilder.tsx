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
import Button from 'flarum/common/components/Button';
import Select from 'flarum/common/components/Select';
import Switch from 'flarum/common/components/Switch';
import icon from 'flarum/common/helpers/icon';
import { parseExpression, stringifyExpression } from '../utils/ExpressionParser';
import type Mithril from 'mithril';

let nodeIdCounter = 1;

function ensureKeys(node: any) {
  if (!node) return;
  if (!node._key) node._key = nodeIdCounter++;
  if (node.left) ensureKeys(node.left);
  if (node.right) ensureKeys(node.right);
  if (node.node) ensureKeys(node.node);
}

function createEmptyRule(providers: any[]) {
  const first = providers[0];
  return { _key: nodeIdCounter++, type: 'rule', provider: first ? first.provider : '', ruleType: first ? first.type : '', operator: 'eq', value: '' };
}

interface LogicalNodeViewAttrs extends ComponentAttrs {
  node: any;
  onchange: (v: any) => void;
  providers: any[];
}

class LogicalNodeView extends Component<LogicalNodeViewAttrs> {
  view(): Mithril.Children {
    const { node, onchange, providers } = this.attrs;
    const isAnd = node.operator === 'AND';
    const isOr = node.operator === 'OR';

    return (
      <div className="Expression-LogicalNode" key={node._key}>
        {[
          <NodeView
            key={node.left ? node.left._key : 'l'}
            node={node.left}
            onchange={(v: any) => {
              if (v === null) onchange(node.right);
              else onchange({ ...node, left: v });
            }}
            providers={providers}
          />,

          isAnd ? (
            <div className="LogicalNode-andContainer" key="and">
              <div className="LogicalNode-connector"></div>
              <Button
                className="Button Button--basic LogicalNode-andButton"
                onclick={() => {
                  onchange({ ...node, operator: 'OR' });
                }}
              >
                {app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.and')}
              </Button>
              <div className="LogicalNode-connector"></div>
            </div>
          ) : null,

          isOr ? (
            <div className="LogicalNode-orContainer" key="or">
              <Button
                className="Button Button--basic LogicalNode-orButton"
                onclick={() => {
                  onchange({ ...node, operator: 'AND' });
                }}
              >
                {app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.or')}
              </Button>
            </div>
          ) : null,

          <NodeView
            key={node.right ? node.right._key : 'r'}
            node={node.right}
            onchange={(v: any) => {
              if (v === null) onchange(node.left);
              else onchange({ ...node, right: v });
            }}
            providers={providers}
          />,
        ].filter(Boolean)}
      </div>
    );
  }
}

interface RuleNodeViewAttrs extends ComponentAttrs {
  node: any;
  isNegated: boolean;
  onchange: (v: any) => void;
  onNegateChange: (v: boolean) => void;
  providers: any[];
}

class RuleNodeView extends Component<RuleNodeViewAttrs> {
  view(): Mithril.Children {
    const { node, onchange, providers } = this.attrs;

    const providerOptions: Record<string, string> = {};
    providers.forEach((p) => {
      if (p.provider) {
        const transKey = `huoxin-filter-rule-manager.admin.providers.${p.provider}`;
        const translated = app.translator.trans(transKey);
        providerOptions[p.provider] = translated !== transKey && translated ? String(translated) : p.providerLabel || p.provider;
      }
    });

    const availableTypes = providers.filter((p) => p.provider === node.provider);
    const typeOptions = availableTypes.reduce((acc: Record<string, string>, p) => {
      acc[p.type] = p.label || p.type;
      return acc;
    }, {});

    return (
      <div className="Expression-RuleNode">
        <div className="RuleNode-header">
          <Select
            options={providerOptions}
            value={node.provider}
            onchange={(val: string) => {
              const firstType = providers.find((p) => p.provider === val);
              onchange({ ...node, provider: val, ruleType: firstType ? firstType.type : '', value: '' });
            }}
          />

          <Select
            options={typeOptions}
            value={node.ruleType}
            onchange={(val: string) => onchange({ ...node, ruleType: val, value: '' })}
            disabled={!node.provider}
          />

          <div className="RuleNode-negateToggle">
            <Switch state={this.attrs.isNegated} onchange={this.attrs.onNegateChange}>
              {app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.negate')}
            </Switch>
          </div>

          <div className="RuleNode-actions">
            <Button
              className="Button Button--icon Button--danger"
              icon="fas fa-times"
              onclick={() => onchange(null)}
              title={String(app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.delete_rule'))}
            />
          </div>
        </div>

        <div className="Expression-RuleNode-config">{this.renderConfig()}</div>
      </div>
    );
  }

  renderConfig(): Mithril.Children {
    const { node, onchange } = this.attrs;

    const filterRuleManager = (app as any).filterRuleManager;
    const providerInstance =
      filterRuleManager && typeof filterRuleManager.getProvider === 'function' ? filterRuleManager.getProvider(node.provider) : null;

    const ConfigComponent =
      providerInstance && typeof providerInstance.getConfigComponent === 'function' ? providerInstance.getConfigComponent(node.ruleType) : null;

    if (ConfigComponent) {
      const configObj = typeof node.value === 'object' && node.value !== null && !Array.isArray(node.value) ? node.value : { value: node.value };

      return (
        <ConfigComponent
          config={configObj}
          type={node.ruleType}
          onchange={(newConfig: any) => {
            onchange({ ...node, value: newConfig });
          }}
        />
      );
    }

    let valStr = typeof node.value === 'string' ? node.value : JSON.stringify(node.value, null, 2);
    if (valStr === undefined) valStr = '';

    return (
      <textarea
        className="FormControl"
        rows={2}
        value={valStr}
        onchange={(e: any) => {
          let v = e.target.value;
          const trimmed = v.trim();
          if (trimmed.startsWith('{') || trimmed.startsWith('[')) {
            try {
              v = JSON.parse(trimmed);
            } catch (e) {}
          }
          onchange({ ...node, value: v });
        }}
        placeholder={String(app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.value_placeholder'))}
      />
    );
  }
}

interface NodeViewAttrs extends ComponentAttrs {
  node: any;
  onchange: (v: any) => void;
  providers: any[];
}

class NodeView extends Component<NodeViewAttrs> {
  view(): Mithril.Children {
    const { node, onchange, providers } = this.attrs;

    if (!node) {
      return (
        <Button
          className="Button Button--danger"
          onclick={() => {
            onchange(createEmptyRule(providers));
          }}
        >
          {icon('fas fa-plus')} {app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.add_rule')}
        </Button>
      );
    }

    if (node.type === 'logical') {
      return <LogicalNodeView node={node} onchange={onchange} providers={providers} />;
    }

    if (node.type === 'rule') {
      return (
        <div className="NodeView-ruleContainer" key={node._key}>
          <RuleNodeView
            node={node}
            isNegated={false}
            onchange={onchange}
            providers={providers}
            onNegateChange={(v: boolean) => {
              if (v) {
                onchange({ type: 'not', node: node });
              }
            }}
          />
        </div>
      );
    }

    if (node.type === 'not') {
      if (node.node && node.node.type === 'rule') {
        return (
          <div className="NodeView-ruleContainer" key={node._key}>
            <RuleNodeView
              node={node.node}
              isNegated={true}
              onchange={(v: any) => {
                if (v === null) onchange(null);
                else onchange({ ...node, node: v });
              }}
              providers={providers}
              onNegateChange={(v: boolean) => {
                if (!v) {
                  onchange(node.node);
                }
              }}
            />
          </div>
        );
      }

      return (
        <div className="Expression-NotNode" key={node._key}>
          <div className="NotNode-label" key="label">
            {app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.not')}
          </div>
          <NodeView
            key={node.node ? node.node._key : 'n'}
            node={node.node}
            onchange={(v: any) => {
              if (v === null) onchange(null);
              else onchange({ ...node, node: v });
            }}
            providers={providers}
          />
        </div>
      );
    }

    return <div>{app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.unknown_node_type')}</div>;
  }
}

export interface RuleBuilderAttrs extends ComponentAttrs {
  expression?: string;
  onchange?: (v: string) => void;
  providers?: any[];
}

export default class RuleBuilder extends Component<RuleBuilderAttrs> {
  mode: string = 'visual';
  expression: string = '';
  ast: any = null;
  parseError: string | null = null;

  oninit(vnode: Mithril.Vnode<RuleBuilderAttrs, this>) {
    super.oninit(vnode);
    this.mode = 'visual';
    this.expression = this.attrs.expression || '';
    this.ast = null;
    this.parseError = null;

    if (this.expression) {
      this.syncToVisual();
    } else {
      this.ast = null;
    }
  }

  syncToVisual() {
    this.parseError = null;
    try {
      this.ast = parseExpression(this.expression);
      ensureKeys(this.ast);
    } catch (e: any) {
      this.parseError = e.message;
      this.mode = 'editor';
    }
  }

  syncToEditor() {
    this.expression = stringifyExpression(this.ast);
    this.emit();
  }

  emit() {
    if (typeof this.attrs.onchange === 'function') {
      this.attrs.onchange(this.expression);
    }
  }

  view(): Mithril.Children {
    const providers = this.attrs.providers || [];

    return (
      <div className="RuleBuilder">
        <div className="RuleBuilder-tabs">
          <Button
            className={`Button RuleBuilder-tabButton ${this.mode === 'visual' ? 'active' : ''}`}
            onclick={() => {
              this.syncToVisual();
              if (!this.parseError) this.mode = 'visual';
            }}
          >
            {icon('fas fa-project-diagram')} {app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.visual_builder')}
          </Button>
          <Button
            className={`Button RuleBuilder-tabButton ${this.mode === 'editor' ? 'active' : ''}`}
            onclick={() => {
              if (this.mode === 'visual') this.syncToEditor();
              this.mode = 'editor';
            }}
          >
            {icon('fas fa-code')} {app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.expression_editor')}
          </Button>
        </div>

        {this.parseError && (
          <div className="Alert Alert--error RuleBuilder-errorAlert">
            <strong>{app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.parse_error')}</strong> {this.parseError}
            <br />
            {app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.parse_error_help')}
          </div>
        )}

        {this.mode === 'visual' ? (
          <div className="RuleBuilder-visual">
            {[
              <NodeView
                key={this.ast ? this.ast._key : 'root'}
                node={this.ast}
                onchange={(newAst: any) => {
                  this.ast = newAst;
                  this.syncToEditor();
                }}
                providers={providers}
              />,
              this.ast ? (
                <div className="RuleBuilder-appendButtons" key="appendButtons">
                  <Button
                    className="Button Button--basic RuleBuilder-appendButton"
                    onclick={() => {
                      const newLogical = {
                        _key: nodeIdCounter++,
                        type: 'logical',
                        operator: 'AND',
                        left: this.ast,
                        right: createEmptyRule(providers),
                      };
                      this.ast = newLogical;
                      this.syncToEditor();
                    }}
                  >
                    {app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.and')}
                  </Button>
                  <Button
                    className="Button Button--basic RuleBuilder-appendButton"
                    onclick={() => {
                      const newLogical = {
                        _key: nodeIdCounter++,
                        type: 'logical',
                        operator: 'OR',
                        left: this.ast,
                        right: createEmptyRule(providers),
                      };
                      this.ast = newLogical;
                      this.syncToEditor();
                    }}
                  >
                    {app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.or')}
                  </Button>
                </div>
              ) : null,
            ].filter(Boolean)}
          </div>
        ) : (
          <div className="RuleBuilder-editor">
            <textarea
              className="FormControl RuleBuilder-textarea"
              value={this.expression}
              oninput={(e: any) => {
                this.expression = e.target.value;
                this.emit();
              }}
              placeholder={String(app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.editor_placeholder'))}
              rows={6}
            />
            <div className="helpText RuleBuilder-editorHelp">{app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.editor_help')}</div>
          </div>
        )}
      </div>
    );
  }
}

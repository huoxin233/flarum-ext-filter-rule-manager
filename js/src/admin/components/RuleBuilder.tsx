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
import Icon from 'flarum/common/components/Icon';
import { parseExpression, stringifyExpression } from '../utils/ExpressionParser';
import { ASTNode } from '../../common/FilterEngine';
import type Mithril from 'mithril';

export interface FrontendProvider {
  provider: string;
  providerLabel?: string;
  type: string;
  label?: string;
  tokens?: string[];
}

let nodeIdCounter = 1;

function ensureKeys(node: ASTNode | null | undefined) {
  if (!node) return;
  if (!node._key) node._key = nodeIdCounter++;
  if (node.left) ensureKeys(node.left);
  if (node.right) ensureKeys(node.right);
  if (node.node) ensureKeys(node.node);
}

function createEmptyRule(providers: FrontendProvider[]): ASTNode {
  const first = providers[0];
  return { _key: nodeIdCounter++, type: 'rule', provider: first ? first.provider : '', ruleType: first ? first.type : '', operator: 'eq', value: '' };
}

interface LogicalNodeViewAttrs extends ComponentAttrs {
  node: ASTNode;
  onchange: (v: ASTNode | null) => void;
  providers: FrontendProvider[];
}

class LogicalNodeView extends Component<LogicalNodeViewAttrs> {
  view(): Mithril.Children {
    const { node, onchange, providers } = this.attrs;
    const isAnd = node.operator === 'AND';
    const isOr = node.operator === 'OR';

    return (
      <div className="FilterRuleManager-Expression-LogicalNode">
        <NodeView
          node={node.left}
          onchange={(v: ASTNode | null) => {
            if (v === null) onchange(node.right || null);
            else onchange({ ...node, left: v });
          }}
          providers={providers}
        />

        {isAnd ? (
          <div className="FilterRuleManager-LogicalNode-andContainer">
            <div className="FilterRuleManager-LogicalNode-connector"></div>
            <Button
              className="Button Button--basic FilterRuleManager-LogicalNode-andButton"
              onclick={() => {
                onchange({ ...node, operator: 'OR' });
              }}
            >
              {app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.and')}
            </Button>
            <div className="FilterRuleManager-LogicalNode-connector"></div>
          </div>
        ) : null}

        {isOr ? (
          <div className="FilterRuleManager-LogicalNode-orContainer">
            <Button
              className="Button Button--basic FilterRuleManager-LogicalNode-orButton"
              onclick={() => {
                onchange({ ...node, operator: 'AND' });
              }}
            >
              {app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.or')}
            </Button>
          </div>
        ) : null}

        <NodeView
          node={node.right}
          onchange={(v: ASTNode | null) => {
            if (v === null) onchange(node.left || null);
            else onchange({ ...node, right: v });
          }}
          providers={providers}
        />
      </div>
    );
  }
}

interface RuleNodeViewAttrs extends ComponentAttrs {
  node: ASTNode;
  isNegated: boolean;
  onchange: (v: ASTNode | null) => void;
  onNegateChange: (v: boolean) => void;
  providers: FrontendProvider[];
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
      <div className="FilterRuleManager-Expression-RuleNode">
        <div className="FilterRuleManager-RuleNode-header">
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

          <div className="FilterRuleManager-RuleNode-negateToggle">
            <Switch state={this.attrs.isNegated} onchange={this.attrs.onNegateChange}>
              {app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.negate')}
            </Switch>
          </div>

          <div className="FilterRuleManager-RuleNode-actions">
            <Button
              className="Button Button--icon Button--danger"
              icon="fas fa-times"
              onclick={() => onchange(null)}
              title={String(app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.delete_rule'))}
            />
          </div>
        </div>

        <div className="FilterRuleManager-Expression-RuleNode-config">{this.renderConfig()}</div>
      </div>
    );
  }

  renderConfig(): Mithril.Children {
    const { node, onchange } = this.attrs;

    const filterRuleManager = (app as Record<string, any>).filterRuleManager;
    const providerInstance =
      filterRuleManager && typeof filterRuleManager.getProvider === 'function' ? filterRuleManager.getProvider(node.provider) : null;

    const ConfigComponent =
      providerInstance && typeof providerInstance.getConfigComponent === 'function' ? providerInstance.getConfigComponent(node.ruleType) : null;

    if (ConfigComponent) {
      const configObj = typeof node.value === 'object' && node.value !== null && !Array.isArray(node.value) ? node.value : { value: node.value };

      return (
        <ConfigComponent
          key={node._key}
          config={configObj}
          type={node.ruleType}
          onchange={(newConfig: Record<string, unknown>) => {
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
        onchange={(e: Event) => {
          let v = (e.target as HTMLTextAreaElement).value;
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
  node: ASTNode | null | undefined;
  onchange: (v: ASTNode | null) => void;
  providers: FrontendProvider[];
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
          <Icon name="fas fa-plus" /> {app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.add_rule')}
        </Button>
      );
    }

    if (node.type === 'logical') {
      return <LogicalNodeView node={node} onchange={onchange} providers={providers} />;
    }

    if (node.type === 'rule') {
      return (
        <div className="FilterRuleManager-NodeView-ruleContainer">
          <RuleNodeView
            node={node}
            isNegated={false}
            onchange={onchange}
            providers={providers}
            onNegateChange={(v: boolean) => {
              if (v) {
                onchange({ _key: nodeIdCounter++, type: 'not', node: node });
              }
            }}
          />
        </div>
      );
    }

    if (node.type === 'not') {
      if (node.node && node.node.type === 'rule') {
        return (
          <div className="FilterRuleManager-NodeView-ruleContainer">
            <RuleNodeView
              node={node.node}
              isNegated={true}
              onchange={(v: ASTNode | null) => {
                if (v === null) onchange(null);
                else onchange({ ...node, node: v });
              }}
              providers={providers}
              onNegateChange={(v: boolean) => {
                if (!v) {
                  onchange(node.node || null);
                }
              }}
            />
          </div>
        );
      }

      return (
        <div className="FilterRuleManager-Expression-NotNode">
          <div className="FilterRuleManager-NotNode-label">{app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.not')}</div>
          <NodeView
            node={node.node}
            onchange={(v: ASTNode | null) => {
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
  providers?: FrontendProvider[];
}

export default class RuleBuilder extends Component<RuleBuilderAttrs> {
  mode: string = 'visual';
  expression: string = '';
  ast: ASTNode | null = null;
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
    } catch (e: Error | unknown) {
      this.parseError = e instanceof Error ? e.message : String(e);
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
      <div className="FilterRuleManager-RuleBuilder">
        <div className="FilterRuleManager-RuleBuilder-tabs">
          <Button
            className={`Button FilterRuleManager-RuleBuilder-tabButton ${this.mode === 'visual' ? 'active' : ''}`}
            onclick={() => {
              this.syncToVisual();
              if (!this.parseError) this.mode = 'visual';
            }}
          >
            <Icon name="fas fa-project-diagram" /> {app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.visual_builder')}
          </Button>
          <Button
            className={`Button FilterRuleManager-RuleBuilder-tabButton ${this.mode === 'editor' ? 'active' : ''}`}
            onclick={() => {
              if (this.mode === 'visual') this.syncToEditor();
              this.mode = 'editor';
            }}
          >
            <Icon name="fas fa-code" /> {app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.expression_editor')}
          </Button>
        </div>
        {this.parseError && (
          <div className="Alert Alert--error FilterRuleManager-RuleBuilder-errorAlert">
            <strong>{app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.parse_error')}</strong> {this.parseError}
            <br />
            {app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.parse_error_help')}
          </div>
        )}
        {this.mode === 'visual' ? (
          <div className="FilterRuleManager-RuleBuilder-visual">
            <NodeView
              node={this.ast}
              onchange={(newAst: ASTNode | null) => {
                this.ast = newAst;
                this.syncToEditor();
              }}
              providers={providers}
            />
            {this.ast ? (
              <div className="FilterRuleManager-RuleBuilder-appendButtons">
                <Button
                  className="Button Button--basic FilterRuleManager-RuleBuilder-appendButton"
                  onclick={() => {
                    const newLogical = {
                      _key: nodeIdCounter++,
                      type: 'logical',
                      operator: 'AND',
                      left: this.ast || undefined,
                      right: createEmptyRule(providers),
                    };
                    this.ast = newLogical;
                    this.syncToEditor();
                  }}
                >
                  {app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.and')}
                </Button>
                <Button
                  className="Button Button--basic FilterRuleManager-RuleBuilder-appendButton"
                  onclick={() => {
                    const newLogical = {
                      _key: nodeIdCounter++,
                      type: 'logical',
                      operator: 'OR',
                      left: this.ast || undefined,
                      right: createEmptyRule(providers),
                    };
                    this.ast = newLogical;
                    this.syncToEditor();
                  }}
                >
                  {app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.or')}
                </Button>
              </div>
            ) : null}
          </div>
        ) : (
          <div className="Form-group FilterRuleManager-RuleBuilder-editor">
            <textarea
              className="FormControl FilterRuleManager-RuleBuilder-textarea"
              value={this.expression}
              oninput={(e: Event) => {
                this.expression = (e.target as HTMLTextAreaElement).value;
                this.emit();
              }}
              placeholder={String(app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.editor_placeholder'))}
              rows={6}
            />
            <div className="helpText FilterRuleManager-RuleBuilder-editorHelp">
              {app.translator.trans('huoxin-filter-rule-manager.admin.rule_builder.editor_help')}
            </div>
          </div>
        )}
      </div>
    );
  }
}

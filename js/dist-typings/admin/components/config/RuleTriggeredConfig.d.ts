import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
export interface IRuleTriggeredConfigAttrs extends ComponentAttrs {
    config?: Record<string, any>;
    onchange: (config: Record<string, any>) => void;
}
export default class RuleTriggeredConfig extends Component<IRuleTriggeredConfigAttrs> {
    matchRuleId: string;
    matchName: string;
    matchScope: string;
    matchIntervention: string;
    matchDisplay: string;
    oninit(vnode: Mithril.Vnode<IRuleTriggeredConfigAttrs, this>): void;
    view(): Mithril.Children;
    updateConfig(key: string, value: any): void;
}

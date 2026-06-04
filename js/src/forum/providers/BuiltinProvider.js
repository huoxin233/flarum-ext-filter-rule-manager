/**
 * Forum-side BuiltinProvider — handles real-time evaluation against the
 * composer content. Supports two rule types:
 *
 *   contains_word  — config: { words: string[] }   (legacy: { word: string })
 *   regex          — config: { patterns: string[] } (legacy: { pattern: string })
 *
 * For each type, the rule triggers if ANY of the listed entries matches.
 * The first match's value is exposed as a token for message interpolation.
 */
export default class BuiltinProvider {
  getSupportedTypes() {
    return ['contains_word', 'regex'];
  }

  evaluate(type, content, config) {
    if (type === 'contains_word') {
      const words = this.normalizeList(config, 'words', 'word');
      if (words.length === 0) return null;
      const lowered = String(content).toLowerCase();
      const hit = words.find((w) => lowered.includes(w.toLowerCase()));
      return hit ? { matched_word: hit } : null;
    }

    if (type === 'regex') {
      const patterns = this.normalizeList(config, 'patterns', 'pattern');
      if (patterns.length === 0) return null;
      for (const pattern of patterns) {
        try {
          let body = pattern;
          let flags = 'i';
          if (body.startsWith('/')) {
            const last = body.lastIndexOf('/');
            if (last > 0) {
              flags = body.substring(last + 1) || 'i';
              body = body.substring(1, last);
            }
          }
          const re = new RegExp(body, flags);
          const match = String(content).match(re);
          if (match) {
            return { matched_pattern: pattern, matched_string: match[0] };
          }
        } catch (e) {
          console.warn('[FilterRuleManager] invalid regex in BuiltinProvider:', pattern, e);
        }
      }
    }

    return null;
  }

  /**
   * Normalise either `{ plural: string[] }` (new) or `{ singular: string }`
   * (legacy) into a clean, trimmed, non-empty string array.
   */
  normalizeList(config, plural, singular) {
    const cfg = config || {};
    if (Array.isArray(cfg[plural])) {
      return cfg[plural]
        .map((v) => (typeof v === 'string' ? v : String(v)))
        .map((s) => s.trim())
        .filter((s) => s.length > 0);
    }
    if (typeof cfg[singular] === 'string' && cfg[singular].trim() !== '') {
      return [cfg[singular].trim()];
    }
    return [];
  }
}

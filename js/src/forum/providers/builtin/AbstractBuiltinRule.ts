/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

export abstract class AbstractBuiltinRule {
  abstract name(): string;
  abstract evaluate(content: string, config: Record<string, unknown>): Record<string, string> | null;

  /**
   * Normalise `{ plural: string[] }` into a clean, trimmed, non-empty string array.
   */
  protected normalizeList(config: Record<string, unknown>, plural: string): string[] {
    const cfg = config || {};
    if (Array.isArray(cfg[plural])) {
      return (cfg[plural] as any[])
        .map((v: unknown) => (typeof v === 'string' ? v : String(v)))
        .map((s: string) => s.trim())
        .filter((s: string) => s.length > 0);
    }
    return [];
  }
}

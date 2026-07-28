export default function stripMentions(content: string): string {
  let target = content.replace(/@"?[^"#\n]+"?#(?:p)?\d+/g, '');
  target = target.replace(/@[\p{L}\p{N}_-]+/gu, '');
  return target;
}

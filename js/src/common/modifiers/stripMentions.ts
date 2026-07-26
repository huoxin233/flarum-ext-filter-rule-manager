export default function stripMentions(content: string): string {
  let target = content.replace(/@"?[^"#\n]+"?#(?:p)?\d+/g, '');
  target = target.replace(/@\w+/g, '');
  return target;
}

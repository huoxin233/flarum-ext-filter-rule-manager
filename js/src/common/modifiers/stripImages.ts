export default function stripImages(content: string): string {
  // Strip [img] bbcode (including attributes like [img width=100])
  let target = content.replace(/\[img[^\]]*\][\s\S]*?\[\/img\]/gi, '');
  // Strip Markdown images: ![alt](url)
  target = target.replace(/!\[([^\]]*)\]\([^\)]+\)/g, '');
  return target;
}

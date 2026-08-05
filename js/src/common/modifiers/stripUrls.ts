export default function stripUrls(content: string): string {
  // Regex to match URLs (http, https, ftp, etc.)
  return content.replace(/https?:\/\/[^\s]+/gi, '');
}

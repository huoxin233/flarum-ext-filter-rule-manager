export default function stripUploadTags(content: string): string {
  // Strip [upl-image-preview ...] and other fof/upload tags
  return content.replace(/\[upl-[a-zA-Z0-9-]+[^\]]*\]/g, '');
}

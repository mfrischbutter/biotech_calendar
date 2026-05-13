export function formatBytes(bytes: number): string {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    let value = bytes;
    let unit = 0;
    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }
    return `${value.toFixed(value < 10 && unit > 0 ? 1 : 0)} ${units[unit]}`;
}

export function isImageMime(mime: string | null): boolean {
    return !!mime && mime.startsWith('image/');
}

export function isPdfMime(mime: string | null): boolean {
    return mime === 'application/pdf';
}

export function isPreviewable(mime: string | null): boolean {
    return isImageMime(mime) || isPdfMime(mime);
}

export type FileIconKey = 'image' | 'pdf' | 'doc' | 'sheet' | 'video' | 'audio' | 'archive' | 'file';

export function fileIconKey(mime: string | null): FileIconKey {
    if (!mime) return 'file';
    if (mime.startsWith('image/')) return 'image';
    if (mime.startsWith('video/')) return 'video';
    if (mime.startsWith('audio/')) return 'audio';
    if (mime === 'application/pdf') return 'pdf';
    if (
        mime.includes('word') ||
        mime === 'application/msword' ||
        mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ) return 'doc';
    if (
        mime.includes('sheet') ||
        mime.includes('excel') ||
        mime === 'text/csv'
    ) return 'sheet';
    if (
        mime.includes('zip') ||
        mime.includes('compressed') ||
        mime.includes('tar') ||
        mime.includes('rar')
    ) return 'archive';
    return 'file';
}

function imageExtension(mimeType) {
    const mime = (mimeType || '').toLowerCase();

    if (mime.includes('png')) return 'png';
    if (mime.includes('jpeg') || mime.includes('jpg')) return 'jpg';
    if (mime.includes('webp')) return 'webp';
    if (mime.includes('gif')) return 'gif';

    return 'png';
}

function screenshotFileName(mimeType) {
    const now = new Date();
    const stamp = [
        now.getFullYear(),
        String(now.getMonth() + 1).padStart(2, '0'),
        String(now.getDate()).padStart(2, '0'),
        '-',
        String(now.getHours()).padStart(2, '0'),
        String(now.getMinutes()).padStart(2, '0'),
        String(now.getSeconds()).padStart(2, '0'),
    ].join('');

    return `screenshot-${stamp}.${imageExtension(mimeType)}`;
}

function fileFromClipboardItem(item) {
    const blob = item.getAsFile();

    if (!blob) {
        return null;
    }

    const type = blob.type || item.type || 'image/png';
    const genericName = !blob.name || blob.name === 'image.png' || blob.name === 'image.jpg';

    if (blob instanceof File && !genericName) {
        return blob;
    }

    return new File([blob], screenshotFileName(type), { type });
}

/**
 * @param {ClipboardEvent} event
 * @returns {File|null}
 */
export function imageFileFromPasteEvent(event) {
    const items = Array.from(event.clipboardData?.items ?? []);
    const imageItem = items.find((item) => (
        item.kind === 'file' && item.type.startsWith('image/')
    ));

    if (!imageItem) {
        return null;
    }

    return fileFromClipboardItem(imageItem);
}

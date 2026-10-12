import { marked } from 'marked';
import DOMPurify from 'dompurify';

marked.setOptions({
    gfm: true,
    breaks: true,
});

/**
 * Restore structure when the model returns Markdown markers without real newlines.
 */
export function normalizeMarkdown(raw) {
    let text = String(raw ?? '').replace(/\r\n/g, '\n').replace(/\u00a0/g, ' ');

    if (!text.trim()) {
        return '';
    }

    const newlineCount = (text.match(/\n/g) || []).length;
    const markerCount = (text.match(/(^|\s)(#{1,6}\s|\*\*|---|>\s|-\s|\d+\.\s)/g) || []).length;

    // Compact dump: many markdown markers, almost no line breaks.
    if (markerCount >= 3 && newlineCount < Math.max(3, Math.floor(markerCount / 2))) {
        text = text
            .replace(/\s+(#{1,6}\s+)/g, '\n\n$1')
            .replace(/\s+(---)\s+/g, '\n\n$1\n\n')
            .replace(/\s+(\*\*\d+[\d.\-–—\s])/g, '\n\n$1')
            .replace(/\s+(\*\*[^*\n]{2,80}\*\*)\s*/g, '\n\n$1\n')
            .replace(/\s+(>\s+)/g, '\n$1')
            .replace(/\s+(-\s+)/g, '\n$1')
            .replace(/\s+(\d+\.\s+)/g, '\n$1');
    }

    return text.trim();
}

export function renderMarkdown(raw) {
    const source = normalizeMarkdown(raw);

    if (!source) {
        return '';
    }

    const html = marked.parse(source, { async: false });

    return DOMPurify.sanitize(html, {
        USE_PROFILES: { html: true },
    });
}

export function looksLikeMarkdown(raw) {
    const text = String(raw ?? '');

    return /(^|\n)\s{0,3}#{1,6}\s|(^|\n)\s{0,3}[-*+]\s|(^|\n)\s{0,3}\d+\.\s|\*\*[^*\n]+\*\*|__[^_\n]+__|(^|\n)\s{0,3}>\s|(^|\n)\s{0,3}```|(^|\n)\s{0,3}---\s*($|\n)/m.test(text)
        || (/#{1,6}\s/.test(text) && /\*\*/.test(text));
}

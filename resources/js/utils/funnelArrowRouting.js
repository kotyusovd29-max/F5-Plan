export const STAGE_SIZE = { w: 200, h: 96 };
export const WIDGET_SIZE = { w: 180, h: 88 };
export const OBSTACLE_PAD = 16;
export const EDGE_INSET = 10;
export const CORNER_RADIUS = 12;

/**
 * @param {{ canvas_pos_x: number, canvas_pos_y: number }} node
 * @param {{ w: number, h: number }} size
 */
export function nodeRect(node, size) {
    const x = Number(node.canvas_pos_x) || 0;
    const y = Number(node.canvas_pos_y) || 0;

    return {
        x,
        y,
        w: size.w,
        h: size.h,
        left: x,
        top: y,
        right: x + size.w,
        bottom: y + size.h,
        cx: x + size.w / 2,
        cy: y + size.h / 2,
    };
}

/**
 * @param {ReturnType<typeof nodeRect>} rect
 * @param {number} pad
 */
export function inflateRect(rect, pad) {
    return {
        x: rect.x - pad,
        y: rect.y - pad,
        w: rect.w + pad * 2,
        h: rect.h + pad * 2,
        left: rect.left - pad,
        top: rect.top - pad,
        right: rect.right + pad,
        bottom: rect.bottom + pad,
        cx: rect.cx,
        cy: rect.cy,
    };
}

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}

/**
 * Anchor on the border of `rect`, facing toward `toward`.
 * @param {ReturnType<typeof nodeRect>} rect
 * @param {{ x: number, y: number }} toward
 */
export function edgeAnchor(rect, toward) {
    const dx = toward.x - rect.cx;
    const dy = toward.y - rect.cy;
    const absDx = Math.abs(dx) / Math.max(rect.w, 1);
    const absDy = Math.abs(dy) / Math.max(rect.h, 1);

    if (absDx >= absDy) {
        return {
            x: dx >= 0 ? rect.right : rect.left,
            y: clamp(toward.y, rect.top + EDGE_INSET, rect.bottom - EDGE_INSET),
        };
    }

    return {
        x: clamp(toward.x, rect.left + EDGE_INSET, rect.right - EDGE_INSET),
        y: dy >= 0 ? rect.bottom : rect.top,
    };
}

function orientation(ax, ay, bx, by, cx, cy) {
    const value = (by - ay) * (cx - bx) - (bx - ax) * (cy - by);
    if (Math.abs(value) < 1e-9) return 0;
    return value > 0 ? 1 : 2;
}

function onSegment(ax, ay, bx, by, cx, cy) {
    return (
        Math.min(ax, bx) - 1e-6 <= cx
        && cx <= Math.max(ax, bx) + 1e-6
        && Math.min(ay, by) - 1e-6 <= cy
        && cy <= Math.max(ay, by) + 1e-6
    );
}

function segmentsIntersect(a, b, c, d) {
    const o1 = orientation(a.x, a.y, b.x, b.y, c.x, c.y);
    const o2 = orientation(a.x, a.y, b.x, b.y, d.x, d.y);
    const o3 = orientation(c.x, c.y, d.x, d.y, a.x, a.y);
    const o4 = orientation(c.x, c.y, d.x, d.y, b.x, b.y);

    if (o1 !== o2 && o3 !== o4) return true;
    if (o1 === 0 && onSegment(a.x, a.y, b.x, b.y, c.x, c.y)) return true;
    if (o2 === 0 && onSegment(a.x, a.y, b.x, b.y, d.x, d.y)) return true;
    if (o3 === 0 && onSegment(c.x, c.y, d.x, d.y, a.x, a.y)) return true;
    if (o4 === 0 && onSegment(c.x, c.y, d.x, d.y, b.x, b.y)) return true;

    return false;
}

/**
 * @param {{ x: number, y: number }} a
 * @param {{ x: number, y: number }} b
 * @param {ReturnType<typeof nodeRect>} rect
 */
export function segmentHitsRect(a, b, rect) {
    if (pointInRect(a, rect) || pointInRect(b, rect)) {
        return true;
    }

    const corners = [
        { x: rect.left, y: rect.top },
        { x: rect.right, y: rect.top },
        { x: rect.right, y: rect.bottom },
        { x: rect.left, y: rect.bottom },
    ];

    for (let i = 0; i < 4; i += 1) {
        if (segmentsIntersect(a, b, corners[i], corners[(i + 1) % 4])) {
            return true;
        }
    }

    return false;
}

function pointInRect(point, rect) {
    return (
        point.x >= rect.left
        && point.x <= rect.right
        && point.y >= rect.top
        && point.y <= rect.bottom
    );
}

function pathHitsObstacles(points, obstacles) {
    let hits = 0;

    for (let i = 0; i < points.length - 1; i += 1) {
        const a = points[i];
        const b = points[i + 1];

        for (const obstacle of obstacles) {
            if (segmentHitsRect(a, b, obstacle)) {
                hits += 1;
            }
        }
    }

    return hits;
}

function pathLength(points) {
    let length = 0;

    for (let i = 0; i < points.length - 1; i += 1) {
        length += Math.hypot(points[i + 1].x - points[i].x, points[i + 1].y - points[i].y);
    }

    return length;
}

function simplifyPoints(points) {
    if (points.length < 3) {
        return points.map((point) => ({ x: Math.round(point.x), y: Math.round(point.y) }));
    }

    const result = [points[0]];

    for (let i = 1; i < points.length - 1; i += 1) {
        const prev = result[result.length - 1];
        const curr = points[i];
        const next = points[i + 1];
        const cross = (curr.x - prev.x) * (next.y - curr.y) - (curr.y - prev.y) * (next.x - curr.x);
        const collinear = Math.abs(cross) < 1;
        const same = Math.hypot(curr.x - prev.x, curr.y - prev.y) < 2;

        if (!same && !collinear) {
            result.push(curr);
        }
    }

    result.push(points[points.length - 1]);

    return result.map((point) => ({ x: Math.round(point.x), y: Math.round(point.y) }));
}

function unionBounds(rects) {
    if (!rects.length) {
        return null;
    }

    return {
        left: Math.min(...rects.map((rect) => rect.left)),
        top: Math.min(...rects.map((rect) => rect.top)),
        right: Math.max(...rects.map((rect) => rect.right)),
        bottom: Math.max(...rects.map((rect) => rect.bottom)),
    };
}

function detoursAround(start, end, obstacle) {
    const pad = 8;
    const left = obstacle.left - pad;
    const right = obstacle.right + pad;
    const top = obstacle.top - pad;
    const bottom = obstacle.bottom + pad;

    return [
        [start, { x: start.x, y: top }, { x: end.x, y: top }, end],
        [start, { x: start.x, y: bottom }, { x: end.x, y: bottom }, end],
        [start, { x: left, y: start.y }, { x: left, y: end.y }, end],
        [start, { x: right, y: start.y }, { x: right, y: end.y }, end],
        [start, { x: left, y: start.y }, { x: left, y: top }, { x: end.x, y: top }, end],
        [start, { x: right, y: start.y }, { x: right, y: top }, { x: end.x, y: top }, end],
        [start, { x: left, y: start.y }, { x: left, y: bottom }, { x: end.x, y: bottom }, end],
        [start, { x: right, y: start.y }, { x: right, y: bottom }, { x: end.x, y: bottom }, end],
    ];
}

/**
 * Build SVG path with rounded elbows.
 * @param {Array<{ x: number, y: number }>} points
 */
export function pointsToPath(points) {
    if (points.length < 2) {
        return '';
    }

    if (points.length === 2) {
        return `M ${points[0].x} ${points[0].y} L ${points[1].x} ${points[1].y}`;
    }

    let d = `M ${points[0].x} ${points[0].y}`;

    for (let i = 1; i < points.length - 1; i += 1) {
        const prev = points[i - 1];
        const curr = points[i];
        const next = points[i + 1];
        const inDx = curr.x - prev.x;
        const inDy = curr.y - prev.y;
        const outDx = next.x - curr.x;
        const outDy = next.y - curr.y;
        const inLen = Math.hypot(inDx, inDy) || 1;
        const outLen = Math.hypot(outDx, outDy) || 1;
        const radius = Math.min(CORNER_RADIUS, inLen / 2, outLen / 2);
        const before = {
            x: curr.x - (inDx / inLen) * radius,
            y: curr.y - (inDy / inLen) * radius,
        };
        const after = {
            x: curr.x + (outDx / outLen) * radius,
            y: curr.y + (outDy / outLen) * radius,
        };

        d += ` L ${before.x} ${before.y} Q ${curr.x} ${curr.y}, ${after.x} ${after.y}`;
    }

    const last = points[points.length - 1];
    d += ` L ${last.x} ${last.y}`;

    return d;
}

function midpointOfPolyline(points) {
    const total = pathLength(points);
    if (total <= 0) {
        return { ...points[0] };
    }

    let remaining = total / 2;

    for (let i = 0; i < points.length - 1; i += 1) {
        const a = points[i];
        const b = points[i + 1];
        const segment = Math.hypot(b.x - a.x, b.y - a.y);

        if (segment >= remaining) {
            const t = segment === 0 ? 0 : remaining / segment;
            return {
                x: a.x + (b.x - a.x) * t,
                y: a.y + (b.y - a.y) * t,
            };
        }

        remaining -= segment;
    }

    return { ...points[points.length - 1] };
}

function applyLaneOffset(points, lane) {
    if (!lane || points.length < 2) {
        return points;
    }

    const shift = lane * 28;
    const first = points[0];
    const last = points[points.length - 1];
    const dx = last.x - first.x;
    const dy = last.y - first.y;
    const len = Math.hypot(dx, dy) || 1;
    const nx = -dy / len;
    const ny = dx / len;

    return points.map((point, index) => {
        if (index === 0 || index === points.length - 1) {
            return point;
        }

        return {
            x: point.x + nx * shift,
            y: point.y + ny * shift,
        };
    });
}

/**
 * @param {object} options
 * @param {ReturnType<typeof nodeRect>} options.fromRect
 * @param {ReturnType<typeof nodeRect>} options.toRect
 * @param {Array<{ key: string, rect: ReturnType<typeof nodeRect> }>} options.obstacles
 * @param {string} [options.fromKey]
 * @param {string} [options.toKey]
 * @param {Array<{ x: number, y: number }>|null|undefined} options.waypoints
 * @param {number} [options.lane]
 */
export function routeArrow({
    fromRect,
    toRect,
    obstacles = [],
    fromKey = '',
    toKey = '',
    waypoints = null,
    lane = 0,
}) {
    const blockers = obstacles
        .filter((item) => item.key !== fromKey && item.key !== toKey)
        .map((item) => inflateRect(item.rect, OBSTACLE_PAD));

    const manual = normalizeWaypoints(waypoints);

    if (manual.length) {
        const start = edgeAnchor(fromRect, manual[0]);
        const end = edgeAnchor(toRect, manual[manual.length - 1]);
        const points = simplifyPoints([start, ...manual, end]);

        return {
            points,
            d: pointsToPath(points),
            mid: midpointOfPolyline(points),
            manual: true,
        };
    }

    const roughStart = edgeAnchor(fromRect, { x: toRect.cx, y: toRect.cy });
    const roughEnd = edgeAnchor(toRect, { x: fromRect.cx, y: fromRect.cy });
    const start = edgeAnchor(fromRect, roughEnd);
    const end = edgeAnchor(toRect, roughStart);

    const candidates = [
        [start, end],
        [start, { x: end.x, y: start.y }, end],
        [start, { x: start.x, y: end.y }, end],
    ];

    const bounds = unionBounds(blockers);
    if (bounds) {
        const pad = 24;
        candidates.push(
            [start, { x: start.x, y: bounds.top - pad }, { x: end.x, y: bounds.top - pad }, end],
            [start, { x: start.x, y: bounds.bottom + pad }, { x: end.x, y: bounds.bottom + pad }, end],
            [start, { x: bounds.left - pad, y: start.y }, { x: bounds.left - pad, y: end.y }, end],
            [start, { x: bounds.right + pad, y: start.y }, { x: bounds.right + pad, y: end.y }, end],
        );
    }

    blockers.forEach((obstacle) => {
        candidates.push(...detoursAround(start, end, obstacle));
    });

    let best = null;
    let bestScore = Infinity;

    candidates.forEach((raw) => {
        const points = simplifyPoints(applyLaneOffset(raw, lane));
        const hits = pathHitsObstacles(points, blockers);
        const score = hits * 100000 + pathLength(points) + (points.length * 8);

        if (score < bestScore) {
            bestScore = score;
            best = points;
        }
    });

    const points = best ?? simplifyPoints([start, end]);

    return {
        points,
        d: pointsToPath(points),
        mid: midpointOfPolyline(points),
        manual: false,
    };
}

/**
 * @param {unknown} value
 * @returns {Array<{ x: number, y: number }>}
 */
export function normalizeWaypoints(value) {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .filter((row) => row && Number.isFinite(Number(row.x)) && Number.isFinite(Number(row.y)))
        .slice(0, 8)
        .map((row) => ({
            x: Math.round(Number(row.x)),
            y: Math.round(Number(row.y)),
        }));
}

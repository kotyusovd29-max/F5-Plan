export function inferKindFromName(name) {
    const text = String(name || '').toLowerCase();

    if (/(успех|успешно|реализован|won)/i.test(text) && !/(не\s*реализ)/i.test(text)) {
        return 'win';
    }

    if (/(отказ|не\s*реализ|проигр|lost|закрыто\s*\.\s*отказ)/i.test(text)) {
        return 'lose';
    }

    return null;
}

export function resolveStageKind(stage) {
    if (!stage) {
        return 'normal';
    }

    if (stage.kind === 'win' || stage.kind === 'lose') {
        return stage.kind;
    }

    return inferKindFromName(stage.name) || 'normal';
}

export function resolveOutcomeStyle(outcome, fromStage, targetStage) {
    if (
        outcome?.mode === 'stay'
        || !targetStage
        || Number(targetStage.id) === Number(fromStage?.id)
    ) {
        return 'stay';
    }

    if (outcome?.mode === 'win' || outcome?.mode === 'lose') {
        return outcome.mode;
    }

    return resolveStageKind(targetStage) === 'normal'
        ? 'move'
        : resolveStageKind(targetStage);
}

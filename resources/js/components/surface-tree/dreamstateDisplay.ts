import type { SurfaceTreeNode } from './types';

export type DreamstateDisplayKind = {
    label: 'Email' | 'Code' | 'Evidence' | 'Context' | 'Unknown';
    slug: 'email' | 'code' | 'evidence' | 'context' | 'unknown';
};

export type DreamstateEvolution = {
    state: 'evolved' | 'awaiting' | 'failed';
    label: string;
};

const CODE_EXTENSIONS = /\.(php|js|jsx|ts|tsx|vue|py|rb|go|rs|java|kt|cs|cpp|cc|c|h|hpp|swift|sh|ps1|bat|sql|css|scss|html|json|ya?ml|toml)$/i;

const CONTEXT_EXTENSIONS = /\.(md|markdown|txt|rst|org|log|csv)$/i;

// Maps the technical kind/schema/source fields onto the handful of
// human display kinds the meaning cards lead with. Order matters: the
// most specific signals win, and anything unrecognised stays Unknown.
export function displayKindFor(fields: {
    kind?: string | null;
    schema?: string | null;
    sourceRef?: string | null;
    sourcePath?: string | null;
}): DreamstateDisplayKind {
    const kind = (fields.kind ?? '').toLowerCase();
    const schema = (fields.schema ?? '').toLowerCase();
    const source = `${fields.sourceRef ?? ''} ${fields.sourcePath ?? ''}`.toLowerCase().trim();

    if (kind.includes('email') || kind.includes('mail') || schema.includes('email') || /@[a-z0-9.-]+\.[a-z]{2,}/.test(source)) {
        return { label: 'Email', slug: 'email' };
    }

    if (kind.includes('code') || CODE_EXTENSIONS.test(source)) {
        return { label: 'Code', slug: 'code' };
    }

    if (
        kind.includes('evidence')
        || kind.includes('telemetry')
        || kind.includes('scene')
        || schema.includes('evidence')
        || schema.includes('camera')
    ) {
        return { label: 'Evidence', slug: 'evidence' };
    }

    if (
        kind.includes('context')
        || kind.includes('note')
        || kind === 'file'
        || kind.includes('document')
        || CONTEXT_EXTENSIONS.test(source)
    ) {
        return { label: 'Context', slug: 'context' };
    }

    return { label: 'Unknown', slug: 'unknown' };
}

// Reads the pipeline status as a human evolution statement. Unrecognised
// or absent statuses return null so the card can show its placeholder.
export function evolutionFor(status: string | null | undefined): DreamstateEvolution | null {
    const value = (status ?? '').toLowerCase();

    if (value === '') {
        return null;
    }

    if (/fail|error|reject/.test(value)) {
        return { state: 'failed', label: 'Dreamstate attempted to evolve this impression but failed' };
    }

    if (/sensemade|evolved|dreamed|transformed|connected|complete/.test(value)) {
        return { state: 'evolved', label: 'Evolved through Dreamstate' };
    }

    if (/observed|pending|queued|new|raw|waiting/.test(value)) {
        return { state: 'awaiting', label: 'Observed, not yet evolved' };
    }

    return null;
}

// Linked impressions may arrive as an array of ids or of objects; anything
// else is treated as no connections so unknown shapes render safely.
export function linkedImpressionsFrom(meta: Record<string, unknown>): { id: string | null; label: string }[] {
    const raw = meta.linked_impressions ?? meta.linkedImpressions ?? meta.links;

    if (!Array.isArray(raw)) {
        return [];
    }

    return raw.flatMap((entry) => {
        if (typeof entry === 'string' && entry !== '') {
            return [{ id: entry, label: entry }];
        }

        if (entry && typeof entry === 'object' && !Array.isArray(entry)) {
            const record = entry as Record<string, unknown>;
            const id = typeof record.impression_id === 'string' ? record.impression_id : null;
            const label = typeof record.label === 'string' && record.label !== '' ? record.label : id;

            return label ? [{ id, label }] : [];
        }

        return [];
    });
}

export function nodeToDreamstatePayload(node: SurfaceTreeNode): Record<string, unknown> {
    return {
        key: node.key,
        label: node.label,
        type: node.type,
        domain: node.domain,
        impression_id: node.impression_id ?? null,
        relation: node.relation ?? null,
        depth: node.depth,
        has_children: node.has_children,
        is_terminal_depth: node.is_terminal_depth,
        href: node.href ?? null,
        meta: node.meta,
    };
}

import type { SurfaceTreeNode } from './types';

export type DreamstateDisplayKind = {
    label: string;
    slug: string;
};

export type DreamstateEvolutionView = {
    stage: string;
    label: string;
    steps: string[];
    evolved: boolean;
};

// Human labels for the memory_kind values the Impressions feed publishes.
// The backend resolves memory_kind from impressions_dreamstate_feed; this
// map only translates an already-resolved kind into a UI label — it never
// guesses provenance from other technical fields or text shape.
const MEMORY_KIND_LABELS: Record<string, string> = {
    email: 'Email',
    living_document: 'Living document',
    canon_document: 'Canon document',
    manifest: 'Manifest',
    evidence: 'Evidence',
    context: 'Context',
    readme: 'Readme',
    code: 'Code',
    asset: 'Asset',
    binary: 'Binary',
};

export function displayKindFor(memoryKind: string | null | undefined): DreamstateDisplayKind {
    const kind = (memoryKind ?? '').trim().toLowerCase();
    const label = MEMORY_KIND_LABELS[kind];

    if (!label) {
        return { label: 'Unknown', slug: 'unknown' };
    }

    return { label, slug: kind };
}

// Reads the evolution lineage the backend resolved from the subconscious
// dreamstate tables. This is a typed reader only: the stage and plain-label
// steps were decided server-side, and nothing here derives progression from
// statuses or ids. Returns null when no lineage source was reachable so the
// card can show its placeholder.
export function evolutionViewFrom(meta: Record<string, unknown>): DreamstateEvolutionView | null {
    const stage = typeof meta.evolution_stage === 'string' && meta.evolution_stage !== ''
        ? meta.evolution_stage
        : null;

    if (!stage) {
        return null;
    }

    const label = typeof meta.evolution_label === 'string' && meta.evolution_label !== ''
        ? meta.evolution_label
        : 'Not evolved yet';

    const steps = Array.isArray(meta.evolution_steps)
        ? meta.evolution_steps.filter((step): step is string => typeof step === 'string' && step !== '')
        : [];

    return { stage, label, steps, evolved: stage !== 'observed' };
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

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

export type DreamstateContains = {
    available: boolean;
    contentKind: 'email' | 'code' | 'document' | 'unknown';
    title: string | null;
    sourceLabel: string | null;
    excerpt: string | null;
    items: string[];
    emailFrom: string | null;
    emailSubject: string | null;
    emailDate: string | null;
    emailExcerpt: string | null;
};

// Reads the contains_* fields the backend presenter normalised into node
// meta. This is a typed reader only — the shape of the contents was decided
// server-side, and nothing here inspects raw payloads or identifiers.
export function containsFrom(meta: Record<string, unknown>): DreamstateContains {
    const contentKind = meta.contains_content_kind;

    return {
        available: meta.contains_available === true,
        contentKind: contentKind === 'email' || contentKind === 'code' || contentKind === 'document' ? contentKind : 'unknown',
        title: metaString(meta, 'contains_title'),
        sourceLabel: metaString(meta, 'contains_source_label'),
        excerpt: metaString(meta, 'contains_excerpt'),
        items: Array.isArray(meta.contains_items)
            ? meta.contains_items.filter((item): item is string => typeof item === 'string' && item !== '')
            : [],
        emailFrom: metaString(meta, 'email_from'),
        emailSubject: metaString(meta, 'email_subject'),
        emailDate: metaString(meta, 'email_date'),
        emailExcerpt: metaString(meta, 'email_excerpt'),
    };
}

function metaString(meta: Record<string, unknown>, key: string): string | null {
    const value = meta[key];

    return typeof value === 'string' && value !== '' ? value : null;
}

export type DreamstateConnectionGroup = {
    kind: string;
    label: string;
    count: number;
};

export type DreamstateConnectionsView = {
    count: number;
    groups: DreamstateConnectionGroup[];
};

// Reads the grouped connection summaries the backend presenter resolved:
// human relationship labels with counts, never raw ids. Returns null when
// the resolver could not check this impression so the card can show a calm
// unavailable state; unknown shapes degrade to no groups.
export function connectionsFrom(meta: Record<string, unknown>): DreamstateConnectionsView | null {
    if (meta.connections_available !== true) {
        return null;
    }

    const groups = Array.isArray(meta.connections)
        ? meta.connections.flatMap((entry): DreamstateConnectionGroup[] => {
            if (!entry || typeof entry !== 'object' || Array.isArray(entry)) {
                return [];
            }

            const record = entry as Record<string, unknown>;
            const kind = typeof record.kind === 'string' && record.kind !== '' ? record.kind : null;
            const label = typeof record.label === 'string' && record.label !== '' ? record.label : null;
            const count = typeof record.count === 'number' && Number.isFinite(record.count) ? record.count : 0;

            return kind && label && count > 0 ? [{ kind, label, count }] : [];
        })
        : [];

    const count = typeof meta.connection_count === 'number' && Number.isFinite(meta.connection_count)
        ? meta.connection_count
        : groups.reduce((total, group) => total + group.count, 0);

    return { count, groups };
}

export type DreamstateLens = 'about' | 'evolution' | 'connections';

export type DreamstateLensGroup = {
    key: string;
    label: string;
    nodes: SurfaceTreeNode[];
};

const EVOLUTION_STAGE_ORDER = ['settled', 'returned', 'matured', 'candidate', 'selected', 'observed'];

const EVOLUTION_STAGE_LABELS: Record<string, string> = {
    settled: 'Settled by Sensemaker',
    returned: 'Returned from Dreamstate',
    matured: 'Matured',
    candidate: 'Became candidate',
    selected: 'Selected for Dreamstate',
    observed: 'Not evolved yet',
};

// The lens groupings below are typed transformers over fields the backend
// already resolved (memory_kind, evolution_stage, run/sender/source meta).
// They arrange meaning islands; they never re-derive provenance, lineage,
// or relationships client-side.

// About lens: impressions grouped by their resolved meaning kind, largest
// groups first, with unresolved impressions gathered at the end.
export function aboutLensGroups(nodes: SurfaceTreeNode[]): DreamstateLensGroup[] {
    const groups = new Map<string, DreamstateLensGroup>();

    for (const node of nodes) {
        const kind = displayKindFor(metaString(node.meta, 'memory_kind'));
        const label = kind.slug === 'unknown' ? 'Unknown / unclassified' : kind.label;
        const group = groups.get(kind.slug) ?? { key: `about:${kind.slug}`, label, nodes: [] };

        group.nodes.push(node);
        groups.set(kind.slug, group);
    }

    return [...groups.values()].sort((a, b) => {
        const aUnknown = a.label === 'Unknown / unclassified' ? 1 : 0;
        const bUnknown = b.label === 'Unknown / unclassified' ? 1 : 0;

        return aUnknown - bUnknown || b.nodes.length - a.nodes.length || a.label.localeCompare(b.label);
    });
}

// Evolution lens: impressions grouped by how far they moved through
// Dreamstate, most evolved first; impressions with no lineage answer come
// last.
export function evolutionLensGroups(nodes: SurfaceTreeNode[]): DreamstateLensGroup[] {
    const groups = new Map<string, DreamstateLensGroup>();

    for (const node of nodes) {
        const stage = metaString(node.meta, 'evolution_stage') ?? 'unresolved';
        const label = stage === 'unresolved'
            ? 'No evolution recorded yet'
            : metaString(node.meta, 'evolution_label') ?? EVOLUTION_STAGE_LABELS[stage] ?? stage;
        const group = groups.get(stage) ?? { key: `evolution:${stage}`, label, nodes: [] };

        group.nodes.push(node);
        groups.set(stage, group);
    }

    const rank = (stage: string) => {
        const index = EVOLUTION_STAGE_ORDER.indexOf(stage);

        return index === -1 ? EVOLUTION_STAGE_ORDER.length : index;
    };

    return [...groups.entries()]
        .sort(([a], [b]) => rank(a) - rank(b))
        .map(([, group]) => group);
}

// Connections lens: clusters of impressions that share a resolved
// relationship — the same Dreamstate run, the same sender, or the same
// source. Each impression joins its first matching cluster; loners gather
// at the end.
export function connectionLensGroups(nodes: SurfaceTreeNode[]): DreamstateLensGroup[] {
    const clusterings: { prefix: string; labelFor: (value: string) => string; valueFor: (node: SurfaceTreeNode) => string | null }[] = [
        { prefix: 'run', labelFor: () => 'Dreamed together', valueFor: (node) => metaString(node.meta, 'run_id') },
        { prefix: 'sender', labelFor: (value) => `From ${value}`, valueFor: (node) => metaString(node.meta, 'email_from') },
        { prefix: 'source', labelFor: (value) => `From ${value}`, valueFor: (node) => metaString(node.meta, 'contains_source_label') },
    ];

    const clusters: DreamstateLensGroup[] = [];
    const remaining = new Set(nodes);

    for (const clustering of clusterings) {
        const byValue = new Map<string, SurfaceTreeNode[]>();

        for (const node of remaining) {
            const value = clustering.valueFor(node);

            if (value !== null) {
                byValue.set(value, [...byValue.get(value) ?? [], node]);
            }
        }

        for (const [value, members] of byValue) {
            if (members.length < 2) {
                continue;
            }

            clusters.push({
                key: `cluster:${clustering.prefix}:${value}`,
                label: clustering.labelFor(value),
                nodes: members,
            });

            for (const member of members) {
                remaining.delete(member);
            }
        }
    }

    clusters.sort((a, b) => b.nodes.length - a.nodes.length || a.label.localeCompare(b.label));

    if (remaining.size > 0) {
        clusters.push({
            key: 'cluster:none',
            label: 'Not clustered yet',
            nodes: nodes.filter((node) => remaining.has(node)),
        });
    }

    return clusters;
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

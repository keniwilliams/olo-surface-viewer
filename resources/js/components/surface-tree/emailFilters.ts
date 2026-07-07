import type { EmailFilterMode, SurfaceTreeNode } from './types';

export const emailFilterChangedEventName = 'olo:surface-tree:email-filter';

export function nodeMatchesEmailFilter(node: SurfaceTreeNode, mode: EmailFilterMode): boolean {
    if (mode === 'all' || node.domain !== 'email' || node.relation !== 'email_listing') {
        return true;
    }

    if (mode === 'sensemade') {
        return hasSensemadeText(node);
    }

    if (mode === 'non_sensemade') {
        return !hasSensemadeText(node);
    }

    return true;
}

export function hasSensemadeText(node: SurfaceTreeNode): boolean {
    const value = node.meta.sensemade_text ?? node.meta.sensemadeText;

    return typeof value === 'string' && value.trim() !== '';
}

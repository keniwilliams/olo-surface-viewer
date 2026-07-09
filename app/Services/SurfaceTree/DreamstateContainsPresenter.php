<?php

namespace App\Services\SurfaceTree;

use App\Models\Impressions\ImpressionDreamstateFeed;
use App\Models\Sidecar\Email;
use Illuminate\Support\Str;

/**
 * Shapes what a dreamed impression contains into calm, human-readable meta
 * fields, so the cards can answer "what does this impression contain?"
 * without shipping full corpora or raw payloads in listing responses. The
 * shape follows the memory_kind the provenance step already established —
 * this layer never re-guesses provenance. Text extraction lives on the
 * ImpressionDreamstateFeed model; email details come from an already-looked-
 * up sidecar Email when the sidecar can vouch for the message.
 */
class DreamstateContainsPresenter
{
    private const EXCERPT_LIMIT = 280;

    private const DOCUMENT_KINDS = ['living_document', 'canon_document', 'manifest', 'evidence', 'context', 'readme'];

    /**
     * @return array<string, mixed> contains_* (and email_*) meta fields
     */
    public function containsMetaFor(?ImpressionDreamstateFeed $feedRow, ?string $memoryKind, ?Email $email = null): array
    {
        $contentKind = $this->contentKindFor($memoryKind);
        $sourceLabel = $feedRow?->sourceLabel();

        $fields = match ($contentKind) {
            'email' => $this->emailContains($feedRow, $email),
            'code' => $this->codeContains($feedRow),
            'document' => $this->documentContains($feedRow, $memoryKind),
            default => ['contains_excerpt' => $feedRow?->plainTextExcerpt(self::EXCERPT_LIMIT)],
        };

        $fields = [
            'contains_content_kind' => $contentKind,
            'contains_title' => $sourceLabel,
            'contains_source_label' => $sourceLabel,
            ...$fields,
        ];

        $fields['contains_available'] = $this->hasContent($fields);

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private function emailContains(?ImpressionDreamstateFeed $feedRow, ?Email $email): array
    {
        if ($email !== null) {
            return [
                'email_from' => $this->text($email->sender),
                'email_subject' => $this->text($email->subject),
                'email_date' => $this->text($email->received_at),
                'email_excerpt' => $this->text($email->body_preview)
                    ?? $this->limitedText($email->normalised_body),
            ];
        }

        // Without a sidecar match, common header lines at the head of the
        // corpus are the only safe email-shaped signal.
        return [
            'email_from' => $feedRow?->emailHeaderLine('from'),
            'email_subject' => $feedRow?->emailHeaderLine('subject'),
            'email_date' => $feedRow?->emailHeaderLine('date'),
            'email_excerpt' => $feedRow?->plainTextExcerpt(self::EXCERPT_LIMIT),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function codeContains(?ImpressionDreamstateFeed $feedRow): array
    {
        $symbols = $feedRow?->codeSymbols() ?? [];

        return [
            'contains_excerpt' => $feedRow?->plainTextExcerpt(self::EXCERPT_LIMIT),
            'contains_items' => $symbols === [] ? null : $symbols,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentContains(?ImpressionDreamstateFeed $feedRow, ?string $memoryKind): array
    {
        // Asset/binary corpora are not readable text, so only the source
        // label describes them.
        if (in_array($memoryKind, ['asset', 'binary'], true)) {
            return [];
        }

        $headings = $feedRow?->markdownHeadings() ?? [];

        return [
            'contains_excerpt' => $feedRow?->plainTextExcerpt(self::EXCERPT_LIMIT),
            'contains_items' => $headings === [] ? null : $headings,
        ];
    }

    private function contentKindFor(?string $memoryKind): string
    {
        return match (true) {
            $memoryKind === 'email' => 'email',
            $memoryKind === 'code' => 'code',
            in_array($memoryKind, [...self::DOCUMENT_KINDS, 'asset', 'binary'], true) => 'document',
            default => 'unknown',
        };
    }

    private function limitedText(?string $text): ?string
    {
        $text = $this->text($text);

        return $text === null ? null : Str::limit(trim(preg_replace('/\s+/', ' ', $text) ?? ''), self::EXCERPT_LIMIT);
    }

    private function text(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function hasContent(array $fields): bool
    {
        foreach ($fields as $key => $value) {
            if ($key !== 'contains_content_kind' && $value !== null && $value !== '' && $value !== []) {
                return true;
            }
        }

        return false;
    }
}

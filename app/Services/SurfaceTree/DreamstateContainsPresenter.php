<?php

namespace App\Services\SurfaceTree;

use App\Models\Sidecar\Email;
use App\Services\SurfaceTree\Concerns\ReadsEloquentSources;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

/**
 * Normalises what a dreamed impression contains into calm, human-readable
 * meta fields, so the cards can answer "what does this impression contain?"
 * without shipping full corpora or raw payloads in listing responses. The
 * shape of the contents follows the memory_kind the provenance resolver
 * already established — this layer never re-guesses provenance. Email-kind
 * impressions are enriched from the sidecar emails source when it can vouch
 * for the message; everything else summarises the head of the raw corpus.
 */
class DreamstateContainsPresenter
{
    use ReadsEloquentSources;

    private const CORPUS_HEAD_LENGTH = 4000;

    private const EXCERPT_LIMIT = 280;

    private const MAX_ITEMS = 6;

    private const EMAIL_COLUMNS = ['message_id', 'thread_id', 'sender', 'from_email', 'from_name', 'subject', 'body_preview', 'normalised_body', 'received_at'];

    private const DOCUMENT_KINDS = ['living_document', 'canon_document', 'manifest', 'evidence', 'context', 'readme'];

    /**
     * Batched sidecar lookup for the email-kind impressions in one listing.
     *
     * @param  list<string>  $references
     * @return array<string, object> sidecar rows keyed by reference variant
     */
    public function emailRowsByReference(array $references): array
    {
        $variants = [];

        foreach ($references as $reference) {
            foreach ($this->referenceVariants($reference) as $variant) {
                $variants[$variant] = true;
            }
        }

        if ($variants === []) {
            return [];
        }

        try {
            if (! $this->sourceExists(new Email)) {
                return [];
            }

            $columns = $this->columns(Email::class);

            if (! in_array('message_id', $columns, true)) {
                return [];
            }

            $rows = Email::query()
                ->select(array_values(array_intersect(self::EMAIL_COLUMNS, $columns)))
                ->whereIn('message_id', array_keys($variants))
                ->toBase()
                ->get();
        } catch (Throwable) {
            return [];
        }

        $byReference = [];

        foreach ($rows as $row) {
            foreach ($this->referenceVariants($this->stringValue($row->message_id ?? null)) as $variant) {
                $byReference[$variant] ??= $row;
            }
        }

        return $byReference;
    }

    /**
     * @return array<string, mixed> contains_* (and email_*) meta fields
     */
    public function containsMetaFor(mixed $row, ?string $memoryKind, ?object $email = null): array
    {
        $contentKind = $this->contentKindFor($memoryKind);
        $sourceLabel = $this->sourceLabelFor($row);
        $title = $this->stringValue($this->rowValue($row, 'label')) ?? $sourceLabel;

        $fields = match ($contentKind) {
            'email' => $this->emailContains($row, $email),
            'code' => $this->codeContains($row),
            'document' => $this->documentContains($row, $memoryKind),
            default => ['contains_excerpt' => $this->excerptFor($row)],
        };

        $fields = [
            'contains_content_kind' => $contentKind,
            'contains_title' => $title,
            'contains_source_label' => $sourceLabel,
            ...$fields,
        ];

        $fields['contains_available'] = $this->hasContent($fields);

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private function emailContains(mixed $row, ?object $email): array
    {
        if ($email !== null) {
            return [
                'email_from' => $this->stringValue($email->sender ?? null)
                    ?? $this->stringValue($email->from_name ?? null)
                    ?? $this->stringValue($email->from_email ?? null),
                'email_subject' => $this->stringValue($email->subject ?? null),
                'email_date' => $this->stringValue($email->received_at ?? null),
                'email_excerpt' => $this->stringValue($email->body_preview ?? null)
                    ?? $this->limitedPlainText($this->stringValue($email->normalised_body ?? null)),
            ];
        }

        // Without a sidecar match, common header lines at the head of the
        // corpus are the only safe email-shaped signal.
        $head = $this->corpusHead($row);

        return [
            'email_from' => $this->headerLine($head, 'from'),
            'email_subject' => $this->headerLine($head, 'subject'),
            'email_date' => $this->headerLine($head, 'date'),
            'email_excerpt' => $this->excerptFor($row),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function codeContains(mixed $row): array
    {
        $head = $this->corpusHead($row);
        $items = [];

        if ($head !== null && preg_match_all('/\b(?:class|interface|trait|enum|function|def)\s+([A-Za-z_][A-Za-z0-9_]*)/', $head, $matches)) {
            $items = array_slice(array_values(array_unique($matches[1])), 0, self::MAX_ITEMS);
        }

        return [
            'contains_excerpt' => $this->excerptFor($row),
            'contains_items' => $items === [] ? null : $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentContains(mixed $row, ?string $memoryKind): array
    {
        // Asset/binary corpora are not readable text, so only the source
        // label describes them.
        if (in_array($memoryKind, ['asset', 'binary'], true)) {
            return [];
        }

        $head = $this->corpusHead($row);
        $items = [];

        if ($head !== null && preg_match_all('/^#{1,4}\s+(.{2,80})$/m', $head, $matches)) {
            $items = array_slice(array_values(array_unique(array_map(trim(...), $matches[1]))), 0, self::MAX_ITEMS);
        }

        return [
            'contains_excerpt' => $this->excerptFor($row),
            'contains_items' => $items === [] ? null : $items,
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

    private function sourceLabelFor(mixed $row): ?string
    {
        $sourcePath = $this->stringValue($this->rowValue($row, 'source_path'));

        if ($sourcePath === null) {
            return null;
        }

        $basename = basename(str_replace('\\', '/', $sourcePath));

        return $basename === '' ? null : Str::limit($basename, 80, '');
    }

    private function excerptFor(mixed $row): ?string
    {
        return $this->limitedPlainText($this->corpusHead($row));
    }

    private function limitedPlainText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $plain = preg_replace('/[#*_>`|\[\]]+/', ' ', $text);
        $plain = trim(preg_replace('/\s+/', ' ', $plain ?? ''));

        return $plain === '' ? null : Str::limit($plain, self::EXCERPT_LIMIT);
    }

    private function corpusHead(mixed $row): ?string
    {
        $corpus = $this->rowValue($row, 'raw_corpus');

        if (! is_string($corpus) || trim($corpus) === '') {
            return null;
        }

        return substr($corpus, 0, self::CORPUS_HEAD_LENGTH);
    }

    private function headerLine(?string $head, string $header): ?string
    {
        if ($head === null || ! preg_match('/^'.$header.':[ \t]*(.+)$/mi', $head, $matches)) {
            return null;
        }

        return Str::limit(trim($matches[1]), 120);
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

    /**
     * @return list<string>
     */
    private function referenceVariants(?string $reference): array
    {
        $reference = $reference === null ? '' : trim($reference);

        if ($reference === '') {
            return [];
        }

        $variants = [$reference];

        if (str_starts_with($reference, 'outlook:')) {
            $variants[] = substr($reference, strlen('outlook:'));
        } else {
            $variants[] = 'outlook:'.$reference;
        }

        return array_values(array_unique($variants));
    }

    private function rowValue(mixed $row, string $key): mixed
    {
        if ($row instanceof Model) {
            return $row->getAttribute($key);
        }

        if (is_array($row)) {
            return $row[$key] ?? null;
        }

        return is_object($row) && property_exists($row, $key) ? $row->{$key} : null;
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null || $value === '' || is_resource($value)) {
            return null;
        }

        return (string) $value;
    }
}

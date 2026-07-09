<?php

namespace App\Models\Impressions;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Read-only projection over the impressions_dreamstate_feed Postgres view.
 *
 * @property string $impression_id
 * @property string|null $source_path
 * @property \Illuminate\Support\Carbon|null $observed_at
 * @property string|null $body_hash
 * @property string|null $process_status
 * @property string|null $sensemade_id
 * @property string $contract_version
 * @property string|null $final_observe
 * @property string|null $final_listen
 * @property string|null $final_orient
 * @property string|null $body
 * @property resource|string|null $raw_corpus// contains the raw data of the impression
 * @property string|null $raw_corpus_encoding
 * @property string|null $kind
 * @property string|null $parse_status
 * @property string|null $sync_status
 * @property string|null $io_pressure
 * @property string|null $pressure_basis
 * @property string|null $memory_kind
 * @property string|null $memory_text
 * @property array<string, mixed>|null $memory_payload // contains the data of the impression
 * @property string|null $memory_encoding
 * @property string|null $memory_source_ref
 */
class ImpressionDreamstateFeed extends Model
{
    use ReadOnlyModel;

    public const string CONTRACT_VERSION = 'impressions_dreamstate_feed_v1';

    protected $connection = 'impressions';

    protected $table = 'impressions_dreamstate_feed';

    protected $primaryKey = 'impression_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'observed_at' => 'datetime',
        'memory_payload' => 'array',
        'io_pressure' => 'decimal:5',
    ];

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The named feed columns the surface tree listing consumes.
     *
     * @var list<string>
     */
    public const array SURFACE_TREE_COLUMNS = [
        'impression_id',
        'source_path',
        'observed_at',
        'kind',
        'process_status',
        'memory_kind',
        'memory_source_ref',
        'raw_corpus',
        'raw_corpus_encoding',
    ];

    /**
     * Latest dreamed impressions for the dreamstate listing.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function latestForSurfaceTree(int $limit)
    {
        return self::query()
            ->select(self::SURFACE_TREE_COLUMNS)
            ->orderByDesc('observed_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Latest path-bearing impressions for the filesystem tree, filtered
     * before the row limit so old path-bearing rows are not crowded out by
     * newer pathless ones.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function latestPathBearingForSurfaceTree(int $limit)
    {
        return self::query()
            ->select(self::SURFACE_TREE_COLUMNS)
            ->whereNotNull('source_path')
            ->where('source_path', '<>', '')
            ->orderByDesc('observed_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Single-row lookup for the raw corpus endpoint.
     */
    public static function findForSurfaceTreeCorpus(string $impressionId): ?self
    {
        return self::query()
            ->select(['impression_id', 'raw_corpus', 'raw_corpus_encoding'])
            ->where('impression_id', $impressionId)
            ->first();
    }

    /**
     * The raw corpus as readable text. The impressions table stores
     * raw_corpus as bytea, which PDO can surface as a stream resource; utf8
     * corpora become text, anything else is base64-encoded.
     */
    public function decodedRawCorpus(): ?string
    {
        $raw = $this->raw_corpus;

        if (is_resource($raw)) {
            $raw = stream_get_contents($raw);
        }

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $encoding = $this->raw_corpus_encoding;

        if (($encoding === null || $encoding === 'utf8') && mb_check_encoding($raw, 'UTF-8')) {
            return $raw;
        }

        return base64_encode($raw);
    }

    /**
     * Head of the raw corpus, the only slice the meaning cards inspect.
     */
    public function corpusHead(int $length = 4000): ?string
    {
        $corpus = $this->raw_corpus;

        if (! is_string($corpus) || trim($corpus) === '') {
            return null;
        }

        return substr($corpus, 0, $length);
    }

    /**
     * Human file label derived from the source path — never a raw id.
     */
    public function sourceLabel(): ?string
    {
        $sourcePath = $this->source_path;

        if ($sourcePath === null || $sourcePath === '') {
            return null;
        }

        $basename = basename(str_replace('\\', '/', $sourcePath));

        return $basename === '' ? null : Str::limit($basename, 80, '');
    }

    /**
     * One-sentence plain-text summary of the corpus head, so cards can lead
     * with meaning instead of identifiers.
     */
    public function summarySentence(): ?string
    {
        $text = $this->plainText($this->corpusHead(2000));

        if ($text === null) {
            return null;
        }

        if (preg_match('/^.{20,}?[.!?](?=\s|$)/u', $text, $matches)) {
            $text = $matches[0];
        }

        return Str::limit($text, 200);
    }

    /**
     * Markdown-stripped excerpt of the corpus head.
     */
    public function plainTextExcerpt(int $limit = 280): ?string
    {
        $text = $this->plainText($this->corpusHead());

        return $text === null ? null : Str::limit($text, $limit);
    }

    /**
     * Markdown headings near the head of the corpus — the document's topics.
     *
     * @return list<string>
     */
    public function markdownHeadings(int $max = 6): array
    {
        $head = $this->corpusHead();

        if ($head === null || ! preg_match_all('/^#{1,4}\s+(.{2,80})$/m', $head, $matches)) {
            return [];
        }

        return array_slice(array_values(array_unique(array_map(trim(...), $matches[1]))), 0, $max);
    }

    /**
     * Declared classes/functions near the head of a code corpus.
     *
     * @return list<string>
     */
    public function codeSymbols(int $max = 6): array
    {
        $head = $this->corpusHead();

        if ($head === null || ! preg_match_all('/\b(?:class|interface|trait|enum|function|def)\s+([A-Za-z_][A-Za-z0-9_]*)/', $head, $matches)) {
            return [];
        }

        return array_slice(array_values(array_unique($matches[1])), 0, $max);
    }

    /**
     * A "Header: value" line near the head of the corpus, e.g. From/Subject
     * on an email-shaped corpus without a sidecar match.
     */
    public function emailHeaderLine(string $header): ?string
    {
        $head = $this->corpusHead();

        if ($head === null || ! preg_match('/^'.preg_quote($header, '/').':[ \t]*(.+)$/mi', $head, $matches)) {
            return null;
        }

        return Str::limit(trim($matches[1]), 120);
    }

    private function plainText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $plain = preg_replace('/[#*_>`|\[\]]+/', ' ', $text);
        $plain = trim(preg_replace('/\s+/', ' ', $plain ?? ''));

        return $plain === '' ? null : $plain;
    }
}

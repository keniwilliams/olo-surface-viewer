<?php

namespace App\Models\Sidecar;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only sidecar emails source. The sidecar schema is owned by another
 * organism, so the default id key is a documented fallback for read-only
 * lookups; rows are matched by message/source references, not by key.
 *
 * @property string $id
 * @property string|null $thread_id
 * @property string|null $conversation_id
 * @property string|null $sender
 * @property string|null $subject
 * @property string|null $body_preview
 * @property string|null $normalised_body
 * @property string|null $received_at
 */
class Email extends Model
{
    use ReadOnlyModel;

    protected $connection = 'sidecar';

    protected $table = 'emails';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    /**
     * The named email columns the surface tree consumes — the canonical
     * sidecar emails contract.
     *
     * @var list<string>
     */
    public const array SURFACE_TREE_COLUMNS = [
        'id',
        'thread_id',
        'conversation_id',
        'sender',
        'subject',
        'body_preview',
        'normalised_body',
        'received_at',
    ];

    /**
     * Label used for emails whose sender column is empty.
     */
    public const string UNKNOWN_SENDER = 'unknown sender';

    public function scopeForSurfaceTree(Builder $query): Builder
    {
        return $query
            ->select(self::SURFACE_TREE_COLUMNS)
            ->orderByDesc('received_at');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function latestForSurfaceTree(int $limit)
    {
        return self::query()->forSurfaceTree()->limit($limit)->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function forSurfaceTreeSender(string $sender, int $limit)
    {
        $query = self::query()->forSurfaceTree()->limit($limit);

        if ($sender === self::UNKNOWN_SENDER) {
            $query->where(function (Builder $query): void {
                $query->whereNull('sender')->orWhere('sender', '');
            });
        } else {
            $query->where('sender', $sender);
        }

        return $query->get();
    }

    /**
     * The named email columns the dreamstate cards consume, matched by
     * message reference.
     *
     * @param  list<string>  $references
     */
    public function scopeForMessageReferences(Builder $query, array $references): Builder
    {
        return $query
            ->select(['id', 'thread_id', 'sender', 'subject', 'body_preview', 'normalised_body', 'received_at'])
            ->whereIn('id', $references);
    }

    /**
     * The equivalent forms of a message reference: sidecar stores the plain
     * message id while the impressions organism stores an outlook-prefixed
     * source ref (or vice versa).
     *
     * @return list<string>
     */
    public static function referenceVariants(?string $reference): array
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
}

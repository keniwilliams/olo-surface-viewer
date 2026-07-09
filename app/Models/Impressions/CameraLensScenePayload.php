<?php

namespace App\Models\Impressions;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only projection over camera_lens_scene_payloads: gated scene payloads
 * the Camera Lens project publishes into Impressions. Dedicated table, not a
 * domain-tagged slice of the Dreamstate feed.
 *
 * @property string $housed_source_id
 * @property string|null $source_kind
 * @property string|null $schema
 * @property string|null $observed_at
 * @property string|null $created_at
 */
class CameraLensScenePayload extends Model
{
    use ReadOnlyModel;

    protected $connection = 'impressions';

    protected $table = 'camera_lens_scene_payloads';

    protected $primaryKey = 'housed_source_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * Latest scene payloads for the camera lens listing.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function latestForSurfaceTree(int $limit)
    {
        return self::query()
            ->select(['housed_source_id', 'source_kind', 'schema', 'observed_at', 'created_at'])
            ->orderByDesc('observed_at')
            ->limit($limit)
            ->get();
    }
}

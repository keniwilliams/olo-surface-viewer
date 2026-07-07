<?php

namespace App\Models\Impressions;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only projection over camera_lens_scene_payloads: gated scene payloads
 * the Camera Lens project publishes into Impressions. Dedicated table, not a
 * domain-tagged slice of the Dreamstate feed.
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
}

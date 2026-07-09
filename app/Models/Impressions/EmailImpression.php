<?php

namespace App\Models\Impressions;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Read-only email sensemaking projection from the impressions organism.
 *
 * @property string $impression_id
 * @property string|null $source_ref
 * @property array<string, mixed>|null $email
 * @property array<string, mixed>|null $state
 */
class EmailImpression extends Model
{
    use ReadOnlyModel;

    protected $connection = 'impressions';

    protected $table = 'email_impressions';

    protected $primaryKey = 'impression_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'email' => 'array',
        'state' => 'array',
    ];

    /**
     * @param  list<string>  $references
     */
    public function scopeForSourceReferences(Builder $query, array $references): Builder
    {
        return $query
            ->select(['impression_id', 'source_ref', 'email', 'state', 'updated_at'])
            ->whereIn('source_ref', $references)
            ->latest('updated_at');
    }

    public function sensemadeHumanSummary(): ?string
    {
        return $this->sensemadeResultField('human_summary');
    }

    public function sensemadeText(): ?string
    {
        return $this->sensemadeResultField('sensemade_text');
    }

    public function sensemadeWhyItMatters(): ?string
    {
        return $this->sensemadeResultField('why_it_matters');
    }

    public function sensemadeRecommendedNextStep(): ?string
    {
        return $this->sensemadeResultField('recommended_next_step');
    }

    /**
     * Every reference this impression can be matched by: its own source_ref
     * plus the reference fields inside its email payload.
     *
     * @return list<string>
     */
    public function referenceCandidates(): array
    {
        $candidates = [];

        foreach ([$this->source_ref, ...Arr::only($this->email ?? [], ['source_ref', 'message_id', 'id'])] as $candidate) {
            if ((is_string($candidate) && trim($candidate) !== '') || is_numeric($candidate)) {
                $candidates[trim((string) $candidate)] = true;
            }
        }

        return array_map(strval(...), array_keys($candidates));
    }

    private function sensemadeResultField(string $field): ?string
    {
        $value = Arr::get($this->state ?? [], 'sensemade_result.'.$field);

        return is_string($value) && $value !== '' ? $value : null;
    }
}

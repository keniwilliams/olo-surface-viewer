<?php

namespace App\Services\SurfaceTree;

use App\Models\Impressions\EmailImpression;
use App\Models\Sidecar\Email;
use App\Models\Sidecar\EmailMessage;
use App\Models\Sidecar\EmailSync;
use App\Services\SurfaceTree\Concerns\ReadsEloquentSources;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Read path for explicit sidecar email listings.
 * All database access goes through read-only Eloquent models.
 */
class EmailImpressionsFeed
{
    use ReadsEloquentSources;

    private const int ROOT_EMAIL_WINDOW = 100;

    private const int SENDER_CHILD_LIMIT = 50;

    private const array SIDECAR_SOURCES = [
        Email::class => [
            [
                'select' => ['id', 'message_id', 'thread_id', 'sender', 'subject', 'status', 'body_preview', 'normalised_body', 'human_summary', 'sensemade_text', 'why_it_matters', 'recommended_next_step', 'received_at'],
                'sender_columns' => ['sender'],
                'required_any' => ['message_id'],
            ],
            [
                'select' => ['id', 'from_email', 'from_name', 'subject', 'body_preview', 'normalised_body', 'received_at', 'thread_id'],
                'sender_columns' => ['from_email', 'from_name'],
                'required_any' => ['from_email', 'from_name'],
            ],
            [
                'select' => ['id', 'from_email', 'from_name', 'subject', 'normalised_body', 'received_at'],
                'sender_columns' => ['from_email', 'from_name'],
                'required_any' => ['from_email', 'from_name'],
            ],
            [
                'select' => ['id', 'subject', 'sender', 'body_preview', 'normalised_body', 'received_at', 'thread_id', 'conversation_id', 'updated_at'],
                'sender_columns' => ['sender'],
                'required_any' => ['id'],
            ],
            [
                'select' => ['id', 'sender', 'subject', 'normalised_body', 'received_at'],
                'sender_columns' => ['sender'],
                'required_any' => ['id'],
            ],
        ],
        EmailMessage::class => [
            [
                'select' => ['id', 'message_id', 'thread_id', 'from_email', 'from_name', 'subject', 'body_preview', 'normalised_body', 'received_at', 'updated_at'],
                'sender_columns' => ['from_email', 'from_name'],
                'required_any' => ['message_id', 'from_email', 'from_name'],
            ],
            [
                'select' => ['id', 'message_id', 'thread_id', 'sender', 'subject', 'body_preview', 'normalised_body', 'received_at', 'updated_at'],
                'sender_columns' => ['sender'],
                'required_any' => ['message_id'],
            ],
        ],
        EmailSync::class => [
            [
                'select' => ['id', 'email_id', 'thread_id', 'from_email', 'from_name', 'subject', 'body_preview', 'normalised_body', 'observed_at', 'updated_at'],
                'sender_columns' => ['from_email', 'from_name'],
                'required_any' => ['email_id', 'from_email', 'from_name'],
            ],
            [
                'select' => ['id', 'email_id', 'thread_id', 'sender', 'subject', 'body_preview', 'normalised_body', 'observed_at', 'updated_at'],
                'sender_columns' => ['sender'],
                'required_any' => ['email_id'],
            ],
        ],
    ];

    private const ORDER_COLUMNS = ['received_at', 'observed_at', 'sensemade_at', 'created_at', 'updated_at'];

    /**
     * @return list<object>
     */
    public function latestEmailRows(): array
    {
        return $this->emailRows(self::ROOT_EMAIL_WINDOW);
    }

    /**
     * @return list<object>
     */
    public function emailRowsForSender(string $sender): array
    {
        return $this->emailRows(self::SENDER_CHILD_LIMIT, $sender);
    }

    /**
     * @return list<object>
     */
    public function sidecarEmailRows(): array
    {
        return $this->emailRows(self::ROOT_EMAIL_WINDOW);
    }

    /**
     * @return list<object>
     */
    private function emailRows(int $limit, ?string $sender = null): array
    {
        foreach (self::SIDECAR_SOURCES as $modelClass => $profiles) {
            try {
                $model = new $modelClass;

                if (! $this->sourceExists($model)) {
                    continue;
                }
            } catch (Throwable) {
                continue;
            }

            foreach ($profiles as $profile) {
                try {
                    $query = $modelClass::query()
                        ->select($profile['select'])
                        ->limit($limit);

                    foreach (self::ORDER_COLUMNS as $orderColumn) {
                        if (! in_array($orderColumn, $profile['select'], true)) {
                            continue;
                        }

                        $query->orderByDesc($orderColumn);
                        break;
                    }

                    if ($sender !== null) {
                        $this->constrainSender($query, $profile['sender_columns'], $sender);
                    }

                    $rows = $query->toBase()->get()->all();

                    if ($rows !== [] && $this->profileMatchesRows($rows, $profile['required_any'])) {
                        return $this->withEmailSensemadeState($rows);
                    }
                } catch (Throwable) {
                    continue;
                }
            }
        }

        return [];
    }

    /**
     * @param  list<object>  $rows
     * @return list<object>
     */
    private function withEmailSensemadeState(array $rows): array
    {
        try {
            if (! $this->sourceExists(new EmailImpression)) {
                return $rows;
            }

            $references = [];

            foreach ($rows as $row) {
                foreach ($this->referenceVariants($this->sourceRefFor($row)) as $reference) {
                    $references[$reference] = true;
                }
            }

            if ($references === []) {
                return $rows;
            }

            $sensemadeByReference = $this->emailImpressionsByReference(array_keys($references));

            foreach ($rows as $row) {
                foreach ($this->referenceVariants($this->sourceRefFor($row)) as $reference) {
                    if (! isset($sensemadeByReference[$reference])) {
                        continue;
                    }

                    $this->applySensemadeState($row, $sensemadeByReference[$reference]);
                    break;
                }
            }
        } catch (Throwable) {
            return $rows;
        }

        return $rows;
    }

    /**
     * @param  list<string>  $references
     * @return array<string, object>
     */
    private function emailImpressionsByReference(array $references): array
    {
        $emailImpressions = EmailImpression::query()
            ->select(['impression_id', 'source_ref', 'email', 'state', 'updated_at'])
            ->whereIn('source_ref', $references)
            ->latest('updated_at')
            ->toBase()
            ->get();

        $indexed = [];

        foreach ($emailImpressions as $emailImpression) {
            foreach ($this->referenceVariants($this->stringValue($emailImpression->source_ref ?? null)) as $reference) {
                $indexed[$reference] ??= $emailImpression;
            }

            $email = $this->jsonObject($emailImpression->email ?? null);

            if (is_array($email)) {
                foreach (['source_ref', 'message_id', 'id'] as $key) {
                    foreach ($this->referenceVariants(Arr::get($email, $key)) as $reference) {
                        $indexed[$reference] ??= $emailImpression;
                    }
                }
            }
        }

        return $indexed;
    }

    private function applySensemadeState(object $row, object $emailImpression): void
    {
        $state = $this->jsonObject($emailImpression->state ?? null);

        if (! is_array($state)) {
            return;
        }

        $humanSummary = Arr::get($state, 'sensemade_result.human_summary');
        $sensemadeText = Arr::get($state, 'sensemade_result.sensemade_text');

        if (is_string($humanSummary) && $humanSummary !== '') {
            $row->human_summary = $humanSummary;
        }

        if (is_string($sensemadeText) && $sensemadeText !== '') {
            $row->sensemade_text = $sensemadeText;
        }

        $row->related_impression_id = $emailImpression->impression_id ?? null;
    }

    /**
     * @return list<string>
     */
    private function referenceVariants(mixed $value): array
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return [];
        }

        $reference = trim((string) $value);

        if ($reference === '') {
            return [];
        }

        $variants = [$reference];

        if (str_starts_with($reference, 'outlook:')) {
            $variants[] = substr($reference, strlen('outlook:'));
        } else {
            $variants[] = 'outlook:'.$reference;
        }

        return array_values(array_unique(array_filter($variants)));
    }

    private function sourceRefFor(object $row): ?string
    {
        foreach (['source_ref', 'source_reference', 'message_id', 'email_id', 'id'] as $column) {
            $value = $this->stringValue($row->{$column} ?? null);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $senderColumns
     */
    private function constrainSender(mixed $query, array $senderColumns, string $sender): void
    {
        if ($sender === 'unknown sender') {
            foreach ($senderColumns as $column) {
                $query->where(function ($query) use ($column): void {
                    $query->whereNull($column)->orWhere($column, '');
                });
            }

            return;
        }

        $query->where(function ($query) use ($senderColumns, $sender): void {
            foreach ($senderColumns as $column) {
                $query->orWhere($column, $sender);
            }
        });
    }

    /**
     * @param  list<object>  $rows
     * @param  list<string>  $requiredColumns
     */
    private function profileMatchesRows(array $rows, array $requiredColumns): bool
    {
        foreach ($rows as $row) {
            foreach ($requiredColumns as $column) {
                if ($this->stringValue($row->{$column} ?? null) !== null) {
                    return true;
                }
            }
        }

        return false;
    }

    private function jsonObject(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null || $value === '' || is_resource($value)) {
            return null;
        }

        return (string) $value;
    }
}

<?php

namespace Tests\Feature;

use App\Models\Concerns\ReadOnlyEloquentBuilder;
use App\Models\Impressions\EmailImpression;
use App\Models\Impressions\Impression;
use App\Models\Impressions\ImpressionDreamstateFeed;
use App\Models\Impressions\SensemadeImpression;
use App\Models\Sidecar\Email;
use App\Models\Sidecar\EmailMessage;
use App\Models\Sidecar\EmailSync;
use App\Models\Sidecar\ScheduledRunnerRun;
use App\Models\Subconscious\DreamstateRun;
use App\Models\SurfaceViewer\SchemaSnapshotRecord;
use RuntimeException;
use Tests\TestCase;

class ReadOnlyImpressionsModelsTest extends TestCase
{
    public function test_impression_dreamstate_feed_model_configuration(): void
    {
        $model = new ImpressionDreamstateFeed;

        $this->assertSame('impressions', $model->getConnectionName());
        $this->assertSame('impressions_dreamstate_feed', $model->getTable());
        $this->assertSame('impression_id', $model->getKeyName());
        $this->assertFalse($model->getIncrementing());
        $this->assertSame('string', $model->getKeyType());
        $this->assertFalse($model->usesTimestamps());
        $this->assertSame([], $model->getGuarded());
    }

    public function test_sensemade_impression_model_configuration(): void
    {
        $model = new SensemadeImpression;

        $this->assertSame('impressions', $model->getConnectionName());
        $this->assertSame('sensemade_impressions', $model->getTable());
        $this->assertFalse($model->usesTimestamps());
    }

    public function test_email_impression_model_configuration(): void
    {
        $model = new EmailImpression;

        $this->assertSame('impressions', $model->getConnectionName());
        $this->assertSame('email_impressions', $model->getTable());
        $this->assertSame('impression_id', $model->getKeyName());
        $this->assertFalse($model->getIncrementing());
        $this->assertSame('string', $model->getKeyType());
        $this->assertFalse($model->usesTimestamps());
        $this->assertSame('array', $model->getCasts()['email']);
        $this->assertSame('array', $model->getCasts()['state']);
    }

    public function test_impression_model_configuration(): void
    {
        $model = new Impression;

        $this->assertSame('impressions', $model->getConnectionName());
        $this->assertSame('impressions', $model->getTable());
        $this->assertSame('id', $model->getKeyName());
        $this->assertFalse($model->usesTimestamps());
    }

    public function test_sidecar_email_models_configuration(): void
    {
        foreach ([
            Email::class => 'emails',
            EmailMessage::class => 'email_messages',
            EmailSync::class => 'email_syncs',
        ] as $modelClass => $table) {
            $model = new $modelClass;

            $this->assertSame('sidecar', $model->getConnectionName());
            $this->assertSame($table, $model->getTable());
            $this->assertFalse($model->usesTimestamps());
        }
    }

    public function test_read_only_models_use_the_read_only_builder(): void
    {
        $this->assertInstanceOf(ReadOnlyEloquentBuilder::class, ImpressionDreamstateFeed::query());
        $this->assertInstanceOf(ReadOnlyEloquentBuilder::class, EmailImpression::query());
        $this->assertInstanceOf(ReadOnlyEloquentBuilder::class, SensemadeImpression::query());
        $this->assertInstanceOf(ReadOnlyEloquentBuilder::class, Impression::query());
        $this->assertInstanceOf(ReadOnlyEloquentBuilder::class, Email::query());
        $this->assertInstanceOf(ReadOnlyEloquentBuilder::class, EmailMessage::query());
        $this->assertInstanceOf(ReadOnlyEloquentBuilder::class, EmailSync::query());
    }

    public function test_instance_write_methods_throw(): void
    {
        $attempts = [
            'save' => fn () => (new ImpressionDreamstateFeed(['impression_id' => 'x']))->save(),
            'update' => fn () => (new ImpressionDreamstateFeed)->update(['kind' => 'x']),
            'delete' => fn () => (new ImpressionDreamstateFeed)->delete(),
            'forceDelete' => fn () => (new ImpressionDreamstateFeed)->forceDelete(),
            'push' => fn () => (new ImpressionDreamstateFeed)->push(),
            'destroy' => fn () => ImpressionDreamstateFeed::destroy('x'),
            'save on SensemadeImpression' => fn () => (new SensemadeImpression)->save(),
            'save on EmailImpression' => fn () => (new EmailImpression)->save(),
            'save on Impression' => fn () => (new Impression)->save(),
            'save on Email' => fn () => (new Email)->save(),
            'delete on Email' => fn () => (new Email)->delete(),
            'save on DreamstateRun' => fn () => (new DreamstateRun)->save(),
            'save on SchemaSnapshotRecord' => fn () => (new SchemaSnapshotRecord)->save(),
        ];

        foreach ($attempts as $method => $attempt) {
            try {
                $attempt();
                $this->fail("Expected {$method} to throw a read-only exception.");
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('read-only', $exception->getMessage());
            }
        }
    }

    public function test_builder_write_methods_throw(): void
    {
        $attempts = [
            'update' => fn () => ImpressionDreamstateFeed::query()->update(['kind' => 'x']),
            'delete' => fn () => ImpressionDreamstateFeed::query()->delete(),
            'forceDelete' => fn () => ImpressionDreamstateFeed::query()->forceDelete(),
            'increment' => fn () => ImpressionDreamstateFeed::query()->increment('io_pressure'),
            'decrement' => fn () => ImpressionDreamstateFeed::query()->decrement('io_pressure'),
            'insert' => fn () => ImpressionDreamstateFeed::query()->insert(['impression_id' => 'x']),
            'insertGetId' => fn () => ImpressionDreamstateFeed::query()->insertGetId(['impression_id' => 'x']),
            'insertOrIgnore' => fn () => ImpressionDreamstateFeed::query()->insertOrIgnore(['impression_id' => 'x']),
            'upsert' => fn () => ImpressionDreamstateFeed::query()->upsert([['impression_id' => 'x']], ['impression_id']),
            'updateOrInsert' => fn () => ImpressionDreamstateFeed::query()->updateOrInsert(['impression_id' => 'x']),
            'update on SensemadeImpression' => fn () => SensemadeImpression::query()->update(['kind' => 'x']),
            'update on EmailImpression' => fn () => EmailImpression::query()->update(['state' => []]),
            'delete on Impression' => fn () => Impression::query()->delete(),
            'update on Email' => fn () => Email::query()->update(['subject' => 'x']),
            'delete on Email' => fn () => Email::query()->delete(),
            'update on DreamstateRun' => fn () => DreamstateRun::query()->update(['status' => 'x']),
            'update on ScheduledRunnerRun' => fn () => ScheduledRunnerRun::query()->update(['status' => 'x']),
            'delete on SchemaSnapshotRecord' => fn () => SchemaSnapshotRecord::query()->delete(),
        ];

        foreach ($attempts as $method => $attempt) {
            try {
                $attempt();
                $this->fail("Expected builder {$method} to throw a read-only exception.");
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('read-only', $exception->getMessage());
            }
        }
    }
}

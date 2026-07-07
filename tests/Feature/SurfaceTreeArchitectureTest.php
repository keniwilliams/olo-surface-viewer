<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SurfaceTreeArchitectureTest extends TestCase
{
    public function test_filesystem_traverser_builds_nodes_only(): void
    {
        $traverser = File::get(app_path('Services/SurfaceTree/FilesystemTreeTraverser.php'));

        $this->assertStringNotContainsString('DB::connection', $traverser);
        $this->assertStringNotContainsString('Schema::', $traverser);
        $this->assertStringNotContainsString('->table(', $traverser);
        $this->assertStringNotContainsString('impressions_dreamstate_feed', $traverser);
        $this->assertStringNotContainsString('sensemade_impressions', $traverser);
        $this->assertStringNotContainsString('to_regclass', $traverser);
        $this->assertStringContainsString('ImpressionsFilesystemFeed', $traverser);
    }

    public function test_filesystem_feed_reads_through_read_only_eloquent_models(): void
    {
        $feed = File::get(app_path('Services/SurfaceTree/ImpressionsFilesystemFeed.php'));

        $this->assertStringContainsString('use App\Models\Impressions\ImpressionDreamstateFeed;', $feed);
        $this->assertStringContainsString('use App\Models\Impressions\SensemadeImpression;', $feed);
        $this->assertStringContainsString('use App\Models\Impressions\Impression;', $feed);
        $this->assertStringContainsString('ImpressionDreamstateFeed::class', $feed);
        $this->assertStringContainsString('$modelClass::query()', $feed);
        $this->assertStringNotContainsString('DB::connection', $feed);
        $this->assertStringNotContainsString('->table(', $feed);
    }

    public function test_email_traverser_builds_nodes_only(): void
    {
        $traverser = File::get(app_path('Services/SurfaceTree/EmailTreeTraverser.php'));

        $this->assertStringNotContainsString('DB::connection', $traverser);
        $this->assertStringNotContainsString('Schema::', $traverser);
        $this->assertStringNotContainsString('->table(', $traverser);
        $this->assertStringNotContainsString('sensemade_impressions', $traverser);
        $this->assertStringNotContainsString('email_messages', $traverser);
        $this->assertStringContainsString('EmailImpressionsFeed', $traverser);
    }

    public function test_email_feed_reads_through_read_only_eloquent_models(): void
    {
        $feed = File::get(app_path('Services/SurfaceTree/EmailImpressionsFeed.php'));

        $this->assertStringContainsString('use App\Models\Sidecar\Email;', $feed);
        $this->assertStringContainsString('use App\Models\Sidecar\EmailMessage;', $feed);
        $this->assertStringContainsString('use App\Models\Sidecar\EmailSync;', $feed);
        $this->assertStringContainsString('use App\Models\Impressions\EmailImpression;', $feed);
        $this->assertStringContainsString('$modelClass::query()', $feed);
        $this->assertStringContainsString('SENDER_CHILD_LIMIT = 50', $feed);
        $this->assertStringContainsString('->select($profile[', $feed);
        $this->assertStringContainsString('->toBase()', $feed);
        $this->assertStringNotContainsString('limit(250)', $feed);
        $this->assertStringNotContainsString('columns($modelClass)', $feed);
        $this->assertStringNotContainsString('DB::connection', $feed);
        $this->assertStringNotContainsString('->table(', $feed);
    }

    public function test_domain_impressions_traverser_builds_nodes_only(): void
    {
        $traverser = File::get(app_path('Services/SurfaceTree/DomainImpressionsTraverser.php'));

        $this->assertStringNotContainsString('DB::connection', $traverser);
        $this->assertStringNotContainsString('Schema::', $traverser);
        $this->assertStringNotContainsString('->table(', $traverser);
        $this->assertStringNotContainsString('impressions_dreamstate_feed', $traverser);
        $this->assertStringNotContainsString('sensemade_impressions', $traverser);
        $this->assertStringNotContainsString('Http::', $traverser);
        $this->assertStringContainsString('DomainImpressionsFeed', $traverser);
        $this->assertStringContainsString('CameraLensTelemetryFeed', $traverser);
    }

    public function test_camera_lens_telemetry_feed_reads_through_http_only(): void
    {
        $feed = File::get(app_path('Services/SurfaceTree/CameraLensTelemetryFeed.php'));

        $this->assertStringContainsString('use Illuminate\Support\Facades\Http;', $feed);
        $this->assertStringContainsString('Http::baseUrl(', $feed);
        $this->assertStringContainsString("config('services.loki.base_url')", $feed);
        $this->assertStringNotContainsString('DB::connection', $feed);
        $this->assertStringNotContainsString('->table(', $feed);
        $this->assertStringNotContainsString('Model', $feed);
    }

    public function test_domain_impressions_feed_reads_through_read_only_eloquent_models(): void
    {
        $feed = File::get(app_path('Services/SurfaceTree/DomainImpressionsFeed.php'));

        $this->assertStringContainsString('use App\Models\Impressions\CameraLensScenePayload;', $feed);
        $this->assertStringContainsString('use App\Models\Impressions\ImpressionDreamstateFeed;', $feed);
        $this->assertStringContainsString('use App\Models\Impressions\SensemadeImpression;', $feed);
        $this->assertStringContainsString('use App\Models\Impressions\Impression;', $feed);
        $this->assertStringContainsString('ImpressionDreamstateFeed::class', $feed);
        $this->assertStringContainsString('CameraLensScenePayload::class', $feed);
        $this->assertStringContainsString('$modelClass::query()', $feed);
        $this->assertStringNotContainsString('DB::connection', $feed);
        $this->assertStringNotContainsString('->table(', $feed);
    }

    public function test_surface_tree_models_are_read_only(): void
    {
        $models = [
            'Impressions/ImpressionDreamstateFeed',
            'Impressions/SensemadeImpression',
            'Impressions/Impression',
            'Impressions/CameraLensScenePayload',
            'Sidecar/Email',
            'Sidecar/EmailMessage',
            'Sidecar/EmailSync',
        ];

        foreach ($models as $model) {
            $source = File::get(app_path("Models/{$model}.php"));

            $this->assertStringContainsString('use ReadOnlyModel;', $source);
        }
    }
}

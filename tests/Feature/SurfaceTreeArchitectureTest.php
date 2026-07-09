<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SurfaceTreeArchitectureTest extends TestCase
{
    public function test_filesystem_traverser_reads_through_the_feed_model_only(): void
    {
        $traverser = File::get(app_path('Services/SurfaceTree/FilesystemTreeTraverser.php'));

        $this->assertFileDoesNotExist(app_path('Services/SurfaceTree/ImpressionsFilesystemFeed.php'));
        $this->assertStringContainsString('use App\Models\Impressions\ImpressionDreamstateFeed;', $traverser);
        $this->assertStringContainsString('ImpressionDreamstateFeed::latestPathBearingForSurfaceTree(', $traverser);
        $this->assertStringContainsString('ImpressionDreamstateFeed::findForSurfaceTreeCorpus(', $traverser);
        $this->assertStringContainsString('decodedRawCorpus()', $traverser);
        $this->assertStringNotContainsString('DB::connection', $traverser);
        $this->assertStringNotContainsString('Schema::', $traverser);
        $this->assertStringNotContainsString('->table(', $traverser);
        $this->assertStringNotContainsString('sensemade_impressions', $traverser);
        $this->assertStringNotContainsString('to_regclass', $traverser);
    }

    public function test_surface_tree_read_path_has_no_runtime_schema_probing(): void
    {
        // The feed services and their schema-archaeology trait are gone: the
        // model contracts define the expected shape, and nothing in the
        // surface tree read path probes tables or columns at runtime.
        $this->assertFileDoesNotExist(app_path('Services/SurfaceTree/DomainImpressionsFeed.php'));
        $this->assertFileDoesNotExist(app_path('Services/SurfaceTree/ImpressionsFilesystemFeed.php'));
        $this->assertFileDoesNotExist(app_path('Services/SurfaceTree/Concerns/ReadsEloquentSources.php'));

        foreach (File::allFiles(app_path('Services/SurfaceTree')) as $file) {
            $source = File::get($file->getPathname());

            $this->assertStringNotContainsString('sourceExists', $source, $file->getFilename());
            $this->assertStringNotContainsString('to_regclass', $source, $file->getFilename());
            $this->assertStringNotContainsString('getColumnListing', $source, $file->getFilename());
            $this->assertStringNotContainsString('ReadsEloquentSources', $source, $file->getFilename());
        }
    }

    public function test_impressions_feed_model_owns_the_surface_tree_read_contract(): void
    {
        $model = File::get(app_path('Models/Impressions/ImpressionDreamstateFeed.php'));

        $this->assertStringContainsString('SURFACE_TREE_COLUMNS', $model);
        $this->assertStringContainsString('public static function latestForSurfaceTree(', $model);
        $this->assertStringContainsString('public static function latestPathBearingForSurfaceTree(', $model);
        $this->assertStringContainsString('public static function findForSurfaceTreeCorpus(', $model);
        $this->assertStringContainsString('public function decodedRawCorpus(): ?string', $model);

        $sceneModel = File::get(app_path('Models/Impressions/CameraLensScenePayload.php'));

        $this->assertStringContainsString('public static function latestForSurfaceTree(', $sceneModel);
    }

    public function test_email_reads_go_through_the_canonical_email_models_only(): void
    {
        $traverser = File::get(app_path('Services/SurfaceTree/DomainImpressionsTraverser.php'));

        // The email service classes are gone: email reads go through the
        // Email and EmailImpression models with named columns, no runtime
        // source or column probing, orchestrated by the domain traverser.
        $this->assertFileDoesNotExist(app_path('Services/SurfaceTree/EmailImpressionsFeed.php'));
        $this->assertFileDoesNotExist(app_path('Services/SurfaceTree/EmailTreeTraverser.php'));
        $this->assertStringContainsString('use App\Models\Sidecar\Email;', $traverser);
        $this->assertStringContainsString('use App\Models\Impressions\EmailImpression;', $traverser);
        $this->assertStringContainsString('Email::latestForSurfaceTree(', $traverser);
        $this->assertStringContainsString('Email::forSurfaceTreeSender(', $traverser);
        $this->assertStringContainsString('EmailImpression::forSourceReferences(', $traverser);
        $this->assertStringNotContainsString('EmailImpressionsFeed', $traverser);
        $this->assertStringNotContainsString('EmailMessage', $traverser);
        $this->assertStringNotContainsString('EmailSync', $traverser);
        $this->assertStringNotContainsString('sourceExists', $traverser);
        $this->assertStringNotContainsString('email_messages', $traverser);
    }

    public function test_email_model_owns_the_surface_tree_read_contract(): void
    {
        $model = File::get(app_path('Models/Sidecar/Email.php'));

        $this->assertStringContainsString('SURFACE_TREE_COLUMNS', $model);
        $this->assertStringContainsString('public function scopeForSurfaceTree(', $model);
        $this->assertStringContainsString('public static function latestForSurfaceTree(', $model);
        $this->assertStringContainsString('public static function forSurfaceTreeSender(', $model);
        $this->assertStringContainsString('public function scopeForMessageReferences(', $model);
        $this->assertStringNotContainsString('SELECT *', $model);
    }

    public function test_domain_impressions_traverser_reads_through_models_and_telemetry_feed_only(): void
    {
        $traverser = File::get(app_path('Services/SurfaceTree/DomainImpressionsTraverser.php'));

        $this->assertStringNotContainsString('DB::connection', $traverser);
        $this->assertStringNotContainsString('Schema::', $traverser);
        $this->assertStringNotContainsString('->table(', $traverser);
        $this->assertStringNotContainsString('sensemade_impressions', $traverser);
        $this->assertStringNotContainsString('Http::', $traverser);
        $this->assertStringNotContainsString('DomainImpressionsFeed', $traverser);
        $this->assertStringContainsString('ImpressionDreamstateFeed::latestForSurfaceTree(', $traverser);
        $this->assertStringContainsString('CameraLensScenePayload::latestForSurfaceTree(', $traverser);
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

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

        $this->assertStringContainsString('use App\Models\Impressions\SensemadeImpression;', $feed);
        $this->assertStringContainsString('use App\Models\Sidecar\Email;', $feed);
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

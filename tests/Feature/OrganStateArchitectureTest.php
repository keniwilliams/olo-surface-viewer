<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OrganStateArchitectureTest extends TestCase
{
    public function test_summary_service_is_an_orchestrator_without_database_crawling(): void
    {
        $service = File::get(app_path('Services/OrganState/OrganStateSummaryService.php'));

        $this->assertStringNotContainsString('DB::connection', $service);
        $this->assertStringNotContainsString('information_schema', $service);
        $this->assertStringNotContainsString('->table(', $service);
        $this->assertStringNotContainsString('sqlite_master', $service);
        $this->assertStringNotContainsString('pragma', $service);
        $this->assertStringNotContainsString('Schema::', $service);
        $this->assertStringNotContainsString('getColumnListing', $service);

        foreach ([
            'BloodstreamActivitySource',
            'SubconsciousActivitySource',
            'ImpressionsActivitySource',
            'SidecarActivitySource',
            'SurfaceViewerActivitySource',
        ] as $sourceClass) {
            $this->assertStringContainsString($sourceClass, $service);
        }
    }

    public function test_activity_sources_declare_explicit_read_only_models(): void
    {
        $expectations = [
            'BloodstreamActivitySource' => ['App\Models\Bloodstream\SubjectMemory', 'App\Models\Bloodstream\ContractMemory'],
            'SubconsciousActivitySource' => ['App\Models\Subconscious\DreamstateRun', 'App\Models\Subconscious\DreamstateSensemakerRequest'],
            'ImpressionsActivitySource' => ['App\Models\Impressions\ImpressionDreamstateFeed', 'App\Models\Impressions\SensemadeImpression'],
            'SidecarActivitySource' => ['App\Models\Sidecar\Email', 'App\Models\Sidecar\ScheduledRunnerRun'],
            'SurfaceViewerActivitySource' => ['App\Models\SurfaceViewer\SchemaSnapshotRecord'],
        ];

        foreach ($expectations as $sourceClass => $models) {
            $source = File::get(app_path("Services/OrganState/ActivitySources/{$sourceClass}.php"));

            foreach ($models as $model) {
                $this->assertStringContainsString("use {$model};", $source);
            }

            $this->assertStringNotContainsString('DB::connection', $source);
            $this->assertStringNotContainsString('information_schema', $source);
            $this->assertStringNotContainsString('->table(', $source);
        }
    }

    public function test_model_activity_source_base_does_not_crawl_schemas(): void
    {
        $base = File::get(app_path('Services/OrganState/ActivitySources/ModelActivitySource.php'));

        $this->assertStringNotContainsString('DB::', $base);
        $this->assertStringNotContainsString('information_schema', $base);
        $this->assertStringNotContainsString('sqlite_master', $base);
        $this->assertStringNotContainsString('getTables', $base);
        $this->assertStringContainsString('$modelClass::query()->max($column)', $base);
    }

    public function test_organ_state_models_are_read_only(): void
    {
        $models = [
            'Subconscious/DreamstateRun',
            'Subconscious/DreamstateCandidate',
            'Subconscious/DreamstateReturnPacket',
            'Subconscious/DreamstateSensemakerRequest',
            'Sidecar/ScheduledRunnerRun',
            'Sidecar/AuditLogEntry',
            'Sidecar/SurfaceEvent',
            'SurfaceViewer/SchemaSnapshotRecord',
        ];

        foreach ($models as $model) {
            $source = File::get(app_path("Models/{$model}.php"));

            $this->assertStringContainsString('use ReadOnlyModel;', $source);
        }

        foreach (['Bloodstream/SubjectMemory', 'Bloodstream/ContractMemory'] as $model) {
            $source = File::get(app_path("Models/{$model}.php"));

            $this->assertStringContainsString('extends ReadOnlyBloodstreamModel', $source);
        }
    }
}

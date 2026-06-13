<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Feature\FeatureService;
use App\Modules\Feature\FeatureRepository;
use App\Modules\Feature\Feature;
use Tests\WebTestCase;

class FeatureServiceTest extends WebTestCase
{
    private FeatureService $featureService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->featureService = new FeatureService(new FeatureRepository());
    }

    public function testListAll(): void
    {
        Feature::create(['id' => 'test-feature', 'name' => 'Test Feature']);
        
        $result = $this->featureService->listAll();
        $this->assertCount(4, $result);
    }

    public function testCreateFeature(): void
    {
        $feature = $this->featureService->create([
            'id' => 'new-feat',
            'name' => 'New Feature',
            'description' => 'Desc'
        ]);
        /** @var Feature $feature */
        $this->assertEquals('New Feature', $feature->name);
    }

    public function testUpdateFeature(): void
    {
        Feature::create(['id' => 'f2', 'name' => 'F2']);
        $feature = $this->featureService->update('f2', ['name' => 'Updated']);
        /** @var Feature $feature */
        $this->assertEquals('Updated', $feature->name);
    }

    public function testCreateFeatureValidationFail(): void
    {
        $this->expectException(\App\Core\Exceptions\ValidationException::class);
        $this->featureService->create(['id' => 'f', 'name' => 'Ab']);
    }
}

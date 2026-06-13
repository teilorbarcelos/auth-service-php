<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Transformers\FeatureTransformer;
use App\Modules\Feature\Feature;
use Tests\WebTestCase;

class FeatureTransformerTest extends WebTestCase
{
    public function test_should_transform_feature_correctly(): void
    {
        $transformer = new FeatureTransformer();
        $feature = new Feature([
            'id' => 'test-feature',
            'name' => 'Test Feature',
            'description' => 'Test Description'
        ]);

        $result = $transformer->transform($feature);

        $this->assertEquals('test-feature', $result['id']);
        $this->assertEquals('Test Feature', $result['name']);
        $this->assertEquals('Test Description', $result['description']);
    }

    public function test_should_transform_collection_of_features(): void
    {
        $transformer = new FeatureTransformer();
        $features = [
            new Feature(['id' => 'f1', 'name' => 'F1']),
            new Feature(['id' => 'f2', 'name' => 'F2']),
        ];

        $result = $transformer->transformCollection($features);

        $this->assertCount(2, $result);
        $this->assertEquals('f1', $result[0]['id']);
        $this->assertEquals('f2', $result[1]['id']);
    }
}

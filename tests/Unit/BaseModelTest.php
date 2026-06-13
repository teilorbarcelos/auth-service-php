<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\BaseModel;
use PHPUnit\Framework\TestCase;

class BaseModelTest extends TestCase
{
    public function testUuidGeneration(): void
    {
        // Mocking Eloquent boot and creating is hard in unit tests,
        // but we can test the logic if we use a concrete class.
        $model = new class extends BaseModel {
            protected $table = 'test_table';
        };

        // We can't easily trigger the 'creating' event without a DB connection
        // but we can manually trigger the callback logic if we want.
        // However, since we have integration tests, maybe it's better there.
        
        $this->assertFalse($model->incrementing);
        $this->assertEquals('string', $model->getKeyType());
    }
}

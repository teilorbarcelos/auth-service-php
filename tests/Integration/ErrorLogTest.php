<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Audit\ErrorLog;
use Tests\WebTestCase;

class ErrorLogTest extends WebTestCase
{
    public function testAutoGenerateUuid(): void
    {
        $log = ErrorLog::create([
            'source' => 'TEST',
            'error_message' => 'Test Message',
            'error_data' => ['foo' => 'bar']
        ]);

        $this->assertNotNull($log->id);
        $this->assertEquals(36, strlen($log->id)); // UUID length
    }

    public function testWithPreExistingId(): void
    {
        $id = (string) \Illuminate\Support\Str::uuid();
        $log = ErrorLog::create([
            'id' => $id,
            'source' => 'TEST',
            'error_message' => 'Test Message'
        ]);

        $this->assertEquals($id, $log->id);
    }
}

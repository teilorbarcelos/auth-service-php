<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Transformers\RoleTransformer;
use App\Modules\Role\Role;
use App\Modules\Feature\Feature;
use Tests\WebTestCase;

class RoleTransformerTest extends WebTestCase
{
    private RoleTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new RoleTransformer();
    }

    public function testTransform(): void
    {
        Feature::create(['id' => 'f1', 'name' => 'Feature 1']);
        $role = Role::create(['id' => 'admin-transformer-test', 'name' => 'Admin']);
        
        \Illuminate\Database\Capsule\Manager::table('role_features')->insert([
            'id_role' => 'admin-transformer-test',
            'id_feature' => 'f1',
            'permissions' => json_encode(['view' => true, 'create' => false])
        ]);

        /** @var array{id: string, name: string, RoleFeature: array<int, array<string, bool>>} $result */
        $result = $this->transformer->transform($role);

        $this->assertEquals('admin-transformer-test', $result['id']);
        $this->assertEquals('Admin', $result['name']);
        $this->assertArrayHasKey('RoleFeature', $result);
        $this->assertCount(1, $result['RoleFeature']);
        $this->assertTrue($result['RoleFeature'][0]['view']);
        $this->assertFalse($result['RoleFeature'][0]['create']);
    }

    public function testTransformInvalidJson(): void
    {
        Feature::create(['id' => 'f-bad', 'name' => 'F Bad']);
        Role::create(['id' => 'role-bad', 'name' => 'Role Bad']);
        
        \Illuminate\Database\Capsule\Manager::table('role_features')->insert([
            'id_role' => 'role-bad',
            'id_feature' => 'f-bad',
            'permissions' => 'true' // Invalid JSON (decodes to boolean true, not array)
        ]);
        
        $role = Role::find('role-bad');
        $this->assertInstanceOf(Role::class, $role);
        
        /** @var array{RoleFeature: array<int, array<string, bool>>} $result */
        $result = $this->transformer->transform($role);
        
        $this->assertCount(1, $result['RoleFeature']);
        $this->assertFalse($result['RoleFeature'][0]['view']);
    }
}

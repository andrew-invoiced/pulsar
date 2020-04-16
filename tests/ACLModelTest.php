<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @see http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace Pulsar\Tests;

use AclObject;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Pulsar\ACLModel;
use Pulsar\Driver\DriverInterface;
use TestModel;
use TestModelNoPermission;

require_once 'tests/test_models.php';

class ACLModelTest extends MockeryTestCase
{
    public static $requester;

    public static function setUpBeforeClass()
    {
        $driver = Mockery::mock(DriverInterface::class);
        ACLModel::setDriver($driver);
    }

    public function testCan()
    {
        $acl = new AclObject();

        $this->assertFalse($acl->can('whatever', new TestModel()));
        $this->assertTrue($acl->can('do nothing', new TestModel(5)));
        $this->assertFalse($acl->can('do nothing', new TestModel()));
    }

    public function testCache()
    {
        $acl = new AclObject();

        for ($i = 0; $i < 10; ++$i) {
            $this->assertFalse($acl->can('whatever', new TestModel()));
        }
    }

    public function testGrantAll()
    {
        $acl = new AclObject();

        $acl->grantAllPermissions();

        $this->assertTrue($acl->can('whatever', new TestModel()));
    }

    public function testEnforcePermissions()
    {
        $acl = new AclObject();

        $this->assertEquals($acl, $acl->grantAllPermissions());
        $this->assertEquals($acl, $acl->enforcePermissions());

        $this->assertFalse($acl->can('whatever', new TestModel()));
    }

    public function testCreateNoPermission()
    {
        $newModel = new TestModelNoPermission();
        $this->assertFalse($newModel->create([]));
        $this->assertCount(1, $newModel->getErrors()->errors());
    }

    public function testSetNoPermission()
    {
        $model = new TestModelNoPermission(5);
        $this->assertFalse($model->set(['answer' => 42]));
        $this->assertCount(1, $model->getErrors()->errors());
    }

    public function testDeleteNoPermission()
    {
        $model = new TestModelNoPermission(5);
        $this->assertFalse($model->delete());
        $this->assertCount(1, $model->getErrors()->errors());
    }
}

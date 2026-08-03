<?php

namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;

abstract class TestCase extends CIUnitTestCase
{
    protected $migrate = true;
    protected $migrateOnce = true;
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}

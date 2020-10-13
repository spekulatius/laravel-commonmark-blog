<?php

namespace Spekulatius\LaravelCommonmarkBlog\Tests\Feature;

use Spekulatius\LaravelCommonmarkBlog\Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBasicTest()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}

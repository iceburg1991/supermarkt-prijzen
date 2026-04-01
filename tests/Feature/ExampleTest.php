<?php

namespace Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_supermarkets(): void
    {
        $response = $this->get(route('home'));

        $response->assertRedirect('/supermarkets');
    }
}

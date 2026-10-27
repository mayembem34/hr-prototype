<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ExampleTest extends TestCase {
    use RefreshDatabase;
    public function test_the_login_page_is_available(): void { $this->get('/login')->assertOk(); }
}

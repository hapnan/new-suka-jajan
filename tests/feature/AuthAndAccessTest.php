<?php

namespace Tests\Feature;

use App\Database\Seeds\InitialSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class AuthAndAccessTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = InitialSeeder::class;

    public function testRegisterAndLoginFlow(): void
    {
        // Guest views for login and register
        $this->get('/login')->assertOK();
        $this->get('/register')->assertOK();

        // Invalid registration validation
        $badReg = $this->post('/register', [
            'username'         => 'ab',
            'email'            => 'not-an-email',
            'password'         => '123',
            'password_confirm' => '456',
        ]);
        $badReg->assertRedirect();

        // Valid registration
        $reg = $this->post('/register', [
            'username'         => 'tester_baru',
            'email'            => 'tester@sukajajan.com',
            'password'         => 'secret123',
            'password_confirm' => 'secret123',
        ]);
        $reg->assertRedirectTo('/login');
        $this->seeInDatabase('users', ['email' => 'tester@sukajajan.com', 'role' => 'contributor']);

        // Invalid login attempt
        $badLogin = $this->post('/login', [
            'email'    => 'tester@sukajajan.com',
            'password' => 'wrongpassword',
        ]);
        $badLogin->assertRedirect();
        $badLogin->assertSessionHas('error', 'Email atau password salah.');

        // Valid contributor login
        $login = $this->post('/login', [
            'email'    => 'tester@sukajajan.com',
            'password' => 'secret123',
        ]);
        $login->assertRedirectTo('/');

        // Valid admin login redirects to /admin
        $adminLogin = $this->post('/login', [
            'email'    => 'admin@sukajajan.com',
            'password' => 'admin123',
        ]);
        $adminLogin->assertRedirectTo('/admin');

        // Logged-in users visiting /login or /register are redirected to /
        $loggedSession = [
            'user_id'   => 2,
            'username'  => 'user1',
            'email'     => 'user1@sukajajan.com',
            'role'      => 'contributor',
            'logged_in' => true,
        ];
        $this->withSession($loggedSession)->get('/login')->assertRedirectTo('/');
        $this->withSession($loggedSession)->get('/register')->assertRedirectTo('/');

        // Logout
        $this->withSession($loggedSession)->get('/logout')->assertRedirectTo('/');
    }

    public function testGuestIsRedirectedFromProtectedRoutes(): void
    {
        $this->get('/kuliner/submit')->assertRedirectTo('/login');
        $this->get('/favorites')->assertRedirectTo('/login');
        $this->get('/admin')->assertRedirectTo('/login');
    }

    public function testContributorCannotAccessAdminRoutes(): void
    {
        $session = [
            'user_id'   => 2,
            'username'  => 'user1',
            'email'     => 'user1@sukajajan.com',
            'role'      => 'contributor',
            'logged_in' => true,
        ];
        $res = $this->withSession($session)->get('/admin');
        $res->assertRedirectTo('/');
    }

    public function testPublicCatalogSearchDetailAnd404OnNonExistentSlug(): void
    {
        $this->get('/')->assertOK();
        $this->get('/kuliner')->assertOK(); // Default sort (latest)
        $this->get('/kuliner?category=warung-makan&rating=4&tag=halal&sort=rating')->assertOK();
        $this->get('/kuliner?lat=-6.9850&lng=110.4190&radius=5')->assertOK();
        $this->get('/search?q=Soto')->assertSee('Warung Soto Bangkong');

        // Detail page as guest and as logged-in contributor
        $this->get('/kuliner/warung-soto-bangkong')->assertOK();
        $session = [
            'user_id'   => 2,
            'username'  => 'user1',
            'email'     => 'user1@sukajajan.com',
            'role'      => 'contributor',
            'logged_in' => true,
        ];
        $this->withSession($session)->get('/kuliner/warung-soto-bangkong')->assertOK();

        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $this->get('/kuliner/slug-tidak-ada-999');
    }
}

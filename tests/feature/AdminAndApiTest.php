<?php

namespace Tests\Feature;

use App\Database\Seeds\InitialSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

final class AdminAndApiTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = InitialSeeder::class;

    private array $adminSession = [
        'user_id'   => 1,
        'username'  => 'admin',
        'email'     => 'admin@sukajajan.com',
        'role'      => 'admin',
        'logged_in' => true,
    ];

    public function testAdminDashboardAndKulinerCrudAndModeration(): void
    {
        // Dashboard
        $this->withSession($this->adminSession)->get('/admin')->assertOK();

        // Admin Kuliner index & create view
        $this->withSession($this->adminSession)->get('/admin/kuliners')->assertOK();
        $this->withSession($this->adminSession)->get('/admin/kuliners?status=pending')->assertOK();
        $this->withSession($this->adminSession)->get('/admin/kuliners/create')->assertOK();

        // Store validation failure
        $this->withSession($this->adminSession)->post('/admin/kuliners/store', [])->assertRedirect();

        // Store valid & duplicate slug
        $this->withSession($this->adminSession)->post('/admin/kuliners/store', [
            'nama'        => 'Warung Soto Bangkong',
            'category_id' => 1,
            'alamat'      => 'Jl. Pemuda No. 1, Semarang',
            'deskripsi'   => 'Ditambahkan oleh admin.',
            'latitude'    => '-6.9800',
            'longitude'   => '110.4100',
            'tags'        => [1, 2],
        ])->assertRedirectTo('/admin/kuliners');

        $row = $this->db->table('kuliners')->orderBy('id', 'DESC')->get()->getRowArray();
        $id = $row['id'];

        // Edit view (valid & not found)
        $this->withSession($this->adminSession)->get("/admin/kuliners/edit/{$id}")->assertOK();
        $this->withSession($this->adminSession)->get('/admin/kuliners/edit/99999')->assertRedirectTo('/admin/kuliners');

        // Update (validation failure & valid)
        $this->withSession($this->adminSession)->post("/admin/kuliners/update/{$id}", [])->assertRedirect();
        $this->withSession($this->adminSession)->post("/admin/kuliners/update/{$id}", [
            'nama'        => 'Soto Admin Updated',
            'category_id' => 1,
            'alamat'      => 'Jl. Pemuda No. 2, Semarang',
            'deskripsi'   => 'Deskripsi diperbarui.',
            'tags'        => [1],
        ])->assertRedirectTo('/admin/kuliners');
        $this->seeInDatabase('kuliners', ['id' => $id, 'nama' => 'Soto Admin Updated']);

        // Approve & Reject
        $this->withSession($this->adminSession)->post("/admin/kuliners/reject/{$id}")->assertRedirect();
        $this->seeInDatabase('kuliners', ['id' => $id, 'status' => 'rejected']);

        $this->withSession($this->adminSession)->post("/admin/kuliners/approve/{$id}")->assertRedirect();
        $this->seeInDatabase('kuliners', ['id' => $id, 'status' => 'approved']);

        // Delete
        $this->withSession($this->adminSession)->post("/admin/kuliners/delete/{$id}")->assertRedirectTo('/admin/kuliners');
        $this->dontSeeInDatabase('kuliners', ['id' => $id]);
    }

    public function testAdminCategoryTagAndReviewManagement(): void
    {
        // Categories CRUD
        $this->withSession($this->adminSession)->get('/admin/categories')->assertOK();
        $this->withSession($this->adminSession)->get('/admin/categories/create')->assertOK();
        $this->withSession($this->adminSession)->post('/admin/categories/store', ['nama' => ''])->assertRedirect();
        $this->withSession($this->adminSession)->post('/admin/categories/store', ['nama' => 'Dessert Semarang'])
             ->assertRedirectTo('/admin/categories');

        $cat = $this->db->table('categories')->where('slug', 'dessert-semarang')->get()->getRowArray();
        $catId = $cat['id'];

        $this->withSession($this->adminSession)->get("/admin/categories/edit/{$catId}")->assertOK();
        $this->withSession($this->adminSession)->get('/admin/categories/edit/99999')->assertRedirectTo('/admin/categories');
        $this->withSession($this->adminSession)->post("/admin/categories/update/{$catId}", ['nama' => ''])->assertRedirect();
        $this->withSession($this->adminSession)->post("/admin/categories/update/{$catId}", ['nama' => 'Dessert Baru'])
             ->assertRedirectTo('/admin/categories');
        $this->withSession($this->adminSession)->post("/admin/categories/delete/{$catId}")
             ->assertRedirectTo('/admin/categories');

        // Tags CRUD
        $this->withSession($this->adminSession)->get('/admin/tags')->assertOK();
        $this->withSession($this->adminSession)->get('/admin/tags/create')->assertOK();
        $this->withSession($this->adminSession)->post('/admin/tags/store', ['nama' => 'Buka 24 Jam'])
             ->assertRedirectTo('/admin/tags');

        $tag = $this->db->table('tags')->where('slug', 'buka-24-jam')->get()->getRowArray();
        $tagId = $tag['id'];

        $this->withSession($this->adminSession)->get("/admin/tags/edit/{$tagId}")->assertOK();
        $this->withSession($this->adminSession)->get('/admin/tags/edit/99999')->assertRedirectTo('/admin/tags');
        $this->withSession($this->adminSession)->post("/admin/tags/update/{$tagId}", ['nama' => '24 Jam Nonstop'])
             ->assertRedirectTo('/admin/tags');
        $this->withSession($this->adminSession)->post("/admin/tags/delete/{$tagId}")
             ->assertRedirectTo('/admin/tags');

        // Admin Review moderation
        $this->withSession($this->adminSession)->get('/admin/reviews')->assertOK();
        $review = $this->db->table('reviews')->get()->getRowArray();
        $this->withSession($this->adminSession)->post("/admin/reviews/delete/{$review['id']}")
             ->assertRedirectTo('/admin/reviews');
    }

    public function testPublicApiKulinerAndGeocodeEndpoints(): void
    {
        // Set foto_utama on kuliner 1 and insert a photo so Api\Kuliner maps both foto_utama and photos
        $this->db->table('kuliners')->where('id', 1)->update(['foto_utama' => 'soto_bangkong.jpg']);
        $this->db->table('photos')->insert([
            'kuliner_id' => 1,
            'user_id'    => 2,
            'filename'   => 'sample.jpg',
            'thumbnail'  => 'thumb_sample.jpg',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $resNearby = $this->get('/api/kuliner?lat=-6.9850&lng=110.4190&radius=5');
        $resNearby->assertOK();
        $resNearby->assertJSONFragment(['status' => 'success']);

        $resAll = $this->get('/api/kuliner');
        $resAll->assertOK();
        $resAll->assertJSONFragment(['status' => 'success']);

        // Geocode empty parameter validation
        $this->get('/geocode?q=')->assertJSONFragment(['error' => 'Alamat diperlukan']);
        $this->get('/geocode/reverse?lat=')->assertJSONFragment(['error' => 'Latitude dan Longitude diperlukan']);

        // Geocode search & reverse with mocked HTTP client (success + cache hit)
        cache()->clean();
        Services::injectMock('curlrequest', new class {
            public function get(string $url, array $options = []): object
            {
                if (str_contains($url, '/reverse?')) {
                    return new class {
                        public function getBody(): string
                        {
                            return json_encode(['display_name' => 'Lawang Sewu, Semarang']);
                        }
                    };
                }
                return new class {
                    public function getBody(): string
                    {
                        return json_encode([
                            ['display_name' => 'Lawang Sewu, Semarang', 'lat' => '-6.9839', 'lon' => '110.4104'],
                        ]);
                    }
                };
            }
        });

        $this->get('/geocode?q=Lawang+Sewu')->assertOK();
        $this->get('/geocode?q=Lawang+Sewu')->assertOK(); // Cache hit

        $this->get('/geocode/reverse?lat=-6.9839&lon=110.4104')->assertOK();
        $this->get('/geocode/reverse?lat=-6.9839&lon=110.4104')->assertOK(); // Cache hit

        // Geocode search & reverse empty API result
        Services::injectMock('curlrequest', new class {
            public function get(string $url, array $options = []): object
            {
                return new class {
                    public function getBody(): string
                    {
                        return json_encode([]);
                    }
                };
            }
        });

        $this->get('/geocode?q=TempatTidakAda123')->assertJSONFragment(['error' => 'Alamat tidak ditemukan']);
        $this->get('/geocode/reverse?lat=0.0001&lon=0.0001')->assertJSONFragment(['error' => 'Alamat tidak ditemukan']);

        // Geocode search & reverse network exception (500)
        Services::injectMock('curlrequest', new class {
            public function get(string $url, array $options = []): object
            {
                throw new \RuntimeException('Nominatim timeout');
            }
        });

        $this->get('/geocode?q=TriggerError500')->assertStatus(500);
        $this->get('/geocode/reverse?lat=1.2345&lon=6.7890')->assertStatus(500);
    }
}

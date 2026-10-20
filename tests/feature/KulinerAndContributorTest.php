<?php

namespace Tests\Feature;

use App\Database\Seeds\InitialSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class KulinerAndContributorTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = InitialSeeder::class;

    private array $contributorSession = [
        'user_id'   => 2,
        'username'  => 'user1',
        'email'     => 'user1@sukajajan.com',
        'role'      => 'contributor',
        'logged_in' => true,
    ];

    public function testContributorSubmitKulinerReportClosedAndPhotoValidation(): void
    {
        // Form view
        $this->withSession($this->contributorSession)->get('/kuliner/submit')->assertOK();

        // Validation error on empty submit
        $this->withSession($this->contributorSession)->post('/kuliner/store', [])->assertRedirect();

        // Valid submit
        $res = $this->withSession($this->contributorSession)->post('/kuliner/store', [
            'nama'        => 'Bakso Mercon Tembalang',
            'category_id' => 1,
            'alamat'      => 'Jl. Banjarsari No. 10, Semarang',
            'deskripsi'   => 'Bakso pedas level 5 dekat kampus.',
            'latitude'    => '-7.0500',
            'longitude'   => '110.4380',
            'tags'        => [1, 2],
        ]);
        $res->assertRedirectTo('/kuliner');
        $this->seeInDatabase('kuliners', [
            'nama'   => 'Bakso Mercon Tembalang',
            'status' => 'pending',
        ]);

        // Duplicate slug handling
        $dupSlug = $this->withSession($this->contributorSession)->post('/kuliner/store', [
            'nama'        => 'Warung Soto Bangkong',
            'category_id' => 1,
            'alamat'      => 'Jl. Brigjen Katamso, Semarang',
            'deskripsi'   => 'Cabang baru Warung Soto Bangkong.',
        ]);
        $dupSlug->assertRedirectTo('/kuliner');

        // Report closed: non-existent ID vs valid ID
        $this->withSession($this->contributorSession)->post('/kuliner/report-closed/99999')
             ->assertSessionHas('error', 'Tempat tidak ditemukan.');
        $this->withSession($this->contributorSession)->post('/kuliner/report-closed/1')
             ->assertSessionHas('success', 'Laporan tutup permanen telah dikirim untuk validasi admin.');
        $this->seeInDatabase('kuliners', ['id' => 1, 'is_closed' => 1]);

        // Upload photo: non-existent kuliner ID
        $this->withSession($this->contributorSession)->post('/kuliner/upload-photo/99999')
             ->assertSessionHas('error', 'Alamat tempat tidak ditemukan.');

        // Upload photo: missing file validation
        $this->withSession($this->contributorSession)->post('/kuliner/upload-photo/1')
             ->assertRedirect();

        // Upload photo: max 3 photos limit
        for ($i = 1; $i <= 3; $i++) {
            $this->db->table('photos')->insert([
                'kuliner_id' => 1,
                'user_id'    => 2,
                'filename'   => "foto_{$i}.jpg",
                'thumbnail'  => "thumb_foto_{$i}.jpg",
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $this->withSession($this->contributorSession)->post('/kuliner/upload-photo/1')
             ->assertSessionHas('error', 'Maksimal 3 foto per tempat.');
    }

    public function testContributorReviewSubmissionUpdateAndDuplicatePrevention(): void
    {
        // Validation error on store
        $this->withSession($this->contributorSession)->post('/review/store', [
            'kuliner_id' => 19,
            'rating'     => 6,
            'komentar'   => 'Pendek',
        ])->assertRedirect();

        // Kuliner 19 only has a review from user_id 1 in InitialSeeder
        $res = $this->withSession($this->contributorSession)->post('/review/store', [
            'kuliner_id' => 19,
            'rating'     => 5,
            'komentar'   => 'Jus alpukat paling kental dan segar di Semarang!',
        ]);
        $res->assertRedirect();
        $this->seeInDatabase('reviews', ['kuliner_id' => 19, 'user_id' => 2, 'rating' => 5]);

        $review = $this->db->table('reviews')->where(['kuliner_id' => 19, 'user_id' => 2])->get()->getRowArray();
        $reviewId = $review['id'];

        // Duplicate review by same user_id 2 must be blocked
        $dup = $this->withSession($this->contributorSession)->post('/review/store', [
            'kuliner_id' => 19,
            'rating'     => 1,
            'komentar'   => 'Mencoba kirim review kedua kalinya.',
        ]);
        $dup->assertSessionHas('error', 'Anda sudah memberikan review untuk tempat ini.');

        // Review update: unauthorized review ID
        $this->withSession($this->contributorSession)->post('/review/update/99999', [
            'rating'   => 4,
            'komentar' => 'Tidak boleh diedit karena bukan pemilik.',
        ])->assertSessionHas('error', 'Anda tidak bisa mengedit review ini (batas 24 jam).');

        // Review update: validation error
        $this->withSession($this->contributorSession)->post("/review/update/{$reviewId}", [
            'rating'   => 4,
            'komentar' => 'Pendek',
        ])->assertRedirect();

        // Review update: valid update within 24 hours
        $upd = $this->withSession($this->contributorSession)->post("/review/update/{$reviewId}", [
            'rating'   => 4,
            'komentar' => 'Diupdate: Jus alpukat tetap segar dan mantap!',
        ]);
        $upd->assertSessionHas('success', 'Review berhasil diupdate!');
        $this->seeInDatabase('reviews', ['id' => $reviewId, 'rating' => 4]);
    }

    public function testFavoriteIndexAndToggleController(): void
    {
        $this->withSession($this->contributorSession)->post('/favorite/toggle/1')
             ->assertSessionHas('success', 'Ditambahkan ke favorit!');
        $this->withSession($this->contributorSession)->get('/favorites')->assertOK();

        $this->withSession($this->contributorSession)->post('/favorite/toggle/1')
             ->assertSessionHas('success', 'Dihapus dari favorit.');
    }
}

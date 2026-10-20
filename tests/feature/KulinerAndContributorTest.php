<?php

namespace CodeIgniter\HTTP\Files {
    if (! function_exists('CodeIgniter\HTTP\Files\is_uploaded_file')) {
        function is_uploaded_file(string $filename): bool
        {
            return is_file($filename) && ! str_contains($filename, 'not_uploaded');
        }
    }

    if (! function_exists('CodeIgniter\HTTP\Files\move_uploaded_file')) {
        function move_uploaded_file(string $from, string $to): bool
        {
            return is_file($from) && copy($from, $to) && unlink($from);
        }
    }
}

namespace Tests\Feature {

    use App\Database\Seeds\InitialSeeder;
    use CodeIgniter\Test\CIUnitTestCase;
    use CodeIgniter\Test\DatabaseTestTrait;
    use CodeIgniter\Test\FeatureTestTrait;
    use Config\Services;

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

        private function setUploadFile(string $field, string $prefix = 'test_img_', string $clientName = 'photo.jpg'): string
        {
            $path = sys_get_temp_dir() . '/' . uniqid($prefix, false) . '.jpg';
            $img  = imagecreatetruecolor(20, 20);
            imagejpeg($img, $path, 85);

            $_FILES = [
                $field => [
                    'name'     => $clientName,
                    'type'     => 'image/jpeg',
                    'tmp_name' => $path,
                    'error'    => UPLOAD_ERR_OK,
                    'size'     => filesize($path),
                ],
            ];
            service('superglobals')->setFilesArray($_FILES);

            return $path;
        }

        private function clearUploadFiles(): void
        {
            $_FILES = [];
            service('superglobals')->setFilesArray([]);
        }

        private function mockFailingImageService(): object
        {
            return new class {
                public function withFile(string $path): self
                {
                    return $this;
                }

                public function resize(int $width, int $height, bool $maintainRatio = false, string $masterDim = 'auto'): self
                {
                    return $this;
                }

                public function save(string $targetPath): bool
                {
                    file_put_contents($targetPath, 'dummy');
                    if (str_contains($targetPath, 'thumbnails')) {
                        throw new \RuntimeException('Simulated GD failure on thumbnail');
                    }

                    return true;
                }
            };
        }

        protected function tearDown(): void
        {
            $this->clearUploadFiles();
            Services::resetSingle('image');
            parent::tearDown();
        }

        public function testContributorSubmitKulinerReportClosedAndPhotoUploads(): void
        {
            // Form view
            $this->withSession($this->contributorSession)->get('/kuliner/submit')->assertOK();

            // Validation error on empty submit
            $this->withSession($this->contributorSession)->post('/kuliner/store', [])->assertRedirect();

            // Valid submit with foto_utama upload (covers validation rules, move, and GD resize)
            $this->setUploadFile('foto_utama', 'kuliner_main_', 'bakso.jpg');
            $res = $this->withSession($this->contributorSession)->post('/kuliner/store', [
                'nama'        => 'Bakso Mercon Tembalang',
                'category_id' => 1,
                'alamat'      => 'Jl. Banjarsari No. 10, Semarang',
                'deskripsi'   => 'Bakso pedas level 5 dekat kampus.',
                'latitude'    => '-7.0500',
                'longitude'   => '110.4380',
                'tags'        => [1, 2],
            ]);
            $this->clearUploadFiles();
            $res->assertRedirectTo('/kuliner');
            $this->seeInDatabase('kuliners', [
                'nama'   => 'Bakso Mercon Tembalang',
                'status' => 'pending',
            ]);
            $inserted = $this->db->table('kuliners')->where('nama', 'Bakso Mercon Tembalang')->get()->getRowArray();
            if (! empty($inserted['foto_utama'])) {
                @unlink(FCPATH . 'uploads/kuliner/' . $inserted['foto_utama']);
                @unlink(FCPATH . 'uploads/thumbnails/' . $inserted['foto_utama']);
            }

            // Submit with duplicate slug + image processing failure (covers catch block & file cleanup)
            $this->setUploadFile('foto_utama', 'kuliner_fail_', 'fail.jpg');
            Services::injectMock('image', $this->mockFailingImageService());
            $failStore = $this->withSession($this->contributorSession)->post('/kuliner/store', [
                'nama'        => 'Warung Soto Bangkong',
                'category_id' => 1,
                'alamat'      => 'Jl. Brigjen Katamso, Semarang',
                'deskripsi'   => 'Cabang baru Warung Soto Bangkong.',
            ]);
            $this->clearUploadFiles();
            Services::resetSingle('image');
            $failStore->assertSessionHas('error', 'Gagal memproses gambar. Pastikan file berupa JPG, JPEG, PNG, atau WEBP yang valid.');

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

            // Upload photo: valid upload (covers move, GD resize, PhotoModel::insert, and success redirect)
            $this->setUploadFile('photo', 'photo_ok_', 'galeri1.jpg');
            $uploadOk = $this->withSession($this->contributorSession)->post('/kuliner/upload-photo/1');
            $this->clearUploadFiles();
            $uploadOk->assertSessionHas('success', 'Foto berhasil diupload.');
            $this->seeInDatabase('photos', ['kuliner_id' => 1, 'user_id' => 2]);
            $photoRow = $this->db->table('photos')->where(['kuliner_id' => 1, 'user_id' => 2])->get()->getRowArray();
            if (! empty($photoRow['filename'])) {
                @unlink(FCPATH . 'uploads/kuliner/' . $photoRow['filename']);
                @unlink(FCPATH . 'uploads/thumbnails/' . $photoRow['thumbnail']);
            }

            // Upload photo: image processing failure (covers catch block and unlinking uploadPath & thumbPath)
            $this->setUploadFile('photo', 'photo_fail_', 'galeri_fail.jpg');
            Services::injectMock('image', $this->mockFailingImageService());
            $uploadFail = $this->withSession($this->contributorSession)->post('/kuliner/upload-photo/1');
            $this->clearUploadFiles();
            Services::resetSingle('image');
            $uploadFail->assertSessionHas('error', 'Gagal memproses gambar. Pastikan file berupa JPG, JPEG, PNG, atau WEBP yang valid.');

            // Upload photo: isValid() false fallback ('Gagal upload foto.')
            $tmpNotUploaded = $this->setUploadFile('photo', 'not_uploaded_', 'galeri_invalid.jpg');
            $uploadFallback = $this->withSession($this->contributorSession)->post('/kuliner/upload-photo/1');
            $this->clearUploadFiles();
            @unlink($tmpNotUploaded);
            $uploadFallback->assertSessionHas('error', 'Gagal upload foto.');

            // Upload photo: max 3 photos limit (1 already inserted above, add 2 more)
            for ($i = 2; $i <= 3; $i++) {
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

            $review   = $this->db->table('reviews')->where(['kuliner_id' => 19, 'user_id' => 2])->get()->getRowArray();
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
}

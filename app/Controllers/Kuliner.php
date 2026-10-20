<?php

namespace App\Controllers;

use App\Models\KulinerModel;
use App\Models\CategoryModel;
use App\Models\TagModel;
use App\Models\KulinerTagModel;
use App\Models\ReviewModel;
use App\Models\PhotoModel;
use App\Models\FavoriteModel;
use CodeIgniter\Images\Handlers\BaseHandler;

class Kuliner extends BaseController
{
    public function index()
    {
        $kulinerModel = new KulinerModel();
        $categoryModel = new CategoryModel();
        $tagModel = new TagModel();

        $lat    = $this->request->getGet('lat');
        $lng    = $this->request->getGet('lng');
        $radius = $this->request->getGet('radius');

        $useNearby = $lat !== null && $lng !== null && is_numeric($lat) && is_numeric($lng);

        if ($useNearby) {
            $radiusKm = in_array($radius, ['1','2','5','10']) ? (float)$radius : 5;
            $builder = $kulinerModel->findNearby((float)$lat, (float)$lng, $radiusKm);
        } else {
            $builder = $kulinerModel->getWithCategory()->where('kuliners.status', 'approved');
        }

        if ($cat = $this->request->getGet('category')) {
            $builder->where('categories.slug', $cat);
        }
        if ($tag = $this->request->getGet('tag')) {
            $builder->join('kuliner_tags', 'kuliner_tags.kuliner_id = kuliners.id')
                    ->join('tags', 'tags.id = kuliner_tags.tag_id')
                    ->where('tags.slug', $tag);
        }
        if ($rating = $this->request->getGet('rating')) {
            $builder->where('avg_rating >=', (int)$rating);
        }

        if (!$useNearby) {
            $sort = $this->request->getGet('sort') ?? 'latest';
            if ($sort === 'rating') {
                $builder->orderBy('avg_rating', 'DESC');
            } else {
                $builder->orderBy('kuliners.created_at', 'DESC');
            }
        }

        $kuliners = $builder->paginate(12);
        $pager = $kulinerModel->pager;

        return view('kuliner/index', [
            'title'      => 'Jelajahi Kuliner',
            'kuliners'   => $kuliners,
            'pager'      => $pager,
            'categories' => $categoryModel->findAll(),
            'tags'       => $tagModel->findAll(),
        ]);
    }

    public function detail($slug)
    {
        $kulinerModel = new KulinerModel();
        $kuliner = $kulinerModel->getWithCategory()
            ->where('kuliners.slug', $slug)
            ->where('kuliners.status', 'approved')
            ->first();

        if (!$kuliner) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $kulinerTagModel = new KulinerTagModel();
        $reviewModel = new ReviewModel();
        $photoModel = new PhotoModel();

        $tags = $kulinerTagModel->getTagsByKuliner($kuliner['id']);
        $reviews = $reviewModel->getByKuliner($kuliner['id']);
        $photos = $photoModel->getByKuliner($kuliner['id']);

        $isFavorited = false;
        if (session()->get('logged_in')) {
            $favModel = new FavoriteModel();
            $isFavorited = $favModel->isFavorited(session()->get('user_id'), $kuliner['id']);
        }

        return view('kuliner/detail', [
            'title'       => $kuliner['nama'],
            'kuliner'     => $kuliner,
            'tags'        => $tags,
            'reviews'     => $reviews,
            'photos'      => $photos,
            'isFavorited' => $isFavorited,
        ]);
    }

    public function search()
    {
        $kulinerModel = new KulinerModel();
        $keyword = $this->request->getGet('q');
        $kuliners = [];

        if ($keyword) {
            $kuliners = $kulinerModel->getWithCategory()
                ->where('kuliners.status', 'approved')
                ->groupStart()
                    ->like('kuliners.nama', $keyword)
                    ->orLike('kuliners.alamat', $keyword)
                ->groupEnd()
                ->findAll();
        }

        return view('kuliner/search', [
            'title'    => 'Cari Kuliner',
            'keyword'  => $keyword,
            'kuliners' => $kuliners,
        ]);
    }

    public function submit()
    {
        $categoryModel = new CategoryModel();
        $tagModel = new TagModel();

        return view('kuliner/submit', [
            'title'      => 'Tambah Tempat Kuliner',
            'categories' => $categoryModel->findAll(),
            'tags'       => $tagModel->findAll(),
        ]);
    }

    public function store()
    {
        $rules = [
            'nama'        => 'required|max_length[200]',
            'alamat'      => 'required',
            'category_id' => 'required|integer',
            'deskripsi'   => 'required',
        ];

        $messages = [];
        $foto = $this->request->getFile('foto_utama');
        if ($foto && $foto->getError() !== UPLOAD_ERR_NO_FILE) {
            $rules['foto_utama'] = 'uploaded[foto_utama]|is_image[foto_utama]|mime_in[foto_utama,image/jpg,image/jpeg,image/png,image/webp]|ext_in[foto_utama,jpg,jpeg,png,webp]|max_size[foto_utama,2048]';
            $messages['foto_utama'] = [
                'is_image' => 'File foto utama harus berupa gambar raster yang valid (bukan SVG atau dokumen).',
                'mime_in'  => 'Format foto utama harus berupa JPG, JPEG, PNG, atau WEBP (format SVG tidak didukung).',
                'ext_in'   => 'Ekstensi file foto utama harus .jpg, .jpeg, .png, atau .webp.',
                'max_size' => 'Ukuran foto utama maksimal 2MB.',
            ];
        }

        if (!$this->validate($rules, $messages)) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->withInput();
        }

        $kulinerModel = new KulinerModel();
        $slug = url_title($this->request->getPost('nama'), '-', true);
        $existing = $kulinerModel->where('slug', $slug)->first();
        if ($existing) {
            $slug .= '-' . time();
        }

        $fotoUtama = null;
        if ($foto && $foto->isValid() && !$foto->hasMoved()) {
            $newName    = $foto->getRandomName();
            $uploadPath = FCPATH . 'uploads/kuliner/' . $newName;
            $thumbPath  = FCPATH . 'uploads/thumbnails/' . $newName;
            $foto->move(FCPATH . 'uploads/kuliner', $newName);

            try {
                $image = \Config\Services::image();
                $image->withFile($uploadPath)
                      ->resize(800, 600, true, 'width')
                      ->save($uploadPath);

                $image->withFile($uploadPath)
                      ->resize(300, 200, true, 'width')
                      ->save($thumbPath);

                $fotoUtama = $newName;
            } catch (\Throwable $e) {
                if (is_file($uploadPath)) {
                    @unlink($uploadPath);
                }
                if (is_file($thumbPath)) {
                    @unlink($thumbPath);
                }
                return redirect()->back()
                    ->with('error', 'Gagal memproses gambar. Pastikan file berupa JPG, JPEG, PNG, atau WEBP yang valid.')
                    ->withInput();
            }
        }

        $kulinerId = $kulinerModel->insert([
            'user_id'     => session()->get('user_id'),
            'category_id' => $this->request->getPost('category_id'),
            'nama'        => $this->request->getPost('nama'),
            'slug'        => $slug,
            'alamat'      => $this->request->getPost('alamat'),
            'deskripsi'   => $this->request->getPost('deskripsi'),
            'latitude'    => $this->request->getPost('latitude') ?: null,
            'longitude'   => $this->request->getPost('longitude') ?: null,
            'foto_utama'  => $fotoUtama,
            'status'      => 'pending',
        ]);

        $tags = $this->request->getPost('tags') ?? [];
        if (!empty($tags)) {
            $kulinerTagModel = new KulinerTagModel();
            $kulinerTagModel->syncTags($kulinerId, $tags);
        }

        return redirect()->to('/kuliner')->with('success', 'Tempat kuliner berhasil disubmit! Menunggu persetujuan admin.');
    }

    public function reportClosed($id)
    {
        $kulinerModel = new KulinerModel();
        $kuliner = $kulinerModel->find($id);
        if (!$kuliner) {
            return redirect()->back()->with('error', 'Tempat tidak ditemukan.');
        }
        $kulinerModel->update($id, ['is_closed' => 1]);
        return redirect()->back()->with('success', 'Laporan tutup permanen telah dikirim untuk validasi admin.');
    }

    public function uploadPhoto($kulinerId)
    {
        $photoModel = new PhotoModel();
        $kulinerModel = new KulinerModel();
        $kuliner = $kulinerModel->find($kulinerId);

        if (!$kuliner) {
            return redirect()->back()->with('error', 'Alamat tempat tidak ditemukan.');
        }

        if ($photoModel->countByKuliner($kulinerId) >= 3) {
            return redirect()->back()->with('error', 'Maksimal 3 foto per tempat.');
        }

        $rules = [
            'photo' => 'uploaded[photo]|is_image[photo]|mime_in[photo,image/jpg,image/jpeg,image/png,image/webp]|ext_in[photo,jpg,jpeg,png,webp]|max_size[photo,2048]',
        ];
        $messages = [
            'photo' => [
                'uploaded' => 'Pilih file foto terlebih dahulu.',
                'is_image' => 'File yang diupload harus berupa gambar raster yang valid (bukan SVG).',
                'mime_in'  => 'Format foto harus berupa JPG, JPEG, PNG, atau WEBP (format SVG tidak didukung).',
                'ext_in'   => 'Ekstensi file foto harus .jpg, .jpeg, .png, atau .webp.',
                'max_size' => 'Ukuran foto maksimal 2MB.',
            ],
        ];

        if (!$this->validate($rules, $messages)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $foto = $this->request->getFile('photo');
        if ($foto && $foto->isValid() && !$foto->hasMoved()) {
            $newName    = $foto->getRandomName();
            $thumbName  = 'thumb_' . $newName;
            $uploadPath = FCPATH . 'uploads/kuliner/' . $newName;
            $thumbPath  = FCPATH . 'uploads/thumbnails/' . $thumbName;
            $foto->move(FCPATH . 'uploads/kuliner', $newName);

            try {
                $image = \Config\Services::image();
                $image->withFile($uploadPath)
                      ->resize(800, 600, true, 'width')
                      ->save($uploadPath);

                $image->withFile($uploadPath)
                      ->resize(300, 200, true, 'width')
                      ->save($thumbPath);
            } catch (\Throwable $e) {
                if (is_file($uploadPath)) {
                    @unlink($uploadPath);
                }
                if (is_file($thumbPath)) {
                    @unlink($thumbPath);
                }
                return redirect()->back()->with('error', 'Gagal memproses gambar. Pastikan file berupa JPG, JPEG, PNG, atau WEBP yang valid.');
            }

            $photoModel->insert([
                'kuliner_id' => $kulinerId,
                'user_id'    => session()->get('user_id'),
                'filename'   => $newName,
                'thumbnail'  => $thumbName,
            ]);

            return redirect()->back()->with('success', 'Foto berhasil diupload.');
        }

        return redirect()->back()->with('error', 'Gagal upload foto.');
    }
}

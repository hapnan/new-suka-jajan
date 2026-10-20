<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\KulinerModel;
use App\Models\CategoryModel;
use App\Models\TagModel;
use App\Models\KulinerTagModel;

class Kuliner extends BaseController
{
    public function index()
    {
        $model = new KulinerModel();
        $status = $this->request->getGet('status') ?? 'all';

        $builder = $model->getWithCategory()
            ->join('users', 'users.id = kuliners.user_id')
            ->select('users.username as submitter');

        if ($status !== 'all') {
            $builder->where('kuliners.status', $status);
        }

        $kuliners = $builder->orderBy('kuliners.created_at', 'DESC')->findAll();

        return view('admin/kuliners/index', [
            'title'    => 'Kelola Kuliner',
            'kuliners' => $kuliners,
            'status'   => $status,
        ]);
    }

    public function create()
    {
        $categoryModel = new CategoryModel();
        $tagModel = new TagModel();

        return view('admin/kuliners/form', [
            'title'      => 'Tambah Kuliner',
            'kuliner'    => null,
            'categories' => $categoryModel->findAll(),
            'tags'       => $tagModel->findAll(),
            'selectedTags' => [],
        ]);
    }

    public function store()
    {
        $rules = [
            'nama'        => 'required|max_length[200]',
            'alamat'      => 'required',
            'category_id' => 'required|integer',
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

        $model = new KulinerModel();
        $nama = $this->request->getPost('nama');
        $slug = url_title($nama, '-', true);

        if ($model->where('slug', $slug)->first()) {
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

        $kulinerId = $model->insert([
            'user_id'     => session()->get('user_id'),
            'category_id' => $this->request->getPost('category_id'),
            'nama'        => $nama,
            'slug'        => $slug,
            'alamat'      => $this->request->getPost('alamat'),
            'deskripsi'   => $this->request->getPost('deskripsi'),
            'latitude'    => $this->request->getPost('latitude') ?: null,
            'longitude'   => $this->request->getPost('longitude') ?: null,
            'foto_utama'  => $fotoUtama,
            'status'      => 'approved',
        ]);

        $tags = $this->request->getPost('tags') ?? [];
        if (!empty($tags)) {
            $kulinerTagModel = new KulinerTagModel();
            $kulinerTagModel->syncTags($kulinerId, $tags);
        }

        return redirect()->to('/admin/kuliners')->with('success', 'Kuliner berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $model = new KulinerModel();
        $kuliner = $model->find($id);
        if (!$kuliner) {
            return redirect()->to('/admin/kuliners')->with('error', 'Kuliner tidak ditemukan.');
        }

        $categoryModel = new CategoryModel();
        $tagModel = new TagModel();
        $kulinerTagModel = new KulinerTagModel();

        $selectedTags = array_column($kulinerTagModel->where('kuliner_id', $id)->findAll(), 'tag_id');

        return view('admin/kuliners/form', [
            'title'        => 'Edit Kuliner',
            'kuliner'      => $kuliner,
            'categories'   => $categoryModel->findAll(),
            'tags'         => $tagModel->findAll(),
            'selectedTags' => $selectedTags,
        ]);
    }

    public function update($id)
    {
        $rules = [
            'nama'        => 'required|max_length[200]',
            'alamat'      => 'required',
            'category_id' => 'required|integer',
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

        $model = new KulinerModel();
        $data = [
            'category_id' => $this->request->getPost('category_id'),
            'nama'        => $this->request->getPost('nama'),
            'alamat'      => $this->request->getPost('alamat'),
            'deskripsi'   => $this->request->getPost('deskripsi'),
            'latitude'    => $this->request->getPost('latitude') ?: null,
            'longitude'   => $this->request->getPost('longitude') ?: null,
        ];

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
                $data['foto_utama'] = $newName;
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

        $model->update($id, $data);

        $tags = $this->request->getPost('tags') ?? [];
        $kulinerTagModel = new KulinerTagModel();
        $kulinerTagModel->syncTags($id, $tags);

        return redirect()->to('/admin/kuliners')->with('success', 'Kuliner berhasil diupdate.');
    }

    public function delete($id)
    {
        $model = new KulinerModel();
        $model->delete($id);
        return redirect()->to('/admin/kuliners')->with('success', 'Kuliner berhasil dihapus.');
    }

    public function approve($id)
    {
        $model = new KulinerModel();
        $model->update($id, ['status' => 'approved']);
        return redirect()->back()->with('success', 'Kuliner berhasil diapprove.');
    }

    public function reject($id)
    {
        $model = new KulinerModel();
        $model->update($id, ['status' => 'rejected']);
        return redirect()->back()->with('success', 'Kuliner berhasil direject.');
    }
}

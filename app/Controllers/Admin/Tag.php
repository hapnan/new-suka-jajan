<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\TagModel;

class Tag extends BaseController
{
    public function index()
    {
        $model = new TagModel();
        return view('admin/tags/index', [
            'title' => 'Kelola Tags',
            'tags'  => $model->findAll(),
        ]);
    }

    public function create()
    {
        return view('admin/tags/form', [
            'title' => 'Tambah Tag',
            'tag'   => null,
        ]);
    }

    public function store()
    {
        $model = new TagModel();
        $nama = $this->request->getPost('nama');

        $model->insert([
            'nama' => $nama,
            'slug' => url_title((string) $nama, '-', true),
        ]);

        return redirect()->to('/admin/tags')->with('success', 'Tag berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $model = new TagModel();
        $tag = $model->find($id);
        if (!$tag) {
            return redirect()->to('/admin/tags')->with('error', 'Tag tidak ditemukan.');
        }

        return view('admin/tags/form', [
            'title' => 'Edit Tag',
            'tag'   => $tag,
        ]);
    }

    public function update($id)
    {
        $model = new TagModel();
        $nama = $this->request->getPost('nama');

        $model->update($id, [
            'nama' => $nama,
            'slug' => url_title((string) $nama, '-', true),
        ]);

        return redirect()->to('/admin/tags')->with('success', 'Tag berhasil diupdate.');
    }

    public function delete($id)
    {
        $model = new TagModel();
        $model->delete($id);
        return redirect()->to('/admin/tags')->with('success', 'Tag berhasil dihapus.');
    }
}

<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CategoryModel;

class Category extends BaseController
{
    public function index()
    {
        $model = new CategoryModel();
        return view('admin/categories/index', [
            'title'      => 'Kelola Kategori',
            'categories' => $model->findAll(),
        ]);
    }

    public function create()
    {
        return view('admin/categories/form', [
            'title'    => 'Tambah Kategori',
            'category' => null,
        ]);
    }

    public function store()
    {
        $model = new CategoryModel();
        $nama = $this->request->getPost('nama');

        // $model->insert([
        //     'nama' => $nama,
        //     'slug' => url_title($nama, '-', true),
        // ]);
        if (!$model->insert([
            'nama' => $nama,
            'slug' => url_title((string) $nama, '-', true),
        ])) {
            return redirect()->back()->with('errors', $model->errors())->withInput();
        }

        return redirect()->to('/admin/categories')->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $model = new CategoryModel();
        $category = $model->find($id);
        if (!$category) {
            return redirect()->to('/admin/categories')->with('error', 'Kategori tidak ditemukan.');
        }

        return view('admin/categories/form', [
            'title'    => 'Edit Kategori',
            'category' => $category,
        ]);
    }

    public function update($id)
    {
        $model = new CategoryModel();
        $nama = $this->request->getPost('nama');

        // $model->update($id, [
        //     'nama' => $nama,
        //     'slug' => url_title($nama, '-', true),
        // ]);

        if (!$model->update($id, [
            'id'   => $id,
            'nama' => $nama,
            'slug' => url_title((string) $nama, '-', true),
        ])) {
            return redirect()->back()->with('errors', $model->errors())->withInput();
        }

        return redirect()->to('/admin/categories')->with('success', 'Kategori berhasil diupdate.');
    }

    public function delete($id)
    {
        $model = new CategoryModel();
        $model->delete($id);
        return redirect()->to('/admin/categories')->with('success', 'Kategori berhasil dihapus.');
    }
}

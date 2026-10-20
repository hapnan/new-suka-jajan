<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\KulinerModel;
use CodeIgniter\API\ResponseTrait;

class Kuliner extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        $kulinerModel = new KulinerModel();
        $photoModel = new \App\Models\PhotoModel();
        $lat = $this->request->getGet('lat');
        $lng = $this->request->getGet('lng');
        $radius = $this->request->getGet('radius') ?? 5;

        if ($lat && $lng) {
            $kuliners = $kulinerModel->findNearby((float)$lat, (float)$lng, (float)$radius)->findAll();
        } else {
            $kuliners = $kulinerModel->getWithCategory()
                ->where('kuliners.status', 'approved')
                ->orderBy('avg_rating', 'DESC')
                ->findAll(50);
        }

        $baseUrl = rtrim(base_url(), '/');

        foreach ($kuliners as &$k) {
            $k['foto_url'] = $k['foto_utama'] ? $baseUrl . '/uploads/kuliner/' . $k['foto_utama'] : null;
            $k['thumbnail_url'] = $k['foto_utama'] ? $baseUrl . '/uploads/thumbnails/' . $k['foto_utama'] : null;

            $photos = $photoModel->where('kuliner_id', $k['id'])->findAll();
            $k['photos'] = array_map(function ($p) use ($baseUrl) {
                return [
                    'foto_url'      => $baseUrl . '/uploads/kuliner/' . $p['filename'],
                    'thumbnail_url' => $baseUrl . '/uploads/thumbnails/' . $p['thumbnail'],
                ];
            }, $photos);
        }

        return $this->respond([
            'status' => 'success',
            'total'  => count($kuliners),
            'data'   => $kuliners,
        ]);
    }
}

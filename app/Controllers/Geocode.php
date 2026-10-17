<?php

namespace App\Controllers;

class Geocode extends BaseController
{
    public function search()
    {
        $alamat = trim($this->request->getGet('q'));

        if (empty($alamat)) {
            return $this->response->setJSON([
                'error' => 'Alamat diperlukan'
            ]);
        }

        $cache = cache();
        $cacheKey = 'geocode_' . md5(strtolower($alamat));

        if ($cachedData = $cache->get($cacheKey)) {
            return $this->response->setJSON($cachedData);
        }

        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'q'      => $alamat . ', Semarang, Indonesia',
            'format' => 'json',
            'limit'  => 5,
        ]);

        try {

            $client = \Config\Services::curlrequest();

            $response = $client->get($url, [
                'headers' => [
                    'User-Agent' => 'SukaJajan/1.0 (contact@sukajajan.com)',
                ],
            ]);

            $results = json_decode($response->getBody(), true);

            if (empty($results)) {
                return $this->response->setJSON([
                    'error' => 'Alamat tidak ditemukan'
                ]);
            }

            $data = [];

            foreach ($results as $r) {
                $data[] = [
                    'display_name' => $r['display_name'],
                    'lat'          => $r['lat'],
                    'lon'          => $r['lon'],
                ];
            }

            $cache->save($cacheKey, $data, 3600);
            //dd($data);
            return $this->response->setJSON($data);

        } catch (\Throwable $e) {

            return $this->response->setStatusCode(500)->setJSON([
                'error' => 'Gagal menghubungi server geocoding.'
            ]);
        }
    }

    public function reverse()
    {
        $lat = trim($this->request->getGet('lat'));
        $lon = trim($this->request->getGet('lon'));

        if (empty($lat) || empty($lon)) {
            return $this->response->setJSON([
                'error' => 'Latitude dan Longitude diperlukan'
            ]);
        }

        $cache = cache();
        $cacheKey = 'reverse_' . md5($lat . '_' . $lon);

        if ($cachedData = $cache->get($cacheKey)) {
            return $this->response->setJSON($cachedData);
        }

        $url = 'https://nominatim.openstreetmap.org/reverse?' . http_build_query([
            'lat'    => $lat,
            'lon'    => $lon,
            'format' => 'json',
        ]);

        try {

            $client = \Config\Services::curlrequest();

            $response = $client->get($url, [
                'headers' => [
                    'User-Agent' => 'SukaJajan/1.0 (contact@sukajajan.com)',
                ],
            ]);

            $result = json_decode($response->getBody(), true);

            if (empty($result)) {
                return $this->response->setJSON([
                    'error' => 'Alamat tidak ditemukan'
                ]);
            }

            $cache->save($cacheKey, $result, 3600);

            return $this->response->setJSON($result);

        } catch (\Throwable $e) {

            return $this->response->setStatusCode(500)->setJSON([
                'error' => 'Gagal mengambil alamat dari server.'
            ]);
        }
    }
}
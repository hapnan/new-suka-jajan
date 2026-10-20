<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Beranda</a></li>
            <li class="breadcrumb-item"><a href="/kuliner">Kuliner</a></li>
            <li class="breadcrumb-item active"><?= esc($kuliner['nama']) ?></li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-md-7">
            <div class="card shadow-sm mb-4">
                <img src="<?= $kuliner['foto_utama'] ? '/uploads/kuliner/' . esc($kuliner['foto_utama']) : 'https://via.placeholder.com/800x400?text=No+Image' ?>"
                     class="card-img-top" style="max-height: 400px; object-fit: cover;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="badge badge-category"><?= esc($kuliner['category_nama']) ?></span>
                            <?php if ($kuliner['is_closed']): ?>
                                <span class="badge bg-secondary">Tutup Permanen</span>
                            <?php endif; ?>
                        </div>
                        <?php if (session()->get('logged_in')): ?>
                        <div class="d-flex gap-2">
                            <form action="/favorite/toggle/<?= $kuliner['id'] ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm <?= $isFavorited ? 'btn-danger' : 'btn-outline-danger' ?>">
                                    <i class="bi bi-heart<?= $isFavorited ? '-fill' : '' ?>"></i>
                                    <?= $isFavorited ? 'Favorit' : 'Simpan' ?>
                                </button>
                            </form>
                            <?php if (!$kuliner['is_closed']): ?>
                            <form action="/kuliner/report-closed/<?= $kuliner['id'] ?>" method="post" class="d-inline"
                                  onsubmit="return confirm('Yakin tempat ini sudah tutup permanen?')">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i> Laporkan Tutup
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <h3><?= esc($kuliner['nama']) ?></h3>
                    <p class="text-muted"><i class="bi bi-geo-alt"></i> <?= esc($kuliner['alamat']) ?></p>
                    <div class="rating-stars mb-2">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi bi-star<?= $i <= round($kuliner['avg_rating']) ? '-fill' : '' ?> fs-5"></i>
                        <?php endfor; ?>
                        <span class="ms-2"><?= number_format($kuliner['avg_rating'], 1) ?>/5 (<?= $kuliner['total_reviews'] ?> review)</span>
                    </div>

                    <?php if (!empty($tags)): ?>
                    <div class="mb-3">
                        <?php foreach ($tags as $tag): ?>
                            <span class="badge badge-tag"><i class="bi bi-tag"></i> <?= esc($tag['nama']) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <hr>
                    <h5>Deskripsi</h5>
                    <p><?= nl2br(esc($kuliner['deskripsi'])) ?></p>
                </div>
            </div>

            <?php if (!empty($photos)): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5>Foto</h5>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php foreach ($photos as $photo): ?>
                        <a href="/uploads/kuliner/<?= esc($photo['filename']) ?>" target="_blank">
                            <img src="/uploads/thumbnails/<?= esc($photo['thumbnail']) ?>" class="foto-thumb">
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (session()->get('logged_in')): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5><i class="bi bi-camera"></i> Upload Foto</h5>
                    <form action="/kuliner/upload-photo/<?= $kuliner['id'] ?>" method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="input-group">
                            <input type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                            <button type="submit" class="btn btn-sj">Upload</button>
                        </div>
                        <small class="text-muted">Maks 3 foto per tempat. Format: JPG, JPEG, PNG, WEBP (Maks. 2MB).</small>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5><i class="bi bi-chat-dots"></i> Review (<?= count($reviews) ?>)</h5>

                    <?php if (session()->get('logged_in')): ?>
                    <form action="/review/store" method="post" class="mb-4 p-3 bg-light rounded">
                        <?= csrf_field() ?>
                        <input type="hidden" name="kuliner_id" value="<?= $kuliner['id'] ?>">
                        <div class="mb-2">
                            <label class="form-label">Rating</label>
                            <div id="star-rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi bi-star star-input" data-value="<?= $i ?>" onclick="setRating(<?= $i ?>)"></i>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="rating" id="rating-value" required>
                        </div>
                        <div class="mb-2">
                            <textarea name="komentar" class="form-control" rows="3" placeholder="Tulis review Anda (min 10 karakter)..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-sj btn-sm">Kirim Review</button>
                    </form>
                    <?php else: ?>
                    <p class="text-muted"><a href="/login">Login</a> untuk menulis review.</p>
                    <?php endif; ?>

                    <?php foreach ($reviews as $r): ?>
                    <div class="border-bottom py-3">
                        <div class="d-flex justify-content-between">
                            <strong><i class="bi bi-person"></i> <?= esc($r['username']) ?></strong>
                            <small class="text-muted"><?= date('d M Y H:i', strtotime($r['created_at'])) ?></small>
                        </div>
                        <div class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi bi-star<?= $i <= $r['rating'] ? '-fill' : '' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="mt-1 mb-1"><?= nl2br(esc($r['komentar'])) ?></p>
                        <?php if (session()->get('user_id') == $r['user_id'] && (time() - strtotime($r['created_at'])) < 86400): ?>
                        <button class="btn btn-sm btn-outline-primary" onclick="toggleEdit(<?= $r['id'] ?>)">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                        <form id="edit-form-<?= $r['id'] ?>" action="/review/update/<?= $r['id'] ?>" method="post" class="mt-2 d-none">
                            <?= csrf_field() ?>
                            <div class="mb-2">
                                <select name="rating" class="form-select form-select-sm">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?= $i ?>" <?= $r['rating'] == $i ? 'selected' : '' ?>><?= $i ?> Bintang</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <textarea name="komentar" class="form-control form-control-sm mb-2" rows="2"><?= esc($r['komentar']) ?></textarea>
                            <button type="submit" class="btn btn-sm btn-sj">Simpan</button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <?php if (empty($reviews)): ?>
                    <p class="text-muted">Belum ada review.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card shadow-sm sticky-top" style="top: 80px;">
                <div class="card-body">
                    <h5><i class="bi bi-map"></i> Lokasi</h5>
                    <?php if ($kuliner['latitude'] && $kuliner['longitude']): ?>
                    <div id="map"></div>
                    <?php else: ?>
                    <p class="text-muted">Koordinat tidak tersedia.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
function setRating(val) {
    document.getElementById('rating-value').value = val;
    document.querySelectorAll('#star-rating .star-input').forEach((s, i) => {
        s.classList.toggle('active', i < val);
        s.classList.toggle('bi-star-fill', i < val);
        s.classList.toggle('bi-star', i >= val);
    });
}

function toggleEdit(id) {
    document.getElementById('edit-form-' + id).classList.toggle('d-none');
}

<?php if ($kuliner['latitude'] && $kuliner['longitude']): ?>
var map = L.map('map').setView([<?= $kuliner['latitude'] ?>, <?= $kuliner['longitude'] ?>], 16);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);
L.marker([<?= $kuliner['latitude'] ?>, <?= $kuliner['longitude'] ?>])
    .addTo(map)
    .bindPopup('<b><?= esc($kuliner['nama'], 'js') ?></b><br><?= esc($kuliner['alamat'], 'js') ?>')
    .openPopup();
<?php endif; ?>
</script>
<?= $this->endSection() ?>

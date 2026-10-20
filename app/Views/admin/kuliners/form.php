<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<h3 class="mb-4"><?= $kuliner ? 'Edit' : 'Tambah' ?> Kuliner</h3>

<div class="card">
    <div class="card-body">
        <form action="<?= $kuliner ? '/admin/kuliners/update/' . $kuliner['id'] : '/admin/kuliners/store' ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Nama Tempat</label>
                        <input type="text" name="nama" class="form-control" value="<?= esc(old('nama', $kuliner['nama'] ?? '')) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Pilih Kategori</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= old('category_id', $kuliner['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                <?= esc($cat['nama']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <div class="input-group">
                            <input type="text" name="alamat" id="alamat" class="form-control" value="<?= esc(old('alamat', $kuliner['alamat'] ?? '')) ?>" required>
                            <button type="button" class="btn btn-warning" onclick="cariKoordinat()">
                                <i class="bi bi-geo-alt"></i> Cari Koordinat
                            </button>
                        </div>
                        <div id="geocode-results" class="list-group mt-1"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label class="form-label">Latitude</label>
                            <input type="text" name="latitude" id="latitude" class="form-control" value="<?= esc(old('latitude', $kuliner['latitude'] ?? '')) ?>">
                        </div>
                        <div class="col">
                            <label class="form-label">Longitude</label>
                            <input type="text" name="longitude" id="longitude" class="form-control" value="<?= esc(old('longitude', $kuliner['longitude'] ?? '')) ?>">
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="deskripsi" class="form-control" rows="4"><?= esc(old('deskripsi', $kuliner['deskripsi'] ?? '')) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tags</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($tags as $tag): ?>
                            <div class="form-check">
                                <input type="checkbox" name="tags[]" value="<?= $tag['id'] ?>" class="form-check-input"
                                       id="tag<?= $tag['id'] ?>" <?= in_array($tag['id'], old('tags', $selectedTags)) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="tag<?= $tag['id'] ?>"><?= esc($tag['nama']) ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Foto Utama</label>
                        <?php if (!empty($kuliner['foto_utama'])): ?>
                        <div class="mb-2">
                            <img src="/uploads/thumbnails/<?= esc($kuliner['foto_utama']) ?>" class="img-thumbnail" style="max-height: 100px;">
                        </div>
                        <?php endif; ?>
                        <input type="file" name="foto_utama" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        <small class="text-muted">Format yang didukung: JPG, JPEG, PNG, WEBP (Maks. 2MB).</small>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Simpan</button>
            <a href="/admin/kuliners" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
function cariKoordinat() {
    var alamat = document.getElementById('alamat').value;
    if (!alamat) return;
    fetch('/geocode?q=' + encodeURIComponent(alamat))
        .then(r => r.json())
        .then(data => {
            var container = document.getElementById('geocode-results');
            container.innerHTML = '';
            if (data.length === 0) {
                container.innerHTML = '<div class="list-group-item text-muted">Alamat Tidak ditemukan.</div>';
                return;
            }
            
            // Otomatis isi dengan hasil pencarian pertama
            document.getElementById('latitude').value = data[0].lat;
            document.getElementById('longitude').value = data[0].lon;

            data.forEach(function(item) {
                var el = document.createElement('a');
                el.href = '#';
                el.className = 'list-group-item list-group-item-action small';
                el.textContent = item.display_name;
                el.onclick = function(e) {
                    e.preventDefault();
                    document.getElementById('latitude').value = item.lat;
                    document.getElementById('longitude').value = item.lon;
                    container.innerHTML = '';
                };
                container.appendChild(el);
            });
        })
        .catch(e => {
            alert('Terjadi kesalahan saat mencari koordinat. Silakan coba lagi.');
        });
}
</script>
<?= $this->endSection() ?>

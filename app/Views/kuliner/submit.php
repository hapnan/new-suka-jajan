<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container py-4">
    <h3 class="mb-4"><i class="bi bi-plus-circle"></i> Tambah Tempat Kuliner</h3>

    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form action="/kuliner/store" method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">Nama Tempat *</label>
                            <input type="text" name="nama" class="form-control" value="<?= old('nama') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kategori *</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Pilih Kategori</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= esc($cat['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat *</label>
                            <div class="input-group">
                                <input type="text" name="alamat" id="alamat" class="form-control" value="<?= old('alamat') ?>" required>
                                <button type="button" class="btn btn-secondary" onclick="getLocation()" title="Gunakan Lokasi Saat Ini (GPS)">
                                    <i class="bi bi-crosshair"></i> GPS
                                </button>
                                <button type="button" class="btn text-dark fw-bold" style="background: var(--sj-yellow);" onclick="cariKoordinat()">
                                    <i class="bi bi-geo-alt"></i> Cari Koordinat
                                </button>
                            </div>
                            <div id="geocode-results" class="list-group mt-1"></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <label class="form-label">Latitude</label>
                                <input type="text" name="latitude" id="latitude" class="form-control" readonly>
                            </div>
                            <div class="col">
                                <label class="form-label">Longitude</label>
                                <input type="text" name="longitude" id="longitude" class="form-control" readonly>
                            </div>
                        </div>
                        <div id="preview-map" style="height: 250px; border-radius: 10px;" class="mb-3"></div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi *</label>
                            <textarea name="deskripsi" class="form-control" rows="4" required><?= old('deskripsi') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tags</label>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($tags as $tag): ?>
                                <div class="form-check">
                                    <input type="checkbox" name="tags[]" value="<?= $tag['id'] ?>" class="form-check-input" id="tag<?= $tag['id'] ?>">
                                    <label class="form-check-label" for="tag<?= $tag['id'] ?>"><?= esc($tag['nama']) ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Foto Utama</label>
                            <input type="file" name="foto_utama" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <small class="text-muted">Format yang didukung: JPG, JPEG, PNG, WEBP (Maks. 2MB).</small>
                        </div>
                        <button type="submit" class="btn btn-sj btn-lg">
                            <i class="bi bi-send"></i> Submit Tempat
                        </button>
                        <small class="text-muted d-block mt-2">* Tempat yang disubmit akan direview oleh admin terlebih dahulu.</small>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
var previewMap = null;
var previewMarker = null;

function cariKoordinat() {
    var alamat = document.getElementById('alamat').value;
    if (!alamat) { alert('Masukkan alamat terlebih dahulu!'); return; }

    fetch('/geocode?q=' + encodeURIComponent(alamat))
        .then(r => r.json())
        .then(data => {
            var container = document.getElementById('geocode-results');
            container.innerHTML = '';
            if (data.length === 0) {
                container.innerHTML = '<div class="list-group-item text-muted">Tidak ditemukan.</div>';
                return;
            }
            
            // Otomatis isi dengan hasil pencarian pertama
            document.getElementById('latitude').value = data[0].lat;
            document.getElementById('longitude').value = data[0].lon;
            showPreviewMap(data[0].lat, data[0].lon);

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
                    showPreviewMap(item.lat, item.lon);
                };
                container.appendChild(el);
            });
        })
        .catch(e => {
            alert('Terjadi kesalahan saat mencari koordinat. Silakan coba lagi.');
        });
}

function showPreviewMap(lat, lon) {
    if (!previewMap) {
        previewMap = L.map('preview-map').setView([lat, lon], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(previewMap);
    } else {
        previewMap.setView([lat, lon], 16);
    }
    if (previewMarker) previewMap.removeLayer(previewMarker);
    previewMarker = L.marker([lat, lon]).addTo(previewMap);
}

function getLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            var lat = position.coords.latitude;
            var lon = position.coords.longitude;
            
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lon;
            showPreviewMap(lat, lon);
            
            fetch('/geocode/reverse?lat=' + lat + '&lon=' + lon)
                .then(r => r.json())
                .then(data => {
                    if(data && data.display_name) {
                        document.getElementById('alamat').value = data.display_name;
                    }
                })
                .catch(e => console.error(e));
        }, function(error) {
            alert('Tidak dapat mengakses lokasi GPS. Pastikan izin lokasi diberikan pada browser Anda.');
        });
    } else {
        alert('Browser Anda tidak mendukung fitur Geolocation.');
    }
}
</script>
<?= $this->endSection() ?>

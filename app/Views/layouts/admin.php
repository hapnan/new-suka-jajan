<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Admin' ?> - SukaJajan Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --sj-cyan: #11BFE1;
            --sj-red: #EA2131;
            --sj-yellow: #F8CD0E;
            --sj-navy: #102C53;
            --sj-navy-light: #1a3d6e;
        }
        body { font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif; background: #f5f7fa; }
        .sidebar { min-height: 100vh; background: var(--sj-navy); }
        .sidebar .nav-link { color: rgba(255,255,255,0.7); padding: 12px 20px; border-radius: 0; font-weight: 500; transition: all 0.2s; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: var(--sj-navy-light); color: var(--sj-cyan); }
        .sidebar .nav-link i { width: 24px; }
        .sidebar-brand { padding: 20px; color: #fff; font-size: 1.3rem; font-weight: 800; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand i { color: var(--sj-cyan); }
        .stat-card { border: none; border-radius: 16px; box-shadow: 0 4px 15px rgba(16,44,83,0.08); }
        .stat-card .stat-icon { font-size: 2.5rem; opacity: 0.3; }
        .navbar { border-bottom: 1px solid rgba(16,44,83,0.08); }
        .btn-primary { background: var(--sj-cyan); border-color: var(--sj-cyan); }
        .btn-primary:hover { background: #0ea8c7; border-color: #0ea8c7; }
        .card { border: none; border-radius: 14px; box-shadow: 0 2px 10px rgba(16,44,83,0.06); }
        h3, h4, h5 { color: var(--sj-navy); font-weight: 700; }
    </style>
</head>
<body>
    <div class="d-flex">
        <div class="sidebar d-flex flex-column" style="width: 260px;">
            <div class="sidebar-brand">
                <i class="bi bi-geo-alt-fill"></i> SukaJajan Admin
            </div>
            <nav class="nav flex-column mt-2">
                <a class="nav-link" href="/admin"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a class="nav-link" href="/admin/categories"><i class="bi bi-grid"></i> Kategori</a>
                <a class="nav-link" href="/admin/tags"><i class="bi bi-tags"></i> Tags</a>
                <a class="nav-link" href="/admin/kuliners"><i class="bi bi-shop"></i> Kuliner</a>
                <a class="nav-link" href="/admin/reviews"><i class="bi bi-chat-dots"></i> Reviews</a>
                <hr class="text-white mx-3">
                <a class="nav-link" href="/"><i class="bi bi-house"></i> Ke Website</a>
                <a class="nav-link" href="/logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </nav>
        </div>
        <div class="flex-grow-1">
            <nav class="navbar navbar-light bg-white shadow-sm px-4">
                <span class="navbar-text">
                    <i class="bi bi-person-circle"></i> <?= esc(session()->get('username')) ?> (Admin)
                </span>
            </nav>
            <div class="p-4">
                <?php if (session()->getFlashdata('success')): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= session()->getFlashdata('success') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= session()->getFlashdata('error') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                <?php if (session()->getFlashdata('errors')): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <ul class="mb-0">
                    <?php foreach (session()->getFlashdata('errors') as $err): ?>
                        <li><?= esc($err) ?></li>
                    <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                <?= $this->renderSection('content') ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?= $this->renderSection('scripts') ?>
</body>
</html>

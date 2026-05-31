<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    header("Location: login.php");
    exit;
}

$siswa_id = (int)$_SESSION['user_id'];

// Fetch accepted application
$accepted_sql = "SELECT a.id, a.job_id, j.title, j.company_name
                 FROM applications a
                 JOIN jobs j ON a.job_id = j.id
                 WHERE a.siswa_id = $siswa_id AND a.status = 'accepted'
                 ORDER BY a.applied_at DESC
                 LIMIT 1";
$accepted_result = $conn->query($accepted_sql);
if (!$accepted_result || $accepted_result->num_rows === 0) {
    // Not accepted anywhere, redirect
    header("Location: siswa_dashboard.php");
    exit;
}
$accepted = $accepted_result->fetch_assoc();
$application_id = (int)$accepted['id'];

// Fetch active session (checked in, not yet checked out)
$active_sql = "SELECT * FROM logbook_sessions
               WHERE siswa_id = $siswa_id AND check_out IS NULL
               ORDER BY check_in DESC LIMIT 1";
$active_result = $conn->query($active_sql);
$active_session = ($active_result && $active_result->num_rows > 0)
                  ? $active_result->fetch_assoc()
                  : null;

// Fetch history (completed sessions)
$history_sql = "SELECT * FROM logbook_sessions
                WHERE siswa_id = $siswa_id AND check_out IS NOT NULL
                ORDER BY check_in DESC
                LIMIT 60";
$history_result = $conn->query($history_sql);

// Today's info
$today          = date('Y-m-d');
$today_label    = date('l, d F Y');
$day_of_week    = (int)date('N'); // 1=Mon … 7=Sun
$is_weekend     = $day_of_week >= 6;

// Count today's completed sessions
$today_count_sql = "SELECT COUNT(*) as c FROM logbook_sessions
                    WHERE siswa_id = $siswa_id
                      AND DATE(check_in) = '$today'
                      AND check_out IS NOT NULL";
$today_count_res = $conn->query($today_count_sql);
$today_sessions  = (int)($today_count_res->fetch_assoc()['c'] ?? 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logbook Harian – Bursa Kerja Khusus SMTI Pontianak</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ── Page layout ───────────────────────────────────── */
        .logbook-layout {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 2rem;
            align-items: start;
            margin: 2rem 0 5rem;
        }
        @media (max-width: 900px) {
            .logbook-layout { grid-template-columns: 1fr; }
        }

        /* ── Panel card ────────────────────────────────────── */
        .panel {
            background: var(--bg-surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .panel-header {
            padding: 1.5rem 1.75rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .panel-header i { color: var(--primary); font-size: 1.2rem; }
        .panel-header h2 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark);
        }
        .panel-body { padding: 1.75rem; }

        /* ── Clock widget ──────────────────────────────────── */
        .clock-widget {
            background: linear-gradient(135deg, var(--primary), #7C3AED);
            border-radius: var(--radius-md);
            padding: 2rem;
            text-align: center;
            color: white;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        .clock-widget::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .clock-time {
            font-size: 2.8rem;
            font-weight: 700;
            letter-spacing: 2px;
            font-variant-numeric: tabular-nums;
        }
        .clock-date {
            font-size: 0.9rem;
            opacity: 0.85;
            margin-top: 6px;
        }
        .clock-company {
            margin-top: 12px;
            font-size: 0.8rem;
            opacity: 0.7;
            background: rgba(255,255,255,0.15);
            border-radius: 20px;
            display: inline-block;
            padding: 4px 14px;
        }

        /* ── State badges ──────────────────────────────────── */
        .state-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: var(--radius-full);
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .state-idle    { background: #F1F5F9; color: #64748B; }
        .state-active  { background: #DCFCE7; color: #166534; }
        .state-overtime { background: #FEF3C7; color: #92400E; }

        /* ── Big action buttons ─────────────────────────────── */
        .btn-checkin {
            width: 100%;
            padding: 1rem;
            font-size: 1.1rem;
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-family: inherit;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: var(--transition);
            box-shadow: 0 4px 14px rgba(16,185,129,0.3);
        }
        .btn-checkin:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16,185,129,0.4);
        }
        .btn-checkout {
            width: 100%;
            padding: 1rem;
            font-size: 1.1rem;
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-family: inherit;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: var(--transition);
            box-shadow: 0 4px 14px rgba(239,68,68,0.3);
        }
        .btn-checkout:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239,68,68,0.4);
        }
        .btn-checkout:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* ── Checkout form ──────────────────────────────────── */
        .checkout-form { margin-top: 1.25rem; }
        .checkout-form .form-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--dark);
            display: block;
            margin-bottom: 8px;
        }
        .checkout-form .form-label .req {
            color: var(--danger);
            margin-left: 2px;
        }
        .checkout-form textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 0.95rem;
            color: var(--text-main);
            background: var(--bg-main);
            resize: vertical;
            min-height: 110px;
            transition: var(--transition);
            box-sizing: border-box;
        }
        .checkout-form textarea:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
        }

        /* ── Photo upload ───────────────────────────────────── */
        .photo-upload-area {
            border: 2px dashed var(--border);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            background: var(--bg-main);
            margin-top: 1rem;
        }
        .photo-upload-area:hover, .photo-upload-area.drag-over {
            border-color: var(--primary);
            background: var(--primary-light);
        }
        .photo-upload-area input[type=file] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }
        .photo-preview-wrap {
            display: none;
            margin-top: 1rem;
            position: relative;
        }
        .photo-preview-wrap img {
            width: 100%;
            border-radius: var(--radius-md);
            max-height: 200px;
            object-fit: cover;
        }
        .photo-preview-wrap .remove-photo {
            position: absolute;
            top: 8px; right: 8px;
            background: rgba(0,0,0,0.6);
            color: white;
            border: none;
            border-radius: 50%;
            width: 28px; height: 28px;
            cursor: pointer;
            font-size: 0.85rem;
            display: flex; align-items: center; justify-content: center;
        }

        /* ── Active session info ────────────────────────────── */
        .session-info {
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            border-radius: var(--radius-md);
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .session-info i { color: #16A34A; font-size: 1.2rem; }
        .session-info .session-detail { flex: 1; }
        .session-info .session-time {
            font-weight: 700;
            font-size: 1.1rem;
            color: #166534;
        }
        .session-info .session-label {
            font-size: 0.8rem;
            color: #4ADE80;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }
        .elapsed-timer {
            font-size: 1rem;
            font-weight: 600;
            color: #16A34A;
            font-variant-numeric: tabular-nums;
        }

        /* ── Toast notification ─────────────────────────────── */
        .toast {
            position: fixed;
            bottom: 2rem; right: 2rem;
            background: var(--dark);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            font-weight: 500;
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: var(--shadow-lg);
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            max-width: 360px;
        }
        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast.toast-success { background: #065F46; }
        .toast.toast-error   { background: #991B1B; }

        /* ── History table ──────────────────────────────────── */
        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th {
            background: var(--bg-main);
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--border);
        }
        .history-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
            font-size: 0.9rem;
        }
        .history-table tr:last-child td { border-bottom: none; }
        .history-table tr:hover td { background: var(--bg-main); }
        .overtime-row td { background: #FFFBEB !important; }
        .photo-thumb {
            width: 56px; height: 56px;
            border-radius: 8px;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.2s;
            border: 2px solid var(--border);
        }
        .photo-thumb:hover { transform: scale(1.1); }
        .duration-chip {
            display: inline-block;
            background: var(--primary-light);
            color: var(--primary);
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
        }

        /* ── Lightbox ───────────────────────────────────────── */
        .lightbox {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.85);
            z-index: 9998;
            align-items: center;
            justify-content: center;
            cursor: zoom-out;
        }
        .lightbox.open { display: flex; }
        .lightbox img {
            max-width: 90vw;
            max-height: 90vh;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
        }
        .lightbox-close {
            position: fixed; top: 1.5rem; right: 1.5rem;
            background: rgba(255,255,255,0.2);
            color: white; border: none;
            border-radius: 50%; width: 40px; height: 40px;
            cursor: pointer; font-size: 1.2rem;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.2s;
        }
        .lightbox-close:hover { background: rgba(255,255,255,0.35); }

        /* ── Spinner ────────────────────────────────────────── */
        .spinner {
            width: 18px; height: 18px;
            border: 3px solid rgba(255,255,255,0.4);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            display: none;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Divider with text ──────────────────────────────── */
        .divider-text {
            display: flex; align-items: center; gap: 1rem;
            color: var(--text-muted); font-size: 0.8rem;
            margin: 1.25rem 0;
        }
        .divider-text::before, .divider-text::after {
            content: ''; flex: 1; height: 1px; background: var(--border);
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="container nav-container">
            <a href="siswa_dashboard.php" class="nav-brand">
                <i class="fa-solid fa-briefcase"></i>
                Bursa Kerja Khusus SMTI Pontianak
            </a>
            <div class="nav-links">
                <a href="siswa_dashboard.php" style="color: var(--text-main); font-weight:500; font-size:0.95rem;">
                    <i class="fa-solid fa-gauge"></i> Dashboard
                </a>
                <span style="color: var(--text-main); font-size: 0.95rem;">
                    <i class="fa-solid fa-user-graduate"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>
                <a href="logout.php" class="btn btn-danger" style="padding: 0.5rem 1rem; font-size: 0.9rem;">
                    <i class="fa-solid fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main -->
    <main class="container">

        <!-- Page heading -->
        <div style="margin: 2rem 0 1.5rem;">
            <h1 style="font-size: 1.75rem; display:flex; align-items:center; gap:12px;">
                <i class="fa-solid fa-book-open" style="color:var(--primary);"></i> Logbook Harian
            </h1>
            <p style="color:var(--text-muted); margin-top:6px;">
                Diterima di <strong><?php echo htmlspecialchars($accepted['company_name']); ?></strong>
                sebagai <strong><?php echo htmlspecialchars($accepted['title']); ?></strong>
            </p>
        </div>

        <div class="logbook-layout">

            <!-- ───── LEFT COLUMN: today's actions ───── -->
            <div>
                <!-- Clock -->
                <div class="clock-widget">
                    <div class="clock-time" id="liveClock">--:--:--</div>
                    <div class="clock-date"><?php echo $today_label; ?></div>
                    <?php if ($is_weekend): ?>
                        <div class="clock-company">🌙 Hari Libur / Weekend</div>
                    <?php else: ?>
                        <div class="clock-company">🏢 Hari Kerja</div>
                    <?php endif; ?>
                </div>

                <!-- Session Status Panel -->
                <div class="panel">
                    <div class="panel-header">
                        <i class="fa-solid fa-clock"></i>
                        <h2>Sesi Hari Ini</h2>
                        <?php if ($today_sessions > 0): ?>
                            <span class="state-badge state-active" style="margin-left:auto;">
                                <?php echo $today_sessions; ?> sesi selesai
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="panel-body">

                        <?php if ($is_weekend): ?>
                            <div style="background:#FEF3C7; border:1px solid #FCD34D; border-radius:var(--radius-sm); padding:10px 14px; margin-bottom:1rem; font-size:0.87rem; color:#92400E; display:flex; gap:8px; align-items:center;">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                Hari ini adalah hari libur. Sesi ini akan ditandai sebagai <strong>overtime</strong>.
                            </div>
                        <?php endif; ?>

                        <?php if ($active_session): ?>
                            <!-- Active session: show checkout form -->
                            <div class="session-info">
                                <i class="fa-solid fa-circle-dot fa-beat"></i>
                                <div class="session-detail">
                                    <div class="session-label">Check-in pada</div>
                                    <div class="session-time">
                                        <?php echo date('H:i', strtotime($active_session['check_in'])); ?>
                                    </div>
                                </div>
                                <div>
                                    <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:2px;">Durasi</div>
                                    <div class="elapsed-timer" id="elapsedTimer">--:--</div>
                                </div>
                            </div>

                            <form id="checkoutForm" class="checkout-form" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="checkout">
                                <input type="hidden" name="session_id" value="<?php echo (int)$active_session['id']; ?>">

                                <label class="form-label" for="deskripsi">
                                    Deskripsi Pekerjaan Hari Ini <span class="req">*</span>
                                </label>
                                <textarea id="deskripsi" name="deskripsi"
                                    placeholder="Tuliskan kegiatan yang Anda lakukan hari ini secara detail..."
                                    required></textarea>
                                <span style="font-size:0.78rem; color:var(--text-muted);">Wajib diisi. Tidak dapat diubah setelah check-out.</span>

                                <div class="divider-text">Foto Kegiatan (opsional)</div>

                                <div class="photo-upload-area" id="photoUploadArea">
                                    <input type="file" name="foto" id="fotoInput"
                                        accept=".jpg,.jpeg,.png,.webp,.heic,.heif">
                                    <i class="fa-solid fa-camera" style="font-size:1.5rem; color:var(--primary); margin-bottom:8px;"></i>
                                    <p style="font-size:0.85rem; color:var(--text-muted); margin:0;">
                                        <strong>Klik atau drag foto di sini</strong><br>
                                        JPG, PNG, WebP, HEIC – maks 5 MB
                                    </p>
                                </div>
                                <div class="photo-preview-wrap" id="photoPreviewWrap">
                                    <img id="photoPreview" src="" alt="Preview">
                                    <button type="button" class="remove-photo" id="removePhoto" title="Hapus foto">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>

                                <button type="submit" class="btn-checkout" id="checkoutBtn" style="margin-top:1.25rem;" disabled>
                                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                                    <span id="checkoutBtnText">Isi deskripsi untuk Check-Out</span>
                                    <div class="spinner" id="checkoutSpinner"></div>
                                </button>
                            </form>

                        <?php else: ?>
                            <!-- No active session: show check-in button -->
                            <p style="font-size:0.9rem; color:var(--text-muted); margin-bottom:1.25rem;">
                                <?php if ($today_sessions > 0): ?>
                                    Anda sudah menyelesaikan <?php echo $today_sessions; ?> sesi hari ini.
                                    Anda dapat memulai sesi baru jika diperlukan (lembur, dll.).
                                <?php else: ?>
                                    Belum ada sesi yang dimulai hari ini. Tekan tombol di bawah untuk memulai.
                                <?php endif; ?>
                            </p>
                            <button class="btn-checkin" id="checkinBtn" onclick="doCheckin()">
                                <i class="fa-solid fa-right-to-bracket"></i>
                                <span id="checkinBtnText">Check In Sekarang</span>
                                <div class="spinner" id="checkinSpinner"></div>
                            </button>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

            <!-- ───── RIGHT COLUMN: history ───── -->
            <div class="panel">
                <div class="panel-header">
                    <i class="fa-solid fa-history"></i>
                    <h2>Riwayat Logbook</h2>
                </div>
                <div style="overflow-x: auto;">
                    <?php if ($history_result && $history_result->num_rows > 0): ?>
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Check In</th>
                                    <th>Check Out</th>
                                    <th>Durasi</th>
                                    <th>Deskripsi</th>
                                    <th>Foto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $history_result->fetch_assoc()): ?>
                                    <?php
                                    $cin  = strtotime($row['check_in']);
                                    $cout = strtotime($row['check_out']);
                                    $dur  = $cout - $cin;
                                    $dur_h = floor($dur / 3600);
                                    $dur_m = floor(($dur % 3600) / 60);
                                    $dur_str = $dur_h . 'j ' . $dur_m . 'm';
                                    $is_ot = (bool)$row['is_overtime'];
                                    $row_class = $is_ot ? 'overtime-row' : '';
                                    ?>
                                    <tr class="<?php echo $row_class; ?>">
                                        <td>
                                            <div style="font-weight:600; color:var(--dark);">
                                                <?php echo date('d M Y', $cin); ?>
                                            </div>
                                            <div style="font-size:0.75rem; color:var(--text-muted);">
                                                <?php echo date('l', $cin); ?>
                                            </div>
                                            <?php if ($is_ot): ?>
                                                <span class="state-badge state-overtime" style="margin-top:4px; display:inline-flex;">
                                                    <i class="fa-solid fa-moon"></i> Overtime
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-weight:600; color:#16A34A;">
                                            <?php echo date('H:i', $cin); ?>
                                        </td>
                                        <td style="font-weight:600; color:#DC2626;">
                                            <?php echo date('H:i', $cout); ?>
                                        </td>
                                        <td>
                                            <span class="duration-chip"><?php echo $dur_str; ?></span>
                                        </td>
                                        <td style="max-width:280px;">
                                            <div style="color:var(--text-main); font-size:0.88rem; line-height:1.5; white-space:pre-wrap; word-break:break-word;">
                                                <?php echo nl2br(htmlspecialchars($row['deskripsi'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($row['foto']): ?>
                                                <img src="uploads/logbook/<?php echo htmlspecialchars($row['foto']); ?>"
                                                     class="photo-thumb"
                                                     alt="Foto kegiatan"
                                                     onclick="openLightbox(this.src)"
                                                     title="Klik untuk memperbesar">
                                            <?php else: ?>
                                                <span style="color:var(--text-muted); font-size:0.8rem;">–</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state" style="border-radius:0; border:none;">
                            <div class="empty-state-icon">
                                <i class="fa-solid fa-book-open"></i>
                            </div>
                            <h3>Belum ada riwayat</h3>
                            <p>Mulai sesi pertama Anda dengan menekan Check In.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>

    <!-- Lightbox -->
    <div class="lightbox" id="lightbox" onclick="closeLightbox()">
        <button class="lightbox-close" onclick="closeLightbox()">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <img id="lightboxImg" src="" alt="Foto kegiatan">
    </div>

    <!-- Toast -->
    <div class="toast" id="toast">
        <i class="fa-solid fa-circle-check" id="toastIcon"></i>
        <span id="toastMsg"></span>
    </div>

    <script>
    // ── Live clock ──────────────────────────────────────────────
    function updateClock() {
        const now = new Date();
        const h = String(now.getHours()).padStart(2, '0');
        const m = String(now.getMinutes()).padStart(2, '0');
        const s = String(now.getSeconds()).padStart(2, '0');
        const el = document.getElementById('liveClock');
        if (el) el.textContent = `${h}:${m}:${s}`;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // ── Elapsed timer (active session) ─────────────────────────
    <?php if ($active_session): ?>
    const checkinTime = new Date("<?php echo $active_session['check_in']; ?>").getTime();
    function updateElapsed() {
        const diff = Math.floor((Date.now() - checkinTime) / 1000);
        const h = Math.floor(diff / 3600);
        const m = Math.floor((diff % 3600) / 60);
        const s = diff % 60;
        const el = document.getElementById('elapsedTimer');
        if (el) el.textContent =
            (h > 0 ? h + 'j ' : '') +
            String(m).padStart(2, '0') + 'm ' +
            String(s).padStart(2, '0') + 'd';
    }
    setInterval(updateElapsed, 1000);
    updateElapsed();
    <?php endif; ?>

    // ── Toast ───────────────────────────────────────────────────
    function showToast(msg, type = 'success') {
        const t = document.getElementById('toast');
        const i = document.getElementById('toastIcon');
        document.getElementById('toastMsg').textContent = msg;
        t.className = 'toast toast-' + type;
        i.className = type === 'success'
            ? 'fa-solid fa-circle-check'
            : 'fa-solid fa-circle-exclamation';
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 4000);
    }

    // ── Lightbox ────────────────────────────────────────────────
    function openLightbox(src) {
        document.getElementById('lightboxImg').src = src;
        document.getElementById('lightbox').classList.add('open');
    }
    function closeLightbox() {
        document.getElementById('lightbox').classList.remove('open');
    }

    // ── Check-in ────────────────────────────────────────────────
    async function doCheckin() {
        const btn  = document.getElementById('checkinBtn');
        const txt  = document.getElementById('checkinBtnText');
        const spin = document.getElementById('checkinSpinner');
        btn.disabled = true;
        txt.textContent = 'Memproses...';
        spin.style.display = 'block';

        try {
            const fd = new FormData();
            fd.append('action', 'checkin');
            const res  = await fetch('logbook_action.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast(data.message, 'error');
                btn.disabled = false;
                txt.textContent = 'Check In Sekarang';
                spin.style.display = 'none';
            }
        } catch (e) {
            showToast('Terjadi kesalahan jaringan.', 'error');
            btn.disabled = false;
            txt.textContent = 'Check In Sekarang';
            spin.style.display = 'none';
        }
    }

    // ── Description → enable checkout button ───────────────────
    <?php if ($active_session): ?>
    const descEl   = document.getElementById('deskripsi');
    const coutBtn  = document.getElementById('checkoutBtn');
    const coutTxt  = document.getElementById('checkoutBtnText');

    function syncCheckoutBtn() {
        const filled = descEl && descEl.value.trim().length >= 10;
        coutBtn.disabled = !filled;
        coutTxt.textContent = filled ? 'Check Out Sekarang' : 'Isi deskripsi untuk Check-Out';
    }
    if (descEl) { descEl.addEventListener('input', syncCheckoutBtn); syncCheckoutBtn(); }

    // ── Photo preview ────────────────────────────────────────────
    const fotoInput      = document.getElementById('fotoInput');
    const previewWrap    = document.getElementById('photoPreviewWrap');
    const previewImg     = document.getElementById('photoPreview');
    const removePhotoBtn = document.getElementById('removePhoto');
    const uploadArea     = document.getElementById('photoUploadArea');

    fotoInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => {
            previewImg.src = e.target.result;
            previewWrap.style.display = 'block';
            uploadArea.style.display  = 'none';
        };
        reader.readAsDataURL(file);
    });

    removePhotoBtn.addEventListener('click', () => {
        fotoInput.value = '';
        previewWrap.style.display = 'none';
        uploadArea.style.display  = 'block';
    });

    // Drag-and-drop
    ['dragenter','dragover'].forEach(ev =>
        uploadArea.addEventListener(ev, e => { e.preventDefault(); uploadArea.classList.add('drag-over'); }));
    ['dragleave','drop'].forEach(ev =>
        uploadArea.addEventListener(ev, e => { e.preventDefault(); uploadArea.classList.remove('drag-over'); }));
    uploadArea.addEventListener('drop', e => {
        const file = e.dataTransfer.files[0];
        if (!file) return;
        const dt = new DataTransfer();
        dt.items.add(file);
        fotoInput.files = dt.files;
        fotoInput.dispatchEvent(new Event('change'));
    });

    // ── Checkout form submit ─────────────────────────────────────
    document.getElementById('checkoutForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn  = document.getElementById('checkoutBtn');
        const txt  = document.getElementById('checkoutBtnText');
        const spin = document.getElementById('checkoutSpinner');
        btn.disabled = true;
        txt.textContent = 'Memproses...';
        spin.style.display = 'block';

        try {
            const fd = new FormData(this);
            const res  = await fetch('logbook_action.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => location.reload(), 1400);
            } else {
                showToast(data.message, 'error');
                btn.disabled = false;
                txt.textContent = 'Check Out Sekarang';
                spin.style.display = 'none';
            }
        } catch (err) {
            showToast('Terjadi kesalahan jaringan.', 'error');
            btn.disabled = false;
            txt.textContent = 'Check Out Sekarang';
            spin.style.display = 'none';
        }
    });
    <?php endif; ?>
    </script>

</body>
</html>

<?php
declare(strict_types=1);

/**
 * Play-On Mombasa - Admin Overview Dashboard
 */

require_once __DIR__ . '/auth.php';
requireAdmin();

$admin = getCurrentAdmin();
$pdo   = getDbConnection();
$flash = getFlash();
$csrf  = generateCsrfToken();

// Fetch Metric Statistics
$today = date('Y-m-d');

$statTotal = (int)$pdo->query("SELECT COUNT(*) FROM `bookings`")->fetchColumn();
$statToday = (int)$pdo->query("SELECT COUNT(*) FROM `bookings` WHERE `booking_date` = '{$today}'")->fetchColumn();
$statPending = (int)$pdo->query("SELECT COUNT(*) FROM `bookings` WHERE `status` = 'pending'")->fetchColumn();
$statConfirmed = (int)$pdo->query("SELECT COUNT(*) FROM `bookings` WHERE `status` = 'confirmed'")->fetchColumn();
$statCancelled = (int)$pdo->query("SELECT COUNT(*) FROM `bookings` WHERE `status` = 'cancelled'")->fetchColumn();

// Fetch 10 Most Recent Bookings
$recentStmt = $pdo->query("SELECT * FROM `bookings` ORDER BY `id` DESC LIMIT 10");
$recentBookings = $recentStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Overview | Play-On Mombasa Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@600;700;800&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="css/admin.css">
</head>
<body>

  <!-- Navigation Bar -->
  <header class="admin-navbar">
    <div class="nav-container">
      <a href="index.php" class="nav-brand">
        <span style="font-size:1.6rem;">🎈</span>
        <span class="nav-brand-title">Play-On Admin</span>
        <span class="nav-brand-badge">Mombasa Mall</span>
      </a>

      <ul class="nav-menu">
        <li class="nav-item"><a href="index.php" class="active"><i class="fas fa-chart-pie"></i> Overview</a></li>
        <li class="nav-item"><a href="bookings.php"><i class="fas fa-calendar-check"></i> All Bookings</a></li>
        <li class="nav-item"><a href="export_csv.php"><i class="fas fa-file-csv"></i> Export CSV</a></li>
        <li class="nav-item"><a href="<?= htmlspecialchars(env('FRONTEND_URL', 'https://shimix-del.github.io/playon')) ?>" target="_blank"><i class="fas fa-external-link-alt"></i> View Site</a></li>
        <li class="nav-item logout"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout (<?= htmlspecialchars($admin['username']) ?>)</a></li>
      </ul>
    </div>
  </header>

  <!-- Main Content -->
  <main class="admin-main">
    
    <!-- Flash Messages -->
    <?php if ($flash): ?>
      <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
        <span><?= htmlspecialchars($flash['message']) ?></span>
        <button type="button" onclick="this.parentElement.remove()" style="background:transparent; border:none; cursor:pointer; font-size:1.1rem; color:inherit;">&times;</button>
      </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title"><i class="fas fa-tachometer-alt"></i> Dashboard Overview</h1>
        <p style="color:var(--text-muted); font-size:0.95rem;">Welcome back, <strong><?= htmlspecialchars($admin['username']) ?></strong>! Here is today's playground activity.</p>
      </div>
      <div class="page-actions">
        <a href="bookings.php" class="btn btn-primary"><i class="fas fa-list"></i> Manage All Bookings</a>
        <a href="export_csv.php" class="btn btn-secondary"><i class="fas fa-download"></i> Download CSV</a>
      </div>
    </div>

    <!-- Stat Metric Cards -->
    <div class="stats-grid">
      <!-- Total -->
      <a href="bookings.php" style="text-decoration:none; color:inherit;">
        <div class="stat-card">
          <div class="stat-icon total"><i class="fas fa-ticket-alt"></i></div>
          <div class="stat-info">
            <h4>Total Bookings</h4>
            <div class="stat-number"><?= number_format($statTotal) ?></div>
          </div>
        </div>
      </a>

      <!-- Today -->
      <a href="bookings.php?date_from=<?= $today ?>&date_to=<?= $today ?>" style="text-decoration:none; color:inherit;">
        <div class="stat-card">
          <div class="stat-icon today"><i class="fas fa-calendar-day"></i></div>
          <div class="stat-info">
            <h4>Today's Visits</h4>
            <div class="stat-number"><?= number_format($statToday) ?></div>
          </div>
        </div>
      </a>

      <!-- Pending -->
      <a href="bookings.php?status=pending" style="text-decoration:none; color:inherit;">
        <div class="stat-card">
          <div class="stat-icon pending"><i class="fas fa-clock"></i></div>
          <div class="stat-info">
            <h4>Pending Action</h4>
            <div class="stat-number" style="color:#B38600;"><?= number_format($statPending) ?></div>
          </div>
        </div>
      </a>

      <!-- Confirmed -->
      <a href="bookings.php?status=confirmed" style="text-decoration:none; color:inherit;">
        <div class="stat-card">
          <div class="stat-icon confirmed"><i class="fas fa-check-circle"></i></div>
          <div class="stat-info">
            <h4>Confirmed</h4>
            <div class="stat-number" style="color:var(--brand-green);"><?= number_format($statConfirmed) ?></div>
          </div>
        </div>
      </a>

      <!-- Cancelled -->
      <a href="bookings.php?status=cancelled" style="text-decoration:none; color:inherit;">
        <div class="stat-card">
          <div class="stat-icon cancelled"><i class="fas fa-times-circle"></i></div>
          <div class="stat-info">
            <h4>Cancelled</h4>
            <div class="stat-number" style="color:#E02424;"><?= number_format($statCancelled) ?></div>
          </div>
        </div>
      </a>
    </div>

    <!-- Recent Bookings Table Card -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title"><i class="fas fa-history" style="color:var(--brand-green);"></i> Recent Bookings (Latest 10)</h2>
        <a href="bookings.php" class="btn btn-sm btn-secondary">View All Bookings &rarr;</a>
      </div>

      <div class="card-body" style="padding:0;">
        <?php if (empty($recentBookings)): ?>
          <div style="padding:3rem; text-align:center; color:var(--text-muted);">
            <i class="fas fa-inbox" style="font-size:3rem; margin-bottom:1rem; opacity:0.4;"></i>
            <p style="font-size:1.1rem; font-weight:700;">No bookings recorded yet.</p>
            <p>New reservations from the website form will appear here automatically.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Ref</th>
                  <th>Parent / Guardian</th>
                  <th>Contact</th>
                  <th>Visit Date & Slot</th>
                  <th>Guests</th>
                  <th>Package</th>
                  <th>Total</th>
                  <th>Status</th>
                  <th style="text-align:right;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentBookings as $b): ?>
                  <tr>
                    <td>
                      <strong style="color:var(--brand-green); font-family:var(--font-heading); font-size:0.95rem;">
                        <?= htmlspecialchars($b['booking_ref']) ?>
                      </strong>
                    </td>
                    <td>
                      <strong><?= htmlspecialchars($b['full_name']) ?></strong>
                    </td>
                    <td>
                      <div><a href="tel:<?= htmlspecialchars($b['phone']) ?>" style="font-weight:700;"><?= htmlspecialchars($b['phone']) ?></a></div>
                      <small style="color:var(--text-muted);"><?= htmlspecialchars($b['email']) ?></small>
                    </td>
                    <td>
                      <div style="font-weight:800; color:var(--brand-orange);"><?= htmlspecialchars($b['booking_date']) ?></div>
                      <small style="color:var(--text-muted);"><?= htmlspecialchars($b['session_time']) ?></small>
                    </td>
                    <td>
                      <?= (int)$b['children_count'] ?> child(ren), <?= (int)$b['adults_count'] ?> adult(s)
                    </td>
                    <td>
                      <span style="font-weight:700; color:var(--brand-green-deep);">
                        <?= htmlspecialchars($b['booking_type_label'] ?? $b['booking_type']) ?>
                      </span>
                    </td>
                    <td>
                      <strong style="color:var(--brand-green);"><?= htmlspecialchars($b['estimated_total']) ?></strong>
                    </td>
                    <td>
                      <form method="POST" action="booking_action.php" style="display:inline-block;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                        <select name="status" class="form-control btn-sm quick-status-select" data-current="<?= htmlspecialchars($b['status']) ?>" style="font-weight:800; cursor:pointer;">
                          <option value="pending" <?= $b['status'] === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                          <option value="confirmed" <?= $b['status'] === 'confirmed' ? 'selected' : '' ?>>✅ Confirmed</option>
                          <option value="cancelled" <?= $b['status'] === 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
                        </select>
                      </form>
                    </td>
                    <td style="text-align:right; white-space:nowrap;">
                      <button type="button" class="btn btn-sm btn-secondary" onclick='viewBookingDetails(<?= json_encode($b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Details">
                        <i class="fas fa-eye"></i> View
                      </button>
                      <button type="button" class="btn btn-sm btn-yellow" onclick='editBooking(<?= json_encode($b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Booking">
                        <i class="fas fa-edit"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </main>

  <!-- View Booking Details Modal -->
  <div class="admin-modal-backdrop" id="viewModal">
    <div class="admin-modal-box">
      <div class="admin-modal-header">
        <h3 class="admin-modal-title"><i class="fas fa-info-circle" style="color:var(--brand-green);"></i> Booking Details</h3>
        <button type="button" class="admin-modal-close" onclick="closeModal('viewModal')">&times;</button>
      </div>
      <div class="admin-modal-body" id="viewModalBody">
        <!-- Dynamically injected via admin.js -->
      </div>
      <div class="admin-modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('viewModal')">Close</button>
      </div>
    </div>
  </div>

  <!-- Edit Booking Modal -->
  <div class="admin-modal-backdrop" id="editModal">
    <div class="admin-modal-box">
      <div class="admin-modal-header">
        <h3 class="admin-modal-title"><i class="fas fa-edit" style="color:var(--brand-green);"></i> Edit Booking</h3>
        <button type="button" class="admin-modal-close" onclick="closeModal('editModal')">&times;</button>
      </div>
      <form method="POST" action="booking_action.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="edit_booking">
        <input type="hidden" name="booking_id" id="editBookingId" value="">

        <div class="admin-modal-body">
          <div class="form-group">
            <label for="editFullName">Parent / Guardian Full Name</label>
            <input type="text" id="editFullName" name="full_name" class="form-control" required>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div class="form-group">
              <label for="editPhone">Phone / WhatsApp</label>
              <input type="text" id="editPhone" name="phone" class="form-control" required>
            </div>
            <div class="form-group">
              <label for="editEmail">Email Address</label>
              <input type="email" id="editEmail" name="email" class="form-control" required>
            </div>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div class="form-group">
              <label for="editBookingDate">Visit Date</label>
              <input type="date" id="editBookingDate" name="booking_date" class="form-control" required>
            </div>
            <div class="form-group">
              <label for="editSessionTime">Session Time</label>
              <input type="text" id="editSessionTime" name="session_time" class="form-control" required>
            </div>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem;">
            <div class="form-group">
              <label for="editChildrenCount">Children</label>
              <input type="number" id="editChildrenCount" name="children_count" class="form-control" min="1" required>
            </div>
            <div class="form-group">
              <label for="editAdultsCount">Adults</label>
              <input type="number" id="editAdultsCount" name="adults_count" class="form-control" min="0" required>
            </div>
            <div class="form-group">
              <label for="editStatus">Status</label>
              <select id="editStatus" name="status" class="form-control">
                <option value="pending">Pending</option>
                <option value="confirmed">Confirmed</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label for="editEstimatedTotal">Estimated Total</label>
            <input type="text" id="editEstimatedTotal" name="estimated_total" class="form-control">
          </div>

          <div class="form-group">
            <label for="editNotes">Customer Special Requests</label>
            <textarea id="editNotes" name="notes" class="form-control" rows="2"></textarea>
          </div>

          <div class="form-group">
            <label for="editAdminNotes">Staff Internal Notes</label>
            <textarea id="editAdminNotes" name="admin_notes" class="form-control" rows="2" placeholder="e.g. Paid via M-Pesa ref QK47XX; socks issued"></textarea>
          </div>
        </div>

        <div class="admin-modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <script src="js/admin.js"></script>
</body>
</html>

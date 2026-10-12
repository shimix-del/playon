<?php
declare(strict_types=1);

/**
 * Play-On Mombasa - Full Bookings Management Table
 * Features Search, Status/Date Filtering, Sorting, Pagination & Modals
 */

require_once __DIR__ . '/auth.php';
requireAdmin();

$admin = getCurrentAdmin();
$pdo   = getDbConnection();
$flash = getFlash();
$csrf  = generateCsrfToken();

// ----------------------------------------------------------------------------
// 1. Query Parameters & Filters
// ----------------------------------------------------------------------------
$search       = trim((string)($_GET['search'] ?? ''));
$status       = trim((string)($_GET['status'] ?? ''));
$bookingType  = trim((string)($_GET['type'] ?? ''));
$dateFrom     = trim((string)($_GET['date_from'] ?? ''));
$dateTo       = trim((string)($_GET['date_to'] ?? ''));
$sort         = trim((string)($_GET['sort'] ?? 'date_desc'));
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 15;
$offset       = ($page - 1) * $perPage;

$whereClauses = ['1=1'];
$params       = [];

if ($search !== '') {
    $whereClauses[] = "(`booking_ref` LIKE :sq1 OR `full_name` LIKE :sq2 OR `phone` LIKE :sq3 OR `email` LIKE :sq4)";
    $params[':sq1'] = "%{$search}%";
    $params[':sq2'] = "%{$search}%";
    $params[':sq3'] = "%{$search}%";
    $params[':sq4'] = "%{$search}%";
}

if ($status !== '' && in_array($status, ['pending', 'confirmed', 'cancelled'], true)) {
    $whereClauses[] = "`status` = :st";
    $params[':st'] = $status;
}

if ($bookingType !== '' && in_array($bookingType, ['session', 'birthday', 'school', 'private'], true)) {
    $whereClauses[] = "`booking_type` = :btype";
    $params[':btype'] = $bookingType;
}

if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $whereClauses[] = "`booking_date` >= :dfrom";
    $params[':dfrom'] = $dateFrom;
}

if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $whereClauses[] = "`booking_date` <= :dto";
    $params[':dto'] = $dateTo;
}

$whereSql = implode(' AND ', $whereClauses);

// Determine Order By clause
$orderSql = match ($sort) {
    'date_asc'     => "`booking_date` ASC, `id` ASC",
    'created_desc' => "`id` DESC",
    'created_asc'  => "`id` ASC",
    'name_asc'     => "`full_name` ASC",
    default        => "`booking_date` DESC, `id` DESC"
};

// ----------------------------------------------------------------------------
// 2. Fetch Total Count & Paginated Rows
// ----------------------------------------------------------------------------
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM `bookings` WHERE {$whereSql}");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$sql = "SELECT * FROM `bookings` WHERE {$whereSql} ORDER BY {$orderSql} LIMIT {$perPage} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Build URL query string helper for pagination
function buildFilterQuery(array $overrides = []): string {
    $current = $_GET;
    $merged = array_merge($current, $overrides);
    return http_build_query($merged);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Bookings | Play-On Mombasa Admin</title>
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
        <li class="nav-item"><a href="index.php"><i class="fas fa-chart-pie"></i> Overview</a></li>
        <li class="nav-item"><a href="bookings.php" class="active"><i class="fas fa-calendar-check"></i> All Bookings</a></li>
        <li class="nav-item"><a href="export_csv.php?<?= http_build_query($_GET) ?>"><i class="fas fa-file-csv"></i> Export Filtered CSV</a></li>
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
        <h1 class="page-title"><i class="fas fa-calendar-alt"></i> Bookings Management</h1>
        <p style="color:var(--text-muted); font-size:0.95rem;">
          Showing <strong><?= count($bookings) ?></strong> of <strong><?= number_format($totalRows) ?></strong> total reservation(s).
        </p>
      </div>
      <div class="page-actions">
        <a href="export_csv.php?<?= http_build_query($_GET) ?>" class="btn btn-secondary">
          <i class="fas fa-file-csv"></i> Export Results (CSV)
        </a>
      </div>
    </div>

    <!-- Search & Filters Card -->
    <div class="card" style="margin-bottom:1.5rem;">
      <div class="card-body" style="padding:1.25rem 1.5rem;">
        <form method="GET" action="bookings.php" class="filter-form">
          <!-- Keyword Search -->
          <input type="text" name="search" class="form-control search-input" placeholder="Search by name, ref, phone, email..." value="<?= htmlspecialchars($search) ?>">

          <!-- Status Filter -->
          <select name="status" class="form-control">
            <option value="">Status: All</option>
            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
            <option value="confirmed" <?= $status === 'confirmed' ? 'selected' : '' ?>>✅ Confirmed</option>
            <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
          </select>

          <!-- Type Filter -->
          <select name="type" class="form-control">
            <option value="">Package: All</option>
            <option value="session" <?= $bookingType === 'session' ? 'selected' : '' ?>>Standard Session</option>
            <option value="birthday" <?= $bookingType === 'birthday' ? 'selected' : '' ?>>Birthday Package</option>
            <option value="school" <?= $bookingType === 'school' ? 'selected' : '' ?>>School Group Trip</option>
            <option value="private" <?= $bookingType === 'private' ? 'selected' : '' ?>>Exclusive Private Hire</option>
          </select>

          <!-- Date Range -->
          <div style="display:flex; align-items:center; gap:0.35rem;">
            <input type="date" name="date_from" class="form-control" title="Date From" value="<?= htmlspecialchars($dateFrom) ?>">
            <span style="color:var(--text-muted); font-weight:700;">to</span>
            <input type="date" name="date_to" class="form-control" title="Date To" value="<?= htmlspecialchars($dateTo) ?>">
          </div>

          <!-- Sort -->
          <select name="sort" class="form-control">
            <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>Visit Date (Newest first)</option>
            <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>>Visit Date (Oldest first)</option>
            <option value="created_desc" <?= $sort === 'created_desc' ? 'selected' : '' ?>>Submitted (Newest)</option>
            <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Parent Name (A - Z)</option>
          </select>

          <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply Filters</button>

          <?php if ($search !== '' || $status !== '' || $bookingType !== '' || $dateFrom !== '' || $dateTo !== '' || $sort !== 'date_desc'): ?>
            <a href="bookings.php" class="btn btn-secondary" title="Reset all filters"><i class="fas fa-undo"></i> Reset</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Main Bookings Table Card -->
    <div class="card">
      <div class="card-body" style="padding:0;">
        <?php if (empty($bookings)): ?>
          <div style="padding:4rem 2rem; text-align:center; color:var(--text-muted);">
            <i class="fas fa-search" style="font-size:3rem; margin-bottom:1rem; opacity:0.3;"></i>
            <h3 style="font-family:var(--font-heading); color:var(--brand-green-deep);">No bookings found</h3>
            <p>Try adjusting your search criteria or filter options above.</p>
            <a href="bookings.php" class="btn btn-secondary" style="margin-top:1rem;">Clear All Filters</a>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Ref</th>
                  <th>Parent / Guardian</th>
                  <th>Phone / WhatsApp</th>
                  <th>Email</th>
                  <th>Visit Date & Slot</th>
                  <th>Guests</th>
                  <th>Package</th>
                  <th>Est. Total</th>
                  <th>Status</th>
                  <th style="text-align:right;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($bookings as $b): ?>
                  <tr>
                    <!-- Ref -->
                    <td>
                      <strong style="color:var(--brand-green); font-family:var(--font-heading); font-size:0.95rem;">
                        <?= htmlspecialchars($b['booking_ref']) ?>
                      </strong>
                    </td>

                    <!-- Name -->
                    <td>
                      <strong><?= htmlspecialchars($b['full_name']) ?></strong>
                      <?php if (!empty($b['notes'])): ?>
                        <div title="<?= htmlspecialchars($b['notes']) ?>" style="font-size:0.78rem; color:#8A6400; cursor:pointer;">
                          <i class="fas fa-sticky-note"></i> Has notes
                        </div>
                      <?php endif; ?>
                    </td>

                    <!-- Phone -->
                    <td>
                      <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $b['phone']) ?>" target="_blank" style="font-weight:700; color:var(--brand-green);">
                        <i class="fab fa-whatsapp"></i> <?= htmlspecialchars($b['phone']) ?>
                      </a>
                    </td>

                    <!-- Email -->
                    <td>
                      <a href="mailto:<?= htmlspecialchars($b['email']) ?>" style="color:inherit; font-size:0.88rem;">
                        <?= htmlspecialchars($b['email']) ?>
                      </a>
                    </td>

                    <!-- Date & Slot -->
                    <td>
                      <div style="font-weight:800; color:var(--brand-orange);"><?= htmlspecialchars($b['booking_date']) ?></div>
                      <small style="color:var(--text-muted); font-size:0.8rem;"><?= htmlspecialchars($b['session_time']) ?></small>
                    </td>

                    <!-- Guests -->
                    <td style="white-space:nowrap;">
                      <strong><?= (int)$b['children_count'] ?></strong> child(ren)<br>
                      <small style="color:var(--text-muted);"><?= (int)$b['adults_count'] ?> adult(s)</small>
                    </td>

                    <!-- Package -->
                    <td>
                      <span style="font-weight:700; color:var(--brand-green-deep);">
                        <?= htmlspecialchars($b['booking_type_label'] ?? $b['booking_type']) ?>
                      </span>
                    </td>

                    <!-- Total -->
                    <td>
                      <strong style="color:var(--brand-green);"><?= htmlspecialchars($b['estimated_total']) ?></strong>
                    </td>

                    <!-- Quick Status Dropdown -->
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

                    <!-- Action Buttons -->
                    <td style="text-align:right; white-space:nowrap;">
                      <button type="button" class="btn btn-sm btn-secondary" onclick='viewBookingDetails(<?= json_encode($b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Details">
                        <i class="fas fa-eye"></i>
                      </button>
                      <button type="button" class="btn btn-sm btn-yellow" onclick='editBooking(<?= json_encode($b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Booking">
                        <i class="fas fa-edit"></i>
                      </button>
                      <form method="POST" action="booking_action.php" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to permanently delete booking <?= htmlspecialchars($b['booking_ref']) ?>?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="action" value="delete_booking">
                        <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger" title="Delete Booking">
                          <i class="fas fa-trash-alt"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- Pagination Bar -->
          <?php if ($totalPages > 1): ?>
            <div style="padding:1.25rem 1.5rem; border-top:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
              <span style="font-size:0.88rem; color:var(--text-muted);">
                Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong> (<?= number_format($totalRows) ?> total bookings)
              </span>

              <div class="pagination">
                <?php if ($page > 1): ?>
                  <a href="?<?= buildFilterQuery(['page' => $page - 1]) ?>" class="page-link">&laquo; Prev</a>
                <?php else: ?>
                  <span class="page-link disabled">&laquo; Prev</span>
                <?php endif; ?>

                <?php
                $startP = max(1, $page - 2);
                $endP   = min($totalPages, $page + 2);
                for ($p = $startP; $p <= $endP; $p++):
                ?>
                  <a href="?<?= buildFilterQuery(['page' => $p]) ?>" class="page-link <?= $p === $page ? 'active' : '' ?>">
                    <?= $p ?>
                  </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                  <a href="?<?= buildFilterQuery(['page' => $page + 1]) ?>" class="page-link">Next &raquo;</a>
                <?php else: ?>
                  <span class="page-link disabled">Next &raquo;</span>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

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

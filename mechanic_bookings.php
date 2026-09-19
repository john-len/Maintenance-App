<?php
session_start();
require 'db.php';
require 'motorcycle_health_helper.php';

// Security: mechanic only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'mechanic') {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Find the mechanic record linked to this user
$mechanic_stmt = $pdo->prepare("SELECT m.*, u.email, u.phone, u.address FROM mechanics m LEFT JOIN users u ON m.user_id = u.id WHERE m.user_id = ?");
$mechanic_stmt->execute([$user_id]);
$mechanic = $mechanic_stmt->fetch(PDO::FETCH_ASSOC);

if (!$mechanic) {
    die("Your mechanic profile is missing. Please contact the administrator.");
}

$mechanic_id = $mechanic['id'];

// Sync mechanic status based on open work
$sync_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM emergency_service_requests
    WHERE assigned_mechanic_id = ? AND request_status IN ('assigned','accepted','in_progress')
");
$sync_stmt->execute([$mechanic_id]);
$open_emergency_count = (int) $sync_stmt->fetchColumn();

if ($open_emergency_count > 0) {
    $pdo->prepare("UPDATE mechanics SET status = 'Busy' WHERE id = ? AND status != 'Busy'")->execute([$mechanic_id]);
} else {
    $booking_check = $pdo->prepare("
        SELECT COUNT(*) FROM bookings
        WHERE mechanic_id = ? AND status IN ('assigned','accepted','in_progress')
    ");
    $booking_check->execute([$mechanic_id]);
    $open_bookings = (int) $booking_check->fetchColumn();

    $current = $pdo->prepare("SELECT current_booking_id FROM mechanics WHERE id = ?");
    $current->execute([$mechanic_id]);
    $current_booking = $current->fetchColumn();

    if ($open_bookings === 0 && empty($current_booking)) {
        $pdo->prepare("UPDATE mechanics SET status = 'Available' WHERE id = ? AND status != 'Available'")->execute([$mechanic_id]);
    }
}

// Handle job status actions
$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);

    try {
        if (!empty($_POST['start_job']) && $booking_id) {
            $pdo->beginTransaction();

            $check = $pdo->prepare("SELECT id FROM bookings WHERE id = ? AND mechanic_id = ? AND status IN ('assigned','accepted')");
            $check->execute([$booking_id, $mechanic_id]);
            if ($check->fetch()) {
                $pdo->prepare("UPDATE bookings SET status = 'in_progress' WHERE id = ?")->execute([$booking_id]);
                $pdo->prepare("UPDATE mechanics SET status = 'Busy', current_booking_id = ? WHERE id = ?")->execute([$booking_id, $mechanic_id]);

                $pdo->commit();
                $msg = "✅ Job #{$booking_id} started.";
                $msg_type = "success";
            } else {
                $pdo->rollBack();
                $msg = "❌ Job cannot be started.";
                $msg_type = "error";
            }
        } elseif (!empty($_POST['complete_job']) && $booking_id) {
            $pdo->beginTransaction();

            $check = $pdo->prepare("SELECT id FROM bookings WHERE id = ? AND mechanic_id = ? AND status IN ('in_progress','assigned','accepted')");
            $check->execute([$booking_id, $mechanic_id]);
            if ($check->fetch()) {
                $pdo->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?")->execute([$booking_id]);
                recordCompletedBookingHistory($booking_id);
                $pdo->prepare("UPDATE mechanics SET current_booking_id = NULL WHERE id = ?")->execute([$mechanic_id]);

                $busy_check = $pdo->prepare("
                    SELECT
                        (SELECT COUNT(*) FROM emergency_service_requests WHERE assigned_mechanic_id = ? AND request_status IN ('assigned','accepted','in_progress')) +
                        (SELECT COUNT(*) FROM bookings WHERE mechanic_id = ? AND status IN ('assigned','accepted','in_progress')) +
                        (SELECT COUNT(*) FROM booking_mechanics bm JOIN bookings b ON b.id = bm.booking_id WHERE bm.mechanic_id = ? AND b.status IN ('assigned','accepted','in_progress')) +
                        (SELECT COUNT(*) FROM mechanics WHERE id = ? AND current_booking_id IS NOT NULL)
                ");
                $busy_check->execute([$mechanic_id, $mechanic_id, $mechanic_id, $mechanic_id]);
                if ((int)$busy_check->fetchColumn() === 0) {
                    $pdo->prepare("UPDATE mechanics SET status = 'Available' WHERE id = ?")->execute([$mechanic_id]);
                }

                $pdo->commit();
                $msg = "✅ Job #{$booking_id} completed.";
                $msg_type = "success";
            } else {
                $pdo->rollBack();
                $msg = "❌ Invalid job or job is not in progress.";
                $msg_type = "error";
            }
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Mechanic bookings error: " . $e->getMessage());
        $msg = "❌ Database error: " . $e->getMessage();
        $msg_type = "error";
    }
}

// Fetch all jobs with customer + motorcycle details
$jobs_stmt = $pdo->prepare("
    SELECT 
        b.id, 
        b.schedule_date, 
        b.schedule_start_time, 
        b.schedule_end_time,
        b.status,
        b.service_ids,
        b.total_price,
        u.id as customer_id,
        u.username as customer_name,
        u.phone as customer_phone,
        u.email as customer_email,
        u.address as customer_address,
        m.id as motorcycle_id,
        m.brand,
        m.model,
        m.year_model,
        m.plate_number,
        m.color,
        m.current_mileage
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    LEFT JOIN motorcycles m ON b.vehicle_id = m.id
    WHERE b.mechanic_id = ?
    ORDER BY 
        CASE b.status
            WHEN 'assigned' THEN 1
            WHEN 'accepted' THEN 2
            WHEN 'in_progress' THEN 3
            WHEN 'completed' THEN 4
            ELSE 5
        END,
        b.schedule_date DESC,
        b.schedule_start_time ASC
");
$jobs_stmt->execute([$mechanic_id]);
$all_jobs = $jobs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch service names
try {
    $stmt = $pdo->query("SELECT id, service_name, price FROM services");
    $services = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $services = [];
}

function getServiceText($service_ids_json, $services) {
    $service_ids = [];
    if (!empty($service_ids_json)) {
        $decoded = json_decode($service_ids_json, true);
        if (is_array($decoded)) {
            $service_ids = $decoded;
        } elseif (is_string($service_ids_json)) {
            $service_ids = array_map('trim', explode(',', $service_ids_json));
        }
    }
    $labels = [];
    foreach ($service_ids as $sid) {
        if (isset($services[$sid])) {
            $labels[] = $services[$sid];
        }
    }
    return !empty($labels) ? implode(', ', $labels) : 'N/A';
}

function getStatusClass($status) {
    return match ($status) {
        'completed' => 'bg-success',
        'assigned' => 'bg-primary',
        'accepted' => 'bg-info',
        'in_progress' => 'bg-warning text-dark',
        default => 'bg-secondary'
    };
}

function renderJobTable($jobs, $services) {
    if (empty($jobs)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-inbox fs-1 d-block mb-3"></i>
            <p class="mb-0">No bookings assigned.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive-card">
            <table class="table emergency-table align-middle">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Booking #</th>
                        <th class="d-none d-md-table-cell">Vehicle</th>
                        <th class="d-none d-md-table-cell">Schedule</th>
                        <th class="d-none d-md-table-cell">Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobs as $job):
                        $vehicle_text = ($job['brand'] ? $job['brand'] . ' ' . $job['model'] : 'N/A') . ($job['plate_number'] ? ' (' . $job['plate_number'] . ')' : '');
                        $status_class = getStatusClass($job['status']);
                    ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($job['customer_name']) ?>
                                <br><small class="text-muted"><?= htmlspecialchars($job['customer_phone'] ?? '') ?></small>
                            </td>
                            <td class="fw-bold">#<?= $job['id'] ?></td>
                            <td class="d-none d-md-table-cell"><?= htmlspecialchars($vehicle_text) ?></td>
                            <td class="d-none d-md-table-cell">
                                <?= date('M d, Y', strtotime($job['schedule_date'])) ?>
                                <br><small class="text-muted"><?= date('g:i A', strtotime($job['schedule_start_time'])) ?> - <?= date('g:i A', strtotime($job['schedule_end_time'])) ?></small>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <span class="badge rounded-pill <?= $status_class ?>"><?= htmlspecialchars(ucfirst($job['status'])) ?></span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#bookingModal<?= $job['id'] ?>" title="View details">
                                        <i class="bi bi-eye d-inline d-md-none"></i><span class="d-none d-md-inline">Details</span>
                                    </button>
                                    <?php if ($job['status'] === 'assigned' || $job['status'] === 'accepted'): ?>
                                        <form method="post" action="" class="d-inline" onsubmit="return confirm('Start this job?');">
                                            <input type="hidden" name="booking_id" value="<?= $job['id'] ?>">
                                            <button type="submit" name="start_job" class="btn btn-sm btn-warning text-dark" title="Start job">
                                                <i class="bi bi-play-fill d-inline d-md-none"></i><span class="d-none d-md-inline">Start</span>
                                            </button>
                                        </form>
                                    <?php elseif ($job['status'] === 'in_progress'): ?>
                                        <form method="post" action="" class="d-inline" onsubmit="return confirm('Mark this job as completed?');">
                                            <input type="hidden" name="booking_id" value="<?= $job['id'] ?>">
                                            <button type="submit" name="complete_job" class="btn btn-sm btn-success" title="Complete job">
                                                <i class="bi bi-check-lg d-inline d-md-none"></i><span class="d-none d-md-inline">Complete</span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif;
}

function renderJobModal($job) {
    $service_text = getServiceText($job['service_ids'], []);
    $vehicle_text = ($job['brand'] ? $job['brand'] . ' ' . $job['model'] : 'N/A') . ($job['plate_number'] ? ' (' . $job['plate_number'] . ')' : '');

    $status_class = match ($job['status']) {
        'completed' => 'bg-success',
        'assigned' => 'bg-primary',
        'accepted' => 'bg-info',
        'in_progress' => 'bg-warning text-dark',
        default => 'bg-secondary'
    };

    $color_lower = strtolower($job['color'] ?? '');
    switch ($color_lower) {
        case 'red': $color_class = 'bg-danger'; break;
        case 'blue': $color_class = 'bg-primary'; break;
        case 'green': $color_class = 'bg-success'; break;
        case 'yellow': case 'gold': $color_class = 'bg-warning text-dark'; break;
        case 'black': $color_class = 'bg-dark'; break;
        case 'white': $color_class = 'bg-light text-dark border'; break;
        default: $color_class = 'bg-secondary';
    }
    ?>
    <div class="modal fade" id="bookingModal<?= $job['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
            <div class="modal-content emergency-modal">
                <div class="modal-header emergency-modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-check text-warning-custom me-2"></i>Booking #<?= $job['id'] ?></h5>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge rounded-pill <?= $status_class ?>"><?= htmlspecialchars(ucfirst($job['status'])) ?></span>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body emergency-modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="emergency-card customer-card h-100">
                                <div class="emergency-card-header customer-header">
                                    <i class="bi bi-person me-2"></i>Customer
                                </div>
                                <div class="emergency-card-body">
                                    <div class="d-flex gap-3">
                                        <div class="customer-avatar">
                                            <i class="bi bi-person"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1 fw-bold"><?= htmlspecialchars($job['customer_name']) ?></h6>
                                            <p class="mb-1 text-muted small"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($job['customer_phone'] ?? '-') ?></p>
                                            <p class="mb-0 text-muted small"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($job['customer_address'] ?? '-') ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="emergency-card motorcycle-card h-100">
                                <div class="emergency-card-header motorcycle-header">
                                    <i class="bi bi-motorcycle me-2"></i>Motorcycle
                                </div>
                                <div class="emergency-card-body">
                                    <div class="d-flex gap-3 align-items-center">
                                        <div class="motorcycle-info flex-grow-1">
                                            <h6 class="mb-1 fw-bold"><?= htmlspecialchars(($job['brand'] ? $job['brand'] . ' ' . $job['model'] : 'N/A')) ?></h6>
                                            <p class="mb-2 text-muted small">Plate: <?= htmlspecialchars($job['plate_number'] ?? '-') ?></p>
                                            <div class="d-flex gap-2">
                                                <span class="badge rounded-pill <?= $color_class ?>"><?= htmlspecialchars(ucfirst($job['color'] ?? 'N/A')) ?></span>
                                                <span class="badge rounded-pill year-badge"><?= htmlspecialchars($job['year_model'] ?? '-') ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="emergency-card booking-info-card mt-3">
                        <div class="emergency-card-header info-header">
                            <i class="bi bi-file-text me-2"></i>Booking Information
                        </div>
                        <div class="emergency-card-body">
                            <div class="row g-3">
                                <div class="col-6 col-md-3 text-center">
                                    <p class="text-muted mb-1 small">Status</p>
                                    <span class="badge rounded-pill <?= $status_class ?>"><?= htmlspecialchars(ucfirst($job['status'])) ?></span>
                                </div>
                                <div class="col-6 col-md-3 text-center info-divider">
                                    <p class="text-muted mb-1 small">Date</p>
                                    <p class="mb-0 small fw-semibold"><i class="bi bi-calendar me-1 text-muted"></i><?= $job['schedule_date'] ? date('M d, Y', strtotime($job['schedule_date'])) : '-' ?></p>
                                </div>
                                <div class="col-6 col-md-3 text-center info-divider">
                                    <p class="text-muted mb-1 small">Time</p>
                                    <p class="mb-0 small fw-semibold"><i class="bi bi-clock me-1 text-muted"></i><?= $job['schedule_start_time'] ? date('g:i A', strtotime($job['schedule_start_time'])) . ' - ' . date('g:i A', strtotime($job['schedule_end_time'])) : '-' ?></p>
                                </div>
                                <div class="col-6 col-md-3 text-center info-divider">
                                    <p class="text-muted mb-1 small">Price</p>
                                    <p class="mb-0 small fw-semibold">₱<?= number_format($job['total_price'], 2) ?></p>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-12">
                                    <p class="text-muted mb-1 small"><i class="bi bi-wrench me-1"></i>Services</p>
                                    <p class="mb-0"><?= htmlspecialchars($service_text) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <?php if ($job['status'] === 'assigned' || $job['status'] === 'accepted'): ?>
                        <form method="post" action="" class="me-auto" onsubmit="return confirm('Start this job?');">
                            <input type="hidden" name="booking_id" value="<?= $job['id'] ?>">
                            <button type="submit" name="start_job" class="btn btn-warning text-dark"><i class="bi bi-play-fill me-2"></i>Start Job</button>
                        </form>
                    <?php elseif ($job['status'] === 'in_progress'): ?>
                        <form method="post" action="" class="me-auto" onsubmit="return confirm('Mark this job as completed?');">
                            <input type="hidden" name="booking_id" value="<?= $job['id'] ?>">
                            <button type="submit" name="complete_job" class="btn btn-success"><i class="bi bi-check-lg me-2"></i>Mark as Complete</button>
                        </form>
                    <?php endif; ?>
                    <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php
}

$pageTitle = 'My Bookings';
include 'mechanic_sidebar.php';
?>
<style>
    .emergency-table,
    .emergency-table thead,
    .emergency-table tbody,
    .emergency-table tfoot,
    .emergency-table tr,
    .emergency-table th,
    .emergency-table td {
        background-color: transparent !important;
    }
    .emergency-table tbody tr:hover {
        background-color: transparent !important;
    }

    .emergency-modal {
        border: none;
        border-radius: 16px;
    }
    .emergency-modal-header {
        background: #ffffff !important;
        color: #1e293b !important;
        border-bottom: none;
        position: relative;
        padding: 1rem 1.25rem;
    }
    .emergency-modal-header::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg,
            transparent 0%,
            rgba(245, 158, 11, 0.8) 20%,
            rgba(59, 130, 246, 0.8) 50%,
            rgba(245, 158, 11, 0.8) 80%,
            transparent 100%
        );
    }
    .emergency-modal-header .modal-title {
        font-weight: 700;
        font-size: 1.05rem;
    }
    .emergency-modal-body {
        background: #f8fafc;
        padding: 1.25rem;
    }
    .emergency-modal .modal-footer {
        background: #f8fafc;
        padding: 0 1.25rem 1.25rem;
    }

    .emergency-card {
        background: #ffffff;
        border-radius: 14px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        overflow: hidden;
    }
    .emergency-card-header {
        padding: 0.65rem 1rem;
        font-size: 0.85rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .emergency-card-body {
        padding: 1rem;
    }

    .customer-header {
        background: #eff6ff;
        color: #1d4ed8;
    }
    .customer-avatar {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: #dbeafe;
        color: #1d4ed8;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .motorcycle-header {
        background: #fff7ed;
        color: #c2410c;
    }

    .year-badge {
        background: #f1f5f9 !important;
        color: #475569 !important;
        border: 1px solid #e2e8f0;
    }

    .info-header, .location-header {
        background: #eff6ff;
        color: #1d4ed8;
    }
    .info-divider {
        border-left: 1px solid #e2e8f0;
    }

    .booking-info-card {
        border-left: 4px solid #3b82f6;
    }

    @media (max-width: 767.98px) {
        .emergency-card-body .row .col-6,
        .emergency-card-body .row .col-md-3 {
            border-left: none !important;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0.75rem;
        }
        .emergency-card-body .row .col-6:last-child,
        .emergency-card-body .row .col-md-3:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
    }
</style>
<div class="content-area">
    <div class="container-fluid">
        <h2 class="fw-bold mb-4"><i class="bi bi-calendar-check me-2 text-warning-custom"></i>My Bookings</h2>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                <?= $msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="p-4">
            <h5 class="fw-bold mb-3">Assigned Bookings</h5>
            <?php renderJobTable($all_jobs, $services); ?>
        </div>
    </div>
</div>

<?php foreach ($all_jobs as $job) { renderJobModal($job); } ?>
<?php include 'mechanic_sidebar_footer.php'; ?>

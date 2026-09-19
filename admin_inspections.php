<?php
session_start();
require 'db.php';
require 'motorcycle_health_helper.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$msg = "";
$msg_type = "";

// Add inspection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_inspection'])) {
    $motorcycle_id = $_POST['motorcycle_id'] ?? 0;
    $inspection_date = $_POST['inspection_date'] ?? '';
    $engine = $_POST['engine'] ?? 'Good';
    $brakes = $_POST['brakes'] ?? 'Good';
    $tires = $_POST['tires'] ?? 'Good';
    $battery = $_POST['battery'] ?? 'Good';
    $lights = $_POST['lights'] ?? 'Good';
    $suspension = $_POST['suspension'] ?? 'Good';
    $fluids = $_POST['fluids'] ?? 'Good';
    $mechanic_remarks = $_POST['mechanic_remarks'] ?? '';
    $performed_by = $_POST['performed_by'] ?? '';

    if (empty($motorcycle_id) || empty($inspection_date)) {
        $msg = "❌ Please fill in all required fields.";
        $msg_type = "error";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO motorcycle_inspections
                (motorcycle_id, inspection_date, engine, brakes, tires, battery, lights, suspension, fluids, mechanic_remarks, performed_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $motorcycle_id, $inspection_date, $engine, $brakes, $tires,
                $battery, $lights, $suspension, $fluids, $mechanic_remarks, $performed_by
            ]);

            updateMotorcycleHealthScore($motorcycle_id);

            $msg = "✅ Inspection recorded successfully!";
            $msg_type = "success";
        } catch (PDOException $e) {
            $msg = "❌ Error recording inspection: " . $e->getMessage();
            $msg_type = "error";
            error_log("Inspection error: " . $e->getMessage());
        }
    }
}

// Delete inspection
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("SELECT motorcycle_id FROM motorcycle_inspections WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $del = $pdo->prepare("DELETE FROM motorcycle_inspections WHERE id = ?");
            $del->execute([$_GET['delete']]);
            updateMotorcycleHealthScore($row['motorcycle_id']);
            $msg = "✅ Inspection deleted.";
            $msg_type = "success";
        }
    } catch (PDOException $e) {
        $msg = "❌ Error deleting inspection.";
        $msg_type = "error";
    }
}

// Fetch motorcycles for dropdown
$motorcycles = [];
$inspections = [];
try {
    $motorcycles = $pdo->query("
        SELECT m.id, m.brand, m.model, m.plate_number, u.username as customer_name
        FROM motorcycles m
        JOIN users u ON m.user_id = u.id
        ORDER BY u.username, m.brand
    ")->fetchAll(PDO::FETCH_ASSOC);

    $inspections = $pdo->query("
        SELECT mi.*, CONCAT(m.brand, ' ', m.model, ' (', m.plate_number, ')') as motorcycle_info, u.username as customer_name
        FROM motorcycle_inspections mi
        JOIN motorcycles m ON mi.motorcycle_id = m.id
        JOIN users u ON m.user_id = u.id
        ORDER BY mi.inspection_date DESC, mi.id DESC
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching inspection data: " . $e->getMessage());
}

$components = ['engine', 'brakes', 'tires', 'battery', 'lights', 'suspension', 'fluids'];
$options = ['Good', 'Fair', 'Needs Attention', 'Critical'];

$pageTitle = 'Mechanic Inspections';
require 'admin_sidebar_template.php';
?>

<div class="container-fluid py-4">
    <?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show" role="alert">
        <?= $msg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Record Inspection</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="add_inspection" value="1">
                        <div class="mb-3">
                            <label class="form-label">Motorcycle</label>
                            <select name="motorcycle_id" class="form-select" required>
                                <option value="">Select Motorcycle</option>
                                <?php foreach ($motorcycles as $m): ?>
                                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['customer_name'] . ' - ' . $m['brand'] . ' ' . $m['model'] . ' (' . $m['plate_number'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Inspection Date</label>
                            <input type="date" name="inspection_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <?php foreach ($components as $comp): ?>
                        <div class="mb-3">
                            <label class="form-label text-capitalize"><?= $comp ?></label>
                            <select name="<?= $comp ?>" class="form-select">
                                <?php foreach ($options as $opt): ?>
                                    <option value="<?= $opt ?>" <?= $opt === 'Good' ? 'selected' : '' ?>><?= $opt ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endforeach; ?>
                        <div class="mb-3">
                            <label class="form-label">Mechanic Remarks</label>
                            <textarea name="mechanic_remarks" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Performed By</label>
                            <input type="text" name="performed_by" class="form-control">
                        </div>
                        <button type="submit" class="btn btn-primary">Save Inspection</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Recent Inspections</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($inspections)): ?>
                        <p class="text-muted">No inspections recorded yet.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Motorcycle</th>
                                    <th>Engine</th>
                                    <th>Brakes</th>
                                    <th>Tires</th>
                                    <th>Battery</th>
                                    <th>Lights</th>
                                    <th>Suspension</th>
                                    <th>Fluids</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inspections as $i): ?>
                                <tr>
                                    <td><?= date('M j, Y', strtotime($i['inspection_date'])) ?></td>
                                    <td><?= htmlspecialchars($i['motorcycle_info']) ?></td>
                                    <td><?= $i['engine'] ?></td>
                                    <td><?= $i['brakes'] ?></td>
                                    <td><?= $i['tires'] ?></td>
                                    <td><?= $i['battery'] ?></td>
                                    <td><?= $i['lights'] ?></td>
                                    <td><?= $i['suspension'] ?></td>
                                    <td><?= $i['fluids'] ?></td>
                                    <td>
                                        <a href="?delete=<?= $i['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this inspection?')">Delete</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'admin_footer.php'; ?>

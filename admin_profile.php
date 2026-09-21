<?php
session_start();
require 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$msg = '';
$msg_type = 'success';

// Fetch admin details
$user = [];
try {
    $stmt = $pdo->prepare("SELECT id, username, email, phone, address FROM users WHERE id = ? AND role = 'admin'");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    $msg = 'Database error: ' . $e->getMessage();
    $msg_type = 'danger';
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');

    if (empty($username) || empty($email)) {
        $msg = '❌ Full Name and Email are required.';
        $msg_type = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = '❌ Invalid email format.';
        $msg_type = 'danger';
    } else {
        try {
            // Ensure email is not already in use by another account
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->execute([$email, $user_id]);
            if ($check->rowCount() > 0) {
                $msg = '❌ Email is already in use by another account.';
                $msg_type = 'danger';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, phone = ?, address = ? WHERE id = ? AND role = 'admin'");
                $stmt->execute([$username, $email, $phone, $address, $user_id]);

                // Update session username if changed
                $_SESSION['username'] = $username;

                // Refresh displayed data
                $stmt = $pdo->prepare("SELECT id, username, email, phone, address FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                $msg = '✅ Profile updated successfully.';
                $msg_type = 'success';
            }
        } catch (PDOException $e) {
            $msg = '❌ Error updating profile: ' . $e->getMessage();
            $msg_type = 'danger';
        }
    }
}

$pageTitle = 'Profile Settings';
?>

<?php require 'admin_sidebar_template.php'; ?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0 text-primary"><i class="bi bi-person-gear me-2"></i>Admin Profile Settings</h2>
                    <a href="dashboard_admin.php" class="btn btn-outline-primary"><i class="bi bi-arrow-left-circle me-1"></i> Back to Dashboard</a>
                </div>

                <?php if (!empty($msg)): ?>
                    <div class="alert alert-<?= $msg_type ?> text-center mb-4 fw-bold"><?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label for="username" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i> Update Profile
                        </button>
                        <a href="dashboard_admin.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require 'admin_sidebar_footer.php'; ?>
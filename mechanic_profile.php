<?php
session_start();
require 'db.php';

// Security: mechanic only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'mechanic') {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch mechanic + user data
$stmt = $pdo->prepare("
    SELECT u.*, m.name as mechanic_name, m.status as mechanic_status, m.id as mechanic_id
    FROM users u
    LEFT JOIN mechanics m ON u.id = m.user_id
    WHERE u.id = ? AND u.role = 'mechanic'
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Account not found.");
}

$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($name)) {
        $msg = "❌ Name is required.";
        $msg_type = "error";
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "❌ Please provide a valid email.";
        $msg_type = "error";
    } else {
        try {
            // Check email uniqueness
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->execute([$email, $user_id]);
            if ($check->rowCount() > 0) {
                $msg = "❌ Email is already in use.";
                $msg_type = "error";
            } else {
                $pdo->beginTransaction();

                // Update users table
                if (!empty($password)) {
                    if (strlen($password) < 6) {
                        throw new Exception("Password must be at least 6 characters.");
                    }
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $update = $pdo->prepare("UPDATE users SET username = ?, name = ?, email = ?, phone = ?, address = ?, password = ? WHERE id = ?");
                    $update->execute([$name, $name, $email, $phone, $address, $hashed, $user_id]);
                } else {
                    $update = $pdo->prepare("UPDATE users SET username = ?, name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
                    $update->execute([$name, $name, $email, $phone, $address, $user_id]);
                }

                // Also update mechanic name to match
                $pdo->prepare("UPDATE mechanics SET name = ? WHERE user_id = ?")->execute([$name, $user_id]);

                $pdo->commit();
                $msg = "✅ Profile updated successfully.";
                $msg_type = "success";

                // Refresh session
                $_SESSION['username'] = $name;

                // Re-fetch user
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $msg = "❌ " . $e->getMessage();
            $msg_type = "error";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $msg = "❌ Database error: " . $e->getMessage();
            $msg_type = "error";
        }
    }
}
?>
<?php
$pageTitle = 'My Profile';
include 'mechanic_sidebar.php';
?>
<div class="content-area">
    <div class="container-fluid">
        <h2 class="fw-bold mb-4"><i class="bi bi-person me-2 text-warning-custom"></i>My Profile</h2>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                <?= $msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-12 col-lg-8">
                <div class="main-card p-4">
                    <h5 class="fw-bold mb-4">Edit Profile</h5>
                    <form method="POST" action="mechanic_profile.php">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($user['mechanic_name'] ?? $user['name'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label fw-semibold">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label fw-semibold">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">New Password</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Leave blank to keep current password">
                            <small class="text-muted">Only fill this in if you want to change your password. Minimum 6 characters.</small>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary" style="background: var(--accent); border: none;">
                                <i class="bi bi-save me-2"></i>Save Changes
                            </button>
                            <a href="dashboard_mechanic.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="main-card p-4">
                    <h5 class="fw-bold mb-3">Account Info</h5>
                    <p class="mb-2"><strong>Status:</strong> <span class="badge bg-<?= $user['mechanic_status'] === 'Available' ? 'success' : 'warning' ?>"><?= htmlspecialchars($user['mechanic_status'] ?? 'Available') ?></span></p>
                    <p class="mb-2"><strong>Role:</strong> Mechanic</p>
                    <p class="mb-0"><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'mechanic_sidebar_footer.php'; ?>

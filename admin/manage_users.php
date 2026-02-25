<?php
require_once __DIR__ . '/../config/database.php';


if (!isAdmin()) {
    redirect('../index.php');
}

// Hapus user
if (isset($_GET['delete'])) {
    $user_id = $_GET['delete'];
    
    // Cegah admin menghapus diri sendiri
    if ($user_id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
    }
    
    redirect('manage_users.php');
}

// Ubah role user
if (isset($_GET['toggle_role'])) {
    $user_id = $_GET['toggle_role'];
    
    if ($user_id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("UPDATE users SET role = IF(role = 'admin', 'user', 'admin') WHERE id = ?");
        $stmt->execute([$user_id]);
    }
    
    redirect('manage_users.php');
}

// Ambil semua users
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
?>
<link rel="stylesheet" href="/css/style.css">

<h1>Manage Users</h1>

<table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
    <thead>
        <tr style="background-color: #f4f4f4;">
            <th style="padding: 10px; text-align: left;">ID</th>
            <th style="padding: 10px; text-align: left;">Username</th>
            <th style="padding: 10px; text-align: left;">Email</th>
            <th style="padding: 10px; text-align: left;">Role</th>
            <th style="padding: 10px; text-align: left;">Joined</th>
            <th style="padding: 10px; text-align: left;">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $user): ?>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 10px;"><?php echo $user['id']; ?></td>
                <td style="padding: 10px;"><?php echo htmlspecialchars($user['username']); ?></td>
                <td style="padding: 10px;"><?php echo htmlspecialchars($user['email']); ?></td>
                <td style="padding: 10px;"><?php echo ucfirst($user['role']); ?></td>
                <td style="padding: 10px;"><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                <td style="padding: 10px;">
                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <a href="?toggle_role=<?php echo $user['id']; ?>" class="btn" style="margin-right: 5px;">Toggle Role</a>
                        <a href="?delete=<?php echo $user['id']; ?>" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus user ini?')">Delete</a>
                    <?php else: ?>
                        <em>Current User</em>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<a href="index.php" class="btn" style="margin-top: 20px;">Kembali ke Admin Panel</a>

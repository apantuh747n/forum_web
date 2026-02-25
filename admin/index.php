<?php
require_once __DIR__ . '/../config/database.php';


if (!isAdmin()) {
    redirect('../index.php');
}

// Statistik
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalTopics = $pdo->query("SELECT COUNT(*) FROM topics")->fetchColumn();
$totalReplies = $pdo->query("SELECT COUNT(*) FROM replies")->fetchColumn();
$totalCategories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
?>


<link rel="stylesheet" href="/css/style.css">


<h1>Admin Panel</h1>

<div class="admin-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="stat-card" style="background-color: #3498db; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo $totalUsers; ?></h3>
        <p>Total Users</p>
    </div>
    <div class="stat-card" style="background-color: #27ae60; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo $totalTopics; ?></h3>
        <p>Total Topics</p>
    </div>
    <div class="stat-card" style="background-color: #e74c3c; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo $totalReplies; ?></h3>
        <p>Total Replies</p>
    </div>
    <div class="stat-card" style="background-color: #f39c12; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo $totalCategories; ?></h3>
        <p>Total Categories</p>
    </div>
</div>

<div class="admin-menu" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
    <a href="manage_users.php" class="btn" style="text-align: center; padding: 20px;">Manage Users</a>
    <a href="manage_topics.php" class="btn" style="text-align: center; padding: 20px;">Manage Topics</a>
    <a href="manage_replies.php" class="btn" style="text-align: center; padding: 20px;">Manage Komentar</a>
    <a href="manage_categories.php" class="btn" style="text-align: center; padding: 20px;">Manage Categories</a>
</div>
<a href="../index.php" class="btn" style="margin-top: 20px;">Kembali</a>


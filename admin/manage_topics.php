<?php
require_once __DIR__ . '/../config/database.php';


if (!isAdmin()) {
    redirect('../index.php');
}

// Hapus topik
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM topics WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    redirect('manage_topics.php');
}

// Tutup/buka topik
if (isset($_GET['toggle_status'])) {
    $topic_id = $_GET['toggle_status'];
    $stmt = $pdo->prepare("UPDATE topics SET status = IF(status = 'open', 'closed', 'open') WHERE id = ?");
    $stmt->execute([$topic_id]);
    redirect('manage_topics.php');
}

// Ambil semua topik
$stmt = $pdo->query("
    SELECT t.*, u.username, c.name as category_name,
           (SELECT COUNT(*) FROM replies WHERE topic_id = t.id) as reply_count
    FROM topics t
    JOIN users u ON t.user_id = u.id
    JOIN categories c ON t.category_id = c.id
    ORDER BY t.created_at DESC
");
$topics = $stmt->fetchAll();
?>
<link rel="stylesheet" href="/css/style.css">
<h1>Manage Topics</h1>

<table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
    <thead>
        <tr style="background-color: #f4f4f4;">
            <th style="padding: 10px; text-align: left;">ID</th>
            <th style="padding: 10px; text-align: left;">Title</th>
            <th style="padding: 10px; text-align: left;">Author</th>
            <th style="padding: 10px; text-align: left;">Category</th>
            <th style="padding: 10px; text-align: left;">Replies</th>
            <th style="padding: 10px; text-align: left;">Views</th>
            <th style="padding: 10px; text-align: left;">Status</th>
            <th style="padding: 10px; text-align: left;">Created</th>
            <th style="padding: 10px; text-align: left;">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($topics as $topic): ?>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 10px;"><?php echo $topic['id']; ?></td>
                <td style="padding: 10px;">
                    <a href="../topic.php?id=<?php echo $topic['id']; ?>"><?php echo htmlspecialchars($topic['title']); ?></a>
                </td>
                <td style="padding: 10px;"><?php echo htmlspecialchars($topic['username']); ?></td>
                <td style="padding: 10px;"><?php echo htmlspecialchars($topic['category_name']); ?></td>
                <td style="padding: 10px;"><?php echo $topic['reply_count']; ?></td>
                <td style="padding: 10px;"><?php echo $topic['views']; ?></td>
                <td style="padding: 10px;">
                    <span style="color: <?php echo $topic['status'] === 'open' ? 'green' : 'red'; ?>">
                        <?php echo ucfirst($topic['status']); ?>
                    </span>
                </td>
                <td style="padding: 10px;"><?php echo date('d M Y', strtotime($topic['created_at'])); ?></td>
                <td style="padding: 10px;">
                    <a href="?toggle_status=<?php echo $topic['id']; ?>" class="btn" style="margin-right: 5px;">
                        <?php echo $topic['status'] === 'open' ? 'Tutup' : 'Buka'; ?>
                    </a>
                    <a href="?delete=<?php echo $topic['id']; ?>" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus topik ini?')">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<a href="index.php" class="btn" style="margin-top: 20px;">Kembali ke Admin Panel</a>


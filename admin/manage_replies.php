<?php
require_once '../includes/header.php';

if (!isAdmin()) {
    redirect('../index.php');
}

$error = '';
$success = '';

// Hapus komentar
if (isset($_GET['delete'])) {
    $reply_id = $_GET['delete'];
    
    $stmt = $pdo->prepare("DELETE FROM replies WHERE id = ?");
    if ($stmt->execute([$reply_id])) {
        $success = 'Komentar berhasil dihapus!';
    } else {
        $error = 'Gagal menghapus komentar.';
    }
}

// Edit komentar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    $reply_id = $_POST['reply_id'];
    $content = trim($_POST['content']);
    
    if (empty($content)) {
        $error = 'Konten komentar tidak boleh kosong!';
    } else {
        $stmt = $pdo->prepare("UPDATE replies SET content = ? WHERE id = ?");
        if ($stmt->execute([$content, $reply_id])) {
            $success = 'Komentar berhasil diupdate!';
        } else {
            $error = 'Gagal mengupdate komentar.';
        }
    }
}

// Filter berdasarkan topik
$topic_filter = isset($_GET['topic_id']) ? $_GET['topic_id'] : '';

// Ambil semua topik untuk dropdown filter
$topics = $pdo->query("SELECT id, title FROM topics ORDER BY created_at DESC")->fetchAll();

// Ambil semua komentar dengan join ke users dan topics
$query = "
    SELECT r.*, u.username, t.title as topic_title, t.id as topic_id
    FROM replies r
    JOIN users u ON r.user_id = u.id
    JOIN topics t ON r.topic_id = t.id
";

if ($topic_filter) {
    $query .= " WHERE r.topic_id = :topic_id";
}

$query .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($query);

if ($topic_filter) {
    $stmt->bindParam(':topic_id', $topic_filter);
}

$stmt->execute();
$replies = $stmt->fetchAll();

// Ambil data komentar untuk diedit
$editReply = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM replies WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editReply = $stmt->fetch();
}
?>

<h1>Manage Komentar</h1>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<!-- Filter Section -->
<div style="background-color: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
    <form method="GET" action="" style="display: flex; gap: 10px; align-items: flex-end;">
        <div style="flex: 1;">
            <label for="topic_id" style="display: block; margin-bottom: 5px; font-weight: bold;">Filter berdasarkan Topik:</label>
            <select name="topic_id" id="topic_id" class="form-control">
                <option value="">Semua Topik</option>
                <?php foreach ($topics as $topic): ?>
                    <option value="<?php echo $topic['id']; ?>" <?php echo $topic_filter == $topic['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(substr($topic['title'], 0, 50)) . (strlen($topic['title']) > 50 ? '...' : ''); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="btn">Filter</button>
            <?php if ($topic_filter): ?>
                <a href="manage_replies.php" class="btn btn-danger">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Edit Form (muncul jika ada yang diedit) -->
<?php if ($editReply): ?>
    <div style="background-color: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <h2>Edit Komentar</h2>
        <form method="POST" action="">
            <input type="hidden" name="reply_id" value="<?php echo $editReply['id']; ?>">
            
            <div class="form-group">
                <label for="content">Konten Komentar</label>
                <textarea id="content" name="content" class="form-control" rows="6" required><?php echo htmlspecialchars($editReply['content']); ?></textarea>
            </div>
            
            <button type="submit" name="edit" class="btn">Update Komentar</button>
            <a href="manage_replies.php<?php echo $topic_filter ? '?topic_id=' . $topic_filter : ''; ?>" class="btn btn-danger">Batal</a>
        </form>
    </div>
<?php endif; ?>

<!-- Daftar Komentar -->
<div style="background-color: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); overflow: hidden;">
    <div style="padding: 15px; background-color: #f8f9fa; border-bottom: 1px solid #dee2e6;">
        <strong>Total Komentar: <?php echo count($replies); ?></strong>
    </div>
    
    <?php if (empty($replies)): ?>
        <div style="padding: 40px; text-align: center; color: #6c757d;">
            <p>Tidak ada komentar ditemukan.</p>
        </div>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background-color: #f4f4f4;">
                    <th style="padding: 12px; text-align: left;">ID</th>
                    <th style="padding: 12px; text-align: left;">Komentar</th>
                    <th style="padding: 12px; text-align: left;">Penulis</th>
                    <th style="padding: 12px; text-align: left;">Topik</th>
                    <th style="padding: 12px; text-align: left;">Tanggal</th>
                    <th style="padding: 12px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($replies as $reply): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 12px; vertical-align: top;">#<?php echo $reply['id']; ?></td>
                        <td style="padding: 12px; max-width: 300px;">
                            <div style="max-height: 80px; overflow: hidden; text-overflow: ellipsis;">
                                <?php echo nl2br(htmlspecialchars(substr($reply['content'], 0, 150))); ?>
                                <?php if (strlen($reply['content']) > 150): ?>...<?php endif; ?>
                            </div>
                        </td>
                        <td style="padding: 12px;">
                            <a href="../profile.php?id=<?php echo $reply['user_id']; ?>" style="color: #3498db; text-decoration: none;">
                                <?php echo htmlspecialchars($reply['username']); ?>
                            </a>
                        </td>
                        <td style="padding: 12px;">
                            <a href="../topic.php?id=<?php echo $reply['topic_id']; ?>" style="color: #3498db; text-decoration: none;" target="_blank">
                                <?php echo htmlspecialchars(substr($reply['topic_title'], 0, 50)); ?>
                                <?php if (strlen($reply['topic_title']) > 50): ?>...<?php endif; ?>
                            </a>
                        </td>
                        <td style="padding: 12px;">
                            <?php echo date('d/m/Y H:i', strtotime($reply['created_at'])); ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <a href="?edit=<?php echo $reply['id']; ?><?php echo $topic_filter ? '&topic_id=' . $topic_filter : ''; ?>" 
                               class="btn" style="padding: 5px 10px; font-size: 12px; margin-right: 5px;">Edit</a>
                            <a href="?delete=<?php echo $reply['id']; ?><?php echo $topic_filter ? '&topic_id=' . $topic_filter : ''; ?>" 
                               class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;" 
                               onclick="return confirm('Yakin ingin menghapus komentar ini?\n\nKomentar: <?php echo addslashes(substr($reply['content'], 0, 100)); ?>...')">Hapus</a>
                        </td>
                    </tr>
                    <!-- Preview komentar lengkap saat hover -->
                    <tr style="display: none;"> 
                        <td colspan="6" class="reply-preview" style="background-color: #f8f9fa; padding: 10px; border-bottom: 1px solid #dee2e6;">
                            <strong>Komentar lengkap:</strong><br>
                            <?php echo nl2br(htmlspecialchars($reply['content'])); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Tambahkan JavaScript untuk preview komentar -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var rows = document.querySelectorAll('tbody tr:first-child');
    rows.forEach(function(row, index) {
        row.addEventListener('mouseenter', function() {
            var previewRow = this.nextElementSibling;
            if (previewRow && previewRow.classList.contains('reply-preview')) {
                previewRow.style.display = 'table-row';
            }
        });
        row.addEventListener('mouseleave', function() {
            var previewRow = this.nextElementSibling;
            if (previewRow && previewRow.classList.contains('reply-preview')) {
                previewRow.style.display = 'none';
            }
        });
    });
});
</script>

<style>
.reply-preview {
    background-color: #f8f9fa;
    padding: 15px;
    border-left: 3px solid #3498db;
    font-size: 14px;
    line-height: 1.6;
    display: none;
}

tbody tr:hover {
    background-color: #f5f5f5;
}
</style>

<div style="margin-top: 20px; display: flex; gap: 10px;">
    <a href="index.php" class="btn">Kembali ke Admin Panel</a>
    <a href="../index.php" class="btn" target="_blank">Lihat Forum</a>
</div>

<?php require_once '../includes/footer.php'; ?>
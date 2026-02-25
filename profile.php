<?php
require_once 'includes/header.php';

$user_id = isset($_GET['id']) ? $_GET['id'] : $_SESSION['user_id'];

// Ambil data user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    redirect('index.php');
}

// Proses hapus topik dari profile
if (isset($_GET['delete_topic']) && $user_id == $_SESSION['user_id']) {
    $topic_id = $_GET['delete_topic'];
    $stmt = $pdo->prepare("DELETE FROM topics WHERE id = ? AND user_id = ?");
    $stmt->execute([$topic_id, $user_id]);
    redirect("profile.php?id=$user_id");
}

// Proses hapus komentar dari profile
if (isset($_GET['delete_reply']) && $user_id == $_SESSION['user_id']) {
    $reply_id = $_GET['delete_reply'];
    $stmt = $pdo->prepare("DELETE FROM replies WHERE id = ? AND user_id = ?");
    $stmt->execute([$reply_id, $user_id]);
    redirect("profile.php?id=$user_id");
}

// Ambil topik user
$stmt = $pdo->prepare("
    SELECT t.*, c.name as category_name,
           (SELECT COUNT(*) FROM replies WHERE topic_id = t.id) as reply_count
    FROM topics t
    JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ?
    ORDER BY t.created_at DESC
");
$stmt->execute([$user_id]);
$topics = $stmt->fetchAll();

// Ambil balasan user
$stmt = $pdo->prepare("
    SELECT r.*, t.title, t.id as topic_id
    FROM replies r
    JOIN topics t ON r.topic_id = t.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
    LIMIT 20
");
$stmt->execute([$user_id]);
$replies = $stmt->fetchAll();

// Update profile (hanya untuk user sendiri)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile']) && $user_id == $_SESSION['user_id']) {
    $bio = trim($_POST['bio']);
    $email = trim($_POST['email']);
    
    // Cek email sudah digunakan atau belum
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $user_id]);
    
    if ($stmt->fetch()) {
        $error = 'Email sudah digunakan oleh user lain!';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET bio = ?, email = ? WHERE id = ?");
        if ($stmt->execute([$bio, $email, $user_id])) {
            $success = 'Profil berhasil diperbarui!';
            $user['bio'] = $bio;
            $user['email'] = $email;
        }
    }
}

// Ganti password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password']) && $user_id == $_SESSION['user_id']) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verifikasi password saat ini
    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $user_id]);
                $success = 'Password berhasil diubah!';
            } else {
                $error = 'Password baru minimal 6 karakter!';
            }
        } else {
            $error = 'Konfirmasi password tidak cocok!';
        }
    } else {
        $error = 'Password saat ini salah!';
    }
}
?>

<div class="profile-header">
    <div class="profile-avatar">
        <img src="avatars/<?php echo $user['avatar']; ?>" alt="Avatar">
    </div>
    <div class="profile-info">
        <h2><?php echo htmlspecialchars($user['username']); ?></h2>
        <p>Email: <?php echo htmlspecialchars($user['email']); ?></p>
        <p>Member sejak: <?php echo date('d M Y', strtotime($user['created_at'])); ?></p>
        <p>Role: <?php echo ucfirst($user['role']); ?></p>
        <p>Total Topik: <?php echo count($topics); ?> | Total Komentar: <?php echo count($replies); ?></p>
    </div>
</div>

<?php if ($user_id == $_SESSION['user_id']): ?>
    <!-- Tab Navigation -->
    <div style="margin-bottom: 20px; border-bottom: 2px solid #ddd;">
        <a href="#profile" class="tab-link <?php echo !isset($_GET['tab']) ? 'active' : ''; ?>" style="display: inline-block; padding: 10px 20px; text-decoration: none; color: #333;" onclick="showTab('profile')">Profile</a>
        <a href="#topics" class="tab-link" style="display: inline-block; padding: 10px 20px; text-decoration: none; color: #333;" onclick="showTab('topics')">Topik Saya</a>
        <a href="#replies" class="tab-link" style="display: inline-block; padding: 10px 20px; text-decoration: none; color: #333;" onclick="showTab('replies')">Komentar Saya</a>
        <a href="#settings" class="tab-link" style="display: inline-block; padding: 10px 20px; text-decoration: none; color: #333;" onclick="showTab('settings')">Pengaturan</a>
    </div>

    <!-- Tab Content -->
    <div id="profile-tab" class="tab-content" style="<?php echo !isset($_GET['tab']) ? 'display: block;' : 'display: none;'; ?>">
        <div class="profile-bio">
            <h3>Bio</h3>
            <?php if (!empty($user['bio'])): ?>
                <p><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
            <?php else: ?>
                <p><em>Belum ada bio.</em></p>
            <?php endif; ?>
        </div>
    </div>

    <div id="topics-tab" class="tab-content" style="display: none;">
        <h3>Topik Saya</h3>
        <?php if (empty($topics)): ?>
            <p>Belum membuat topik.</p>
        <?php else: ?>
            <div class="topics-grid">
                <?php foreach ($topics as $topic): ?>
                    <div class="topic-card">
                        <h4><a href="topic.php?id=<?php echo $topic['id']; ?>"><?php echo htmlspecialchars($topic['title']); ?></a></h4>
                        <div class="topic-meta">
                            Kategori: <?php echo htmlspecialchars($topic['category_name']); ?>
                            | <?php echo date('d M Y', strtotime($topic['created_at'])); ?>
                        </div>
                        <div class="topic-stats">
                            <span>👁️ <?php echo $topic['views']; ?></span>
                            <span>💬 <?php echo $topic['reply_count']; ?></span>
                        </div>
                        <div style="margin-top: 10px;">
                            <a href="topic.php?id=<?php echo $topic['id']; ?>&edit_topic=1" class="btn" style="padding: 5px 10px; font-size: 12px;">Edit</a>
                            <a href="?id=<?php echo $user_id; ?>&delete_topic=<?php echo $topic['id']; ?>" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;" 
                               onclick="return confirm('Yakin ingin menghapus topik ini?')">Hapus</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div id="replies-tab" class="tab-content" style="display: none;">
        <h3>Komentar Saya</h3>
        <?php if (empty($replies)): ?>
            <p>Belum memberikan komentar.</p>
        <?php else: ?>
            <?php foreach ($replies as $reply): ?>
                <div class="reply-item">
                    <div class="reply-meta">
                        <div>
                            <span class="reply-author">Pada topik: <a href="topic.php?id=<?php echo $reply['topic_id']; ?>"><?php echo htmlspecialchars($reply['title']); ?></a></span>
                            <span class="reply-date"><?php echo date('d M Y H:i', strtotime($reply['created_at'])); ?></span>
                        </div>
                        <div>
                            <a href="topic.php?id=<?php echo $reply['topic_id']; ?>&edit_reply=<?php echo $reply['id']; ?>" class="btn" style="padding: 3px 8px; font-size: 12px;">Edit</a>
                            <a href="?id=<?php echo $user_id; ?>&delete_reply=<?php echo $reply['id']; ?>" class="btn btn-danger" style="padding: 3px 8px; font-size: 12px;" 
                               onclick="return confirm('Yakin ingin menghapus komentar ini?')">Hapus</a>
                        </div>
                    </div>
                    <div class="reply-content">
                        <?php echo nl2br(htmlspecialchars(substr($reply['content'], 0, 200))); ?>
                        <?php if (strlen($reply['content']) > 200): ?>...<?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="settings-tab" class="tab-content" style="display: none;">
        <h3>Pengaturan Profil</h3>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div style="background-color: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h4>Update Profil</h4>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="bio">Bio</label>
                    <textarea id="bio" name="bio" class="form-control" rows="5"><?php echo htmlspecialchars($user['bio']); ?></textarea>
                </div>
                
                <button type="submit" name="update_profile" class="btn">Update Profil</button>
            </form>
        </div>
        
        <div style="background-color: #fff; padding: 20px; border-radius: 8px;">
            <h4>Ganti Password</h4>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="current_password">Password Saat Ini</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password">Password Baru</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password Baru</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>
                
                <button type="submit" name="change_password" class="btn">Ganti Password</button>
            </form>
        </div>
    </div>

    <script>
    function showTab(tabName) {
        // Sembunyikan semua tab
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.style.display = 'none';
        });
        
        // Tampilkan tab yang dipilih
        document.getElementById(tabName + '-tab').style.display = 'block';
        
        // Update active class pada tab link
        document.querySelectorAll('.tab-link').forEach(link => {
            link.classList.remove('active');
        });
        event.target.classList.add('active');
    }
    
    // Cek hash di URL
    if (window.location.hash) {
        const tab = window.location.hash.substring(1);
        if (tab === 'topics' || tab === 'replies' || tab === 'settings') {
            showTab(tab);
        }
    }
    </script>

<?php else: ?>
    <!-- Tampilan profile untuk user lain -->
    <div class="profile-bio">
        <h3>Bio</h3>
        <?php if (!empty($user['bio'])): ?>
            <p><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
        <?php else: ?>
            <p><em>User ini belum mengisi bio.</em></p>
        <?php endif; ?>
    </div>

    <div class="profile-topics">
        <h3>Topik yang Dibuat (<?php echo count($topics); ?>)</h3>
        <?php if (empty($topics)): ?>
            <p>Belum membuat topik.</p>
        <?php else: ?>
            <div class="topics-grid">
                <?php foreach ($topics as $topic): ?>
                    <div class="topic-card">
                        <h4><a href="topic.php?id=<?php echo $topic['id']; ?>"><?php echo htmlspecialchars($topic['title']); ?></a></h4>
                        <div class="topic-meta">
                            Kategori: <?php echo htmlspecialchars($topic['category_name']); ?>
                            | <?php echo date('d M Y', strtotime($topic['created_at'])); ?>
                        </div>
                        <div class="topic-stats">
                            <span>👁️ <?php echo $topic['views']; ?></span>
                            <span>💬 <?php echo $topic['reply_count']; ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="profile-replies">
        <h3>Komentar Terbaru</h3>
        <?php if (empty($replies)): ?>
            <p>Belum memberikan komentar.</p>
        <?php else: ?>
            <?php foreach ($replies as $reply): ?>
                <div class="reply-item">
                    <div class="reply-meta">
                        <span class="reply-author">Pada topik: <a href="topic.php?id=<?php echo $reply['topic_id']; ?>"><?php echo htmlspecialchars($reply['title']); ?></a></span>
                        <span class="reply-date"><?php echo date('d M Y H:i', strtotime($reply['created_at'])); ?></span>
                    </div>
                    <div class="reply-content">
                        <?php echo nl2br(htmlspecialchars(substr($reply['content'], 0, 200))); ?>
                        <?php if (strlen($reply['content']) > 200): ?>...<?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<style>
.tab-link.active {
    border-bottom: 3px solid #3498db;
    color: #3498db !important;
    font-weight: bold;
}

.tab-link:hover {
    background-color: #f4f4f4;
}

.tab-content {
    padding: 20px 0;
}
</style>

<?php require_once 'includes/footer.php'; ?>
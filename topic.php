<?php
require_once 'includes/header.php';
require_once 'config/functions.php';

if (!isset($_GET['id'])) {
    redirect('index.php');
}

$topic_id = $_GET['id'];

// Update views
$pdo->prepare("UPDATE topics SET views = views + 1 WHERE id = ?")->execute([$topic_id]);

// Ambil data topik
$stmt = $pdo->prepare("
    SELECT t.*, u.username, u.id as user_id, c.name as category_name
    FROM topics t
    JOIN users u ON t.user_id = u.id
    JOIN categories c ON t.category_id = c.id
    WHERE t.id = ?
");
$stmt->execute([$topic_id]);
$topic = $stmt->fetch();

if (!$topic) {
    redirect('index.php');
}

// Proses edit topik (untuk pemilik)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_topic'])) {
    if (isLoggedIn() && ($_SESSION['user_id'] == $topic['user_id'] || isAdmin())) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $category_id = $_POST['category_id'];
        
        if (!empty($title) && !empty($content) && !empty($category_id)) {
            $stmt = $pdo->prepare("UPDATE topics SET title = ?, content = ?, category_id = ? WHERE id = ?");
            $stmt->execute([$title, $content, $category_id, $topic_id]);
            redirect("topic.php?id=$topic_id");
        }
    }
}

// Proses hapus topik (untuk pemilik)
if (isset($_GET['delete_topic'])) {
    if (isLoggedIn() && ($_SESSION['user_id'] == $topic['user_id'] || isAdmin())) {
        $stmt = $pdo->prepare("DELETE FROM topics WHERE id = ?");
        $stmt->execute([$topic_id]);
        redirect('index.php');
    }
}

// Proses edit balasan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_reply'])) {
    $reply_id = $_POST['reply_id'];
    $content = trim($_POST['content']);
    
    // Cek kepemilikan balasan
    $stmt = $pdo->prepare("SELECT user_id FROM replies WHERE id = ?");
    $stmt->execute([$reply_id]);
    $reply = $stmt->fetch();
    
    if ($reply && isLoggedIn() && ($_SESSION['user_id'] == $reply['user_id'] || isAdmin())) {
        if (!empty($content)) {
            $stmt = $pdo->prepare("UPDATE replies SET content = ? WHERE id = ?");
            $stmt->execute([$content, $reply_id]);
            redirect("topic.php?id=$topic_id");
        }
    }
}

// Proses hapus balasan
if (isset($_GET['delete_reply'])) {
    $reply_id = $_GET['delete_reply'];
    
    // Cek kepemilikan balasan
    $stmt = $pdo->prepare("SELECT user_id FROM replies WHERE id = ?");
    $stmt->execute([$reply_id]);
    $reply = $stmt->fetch();
    
    if ($reply && isLoggedIn() && ($_SESSION['user_id'] == $reply['user_id'] || isAdmin())) {
        $stmt = $pdo->prepare("DELETE FROM replies WHERE id = ?");
        $stmt->execute([$reply_id]);
        redirect("topic.php?id=$topic_id");
    }
}

// Ambil balasan
$stmt = $pdo->prepare("
    SELECT r.*, u.username, u.avatar, u.id as user_id
    FROM replies r
    JOIN users u ON r.user_id = u.id
    WHERE r.topic_id = ?
    ORDER BY r.created_at ASC
");
$stmt->execute([$topic_id]);
$replies = $stmt->fetchAll();

// Ambil kategori untuk form edit
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Proses balasan baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reply']) && isLoggedIn()) {
    $content = trim($_POST['content']);
    
    if (!empty($content) && $topic['status'] === 'open') {
        $stmt = $pdo->prepare("INSERT INTO replies (content, user_id, topic_id) VALUES (?, ?, ?)");
        $stmt->execute([$content, $_SESSION['user_id'], $topic_id]);
        redirect("topic.php?id=$topic_id");
    }
}
?>

<div class="topic-header">
    <?php if (isset($_GET['edit_topic']) && (isLoggedIn() && ($_SESSION['user_id'] == $topic['user_id'] || isAdmin()))): ?>
        <!-- Form Edit Topik -->
        <h2>Edit Topik</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="title">Judul Topik</label>
                <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($topic['title']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="category_id">Kategori</label>
                <select id="category_id" name="category_id" class="form-control" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo $category['id'] == $topic['category_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="content">Konten</label>
                <textarea id="content" name="content" class="form-control" rows="10" required><?php echo htmlspecialchars($topic['content']); ?></textarea>
                <small style="color: #666; display: block; margin-top: 5px;">
                    Tips: Gunakan [img]https://link-gambar.jpg[/img] untuk menampilkan gambar
                </small>
            </div>
            
            <button type="submit" name="edit_topic" class="btn">Simpan Perubahan</button>
            <a href="topic.php?id=<?php echo $topic_id; ?>" class="btn btn-danger">Batal</a>
        </form>
    <?php else: ?>
        <!-- Tampilan Normal Topik -->
        <div style="display: flex; justify-content: space-between; align-items: start;">
            <h1><?php echo htmlspecialchars($topic['title']); ?></h1>
            
            <?php if (isLoggedIn() && ($_SESSION['user_id'] == $topic['user_id'] || isAdmin())): ?>
                <div>
                    <a href="?id=<?php echo $topic_id; ?>&edit_topic=1" class="btn" style="margin-right: 5px;">Edit Topik</a>
                    <a href="?id=<?php echo $topic_id; ?>&delete_topic=1" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus topik ini?')">Hapus Topik</a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="topic-meta">
            Oleh: <a href="profile.php?id=<?php echo $topic['user_id']; ?>"><?php echo htmlspecialchars($topic['username']); ?></a>
            | Kategori: <?php echo htmlspecialchars($topic['category_name']); ?>
            | Dibuat: <?php echo date('d M Y H:i', strtotime($topic['created_at'])); ?>
            | Dilihat: <?php echo $topic['views']; ?> kali
        </div>
    <?php endif; ?>
</div>

<?php if (!isset($_GET['edit_topic'])): ?>
    <div class="topic-content forum-content">
        <?php echo parseContent($topic['content']); ?>
    </div>

    <h2>Balasan (<?php echo count($replies); ?>)</h2>

    <?php if (empty($replies)): ?>
        <p>Belum ada balasan.</p>
    <?php else: ?>
        <?php foreach ($replies as $index => $reply): ?>
            <div class="reply-item" id="reply-<?php echo $reply['id']; ?>">
                <?php if (isset($_GET['edit_reply']) && $_GET['edit_reply'] == $reply['id'] && (isLoggedIn() && ($_SESSION['user_id'] == $reply['user_id'] || isAdmin()))): ?>
                    <!-- Form Edit Balasan -->
                    <form method="POST" action="">
                        <input type="hidden" name="reply_id" value="<?php echo $reply['id']; ?>">
                        <div class="form-group">
                            <label>Tips: Gunakan [img]link_gambar[/img] untuk menampilkan gambar</label>
                            <textarea name="content" class="form-control" rows="5" required><?php echo htmlspecialchars($reply['content']); ?></textarea>
                        </div>
                        <button type="submit" name="edit_reply" class="btn">Simpan</button>
                        <a href="topic.php?id=<?php echo $topic_id; ?>" class="btn btn-danger">Batal</a>
                    </form>
                <?php else: ?>
                    <!-- Tampilan Normal Balasan -->
                    <div class="reply-meta">
                        <div>
                            <span class="reply-author">
                                <a href="profile.php?id=<?php echo $reply['user_id']; ?>"><?php echo htmlspecialchars($reply['username']); ?></a>
                            </span>
                            <span class="reply-date"><?php echo date('d M Y H:i', strtotime($reply['created_at'])); ?></span>
                            <?php if ($index == 0): ?>
                                <span class="topic-starter-badge">Topik Starter</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (isLoggedIn() && ($_SESSION['user_id'] == $reply['user_id'] || isAdmin())): ?>
                            <div>
                                <a href="?id=<?php echo $topic_id; ?>&edit_reply=<?php echo $reply['id']; ?>#reply-<?php echo $reply['id']; ?>" class="btn" style="padding: 3px 8px; font-size: 12px;">Edit</a>
                                <a href="?id=<?php echo $topic_id; ?>&delete_reply=<?php echo $reply['id']; ?>" class="btn btn-danger" style="padding: 3px 8px; font-size: 12px;" 
                                   onclick="return confirm('Yakin ingin menghapus komentar ini?')">Hapus</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="reply-content forum-content">
                        <?php echo parseContent($reply['content']); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (isLoggedIn() && $topic['status'] === 'open'): ?>
        <h3>Tulis Balasan</h3>
        <form method="POST" action="">
            <input type="hidden" name="add_reply" value="1">
            <div class="form-group">
                <label for="reply_content">Konten Balasan:</label>
                <textarea name="content" id="reply_content" class="form-control" rows="5" required placeholder="Tulis balasan Anda..."></textarea>
                <small style="color: #666; display: block; margin-top: 5px;">
                    Tips: 
                    - Gunakan [img]https://link-gambar.jpg[/img] untuk menampilkan gambar<br>
                    - Atau langsung tempel link gambar (jpg, png, gif, dll)<br>
                    - Gunakan ![alt text](https://link-gambar.jpg) untuk gambar dengan keterangan<br>
                    - Link YouTube akan otomatis di-embed
                </small>
            </div>
            <button type="submit" class="btn">Kirim Balasan</button>
        </form>
    <?php elseif (!isLoggedIn()): ?>
        <p><a href="login.php">Login</a> untuk menulis balasan.</p>
    <?php elseif ($topic['status'] === 'closed'): ?>
        <p>Topik ini telah ditutup.</p>
    <?php endif; ?>
<?php endif; ?>

<!-- Tambahkan CSS untuk gambar -->
<style>
.forum-content {
    line-height: 1.6;
    overflow-wrap: break-word;
}

.embedded-image {
    margin: 20px 0;
    text-align: center;
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
}

.forum-image {
    max-width: 100%;
    max-height: 500px;
    object-fit: contain;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    cursor: pointer;
    transition: transform 0.3s;
}

.forum-image:hover {
    transform: scale(1.02);
}

.image-caption {
    margin-top: 10px;
    color: #666;
    font-size: 14px;
}

.image-caption a {
    color: #3498db;
    text-decoration: none;
}

.image-caption a:hover {
    text-decoration: underline;
}

.embedded-video {
    position: relative;
    padding-bottom: 56.25%;
    height: 0;
    overflow: hidden;
    max-width: 100%;
    margin: 20px 0;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.embedded-video iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border-radius: 8px;
}

.topic-starter-badge {
    background-color: #27ae60;
    color: white;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    margin-left: 10px;
}

.forum-link {
    color: #3498db;
    text-decoration: none;
    word-break: break-all;
}

.forum-link:hover {
    text-decoration: underline;
}

.image-lightbox {
    display: none;
    position: fixed;
    z-index: 9999;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.9);
    text-align: center;
    padding: 20px;
}

.image-lightbox img {
    max-width: 90%;
    max-height: 90%;
    margin-top: 2%;
    border-radius: 8px;
}

.image-lightbox:target {
    display: flex;
    justify-content: center;
    align-items: center;
}

.close-lightbox {
    position: absolute;
    top: 20px;
    right: 30px;
    color: white;
    font-size: 30px;
    text-decoration: none;
    background-color: rgba(0,0,0,0.5);
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.3s;
}

.close-lightbox:hover {
    background-color: rgba(0,0,0,0.8);
}
</style>

<!-- Lightbox container -->
<div class="image-lightbox" id="lightbox">
    <a href="#" class="close-lightbox">&times;</a>
    <img id="lightbox-img" src="" alt="">
</div>

<!-- JavaScript untuk lightbox -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const images = document.querySelectorAll('.forum-image');
    const lightbox = document.getElementById('lightbox');
    const lightboxImg = document.getElementById('lightbox-img');
    
    images.forEach(img => {
        img.addEventListener('click', function(e) {
            e.preventDefault();
            lightboxImg.src = this.src;
            window.location.hash = 'lightbox';
        });
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && window.location.hash === '#lightbox') {
            window.location.hash = '';
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
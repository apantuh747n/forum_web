<?php
require_once 'includes/header.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Ambil kategori dari URL jika ada
$selected_category = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Ambil semua kategori
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $category_id = $_POST['category_id'];
    
    if (empty($title) || empty($content) || empty($category_id)) {
        $error = 'Semua field harus diisi!';
    } else {
        $stmt = $pdo->prepare("INSERT INTO topics (title, content, user_id, category_id) VALUES (?, ?, ?, ?)");
        
        if ($stmt->execute([$title, $content, $_SESSION['user_id'], $category_id])) {
            $topic_id = $pdo->lastInsertId();
            redirect("topic.php?id=$topic_id");
        } else {
            $error = 'Terjadi kesalahan. Silakan coba lagi.';
        }
    }
}
?>

<h1>Buat Topik Baru</h1>

<?php if ($selected_category > 0): ?>
    <div class="alert alert-info" style="background-color: #e8f4fd; color: #2c3e50;">
        💡 Anda akan membuat topik di kategori yang dipilih.
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<form method="POST" action="">
    <div class="form-group">
        <label for="title">Judul Topik</label>
        <input type="text" id="title" name="title" class="form-control" required>
    </div>
    
    <div class="form-group">
        <label for="category_id">Kategori</label>
        <select id="category_id" name="category_id" class="form-control" required>
            <option value="">Pilih Kategori</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo $category['id']; ?>" <?php echo $category['id'] == $selected_category ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($category['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="form-group">
        <label for="content">Konten</label>
        <textarea id="content" name="content" class="form-control" rows="10" required></textarea>
        <small style="color: #666; display: block; margin-top: 5px;">
            <strong>Tips Menampilkan Gambar:</strong><br>
            • <strong>Link gambar langsung:</strong> https://contoh.com/gambar.jpg (akan otomatis tampil)<br>
            • <strong>BBCode style:</strong> [img]https://contoh.com/gambar.jpg[/img]<br>
            • <strong>Markdown style:</strong> ![keterangan gambar](https://contoh.com/gambar.jpg)<br>
            • <strong>Video YouTube:</strong> Tempel link YouTube (akan auto-embed)
        </small>
    </div>
    
    <button type="submit" class="btn">Buat Topik</button>
    <a href="javascript:history.back()" class="btn btn-danger">Batal</a>
</form>

<?php require_once 'includes/footer.php'; ?>
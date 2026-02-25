<?php
require_once 'includes/header.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Ambil kategori
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
                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
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
            • <strong>Dengan caption:</strong> [img=Keterangan gambar]https://contoh.com/gambar.jpg[/img]<br>
            • <strong>Video YouTube:</strong> Tempel link YouTube (akan auto-embed)<br>
            <br>
            <strong>Format gambar didukung:</strong> jpg, jpeg, png, gif, webp, bmp, svg
        </small>
    </div>
    
    <button type="submit" class="btn">Buat Topik</button>
    <a href="index.php" class="btn btn-danger">Batal</a>
</form>

<!-- Preview Gambar (Opsional) -->
<div id="image-preview" style="display: none; margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 8px;">
    <h3>Preview Gambar:</h3>
    <div id="preview-container"></div>
</div>

<script>
// Simple preview untuk link gambar yang ditemukan
document.getElementById('content').addEventListener('input', function() {
    const content = this.value;
    const imagePattern = /(https?:\/\/[^\s]+\.(jpg|jpeg|png|gif|webp))/gi;
    const matches = content.match(imagePattern);
    
    const previewDiv = document.getElementById('image-preview');
    const container = document.getElementById('preview-container');
    
    if (matches && matches.length > 0) {
        container.innerHTML = '';
        matches.slice(0, 3).forEach(url => { // Preview max 3 gambar
            container.innerHTML += `
                <div style="display: inline-block; margin: 10px;">
                    <img src="${url}" style="max-width: 150px; max-height: 150px; border-radius: 4px;" 
                         onerror="this.style.display='none'">
                </div>
            `;
        });
        previewDiv.style.display = 'block';
    } else {
        previewDiv.style.display = 'none';
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
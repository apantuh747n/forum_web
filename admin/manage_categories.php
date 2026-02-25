<?php
require_once '../includes/header.php';

if (!isAdmin()) {
    redirect('../index.php');
}

$error = '';
$success = '';

// Proses tambah kategori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    if (empty($name)) {
        $error = 'Nama kategori harus diisi!';
    } else {
        $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        if ($stmt->execute([$name, $description])) {
            $success = 'Kategori berhasil ditambahkan!';
        } else {
            $error = 'Gagal menambahkan kategori.';
        }
    }
}

// Proses edit kategori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    $id = $_POST['id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    if (empty($name)) {
        $error = 'Nama kategori harus diisi!';
    } else {
        $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
        if ($stmt->execute([$name, $description, $id])) {
            $success = 'Kategori berhasil diupdate!';
        } else {
            $error = 'Gagal mengupdate kategori.';
        }
    }
}

// Hapus kategori
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Cek apakah kategori memiliki topik
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM topics WHERE category_id = ?");
    $stmt->execute([$id]);
    $topicCount = $stmt->fetchColumn();
    
    if ($topicCount > 0) {
        $error = 'Kategori tidak bisa dihapus karena masih memiliki topik!';
    } else {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        if ($stmt->execute([$id])) {
            $success = 'Kategori berhasil dihapus!';
        } else {
            $error = 'Gagal menghapus kategori.';
        }
    }
}

// Ambil semua kategori
$stmt = $pdo->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM topics WHERE category_id = c.id) as topic_count 
    FROM categories c 
    ORDER BY c.name
");
$categories = $stmt->fetchAll();

// Ambil data kategori untuk diedit
$editCategory = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editCategory = $stmt->fetch();
}
?>

<h1>Manage Categories</h1>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px; margin-top: 20px;">
    <!-- Form Tambah/Edit Kategori -->
    <div>
        <div class="form-container" style="background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            <h2><?php echo $editCategory ? 'Edit Kategori' : 'Tambah Kategori Baru'; ?></h2>
            
            <form method="POST" action="">
                <?php if ($editCategory): ?>
                    <input type="hidden" name="id" value="<?php echo $editCategory['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="name">Nama Kategori</label>
                    <input type="text" id="name" name="name" class="form-control" 
                           value="<?php echo $editCategory ? htmlspecialchars($editCategory['name']) : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="description">Deskripsi</label>
                    <textarea id="description" name="description" class="form-control" rows="4"><?php echo $editCategory ? htmlspecialchars($editCategory['description']) : ''; ?></textarea>
                </div>
                
                <?php if ($editCategory): ?>
                    <button type="submit" name="edit" class="btn">Update Kategori</button>
                    <a href="manage_categories.php" class="btn btn-danger">Batal</a>
                <?php else: ?>
                    <button type="submit" name="add" class="btn">Tambah Kategori</button>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Daftar Kategori -->
    <div>
        <div style="background-color: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background-color: #f4f4f4;">
                        <th style="padding: 12px; text-align: left;">Nama</th>
                        <th style="padding: 12px; text-align: left;">Deskripsi</th>
                        <th style="padding: 12px; text-align: center;">Topik</th>
                        <th style="padding: 12px; text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="4" style="padding: 20px; text-align: center;">Belum ada kategori.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px;">
                                    <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                </td>
                                <td style="padding: 12px;">
                                    <?php echo htmlspecialchars($category['description']); ?>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <span class="badge" style="background-color: #3498db; color: white; padding: 3px 8px; border-radius: 3px;">
                                        <?php echo $category['topic_count']; ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <a href="?edit=<?php echo $category['id']; ?>" class="btn" style="margin-right: 5px; padding: 5px 10px; font-size: 14px;">Edit</a>
                                    <?php if ($category['topic_count'] == 0): ?>
                                        <a href="?delete=<?php echo $category['id']; ?>" class="btn btn-danger" style="padding: 5px 10px; font-size: 14px;" 
                                           onclick="return confirm('Yakin ingin menghapus kategori ini?')">Hapus</a>
                                    <?php else: ?>
                                        <span class="btn btn-danger" style="padding: 5px 10px; font-size: 14px; opacity: 0.5; cursor: not-allowed;" 
                                              title="Tidak bisa dihapus karena masih memiliki topik">Hapus</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div style="margin-top: 20px;">
    <a href="index.php" class="btn">Kembali ke Admin Panel</a>
</div>

<?php require_once '../includes/footer.php'; ?>
<?php
require_once 'includes/header.php';
require_once 'config/functions.php';

// Ambil ID kategori dari URL
$category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($category_id === 0) {
    redirect('index.php');
}

// Ambil data kategori
$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$category_id]);
$category = $stmt->fetch();

if (!$category) {
    redirect('index.php');
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Ambil topik dalam kategori ini - PERBAIKAN: gunakan semua parameter named atau semua positional
$stmt = $pdo->prepare("
    SELECT t.*, u.username,
           (SELECT COUNT(*) FROM replies WHERE topic_id = t.id) as reply_count,
           (SELECT MAX(created_at) FROM replies WHERE topic_id = t.id) as last_reply
    FROM topics t
    JOIN users u ON t.user_id = u.id
    WHERE t.category_id = :category_id
    ORDER BY 
        CASE WHEN t.status = 'open' THEN 0 ELSE 1 END,
        t.created_at DESC
    LIMIT :offset, :perPage
");

// Bind parameter dengan metode named
$stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':perPage', $perPage, PDO::PARAM_INT);
$stmt->execute();
$topics = $stmt->fetchAll();

// Atau alternatifnya dengan parameter positional semua:
/*
$stmt = $pdo->prepare("
    SELECT t.*, u.username,
           (SELECT COUNT(*) FROM replies WHERE topic_id = t.id) as reply_count,
           (SELECT MAX(created_at) FROM replies WHERE topic_id = t.id) as last_reply
    FROM topics t
    JOIN users u ON t.user_id = u.id
    WHERE t.category_id = ?
    ORDER BY 
        CASE WHEN t.status = 'open' THEN 0 ELSE 1 END,
        t.created_at DESC
    LIMIT ?, ?
");
$stmt->execute([$category_id, $offset, $perPage]);
$topics = $stmt->fetchAll();
*/

// Hitung total topik di kategori ini
$stmt = $pdo->prepare("SELECT COUNT(*) FROM topics WHERE category_id = ?");
$stmt->execute([$category_id]);
$totalTopics = $stmt->fetchColumn();
$totalPages = ceil($totalTopics / $perPage);

// Ambil statistik kategori
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT t.id) as total_topics,
        COUNT(r.id) as total_replies,
        MAX(t.created_at) as last_topic_date,
        (SELECT username FROM users u JOIN topics t2 ON u.id = t2.user_id 
         WHERE t2.category_id = ? ORDER BY t2.created_at DESC LIMIT 1) as last_poster
    FROM topics t
    LEFT JOIN replies r ON t.id = r.topic_id
    WHERE t.category_id = ?
");
$stmt->execute([$category_id, $category_id]);
$stats = $stmt->fetch();

// Ambil kategori lain untuk sidebar
$stmt = $pdo->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM topics WHERE category_id = c.id) as topic_count
    FROM categories c
    WHERE c.id != ?
    ORDER BY topic_count DESC
    LIMIT 5
");
$stmt->execute([$category_id]);
$otherCategories = $stmt->fetchAll();

// Ambil topik populer di kategori ini
$stmt = $pdo->prepare("
    SELECT t.id, t.title, t.views, COUNT(r.id) as reply_count
    FROM topics t
    LEFT JOIN replies r ON t.id = r.topic_id
    WHERE t.category_id = ?
    GROUP BY t.id
    ORDER BY (t.views + COUNT(r.id) * 5) DESC
    LIMIT 5
");
$stmt->execute([$category_id]);
$popularTopics = $stmt->fetchAll();
?>

<div class="category-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 8px; margin-bottom: 30px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
        <div>
            <h1 style="margin: 0 0 10px 0; font-size: 2em;"><?php echo htmlspecialchars($category['name']); ?></h1>
            <p style="margin: 0; opacity: 0.9; font-size: 1.1em;"><?php echo htmlspecialchars($category['description']); ?></p>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 2em; font-weight: bold;"><?php echo $stats['total_topics']; ?></div>
            <div style="opacity: 0.9;">Total Topik</div>
            <div style="margin-top: 10px; font-size: 0.9em;">💬 <?php echo $stats['total_replies']; ?> komentar</div>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 280px; gap: 30px;">
    <!-- Main Content - Daftar Topik -->
    <div>
        <!-- Breadcrumb -->
        <div style="margin-bottom: 20px; padding: 10px 0; border-bottom: 2px solid #eee;">
            <a href="index.php" style="color: #3498db; text-decoration: none;">Beranda</a> 
            <span style="color: #999; margin: 0 5px;">›</span>
            <span style="color: #666;"><?php echo htmlspecialchars($category['name']); ?></span>
        </div>

        <!-- Action Buttons -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
            <div>
                <span style="background-color: #e8f4fd; color: #2c3e50; padding: 5px 15px; border-radius: 20px; font-size: 14px;">
                    📌 Menampilkan <?php echo count($topics); ?> dari <?php echo $totalTopics; ?> topik
                </span>
            </div>
            <?php if (isLoggedIn()): ?>
                <a href="create_topic.php?category=<?php echo $category_id; ?>" class="btn btn-success" style="padding: 10px 25px;">
                    ➕ Buat Topik di Kategori Ini
                </a>
            <?php else: ?>
                <a href="login.php" class="btn" style="padding: 10px 25px;">
                    🔑 Login untuk Buat Topik
                </a>
            <?php endif; ?>
        </div>

        <!-- Daftar Topik -->
        <?php if (empty($topics)): ?>
            <div style="text-align: center; padding: 60px 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <div style="font-size: 4em; margin-bottom: 20px;">📭</div>
                <h3 style="color: #333; margin-bottom: 10px;">Belum Ada Topik di Kategori Ini</h3>
                <p style="color: #666; margin-bottom: 30px;">Jadilah yang pertama membuat topik diskusi!</p>
                <?php if (isLoggedIn()): ?>
                    <a href="create_topic.php?category=<?php echo $category_id; ?>" class="btn btn-success">Buat Topik Pertama</a>
                <?php else: ?>
                    <a href="login.php" class="btn">Login untuk Membuat Topik</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div style="background-color: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); overflow: hidden;">
                <!-- Header Tabel -->
                <div style="display: grid; grid-template-columns: 3fr 1fr 1fr 1fr; padding: 15px; background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; font-weight: bold; color: #495057;">
                    <div>Topik</div>
                    <div style="text-align: center;">Balasan</div>
                    <div style="text-align: center;">Dilihat</div>
                    <div style="text-align: right;">Aktivitas</div>
                </div>

                <!-- Daftar Topik -->
                <?php foreach ($topics as $index => $topic): ?>
                    <div style="display: grid; grid-template-columns: 3fr 1fr 1fr 1fr; padding: 15px; border-bottom: 1px solid #eee; <?php echo $index % 2 == 0 ? 'background-color: #fff;' : 'background-color: #fafafa;'; ?>">
                        <div>
                            <div style="margin-bottom: 5px;">
                                <?php if ($topic['status'] === 'closed'): ?>
                                    <span style="background-color: #dc3545; color: white; padding: 2px 8px; border-radius: 3px; font-size: 12px; margin-right: 5px;">🔒 Ditutup</span>
                                <?php endif; ?>
                                <a href="topic.php?id=<?php echo $topic['id']; ?>" style="color: #2c3e50; text-decoration: none; font-weight: bold; font-size: 1.1em;">
                                    <?php echo htmlspecialchars($topic['title']); ?>
                                </a>
                            </div>
                            <div style="font-size: 12px; color: #666;">
                                Oleh: <a href="profile.php?id=<?php echo $topic['user_id']; ?>" style="color: #3498db; text-decoration: none;"><?php echo htmlspecialchars($topic['username']); ?></a>
                                | <?php echo date('d M Y H:i', strtotime($topic['created_at'])); ?>
                            </div>
                        </div>
                        <div style="text-align: center; align-self: center;">
                            <span style="background-color: #e9ecef; padding: 3px 10px; border-radius: 15px; font-size: 14px;">
                                <?php echo $topic['reply_count']; ?>
                            </span>
                        </div>
                        <div style="text-align: center; align-self: center; color: #666;">
                            👁️ <?php echo $topic['views']; ?>
                        </div>
                        <div style="text-align: right; align-self: center; font-size: 12px; color: #666;">
                            <?php if ($topic['last_reply']): ?>
                                <div>Balasan terakhir</div>
                                <div><?php echo date('d M H:i', strtotime($topic['last_reply'])); ?></div>
                            <?php else: ?>
                                <span style="color: #999;">Belum ada balasan</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination" style="margin-top: 30px; display: flex; justify-content: center; gap: 5px; flex-wrap: wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?id=<?php echo $category_id; ?>&page=<?php echo $page-1; ?>" style="padding: 8px 12px; background-color: #fff; border: 1px solid #ddd; border-radius: 4px; color: #333; text-decoration: none;">&laquo; Sebelumnya</a>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $startPage + 4);
                    for ($i = $startPage; $i <= $endPage; $i++): 
                    ?>
                        <a href="?id=<?php echo $category_id; ?>&page=<?php echo $i; ?>" 
                           style="padding: 8px 12px; background-color: <?php echo $page == $i ? '#3498db' : '#fff'; ?>; border: 1px solid #ddd; border-radius: 4px; color: <?php echo $page == $i ? '#fff' : '#333'; ?>; text-decoration: none; font-weight: <?php echo $page == $i ? 'bold' : 'normal'; ?>;">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?id=<?php echo $category_id; ?>&page=<?php echo $page+1; ?>" style="padding: 8px 12px; background-color: #fff; border: 1px solid #ddd; border-radius: 4px; color: #333; text-decoration: none;">Selanjutnya &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div>
        <!-- Info Kategori -->
        <div style="background-color: #fff; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            <h3 style="margin-top: 0; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #3498db;">📊 Statistik Kategori</h3>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #666;">Total Topik:</span>
                    <span style="font-weight: bold;"><?php echo $stats['total_topics']; ?></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #666;">Total Komentar:</span>
                    <span style="font-weight: bold;"><?php echo $stats['total_replies']; ?></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #666;">Topik Terakhir:</span>
                    <span style="font-weight: bold;"><?php echo $stats['last_topic_date'] ? date('d M Y', strtotime($stats['last_topic_date'])) : '-'; ?></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #666;">Poster Terakhir:</span>
                    <span style="font-weight: bold;"><?php echo $stats['last_poster'] ? htmlspecialchars($stats['last_poster']) : '-'; ?></span>
                </div>
            </div>
        </div>

        <!-- Kategori Lainnya -->
        <?php if (!empty($otherCategories)): ?>
        <div style="background-color: #fff; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            <h3 style="margin-top: 0; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #3498db;">📌 Kategori Lainnya</h3>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php foreach ($otherCategories as $cat): ?>
                    <a href="category.php?id=<?php echo $cat['id']; ?>" style="display: flex; justify-content: space-between; text-decoration: none; color: #333; padding: 8px; border-radius: 4px; transition: background-color 0.2s;" 
                       onmouseover="this.style.backgroundColor='#f5f5f5'" onmouseout="this.style.backgroundColor='transparent'">
                        <span><?php echo htmlspecialchars($cat['name']); ?></span>
                        <span style="background-color: #e9ecef; padding: 2px 8px; border-radius: 12px; font-size: 12px;"><?php echo $cat['topic_count']; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Topik Populer di Kategori Ini -->
        <?php if (!empty($popularTopics)): ?>
        <div style="background-color: #fff; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            <h3 style="margin-top: 0; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #3498db;">🔥 Topik Populer</h3>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($popularTopics as $index => $topic): ?>
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                            <span style="background-color: <?php 
                                echo $index == 0 ? '#ffd700' : ($index == 1 ? '#c0c0c0' : ($index == 2 ? '#cd7f32' : '#e9ecef')); 
                            ?>; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold;">
                                <?php echo $index + 1; ?>
                            </span>
                            <a href="topic.php?id=<?php echo $topic['id']; ?>" style="color: #2c3e50; text-decoration: none; font-weight: 500; flex: 1;">
                                <?php echo htmlspecialchars(substr($topic['title'], 0, 30)) . (strlen($topic['title']) > 30 ? '...' : ''); ?>
                            </a>
                        </div>
                        <div style="font-size: 11px; color: #666; margin-left: 28px;">
                            👁️ <?php echo $topic['views']; ?> dilihat • 💬 <?php echo $topic['reply_count']; ?> balasan
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Link Cepat -->
        <div style="background-color: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            <h3 style="margin-top: 0; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #3498db;">⚡ Link Cepat</h3>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <a href="index.php" style="color: #3498db; text-decoration: none;">🏠 Beranda</a>
                <a href="search.php" style="color: #3498db; text-decoration: none;">🔍 Cari Topik</a>
                <?php if (isLoggedIn()): ?>
                    <a href="create_topic.php" style="color: #3498db; text-decoration: none;">➕ Buat Topik Baru</a>
                    <a href="profile.php" style="color: #3498db; text-decoration: none;">👤 Profil Saya</a>
                <?php else: ?>
                    <a href="login.php" style="color: #3498db; text-decoration: none;">🔑 Login</a>
                    <a href="register.php" style="color: #3498db; text-decoration: none;">📝 Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.pagination a:hover {
    background-color: #3498db !important;
    color: white !important;
    border-color: #3498db !important;
}

.category-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    position: relative;
    overflow: hidden;
}

.category-header::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 50%);
    opacity: 0.3;
    pointer-events: none;
}

@media (max-width: 768px) {
    div[style*="grid-template-columns: 1fr 280px"] {
        grid-template-columns: 1fr !important;
    }
    
    div[style*="grid-template-columns: 3fr 1fr 1fr 1fr"] {
        grid-template-columns: 2fr 1fr 1fr !important;
    }
    
    div[style*="grid-template-columns: 3fr 1fr 1fr 1fr"] > div:last-child {
        display: none;
    }
    
    .category-header {
        text-align: center;
    }
    
    .category-header > div {
        justify-content: center !important;
    }
    
    div[style*="text-align: right"] {
        text-align: center !important;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>
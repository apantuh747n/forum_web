<?php
require_once 'includes/header.php';
require_once 'config/functions.php';

// Ambil semua kategori dengan jumlah topik
$stmt = $pdo->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM topics WHERE category_id = c.id) as topic_count,
           (SELECT MAX(created_at) FROM topics WHERE category_id = c.id) as last_topic
    FROM categories c
    ORDER BY topic_count DESC, c.name
");
$categories = $stmt->fetchAll();

// Ambil topik terbaru dengan pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT t.*, u.username, c.name as category_name, c.id as category_id,
           (SELECT COUNT(*) FROM replies WHERE topic_id = t.id) as reply_count,
           (SELECT MAX(created_at) FROM replies WHERE topic_id = t.id) as last_reply
    FROM topics t
    JOIN users u ON t.user_id = u.id
    JOIN categories c ON t.category_id = c.id
    ORDER BY 
        CASE WHEN t.status = 'open' THEN 0 ELSE 1 END,
        t.created_at DESC
    LIMIT :offset, :perPage
");
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
$stmt->execute();
$topics = $stmt->fetchAll();

// Hitung total topik untuk pagination
$totalTopics = $pdo->query("SELECT COUNT(*) FROM topics")->fetchColumn();
$totalPages = ceil($totalTopics / $perPage);
?>

<h1>Selamat Datang di Forum Diskusi</h1>

<!-- Hero Section -->
<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px; border-radius: 8px; margin-bottom: 30px; text-align: center;">
    <h2 style="margin: 0 0 10px 0; font-size: 2.2em;">Forum Diskusi Komunitas</h2>
    <p style="margin: 0 0 20px 0; font-size: 1.2em; opacity: 0.9;">Tempat berbagi pengetahuan dan berdiskusi</p>
    <div style="display: flex; gap: 15px; justify-content: center;">
        <a href="search.php" class="btn" style="background-color: white; color: #667eea;">🔍 Cari Topik</a>
        <?php if (isLoggedIn()): ?>
            <a href="create_topic.php" class="btn" style="background-color: #ffd700; color: #333;">➕ Buat Topik Baru</a>
        <?php endif; ?>
    </div>
</div>

<!-- Statistik Cepat -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <?php
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalReplies = $pdo->query("SELECT COUNT(*) FROM replies")->fetchColumn();
    ?>
    <div style="background-color: #fff; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <div style="font-size: 2em; color: #3498db;">📚</div>
        <div style="font-size: 1.5em; font-weight: bold;"><?php echo $totalTopics; ?></div>
        <div style="color: #666;">Total Topik</div>
    </div>
    <div style="background-color: #fff; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <div style="font-size: 2em; color: #27ae60;">💬</div>
        <div style="font-size: 1.5em; font-weight: bold;"><?php echo $totalReplies; ?></div>
        <div style="color: #666;">Total Komentar</div>
    </div>
    <div style="background-color: #fff; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <div style="font-size: 2em; color: #e74c3c;">👥</div>
        <div style="font-size: 1.5em; font-weight: bold;"><?php echo $totalUsers; ?></div>
        <div style="color: #666;">Member</div>
    </div>
    <div style="background-color: #fff; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <div style="font-size: 2em; color: #f39c12;">🏷️</div>
        <div style="font-size: 1.5em; font-weight: bold;"><?php echo count($categories); ?></div>
        <div style="color: #666;">Kategori</div>
    </div>
</div>

<!-- Daftar Kategori -->
<div class="categories-section" style="margin-bottom: 40px;">
    <h2 style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
        <span>📂 Kategori Diskusi</span>
        <span style="font-size: 14px; font-weight: normal; color: #666;">(klik kategori untuk lihat topik)</span>
    </h2>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        <?php foreach ($categories as $category): ?>
            <a href="category.php?id=<?php echo $category['id']; ?>" style="text-decoration: none; color: inherit;">
                <div class="category-card" style="background-color: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: all 0.3s; border: 1px solid #eee; height: 100%;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                        <h3 style="margin: 0; color: #2c3e50;"><?php echo htmlspecialchars($category['name']); ?></h3>
                        <span style="background-color: #3498db; color: white; padding: 3px 10px; border-radius: 15px; font-size: 12px;">
                            <?php echo $category['topic_count']; ?> Topik
                        </span>
                    </div>
                    <p style="color: #666; margin: 10px 0; font-size: 14px;"><?php echo htmlspecialchars($category['description']); ?></p>
                    
                    <?php if ($category['last_topic']): ?>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; font-size: 12px; color: #999;">
                        <span>🕒 Topik terakhir: <?php echo date('d M Y', strtotime($category['last_topic'])); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div style="margin-top: 15px; color: #3498db; font-size: 13px; text-align: right;">
                        Lihat Topik →
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Topik Terbaru -->
<div class="topics-section">
    <h2 style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <span>📌 Topik Terbaru</span>
        <a href="search.php" style="font-size: 14px; color: #3498db; text-decoration: none;">Lihat Semua →</a>
    </h2>
    
    <?php if (empty($topics)): ?>
        <div style="text-align: center; padding: 60px 20px; background-color: #fff; border-radius: 8px;">
            <div style="font-size: 4em; margin-bottom: 20px;">📭</div>
            <h3 style="color: #333; margin-bottom: 10px;">Belum Ada Topik</h3>
            <p style="color: #666; margin-bottom: 30px;">Jadilah yang pertama membuat topik diskusi!</p>
            <?php if (isLoggedIn()): ?>
                <a href="create_topic.php" class="btn btn-success">Buat Topik Pertama</a>
            <?php else: ?>
                <a href="login.php" class="btn">Login untuk Membuat Topik</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="background-color: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); overflow: hidden;">
            <?php foreach ($topics as $index => $topic): ?>
                <div style="display: grid; grid-template-columns: auto 1fr auto; gap: 15px; padding: 20px; border-bottom: 1px solid #eee; <?php echo $index % 2 == 0 ? 'background-color: #fff;' : 'background-color: #fafafa;'; ?>">
                    <!-- Avatar/Icon -->
                    <div style="width: 50px; height: 50px; background-color: #3498db; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 20px;">
                        <?php echo strtoupper(substr($topic['username'], 0, 1)); ?>
                    </div>
                    
                    <!-- Konten Topik -->
                    <div style="flex: 1;">
                        <div style="margin-bottom: 8px;">
                            <?php if ($topic['status'] === 'closed'): ?>
                                <span style="background-color: #dc3545; color: white; padding: 2px 8px; border-radius: 3px; font-size: 12px; margin-right: 5px;">🔒 Ditutup</span>
                            <?php endif; ?>
                            <a href="topic.php?id=<?php echo $topic['id']; ?>" style="color: #2c3e50; text-decoration: none; font-weight: bold; font-size: 1.1em;">
                                <?php echo htmlspecialchars($topic['title']); ?>
                            </a>
                        </div>
                        
                        <!-- Preview konten -->
                        <?php 
                        $preview = strip_tags(parseContent($topic['content']));
                        if (strlen($preview) > 150) {
                            $preview = substr($preview, 0, 150) . '...';
                        }
                        ?>
                        <p style="color: #666; font-size: 13px; margin-bottom: 8px;"><?php echo $preview; ?></p>
                        
                        <!-- Meta info -->
                        <div style="display: flex; gap: 15px; font-size: 12px; color: #999; flex-wrap: wrap;">
                            <span>👤 <a href="profile.php?id=<?php echo $topic['user_id']; ?>" style="color: #3498db; text-decoration: none;"><?php echo htmlspecialchars($topic['username']); ?></a></span>
                            <span>📁 <a href="category.php?id=<?php echo $topic['category_id']; ?>" style="color: #3498db; text-decoration: none;"><?php echo htmlspecialchars($topic['category_name']); ?></a></span>
                            <span>🕒 <?php echo date('d M Y H:i', strtotime($topic['created_at'])); ?></span>
                        </div>
                    </div>
                    
                    <!-- Statistik -->
                    <div style="text-align: right; min-width: 100px;">
                        <div style="margin-bottom: 5px;">
                            <span style="background-color: #e9ecef; padding: 3px 10px; border-radius: 15px; font-size: 13px;">
                                💬 <?php echo $topic['reply_count']; ?>
                            </span>
                        </div>
                        <div style="color: #666; font-size: 12px;">
                            👁️ <?php echo $topic['views']; ?> dilihat
                        </div>
                        <?php if ($topic['last_reply']): ?>
                            <div style="color: #999; font-size: 11px; margin-top: 5px;">
                                Terakhir: <?php echo date('d M H:i', strtotime($topic['last_reply'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination" style="margin-top: 30px; display: flex; justify-content: center; gap: 5px;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>" style="padding: 8px 12px; background-color: #fff; border: 1px solid #ddd; border-radius: 4px; color: #333; text-decoration: none;">&laquo;</a>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $startPage + 4);
                for ($i = $startPage; $i <= $endPage; $i++): 
                ?>
                    <a href="?page=<?php echo $i; ?>" style="padding: 8px 12px; background-color: <?php echo $page == $i ? '#3498db' : '#fff'; ?>; border: 1px solid #ddd; border-radius: 4px; color: <?php echo $page == $i ? '#fff' : '#333'; ?>; text-decoration: none;">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page+1; ?>" style="padding: 8px 12px; background-color: #fff; border: 1px solid #ddd; border-radius: 4px; color: #333; text-decoration: none;">&raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.category-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    border-color: #3498db !important;
}

@media (max-width: 768px) {
    div[style*="grid-template-columns: auto 1fr auto"] {
        grid-template-columns: 1fr !important;
        text-align: center;
    }
    
    div[style*="width: 50px"] {
        margin: 0 auto;
    }
    
    div[style*="text-align: right"] {
        text-align: center !important;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>
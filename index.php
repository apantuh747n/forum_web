<?php
require_once 'includes/header.php';
require_once 'config/functions.php';

// Ambil semua kategori
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

// Ambil topik terbaru dengan pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT t.*, u.username, c.name as category_name,
           (SELECT COUNT(*) FROM replies WHERE topic_id = t.id) as reply_count
    FROM topics t
    JOIN users u ON t.user_id = u.id
    JOIN categories c ON t.category_id = c.id
    ORDER BY t.created_at DESC
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

<div class="categories-section">
    <h2>Kategori</h2>
    <div class="categories-list">
        <?php foreach ($categories as $category): ?>
            <?php
            $topicCount = $pdo->prepare("SELECT COUNT(*) FROM topics WHERE category_id = ?");
            $topicCount->execute([$category['id']]);
            $count = $topicCount->fetchColumn();
            ?>
            <div class="category-item">
                <div class="category-info">
                    <h3><a href="index.php?category=<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></a></h3>
                    <p><?php echo htmlspecialchars($category['description']); ?></p>
                </div>
                <div class="category-stats">
                    <span><?php echo $count; ?> Topik</span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="topics-section">
    <h2>Topik Terbaru</h2>
    <?php if (empty($topics)): ?>
        <p>Belum ada topik diskusi.</p>
    <?php else: ?>
        <div class="topics-grid">
            <?php foreach ($topics as $topic): ?>
                <div class="topic-card">
                    <h3><a href="topic.php?id=<?php echo $topic['id']; ?>"><?php echo htmlspecialchars($topic['title']); ?></a></h3>
                    <div class="topic-meta">
                        Oleh: <a href="profile.php?id=<?php echo $topic['user_id']; ?>"><?php echo htmlspecialchars($topic['username']); ?></a>
                        | Kategori: <?php echo htmlspecialchars($topic['category_name']); ?>
                        | <?php echo date('d M Y', strtotime($topic['created_at'])); ?>
                    </div>
                    
                    <?php 
                    // Parse content untuk preview
                    $parsedContent = parseContent($topic['content']);
                    $firstImage = extractFirstImage($parsedContent);
                    if ($firstImage): 
                    ?>
                    <div class="topic-image-preview">
                        <img src="<?php echo $firstImage; ?>" alt="Preview" 
                             style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 4px; margin: 10px 0;">
                    </div>
                    <?php endif; ?>
                    
                    <p><?php echo substr(strip_tags($parsedContent), 0, 200); ?>...</p>
                    <div class="topic-stats">
                        <span>👁️ <?php echo $topic['views']; ?> dilihat</span>
                        <span>💬 <?php echo $topic['reply_count']; ?> balasan</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" <?php echo $page == $i ? 'class="active"' : ''; ?>><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.topic-card {
    position: relative;
    overflow: hidden;
}

.topic-image-preview {
    position: relative;
    overflow: hidden;
    border-radius: 4px;
}

.topic-image-preview img {
    transition: transform 0.3s;
}

.topic-image-preview:hover img {
    transform: scale(1.05);
}

.topic-card p {
    color: #666;
    font-size: 14px;
    line-height: 1.6;
}
</style>

<?php require_once 'includes/footer.php'; ?>
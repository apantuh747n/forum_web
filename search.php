<?php
require_once 'includes/header.php';
require_once 'config/functions.php';

$keyword = isset($_GET['q']) ? trim($_GET['q']) : '';
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest'; // Default ke newest biar lebih umum
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Ambil semua kategori untuk filter
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$results = [];
$totalResults = 0;
$searchTime = 0;

if (!empty($keyword)) {
    $startTime = microtime(true);
    
    // Split keyword menjadi kata-kata terpisah untuk pencarian lebih fleksibel
    $keywords = explode(' ', $keyword);
    $keywords = array_filter($keywords, function($k) {
        return strlen($k) > 1; // Abaikan kata yang terlalu pendek (1 karakter)
    });
    
    // Jika tidak ada kata yang valid, gunakan keyword asli
    if (empty($keywords)) {
        $keywords = [$keyword];
    }
    
    // Bangun query dengan kondisi LIKE untuk setiap kata
    $conditions = [];
    $params = [];
    
    foreach ($keywords as $index => $word) {
        $conditions[] = "(t.title LIKE :search_{$index} OR t.content LIKE :search_{$index})";
        $params[":search_{$index}"] = "%$word%";
    }
    
    // Query untuk menghitung total
    $countQuery = "SELECT COUNT(DISTINCT t.id) as total 
                   FROM topics t 
                   JOIN users u ON t.user_id = u.id 
                   WHERE 1=1";
    
    if (!empty($conditions)) {
        $countQuery .= " AND (" . implode(" OR ", $conditions) . ")";
    }
    
    // Filter kategori
    if ($category_id > 0) {
        $countQuery .= " AND t.category_id = :category_id";
        $params[':category_id'] = $category_id;
    }
    
    // Query untuk mengambil data
    $query = "SELECT DISTINCT t.*, u.username, c.name as category_name,
                     (SELECT COUNT(*) FROM replies WHERE topic_id = t.id) as reply_count
              FROM topics t 
              JOIN users u ON t.user_id = u.id 
              JOIN categories c ON t.category_id = c.id 
              WHERE 1=1";
    
    if (!empty($conditions)) {
        $query .= " AND (" . implode(" OR ", $conditions) . ")";
    }
    
    // Filter kategori
    if ($category_id > 0) {
        $query .= " AND t.category_id = :category_id";
    }
    
    // Sorting
    switch ($sort) {
        case 'oldest':
            $query .= " ORDER BY t.created_at ASC";
            break;
        case 'most_viewed':
            $query .= " ORDER BY t.views DESC";
            break;
        case 'most_replied':
            $query .= " ORDER BY reply_count DESC";
            break;
        case 'relevance':
            // Relevance berdasarkan jumlah kata yang cocok
            $query .= " ORDER BY 
                        (CASE WHEN t.title LIKE :exact_title THEN 10 ELSE 0 END) +
                        (CASE WHEN t.content LIKE :exact_content THEN 5 ELSE 0 END) +
                        (CASE WHEN t.title LIKE :title_like THEN 3 ELSE 0 END) +
                        (CASE WHEN t.content LIKE :content_like THEN 1 ELSE 0 END) DESC,
                        t.created_at DESC";
            $params[':exact_title'] = "%$keyword%";
            $params[':exact_content'] = "%$keyword%";
            $params[':title_like'] = "%" . implode("%", $keywords) . "%";
            $params[':content_like'] = "%" . implode("%", $keywords) . "%";
            break;
        default: // newest
            $query .= " ORDER BY t.created_at DESC";
    }
    
    // Pagination
    $query .= " LIMIT :offset, :perPage";
    
    // Hitung total results
    $stmt = $pdo->prepare($countQuery);
    foreach ($params as $key => $value) {
        if ($key !== ':offset' && $key !== ':perPage') {
            $stmt->bindValue($key, $value);
        }
    }
    $stmt->execute();
    $totalResults = $stmt->fetch()['total'];
    $totalPages = ceil($totalResults / $perPage);
    
    // Ambil results jika ada
    if ($totalResults > 0) {
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll();
    }
    
    $endTime = microtime(true);
    $searchTime = round($endTime - $startTime, 2);
}

// Fungsi untuk mendapatkan cuplikan teks dengan kata kunci
function getSearchExcerpt($text, $keywords, $length = 200) {
    $text = strip_tags($text);
    $lowerText = strtolower($text);
    
    // Cari posisi kata kunci pertama yang cocok
    $bestPosition = 0;
    $bestScore = 0;
    
    foreach ($keywords as $keyword) {
        $keyword = strtolower($keyword);
        $pos = strpos($lowerText, $keyword);
        if ($pos !== false) {
            // Beri skor berdasarkan posisi (lebih awal lebih baik)
            $score = strlen($keyword) / ($pos + 1);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestPosition = $pos;
            }
        }
    }
    
    if ($bestScore > 0) {
        $start = max(0, $bestPosition - 50);
        $excerpt = substr($text, $start, $length);
        if ($start > 0) $excerpt = '...' . $excerpt;
        if (strlen($text) > $start + $length) $excerpt .= '...';
        
        // Highlight semua kata kunci
        foreach ($keywords as $keyword) {
            $excerpt = preg_replace('/(' . preg_quote($keyword, '/') . ')/i', '<span class="highlight">$1</span>', $excerpt);
        }
        
        return $excerpt;
    }
    
    // Jika tidak ada kata kunci yang cocok, ambil awal teks
    return substr($text, 0, $length) . '...';
}
?>

<h1>Pencarian</h1>

<!-- Search Form -->
<div class="search-form" style="background-color: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
    <form method="GET" action="search.php">
        <div style="display: flex; gap: 10px; margin-bottom: 15px;">
            <input type="text" 
                   name="q" 
                   value="<?php echo htmlspecialchars($keyword); ?>" 
                   placeholder="Cari topik... (contoh: teknologi programming)" 
                   class="form-control"
                   style="flex: 1; padding: 12px; font-size: 16px;"
                   autofocus>
            <button type="submit" class="btn" style="padding: 12px 30px;">Cari</button>
        </div>
        
        <!-- Filter Options -->
        <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
            <div style="flex: 1; min-width: 200px;">
                <label for="category" style="display: block; margin-bottom: 5px; font-weight: bold;">Kategori:</label>
                <select name="category" id="category" class="form-control">
                    <option value="0">Semua Kategori</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="flex: 1; min-width: 200px;">
                <label for="sort" style="display: block; margin-bottom: 5px; font-weight: bold;">Urutkan:</label>
                <select name="sort" id="sort" class="form-control">
                    <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Terbaru</option>
                    <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Terlama</option>
                    <option value="most_viewed" <?php echo $sort == 'most_viewed' ? 'selected' : ''; ?>>Paling Dilihat</option>
                    <option value="most_replied" <?php echo $sort == 'most_replied' ? 'selected' : ''; ?>>Paling Banyak Dibalas</option>
                    <option value="relevance" <?php echo $sort == 'relevance' ? 'selected' : ''; ?>>Relevansi</option>
                </select>
            </div>
        </div>
        
        <!-- Info pencarian -->
        <div style="margin-top: 10px; font-size: 13px; color: #666;">
            <span>💡 Tips: Cukup tulis kata kunci saja, misal "teknologi" atau "programming php"</span>
        </div>
    </form>
</div>

<!-- Search Results -->
<?php if (!empty($keyword)): ?>
    <div class="search-results">
        <div style="margin-bottom: 15px; padding: 10px; background-color: #e8f4fd; border-radius: 4px; color: #2c3e50;">
            <?php if ($totalResults > 0): ?>
                <strong>📊 Ditemukan <?php echo $totalResults; ?> topik</strong> untuk pencarian "<?php echo htmlspecialchars($keyword); ?>" 
                (waktu: <?php echo $searchTime; ?> detik)
            <?php else: ?>
                <strong>😕 Tidak ditemukan topik</strong> untuk "<?php echo htmlspecialchars($keyword); ?>"
            <?php endif; ?>
        </div>
        
        <?php if (!empty($results)): ?>
            <div class="topics-grid">
                <?php foreach ($results as $topic): ?>
                    <div class="topic-card">
                        <h3><a href="topic.php?id=<?php echo $topic['id']; ?>"><?php echo htmlspecialchars($topic['title']); ?></a></h3>
                        <div class="topic-meta">
                            Oleh: <a href="profile.php?id=<?php echo $topic['user_id']; ?>"><?php echo htmlspecialchars($topic['username']); ?></a>
                            | Kategori: <?php echo htmlspecialchars($topic['category_name']); ?>
                            | <?php echo date('d M Y', strtotime($topic['created_at'])); ?>
                        </div>
                        
                        <!-- Preview konten dengan kata kunci di-highlight -->
                        <div class="search-excerpt">
                            <?php 
                            $excerpt = getSearchExcerpt($topic['content'], $keywords);
                            echo '<p>' . $excerpt . '</p>';
                            ?>
                        </div>
                        
                        <div class="topic-stats">
                            <span>👁️ <?php echo $topic['views']; ?> dilihat</span>
                            <span>💬 <?php echo $topic['reply_count']; ?> balasan</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?q=<?php echo urlencode($keyword); ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page-1; ?>">&laquo; Sebelumnya</a>
                    <?php endif; ?>
                    
                    <?php 
                    // Tampilkan max 5 halaman
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $startPage + 4);
                    for ($i = $startPage; $i <= $endPage; $i++): 
                    ?>
                        <a href="?q=<?php echo urlencode($keyword); ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>&page=<?php echo $i; ?>" 
                           <?php echo $page == $i ? 'class="active"' : ''; ?>><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?q=<?php echo urlencode($keyword); ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page+1; ?>">Selanjutnya &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div style="text-align: center; padding: 40px; background-color: #fff; border-radius: 8px;">
                <p style="font-size: 18px; color: #666; margin-bottom: 20px;">Tidak ada topik yang cocok dengan pencarian Anda</p>
                
                <div style="max-width: 400px; margin: 0 auto 30px; text-align: left; background-color: #f8f9fa; padding: 20px; border-radius: 8px;">
                    <p style="font-weight: bold; margin-bottom: 10px;">🔍 Saran:</p>
                    <ul style="list-style: none; color: #666;">
                        <li>• Coba dengan kata kunci yang lebih umum</li>
                        <li>• Gunakan kata kunci tunggal (misal: "php" bukan "pemrograman php")</li>
                        <li>• Periksa ejaan kata kunci</li>
                        <li>• Coba kategori yang berbeda</li>
                    </ul>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                    <a href="create_topic.php" class="btn btn-success">Buat Topik Baru</a>
                    <a href="search.php" class="btn">Coba Pencarian Lain</a>
                </div>
                
                <!-- Topik terbaru sebagai alternatif -->
                <?php
                $latestTopics = $pdo->query("
                    SELECT t.title, t.id 
                    FROM topics t 
                    ORDER BY t.created_at DESC 
                    LIMIT 5
                ")->fetchAll();
                
                if (!empty($latestTopics)): 
                ?>
                <div style="margin-top: 40px;">
                    <h3 style="margin-bottom: 15px;">📌 Topik Terbaru</h3>
                    <div style="display: flex; flex-direction: column; gap: 10px; max-width: 400px; margin: 0 auto;">
                        <?php foreach ($latestTopics as $topic): ?>
                            <a href="topic.php?id=<?php echo $topic['id']; ?>" style="color: #3498db; text-decoration: none;">
                                • <?php echo htmlspecialchars($topic['title']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
<?php else: ?>
    <!-- Tampilan awal sebelum mencari -->
    <div style="text-align: center; padding: 40px 20px; background-color: #fff; border-radius: 8px;">
        <h2 style="margin-bottom: 20px; color: #333;">🔍 Cari Topik Diskusi</h2>
        <p style="color: #666; margin-bottom: 30px; max-width: 500px; margin-left: auto; margin-right: auto;">
            Masukkan kata kunci untuk mencari topik. Bisa dengan satu kata atau beberapa kata.
        </p>
        
        <div style="max-width: 500px; margin: 0 auto;">
            <form method="GET" action="search.php">
                <div style="display: flex; gap: 10px; flex-direction: column;">
                    <input type="text" name="q" placeholder="Contoh: teknologi, php, programming, olahraga..." 
                           class="form-control" style="padding: 15px; font-size: 16px;" autofocus>
                    <button type="submit" class="btn" style="padding: 15px;">Cari Topik</button>
                </div>
            </form>
        </div>
        
        <!-- Pencarian Populer -->
        <div style="margin-top: 40px;">
            <h3 style="margin-bottom: 15px;">🔥 Pencarian Populer</h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: center;">
                <?php
                $popularKeywords = ['teknologi', 'php', 'programming', 'game', 'musik', 'olahraga', 'sepak bola', 'film', 'pendidikan', 'karir'];
                foreach ($popularKeywords as $kw): 
                ?>
                    <a href="search.php?q=<?php echo urlencode($kw); ?>" class="btn" style="background-color: #f0f0f0; color: #333; border: 1px solid #ddd;">
                        <?php echo $kw; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Statistik cepat -->
        <div style="margin-top: 40px; display: flex; gap: 20px; justify-content: center; color: #666; font-size: 14px;">
            <?php
            $totalTopics = $pdo->query("SELECT COUNT(*) FROM topics")->fetchColumn();
            $totalReplies = $pdo->query("SELECT COUNT(*) FROM replies")->fetchColumn();
            ?>
            <span>📚 Total Topik: <?php echo $totalTopics; ?></span>
            <span>💬 Total Komentar: <?php echo $totalReplies; ?></span>
        </div>
    </div>
<?php endif; ?>

<style>
.search-excerpt {
    margin: 10px 0;
    font-size: 14px;
    color: #555;
    line-height: 1.6;
    background-color: #f9f9f9;
    padding: 10px;
    border-radius: 4px;
    border-left: 3px solid #3498db;
}

.highlight {
    background-color: #fff3cd;
    padding: 2px 4px;
    font-weight: bold;
    color: #856404;
    border-radius: 2px;
}

.topic-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border: 1px solid #eee;
}

.topic-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    border-color: #3498db;
}

.pagination {
    margin-top: 30px;
    justify-content: center;
    gap: 5px;
}

.pagination a {
    padding: 8px 12px;
    background-color: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    color: #333;
    text-decoration: none;
    transition: all 0.2s;
}

.pagination a:hover {
    background-color: #3498db;
    color: white;
    border-color: #3498db;
}

.pagination a.active {
    background-color: #3498db;
    color: white;
    border-color: #3498db;
}

.search-form {
    background-color: #fff;
    border: 1px solid #e0e0e0;
}

@media (max-width: 768px) {
    .search-form form > div:first-child {
        flex-direction: column;
    }
    
    .search-form button {
        width: 100%;
    }
    
    .pagination {
        flex-wrap: wrap;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>
<?php
require_once __DIR__ . '/../config/database.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forum Diskusi</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">
                <a href="index.php">Forum Diskusi</a>
            </div>
            
            <!-- Search Bar -->
            <div class="search-bar">
                <form action="search.php" method="GET" style="display: flex; gap: 5px;">
                    <input type="text" 
                           name="q" 
                           placeholder="Cari topik..." 
                           value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>"
                           style="padding: 8px; border: none; border-radius: 4px; width: 250px;">
                    <button type="submit" style="padding: 8px 15px; background-color: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        🔍 Cari
                    </button>
                </form>
            </div>
            
            <nav>
                <ul class="nav-menu">
                    <li><a href="index.php">Beranda</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li><a href="create_topic.php">Buat Topik</a></li>
                        <li><a href="profile.php">Profil (<?php echo $_SESSION['username']; ?>)</a></li>
                        <?php if (isAdmin()): ?>
                            <li><a href="admin/index.php">Admin Panel</a></li>
                        <?php endif; ?>
                        <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    
    <style>
    header .container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .search-bar {
        flex: 1;
        max-width: 400px;
    }
    
    .search-bar form {
        display: flex;
        gap: 5px;
    }
    
    .search-bar input {
        flex: 1;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }
    
    .search-bar input:focus {
        outline: none;
        border-color: #3498db;
    }
    
    .search-bar button {
        padding: 8px 15px;
        background-color: #3498db;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.3s;
    }
    
    .search-bar button:hover {
        background-color: #2980b9;
    }
    
    @media (max-width: 768px) {
        header .container {
            flex-direction: column;
        }
        
        .search-bar {
            max-width: 100%;
            width: 100%;
        }
        
        .nav-menu {
            flex-direction: column;
            width: 100%;
            text-align: center;
        }
    }
    </style>
    
    <main class="main-content">
        <div class="container">
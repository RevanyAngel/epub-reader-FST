<?php
require_once 'functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('app_name'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
    // Langsung terapkan tema sebelum halaman render sepenuhnya
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-brand">
                <a href="index.php"><?php echo t('app_name'); ?></a>
            </div>
            
            <?php if(isset($_SESSION['user_id'])): ?>
            <div class="nav-menu">
                <a href="dashboard.php"><i class="fas fa-home"></i> <?php echo t('dashboard'); ?></a>
                <a href="upload.php"><i class="fas fa-upload"></i> <?php echo t('upload_book'); ?></a>
                <a href="dashboard.php?view=mybooks"><i class="fas fa-book"></i> <?php echo t('my_books'); ?></a>
                
                <div class="nav-dropdown">
                    <button class="nav-dropbtn">
                        <i class="fas fa-graduation-cap"></i> <?php echo t('category'); ?>
                    </button>
                    <div class="nav-dropdown-content">
                        <a href="dashboard.php"><?php echo t('all_categories'); ?></a>
                        <?php $categories = getCategories(); ?>
                        <?php foreach($categories as $category): ?>
                            <a href="dashboard.php?category=<?php echo $category['id']; ?>">
                                <?php echo htmlspecialchars($category['display_name'] ?? $category['name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <a href="bookmarks.php"><i class="fas fa-bookmark"></i> Bookmark</a>
                
                <div class="nav-dropdown">
                    <button class="nav-dropbtn">
                        <i class="fas fa-globe"></i> <?php echo t('language'); ?>
                    </button>
                    <div class="nav-dropdown-content">
                        <a href="?lang=id">Indonesia</a>
                        <a href="?lang=en">English</a>
                    </div>
                </div>
                <button id="theme-toggle" class="theme-toggle-btn" title="Ganti Tema">
                    <i class="fas fa-moon"></i>
                </button>
                <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> <?php echo t('logout'); ?></a>
            </div>
            <?php endif; ?>
        </nav>
    </header>
    
    <main class="container">
    <?php if(isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
            <button type="button" class="close-alert">&times;</button>
            <?php 
                echo $_SESSION['message'];
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
            ?>
        </div>
    <?php endif; ?>
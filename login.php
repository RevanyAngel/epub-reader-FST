<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect jika sudah login
if(isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Proses login
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['language'] = $user['language'];
            
            $_SESSION['message'] = t('login_success');
            $_SESSION['message_type'] = 'success';
            header('Location: dashboard.php');
            exit();
        } else {
            $_SESSION['message'] = t('login_error');
            $_SESSION['message_type'] = 'error';
        }
    } catch(PDOException $e) {
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="auth-container">
    <h2><?php echo t('login'); ?></h2>
    
    <form method="POST" action="">
        <div class="form-group">
            <label for="username"><?php echo t('username'); ?> / Email</label>
            <input type="text" id="username" name="username" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label for="password"><?php echo t('password'); ?></label>
            <input type="password" id="password" name="password" class="form-control" required>
        </div>
        
        <button type="submit" class="btn btn-block"><?php echo t('login'); ?></button>
        
        <p style="text-align: center; margin-top: 20px;">
            <?php echo t('no_account'); ?> 
            <a href="register.php"><?php echo t('register'); ?></a>
        </p>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
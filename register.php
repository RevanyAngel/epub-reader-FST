<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect jika sudah login
if(isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Proses registrasi
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validasi
    if($password !== $confirm_password) {
        $_SESSION['message'] = "Passwords do not match";
        $_SESSION['message_type'] = 'error';
    } else {
        try {
            // Cek apakah username atau email sudah ada
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if($stmt->rowCount() > 0) {
                $_SESSION['message'] = "Username or email already exists";
                $_SESSION['message_type'] = 'error';
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user baru
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
                $stmt->execute([$username, $email, $hashed_password]);
                
                $_SESSION['message'] = t('register_success');
                $_SESSION['message_type'] = 'success';
                header('Location: login.php');
                exit();
            }
        } catch(PDOException $e) {
            $_SESSION['message'] = "Error: " . $e->getMessage();
            $_SESSION['message_type'] = 'error';
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="auth-container">
    <h2><?php echo t('register'); ?></h2>
    
    <form method="POST" action="">
        <div class="form-group">
            <label for="username"><?php echo t('username'); ?></label>
            <input type="text" id="username" name="username" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label for="email"><?php echo t('email'); ?></label>
            <input type="email" id="email" name="email" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label for="password"><?php echo t('password'); ?></label>
            <input type="password" id="password" name="password" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label for="confirm_password"><?php echo t('confirm_password'); ?></label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
        </div>
        
        <button type="submit" class="btn btn-block btn-success"><?php echo t('register'); ?></button>
        
        <p style="text-align: center; margin-top: 20px;">
            <?php echo t('have_account'); ?> 
            <a href="login.php"><?php echo t('login'); ?></a>
        </p>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
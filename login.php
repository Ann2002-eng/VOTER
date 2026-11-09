<?php
// 1. Start session at the very top
session_start();

// 2. Database Connection
$conn = new mysqli("127.0.0.1", "root", "", "school_system"); 

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 3. Process Login Form
if (isset($_POST['login_btn'])) {
    // Trim to remove accidental spaces
    $email = trim($_POST['pmail']);
    $pass  = trim($_POST['pass']);

    // Fetch User Data including role
    $stmt = $conn->prepare("SELECT id, first_name, password, user_role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        // Verify Hashed Password
        if (password_verify($pass, $user['password'])) {
            
            // Set Session Variables
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['first_name'];
            $_SESSION['user_role'] = $user['user_role']; 

            // LOG THE LOGIN (Audit Log)
            $ip = $_SERVER['REMOTE_ADDR'];
            $log = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'User Logged In', ?)");
            $log->bind_param("is", $user['id'], $ip);
            $log->execute();

            // Role-Based Redirection
            if ($user['user_role'] === 'Admin') {
                header("Location: admin.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $error = "Incorrect password. Please try again.";
        }
    } else {
        $error = "No user account found with that email.";
    }
}
?>

<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width,initial-scale=1" />
        <title>Login | E-School Voting</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <div class="login-wrap">
            <div class="card" role="main" aria-labelledby="login-title">
                <h2 id="login-title">Sign in to your account</h2>
                <p class="lead">Enter your credentials to access the voting system.</p>

                <?php if(isset($error)): ?>
                        <div class="error-box"><?php echo $error; ?></div>
                <?php endif; ?>

                <form action="login.php" method="POST" novalidate>
                    <div class="field">
                        <label for="email">Email address</label>
                        <input id="email" name="pmail" type="email" placeholder="admin@school.com" required>
                    </div>

                    <div class="field password-row">
                        <label for="password">Password</label>
                        <input id="password" name="pass" type="password" placeholder="Your password" required>
                        <button type="button" class="toggle-pass" onclick="toggle('password')" aria-label="Toggle password">Show</button>
                    </div>

                    <button class="btn" type="submit" name="login_btn">Sign In</button>
                </form>

                <div class="help">Don't have an account? <a class="link" href="index.html">Register here</a></div>
            </div>
        </div>

        <script>
            function toggle(id){const e=document.getElementById(id); if(!e) return; e.type = e.type === 'password' ? 'text' : 'password';}
        </script>
    </body>
</html>


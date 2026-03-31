<?php 

include_once 'config/config.php';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $email = $_POST['email'];
    $password = $_POST['pass'];
    $confirmPassword = $_POST['confirm_pass'];
    $firstName = $_POST['first_name'];
    $lastName = $_POST['last_name'];
    $phone = $_POST['phone'];

    $targetDir = "uploads/avatars/";
    $defaultAvatar = "asset/image/default_avatar.png";
    $profilePhotoUrl = $defaultAvatar;

    if(isset($_FILES['avatar']) && $_FILES['avatar']['error']=== UPLOAD_ERR_OK){
        $fileTmpPath = $_FILES['avatar']['tmp_name'];
        $fileName = $_FILES['avatar']['name'];
        $fileExtension = strtolower(pathinfo($fileName,PATHINFO_EXTENSION));

        $allowedExt = ['jpg','jpeg','png','gif'];
        if(in_array($fileExtension, $allowedExt)){
            //Use UUID to generate a unique file name to avoid conflicts
            $newFileName = vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex(random_bytes(16)),4)) . '.' . $fileExtension;
            $destPath = $targetDir . $newFileName;

            if(move_uploaded_file($fileTmpPath, $destPath)){
                $profilePhotoUrl = $destPath;
            } else {
                echo json_encode(["error" => "Failed to upload avatar."]);
                exit();
            }
        }
    }

    try{
        $pdo->beginTransaction();

        $userId = vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex(random_bytes(16)),4));

        if($password !== $confirmPassword){
            echo json_encode(["error" => "Passwords do not match."]);
            exit();
        }
        $passwordHash = password_hash($confirmPassword,PASSWORD_DEFAULT);

        $roleStmt = $pdo->prepare("SELECT RoleId FROM Roles WHERE `RoleName` =  'Member' LIMIT 1");
        $roleStmt->execute();
        $role = $roleStmt->fetchColumn();

        $insUser = $pdo->prepare("INSERT INTO Users (`UserId`,`RoleId`,`Email`,`PasswordHash`,`CreatedDate`)VALUES (?,?,?,?,?)");
        $insUser->execute([$userId, $role, $email, $passwordHash, date('Y-m-d H:i:s')]);

        $profileId = vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex(random_bytes(16)),4));
        $insProfile = $pdo->prepare("INSERT INTO UserProfile (`ProfileId`,`UserId`,`FirstName`,`LastName`,`PhoneNumber`,`ProfilePhotoUrl`,`CreateDate`)VALUES (?,?,?,?,?,?,?)");
        $insProfile->execute([$profileId, $userId, $firstName, $lastName, $phone, $profilePhotoUrl, date('Y-m-d H:i:s')]);

        $pdo->commit();
        echo json_encode(["message" => "Registration successful. You can now log in."]);
        exit();
    }catch(Exception $e){
        $pdo->rollBack();
        echo json_encode(["error" => "An error occurred during registration. Please try again."]);
        exit();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Commerce | Create Account</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Space+Grotesk:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #f0faf5 0%, #e6f4ff 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: #1b2530;
        }

        /* ── Navigation ── */
        .navbar-brand { font-family: 'Space Grotesk', sans-serif; font-weight: 700; }

        /* ── Auth Container ── */
        .auth-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .auth-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(10, 36, 60, 0.12);
            width: 100%;
            max-width: 480px;
            padding: 48px 40px;
            position: relative;
            overflow: hidden;
        }

        .auth-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(15,143,111,.05), transparent);
            pointer-events: none;
        }

        .auth-content { position: relative; z-index: 1; }

        .auth-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .auth-header h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: -.02em;
            margin-bottom: 8px;
            color: #0b6f56;
        }

        .auth-header p {
            color: #6b7c8d;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 8px;
            color: #1b2530;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .form-control, .form-select {
            border: 1.5px solid #e2ede9;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 14px;
            background: #f8fbf9;
            color: #1b2530;
            transition: border-color .2s ease, background .2s ease;
            font-family: 'Outfit', sans-serif;
        }

        .form-control:focus, .form-select:focus {
            border-color: #0f8f6f;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(15,143,111,.1);
            outline: none;
        }

        .form-control::placeholder {
            color: #9eb3c0;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-row.full {
            grid-template-columns: 1fr;
        }

        .avatar-upload {
            position: relative;
            margin-bottom: 18px;
        }

        .avatar-preview {
            width: 100px;
            height: 100px;
            border-radius: 16px;
            background: linear-gradient(135deg, #eef8f4, #e8f3ff);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            border: 2px dashed #d4ede5;
            overflow: hidden;
            cursor: pointer;
            transition: border-color .2s ease;
        }

        .avatar-preview:hover {
            border-color: #0f8f6f;
        }

        .avatar-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-preview.no-image {
            font-size: 40px;
            color: #0f8f6f;
        }

        #avatar {
            display: none;
        }

        .avatar-text {
            font-size: 12px;
            color: #6b7c8d;
            margin-top: 8px;
        }

        .btn-register {
            background: linear-gradient(135deg, #0f8f6f, #0b6f56);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-weight: 700;
            padding: 14px 28px;
            font-size: 15px;
            width: 100%;
            transition: transform .15s ease, box-shadow .15s ease;
            cursor: pointer;
            margin-top: 24px;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(11,111,86,.30);
            color: #fff;
        }

        .btn-register:active {
            transform: translateY(0);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 24px 0;
            gap: 12px;
            font-size: 13px;
            color: #9eb3c0;
        }

        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2ede9;
        }

        .auth-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #6b7c8d;
        }

        .auth-footer a {
            color: #0f8f6f;
            text-decoration: none;
            font-weight: 600;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        .alert {
            border: none;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: rgba(20,122,82,.1);
            color: #0b6f56;
            border-left: 4px solid #0f8f6f;
        }

        .alert-danger {
            background: rgba(166,58,43,.1);
            color: #a63a2b;
            border-left: 4px solid #d32f2f;
        }

        .password-instructions {
            background: rgba(15,143,111,.05);
            border-left: 3px solid #0f8f6f;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 12px;
            color: #1b2530;
            margin-top: 8px;
            line-height: 1.5;
        }

        @media (max-width: 576px) {
            .auth-card {
                padding: 32px 24px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .auth-header h1 {
                font-size: 1.4rem;
            }
        }
    </style>
</head>
<body>
<?php include 'layout/nav.php'; ?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-content">
            <div class="auth-header">
                <h1>Create Account</h1>
                <p>Join our community and start shopping today</p>
            </div>

            <div id="alertContainer"></div>

            <form id="registerForm" action="member_register.php" method="POST" enctype="multipart/form-data" onsubmit="return handleRegister(event)">
                <!-- Account Section -->
                <div class="form-row full">
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" name="email" id="email" placeholder="you@example.com" required>
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="pass" id="pass" placeholder="Create a strong password" required>
                        <div class="password-instructions">
                            <i class="fas fa-shield-alt"></i> At least 8 characters with mix of letters & numbers
                        </div>
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" name="confirm_pass" id="confirm_pass" placeholder="Confirm your password" required>
                    </div>
                </div>

                <div class="divider">Account Details</div>

                <!-- Profile Section -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control" name="first_name" id="first_name" placeholder="John" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name" id="last_name" placeholder="Doe" required>
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" name="phone" id="phone" placeholder="+60 12345 6789" required>
                    </div>
                </div>

                <!-- Avatar Upload -->
                <div class="avatar-upload">
                    <label class="form-label">Profile Picture</label>
                    <div class="avatar-preview no-image" id="avatarPreview" onclick="document.getElementById('avatar').click();">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <input type="file" name="avatar" id="avatar" accept="image/*">
                    <div class="avatar-text">Click to upload a profile picture (optional)</div>
                </div>

                <button type="submit" class="btn-register">
                    <i class="fas fa-user-plus me-2"></i>Create Account
                </button>

                <div class="auth-footer">
                    Already have an account? <a href="member_login.php">Sign in here</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Avatar preview handler
    document.getElementById('avatar').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const preview = document.getElementById('avatarPreview');
        
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(event) {
                preview.innerHTML = '<img src="' + event.target.result + '" alt="Preview">';
                preview.classList.remove('no-image');
            };
            reader.readAsDataURL(file);
        }
    });

    // Registration form handler
    function handleRegister(event) {
        event.preventDefault();
        const form = document.getElementById('registerForm');
        const formData = new FormData(form);

        fetch('member_register.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            const alertContainer = document.getElementById('alertContainer');
            
            if (data.error) {
                alertContainer.innerHTML = `<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>${data.error}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>`;
            } else if (data.message) {
                alertContainer.innerHTML = `<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>${data.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>`;
                form.reset();
                document.getElementById('avatarPreview').innerHTML = '<i class="fas fa-user-circle"></i>';
                document.getElementById('avatarPreview').classList.add('no-image');
                
                setTimeout(() => {
                    window.location.href = 'member_login.php';
                }, 2000);
            }
        })
        .catch(error => {
            document.getElementById('alertContainer').innerHTML = `<div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>An error occurred. Please try again.
            </div>`;
        });
        
        return false;
    }
</script>
</body>
</html>
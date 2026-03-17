<?php

require_once 'config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: member_login.php");
    exit();
}

$userId = $_SESSION['user_id'];

// ─── AJAX POST handlers ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    // 1. Update Profile
    if ($action === 'update_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name']  ?? '');
        $phone     = trim($_POST['phone']      ?? '');

        $photoUrl = null;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $fileExt    = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            $allowedExt = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($fileExt, $allowedExt)) {
                echo json_encode(['error' => 'Invalid file type.']);
                exit();
            }
            $newName  = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4)) . '.' . $fileExt;
            $destPath = 'uploads/avatars/' . $newName;
            if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $destPath)) {
                echo json_encode(['error' => 'Failed to upload avatar.']);
                exit();
            }
            $photoUrl = $destPath;
        }

        try {
            if ($photoUrl) {
                $stmt = $pdo->prepare("UPDATE UserProfile SET FirstName=?, LastName=?, PhoneNumber=?, ProfilePhotoUrl=?, UpdateDate=NOW() WHERE UserId=?");
                $stmt->execute([$firstName, $lastName, $phone, $photoUrl, $userId]);
            } else {
                $stmt = $pdo->prepare("UPDATE UserProfile SET FirstName=?, LastName=?, PhoneNumber=?, UpdateDate=NOW() WHERE UserId=?");
                $stmt->execute([$firstName, $lastName, $phone, $userId]);
            }
            echo json_encode(['message' => 'Profile updated successfully.', 'photo' => $photoUrl]);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Failed to update profile.']);
        }
        exit();
    }

    // 2. Change Password
    if ($action === 'change_password') {
        $currentPass = $_POST['current_password']  ?? '';
        $newPass     = $_POST['new_password']       ?? '';
        $confirmPass = $_POST['confirm_password']   ?? '';

        if ($newPass !== $confirmPass) {
            echo json_encode(['error' => 'New passwords do not match.']);
            exit();
        }
        if (strlen($newPass) < 8) {
            echo json_encode(['error' => 'Password must be at least 8 characters.']);
            exit();
        }
        try {
            $stmt = $pdo->prepare("SELECT PasswordHash FROM Users WHERE UserId = ?");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            if (!$row || !password_verify($currentPass, $row['PasswordHash'])) {
                echo json_encode(['error' => 'Current password is incorrect.']);
                exit();
            }
            $pdo->prepare("UPDATE Users SET PasswordHash = ? WHERE UserId = ?")
                ->execute([password_hash($newPass, PASSWORD_DEFAULT), $userId]);
            echo json_encode(['message' => 'Password changed successfully.']);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Failed to change password.']);
        }
        exit();
    }

    // 3. Add Address
    if ($action === 'add_address') {
        $recipientName = trim($_POST['recipient_name'] ?? '');
        $phone         = trim($_POST['phone']          ?? '');
        $addressLine1  = trim($_POST['address_line1']  ?? '');
        $addressLine2  = trim($_POST['address_line2']  ?? '');
        $state         = trim($_POST['states']         ?? '');
        $city          = trim($_POST['cities']         ?? '');
        $postcode      = trim($_POST['postcodes']      ?? '');
        $isDefault     = isset($_POST['is_default']) ? 1 : 0;

        if (!$recipientName || !$phone || !$addressLine1 || !$city || !$state || !$postcode) {
            echo json_encode(['error' => 'All address fields are required.']);
            exit();
        }
        try {
            $pdo->beginTransaction();
            if ($isDefault) {
                $pdo->prepare("UPDATE Addresses SET IsDefault = 0 WHERE UserId = ?")->execute([$userId]);
            }
            $addressId = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
            $pdo->prepare("INSERT INTO Addresses (AddressId, UserId, RecipientName, PhoneNumber, AddressLine1, AddressLine2, States, City, Postcode, IsDefault) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$addressId, $userId, $recipientName, $phone, $addressLine1, $addressLine2, $state, $city, $postcode, $isDefault]);
            $pdo->commit();
            echo json_encode(['message' => 'Address added successfully.']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['error' => 'Failed to add address.']);
        }
        exit();
    }

    // 4. Set Default Address
    if ($action === 'set_default_address') {
        $addressId = trim($_POST['address_id'] ?? '');

        if ($addressId === '') {
            echo json_encode(['error' => 'Address id is required.']);
            exit();
        }

        try {
            $checkStmt = $pdo->prepare("SELECT AddressId FROM Addresses WHERE AddressId = ? AND UserId = ? LIMIT 1");
            $checkStmt->execute([$addressId, $userId]);
            if (!$checkStmt->fetch()) {
                echo json_encode(['error' => 'Address not found.']);
                exit();
            }

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE Addresses SET IsDefault = 0 WHERE UserId = ?")->execute([$userId]);
            $pdo->prepare("UPDATE Addresses SET IsDefault = 1 WHERE AddressId = ? AND UserId = ?")->execute([$addressId, $userId]);
            $pdo->commit();

            echo json_encode(['message' => 'Default address updated.']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['error' => 'Failed to update default address.']);
        }
        exit();
    }

    // 5. Delete Address
    if ($action === 'delete_address') {
        $addressId = trim($_POST['address_id'] ?? '');

        if ($addressId === '') {
            echo json_encode(['error' => 'Address id is required.']);
            exit();
        }

        try {
            $checkStmt = $pdo->prepare("SELECT AddressId FROM Addresses WHERE AddressId = ? AND UserId = ? LIMIT 1");
            $checkStmt->execute([$addressId, $userId]);
            if (!$checkStmt->fetch()) {
                echo json_encode(['error' => 'Address not found.']);
                exit();
            }

            $pdo->prepare("DELETE FROM Addresses WHERE AddressId = ? AND UserId = ?")->execute([$addressId, $userId]);
            echo json_encode(['message' => 'Address deleted.']);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Failed to delete address.']);
        }
        exit();
    }

    // 6. Update Address
    if ($action === 'update_address') {
        $addressId     = trim($_POST['address_id']      ?? '');
        $recipientName = trim($_POST['recipient_name']  ?? '');
        $phone         = trim($_POST['phone']           ?? '');
        $addressLine1  = trim($_POST['address_line1']   ?? '');
        $addressLine2  = trim($_POST['address_line2']   ?? '');
        $state         = trim($_POST['states']          ?? '');
        $city          = trim($_POST['cities']          ?? '');
        $postcode      = trim($_POST['postcodes']       ?? '');
        $isDefault     = isset($_POST['is_default']) ? 1 : 0;

        if (!$addressId || !$recipientName || !$phone || !$addressLine1 || !$city || !$state || !$postcode) {
            echo json_encode(['error' => 'All address fields are required.']);
            exit();
        }

        try {
            $checkStmt = $pdo->prepare("SELECT AddressId FROM Addresses WHERE AddressId = ? AND UserId = ? LIMIT 1");
            $checkStmt->execute([$addressId, $userId]);
            if (!$checkStmt->fetch()) {
                echo json_encode(['error' => 'Address not found.']);
                exit();
            }

            $pdo->beginTransaction();
            if ($isDefault) {
                $pdo->prepare("UPDATE Addresses SET IsDefault = 0 WHERE UserId = ?")->execute([$userId]);
            }

            $pdo->prepare("UPDATE Addresses SET RecipientName = ?, PhoneNumber = ?, AddressLine1 = ?, AddressLine2 = ?, States = ?, City = ?, Postcode = ?, IsDefault = ? WHERE AddressId = ? AND UserId = ?")
                ->execute([$recipientName, $phone, $addressLine1, $addressLine2, $state, $city, $postcode, $isDefault, $addressId, $userId]);

            $pdo->commit();
            echo json_encode(['message' => 'Address updated successfully.']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['error' => 'Failed to update address.']);
        }
        exit();
    }

    echo json_encode(['error' => 'Unknown action.']);
    exit();
}

// ─── Page load: fetch profile & addresses ─────────────────────────────────────
$stmt = $pdo->prepare(
    "SELECT u.Email, up.FirstName, up.LastName, up.PhoneNumber, up.ProfilePhotoUrl
     FROM Users u
     JOIN UserProfile up ON u.UserId = up.UserId
     WHERE u.UserId = ? LIMIT 1"
);
$stmt->execute([$userId]);
$profile = $stmt->fetch();

$addrStmt = $pdo->prepare("SELECT * FROM Addresses WHERE UserId = ? ORDER BY IsDefault DESC");
$addrStmt->execute([$userId]);
$addresses = $addrStmt->fetchAll();

$avatarSrc = htmlspecialchars($profile['ProfilePhotoUrl'] ?? 'asset/image/default_avatar.png');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-commerce || My Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .avatar-circle { width: 80px; height: 80px; object-fit: cover; }
        .avatar-preview { width: 100px; height: 100px; object-fit: cover; }
        .toast-shell { position: fixed; top: 70px; right: 20px; z-index: 9999; min-width: 280px; }
        .list-group-item.active { background-color: #0d6efd; border-color: #0d6efd; }
    </style>
</head>
<body>
<?php include 'layout/nav.php'; ?>

<div class="container py-5">
    <div class="row g-4">

        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card text-center p-3 mb-3">
                <img src="<?php echo $avatarSrc; ?>" id="sidebarAvatar"
                     class="rounded-circle mx-auto d-block mb-2 avatar-circle" alt="Avatar">
                <h6 class="mb-0"><?php echo htmlspecialchars(($profile['FirstName'] ?? '') . ' ' . ($profile['LastName'] ?? '')); ?></h6>
                <small class="text-muted"><?php echo htmlspecialchars($profile['Email'] ?? ''); ?></small>
            </div>
            <div class="list-group" id="profileTabs">
                <a href="#tab-profile"  class="list-group-item list-group-item-action active" data-bs-toggle="list">
                    <i class="fas fa-user me-2"></i>Edit Profile
                </a>
                <a href="#tab-password" class="list-group-item list-group-item-action" data-bs-toggle="list">
                    <i class="fas fa-lock me-2"></i>Change Password
                </a>
                <a href="#tab-address"  class="list-group-item list-group-item-action" data-bs-toggle="list">
                    <i class="fas fa-map-marker-alt me-2"></i>Addresses
                </a>
            </div>
        </div>

        <!-- Tab content -->
        <div class="col-md-9">
            <div class="tab-content">

                <!-- Tab 1: Edit Profile -->
                <div class="tab-pane fade show active" id="tab-profile">
                    <div class="card p-4">
                        <h5 class="mb-4">Edit Profile</h5>
                        <div class="text-center mb-3">
                            <img src="<?php echo $avatarSrc; ?>" id="avatarPreview"
                                 class="rounded-circle avatar-preview" alt="Avatar Preview">
                            <div class="mt-2 text-muted small">Click "Choose File" below to change your avatar</div>
                        </div>
                        <form id="formProfile" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="mb-3">
                                <label class="form-label">Avatar</label>
                                <input type="file" class="form-control" name="avatar" id="avatarInput" accept="image/*">
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" class="form-control" name="first_name"
                                           value="<?php echo htmlspecialchars($profile['FirstName'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" class="form-control" name="last_name"
                                           value="<?php echo htmlspecialchars($profile['LastName'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="form-control" name="phone"
                                       value="<?php echo htmlspecialchars($profile['PhoneNumber'] ?? ''); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Save Changes
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Tab 2: Change Password -->
                <div class="tab-pane fade" id="tab-password">
                    <div class="card p-4">
                        <h5 class="mb-4">Change Password</h5>
                        <form id="formPassword" style="max-width: 480px;">
                            <input type="hidden" name="action" value="change_password">
                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password <small class="text-muted">(min 8 characters)</small></label>
                                <input type="password" class="form-control" name="new_password" required minlength="8">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" required minlength="8">
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key me-1"></i>Change Password
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Tab 3: Addresses -->
                <div class="tab-pane fade" id="tab-address">
                    <div class="card p-4">
                        <h5 class="mb-4">My Addresses</h5>

                        <!-- Saved addresses -->
                        <div id="addressList">
                            <?php if ($addresses): ?>
                                <?php foreach ($addresses as $addr): ?>
                                <?php $displayAddress = implode(', ', array_filter([
                                    $addr['AddressLine1'] ?? '',
                                    $addr['AddressLine2'] ?? '',
                                    $addr['Postcode'] ?? '',
                                    $addr['City'] ?? '',
                                    $addr['States'] ?? ''
                                ])); ?>
                                <div class="border rounded p-3 mb-2">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div class="fw-semibold">
                                            <?php echo htmlspecialchars($addr['RecipientName']); ?>
                                            <?php if ($addr['IsDefault']): ?>
                                                <span class="badge bg-success ms-2">Default</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex gap-2 flex-shrink-0">
                                            <button type="button"
                                                    class="btn btn-outline-secondary btn-sm editAddressBtn"
                                                    data-address-id="<?php echo htmlspecialchars($addr['AddressId'], ENT_QUOTES); ?>"
                                                    data-recipient-name="<?php echo htmlspecialchars($addr['RecipientName'], ENT_QUOTES); ?>"
                                                    data-phone="<?php echo htmlspecialchars($addr['PhoneNumber'], ENT_QUOTES); ?>"
                                                    data-address-line1="<?php echo htmlspecialchars($addr['AddressLine1'], ENT_QUOTES); ?>"
                                                    data-address-line2="<?php echo htmlspecialchars($addr['AddressLine2'], ENT_QUOTES); ?>"
                                                    data-states="<?php echo htmlspecialchars($addr['States'], ENT_QUOTES); ?>"
                                                    data-city="<?php echo htmlspecialchars($addr['City'], ENT_QUOTES); ?>"
                                                    data-postcode="<?php echo htmlspecialchars($addr['Postcode'], ENT_QUOTES); ?>"
                                                    data-is-default="<?php echo (int)$addr['IsDefault']; ?>">
                                                Edit
                                            </button>
                                            <?php if (!$addr['IsDefault']): ?>
                                                <form class="setDefaultForm m-0">
                                                    <input type="hidden" name="action" value="set_default_address">
                                                    <input type="hidden" name="address_id" value="<?php echo htmlspecialchars($addr['AddressId']); ?>">
                                                    <button type="submit" class="btn btn-outline-primary btn-sm">Set Default</button>
                                                </form>
                                            <?php endif; ?>
                                            <form class="deleteAddressForm m-0">
                                                <input type="hidden" name="action" value="delete_address">
                                                <input type="hidden" name="address_id" value="<?php echo htmlspecialchars($addr['AddressId']); ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($addr['PhoneNumber']); ?></div>
                                    <div><?php echo nl2br(htmlspecialchars($displayAddress)); ?></div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted" id="noAddressMsg">No addresses saved yet.</p>
                            <?php endif; ?>
                        </div>

                        <hr>
                        <h6 class="mb-3">Add New Address</h6>
                        <form id="formAddress" style="max-width: 540px;">
                            <input type="hidden" name="action" value="add_address">
                            <div class="mb-3">
                                <label class="form-label">Recipient Name</label>
                                <input type="text" class="form-control" name="recipient_name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="form-control" name="phone" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address Line 1</label>
                                <input type="text" class="form-control" name="address_line1" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address Line 2</label>
                                <input type="text" class="form-control" name="address_line2">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">State</label>
                                <select class="form-control" name="states" id="states" disabled>
                                    <option value="">Loading states...</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">City</label>
                                <select class="form-control" name="cities" id="cities" disabled>
                                    <option value="">Select City</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Postcode</label>
                                <select class="form-control" name="postcodes" id="postcode" disabled>
                                    <option value="">Select Postcode</option>
                                </select>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" name="is_default" id="isDefault">
                                <label class="form-check-label" for="isDefault">Set as default address</label>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>Add Address
                            </button>
                        </form>
                    </div>
                </div>

            </div><!-- /.tab-content -->
        </div>
    </div>
</div>

<div class="modal fade" id="editAddressModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Address</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formEditAddress">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_address">
                    <input type="hidden" name="address_id" id="editAddressId">

                    <div class="mb-3">
                        <label class="form-label">Recipient Name</label>
                        <input type="text" class="form-control" name="recipient_name" id="editRecipientName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" class="form-control" name="phone" id="editPhone" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address Line 1</label>
                        <input type="text" class="form-control" name="address_line1" id="editAddressLine1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address Line 2</label>
                        <input type="text" class="form-control" name="address_line2" id="editAddressLine2">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">State</label>
                        <select class="form-control" name="states" id="editStates" disabled>
                            <option value="">Loading states...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">City</label>
                        <select class="form-control" name="cities" id="editCities" disabled>
                            <option value="">Select state first</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Postcode</label>
                        <select class="form-control" name="postcodes" id="editPostcode" disabled>
                            <option value="">Select city first</option>
                        </select>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_default" id="editIsDefault">
                        <label class="form-check-label" for="editIsDefault">Set as default address</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Address</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast container -->
<div class="toast-shell" id="toastShell"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let postcodeDataset = [];

    function setSelectOptions(selectEl, placeholder, values) {
        selectEl.innerHTML = '';
        const placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = placeholder;
        selectEl.appendChild(placeholderOption);

        values.forEach((value) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            selectEl.appendChild(option);
        });
    }

    function findStateByName(stateName) {
        return postcodeDataset.find((state) => state.name === stateName);
    }

    function findCityByName(stateObj, cityName) {
        if (!stateObj) return null;
        return (stateObj.city || []).find((city) => city.name === cityName) || null;
    }

    function setStateCityPostcode(stateEl, cityEl, postcodeEl, stateValue, cityValue, postcodeValue) {
        const stateObj = findStateByName(stateValue);
        const cities = stateObj ? (stateObj.city || []).map((item) => item.name) : [];

        setSelectOptions(cityEl, cities.length ? 'Select City' : 'No city data available', cities);
        cityEl.disabled = cities.length === 0;
        cityEl.value = cities.includes(cityValue) ? cityValue : '';

        const cityObj = findCityByName(stateObj, cityEl.value);
        const postcodes = cityObj ? (cityObj.postcode || []) : [];

        setSelectOptions(postcodeEl, postcodes.length ? 'Select Postcode' : 'No postcode data available', postcodes);
        postcodeEl.disabled = postcodes.length === 0;
        postcodeEl.value = postcodes.includes(postcodeValue) ? postcodeValue : '';
    }

    async function loadMalaysiaPostcodes() {
        const stateEl = document.getElementById('states');
        const cityEl = document.getElementById('cities');
        const postcodeEl = document.getElementById('postcode');
        const editStateEl = document.getElementById('editStates');
        const editCityEl = document.getElementById('editCities');
        const editPostcodeEl = document.getElementById('editPostcode');

        stateEl.disabled = true;
        cityEl.disabled = true;
        postcodeEl.disabled = true;
        editStateEl.disabled = true;
        editCityEl.disabled = true;
        editPostcodeEl.disabled = true;

        try {
            const response = await fetch('malaysia_postcodes/all.json', { cache: 'no-store' });
            if (!response.ok) {
                throw new Error('Failed to load postcode data.');
            }

            const data = await response.json();
            postcodeDataset = Array.isArray(data.state) ? data.state : [];

            if (!postcodeDataset.length) {
                setSelectOptions(stateEl, 'No state data available', []);
                setSelectOptions(cityEl, 'No city data available', []);
                setSelectOptions(postcodeEl, 'No postcode data available', []);
                setSelectOptions(editStateEl, 'No state data available', []);
                setSelectOptions(editCityEl, 'No city data available', []);
                setSelectOptions(editPostcodeEl, 'No postcode data available', []);
                return;
            }

            const stateNames = postcodeDataset.map((item) => item.name);
            setSelectOptions(stateEl, 'Select State', stateNames);
            setSelectOptions(cityEl, 'Select City', []);
            setSelectOptions(postcodeEl, 'Select Postcode', []);
            setSelectOptions(editStateEl, 'Select State', stateNames);
            setSelectOptions(editCityEl, 'Select City', []);
            setSelectOptions(editPostcodeEl, 'Select Postcode', []);

            stateEl.disabled = false;
            cityEl.disabled = true;
            postcodeEl.disabled = true;
            editStateEl.disabled = false;
            editCityEl.disabled = true;
            editPostcodeEl.disabled = true;
        } catch (error) {
            setSelectOptions(stateEl, 'Failed to load states', []);
            setSelectOptions(cityEl, 'No city data available', []);
            setSelectOptions(postcodeEl, 'No postcode data available', []);
            setSelectOptions(editStateEl, 'Failed to load states', []);
            setSelectOptions(editCityEl, 'No city data available', []);
            setSelectOptions(editPostcodeEl, 'No postcode data available', []);

            stateEl.disabled = true;
            cityEl.disabled = true;
            postcodeEl.disabled = true;
            editStateEl.disabled = true;
            editCityEl.disabled = true;
            editPostcodeEl.disabled = true;
            showToast('Unable to load state/city/postcode data.', 'error');
        }
    }

    function attachAddressSelectors() {
        const stateEl = document.getElementById('states');
        const cityEl = document.getElementById('cities');
        const postcodeEl = document.getElementById('postcode');

        stateEl.addEventListener('change', function () {
            const stateObj = findStateByName(this.value);
            const cities = stateObj ? (stateObj.city || []).map((item) => item.name) : [];
            setSelectOptions(cityEl, cities.length ? 'Select City' : 'No city data available', cities);
            setSelectOptions(postcodeEl, 'Select city first', []);
            cityEl.disabled = cities.length === 0;
            postcodeEl.disabled = true;
        });

        cityEl.addEventListener('change', function () {
            const stateObj = findStateByName(stateEl.value);
            const cityObj = findCityByName(stateObj, this.value);
            const postcodes = cityObj ? (cityObj.postcode || []) : [];
            setSelectOptions(postcodeEl, postcodes.length ? 'Select Postcode' : 'No postcode data available', postcodes);
            postcodeEl.disabled = postcodes.length === 0;
        });

        const editStateEl = document.getElementById('editStates');
        const editCityEl = document.getElementById('editCities');
        const editPostcodeEl = document.getElementById('editPostcode');

        editStateEl.addEventListener('change', function () {
            setStateCityPostcode(editStateEl, editCityEl, editPostcodeEl, this.value, '', '');
        });

        editCityEl.addEventListener('change', function () {
            const stateObj = findStateByName(editStateEl.value);
            const cityObj = findCityByName(stateObj, this.value);
            const postcodes = cityObj ? (cityObj.postcode || []) : [];
            setSelectOptions(editPostcodeEl, postcodes.length ? 'Select Postcode' : 'No postcode data available', postcodes);
            editPostcodeEl.disabled = postcodes.length === 0;
        });
    }

    function showToast(msg, type) {
        const el = document.createElement('div');
        el.className = 'alert alert-' + (type === 'error' ? 'danger' : 'success') +
                       ' alert-dismissible fade show shadow';
        el.innerHTML = msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        document.getElementById('toastShell').appendChild(el);
        setTimeout(() => el.remove(), 4000);
    }

    function ajaxForm(formEl) {
        return fetch('userProfile.php', { method: 'POST', body: new FormData(formEl) })
            .then(r => r.json());
    }

    // Avatar live preview
    document.getElementById('avatarInput').addEventListener('change', function () {
        if (this.files[0]) {
            document.getElementById('avatarPreview').src = URL.createObjectURL(this.files[0]);
        }
    });

    // Update Profile
    document.getElementById('formProfile').addEventListener('submit', function (e) {
        e.preventDefault();
        ajaxForm(this).then(res => {
            showToast(res.message || res.error, res.error ? 'error' : 'success');
            if (res.message && res.photo) {
                document.getElementById('sidebarAvatar').src = res.photo;
            }
        });
    });

    // Change Password
    document.getElementById('formPassword').addEventListener('submit', function (e) {
        e.preventDefault();
        ajaxForm(this).then(res => {
            showToast(res.message || res.error, res.error ? 'error' : 'success');
            if (res.message) this.reset();
        });
    });

    // Add Address
    document.getElementById('formAddress').addEventListener('submit', function (e) {
        e.preventDefault();
        ajaxForm(this).then(res => {
            showToast(res.message || res.error, res.error ? 'error' : 'success');
            if (res.message) {
                // Reload after short delay so the new address appears in the list
                setTimeout(() => location.reload(), 1200);
            }
        });
    });

    // Set Default Address
    document.querySelectorAll('.setDefaultForm').forEach((formEl) => {
        formEl.addEventListener('submit', function (e) {
            e.preventDefault();
            ajaxForm(this).then((res) => {
                showToast(res.message || res.error, res.error ? 'error' : 'success');
                if (res.message) {
                    setTimeout(() => location.reload(), 800);
                }
            });
        });
    });

    // Delete Address
    document.querySelectorAll('.deleteAddressForm').forEach((formEl) => {
        formEl.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to delete this address?')) return;
            const card = this.closest('.border.rounded');
            ajaxForm(this).then((res) => {
                showToast(res.message || res.error, res.error ? 'error' : 'success');
                if (res.message && card) {
                    card.remove();
                }
            });
        });
    });

    const editAddressModalEl = document.getElementById('editAddressModal');
    const editAddressModal = new bootstrap.Modal(editAddressModalEl);

    document.querySelectorAll('.editAddressBtn').forEach((btn) => {
        btn.addEventListener('click', function () {
            document.getElementById('editAddressId').value = this.dataset.addressId || '';
            document.getElementById('editRecipientName').value = this.dataset.recipientName || '';
            document.getElementById('editPhone').value = this.dataset.phone || '';
            document.getElementById('editAddressLine1').value = this.dataset.addressLine1 || '';
            document.getElementById('editAddressLine2').value = this.dataset.addressLine2 || '';
            document.getElementById('editIsDefault').checked = this.dataset.isDefault === '1';

            const editStateEl = document.getElementById('editStates');
            const editCityEl = document.getElementById('editCities');
            const editPostcodeEl = document.getElementById('editPostcode');
            const targetState = this.dataset.states || '';
            const targetCity = this.dataset.city || '';
            const targetPostcode = this.dataset.postcode || '';

            if (!editStateEl.disabled) {
                editStateEl.value = targetState;
                setStateCityPostcode(editStateEl, editCityEl, editPostcodeEl, targetState, targetCity, targetPostcode);
            }

            editAddressModal.show();
        });
    });

    document.getElementById('formEditAddress').addEventListener('submit', function (e) {
        e.preventDefault();
        ajaxForm(this).then((res) => {
            showToast(res.message || res.error, res.error ? 'error' : 'success');
            if (res.message) {
                editAddressModal.hide();
                setTimeout(() => location.reload(), 800);
            }
        });
    });

    loadMalaysiaPostcodes();
    attachAddressSelectors();
</script>
</body>
</html>

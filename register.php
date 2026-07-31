<?php
// Start session for any potential session needs
session_start();

// Database connection will be included from db.php
require_once 'db.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Your Account - Fitness Buddy</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4CAF50;
            --secondary-color: #2E7D32;
            --accent-color: #81C784;
            --light-bg: #f8f9fa;
            --dark-bg: #343a40;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
            color: #333;
        }

        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }

        .form-container {
            max-width: 900px;
            margin: 2rem auto;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            background-color: white;
        }

        .form-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }

        .form-body {
            padding: 2rem;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section-title {
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--dark-bg);
            border-bottom: 2px solid var(--accent-color);
            padding-bottom: 0.5rem;
        }

        .plan-card {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 1.5rem;
            height: 100%;
            transition: all 0.3s ease;
        }

        .plan-card.selected {
            border-color: var(--primary-color);
            box-shadow: 0 0 10px rgba(76, 175, 80, 0.5);
        }

        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .plan-title {
            color: var(--primary-color);
            font-weight: bold;
            margin-bottom: 1rem;
        }

        .features-list {
            padding-left: 0;
            list-style-type: none;
        }

        .features-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .features-list li:before {
            content: "\f058";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: var(--primary-color);
            margin-right: 0.5rem;
        }

        .price-tag {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 1rem 0;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .password-container {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
        }

        .password-requirements {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }

        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 0.25rem;
        }

        .requirement i {
            margin-right: 0.5rem;
            font-size: 0.8rem;
        }

        .invalid {
            color: #dc3545;
        }

        .valid {
            color: var(--primary-color);
        }

        .form-footer {
            background-color: var(--light-bg);
            padding: 1.5rem;
            text-align: center;
            border-top: 1px solid #dee2e6;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #fa8072;">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-dumbbell me-2"></i>Fitness Buddy
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="form-container">
            <div class="form-header">
                <h2><i class="fas fa-user-plus me-2"></i>Create Your Account</h2>
                <p class="mb-0">Join our fitness community and find your perfect workout partner</p>
            </div>

            <form id="registerForm" novalidate>
                <div class="form-body">
                    <div class="form-section">
                        <h4 class="form-section-title">Account Information</h4>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" required>
                            <div class="invalid-feedback">
                                Please choose a username.
                            </div>
                            <div class="form-text">
                                This will be visible to other users.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" required>
                            <div class="invalid-feedback">
                                Please enter a valid email address.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="password-container">
                                <input type="password" class="form-control" id="password" required>
                                <span class="password-toggle" id="togglePassword">
                                    <i class="far fa-eye"></i>
                                </span>
                                <div class="invalid-feedback">
                                    Please enter a valid password.
                                </div>
                            </div>
                            <div class="password-requirements mt-2">
                                <div class="requirement" id="length"><i class="fas fa-circle"></i> At least 8 characters
                                </div>
                                <div class="requirement" id="letter"><i class="fas fa-circle"></i> At least one letter
                                </div>
                                <div class="requirement" id="number"><i class="fas fa-circle"></i> At least one number
                                </div>
                                <div class="requirement" id="special"><i class="fas fa-circle"></i> At least one special
                                    character</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">Confirm Password</label>
                            <div class="password-container">
                                <input type="password" class="form-control" id="confirmPassword" required>
                                <span class="password-toggle" id="toggleConfirmPassword">
                                    <i class="far fa-eye"></i>
                                </span>
                                <div class="invalid-feedback">
                                    Passwords don't match.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h4 class="form-section-title">Choose Your Membership Plan</h4>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="plan-card" id="freePlanCard">
                                    <h4 class="plan-title">Free Tier</h4>
                                    <div class="price-tag">$0 / month</div>
                                    <ul class="features-list">
                                        <li>Basic profile creation</li>
                                        <li>Limited matching (5 per day)</li>
                                        <li>Basic consistency tracker</li>
                                        <li>Forum access (view only)</li>
                                    </ul>
                                    <div class="form-check mt-3">
                                        <input class="form-check-input" type="radio" name="membershipTier" id="freeTier"
                                            value="free" checked>
                                        <label class="form-check-label" for="freeTier">
                                            Select Free Tier
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <div class="plan-card" id="premiumPlanCard">
                                    <h4 class="plan-title">Premium Tier</h4>
                                    <div class="price-tag">$9.99 / month</div>
                                    <ul class="features-list">
                                        <li>Advanced profile creation</li>
                                        <li>Unlimited matching</li>
                                        <li>Advanced fitness tracking</li>
                                        <li>Workout planning tools</li>
                                        <li>Ad-free experience</li>
                                        <li>Full forum participation</li>
                                    </ul>
                                    <div class="form-check mt-3">
                                        <input class="form-check-input" type="radio" name="membershipTier"
                                            id="premiumTier" value="premium">
                                        <label class="form-check-label" for="premiumTier">
                                            Select Premium Tier
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-footer">
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="termsCheck" required>
                        <label class="form-check-label" for="termsCheck">
                            I agree to the <a href="terms.php">Terms of Service</a> and <a href="privacy.php">Privacy
                                Policy</a>
                        </label>
                        <div class="invalid-feedback">
                            You must agree to the terms before registering.
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg px-4">
                        <i class="fas fa-user-plus me-2"></i>Create Account
                    </button>
                    <div class="mt-3">
                        Already have an account? <a href="login.php">Sign In</a>
                    </div>
                    <div id="registerMessage" class="mt-3"></div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function () {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        document.getElementById('toggleConfirmPassword').addEventListener('click', function () {
            const passwordInput = document.getElementById('confirmPassword');
            const icon = this.querySelector('i');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Plan selection
        document.getElementById('freeTier').addEventListener('change', function () {
            if (this.checked) {
                document.getElementById('freePlanCard').classList.add('selected');
                document.getElementById('premiumPlanCard').classList.remove('selected');
            }
        });

        document.getElementById('premiumTier').addEventListener('change', function () {
            if (this.checked) {
                document.getElementById('premiumPlanCard').classList.add('selected');
                document.getElementById('freePlanCard').classList.remove('selected');
            }
        });

        // Initialize plan cards
        if (document.getElementById('freeTier').checked) {
            document.getElementById('freePlanCard').classList.add('selected');
        } else if (document.getElementById('premiumTier').checked) {
            document.getElementById('premiumPlanCard').classList.add('selected');
        }

        // Password validation
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirmPassword');
        const lengthReq = document.getElementById('length');
        const letterReq = document.getElementById('letter');
        const numberReq = document.getElementById('number');
        const specialReq = document.getElementById('special');

        function validatePassword() {
            const val = password.value;

            // Check length
            if (val.length >= 8) {
                lengthReq.classList.remove('invalid');
                lengthReq.classList.add('valid');
                lengthReq.querySelector('i').classList.remove('fa-circle');
                lengthReq.querySelector('i').classList.add('fa-check-circle');
            } else {
                lengthReq.classList.remove('valid');
                lengthReq.classList.add('invalid');
                lengthReq.querySelector('i').classList.remove('fa-check-circle');
                lengthReq.querySelector('i').classList.add('fa-circle');
            }

            // Check for letter
            if (/[A-Za-z]/.test(val)) {
                letterReq.classList.remove('invalid');
                letterReq.classList.add('valid');
                letterReq.querySelector('i').classList.remove('fa-circle');
                letterReq.querySelector('i').classList.add('fa-check-circle');
            } else {
                letterReq.classList.remove('valid');
                letterReq.classList.add('invalid');
                letterReq.querySelector('i').classList.remove('fa-check-circle');
                letterReq.querySelector('i').classList.add('fa-circle');
            }

            // Check for number
            if (/\d/.test(val)) {
                numberReq.classList.remove('invalid');
                numberReq.classList.add('valid');
                numberReq.querySelector('i').classList.remove('fa-circle');
                numberReq.querySelector('i').classList.add('fa-check-circle');
            } else {
                numberReq.classList.remove('valid');
                numberReq.classList.add('invalid');
                numberReq.querySelector('i').classList.remove('fa-check-circle');
                numberReq.querySelector('i').classList.add('fa-circle');
            }

            // Check for special character
            if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(val)) {
                specialReq.classList.remove('invalid');
                specialReq.classList.add('valid');
                specialReq.querySelector('i').classList.remove('fa-circle');
                specialReq.querySelector('i').classList.add('fa-check-circle');
            } else {
                specialReq.classList.remove('valid');
                specialReq.classList.add('invalid');
                specialReq.querySelector('i').classList.remove('fa-check-circle');
                specialReq.querySelector('i').classList.add('fa-circle');
            }
        }

        password.addEventListener('keyup', validatePassword);
        password.addEventListener('blur', validatePassword);

        // Form validation and submission
        const form = document.getElementById('registerForm');

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            // Check form validity
            if (!form.checkValidity()) {
                event.stopPropagation();
                form.classList.add('was-validated');
                return;
            }

            // Check if passwords match
            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
                form.classList.add('was-validated');
                return;
            } else {
                confirmPassword.setCustomValidity('');
            }

            // Check if terms are accepted
            if (!document.getElementById('termsCheck').checked) {
                document.getElementById('termsCheck').setCustomValidity('You must accept the terms');
                form.classList.add('was-validated');
                return;
            } else {
                document.getElementById('termsCheck').setCustomValidity('');
            }

            // Check password requirements
            const isPasswordValid =
                password.value.length >= 8 &&
                /[A-Za-z]/.test(password.value) &&
                /\d/.test(password.value) &&
                /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password.value);

            if (!isPasswordValid) {
                document.getElementById('registerMessage').innerHTML =
                    '<div class="alert alert-danger">Password does not meet all requirements.</div>';
                return;
            }

            // Create data object
            const formData = {
                username: document.getElementById('username').value,
                email: document.getElementById('email').value,
                password: password.value,
                membershipTier: document.querySelector('input[name="membershipTier"]:checked').value
            };

            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating account...';
            submitBtn.disabled = true;

            // Send registration request
            fetch('api_register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(formData)
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    const messageBox = document.getElementById('registerMessage');

                    if (data.status === 'success') {
                        messageBox.innerHTML = `<div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>${data.message}
                    </div>`;

                        // Reset form
                        form.reset();
                        form.classList.remove('was-validated');

                        // Redirect after delay
                        setTimeout(() => {
                            window.location.href = data.redirect || 'profileSetup.php';
                        }, 2000);
                    } else {
                        messageBox.innerHTML = `<div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>${data.message}
                    </div>`;

                        // Reset button
                        submitBtn.innerHTML = originalBtnText;
                        submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);

                    document.getElementById('registerMessage').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        An error occurred. Please try again later or contact support.
                    </div>`;

                    // Reset button
                    submitBtn.innerHTML = originalBtnText;
                    submitBtn.disabled = false;
                });
        });
    </script>
</body>

</html>
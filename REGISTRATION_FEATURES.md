# Registration Features Documentation

## Overview
Your MediStock RHU application now includes a complete registration system similar to Facebook's sign-up process, with multiple authentication options.

## Available Registration Methods

### 1. Traditional Email Registration
- Users can create accounts with email and password
- Includes fields for name, email, password, phone (optional), and job title (optional)
- OTP verification required for email confirmation
- New users are assigned default "staff" role

### 2. Google OAuth Registration
- Users can sign up with their Google/Gmail accounts
- Automatic account creation with Google profile data
- OTP verification for security
- Seamless integration with existing Google login

### 3. Forgot Password
- Existing password reset functionality
- Email-based password reset links
- Secure token-based reset process

## Registration Flow

### Traditional Registration:
1. User fills out registration form
2. System generates 6-digit OTP
3. OTP is logged to Laravel logs (for demo)
4. User enters OTP on verification page
5. Account created and user logged in
6. Redirected to dashboard

### Google Registration:
1. User clicks "Sign up with Google"
2. Google OAuth authentication
3. System generates OTP
4. User enters OTP verification
5. Account created with Google data
6. User logged in and redirected

## Routes Added

- `GET /register` - Registration form
- `POST /register` - Submit registration
- `GET /register/otp` - OTP verification form
- `POST /register/verify-otp` - Verify OTP and create account
- `POST /register/resend-otp` - Resend OTP code

## Security Features

- Rate limiting on registration attempts (3 per minute)
- OTP expiration (10 minutes)
- Session-based registration data storage
- Email validation and uniqueness checks
- Password confirmation requirement
- CSRF protection on all forms

## Testing the Registration

### Traditional Registration:
1. Visit `http://localhost:8000/register`
2. Fill in the registration form
3. Submit the form
4. Check `storage/logs/laravel.log` for the OTP code
5. Enter the OTP on the verification page
6. Account will be created and you'll be logged in

### Google Registration:
1. Visit `http://localhost:8000/register`
2. Click "Sign up with Google"
3. Complete Google authentication
4. Check logs for OTP code
5. Enter OTP to complete registration

## Configuration

### For Production Email OTP:
Update your `.env` file with mail settings:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_app_password
MAIL_ENCRYPTION=tls
```

### For Google OAuth:
See `GOOGLE_OAUTH_SETUP.md` for detailed setup instructions.

## User Roles

New registrations are automatically assigned:
- Default role: "staff" (if exists)
- Fallback: First available role
- Status: "active"
- Email verified: "yes" (after OTP verification)

## Customization

You can customize:
- Default role assignment in `RegisterController.php`
- OTP expiration time in `OtpService.php`
- Registration form fields in `register.blade.php`
- Email templates for OTP delivery

## Database Notes

No database migrations were required. The system uses existing user table fields:
- `name`, `email`, `password`, `phone`, `job_title`
- `role_id`, `status`, `email_verified_at`
- `preferences` (stores Google auth data)

## Support

For issues or questions:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Verify mail settings in `.env`
3. Ensure Google OAuth credentials are configured
4. Check rate limiting settings in routes
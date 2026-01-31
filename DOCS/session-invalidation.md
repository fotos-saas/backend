# Work Session Invalidation System

## Overview
Automatic logout system for digit code guest users when their work session becomes invalid.

## Problem
Guest users logged in with 6-digit codes remained authenticated even when:
- Work session digit code was disabled
- Work session expired
- Work session status changed (archived/completed)
- Work session was deleted
- User was manually detached from session

## Solution Architecture

### Backend Components

#### 1. Database Schema Enhancement
**Migration:** `2025_10_29_083829_add_work_session_id_to_personal_access_tokens_table`
- Added `work_session_id` column to `personal_access_tokens` table
- Nullable foreign key with `nullOnDelete()` behavior
- Indexed for performance

#### 2. Token Creation Enhancement
**File:** `app/Http/Controllers/Api/AuthController.php:90-94`
```php
$tokenResult = $guestUser->createToken('auth-token');
$tokenResult->accessToken->work_session_id = $workSession->id;
$tokenResult->accessToken->save();
```
- Stores work session ID when creating tokens for digit code logins

#### 3. Middleware Implementation
**File:** `app/Http/Middleware/CheckWorkSessionStatus.php`
- Validates work session on every authenticated API request
- Checks:
  - `digit_code_enabled` is true
  - Not expired (`digit_code_expires_at` is future)
  - Status is not 'archived' or 'completed'
  - Not soft-deleted
- Returns 401 with `work_session_invalid` error if invalid
- Automatically revokes token

**Registration:** `bootstrap/app.php:22-25`
```php
$middleware->api(append: [
    \App\Http\Middleware\CheckWorkSessionStatus::class,
]);
```

#### 4. Observer Enhancement
**File:** `app/Observers/WorkSessionObserver.php:59-80`
- `updated()` event: Revokes tokens when `digit_code_enabled` changes to false
- `deleting()` event: Revokes tokens when work session is deleted

#### 5. Model Method
**File:** `app/Models/WorkSession.php:208-216`
```php
public function revokeUserTokens(): void
{
    \Laravel\Sanctum\PersonalAccessToken::where('work_session_id', $this->id)
        ->delete();
}
```

#### 6. Login Validation
**File:** `app/Http/Controllers/Api/AuthController.php:62-94`
**Method:** `loginCode()`

Login validation with detailed error messages:
- **Code not found:** "Ez a munkamenet már megszűnt vagy lejárt"
- **digit_code_enabled = false:** "A belépési kód le van tiltva"
- **digit_code_expires_at < now:** "A belépési kód lejárt"
- **status !== 'active':** "Ez a munkamenet már nem elérhető"

**File:** `app/Models/WorkSession.php:169-178`
**Scope:** `byDigitCode()`
```php
public function scopeByDigitCode($query, string $code)
{
    return $query->where('digit_code', $code)
        ->where('digit_code_enabled', true)
        ->where('status', 'active')  // Only active sessions
        ->where(function ($q) {
            $q->whereNull('digit_code_expires_at')
                ->orWhere('digit_code_expires_at', '>', now());
        });
}
```
- Validates work session status at database level
- Prevents login for inactive/archived/completed sessions

#### 7. Validation Endpoint
**File:** `app/Http/Controllers/Api/AuthController.php:669-680`
**Route:** `GET /api/auth/validate-session`
- Simple endpoint for frontend to check session validity
- Triggers middleware validation

#### 8. Sanctum Configuration
**File:** `config/sanctum.php:51`
```php
'expiration' => 1440, // 24 hours
```
- Changed from `null` (never expire) to 1440 minutes (24 hours)

### Frontend Components

#### 1. AuthService Enhancement
**File:** `frontend/src/app/core/services/auth.service.ts:164-191`
```typescript
public validateWorkSession(): Observable<boolean> {
  // Only validates for guest users with work session
  // Calls backend validation endpoint
  // Returns true if valid, false if invalid
}
```

#### 2. AuthInterceptor Enhancement
**File:** `frontend/src/app/core/interceptors/auth.interceptor.ts:40-59`
- Detects `work_session_invalid` error (401)
- **Stores snackbar data in sessionStorage** (survives page reload)
- Message: "A munkamenet már nem érvényes. Kérjük, jelentkezz be újra."
- Calls `authService.logout()` which does `window.location.reload()`
- sessionStorage key: `pending_snackbar` (JSON format)

#### 3. App Component - Snackbar Persistence & Periodic Validation
**File:** `frontend/src/app/app.component.ts`

**Snackbar Persistence (lines 64-86):**
```typescript
private checkPendingSnackbar(): void {
  // Check sessionStorage for pending snackbar (survives page reload)
  const pendingSnackbar = sessionStorage.getItem('pending_snackbar');
  if (pendingSnackbar) {
    const snackbarData = JSON.parse(pendingSnackbar);
    this.snackbarService.show(snackbarData.message, snackbarData.type, snackbarData.duration);
    sessionStorage.removeItem('pending_snackbar');
  }
}
```

**Periodic Validation (lines 88-104):**
```typescript
private startSessionMonitoring(): void {
  // Validates every 60 seconds
  this.sessionValidationSubscription = interval(60000).pipe(
    switchMap(() => this.authService.validateWorkSession())
  ).subscribe({...});
}

// validateWorkSession() in auth.service.ts:
// - Detects ANY 401 error for guest users
// - Stores snackbar message in sessionStorage
// - Calls logout() which reloads the page
// - AppComponent displays the snackbar after reload
```

## Flow Diagram

```
Guest User Login (6-digit code)
    ↓
Token created with work_session_id
    ↓
Every API Request → CheckWorkSessionStatus Middleware
    ↓
Valid? → Continue
Invalid? → 401 with work_session_invalid error
    ↓
Frontend Interceptor catches error
    ↓
Store snackbar data in sessionStorage
    ↓
Calls AuthService.logout()
    ↓
Clears LocalStorage + redirects to home
    ↓
Force page reload (window.location.reload())
    ↓
Angular app restarts
    ↓
AppComponent checks sessionStorage for pending_snackbar
    ↓
Display sticky error notification
    ↓
Remove from sessionStorage
    ↓
User sees error message, must manually close (X button)

Additionally:
- Frontend periodically validates (every 60 seconds)
- Admin disables digit code → Observer revokes all tokens
- Admin deletes work session → Observer revokes all tokens
- sessionStorage persists snackbar across page reload
```

## Triggers for Automatic Logout

1. **digit_code_enabled = false**
   - Observer immediately revokes all tokens

2. **digit_code_expires_at < now**
   - Middleware rejects on next API call
   - Frontend periodic check detects

3. **status is not 'active' (inactive/archived/completed)**
   - Middleware rejects on next API call
   - Frontend periodic check detects
   - Only 'active' status allows login

4. **Work session deleted**
   - Observer immediately revokes all tokens

5. **Token expires (24 hours)**
   - Sanctum automatically invalidates

6. **User manually detached from work session**
   - No explicit token revoke (work session still valid for other users)
   - But if digit code is disabled → Observer revokes

## User Experience

1. **Immediate Feedback**
   - **Sticky** error notification: "A munkamenet már nem érvényes. Kérjük, jelentkezz be újra."
   - Red snackbar with error icon
   - **Persists even after redirect** until user manually closes (X button)
   - No auto-dismiss, user must acknowledge the error

2. **Graceful Logout**
   - All LocalStorage data cleared (kv:* namespace)
   - Photo grid state reset
   - Browser history cleared
   - Redirect to home page
   - Force page reload

3. **Proactive Validation**
   - Every 60 seconds frontend checks validity
   - Maximum 60 second delay before detection

## Testing

### Manual Test Cases

1. **Digit Code Disable**
   - Login with digit code
   - Admin disables digit code
   - Next API call → immediate logout

2. **Expiration**
   - Login with digit code
   - Set expiration to past
   - Wait 60 seconds or make API call → logout

3. **Status Change to Inactive**
   - Login with digit code
   - Admin changes status to 'inactive'
   - Next API call or 60 seconds → logout

4. **Status Change to Archived**
   - Login with digit code
   - Admin changes status to 'archived'
   - Next API call or 60 seconds → logout

5. **Deletion**
   - Login with digit code
   - Admin deletes work session
   - Next API call → immediate logout

6. **24 Hour Token Expiration**
   - Login with digit code
   - Wait 24 hours
   - Any API call → token expired, logout

### Verification Commands

```bash
# Check token has work_session_id after login
docker compose exec db psql -U photo_stack -d photo_stack -c "SELECT id, tokenable_id, work_session_id FROM personal_access_tokens ORDER BY id DESC LIMIT 5;"

# Check all tokens for a specific work session
docker compose exec db psql -U photo_stack -d photo_stack -c "SELECT * FROM personal_access_tokens WHERE work_session_id = 1;"

# Verify token revocation after digit_code_enabled change
# 1. Count tokens before
# 2. Disable digit_code_enabled in Filament
# 3. Count tokens after (should be 0)
```

## Performance Considerations

- **Middleware overhead:** Minimal (1 DB query per API request for guest users)
- **Index on work_session_id:** Ensures fast lookups
- **Frontend validation frequency:** 60 seconds (configurable)
- **No impact on regular users:** Middleware skips validation if no work_session_id

## Security Benefits

1. **Automatic token revocation** when session becomes invalid
2. **No orphaned tokens** after work session changes
3. **Time-limited authentication** (24 hours max)
4. **Immediate enforcement** of access policy changes
5. **Proactive frontend validation** reduces window of unauthorized access

## Future Enhancements

- [ ] Configurable validation interval (currently 60 seconds)
- [ ] Admin notification when mass logout occurs
- [ ] Grace period before logout (warning notification)
- [ ] Reconnection attempt with same digit code (if still valid)
- [ ] Analytics tracking of automatic logouts

## Related Files

**Backend:**
- `database/migrations/2025_10_29_083829_add_work_session_id_to_personal_access_tokens_table.php`
- `app/Http/Middleware/CheckWorkSessionStatus.php`
- `bootstrap/app.php`
- `app/Http/Controllers/Api/AuthController.php`
- `app/Observers/WorkSessionObserver.php`
- `app/Models/WorkSession.php`
- `config/sanctum.php`
- `routes/api.php`

**Frontend:**
- `frontend/src/app/core/services/auth.service.ts`
- `frontend/src/app/core/services/snackbar.service.ts`
- `frontend/src/app/core/interceptors/auth.interceptor.ts`
- `frontend/src/app/app.component.ts`
- `frontend/src/app/shared/components/snackbar/snackbar.component.ts`

## Commit Message

```
feat: implement automatic logout for expired work sessions

- Add work_session_id to personal_access_tokens table
- Create CheckWorkSessionStatus middleware for validation
- Enhance WorkSessionObserver to revoke tokens on changes
- Add frontend periodic session validation (60s interval)
- Configure Sanctum token expiration (24h)
- Show toast notification on session invalidation
- Immediate logout when digit code disabled/expired/archived

Fixes issue where guest users remained logged in after work
session became invalid. Now automatically logs out users when:
- Digit code is disabled
- Work session expires
- Status changes to archived/completed
- Work session is deleted
- Token expires (24 hours)
```

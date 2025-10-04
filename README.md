# newsletter.szabo.jp# newsletter.szabo.jp

Source of [https://newsletter.szabo.jp](https://newsletter.szabo.jp)

A simple PHP newsletter subscription system for my blog, [szabo.jp](https://szabo.jp). This wasn't really made to be reused, so it has bunch of hardcoded values.

## Features

- Email subscription with confirmation
- Unsubscribe functionality
- Automated digest sending for new blog posts
- Full end-to-end test coverage
- Mock email system for testing

### Development & Testing

#### Running Tests

Simply run:
```bash
make test
```

This single command:
- Starts MySQL database in Docker
- Starts PHP container
- Runs all end-to-end tests
- Cleans up everything automatically

#### Other Commands

```bash
make help       # Show all available commands
make clean      # Clean up Docker resources
make db-shell   # Connect to test database (while tests are running)
make db-logs    # View database logs
```

**That's it!** No need to install PHP or MySQL locally - everything runs in Docker.

## Testing

The test suite covers the complete user flows:

### Test Cases

1. **Full Subscription Flow**
   - User subscribes with email
   - Confirmation email is sent
   - User confirms subscription
   - Subscription is activated

2. **Digest Sending**
   - Multiple users subscribe and confirm
   - New blog post is published
   - Digest emails are sent only to confirmed subscribers
   - Unconfirmed users don't receive emails

3. **Unsubscribe Flow**
   - User unsubscribes
   - All user data is deleted from database

4. **Security**
   - Unauthorized digest access is blocked
   - API key verification works correctly

5. **Edge Cases**
   - Duplicate subscriptions are handled correctly
   - Token updates work as expected

### Mock Mailer

The `MockMailer` class captures all emails sent during tests, allowing you to:
- Verify emails were sent
- Check email recipients, subjects, and content
- Test email flows without sending real emails

Example usage:
```php
$mailer = new MockMailer();
$service = new NewsletterService($db, $mailer);

$service->subscribe('test@example.com');

$emails = $mailer->getSentEmails();
// Verify confirmation email was sent
```

## CI/CD

### Unified Testing & Deployment

Both local development and CI/CD use the **exact same Docker Compose setup** - no differences!

### How It Works

On every push:

**1. Test Job** - Runs `make test` using Docker Compose
- Starts MySQL container
- Starts PHP container  
- Runs all E2E tests
- Deployment blocked if any test fails

**2. Deploy Job** - Only runs if tests pass
- Generates config.php from secrets
- Deploys to FTP server
- Dry-run on non-main branches

See `.github/workflows/deploy.yaml` for details.

**Key benefit**: What you test locally is exactly what runs in CI. No environment differences!

## API Endpoints

### Subscribe
```
POST /?action=subscribe
Body: email=user@example.com
```

### Confirm Subscription
```
GET /?action=confirm&token={token}
```

### Unsubscribe
```
GET /?action=unsubscribe&token={token}
POST /?action=unsubscribe&token={token}  (confirmation)
```

### Send Digest (Protected)
```
GET /?action=send-digest&key={secret-key}
```

## Development

### Adding New Tests

1. Add new test methods to `tests/e2e_test.php`
2. Follow the existing pattern:
   ```php
   public function testNewFeature() {
       echo "Running: testNewFeature\n";
       
       // Your test code here
       
       echo "✓ testNewFeature passed\n\n";
   }
   ```
3. Call the test in the main execution block

### Modifying the Service

The business logic is in `NewsletterService.php`. When making changes:
1. Update the service method
2. Add or update tests
3. Run the test suite to verify changes

## Contact

Questions? Contact [newsletter@szabo.jp](mailto:newsletter@szabo.jp)

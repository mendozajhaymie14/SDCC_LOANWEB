const { defineConfig } = require('@playwright/test');

/**
 * Browser tests for the registration form.
 *
 * The app is served against the scratch database (never the production
 * `sdcc` one) with LOG_LEVEL=debug, because Laravel's `log` mailer writes the
 * verification e-mail — and therefore the one-time code — at debug level.
 *
 * The browser: use the locally installed Microsoft Edge by default so no
 * browser download is needed. Override with PW_CHANNEL (e.g. 'chrome') or
 * PW_EXECUTABLE (an absolute path to a browser binary).
 */
module.exports = defineConfig({
    testDir: './tests/Browser',
    timeout: 60_000,
    globalSetup: require.resolve('./tests/Browser/global-setup.cjs'),
    reporter: [['list']],
    use: {
        baseURL: process.env.PW_BASE_URL || 'http://127.0.0.1:8001',
        channel: process.env.PW_CHANNEL || 'msedge',
        launchOptions: process.env.PW_EXECUTABLE
            ? { executablePath: process.env.PW_EXECUTABLE }
            : {},
        headless: true,
    },
    webServer: {
        command: 'php artisan serve --port=8001',
        url: 'http://127.0.0.1:8001/register',
        reuseExistingServer: false,
        timeout: 60_000,
        env: {
            APP_ENV: 'scratch',
            LOG_LEVEL: 'debug',
        },
    },
});

import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright E2E Test Configuration for DanaVision
 * 
 * Run tests: npm run test:e2e
 * Run with UI: npm run test:e2e:ui
 * Run headed: npm run test:e2e:headed
 */
export default defineConfig({
  testDir: './e2e',
  
  /* Run tests in files in parallel */
  fullyParallel: true,
  
  /* Fail the build on CI if you accidentally left test.only in the source code */
  forbidOnly: !!process.env.CI,
  
  /* Retry on CI only - reduced to 1 retry to speed up CI */
  retries: process.env.CI ? 1 : 0,
  
  /* Limit workers to prevent overwhelming the server */
  workers: process.env.CI ? 1 : 3,

  /* Reporter to use */
  reporter: [
    ['html', { outputFolder: 'playwright-report' }],
    ['list'],
  ],
  
  /* Shared settings for all the projects below */
  use: {
    /* Base URL to use in actions like `await page.goto('/')` */
    baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080',

    /* Collect trace when retrying the failed test */
    trace: 'on-first-retry',
    
    /* Take screenshot on failure */
    screenshot: 'only-on-failure',
    
    /* Video on failure */
    video: 'on-first-retry',
  },

  /* Configure projects for major browsers */
  projects: [
    /* Setup project - handles authentication */
    {
      name: 'setup',
      testMatch: /.*\.setup\.ts/,
    },

    {
      name: 'chromium',
      use: { 
        ...devices['Desktop Chrome'],
        /* Use stored auth state */
        storageState: 'e2e/.auth/user.json',
      },
      dependencies: ['setup'],
    },

    /* Test against mobile viewport */
    {
      name: 'mobile',
      use: { 
        ...devices['iPhone 13'],
        storageState: 'e2e/.auth/user.json',
      },
      dependencies: ['setup'],
    },
  ],

  /* Output folder for test artifacts */
  outputDir: 'test-results/',

  /* Global timeout for each test - longer in CI */
  timeout: process.env.CI ? 60000 : 30000,

  /* Timeout for each expect() assertion - longer in CI */
  expect: {
    timeout: process.env.CI ? 10000 : 5000,
  },
});

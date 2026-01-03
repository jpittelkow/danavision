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
  
  /* Retry on CI only */
  retries: process.env.CI ? 2 : 0,
  
  /* Opt out of parallel tests on CI */
  workers: process.env.CI ? 1 : undefined,
  
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

  /* Global timeout for each test */
  timeout: 30000,

  /* Timeout for each expect() assertion */
  expect: {
    timeout: 5000,
  },
});

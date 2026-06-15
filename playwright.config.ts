import { defineConfig, devices } from '@playwright/test';
import * as dotenv from 'dotenv';
dotenv.config();

export default defineConfig({
	testDir: './tests/e2e',
	outputDir: './temp/test-results',
	fullyParallel: true,
	reporter: [['html', { outputFolder: './temp/playwright-report' }]],
	use: {
		baseURL: process.env.APP_URL || 'http://ytaq.loc/',
		trace: 'on-first-retry',
	},
	projects: [
		{ name: 'chromium', use: { ...devices['Desktop Chrome'] } },
	],
});

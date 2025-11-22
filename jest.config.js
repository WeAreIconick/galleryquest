module.exports = {
	testEnvironment: 'jsdom',
	setupFilesAfterEnv: ['<rootDir>/tests/js/setup.js'],
	transform: {
		'^.+\\.js$': 'babel-jest',
	},
	transformIgnorePatterns: [
		'node_modules/(?!(glightbox)/)',
	],
	testMatch: ['<rootDir>/tests/**/*.test.js', '<rootDir>/tests/**/*.spec.js'],
	collectCoverageFrom: [
		'admin/js/**/*.js',
		'src/**/*.js',
		'!**/node_modules/**',
		'!**/build/**',
		'!**/dist/**',
		'!**/coverage/**',
		'!**/*.test.js',
		'!**/*.spec.js',
	],
	coverageThreshold: {
		global: {
			branches: 0, // Will increase as tests are added
			functions: 0,
			lines: 0,
			statements: 0,
		},
	},
	coverageReporters: ['text', 'lcov', 'html'],
	testTimeout: 10000,
	verbose: true,
};

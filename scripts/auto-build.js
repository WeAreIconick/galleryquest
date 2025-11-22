#!/usr/bin/env node

/**
 * Auto-build script that runs build:dist automatically
 * Can be used in CI/CD or pre-commit hooks
 */

const { execSync } = require('child_process');
const path = require('path');

const PLUGIN_DIR = path.resolve(__dirname, '..');

console.log('🤖 Auto-building production files...\n');

try {
	execSync('npm run build:dist', {
		cwd: PLUGIN_DIR,
		stdio: 'inherit',
	});
	console.log('\n✅ Auto-build completed successfully!');
	process.exit(0);
} catch (error) {
	console.error('\n❌ Auto-build failed!');
	process.exit(1);
}


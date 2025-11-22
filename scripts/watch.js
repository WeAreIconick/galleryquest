#!/usr/bin/env node

/**
 * Auto-start watch mode for development
 */

const { spawn } = require('child_process');
const path = require('path');

console.log('🚀 Starting development watch mode...\n');
console.log('📝 Watching for changes in src/ and admin/js/');
console.log('🔄 Auto-rebuilding on file changes...\n');
console.log('Press Ctrl+C to stop\n');

// Start watch mode
const watchProcess = spawn('npm', ['run', 'watch'], {
	stdio: 'inherit',
	shell: true,
	cwd: path.resolve(__dirname, '..'),
});

watchProcess.on('error', (error) => {
	console.error('❌ Error starting watch mode:', error);
	process.exit(1);
});

watchProcess.on('exit', (code) => {
	if (code !== 0 && code !== null) {
		console.error(`\n❌ Watch mode exited with code ${code}`);
		process.exit(code);
	}
});

// Handle graceful shutdown
process.on('SIGINT', () => {
	console.log('\n\n👋 Stopping watch mode...');
	watchProcess.kill('SIGINT');
	process.exit(0);
});

process.on('SIGTERM', () => {
	watchProcess.kill('SIGTERM');
	process.exit(0);
});


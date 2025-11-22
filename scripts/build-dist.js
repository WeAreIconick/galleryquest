#!/usr/bin/env node

/**
 * Production build script for Gallery Quest
 * Creates a clean dist/ folder with only production files
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const PLUGIN_DIR = path.resolve(__dirname, '..');
const DIST_DIR = path.join(PLUGIN_DIR, 'dist');
const BUILD_DIR = path.join(PLUGIN_DIR, 'build');

// Files and directories to copy
const COPY_PATTERNS = [
	'gallery-quest.php',
	'includes/**/*',
	'admin/class-meta-panel.php',
	'admin/js/attachment-fields.js', // This is vanilla JS, not built
	'README.md',
];

// Files to exclude
const EXCLUDE_PATTERNS = [
	'**/node_modules/**',
	'**/.git/**',
	'**/src/**',
	'**/build/**',
	'**/dist/**',
	'**/.gitignore',
	'**/.distignore',
	'**/webpack.config.js',
	'**/package.json',
	'**/package-lock.json',
	'**/*.log',
	'**/.DS_Store',
];

/**
 * Copy file or directory
 * @param src
 * @param dest
 */
function copyItem(src, dest) {
	const stat = fs.statSync(src);

	if (stat.isDirectory()) {
		if (!fs.existsSync(dest)) {
			fs.mkdirSync(dest, { recursive: true });
		}
		const items = fs.readdirSync(src);
		items.forEach((item) => {
			copyItem(path.join(src, item), path.join(dest, item));
		});
	} else {
		const destDir = path.dirname(dest);
		if (!fs.existsSync(destDir)) {
			fs.mkdirSync(destDir, { recursive: true });
		}
		fs.copyFileSync(src, dest);
	}
}

/**
 * Check if path should be excluded
 * @param filePath
 */
function shouldExclude(filePath) {
	const relativePath = path.relative(PLUGIN_DIR, filePath);
	return EXCLUDE_PATTERNS.some((pattern) => {
		const regex = new RegExp(pattern.replace(/\*\*/g, '.*').replace(/\*/g, '[^/]*'));
		return regex.test(relativePath);
	});
}

/**
 * Main build function
 */
function buildDist() {
	console.log('🚀 Starting production build...\n');

	// Step 1: Build assets
	console.log('📦 Building assets...');
	try {
		execSync('npm run build', {
			cwd: PLUGIN_DIR,
			stdio: 'inherit',
			env: { ...process.env, NODE_ENV: 'production' },
		});
		console.log('✅ Assets built successfully\n');
	} catch (error) {
		console.error('❌ Build failed:', error.message);
		process.exit(1);
	}

	// Step 2: Clean dist directory
	if (fs.existsSync(DIST_DIR)) {
		console.log('🧹 Cleaning dist directory...');
		fs.rmSync(DIST_DIR, { recursive: true, force: true });
	}
	fs.mkdirSync(DIST_DIR, { recursive: true });
	console.log('✅ Dist directory cleaned\n');

	// Step 3: Copy production files
	console.log('📋 Copying production files...');

	// Copy main plugin file
	copyItem(path.join(PLUGIN_DIR, 'gallery-quest.php'), path.join(DIST_DIR, 'gallery-quest.php'));

	// Copy includes directory
	copyItem(path.join(PLUGIN_DIR, 'includes'), path.join(DIST_DIR, 'includes'));

	// Copy admin files (excluding JS source, but including built files)
	const adminDist = path.join(DIST_DIR, 'admin');
	fs.mkdirSync(adminDist, { recursive: true });
	copyItem(
		path.join(PLUGIN_DIR, 'admin/class-meta-panel.php'),
		path.join(adminDist, 'class-meta-panel.php')
	);

	// Copy attachment-fields.js (vanilla JS, not built)
	const adminJsDist = path.join(adminDist, 'js');
	fs.mkdirSync(adminJsDist, { recursive: true });
	copyItem(
		path.join(PLUGIN_DIR, 'admin/js/attachment-fields.js'),
		path.join(adminJsDist, 'attachment-fields.js')
	);

	// Copy built files from build/ to dist/build/
	console.log('📦 Copying built assets...');

	// Copy entire build directory to dist/build/ (WordPress expects block files here)
	const buildDist = path.join(DIST_DIR, 'build');
	copyItem(BUILD_DIR, buildDist);

	// Copy README if it exists
	if (fs.existsSync(path.join(PLUGIN_DIR, 'README.md'))) {
		copyItem(path.join(PLUGIN_DIR, 'README.md'), path.join(DIST_DIR, 'README.md'));
	}

	console.log('✅ Production files copied\n');

	// Step 4: Summary
	const distSize = getDirectorySize(DIST_DIR);
	console.log('✨ Build complete!');
	console.log(`📁 Dist directory: ${DIST_DIR}`);
	console.log(`📊 Total size: ${formatBytes(distSize)}`);
	console.log('\n🎉 Ready for deployment!');
}

/**
 * Get directory size in bytes
 * @param dirPath
 */
function getDirectorySize(dirPath) {
	let totalSize = 0;

	function calculateSize(currentPath) {
		const stat = fs.statSync(currentPath);
		if (stat.isDirectory()) {
			const items = fs.readdirSync(currentPath);
			items.forEach((item) => {
				calculateSize(path.join(currentPath, item));
			});
		} else {
			totalSize += stat.size;
		}
	}

	calculateSize(dirPath);
	return totalSize;
}

/**
 * Format bytes to human readable
 * @param bytes
 */
function formatBytes(bytes) {
	if (bytes === 0) {
		return '0 Bytes';
	}
	const k = 1024;
	const sizes = ['Bytes', 'KB', 'MB', 'GB'];
	const i = Math.floor(Math.log(bytes) / Math.log(k));
	return `${Math.round((bytes / Math.pow(k, i)) * 100) / 100} ${sizes[i]}`;
}

// Run build
buildDist();

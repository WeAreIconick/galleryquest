# Build System

Gallery Quest includes a comprehensive build system for development and production.

## Automatic Build Features

### 🚀 Auto-Build on Install

After running `npm install`, the build automatically runs via the `postinstall` hook.

### 🔄 Auto-Watch Mode

Simply run:

```bash
npm start
# or
npm run dev
```

This automatically:

- Starts watch mode
- Rebuilds on every file change
- Shows clear status messages
- Handles graceful shutdown (Ctrl+C)

### 📝 VS Code Integration

If you use VS Code, the build tasks run automatically:

- **On folder open**: Watch mode starts automatically (configurable)
- **Tasks**: Press `Cmd+Shift+P` → "Run Task" → "Watch: Gallery Quest"

### 🤖 CI/CD Integration

GitHub Actions workflow automatically builds on:

- Push to main/master
- Pull requests
- Tag releases (v\*)
- Manual workflow dispatch

## Available Scripts

### Development

```bash
# Start development mode with auto-rebuild (watch mode)
npm start
# or
npm run dev
```

This will:

- Watch for file changes in `src/` and `admin/js/`
- Automatically rebuild assets when files change
- Enable hot reloading in the WordPress block editor
- Run continuously until stopped (Ctrl+C)

### Production Build

```bash
# Build production-ready dist folder
npm run build:dist
```

This will:

1. Build all assets (JavaScript, CSS) with optimizations
2. Create a clean `dist/` folder
3. Copy only production files (excludes source files, node_modules, etc.)
4. Prepare the plugin for deployment

### Other Commands

```bash
# Build assets only (for development)
npm run build

# Format code
npm run format

# Lint CSS
npm run lint:css

# Lint JavaScript
npm run lint:js

# Create plugin ZIP
npm run plugin-zip
```

## File Structure

### Development Structure

```
gallery-quest/
├── src/              # Block source files
├── admin/js/         # Admin JavaScript source
├── includes/         # PHP classes
├── build/            # Built assets (gitignored)
└── dist/             # Production build (gitignored)
```

### Production Structure (dist/)

```
dist/
├── gallery-quest.php
├── includes/
├── admin/
│   ├── class-meta-panel.php
│   └── js/
│       └── attachment-fields.js
├── build/            # All compiled assets
│   ├── block.json
│   ├── index.js
│   ├── view.js
│   └── ...
└── README.md
```

## Development Workflow

1. **Start development:**

   ```bash
   npm run dev
   ```

2. **Make changes** to files in `src/` or `admin/js/`

3. **Files auto-rebuild** - no need to manually run build

4. **Test in WordPress** - refresh the block editor to see changes

## Production Workflow

1. **Build production version:**

   ```bash
   npm run build:dist
   ```

2. **Deploy the `dist/` folder** to your WordPress installation

3. The `dist/` folder contains only production files:
   - No source files (`src/`, `admin/js/` source)
   - No development dependencies (`node_modules/`)
   - No build configuration files
   - Only compiled, optimized assets

## What Gets Excluded

The production build excludes:

- `src/` - Source files (only built assets included)
- `admin/js/` - Source JavaScript (only built files included)
- `node_modules/` - Dependencies
- `build/` - Development build folder
- `webpack.config.js` - Build configuration
- `package.json` / `package-lock.json` - NPM files
- `.git/` - Git repository
- Development tools and IDE files

## Notes

- The `dist/` folder is gitignored - it's generated, not committed
- Always run `npm run build:dist` before deploying
- The watch mode (`npm run dev`) is for development only
- Production builds are optimized and minified

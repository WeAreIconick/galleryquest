const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
	...defaultConfig,
	entry: {
		index: './src/index.js',
		view: './src/view.js',
		'gallery-meta-panel': './admin/js/gallery-meta-panel.js',
	},
	output: {
		...defaultConfig.output,
		// WordPress scripts handles output paths automatically
	},
};

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		main: './src/js/main.js',
	},
	output: {
		path: defaultConfig.output.path,
		filename: '[name].js',
	},
};

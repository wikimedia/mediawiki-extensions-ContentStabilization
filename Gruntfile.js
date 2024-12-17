module.exports = function ( grunt ) {
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );

	const conf = grunt.file.readJSON( 'extension.json' );
	grunt.initConfig( {
		eslint: {
			options: {
				cache: true
			},
			all: [
				'**/*.{js,json}',
				'!node_modules/**',
				'!vendor/**'
			]
		},
		banana: Object.assign(
			conf.MessagesDirs,
			{
				options: {
					requireLowerCase: 'initial'
				}
			}
		)
	} );

	grunt.registerTask( 'test', [ 'eslint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};

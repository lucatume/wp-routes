module.exports = function ( grunt ) {

	var dependencies = ["vendor/lucatume/klein52/"],
		delete_patterns = [".git/**", "tests/**", ".gitignore","**.md", "Gruntfile.js", "example-functions.php", "composer.{json,lock}", "{.travis,.scrutinizer,codeception*,}.yml", "coverage.clover", "phpunit.xml.dist"],
		clean_dist_patterns = [],
		git_add_patterns = [];

	for ( i = 0; i < dependencies.length; i++ ) {
		git_add_patterns.push( dependencies[i] + '**' );
		for ( k = 0; k < delete_patterns.length; k++ ) {
			clean_dist_patterns.push( dependencies[i] + delete_patterns[k] );
		}
	}

	grunt.initConfig( {
		pkg: grunt.file.readJSON( 'package.json' ),
		clean: {
			dist: clean_dist_patterns
		},
		gitadd: {
			dist: {
				options: {
					verbose: true,
					force: true
				},
				files: {
					src: git_add_patterns
				}
			}
		}
	} );

	grunt.loadNpmTasks( 'grunt-contrib-clean' );
	grunt.loadNpmTasks( 'grunt-git' );

	grunt.registerTask( 'after-composer-update', ['clean:dist', 'gitadd:dist'] );
};
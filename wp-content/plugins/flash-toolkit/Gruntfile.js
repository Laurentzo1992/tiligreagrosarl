/* jshint node:true */
module.exports = function( grunt ){
	'use strict';

	grunt.initConfig({

		// Setting folder templates.
		dirs: {
			js: 'assets/js',
			css: 'assets/css'
		},

		// JavaScript linting with JSHint.
		jshint: {
			options: {
				jshintrc: '.jshintrc'
			},
			all: [
				'Gruntfile.js',
				'<%= dirs.js %>/admin/*.js',
				'!<%= dirs.js %>/admin/*.min.js'
			]
		},

		// Sass linting with Stylelint.
		stylelint: {
			options: {
				stylelintrc: '.stylelintrc'
			},
			all: [
				'<%= dirs.css %>/*.scss',
				'!<%= dirs.css %>/select2.scss'
			]
		},

		// Minify all .js files.
		uglify: {
			options: {
				ie8: true,
				parse: {
					strict: false
				},
				output: {
					comments: /@license|@preserve|^!/
				}
			},
			admin: {
				files: [{
					expand: true,
					cwd: '<%= dirs.js %>/admin/',
					src: [
						'*.js',
						'!*.min.js'
					],
					dest: '<%= dirs.js %>/admin/',
					ext: '.min.js'
				}]
			},
			vendor: {
				files: {
					'<%= dirs.js %>/jquery-tiptip/jquery.tipTip.min.js': ['<%= dirs.js %>/jquery-tiptip/jquery.tipTip.js'],
					'<%= dirs.js %>/select2/select2.min.js': ['<%= dirs.js %>/select2/select2.js'],
					'<%= dirs.js %>/admin/jquery-ui-timepicker-addon.min.js': ['<%= dirs.js %>/admin/jquery-ui-timepicker-addon.js']
				}
			}
		},

		// Compile all .scss files.
		sass: {
			options: {
				sourcemap: 'none'
			},
			compile: {
				files: [{
					expand: true,
					cwd: '<%= dirs.css %>/',
					src: ['*.scss'],
					dest: '<%= dirs.css %>/',
					ext: '.css'
				}]
			}
		},

		// Generate all RTL .css files.
		rtlcss: {
			generate: {
				expand: true,
				cwd: '<%= dirs.css %>',
				src: [
					'*.css',
					'!select2.css',
					'!fontawesome.css',
					'!jquery-ui-timepicker-addon.css',
					'!*-rtl.css'
				],
				dest: '<%= dirs.css %>/',
				ext: '-rtl.css'
			}
		},

		// Minify all .css files.
		cssmin: {
			minify: {
				expand: true,
				cwd: '<%= dirs.css %>/',
				src: ['*.css'],
				dest: '<%= dirs.css %>/',
				ext: '.css'
			}
		},

		// Concatenate select2.css onto the admin.css files.
		concat: {
			admin: {
				files: {
					'<%= dirs.css %>/admin.css' : ['<%= dirs.css %>/select2.css', '<%= dirs.css %>/admin.css'],
					'<%= dirs.css %>/admin-rtl.css' : ['<%= dirs.css %>/select2.css', '<%= dirs.css %>/admin-rtl.css'],
					'<%= dirs.css %>/widgets.css' : ['<%= dirs.css %>/select2.css', '<%= dirs.css %>/widgets.css'],
					'<%= dirs.css %>/widgets-rtl.css' : ['<%= dirs.css %>/select2.css', '<%= dirs.css %>/widgets-rtl.css']
				}
			}
		},

		// Watch changes for assets.
		watch: {
			css: {
				files: [
					'<%= dirs.css %>/*.scss'
				],
				tasks: ['sass', 'rtlcss', 'cssmin', 'concat']
			},
			js: {
				files: [
					'<%= dirs.js %>/admin/*.js',
					'!<%= dirs.js %>/admin/*.min.js'
				],
				tasks: ['jshint', 'uglify']
			}
		},

		// Generate POT files.
		makepot: {
			options: {
				type: 'wp-plugin',
				domainPath: 'i18n/languages',
				potHeaders: {
					'report-msgid-bugs-to': 'themegrill@gmail.com',
					'language-team': 'LANGUAGE <EMAIL@ADDRESS>'
				}
			},
			dist: {
				options: {
					potFilename: 'flash-toolkit.pot',
					exclude: [
						'vendor/.*'
					]
				}
			}
		},

		// Check textdomain errors.
		checktextdomain: {
			options: {
				text_domain: 'flash-toolkit',
				keywords: [
					'__:1,2d',
					'_e:1,2d',
					'_x:1,2c,3d',
					'esc_html__:1,2d',
					'esc_html_e:1,2d',
					'esc_html_x:1,2c,3d',
					'esc_attr__:1,2d',
					'esc_attr_e:1,2d',
					'esc_attr_x:1,2c,3d',
					'_ex:1,2c,3d',
					'_n:1,2,4d',
					'_nx:1,2,4c,5d',
					'_n_noop:1,2,3d',
					'_nx_noop:1,2,3c,4d'
				]
			},
			files: {
				src: [
					'**/*.php',         // Include all files
					'!node_modules/**', // Exclude node_modules/
					'!vendor/**'        // Exclude vendor/
				],
				expand: true
			}
		},

		// PHP Code Sniffer.
		phpcs: {
			options: {
				bin: 'vendor/bin/phpcs'
			},
			dist: {
				src:  [
					'**/*.php',         // Include all files
					'!node_modules/**', // Exclude node_modules/
					'!vendor/**'        // Exclude vendor/
				]
			}
		},

		// Autoprefixer.
		postcss: {
			options: {
				processors: [
					require( 'autoprefixer' )({
						browsers: [
							'> 0.1%',
							'ie 8',
							'ie 9'
						]
					})
				]
			},
			dist: {
				src: [
					'<%= dirs.css %>/*.css'
				]
			}
		},

		// Compress files and folders.
		compress: {
			options: {
				archive: 'flash-toolkit.zip'
			},
			files: {
				src: [
					'**',
					'!.*',
					'!*.md',
					'!none',
					'!*.zip',
					'!.*/**',
					'!sass/**',
					'!vendor/**',
					'!phpcs.xml',
					'!composer.*',
					'!package.json',
					'!Gruntfile.js',
					'!node_modules/**',
					'!package-lock.json'
				],
				dest: 'flash-toolkit',
				expand: true
			}
		}
	});

	// Load NPM tasks to be used here
	grunt.loadNpmTasks( 'grunt-sass' );
	grunt.loadNpmTasks( 'grunt-phpcs' );
	grunt.loadNpmTasks( 'grunt-rtlcss' );
	grunt.loadNpmTasks( 'grunt-postcss' );
	grunt.loadNpmTasks( 'grunt-stylelint' );
	grunt.loadNpmTasks( 'grunt-wp-i18n' );
	grunt.loadNpmTasks( 'grunt-checktextdomain' );
	grunt.loadNpmTasks( 'grunt-contrib-jshint' );
	grunt.loadNpmTasks( 'grunt-contrib-uglify' );
	grunt.loadNpmTasks( 'grunt-contrib-cssmin' );
	grunt.loadNpmTasks( 'grunt-contrib-concat' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );
	grunt.loadNpmTasks( 'grunt-contrib-compress' );

	// Register tasks.
	grunt.registerTask( 'default', [
		'js',
		'css',
		'i18n'
	]);

	grunt.registerTask( 'js', [
		'jshint',
		'uglify:admin'
	]);

	grunt.registerTask( 'css', [
		'sass',
		'rtlcss',
		'postcss',
		'cssmin',
		'concat'
	]);

	// Only an alias to 'default' task.
	grunt.registerTask( 'dev', [
		'default'
	]);

	grunt.registerTask( 'i18n', [
		'checktextdomain',
		'makepot'
	]);

	grunt.registerTask( 'zip', [
		'dev',
		'compress'
	]);

	grunt.registerTask( 'zip', [
		'default',
		'compress'
	]);

	grunt.registerTask( 'watch', [
		'watch'
	]);
};

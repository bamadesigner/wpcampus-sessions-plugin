// Require all the things (that we need).
var gulp = require('gulp');
var phpcs = require('gulp-phpcs');
var watch = require('gulp-watch');

// Define the source paths for each file type.
var src = {
	php: ['**/*.php','!vendor/**','!node_modules/**']
};

// Check our PHP.
gulp.task('php',function() {
	gulp.src(src.php)
		.pipe(phpcs({
			bin: 'vendor/bin/phpcs',
			standard: 'WordPress-Core'
		}))
		.pipe(phpcs.reporter('log'));
});

// Test our files.
gulp.task('test',['php']);

// I've got my eyes on you(r file changes).
gulp.task('watch',function() {
	gulp.watch(src.php,['php']);
});

// Let's get this party started.
gulp.task('default',['test']);
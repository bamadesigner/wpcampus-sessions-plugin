var gulp = require('gulp');
var phpcs = require('gulp-phpcs');
var watch = require('gulp-watch');

// Set the source for specific files.
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

// Watch the files.
gulp.task('watch',function() {
	gulp.watch(src.php,['php']);
});

// Our default tasks.
gulp.task('default',['test']);
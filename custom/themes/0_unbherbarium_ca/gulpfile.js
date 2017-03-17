/*jshint globalstrict: true, node: true */
'use strict';
var path = require('path');
var options = {};

// For use with browsersync.
// var local_uri = 'thunder.local'; // 'sitename.local'

// For use with gulp-shell in special cases.
var cliSass = 'node_modules/node-sass/bin/node-sass';

// #############################
// Edit these paths and options.
// #############################

// The root paths are used to construct all the other paths in this
// configuration. The "project" root path is where this gulpfile.js is located.
// This gulpfile is based on the Zen 7.6 version with several modifications
// to remove Ruby dependencies and add other features.
options.rootPath = {
  project: __dirname + '/',
  styleGuide: __dirname + '/styleguide/',
  theme: __dirname + '/'
};

options.theme = {
  root: options.rootPath.theme,
  css: options.rootPath.theme + 'dist/css/',
  sass: options.rootPath.theme + 'src/scss/',
  js: options.rootPath.theme + 'src/js/'
};

// Define the path to the project's .scss-lint.yml.
options.scssLint = {
  yml: options.rootPath.project + '.scss-lint.yml'
};

// Define the paths to the JS files to lint.
options.eslint = {
  files: [
    options.theme.js + '**/*.js',
    '!' + options.theme.js + '**/*.min.js'
  ]
};

options.gulpWatchOptions = {};

// ################################
// Load Gulp and tools we will use.
// ################################
var gulp      = require('gulp'),
  $           = require('gulp-load-plugins')(),
  del         = require('del'),
  runSequence = require('run-sequence'),
  sassLint    = require('gulp-sass-lint'),
  sourcemaps  = require('gulp-sourcemaps'),
  globbing    = require('gulp-css-globbing'),
  sass        = require('gulp-sass'),
  debug       = require('gulp-debug'),
  importOnce  = require('node-sass-import-once'),
  pngquant    = require('imagemin-pngquant'),
  optipng     = require('imagemin-optipng'),
  jpegoptim   = require('imagemin-jpegoptim'),
  svgo        = require('imagemin-svgo'),
  concat      = require('gulp-concat'),
  uglify      = require('gulp-uglify'),
  rename      = require('gulp-rename');

// The default task.
gulp.task('default', ['build']);

// #################
// Build everything.
// #################
gulp.task('build', ['styles:production', 'images', 'scripts'], function (cb) {
  // Run linting last, otherwise its output gets lost.
  runSequence(['lint'], cb);
});

gulp.task('build:dev', ['styles', 'images', 'scripts'], function (cb) {
  // Run linting last, otherwise its output gets lost.
  runSequence(['lint'], cb);
});

// ##########
// Build CSS.
// ##########

gulp.task('styles', ['clean:css'], function () {
  return gulp.src([
    options.theme.sass + 'style.scss'
    ])
    // Initializes sourcemaps
    .pipe(sourcemaps.init())
    .pipe(globbing({
        // Configure it to use SCSS files
        extensions: ['.scss']
    }))
    .pipe(sass({
      errLogToConsole: true,
      outputStyle: 'expanded'
      }))
    // Writes sourcemaps into the CSS file
    .pipe(sourcemaps.write())
    // Send out to the stylesheets in /dist ༼つ◕◡◕༽つ
    .pipe(gulp.dest(options.theme.css));
});

gulp.task('styles:production', ['clean:css'], function () {
  return gulp.src([
    options.theme.sass + 'style.scss',
    ])
      .pipe(globbing({
          // Configure it to use SCSS files
          extensions: ['.scss']
      }))
      .pipe(sass({
          errLogToConsole: true,
          outputStyle: 'compressed'
      }))
      .pipe(gulp.dest(options.theme.css));
});

gulp.task('scripts', function () {
    return gulp.src(options.theme.js + '**/*.js')
        .pipe(concat('all.js'))
        .pipe(gulp.dest('dist/js'))
        .pipe(rename('all.min.js'))
        .pipe(uglify())
        .pipe(gulp.dest('dist/js'));
});

// #########################
// Lint Sass and JavaScript.
// #########################
gulp.task('lint', function (cb) {
  runSequence(['lint:js', 'lint:sass'], cb);
});

// Lint JavaScript.
gulp.task('lint:js', function () {
  return gulp.src(options.eslint.files)
    .pipe($.eslint())
    .pipe($.eslint.format());
});

// Lint JavaScript and throw an error for a CI to catch.
gulp.task('lint:js-with-fail', function () {
  return gulp.src(options.eslint.files)
    .pipe($.eslint())
    .pipe($.eslint.format())
    .pipe($.eslint.failOnError());
});

// Lint Sass.
gulp.task('lint:sass', function () {
  return gulp.src(options.theme.sass + '**/*.s+(a|c)ss')
    .pipe(sassLint())
    .pipe(sassLint.format());
});

// Lint Sass and throw an error for a CI to catch.
gulp.task('lint:sass-with-fail', function () {
  return gulp.src(options.theme.sass + '**/*.s+(a|c)ss')
    .pipe(sassLint())
    .pipe(sassLint.format())
    .pipe(sassLint.failOnError());
});

// ##############################
// Optimize images.
// ##############################
gulp.task('images', function () {
  gulp.src('src/img/**/*.{png,jpg,jpeg,gif,svg}')
      .pipe(debug({title: 'optimized images:'}))
      .pipe(pngquant({quality: '65-80', speed: 4})())
      .pipe(optipng({optimizationLevel: 3})())
      .pipe(jpegoptim({max: 70})())
      .pipe(svgo()())
      .pipe(gulp.dest('dist/img'));
});

// ##############################
// Watch for changes and rebuild.
// ##############################
// gulp.task('watch', ['watch:lint-and-styleguide', 'watch:js'], function (cb) {
gulp.task('watch', ['watch:js'], function (cb) {
  // Since watch:css will never return, call it last (not as dependency.)
  runSequence(['watch:css'], cb);
});

gulp.task('watch:css', ['lint:sass', 'styles'], function () {
  return gulp.watch([
      options.theme.sass + '**/*.scss'
    ], options.gulpWatchOptions, ['lint:sass','styles']);
});

gulp.task('watch:lint-and-styleguide', ['styleguide', 'lint:sass'], function () {
  return gulp.watch([
      options.theme.sass + '**/*.scss',
      options.theme.sass + '**/*.hbs'
    ], options.gulpWatchOptions, ['styleguide', 'lint:sass']);
});

gulp.task('watch:js', ['lint:js', 'scripts'], function () {
  return gulp.watch([options.theme.js + '**/*.js'], options.gulpWatchOptions, ['lint:js','scripts']);
});

// ######################
// Clean all directories.
// ######################
gulp.task('clean', ['clean:css', 'clean:styleguide']);

// Clean style guide files.
gulp.task('clean:styleguide', function (cb) {
  // You can use multiple globbing patterns as you would with `gulp.src`
  del([
      options.styleGuide.destination + '*.html',
      options.styleGuide.destination + 'public',
      options.theme.css + '**/*.hbs'
    ], {force: true}, cb);
});

// Clean CSS files.
gulp.task('clean:css', function (cb) {
  del([
      options.theme.root + '**/.sass-cache',
      options.theme.css + '**/*.css',
      options.theme.css + '**/*.map'
    ], {force: true}, cb);
});

// Resources used to create this gulpfile.js:
// - https://github.com/google/web-starter-kit/blob/master/gulpfile.js
// - https://github.com/north/generator-north/blob/master/app/templates/Gulpfile.js

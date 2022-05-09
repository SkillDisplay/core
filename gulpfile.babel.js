"use strict";

let gulp = require('gulp');
let plugins = require('gulp-load-plugins');
let rimraf = require('rimraf');
let panini = require('panini');
let lazypipe = require('lazypipe');
let inky = require('inky');
let fs = require('fs');
let siphon = require('siphon-media-query');
let sass = require('gulp-sass')(require('sass'));

const $ = plugins();

let Tasks = {
  devContext: true,
  sources: {
    css: [
      'Resources/Public/Scss/*.scss'
    ],
  },

  setProduction: function (done) {
    Tasks.devContext = false;
    return done();
  },
  copy: function () {
    return gulp.src('node_modules/@fortawesome/fontawesome-free/webfonts/*')
      .pipe(gulp.dest('Resources/Public/Fonts/'));
  },
  jsbe: function () {
    let tsProject = $.typescript.createProject("tsconfig.json", {
      module: 'amd',
      moduleResolution: "node",
    });
    let src = gulp
      .src('Resources/Private/TypeScript/BE/*.ts')
      .pipe(tsProject($.typescript.reporter.longReporter())).js;
    return src.pipe(gulp.dest('Resources/Public/JavaScript/'));
  },
  watch: function (done) {
    gulp.watch(Tasks.sources.js, ['js']);
    return done();
  },
  sass: function () {
    let scss_paths = [
      'node_modules/'
    ];

    return gulp
      .src(Tasks.sources.css)
      .pipe(sass({
        outputStyle: Tasks.devContext ? '' : 'compressed',
        includePaths: scss_paths
      }).on('error', sass.logError))
      .pipe($.autoprefixer({
        cascade: false
      }))
      .pipe(gulp.dest('Resources/Public/Css/'));
  },
  mail: {
    dest: 'Resources/Private/MailTemplates',
    // Compile Sass into CSS
    sass: function () {
      return gulp.src('Resources/Private/MailTemplatesSrc/scss/app.scss')
        .pipe(sass({
          includePaths: ['node_modules/foundation-emails/scss']
        }).on('error', sass.logError))
        .pipe($.uncss({
            html: [Tasks.mail.dest + '/**/*.html']
          }
        ))
        .pipe(gulp.dest(Tasks.mail.dest));
    },
    // Delete the "dist" folder
    // This happens every time a build starts
    clean: function (done) {
      rimraf(Tasks.mail.dest, done);
    },
    // Compile layouts, pages, and partials into flat HTML files
    // Then parse using Inky templates
    pages: function () {
      return gulp
        .src([
          'Resources/Private/MailTemplatesSrc/pages/**/*.html',
          'Resources/Private/MailTemplatesSrc/pages/**/*.txt'
        ])
        .pipe(panini({
          root: 'Resources/Private/MailTemplatesSrc/pages',
          partials: 'Resources/Private/MailTemplatesSrc/partials',
          layouts: 'Resources/Private/MailTemplatesSrc/layouts'
        }))
        .pipe(inky({
          cheerio: {
            xmlMode: true,
            lowerCaseAttributeNames: false,
            lowerCaseTags: false
          }
        }))
        .pipe(gulp.dest(Tasks.mail.dest));
    },
    // Inline CSS and minify HTML
    inline: function () {
      return gulp.src(Tasks.mail.dest + '/**/*.html')
        .pipe(inliner(Tasks.mail.dest + '/app.css'))
        .pipe(gulp.dest(Tasks.mail.dest));
    }
  }
};

// Inlines CSS into HTML, adds media query CSS into the <style> tag of the email, and compresses the HTML
function inliner(css) {
  css = fs.readFileSync(css).toString();
  let mqCss = siphon(css);

  let pipe = lazypipe()
    .pipe($.inlineCss, {
      applyStyleTags: false,
      preserveMediaQueries: true,
      removeLinkTags: false,
      lowerCaseTags: false,
    })
    .pipe($.replace, '<!-- <style> -->', `<style>${mqCss}</style>`)
    .pipe($.replace, '<link rel="stylesheet" type="text/css" href="../../app.css">', '');

  return pipe();
}

gulp.task('sass', Tasks.sass);
gulp.task('copy', Tasks.copy);
gulp.task('jsbe', Tasks.jsbe);
gulp.task('watch', Tasks.watch);

gulp.task('setProduction', Tasks.setProduction);

gulp.task('release', gulp.series('setProduction', 'copy', gulp.parallel('sass', 'jsbe')));
gulp.task('dev', gulp.series(gulp.parallel('sass', 'jsbe')));
gulp.task('mail', gulp.series(Tasks.mail.clean, Tasks.mail.pages, Tasks.mail.sass, Tasks.mail.inline));

gulp.task('default', function (done) {
  process.stdout.write('\n'
    + '===========================================\n'
    + 'Tasks: dev, release, copy, watch, mail\n'
    + '===========================================\n'
    + '\n'
  );
  return done();
});

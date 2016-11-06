<?php

/** @author Martin Mozos <martinmozos@gmail.com> */
require_once 'recipe/common.php';

/* App Configuration  */

// Symfony shared dirs
set('shared_dirs', ['app/logs']);

// Symfony shared files
set('shared_files', ['app/config/parameters.yml']);

// Symfony writable dirs
set('writable_dirs', ['app/cache', 'app/logs']);

// Assets
set('assets', ['public/css', 'public/images', 'public/js']);
// Default true - BC for Symfony < 3.0
set('dump_assets', false);

// Environment vars
env('env_vars', 'SYMFONY_ENV=prod');
env('env', 'prod');

// Adding support for the Symfony3 directory structure
set('bin_dir', 'bin');
set('var_dir', 'app');

/*
 * Create cache dir
 */
task('deploy:create_cache_dir', function () {
    // Set cache dir
    env('cache_dir', '{{release_path}}/' . trim(get('var_dir'), '/') . '/cache');

    // Remove cache dir if it exist
    run('if [ -d "{{cache_dir}}" ]; then rm -rf {{cache_dir}}; fi');

    // Create cache dir
    run('mkdir -p {{cache_dir}}');

    // Set rights
    run('chmod -R g+w {{cache_dir}}');
})->desc('Create cache dir');

/*
 * Create cache dir
 */
task('deploy:create_logs_dir', function () {
    // Set cache dir
    env('logs_dir', '{{release_path}}/' . trim(get('var_dir'), '/') . '/logs');
    // Remove cache dir if it exist
    run('if [ -d "{{logs_dir}}" ]; then rm -rf {{logs_dir}}; fi');
    // Create cache dir
    run('mkdir -p {{logs_dir}}');
    // Set rights
    run('chmod -R g+w {{logs_dir}}');
})->desc('Create logs dir');

/*
 * Normalize asset timestamps
 */
task('deploy:assets', function () {
    $assets = implode(' ', array_map(function ($asset) {
        return "{{release_path}}/$asset";
    }, get('assets')));

    $time = date('YmdHi.s');

    run("find $assets -exec touch -t $time {} ';' &> /dev/null || true");
})->desc('Normalize asset timestamps');

/*
 * Remove app_dev.php files
 */
task('deploy:clear_controllers', function () {
    run('rm -f {{release_path}}/public/index_*.php');
    run('rm -f {{release_path}}/public/config.php');
})->setPrivate();

after('deploy:update_code', 'deploy:clear_controllers');

/*
 * Main task
 */
task('deploy', [
    'deploy:prepare',
    'deploy:release',
    'deploy:update_code',
    'deploy:create_cache_dir',
    'deploy:create_logs_dir',
    'deploy:shared',
    'deploy:assets',
    'deploy:vendors',
    'deploy:writable',
    'deploy:symlink',
    'cleanup',
])->desc('Deploy App');

after('deploy', 'success');

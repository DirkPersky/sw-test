<?php

namespace Deployer;

require_once 'recipe/common.php';

set('application', 'Shopware 6');
set('allow_anonymous_stats', false);
set('default_timeout', 3600); // Increase the `default_timeout`, if needed, when tasks take longer than the limit.
set('ssh_multiplexing', false);

host('p593040.mittwaldserver.info')
    ->stage('production')
    ->user('p593040')
    ->set('deploy_path', '/html/shopware-deploy') // This is the path, where deployer will create its directory structure
    ->set('writable_mode', 'chmod');

// For more information, please visit the Deployer docs: https://deployer.org/docs/configuration.html#shared_files
set('shared_files', [
    '.env',
]);

// For more information, please visit the Deployer docs: https://deployer.org/docs/configuration.html#shared_dirs
set('shared_dirs', [
    'custom/plugins',
    'config/jwt',
    'files',
    'var/log',
    'public/media',
    'public/thumbnail',
    'public/sitemap',
]);

// For more information, please visit the Deployer docs: https://deployer.org/docs/configuration.html#writable_dirs
set('writable_dirs', [
    'custom/plugins',
    'config/jwt',
    'files',
    'public/bundles',
    'public/css',
    'public/fonts',
    'public/js',
    'public/media',
    'public/sitemap',
    'public/theme',
    'public/thumbnail',
    'var',
]);

// This task uploads the whole workspace to the target server
task('deploy:update_code', static function () {
    upload('.', '{{release_path}}');
});

// This task remotely creates the `install.lock` file on the target server.
task('sw:touch_install_lock', static function () {
    run('cd {{release_path}} && touch install.lock');
});

// This task remotely executes the `theme:compile` console command on the target server.
task('sw:theme:compile', static function () {
    run('cd {{release_path}} && bin/console theme:compile');
});

// This task remotely executes the `cache:clear` console command on the target server.
task('sw:cache:clear', static function () {
    run('cd {{release_path}} && bin/console cache:clear');
});

// This task remotely executes the cache warmup console commands on the target server, so that the first user, who
// visits the website, doesn't have to wait for the cache to be built up.
task('sw:cache:warmup', static function () {
    run('cd {{release_path}} && bin/console cache:warmup');
    run('cd {{release_path}} && bin/console http:cache:warm:up');
});

// This task remotely executes the `database:migrate` console command on the target server.
task('sw:database:migrate', static function () {
    run('cd {{release_path}} && bin/console database:migrate --all');
});

task('sw:plugin:refresh', function () {
    run('cd {{release_path}} && bin/console plugin:refresh');
});

task('sw:plugin:update:all', static function () {
    $plugins = getPlugins();
    foreach ($plugins as $plugin) {
        if ($plugin['Installed'] === 'Yes') {
            writeln("<info>Running plugin update for " . $plugin['Plugin'] . "</info>\n");
            run("cd {{release_path}} && bin/console plugin:update " . $plugin['Plugin']);
        }
    }
});

task('sw:writable:jwt', static function () {
    run('cd {{release_path}} && chmod -R 660 config/jwt/*');
});
/**
 * Grouped SW deploy tasks
 */
task('sw:deploy', [
    'sw:touch_install_lock',
    'sw:database:migrate',
    'sw:plugin:refresh',
    'sw:theme:compile',
    'sw:cache:clear',
    'sw:plugin:update:all',
    'sw:cache:clear',
]);

/**
 * Main task
 */
task('deploy', [
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'sw:deploy',
    'deploy:writable',
    'deploy:clear_paths',
    'sw:cache:warmup',
    'sw:writable:jwt',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'success',
])->desc('Deploy your project');

task('sw-build-without-db:get-remote-config', static function () {
    if (!test('[ -d {{current_path}} ]')) {
        return;
    }
    within('{{deploy_path}}/current', function () {
        run('./bin/console bundle:dump');
        download('{{deploy_path}}/current/var/plugins.json', './var/');

        run('./bin/console theme:dump');
        download('{{deploy_path}}/current/files/theme-config', './files/');

        // Temporary workaround to remove absolute file paths in Shopware <6.4.6.0
        // See https://github.com/shopware/platform/commit/01c8ff86c7d8d3bee1888a26c24c9dc9b4529cbc and https://issues.shopware.com/issues/NEXT-17720
        runLocally('sed -i "" -E \'s/\\\\\/var\\\\\/www\\\\\/htdocs\\\\\/releases\\\\\/[0-9]+\\\\\///g\' files/theme-config/* || true');
    });
});

task('sw-build-without-db:build', static function () {
    runLocally('CI=1 SHOPWARE_SKIP_BUNDLE_DUMP=1 ./bin/build.sh');
});

task('sw-build-without-db', [
    'sw-build-without-db:get-remote-config',
    'sw-build-without-db:build',
]);

after('deploy:failed', 'deploy:unlock');
before('deploy:update_code', 'sw-build-without-db');



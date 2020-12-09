<?php

namespace Deployer;

/**
 * Create a backup from the current database on the server
 *
 * @return void
 */
function dbBackup(): void
{
    if (!test('[ -d {{db_backup_folder}} ]')) {
        run('mkdir -p {{db_backup_folder}}');
    }
    cd('{{db_backup_folder}}');
    run('mysqldump {{db_name}} > dump.sql');
    run('tar cfz {{release_name}}.sql.tgz dump.sql');
    run('rm -f dump.sql');
    writebox('Database backup created', 'blue');

    $keep = get('db_backup_keep_dumps');
    if ($keep === -1) {
        // Keep unlimited dumps.
        return;
    }
    $backups = \explode("\n", run('ls -dt *'));
    $sudo = get('cleanup_use_sudo') ? 'sudo ' : '';
    $runOpts = [];
    if ($sudo) {
        $runOpts['tty'] = get('cleanup_tty', false);
    }
    while ($keep > 0) {
        \array_shift($backups);
        --$keep;
    }

    foreach ($backups as $backup) {
        run("{$sudo}rm -f {{db_backup_folder}}/$backup", $runOpts);
    }
}
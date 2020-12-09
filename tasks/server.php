<?php

namespace Deployer;

desc('Edit the cronjobs');
task('server:cronjob', static function (): void {
    run('EDITOR={{editor}} crontab -e', ['timeout' => null, 'tty' => true]);
})->shallow();

desc('Restart PHP');
task('server:php:restart', static function (): void {
    run('uberspace tools restart php');
});

desc('Create and/or read the deployment key');
task('server:ssh_key', static function (): void {
    if (!test('[ -f ~/.ssh/{{ssh_key}}.pub ]')) {
        // -q Silence key generation
        // -t Set algorithm
        // -b Specifies the number of bits in the key
        // -N Set the passphrase
        // -C Comment for the key
        // -f Specifies name of the file in which to store the created key
        run('cat /dev/zero | ssh-keygen -q -t rsa -b 4096 -N "" -C "$(hostname -f)" -f ~/.ssh/{{ssh_key}}');
    }

    // We dont use `ssh-keygen -y` because we also want to output the comment
    writebox('The public key ({{ssh_key}}.pub) from <strong>' . getRealHostname() . '</strong> is:', 'blue');
    writeln('<info>' . run('cat ~/.ssh/{{ssh_key}}.pub') . '</info>',);
    writeln('');

    $sshKnowHostsFile = '~/.ssh/known_hosts';
    $repoHost = get('repository_url_parts')['host'];

    if (!test("grep -q '$repoHost' $sshKnowHostsFile")) {
        run("ssh-keyscan $repoHost >> $sshKnowHostsFile");
    }
})->shallow();


desc('Set the PHP version on the server');
task('server:php:version', static function (): void {
    \preg_match('/(PHP [\d\.]+)/', run('php -v'), $currentVersion);
    $currentVersion = $currentVersion[0];

    \preg_match('/(\d\.\d)/', $currentVersion, $currentVersionShort);
    $currentVersionShort = $currentVersionShort[0];

    writebox("<strong>Set PHP version on uberspace</strong><br><br><strong>Current version:</strong><br>$currentVersion", 'blue');

    $availableVersions = run('uberspace tools version list php');
    $versionsList = \explode(\PHP_EOL, \str_replace('- ', '', $availableVersions));
    $version = askChoiceln('Please choose the desired version', $versionsList);

    if ($version !== $currentVersionShort) {
        $output = run("uberspace tools version use php $version");
        writebox($output, 'green');
    }
    writebox("As PHP is already set to $version,<br>no configuration was changed", 'blue');
});

desc('Add a domain to uberspace');
task('server:domain:add', static function (): void {
    writebox('Get list of domains, please wait...');
    $currentEntry = run('uberspace web domain list');
    $realHostname = getRealHostname();
    writebox("<strong>Add a domain to uberspace</strong>
If you have multiple domains, you will be asked
after every entry if you wand to add another domain.

<strong>Current entry:</strong>
$currentEntry

To cancel enter <strong>exit</strong> as answer", 'blue');
    // Check if the realHostname seems to have a subdomain
    $wwwDomain = 'www.' . $realHostname;
    $defaultDomain = \substr_count($realHostname, '.') > 1 ? $realHostname : $wwwDomain;
    $suggestions = [$realHostname, $wwwDomain];
    $firstDomain = askDomain('Please enter the domain', $defaultDomain, $suggestions);
    if ($firstDomain === 'exit') {
        return;
    }
    $domains = [
        $firstDomain
    ];
    writeln('');
    while ($domain = askDomain('Please enter another domain or press enter to continue', null, $suggestions)) {
        if ($domain === 'exit') {
            return;
        }
        if ($domain) {
            $domains[] = $domain;
        }
        writeln('');
    }
    $outputDomains = \implode("\n", $domains);
    $ip = '';
    writebox('Adding domains, please wait...');
    foreach ($domains as $domain) {
        $ip = run("uberspace web domain add $domain");
    }
    writebox("<strong>Following entries are added:</strong><br><br>$outputDomains<br><br>$ip", 'green');
})->shallow();

desc('Remove a domain from uberspace');
task('server:domain:remove', static function (): void {
    writebox('Get list of domains, please wait...');
    $currentEntry = run('uberspace web domain list');
    writebox("<strong>Remove a domain from uberspace</strong>
If you have multiple domains, you will be asked
after every entry if you wand to remove another domain.

<strong>Current entry:</strong>
$currentEntry

To finish the setup, press enter or choose the last entry", 'blue');

    $currentEntriesArray = \explode(\PHP_EOL, $currentEntry);
    $currentEntriesArray[] = 'Finish setup';
    $domains = [];

    while ($domain = askChoiceln('Please choose the domain you want to remove', $currentEntriesArray, sizeof($currentEntriesArray) - 1)) {
        if ($domain === 'Finish setup') {
            break;
        }
        $currentEntriesArray = \array_values(\array_diff($currentEntriesArray, [$domain]));
        $domains[] = $domain;
    }
    if (sizeof($domains)) {
        $outputDomains = \implode("\n", $domains);
        writebox('Removing domains, please wait...');
        foreach ($domains as $domain) {
            run("uberspace web domain del $domain");
        }
        writebox('<strong>Following entries are removed:</strong><br><br>' . $outputDomains, 'green');
    } else {
        writebox('<strong>No Domains are removed</strong>', 'red');
    }
})->shallow();


desc('List all domains and subdomains');
task('server:domain:list', static function (): void {
    writebox('Get list of domains, please wait...');
    $currentEntry = run('uberspace web domain list');
    writebox("<strong>Registered domains on the uberspace account \"{{user}}\"</strong>

$currentEntry
", 'blue');
});
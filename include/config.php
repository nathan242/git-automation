<?php

$dbhost = '127.0.0.1';
$dbuser = 'git_auto';
$dbpass = 'password';
$dbdb = 'git_auto';

$db = new mdb($dbhost, $dbuser, $dbpass, $dbdb);

$deployuser = 'git-auto-user';
$scriptpath = '/var/www/html/git-automation/scripts';

$gitserver = 'server.local';
$gitpath = '/home/git';
$gituser = 'git-auto';
$gitpass = 'password';

$shellregex = '/[<>\ ]+|^-/';

?>

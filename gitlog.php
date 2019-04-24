<?php
//require_once './include/error.php';
require_once './include/mdb.php';
require_once './include/config.php';

echo '<!DOCTYPE html><head><title>GIT Log</title></head>';
echo '<body>';

if (isset($_GET['session'])) {
    echo '<p><a href="?">&lt;&lt;BACK</a></p>';
    $db->prepared_query('SELECT `session`, `user`, `action` FROM `sessions` WHERE `session` = ?', array('s'), array($_GET['session']));
    if (isset($db->result[0])) {
        $sessdata = $db->result[0];
    } else {
        echo '<p>Session not found!</p>';
        exit();
    }
    echo '<table>';
    echo '<tr><td>Session:</td><td>'.$sessdata['session'].'</td></tr>';
    echo '<tr><td>User:</td><td>'.$sessdata['user'].'</td></tr>';
    echo '<tr><td>Action:</td><td>'.$sessdata['action'].'</td></tr>';
    echo '</table>';
    echo '<br>';

    $db->prepared_query('SELECT `log`.`id`, `log`.`timestamp`, `log`.`action`, `log`.`data` FROM `log` INNER JOIN `sessions` ON `sessions`.`id` = `log`.`session` WHERE `sessions`.`session` = ? ORDER BY `log`.`timestamp` ASC', array('s'), array($_GET['session']));
    if (isset($db->result[0])) {
        $data = $db->result;
    } else {
        echo '<p>Log data not found!</p>';
        exit();
    }

    echo '<table border="1"><tr><th>Id</th><th>Timestamp</th><th>Action</th><th>Data</th></tr>';
    foreach ($data as $r) {
        echo '<tr>';
        echo '<td>'.$r['id'].'</td><td>'.$r['timestamp'].'</td><td>'.$r['action'].'</td><td><pre>'.$r['data'].'</pre></td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<h1>GIT Logs:</h1>';
    $db->query('SELECT `sessions`.`session`, `sessions`.`user`, `sessions`.`action`, `log`.`timestamp` FROM `sessions` INNER JOIN `log` ON `log`.`session` = `sessions`.`id` GROUP BY `sessions`.`session` ORDER BY `sessions`.`session` DESC, `log`.`timestamp` ASC');
    if (isset($db->result[0])) {
        $data = $db->result;
    } else {
        echo '<p>No logs.</p></body></html>';
        exit();
    }

    echo '<table border="1"><tr><th>Session</th><th>User</th><th>Action</th><th>Timestamp</th></tr>';
    foreach ($data as $r) {
        echo '<tr>';
        echo '<td><a href="?session='.$r['session'].'">'.$r['session'].'</a></td><td>'.$r['user'].'</td><td>'.$r['action'].'</td><td>'.$r['timestamp'].'</td>';
        echo '</tr>';
    }
    echo '</table>';
}

echo '</body>';
echo '</html>';
?>

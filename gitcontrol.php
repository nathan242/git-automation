<?php

require_once './include/error.php';
require_once './include/mdb.php';
require_once './include/user.php';
require_once './include/session.php';
require_once './include/log.php';
require_once './include/config.php';

// AJAX

if (isset($_GET['ajax_get_branches']) && preg_match($shellregex, $_GET['ajax_get_branches']) === 0) {
    $branchselect = '<option>&lt;&lt;SELECT&gt;&gt;</option>';
    $repolist = shell_exec('sshpass -p'.$gitpass.' ssh '.$gituser.'@'.$gitserver.' git-auto-list-repo-basic');
    $repoarray = array();
    foreach(preg_split("/((\r?\n)|(\r\n?))/", $repolist) as $line) {
        $repoarray[] = $line;
    }
    if (in_array($_GET['ajax_get_branches'], $repoarray)) {
        $branchlist = shell_exec('sshpass -p'.$gitpass.' ssh '.$gituser.'@'.$gitserver.' git-auto-list-branches-basic '.$_GET['ajax_get_branches']);
        foreach(preg_split("/((\r?\n)|(\r\n?))/", $branchlist) as $line) {
            if (!empty($line)) {
                $branchselect .= '<option>'.$line.'</option>';
            }
        }
    }
    exit($branchselect);
}

if (isset($_GET['ajax_get_paths'])) {
    $pathselect = '<option>&lt;&lt;SELECT&gt;&gt;</option>';
    $db->prepared_query('SELECT `path` FROM `destinations` WHERE `server`=?', array('s'), array($_GET['ajax_get_paths']));
    if (isset($db->result[0])) {
        foreach ($db->result as $p) {
            $pathselect .= '<option>'.$p['path'].'</option>';
        }
    }
    exit($pathselect);
}

if (isset($_GET['ajax_get_deploy_config']) && preg_match($shellregex, $_GET['ajax_get_deploy_config']) === 0 && isset($_GET['branch']) && preg_match($shellregex, $_GET['branch']) === 0) {
    $deployconfig = '';
    $repolist = shell_exec('sshpass -p'.$gitpass.' ssh '.$gituser.'@'.$gitserver.' git-auto-list-repo-basic');
    $repoarray = array();
    foreach(preg_split("/((\r?\n)|(\r\n?))/", $repolist) as $line) {
        $repoarray[] = $line;
    }
    if (in_array($_GET['ajax_get_deploy_config'], $repoarray)) {
        $deployconfig = shell_exec('sshpass -p'.$gitpass.' ssh '.$gituser.'@'.$gitserver.' git-auto-show-file '.$_GET['ajax_get_deploy_config'].' '.$_GET['branch'].' git-deploy.config');
    }
    exit($deployconfig);
}

if (isset($_GET['ajax_get_git_log']) && preg_match($shellregex, $_GET['ajax_get_git_log']) === 0 && isset($_GET['branch']) && preg_match($shellregex, $_GET['branch']) === 0) {
    $gitlog = '';
    $repolist = shell_exec('sshpass -p'.$gitpass.' ssh '.$gituser.'@'.$gitserver.' git-auto-list-repo-basic');
    $repoarray = array();
    foreach(preg_split("/((\r?\n)|(\r\n?))/", $repolist) as $line) {
        $repoarray[] = $line;
    }
    if (in_array($_GET['ajax_get_git_log'], $repoarray)) {
        $gitlog = shell_exec('sshpass -p'.$gitpass.' ssh '.$gituser.'@'.$gitserver.' git-auto-show-log-short '.$_GET['ajax_get_git_log'].' '.$_GET['branch']);
    }
    exit($gitlog);
}


// PROCESS

if (isset($_POST['runactions']) && $_POST['runactions'] == 1 && isset($_POST['repo']) && !empty($_POST['repo']) && isset($_POST['branch']) && !empty($_POST['branch']) && isset($_POST['server']) && !empty($_POST['server']) && isset($_POST['path']) && !empty($_POST['path'])) {
    //echo '<!DOCTYPE html><head><title>GIT Control</title></head>';
    //echo '<body>';

    // Check inputs are safe
    if (isset($_POST['revert'])) {
        if (preg_match($shellregex, $_POST['server']) !== 0 || preg_match($shellregex, $_POST['path']) !== 0) {
            echo '<p>ERROR: Invalid parameters</p>';
            //echo '</body>';
            //echo '</html>';
            exit();
        }
    } elseif (preg_match($shellregex, $_POST['repo']) !== 0 || preg_match($shellregex, $_POST['branch']) !== 0 || preg_match($shellregex, $_POST['server']) !== 0 || preg_match($shellregex, $_POST['path']) !== 0) {
        echo '<p>ERROR: Invalid parameters</p>';
        //echo '</body>';
        //echo '</html>';
        exit();
    }

    echo '<p>Checking parameters...</p>';
    if (!isset($_POST['revert'])) {
        $repolist = shell_exec('sshpass -p'.$gitpass.' ssh '.$gituser.'@'.$gitserver.' git-auto-list-repo-basic');
        $repoarray = array();
        foreach(preg_split("/((\r?\n)|(\r\n?))/", $repolist) as $line) {
            $repoarray[] = $line;
        }
    }
    if (isset($_POST['revert']) || in_array($_POST['repo'], $repoarray)) {
        if (!isset($_POST['revert'])) {
            $branchlist = shell_exec('sshpass -p'.$gitpass.' ssh '.$gituser.'@'.$gitserver.' git-auto-list-branches-basic '.$_POST['repo']);
            $brancharray = array();
            foreach(preg_split("/((\r?\n)|(\r\n?))/", $branchlist) as $line) {
                $brancharray[] = $line;
            }
        }
        if (isset($_POST['revert']) || in_array($_POST['branch'], $brancharray)) {
            //$db->prepared_query('SELECT COUNT(*) AS `count` FROM `destinations` WHERE `server`=? AND `path`=?', array('s','s'), array($_POST['server'], $_POST['path']));
            //if (isset($db->result[0]) && $db->result[0]['count'] > 0) {
            $db->prepared_query('SELECT `permissions` FROM `destinations` WHERE `server`=? AND `path`=?', array('s','s'), array($_POST['server'], $_POST['path']));
            if (isset($db->result[0])) {
                $permission = $db->result[0]['permissions'];
                $user = new user($db, $_SERVER['REMOTE_USER']);
                if ($user->check_permission($permission) === true) {
                    $gitctlcmd = '';
                    if (isset($_POST['keepbackups']) && preg_match('/^[0-9]+$/', $_POST['keepbackups'])) {
                        $gitctlcmd = ' -k '.(int)$_POST['keepbackups'];
                    }
                    if (isset($_POST['revert'])) {
                        $sessionaction = 'REVERTING: '.$_POST['server'].' - '.$_POST['path'];
                    } else {
                        $sessionaction = 'DEPLOYING: '.$_POST['repo'].':'.$_POST['branch'].' -> '.$_POST['server'].':'.$_POST['path'];
                    }
                    if (!session::active_sessions_exist($db)) {
                        if (isset($_SERVER['REMOTE_USER']) && $_SERVER['REMOTE_USER'] != '') {
                            $session = new session($db, $sessionaction, $_SERVER['REMOTE_USER']);
                        } else {
                            $session = new session($db, $sessionaction);
                        }
                    } else {
                        echo '<p>ERROR: Session already active</p>';
                        exit();
                    }
                    $log = new log($db, $session->get_session_id());
                    if (isset($_POST['revert'])) {
                        echo '<p>Reverting...</p>';
                        echo '<p><pre>'.$log->log_and_return('scp '.$scriptpath.'/git-revert '.$deployuser.'@'.$_POST['server'].':/home/'.$deployuser.'/ 2>&1', shell_exec('scp '.$scriptpath.'/git-revert '.$deployuser.'@'.$_POST['server'].':/home/'.$deployuser.'/ 2>&1')).'</pre></p>';
                        echo '<p><pre>'.$log->log_and_return('ssh '.$deployuser.'@'.$_POST['server'].' "chmod 700 /home/'.$deployuser.'/git-revert" 2>&1', shell_exec('ssh '.$deployuser.'@'.$_POST['server'].' "chmod 700 /home/'.$deployuser.'/git-revert" 2>&1')).'</pre></p>';
                        echo '<p><pre>'.$log->log_and_return('ssh '.$deployuser.'@'.$_POST['server'].' "sudo /home/'.$deployuser.'/git-revert '.$_POST['path'].'" 2>&1', shell_exec('ssh '.$deployuser.'@'.$_POST['server'].' "sudo /home/'.$deployuser.'/git-revert '.$_POST['path'].'" 2>&1')).'</pre></p>';
                        echo '<p><pre>'.$log->log_and_return('ssh '.$deployuser.'@'.$_POST['server'].' "rm /home/'.$deployuser.'/git-revert" 2>&1', shell_exec('ssh '.$deployuser.'@'.$_POST['server'].' "rm /home/'.$deployuser.'/git-revert" 2>&1')).'</pre></p>';
                    } else {
                        echo '<p>Processing...</p>';
                        echo '<p><pre>'.$log->log_and_return('scp '.$scriptpath.'/gitctl '.$deployuser.'@'.$_POST['server'].':/home/'.$deployuser.'/ 2>&1', shell_exec('scp '.$scriptpath.'/gitctl '.$deployuser.'@'.$_POST['server'].':/home/'.$deployuser.'/ 2>&1')).'</pre></p>';
                        echo '<p><pre>'.$log->log_and_return('scp '.$scriptpath.'/git-prepare '.$deployuser.'@'.$_POST['server'].':/home/'.$deployuser.'/ 2>&1', shell_exec('scp '.$scriptpath.'/git-prepare '.$deployuser.'@'.$_POST['server'].':/home/'.$deployuser.'/ 2>&1')).'</pre></p>';
                        echo '<p><pre>'.$log->log_and_return('scp '.$scriptpath.'/pass '.$deployuser.'@'.$_POST['server'].':/home/'.$deployuser.'/ 2>&1', shell_exec('scp '.$scriptpath.'/pass '.$deployuser.'@'.$_POST['server'].':/home/'.$deployuser.'/ 2>&1')).'</pre></p>';
                        echo '<p><pre>'.$log->log_and_return('ssh '.$deployuser.'@'.$_POST['server'].' "chmod 700 /home/'.$deployuser.'/gitctl" 2>&1', shell_exec('ssh '.$deployuser.'@'.$_POST['server'].' "chmod 700 /home/'.$deployuser.'/gitctl" 2>&1')).'</pre></p>';
                        echo '<p><pre>'.$log->log_and_return('ssh '.$deployuser.'@'.$_POST['server'].' "chmod 700 /home/'.$deployuser.'/git-prepare" 2>&1', shell_exec('ssh '.$deployuser.'@'.$_POST['server'].' "chmod 700 /home/'.$deployuser.'/git-prepare" 2>&1')).'</pre></p>';
                        echo '<p><pre>'.$log->log_and_return('ssh '.$deployuser.'@'.$_POST['server'].' "mkdir -p /tmp/git-tmp" 2>&1', shell_exec('ssh '.$deployuser.'@'.$_POST['server'].' "mkdir -p /tmp/git-tmp" 2>&1')).'</pre></p>';
                        echo '<p><pre>'.$log->log_and_return('ssh '.$deployuser.'@'.$_POST['server'].' "sudo /home/'.$deployuser.'/gitctl -w /tmp/git-tmp -s '.$gituser.'@'.$gitserver.':'.$gitpath.'/'.$_POST['repo'].' -b '.$_POST['branch'].' -d '.$_POST['path'].' -c /home/'.$deployuser.'/git-prepare -p /home/'.$deployuser.'/pass '.$gitctlcmd.'" 2>&1', shell_exec('ssh '.$deployuser.'@'.$_POST['server'].' "sudo /home/'.$deployuser.'/gitctl -w /tmp/git-tmp -s '.$gituser.'@'.$gitserver.':'.$gitpath.'/'.$_POST['repo'].' -b '.$_POST['branch'].' -d '.$_POST['path'].' -c /home/'.$deployuser.'/git-prepare -p /home/'.$deployuser.'/pass '.$gitctlcmd.'" 2>&1')).'</pre></p>';
                        echo '<p><pre>'.$log->log_and_return('ssh '.$deployuser.'@'.$_POST['server'].' "rm /home/'.$deployuser.'/gitctl /home/'.$deployuser.'/git-prepare /home/'.$deployuser.'/pass" 2>&1', shell_exec('ssh '.$deployuser.'@'.$_POST['server'].' "rm /home/'.$deployuser.'/gitctl /home/'.$deployuser.'/git-prepare /home/'.$deployuser.'/pass" 2>&1')).'</pre></p>';
                    }
                } else {
                    echo '<p>Permission Denied!</p>';
                }
            } else {
                echo '<p>ERROR: Server/path not recognized.</p>';
            }
        } else {
            echo '<p>ERROR: Branch not recognized.</p>';
        }
    } else {
        echo '<p>ERROR: Repository not recognized.</p>';
    }

    //echo '<p><a href="gitcontrol.php">&lt;&lt;RETURN</a></p>';

    //echo '</body>';
    //echo '</html>';
    exit();
}


// MAIN PAGE

echo '<!DOCTYPE html><head><title>GIT Control</title>';
echo '<script>
    function getBranches(repo) {
        document.getElementById("outputwindow").innerHTML = "";
        if (repo == "<<SELECT>>" || repo == "") {
            document.getElementById("branchsel").innerHTML = "<option>&lt;&lt;SELECT&gt;&gt;</option>";
            return;
        }
	document.getElementById("branchsel").style.backgroundColor = "#FFFF00";
        xmlHttpBranch = new XMLHttpRequest();
        xmlHttpBranch.open("GET", "?ajax_get_branches="+repo);
        xmlHttpBranch.onreadystatechange = function() {
            if (xmlHttpBranch.readyState === XMLHttpRequest.DONE && xmlHttpBranch.status === 200) {
                document.getElementById("branchsel").innerHTML = xmlHttpBranch.responseText;
                document.getElementById("branchsel").style.backgroundColor = "#FFFFFF";
            }
        };
        xmlHttpBranch.send(null);
    }
    function getPaths(server) {
        if (server == "<<SELECT>>" || server == "") {
            document.getElementById("pathsel").innerHTML = "<option>&lt;&lt;SELECT&gt;&gt;</option>";
            return;
        }
        document.getElementById("pathsel").style.backgroundColor = "#FFFF00";
        xmlHttpServer = new XMLHttpRequest();
        xmlHttpServer.open("GET", "?ajax_get_paths="+server);
        xmlHttpServer.onreadystatechange = function() {
            if (xmlHttpServer.readyState === XMLHttpRequest.DONE && xmlHttpServer.status === 200) {
                document.getElementById("pathsel").innerHTML = xmlHttpServer.responseText;
                document.getElementById("pathsel").style.backgroundColor = "#FFFFFF";
            }
        };
        xmlHttpServer.send(null);
    }
    function getDeployConfig(branch) {
        if (branch == "<<SELECT>>" || branch == "") {
            document.getElementById("outputwindow").innerHTML = "";
            return;
        }
        document.getElementById("outputwindow").innerHTML = "<pre>git-deploy.config:\n</pre><pre id=\'deployconfig\'>Loading...\n\n</pre><pre>Last commit:\n</pre><pre id=\'lastcommit\'>Loading...</pre>";
        var repo = document.getElementById("reposel").value;
        xmlHttpConfig = new XMLHttpRequest();
        xmlHttpConfig.open("GET", "?ajax_get_deploy_config="+repo+"&branch="+branch);
        xmlHttpConfig.onreadystatechange = function() {
            if (xmlHttpConfig.readyState === XMLHttpRequest.DONE && xmlHttpConfig.status === 200) {
                var deployconfig = xmlHttpConfig.responseText;
                if (deployconfig.length > 0) {
                    document.getElementById("deployconfig").innerHTML = deployconfig+"\n\n";
                } else {
                    document.getElementById("deployconfig").innerHTML = "No config file.\n\n";
                }
            }
        };
        xmlHttpConfig.send(null);
        xmlHttpGitlog = new XMLHttpRequest();
        xmlHttpGitlog.open("GET", "?ajax_get_git_log="+repo+"&branch="+branch);
        xmlHttpGitlog.onreadystatechange = function() {
            if (xmlHttpGitlog.readyState === XMLHttpRequest.DONE && xmlHttpGitlog.status === 200) {
                document.getElementById("lastcommit").innerHTML = xmlHttpGitlog.responseText;
            }
        };
        xmlHttpGitlog.send(null);
    }
    function submit(type) {
        var status = true;

        var outputwindow = document.getElementById("outputwindow");

        var repo = document.getElementById("reposel");
        var branch = document.getElementById("branchsel");
        var server = document.getElementById("serversel");
        var path = document.getElementById("pathsel");
        var keepbackups = document.getElementById("keepbackups");
        var submit = document.getElementById("submit");
        var revert = document.getElementById("revert");
        var runactions = document.getElementById("runactions");

        if (type == 1) {
            if (repo.value == "<<SELECT>>" || repo.value == "") { repo.style.backgroundColor = "#FF0000"; status = false; } else { repo.style.backgroundColor = "#FFFFFF"; }
            if (branch.value == "<<SELECT>>" || branch.value == "") { branch.style.backgroundColor = "#FF0000"; status = false; } else { branch.style.backgroundColor = "#FFFFFF"; }
        }
        if (server.value == "<<SELECT>>" || server.value == "") { server.style.backgroundColor = "#FF0000"; status = false; } else { server.style.backgroundColor = "#FFFFFF"; }
        if (path.value == "<<SELECT>>" || path.value == "") { path.style.backgroundColor = "#FF0000"; status = false; } else { path.style.backgroundColor = "#FFFFFF"; }
        if (keepbackups == null || keepbackups.value == null || keepbackups.value == "") { status = false; }

        if (status === true) {
            if (type == 1) {
                if (confirm("You are about to DEPLOY the "+branch.value+" branch from "+repo.value+" to "+server.value+":"+path.value+"\nAre you sure you wish to proceed?") != true) {
                    status = false;
                }
            } else if (type == 2) {
                if (confirm("You are about to REVERT the destination at "+server.value+":"+path.value+"\nAre you sure you wish to proceed?") != true) {
                    status = false;
                }
            } else {
                status = false;
            }
        }

        if (status === true) {
            repo.style.backgroundColor = "#FFFFFF";
            branch.style.backgroundColor = "#FFFFFF";
            server.style.backgroundColor = "#FFFFFF";
            path.style.backgroundColor = "#FFFFFF";
            repo.disabled = true;
            branch.disabled = true;
            server.disabled = true;
            path.disabled = true;
            submit.disabled = true;
            revert.disabled = true;

            outputwindow.innerHTML = "<pre>Processing...</pre>";

            formData = new FormData();
            formData.append("repo", repo.options[repo.selectedIndex].text);
            formData.append("branch", branch.options[branch.selectedIndex].text);
            formData.append("server", server.options[server.selectedIndex].text);
            formData.append("path", path.options[path.selectedIndex].text);
            formData.append("keepbackups", keepbackups.value);
            if (type == 2) { formData.append("revert", ""); }
            formData.append("runactions", runactions.value);
            xmlHttp = new XMLHttpRequest();
            xmlHttp.open("POST", "");
            xmlHttp.onreadystatechange = function() {
               if (xmlHttp.readyState === XMLHttpRequest.DONE && xmlHttp.status === 200) {
                   outputwindow.innerHTML = xmlHttp.responseText;
                   repo.disabled = false;
                   branch.disabled = false;
                   server.disabled = false;
                   path.disabled = false;
                   submit.disabled = false;
                   revert.disabled = false;
               }
            };
            xmlHttp.send(formData);
       }
    }
</script>
<style>
    table {
        height: 100%;
        width: 100%;
    }
    td {
        border: solid;
        height: 100%;
        padding: 10px;
    }
    .controls {
        width: 30%;
    }
    .data {
        vertical-align: top;
    }
</style>
';
/*
    function validate() {
        var status = true;
        var elements = document.forms["gitform"].getElementsByTagName("select");
        for (var e = 0; e < elements.length; e++) {
            elements[e].style.backgroundColor = "#FFFFFF";
            if (elements[e].options[elements[e].selectedIndex].text == "<<SELECT>>" || elements[e].options[elements[e].selectedIndex].text == "") {
                status = false;
                elements[e].style.backgroundColor = "#FF0000";
            }
        }
        return status;
    }
*/
echo '</head><body>';

echo '<table>';
echo '<tr><td colspan="2"><center>GIT CONTROL</center></td></tr>';
echo '<tr>';
echo '<td class="controls">';
echo '<p><pre>'.shell_exec('sshpass -p'.$gitpass.' ssh '.$gituser.'@'.$gitserver.' list-repo').'</pre></p>';
echo '</td>';

echo '<td rowspan="2" class="data">';
echo '<p id="outputwindow"></p>';
echo '</td>';
echo '</tr>';

echo '<tr>';
echo '<td class="controls">';
$repolist = shell_exec('sshpass -p'.$gitpass.' ssh '.$gituser.'@'.$gitserver.' git-auto-list-repo-basic');

$reposelect = '';
foreach(preg_split("/((\r?\n)|(\r\n?))/", $repolist) as $line) {
    if (!empty($line)) { $reposelect .= '<option>'.$line.'</option>'; }
}

//echo '<form name="gitform" action="" method="post">';
echo '<p>SOURCE:</p>';
echo '<p>';
echo 'REPOSITORY: <select name="repo" id="reposel" onchange="getBranches(this.value);"><option>&lt;&lt;SELECT&gt;&gt;</option>'.$reposelect.'</select>';
echo ' BRANCH: <select name="branch" id="branchsel" onchange="getDeployConfig(this.value);"><option>&lt;&lt;SELECT&gt;&gt;</option></select>';
echo '</p>';

echo '<hr>';

$db->query('SELECT DISTINCT `server` FROM `destinations`');
$serverselect = '';
if (isset($db->result[0])) {
    foreach ($db->result as $s) {
        $serverselect .= '<option>'.$s['server'].'</option>';
    }
}

echo '<p>DESTINATION:</p>';
echo '<p>';
echo 'SERVER: <select name="server" id="serversel" onchange="getPaths(this.value);"><option>&lt;&lt;SELECT&gt;&gt;</option>'.$serverselect.'</select>';
echo ' PATH: <select name="path" id="pathsel"><option>&lt;&lt;SELECT&gt;&gt;</option></select>';
echo '</p>';

echo '<hr>';

//echo '<!--<p>KEEP BACKUPS: <select name="keepbackups" id="keepbackups"><option>0</option><option selected>1</option><option>2</option><option>3</option><option>4</option></select></p>-->';
echo '<input type="hidden" name="keepbackups" id="keepbackups" value="1">';

echo '<hr>';

echo '<input type="hidden" id="runactions" name="runactions" value="1">';
echo '<input type="button" id="submit" value="DEPLOY" onclick="submit(1);" style="width: 100%; margin-bottom: 10px;">';
echo '<input type="button" id="revert" name="revert" value="REVERT" onclick="submit(2);" style="width: 100%">';
//echo '<input type="submit" value="DEPLOY" onclick="return validate();" style="width: 100%; margin-bottom: 10px;">';
//echo '<input type="submit" id="revert" name="revert" value="REVERT" onclick="return validate();" style="width: 100%">';
//echo '</form>';

echo '</td>';
echo '</tr>';

echo '</table>';

echo '</body>';
echo '</html>';

?>

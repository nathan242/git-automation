<?php
class session {

    private $db;
    private $sessionid = 0;
    private $user;
    private $maxsessions = 200;

    function __construct(&$db, $action, $user = "None") {
        $this->db = $db;
        $this->user = $user;
        $sesscount = 0;
        while ($this->sessionid == 0) {
            $sessionid = date('YmdHis').$sesscount++;
            if (!$this->db->query('SELECT COUNT(*) AS `total` FROM `sessions` WHERE `session`="'.$sessionid.'"')) {
                return false;
            }
            if (isset($this->db->result[0]) && $this->db->result[0]['total'] == 0) {
                $this->sessionid = $sessionid;
            }
        }
	if (!$this->db->query('INSERT INTO `sessions` (`session`, `user`, `action`) VALUES ("'.$this->sessionid.'", "'.$this->user.'", "'.$action.'")')) {
            return false;
        }
    }

    public static function active_sessions_exist(&$db) {
        if ($db->query('SELECT `id` FROM `sessions` WHERE `active` = 1') && isset($db->result[0])) {
            return true;
        } else {
            return false;
        }
    }

    public function get_session_id() {
        if ($this->db->query('SELECT `id` FROM `sessions` WHERE `session`="'.$this->sessionid.'"') && isset($this->db->result[0])) {
            return $this->db->result[0]['id'];
        } else {
            return false;
        }
    }

    private function remove_old_sessions() {
        $this->db->query('SELECT COUNT(*) AS `count` FROM `sessions`');
        if (isset($this->db->result[0]) && $this->db->result[0]['count'] > $this->maxsessions) {
            $remove = $this->db->result[0]['count'] - $this->maxsessions;
            $this->db->query('SELECT `id` FROM `sessions` ORDER BY `session` ASC LIMIT '.$remove);
            if (isset($this->db->result[0])) {
                $sessions = array();
                foreach ($this->db->result as $r) {
                    $sessions[] = $r['id'];
                }
                $this->db->query('DELETE FROM `sessions` WHERE `id` IN ('.implode(',', $sessions).')');
                $this->db->query('DELETE FROM `log` WHERE `session` IN ('.implode(',', $sessions).')');
            }
        }
    }

    function __destruct() {
        $this->db->query('UPDATE `sessions` SET `active`=0 WHERE `session`="'.$this->sessionid.'"');
        $this->remove_old_sessions();
    }
}
?>

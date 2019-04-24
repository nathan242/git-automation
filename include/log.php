<?php
class log {

    private $db;
    private $sessionid;

    function __construct(&$db, $sessionid) {
        $this->db = $db;
        $this->sessionid = $sessionid;
    }

    public function log_and_return($action, $text) {
        if ($this->db->prepared_query('INSERT INTO `log` (`session`, `action`, `data`) VALUES (?, ?, ?)', array('s', 's', 's'), array($this->sessionid, $action, $text))) {
            return $text;
        } else {
            return '[LOG ERROR]:'.$text;
        }
    }
}
?>

<?php
class user {

    private $db;
    private $userid = false;
    private $permissions;

    function __construct(&$db, $username) {
        $this->db = $db;
        $this->db->prepared_query('SELECT `id`, `permissions` FROM `users` WHERE `username`=?', array('s'), array($username));
        if (isset($this->db->result[0])) {
            $this->userid = $this->db->result[0]['id'];
            $this->permissions = $this->db->result[0]['permissions'];
        }
    }

    public function check_permission($permission) {
        if ($this->userid !== false) {
            if ((($this->permissions & $permission) == $permission) || (($this->permissions & 128) == 128 )) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

}
?>

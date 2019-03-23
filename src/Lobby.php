<?php
namespace CSGOClubLobby;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use \stdClass;

class Lobby implements MessageComponentInterface {

    protected $clients;
    protected $clients_info;
    protected $csgo_url = 'http://csgoclub.tk/';
    protected $max_clients = 8;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->clients_info = array();
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        if(count($this->clients_info) > $this->max_clients) {
            $from->send("El lobby está completo.");
            $from->close();
            return false;
        }

        $user = $this->get_user_by_token($msg);

        if(!$user) {
            $from->send("Usuario inválido.");
            $from->close();
            return false;
        }

        if($this->is_user_already_in_lobby($user)) {
            $from->send("No puedes agregarte a la sala más de una vez.");
            $from->close();
            return false;
        }

        $user->resource_id = $from->resourceId;

        $this->clients_info[] = $user;

        $this->update_clients_list();
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        $this->remove_user($conn->resourceId);
        $this->update_clients_list();
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $conn->close();
    }

    private function get_user_by_token($user_token) {
        $check_user_url = $this->csgo_url . 'lobbyuserinfo/get_user_by_token/' . $user_token;
        $result = file_get_contents($check_user_url);

        if(strlen($result) == 0) {
            return false;
        }

        if($result == 'Invalid user') {
            return false;
        }

        return json_decode($result);
    }

    private function is_user_already_in_lobby(&$user) {
        foreach($this->clients_info as $aUser) {
            if($user->user_token == $aUser->user_token) {
                return true;
            }

            if($user->email == $aUser->email) {
                return true;
            }

            if($user->nickname == $aUser->nickname) {
                return true;
            }
        }

        return false;
    }

    private function remove_user($resource_id) {
        $the_key = null;
        foreach($this->clients_info as $key => $user) {
            if($user->resource_id == $resource_id) {
                $the_key = $key;
            }
        }

        if(!is_null($the_key)) {
            unset($this->clients_info[$the_key]);
        }
    }

    private function update_clients_list() {
        $list = array();
        foreach($this->clients_info as $user) {
            $list[] = $user->nickname;
        }

        $message = json_encode($list);

        foreach($this->clients as $value) {
            $client = $this->clients->current();
            $client->send($message);
        }
    }

}
?>
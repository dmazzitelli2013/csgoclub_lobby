<?php
namespace CSGOClubLobby;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use \stdClass;

class Lobby implements MessageComponentInterface {

    protected $users;
    protected $csgo_url = 'http://localhost/csgoclub/';
    protected $max_clients = 16;

    public function __construct() {
        $this->users = array();
    }

    public function onOpen(ConnectionInterface $conn) {}

    public function onMessage(ConnectionInterface $from, $msg) {
        if(count($this->users) > $this->max_clients) {
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

        $user->connection = $from;

        $this->users[] = $user;

        $this->update_users_list();
    }

    public function onClose(ConnectionInterface $conn) {
        $this->remove_user($conn->resourceId);
        $this->update_users_list();
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
        foreach($this->users as $aUser) {
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
        foreach($this->users as $key => $user) {
            if($user->connection->resourceId == $resource_id) {
                $the_key = $key;
            }
        }

        if(!is_null($the_key)) {
            unset($this->users[$the_key]);
        }
    }

    private function update_users_list() {
        $list = array();
        $all_users = $this->users;

        foreach($all_users as $user) {
            $list[] = $user->nickname;
        }

        $message = json_encode($list);

        foreach($all_users as $user) {
            $user->connection->send($message);
        }

        if(count($this->users) >= 4) {
            $this->create_random_match_2vs2();
        }
    }

    private function create_random_match_2vs2() {
        $players = array_slice($this->users, 0, 4);
        $user_ids = $players[0]->id . '-' . $players[1]->id . '-' . $players[2]->id . '-' . $players[3]->id;
        
        $match_making_url = $this->csgo_url . 'match/generate_random_2vs2_match/' . $user_ids;
        $result = file_get_contents($match_making_url);
        
        $message = 'Redirect:' . $result;
        foreach($players as $player) {
            $player->connection->send($message);
            $player->connection->close();
        }
    }

}
?>
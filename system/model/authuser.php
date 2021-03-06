<?php
    namespace Model;

    /**
     * This model is used as model for auth
     */
    class AuthUser
    {
        public function getId($username)
        {
            $result = $this->db->table('user')->where('name', $username)->get('id');
            if ($result->isError())
            {
                return -1;
            }
            return $result->first('id');
        }

        public function hasUser($username='')
        {
            $result = null;
            if ($username === '')
            {
                $result = $this->db->table('user')->limit(0,1)->get();
            }
            else
            {
                $result = $this->db->table('user')->where('name', $username)->limit(0,1)->get();
            }
            return !$result->isEmpty();
        }

        public function insert($username, $password)
        {
            $hasUser = $this->hasUser();
            $hashPass = page()->encryption->hashPassword($password);
            $this->db->table('user')->insert([NULL,$username, $hashPass]);

            $id = $this->getId($username);
            if (!$hasUser)
            {
                $this->setOption($id, 'privilege', 'admin');
            }
            else
            {
                $this->setOption($id, 'privilege', 'user');
            }
        }

        public function setOption($idUser, $key, $val)
        {
            $result = $this->db->table('user_option')->where('id_user', $idUser)
                ->where('option_key', $key)->get();

            if ($result->isEmpty())
            {
                $this->db->table('user_option')->insert([NULL, $idUser, $key, $val]);
            }
            else
            {
                $this->db->table('user_option')->where('id_user', $idUser)
                    ->where('option_key', $key)->update();
            }
        }

        public function getOption($idUser)
        {
            $result = $this->db->table('user_option')->where('id_user', $idUser)
                ->get('option_key, option_value');

            $option = array();
            while ($row = $result->row())
            {
                $option[$row->option_key] = $row->option_value;
            }

            return $result->isEmpty() ? array() : $option;
        }

        public function verify($username, $password)
        {
            $result = $this->db->table('user')->where('name', $username)->get('password');

            if ($result->isEmpty())
            {
                return false;
            }

            $hashPass = $result->first('password');
            return (page()->encryption->verifyPassword($password, $hashPass));
        }

        public function addSession($username, $hashedToken)
        {
            $id = $this->getId($username);
            $result = $this->db->table('user_session')->insert([NULL, $id, $hashedToken, time()]);
            return !$result->isError();
        }

        public function verifySession($username, $hashedToken)
        {
            $id = $this->getId($username);
            $result = $this->db->table('user_session')->where('id_user', $id)
                ->where('session_token', $hashedToken)->get();
            return !($result->isEmpty());
        }

        public function removeSession($username, $hashedToken)
        {
            $id = $this->getId($username);
            $result = $this->db->table('user_session')->where('id', $id)
                ->where('session_token', $hashedToken)->delete();
            return !($result->isEmpty());
        }
    }

?>

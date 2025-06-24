<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * User Model
 * 
 * 사용자 인증 및 관리를 위한 모델
 */
class User_model extends CI_Model
{
    private $table = 'users';

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * 사용자명으로 사용자 정보 조회
     * 
     * @param string $username 사용자명
     * @return array|null 사용자 정보 또는 null
     */
    public function get_user_by_username($username)
    {
        $this->db->where('username', $username);
        $query = $this->db->get($this->table);
        
        if ($query->num_rows() == 1) {
            return $query->row_array();
        }
        
        return null;
    }

    /**
     * 사용자 ID로 사용자 정보 조회
     * 
     * @param int $user_id 사용자 ID
     * @return array|null 사용자 정보 또는 null
     */
    public function get_user_by_id($user_id)
    {
        $this->db->where('id', $user_id);
        $query = $this->db->get($this->table);
        
        if ($query->num_rows() == 1) {
            return $query->row_array();
        }
        
        return null;
    }

    /**
     * 로그인 인증
     * 
     * @param string $username 사용자명
     * @param string $password 패스워드
     * @return array|false 인증 성공시 사용자 정보, 실패시 false
     */
    public function authenticate($username, $password)
    {
        $user = $this->get_user_by_username($username);
        
        if ($user && password_verify($password, $user['password'])) {
            // 패스워드는 반환하지 않음
            unset($user['password']);
            return $user;
        }
        
        return false;
    }

    /**
     * 사용자 생성
     * 
     * @param array $user_data 사용자 정보
     * @return int|false 생성된 사용자 ID 또는 false
     */
    public function create_user($user_data)
    {
        // 패스워드 해시화
        if (isset($user_data['password'])) {
            $user_data['password'] = password_hash($user_data['password'], PASSWORD_DEFAULT);
        }
        
        if ($this->db->insert($this->table, $user_data)) {
            return $this->db->insert_id();
        }
        
        return false;
    }

    /**
     * 사용자 정보 업데이트
     * 
     * @param int $user_id 사용자 ID
     * @param array $user_data 업데이트할 사용자 정보
     * @return bool 성공여부
     */
    public function update_user($user_id, $user_data)
    {
        // 패스워드가 있다면 해시화
        if (isset($user_data['password'])) {
            $user_data['password'] = password_hash($user_data['password'], PASSWORD_DEFAULT);
        }
        
        $user_data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->db->where('id', $user_id);
        return $this->db->update($this->table, $user_data);
    }

    /**
     * 사용자 삭제
     * 
     * @param int $user_id 사용자 ID
     * @return bool 성공여부
     */
    public function delete_user($user_id)
    {
        $this->db->where('id', $user_id);
        return $this->db->delete($this->table);
    }

    /**
     * 모든 사용자 목록 조회 (관리자용)
     * 
     * @param int $limit 제한 수
     * @param int $offset 오프셋
     * @return array 사용자 목록
     */
    public function get_all_users($limit = 10, $offset = 0)
    {
        $this->db->select('id, username, email, created_at, updated_at');
        $this->db->limit($limit, $offset);
        $this->db->order_by('created_at', 'DESC');
        
        $query = $this->db->get($this->table);
        return $query->result_array();
    }

    /**
     * 사용자 총 개수 조회
     * 
     * @return int 사용자 총 개수
     */
    public function count_users()
    {
        return $this->db->count_all($this->table);
    }
} 
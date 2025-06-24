<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * User Token Model
 * 
 * 사용자 토큰 관리 모델
 */
class User_token_model extends CI_Model
{
    private $table = 'user_tokens';

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * 토큰 저장
     * 
     * @param int $user_id 사용자 ID
     * @param string $token JWT 토큰
     * @param string $ip_address IP 주소
     * @param string $user_agent 사용자 에이전트
     * @param int $expires_in 만료 시간 (초)
     * @param string $token_type 토큰 타입
     * @return int|bool 삽입된 ID 또는 false
     */
    public function save_token($user_id, $token, $ip_address, $user_agent = null, $expires_in = 3600, $token_type = 'access')
    {
        // 토큰 해시 생성 (보안을 위해 해시값 저장)
        $token_hash = hash('sha256', $token);
        
        // 발급 시간과 만료 시간 계산
        $issued_at = date('Y-m-d H:i:s');
        $expires_at = date('Y-m-d H:i:s', time() + $expires_in);

        $data = [
            'user_id' => (int)$user_id,
            'token_hash' => $token_hash,
            'token_type' => $token_type,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'issued_at' => $issued_at,
            'expires_at' => $expires_at,
            'is_active' => 1
        ];

        if ($this->db->insert($this->table, $data)) {
            return $this->db->insert_id();
        }

        log_message('error', 'Failed to save user token: ' . $this->db->last_query());
        return false;
    }

    /**
     * 토큰 검증
     * 
     * @param string $token JWT 토큰
     * @return array|false 토큰 정보 또는 false
     */
    public function verify_token($token)
    {
        $token_hash = hash('sha256', $token);
        
        $this->db->select('ut.*, u.username, u.email');
        $this->db->from($this->table . ' ut');
        $this->db->join('users u', 'ut.user_id = u.id', 'left');
        $this->db->where('ut.token_hash', $token_hash);
        $this->db->where('ut.is_active', 1);
        $this->db->where('ut.expires_at >', date('Y-m-d H:i:s'));
        
        $query = $this->db->get();
        
        if ($query->num_rows() == 1) {
            $token_info = $query->row_array();
            
            // 마지막 사용 시간 업데이트
            $this->update_last_used($token_info['id']);
            
            return $token_info;
        }
        
        return false;
    }

    /**
     * 토큰 무효화
     * 
     * @param string $token JWT 토큰
     * @return bool 성공여부
     */
    public function invalidate_token($token)
    {
        $token_hash = hash('sha256', $token);
        
        $this->db->where('token_hash', $token_hash);
        return $this->db->update($this->table, ['is_active' => 0]);
    }

    /**
     * 사용자의 모든 토큰 무효화 (로그아웃 전체)
     * 
     * @param int $user_id 사용자 ID
     * @param string $except_token 제외할 토큰 (현재 토큰)
     * @return bool 성공여부
     */
    public function invalidate_user_tokens($user_id, $except_token = null)
    {
        $this->db->where('user_id', $user_id);
        $this->db->where('is_active', 1);
        
        if ($except_token) {
            $except_hash = hash('sha256', $except_token);
            $this->db->where('token_hash !=', $except_hash);
        }
        
        return $this->db->update($this->table, ['is_active' => 0]);
    }

    /**
     * 만료된 토큰 정리
     * 
     * @return int 정리된 토큰 수
     */
    public function cleanup_expired_tokens()
    {
        $this->db->where('expires_at <', date('Y-m-d H:i:s'));
        $this->db->where('is_active', 1);
        
        if ($this->db->update($this->table, ['is_active' => 0])) {
            return $this->db->affected_rows();
        }
        
        return 0;
    }

    /**
     * 마지막 사용 시간 업데이트
     * 
     * @param int $token_id 토큰 ID
     * @return bool 성공여부
     */
    private function update_last_used($token_id)
    {
        $this->db->where('id', $token_id);
        return $this->db->update($this->table, ['last_used_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * 사용자별 활성 토큰 조회
     * 
     * @param int $user_id 사용자 ID
     * @param int $limit 제한 수
     * @param int $offset 오프셋
     * @return array 토큰 목록
     */
    public function get_user_tokens($user_id, $limit = 10, $offset = 0)
    {
        $this->db->select('id, token_type, ip_address, user_agent, issued_at, expires_at, last_used_at, is_active');
        $this->db->from($this->table);
        $this->db->where('user_id', $user_id);
        $this->db->order_by('issued_at', 'DESC');
        $this->db->limit($limit, $offset);

        return $this->db->get()->result_array();
    }

    /**
     * 토큰 통계 조회
     * 
     * @param int $user_id 사용자 ID (선택)
     * @return array 통계 정보
     */
    public function get_token_statistics($user_id = null)
    {
        $this->db->select('
            COUNT(*) as total_tokens,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_tokens,
            SUM(CASE WHEN expires_at > NOW() AND is_active = 1 THEN 1 ELSE 0 END) as valid_tokens,
            SUM(CASE WHEN expires_at <= NOW() OR is_active = 0 THEN 1 ELSE 0 END) as expired_tokens
        ');
        $this->db->from($this->table);
        
        if ($user_id) {
            $this->db->where('user_id', $user_id);
        }
        
        $query = $this->db->get();
        return $query->row_array();
    }

    /**
     * IP별 토큰 사용 통계
     * 
     * @param int $user_id 사용자 ID
     * @param int $days 조회 일수
     * @return array IP별 통계
     */
    public function get_ip_statistics($user_id, $days = 30)
    {
        $since_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $this->db->select('
            ip_address,
            COUNT(*) as token_count,
            MAX(issued_at) as last_login,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count
        ');
        $this->db->from($this->table);
        $this->db->where('user_id', $user_id);
        $this->db->where('issued_at >=', $since_date);
        $this->db->group_by('ip_address');
        $this->db->order_by('last_login', 'DESC');
        
        return $this->db->get()->result_array();
    }

    /**
     * 토큰 해시로 토큰 정보 조회 (내부 사용)
     * 
     * @param string $token_hash 토큰 해시
     * @return array|false 토큰 정보 또는 false
     */
    public function get_token_by_hash($token_hash)
    {
        $this->db->where('token_hash', $token_hash);
        $query = $this->db->get($this->table);
        
        if ($query->num_rows() == 1) {
            return $query->row_array();
        }
        
        return false;
    }
} 
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Login Log Model
 * 
 * 로그인 로그 관리 모델
 */
class Login_log_model extends CI_Model
{
    private $table = 'login_logs';

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * 로그인 로그 저장
     * 
     * @param array $data 로그 데이터
     * @return int|bool 삽입된 ID 또는 false
     */
    public function save_login_log($data)
    {
        // 필수 필드 검증
        $required_fields = ['ip_address', 'login_status'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                log_message('error', "Login log save failed: {$field} is required");
                return false;
            }
        }

        // 기본값 설정
        if (!isset($data['request_time'])) {
            $data['request_time'] = date('Y-m-d H:i:s');
        }

        // 데이터 정제
        $log_data = [
            'username' => isset($data['username']) ? trim($data['username']) : null,
            'user_id' => isset($data['user_id']) ? (int)$data['user_id'] : null,
            'ip_address' => trim($data['ip_address']),
            'user_agent' => isset($data['user_agent']) ? trim($data['user_agent']) : null,
            'login_status' => in_array($data['login_status'], ['success', 'failed']) ? $data['login_status'] : 'failed',
            'failure_reason' => isset($data['failure_reason']) ? trim($data['failure_reason']) : null,
            'request_time' => $data['request_time']
        ];

        // NULL 값 제거 (username, user_id, user_agent, failure_reason은 NULL 허용)
        $filtered_data = array_filter($log_data, function($value, $key) {
            return !is_null($value) || in_array($key, ['username', 'user_id', 'user_agent', 'failure_reason']);
        }, ARRAY_FILTER_USE_BOTH);

        if ($this->db->insert($this->table, $filtered_data)) {
            return $this->db->insert_id();
        }

        log_message('error', 'Failed to save login log: ' . $this->db->last_query());
        return false;
    }

    /**
     * 로그인 성공 로그 저장
     * 
     * @param array $user_data 사용자 데이터
     * @param string $ip_address IP 주소
     * @param string $user_agent 사용자 에이전트
     * @return int|bool
     */
    public function log_successful_login($user_data, $ip_address, $user_agent = null)
    {
        $log_data = [
            'username' => $user_data['username'],
            'user_id' => $user_data['id'],
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'login_status' => 'success'
        ];

        return $this->save_login_log($log_data);
    }

    /**
     * 로그인 실패 로그 저장
     * 
     * @param string $username 사용자명
     * @param string $ip_address IP 주소
     * @param string $failure_reason 실패 사유
     * @param string $user_agent 사용자 에이전트
     * @return int|bool
     */
    public function log_failed_login($username, $ip_address, $failure_reason, $user_agent = null)
    {
        $log_data = [
            'username' => $username,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'login_status' => 'failed',
            'failure_reason' => $failure_reason
        ];

        return $this->save_login_log($log_data);
    }

    /**
     * 사용자별 로그인 로그 조회
     * 
     * @param int $user_id 사용자 ID
     * @param int $limit 제한 수
     * @param int $offset 오프셋
     * @return array
     */
    public function get_user_login_logs($user_id, $limit = 50, $offset = 0)
    {
        $this->db->select('*');
        $this->db->from($this->table);
        $this->db->where('user_id', $user_id);
        $this->db->order_by('request_time', 'DESC');
        $this->db->limit($limit, $offset);

        return $this->db->get()->result_array();
    }

    /**
     * IP별 로그인 로그 조회
     * 
     * @param string $ip_address IP 주소
     * @param int $limit 제한 수
     * @param int $offset 오프셋
     * @return array
     */
    public function get_ip_login_logs($ip_address, $limit = 50, $offset = 0)
    {
        $this->db->select('*');
        $this->db->from($this->table);
        $this->db->where('ip_address', $ip_address);
        $this->db->order_by('request_time', 'DESC');
        $this->db->limit($limit, $offset);

        return $this->db->get()->result_array();
    }

    /**
     * 최근 로그인 실패 횟수 조회 (특정 시간 내)
     * 
     * @param string $ip_address IP 주소
     * @param int $minutes 시간 (분)
     * @return int
     */
    public function get_recent_failed_attempts($ip_address, $minutes = 30)
    {
        $since_time = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
        
        $this->db->select('COUNT(*) as count');
        $this->db->from($this->table);
        $this->db->where('ip_address', $ip_address);
        $this->db->where('login_status', 'failed');
        $this->db->where('request_time >=', $since_time);

        $result = $this->db->get()->row_array();
        return (int)$result['count'];
    }

    /**
     * 로그인 통계 조회
     * 
     * @param string $date_from 시작 날짜 (Y-m-d)
     * @param string $date_to 종료 날짜 (Y-m-d)
     * @return array
     */
    public function get_login_statistics($date_from = null, $date_to = null)
    {
        if (!$date_from) {
            $date_from = date('Y-m-d', strtotime('-30 days'));
        }
        if (!$date_to) {
            $date_to = date('Y-m-d');
        }

        $this->db->select("
            DATE(request_time) as login_date,
            COUNT(*) as total_attempts,
            SUM(CASE WHEN login_status = 'success' THEN 1 ELSE 0 END) as successful_logins,
            SUM(CASE WHEN login_status = 'failed' THEN 1 ELSE 0 END) as failed_logins
        ");
        $this->db->from($this->table);
        $this->db->where('DATE(request_time) >=', $date_from);
        $this->db->where('DATE(request_time) <=', $date_to);
        $this->db->group_by('DATE(request_time)');
        $this->db->order_by('login_date', 'DESC');

        return $this->db->get()->result_array();
    }
} 
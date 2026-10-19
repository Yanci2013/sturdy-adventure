<?php
/**
 * 彩虹聚合登录SDK
 * 聚合登录请求类
 * 1.4
**/

require_once __DIR__ . '/../config.php';

class Oauth {
    private $apiurl;
    private $appid;
    private $appkey;
    private $callback;
    private $db;

    function __construct($config){
        $this->apiurl = $config['apiurl'].'connect.php';
        $this->appid = $config['appid'];
        $this->appkey = $config['appkey'];
        $this->callback = $config['callback'];
        $this->initDB();
    }

    private function initDB(){
        try {
            $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->db = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            throw new Exception("数据库连接失败: " . $e->getMessage());
        }
    }

    public function login($type){
        $state = md5(uniqid(rand(), TRUE));
        $_SESSION['Oauth_state'] = $state;

        $keysArr = array(
            "act" => "login",
            "appid" => $this->appid,
            "appkey" => $this->appkey,
            "type" => $type,
            "redirect_uri" => $this->callback,
            "state" => $state
        );
        $login_url = $this->apiurl.'?'.http_build_query($keysArr);
        $response = $this->get_curl($login_url);
        return json_decode($response, true);
    }

    public function callback(){
        $keysArr = array(
            "act" => "callback",
            "appid" => $this->appid,
            "appkey" => $this->appkey,
            "code" => $_GET['code']
        );

        $token_url = $this->apiurl.'?'.http_build_query($keysArr);
        $response = $this->get_curl($token_url);
        $arr = json_decode($response, true);
        
        if(isset($arr['social_uid'])){
            $userInfo = $this->saveUserToDatabase($arr);
            $arr['local_user_id'] = $userInfo['id'];
            $arr['local_username'] = $userInfo['username'];
        }
        
        return $arr;
    }

    public function query($type, $social_uid){
        $keysArr = array(
            "act" => "query",
            "appid" => $this->appid,
            "appkey" => $this->appkey,
            "type" => $type,
            "social_uid" => $social_uid
        );

        $token_url = $this->apiurl.'?'.http_build_query($keysArr);
        $response = $this->get_curl($token_url);
        return json_decode($response, true);
    }

    private function saveUserToDatabase($userData){
        $sql = "SELECT id, username FROM h5user WHERE social_uid = ? AND social_type = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userData['social_uid'], $userData['type']]);
        $existingUser = $stmt->fetch();
        
        return $existingUser ? $this->updateUser($existingUser['id'], $userData) : $this->insertUser($userData);
    }
    
    private function insertUser($userData){
    // 获取下一个ID
    $sql = "SELECT MAX(id) as max_id FROM h5user";
    $stmt = $this->db->query($sql);
    $result = $stmt->fetch();
    $nextId = max(1000, ($result['max_id'] ?? 999) + 1);
    
    $password = substr(md5(uniqid()), 0, 8);
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $userIdU = $this->generateUserIdU();
    $avatarPath = $this->downloadAvatar($userData['avatar'] ?? '', $nextId);
    $username = $this->generateUniqueUsername();
    
    // 生成11位随机手机号(1开头)
    $phone = '1' . str_pad(mt_rand(0, 9999999999), 10, '0', STR_PAD_LEFT);
    
    $data = [
        'id' => $nextId,
        'user_idu' => $userIdU,
        'username' => $username,
        'password' => $hashedPassword,
        'nickname' => $userData['nickname'] ?? '用户_'.$nextId,
        'avatar' => $avatarPath,
        'social_uid' => $userData['social_uid'],
        'access_token' => $userData['access_token'] ?? '',
        'social_type' => $userData['type'] ?? '',
        'gender' => $this->convertGender($userData['gender'] ?? 0),
        'reg_ip' => $_SERVER['REMOTE_ADDR'],
        'last_ip' => $_SERVER['REMOTE_ADDR'],
        'phone' => $phone  // 添加phone字段
    ];
    
    $sql = "INSERT INTO h5user (id, user_idu, username, password, nickname, avatar, social_uid, 
            access_token, social_type, gender, reg_ip, last_ip, phone, created_at, updated_at) 
            VALUES (:id, :user_idu, :username, :password, :nickname, :avatar, :social_uid, 
            :access_token, :social_type, :gender, :reg_ip, :last_ip, :phone, NOW(), NOW())";
    
    $this->db->prepare($sql)->execute($data);
    
    return ['id' => $nextId, 'username' => $username];
}
    
    private function updateUser($userId, $userData){
        $data = [
            'id' => $userId,
            'access_token' => $userData['access_token'] ?? '',
            'last_ip' => $_SERVER['REMOTE_ADDR']
        ];
        
        $sql = "UPDATE h5user SET access_token = :access_token, last_ip = :last_ip, updated_at = NOW()";
        
        if(!empty($userData['avatar'])) {
            $avatarPath = $this->downloadAvatar($userData['avatar'], $userId);
            $sql .= ", avatar = :avatar";
            $data['avatar'] = $avatarPath;
        }
        
        $sql .= " WHERE id = :id";
        
        $this->db->prepare($sql)->execute($data);
        
        // 获取用户名
        $stmt = $this->db->prepare("SELECT username FROM h5user WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        return ['id' => $userId, 'username' => $user['username']];
    }
    
    /**
     * 生成以1开头的8位唯一数字账号
     */
    private function generateUniqueUsername() {
        $maxAttempts = 10; // 最大尝试次数
        $attempt = 0;
        
        do {
            // 生成1开头的8位数字
            $username = '1' . str_pad(mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT);
            
            // 检查是否已存在
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM h5user WHERE username = ?");
            $stmt->execute([$username]);
            $result = $stmt->fetch();
            
            if ($result['count'] == 0) {
                return $username;
            }
            
            $attempt++;
        } while ($attempt < $maxAttempts);
        
        // 如果尝试多次仍然冲突，使用时间戳+随机数的方式
        return '1' . substr(time(), -7) . mt_rand(0, 9);
    }
    
    private function downloadAvatar($avatarUrl, $userId){
        if(empty($avatarUrl)) return 'default_avatar.png';
        
        $avatarDir = $_SERVER['DOCUMENT_ROOT'].'/assets/images/h5user/';
        if(!is_dir($avatarDir)) mkdir($avatarDir, 0755, true);
        
        $ext = pathinfo($avatarUrl, PATHINFO_EXTENSION) ?: 'jpg';
        $filename = 'avatar_'.$userId.'_'.time().'.'.$ext;
        $filepath = $avatarDir.$filename;
        
        $ch = curl_init($avatarUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 10
        ]);
        $avatarData = curl_exec($ch);
        curl_close($ch);
        
        if($avatarData && file_put_contents($filepath, $avatarData)) {
            return 'assets/images/h5user/'.$filename;
        }
        
        return 'default_avatar.png';
    }
    
    private function generateUserIdU(){
        return substr(md5(uniqid().mt_rand(100000,999999)), 0, 18);
    }
    
    private function convertGender($gender){
        if(is_numeric($gender)) return (int)$gender;
        
        $gender = strtolower($gender);
        if(strpos($gender, '男') !== false || $gender == 'm' || $gender == 'male') return 1;
        if(strpos($gender, '女') !== false || $gender == 'f' || $gender == 'female') return 2;
        
        return 0;
    }

    private function get_curl($url){
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.132 Safari/537.36",
            CURLOPT_TIMEOUT => 10,
            CURLOPT_RETURNTRANSFER => 1
        ]);
        $ret = curl_exec($ch);
        curl_close($ch);
        return $ret;
    }
    
    public function __destruct(){
        $this->db = null;
    }
}
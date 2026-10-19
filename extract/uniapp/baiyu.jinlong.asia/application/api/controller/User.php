<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\Ems;
use app\common\library\Sms;
use app\common\library\Token;
use app\common\library\Upload;
use fast\Random;
use think\Config;
use think\File;
use think\Validate;

/**
 * 会员接口
 */
class User extends Api
{
    protected $noNeedLogin = ['login', 'mobilelogin', 'register', 'resetpwd', 'changeemail', 'changemobile', 'third', 'wechatlogin', 'getParentList', 'getSystemConfig', 'unlockNotify', 'unlockReturn', 'getMiniappContent', 'quickregister', 'quicklogin'];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();

        if (!Config::get('fastadmin.usercenter')) {
            $this->error(__('User center already closed'));
        }

    }

    /**
     * 会员中心
     */
    public function index()
    {
        $this->success('', ['welcome' => $this->auth->nickname]);
    }

    /**
     * 会员登录
     *
     * @ApiMethod (POST)
     * @ApiParams (name="account", type="string", required=true, description="账号")
     * @ApiParams (name="password", type="string", required=true, description="密码")
     */
    public function login()
    {
        $account = $this->request->post('account');
        $password = $this->request->post('password');
        if (!$account || !$password) {
            $this->error(__('Invalid parameters'));
        }
        $ret = $this->auth->login($account, $password);
        if ($ret) {
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('Logged in successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 手机验证码登录
     *
     * @ApiMethod (POST)
     * @ApiParams (name="mobile", type="string", required=true, description="手机号")
     * @ApiParams (name="captcha", type="string", required=true, description="验证码")
     */
    public function mobilelogin()
    {
        $mobile = $this->request->post('mobile');
        $captcha = $this->request->post('captcha');
        if (!$mobile || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        if (!Sms::check($mobile, $captcha, 'mobilelogin')) {
            $this->error(__('Captcha is incorrect'));
        }
        $user = \app\common\model\User::getByMobile($mobile);
        if ($user) {
            if ($user->status != 'normal') {
                $this->error(__('Account is locked'));
            }
            //如果已经有账号则直接登录
            $ret = $this->auth->direct($user->id);
        } else {
            $ret = $this->auth->register($mobile, Random::alnum(), '', $mobile, []);
        }
        if ($ret) {
            Sms::flush($mobile, 'mobilelogin');
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('Logged in successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 注册会员
     *
     * @ApiMethod (POST)
     * @ApiParams (name="username", type="string", required=true, description="用户名")
     * @ApiParams (name="password", type="string", required=true, description="密码")
     * @ApiParams (name="email", type="string", required=true, description="邮箱")
     * @ApiParams (name="mobile", type="string", required=true, description="手机号")
     * @ApiParams (name="code", type="string", required=true, description="验证码")
     */
    public function register()
    {
        $username = $this->request->post('username');
        $password = $this->request->post('password');
        $email = $this->request->post('email');
        $mobile = $this->request->post('mobile');
        $code = $this->request->post('code');
        if (!$username || !$password) {
            $this->error(__('Invalid parameters'));
        }
        if ($email && !Validate::is($email, "email")) {
            $this->error(__('Email is incorrect'));
        }
        if ($mobile && !Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        $ret = Sms::check($mobile, $code, 'register');
        if (!$ret) {
            $this->error(__('Captcha is incorrect'));
        }
        
        $roleType = $this->request->post('role_type', 'dashou'); // 默认为打手
        if (!in_array($roleType, ['user', 'dashou', 'jindashou', 'guanshi', 'zuzhang', 'zongguan', 'jinshangjia', 'shangjia', 'kefu', 'shouhou'])) {
            $roleType = 'dashou';
        }
        
        \think\Db::startTrans();
        try {
            $ret = $this->auth->register($username, $password, $email, $mobile, []);
            if ($ret) {
                // 直接更新用户表的role_type字段
                $userId = $this->auth->id;
                $user = \app\common\model\User::get($userId);
                if ($user) {
                    $user->role_type = $roleType;
                    $user->save();
                }
                
                \think\Db::commit();
                
                // 获取用户信息（包含角色）
                $userInfo = $this->auth->getUserinfo();
                $userInfo['role_type'] = $roleType;
                
                $data = ['userinfo' => $userInfo];
                $this->success(__('Sign up successful'), $data);
            } else {
                \think\Db::rollback();
                $this->error($this->auth->getError());
            }
        } catch (\Exception $e) {
            \think\Db::rollback();
            $this->error('注册失败：' . $e->getMessage());
        }
    }

    /**
     * 快速注册（无需验证码）
     *
     * @ApiMethod (POST)
     * @ApiParams (name="username", type="string", required=true, description="用户名")
     * @ApiParams (name="password", type="string", required=true, description="密码")
     * @ApiParams (name="repassword", type="string", required=true, description="确认密码")
     */
    public function quickregister()
    {
        $username = $this->request->post('username');
        $password = $this->request->post('password');
        $repassword = $this->request->post('repassword');
        
        // 验证参数
        if (!$username || !$password || !$repassword) {
            $this->error('参数不正确');
        }
        
        if (strlen($password) < 6) {
            $this->error('密码长度不能少于6位');
        }
        
        if (!preg_match('/^(?=.*[a-zA-Z])(?=.*\d)/', $password)) {
            $this->error('密码必须包含字母和数字');
        }
        
        if ($password !== $repassword) {
            $this->error('两次输入的密码不一致');
        }
        
        $roleType = $this->request->post('role_type', 'dashou'); // 默认为打手
        if (!in_array($roleType, ['user', 'dashou', 'jindashou', 'guanshi', 'zuzhang', 'zongguan', 'jinshangjia', 'shangjia', 'kefu', 'shouhou'])) {
            $roleType = 'dashou';
        }
        
        // 检查用户名是否已存在
        $existingUser = \app\common\model\User::getByUsername($username);
        if ($existingUser) {
            $this->error('用户名已存在');
        }
        
        // 检查昵称是否已存在
        $existingNickname = \app\common\model\User::getByNickname($username);
        if ($existingNickname) {
            $this->error('昵称已存在');
        }
        
        // 直接创建用户，不使用Auth::register方法
        $ip = request()->ip();
        $time = time();
        $salt = \fast\Random::alnum();
        
        $user = \app\common\model\User::create([
            'username' => $username,
            'password' => md5(md5($password) . $salt),
            'salt' => $salt,
            'nickname' => $username,
            'jointime' => $time,
            'joinip' => $ip,
            'logintime' => $time,
            'loginip' => $ip,
            'prevtime' => $time,
            'status' => 'normal',
            'level' => 1,
            'score' => 0,
            'avatar' => ''
        ]);
        
        if ($user) {
            // 生成Token
            $token = \fast\Random::uuid();
            \app\common\library\Token::set($token, $user->id, 2592000);
            
            // 获取用户信息（避免访问money属性，防止与money方法冲突）
            $userData = $user->toArray();
            $userInfo = [
                'id' => $userData['id'],
                'username' => $userData['username'],
                'nickname' => $userData['nickname'],
                'avatar' => $userData['avatar'],
                'level' => $userData['level'],
                'score' => $userData['score'],
                'money' => isset($userData['money']) ? $userData['money'] : 0,
                'role_type' => $roleType,
                'token' => $token
            ];
            
            $this->success('注册成功', ['userinfo' => $userInfo]);
        } else {
            $this->error('注册失败：创建用户失败');
        }
    }

    /**
     * 快速登录（无需验证码）
     *
     * @ApiMethod (POST)
     * @ApiParams (name="username", type="string", required=true, description="用户名")
     * @ApiParams (name="password", type="string", required=true, description="密码")
     */
    public function quicklogin()
    {
        $username = $this->request->post('username');
        $password = $this->request->post('password');
        
        if (!$username || !$password) {
            $this->error('参数不正确');
        }
        
        // 查找用户
        $user = \app\common\model\User::getByUsername($username);
        if (!$user) {
            $this->error('账号不存在');
        }
        
        if ($user->status != 'normal') {
            $this->error('账号被锁定');
        }
        
        // 验证密码
        $salt = $user->salt;
        $encryptedPassword = md5(md5($password) . $salt);
        if ($user->password != $encryptedPassword) {
            $this->error('密码错误');
        }
        
        // 更新登录信息
        $ip = request()->ip();
        $time = time();
        $user->save([
            'loginip' => $ip,
            'logintime' => $time,
            'prevtime' => $user->logintime,
            'loginfailure' => 0
        ]);
        
        // 生成Token
        $token = \fast\Random::uuid();
        \app\common\library\Token::set($token, $user->id, 2592000);
        
        // 获取用户信息（避免访问money属性，防止与money方法冲突）
        $userData = $user->toArray();
        $userInfo = [
            'id' => $userData['id'],
            'username' => $userData['username'],
            'nickname' => $userData['nickname'],
            'avatar' => $userData['avatar'],
            'level' => $userData['level'],
            'score' => $userData['score'],
            'money' => isset($userData['money']) ? $userData['money'] : 0,
            'role_type' => isset($userData['role_type']) ? $userData['role_type'] : 'dashou',
            'token' => $token
        ];
        
        $this->success('登录成功', ['userinfo' => $userInfo]);
    }

    /**
     * 退出登录
     * @ApiMethod (POST)
     */
    public function logout()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $this->auth->logout();
        $this->success(__('Logout successful'));
    }

    /**
     * 修改会员个人信息
     *
     * @ApiMethod (POST)
     * @ApiParams (name="avatar", type="string", required=true, description="头像地址")
     * @ApiParams (name="username", type="string", required=true, description="用户名")
     * @ApiParams (name="nickname", type="string", required=true, description="昵称")
     * @ApiParams (name="bio", type="string", required=true, description="个人简介")
     */
    public function profile()
    {
        $user = $this->auth->getUser();
        $username = $this->request->post('username');
        $nickname = $this->request->post('nickname');
        $bio = $this->request->post('bio');
        $avatar = $this->request->post('avatar', '', 'trim,strip_tags,htmlspecialchars');
        if ($username) {
            $exists = \app\common\model\User::where('username', $username)->where('id', '<>', $this->auth->id)->find();
            if ($exists) {
                $this->error(__('Username already exists'));
            }
            $user->username = $username;
        }
        if ($nickname) {
            $exists = \app\common\model\User::where('nickname', $nickname)->where('id', '<>', $this->auth->id)->find();
            if ($exists) {
                $this->error(__('Nickname already exists'));
            }
            $user->nickname = $nickname;
        }
        $user->bio = $bio;
        $user->avatar = $avatar;
        $user->save();
        $this->success();
    }

    /**
     * 修改邮箱
     *
     * @ApiMethod (POST)
     * @ApiParams (name="email", type="string", required=true, description="邮箱")
     * @ApiParams (name="captcha", type="string", required=true, description="验证码")
     */
    public function changeemail()
    {
        $user = $this->auth->getUser();
        $email = $this->request->post('email');
        $captcha = $this->request->post('captcha');
        if (!$email || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::is($email, "email")) {
            $this->error(__('Email is incorrect'));
        }
        if (\app\common\model\User::where('email', $email)->where('id', '<>', $user->id)->find()) {
            $this->error(__('Email already exists'));
        }
        $result = Ems::check($email, $captcha, 'changeemail');
        if (!$result) {
            $this->error(__('Captcha is incorrect'));
        }
        $verification = $user->verification;
        $verification->email = 1;
        $user->verification = $verification;
        $user->email = $email;
        $user->save();

        Ems::flush($email, 'changeemail');
        $this->success();
    }

    /**
     * 修改手机号
     *
     * @ApiMethod (POST)
     * @ApiParams (name="mobile", type="string", required=true, description="手机号")
     * @ApiParams (name="captcha", type="string", required=true, description="验证码")
     */
    public function changemobile()
    {
        $user = $this->auth->getUser();
        $mobile = $this->request->post('mobile');
        $captcha = $this->request->post('captcha');
        if (!$mobile || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        if (\app\common\model\User::where('mobile', $mobile)->where('id', '<>', $user->id)->find()) {
            $this->error(__('Mobile already exists'));
        }
        $result = Sms::check($mobile, $captcha, 'changemobile');
        if (!$result) {
            $this->error(__('Captcha is incorrect'));
        }
        $verification = $user->verification;
        $verification->mobile = 1;
        // 使用save方法传入数组更新（避免属性不存在错误）
        $user->save([
            'mobile' => $mobile,
            'verification' => json_encode($verification)
        ]);

        Sms::flush($mobile, 'changemobile');
        $this->success();
    }

    /**
     * 微信小程序登录
     *
     * @ApiMethod (POST)
     * @ApiParams (name="code", type="string", required=true, description="微信登录code")
     * @ApiParams (name="nickname", type="string", required=false, description="昵称")
     * @ApiParams (name="avatar", type="string", required=false, description="头像")
     * @ApiParams (name="client_type", type="string", required=false, description="客户端类型:client=点单端,companion=陪玩端")
     * @ApiParams (name="scene", type="string", required=false, description="小程序码scene参数（格式：邀请码_邀请类型）")
     */
    public function wechatLogin()
    {
        // 调试：输出当前action名
        \think\Log::write('wechatLogin action: ' . $this->request->action(), 'info');
        \think\Log::write('noNeedLogin: ' . json_encode($this->noNeedLogin), 'info');
        
        $code = $this->request->post('code');
        $nickname = $this->request->post('nickname', '');
        $avatar = $this->request->post('avatar', '');
        $clientType = $this->request->post('client_type', 'companion'); // 默认为陪玩端，保持兼容性
        $scene = $this->request->post('scene', ''); // 小程序码scene参数
        
        if (!$code) {
            $this->error('缺少code参数');
        }
        
        // 解析scene参数（格式：邀请码_邀请类型）
        $inviteCode = '';
        $inviteType = '';
        $inviter = null;
        if (!empty($scene)) {
            $sceneParts = explode('_', urldecode($scene));
            if (count($sceneParts) >= 2) {
                $inviteCode = $sceneParts[0];
                $inviteType = $sceneParts[1];
                
                // 根据邀请码查找邀请人
                if (!empty($inviteCode)) {
                    $inviter = \think\Db::name('user')
                        ->where('invite_code', $inviteCode)
                        ->where('status', 'normal')
                        ->find();
                }
            }
        }
        
        // 通过code获取openid和session_key
        // 从系统设置表读取微信小程序配置
        $systemConfig = \think\Db::name('system_config')->where('id', 1)->find();
        
        if (!$systemConfig) {
            $this->error('系统设置未初始化，请联系管理员');
        }
        
        $appid = $systemConfig['wxappid'] ?? '';
        $secret = $systemConfig['wxappsecret'] ?? '';
        
        if (!$appid || !$secret) {
            $this->error('小程序配置未设置，请在后台"陪玩->系统设置管理"中设置AppID和AppSecret');
        }
        
        // 调用微信接口获取openid
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$secret}&js_code={$code}&grant_type=authorization_code";
        $result = file_get_contents($url);
        $wxData = json_decode($result, true);
        
        if (isset($wxData['errcode'])) {
            $this->error('微信登录失败：' . $wxData['errmsg']);
        }
        
        $openid = $wxData['openid'];
        $sessionKey = $wxData['session_key'];
        
        if (!$openid) {
            $this->error('获取openid失败');
        }
        
        // 处理头像URL：如果是临时路径，需要下载并上传到服务器
        if ($avatar && (strpos($avatar, 'tmp/') !== false || strpos($avatar, 'http://tmp/') !== false)) {
            $avatar = $this->downloadAndUploadAvatar($avatar);
        }
        
        // 查找或创建用户
        $user = \app\common\model\User::where('openid', $openid)->find();
        $isNewUser = false;
        
        if (!$user) {
            // 新用户，创建账号（仅在第一次登录时写入昵称和头像）
            // 默认角色：陷玩端=未认证，点单端=用户
            $defaultRoleType = ($clientType === 'client') ? 'user' : 'weironzheng';
            
            // 如果有邀请人且邀请类型不是promote，则使用邀请类型作为角色
            $newRoleType = $defaultRoleType;
            $promoteParentId = 0;
            $parentId = 0;
            
            if ($inviter) {
                // 根据邀请类型分别处理
                if ($inviteType === 'promote') {
                    // 推广海报：只绑定推广上级，不改变身份
                    $promoteParentId = $inviter['id'];
                } else {
                    // 身份海报：只绑定层级上级，不绑定推广上级
                    $validRoles = ['dashou', 'guanshi', 'zuzhang', 'shangjia'];
                    if (in_array($inviteType, $validRoles)) {
                        $newRoleType = $inviteType;
                        $parentId = $inviter['id'];
                    }
                }
            }
            
            $username = 'wx_' . substr($openid, -8) . Random::numeric(4);
            $password = Random::alnum(16);
            $user = \app\common\model\User::create([
                'username' => $username,
                'nickname' => $nickname ?: '微信用户' . Random::numeric(6),
                'avatar'   => $avatar ?: '',
                'password' => $password,
                'openid'   => $openid,
                'role_type' => $newRoleType,
                'parent_id' => $parentId,
                'promote_parent_id' => $promoteParentId,
                'status'   => 'normal'
            ]);
            $isNewUser = true;
        } else {
            // 老用户：不再用微信返回的昵称和头像覆盖，避免多端登录把手动设置的信息改掉
            // 只确保 openid 最新
            $user->openid = $openid;
            
            // 老用户：只有推广海报才绑定推广上级
            if ($inviter && $inviteType === 'promote' && empty($user->promote_parent_id)) {
                $user->promote_parent_id = $inviter['id'];
            }
            
            $user->save();
        }
        
        // 登录用户
        $ret = $this->auth->direct($user->id);
        if (!$ret) {
            $this->error($this->auth->getError());
        }
        
        // 保存session_key到缓存（用于后续解密手机号）
        // 注意：session_key有效期较短，建议设置过期时间
        cache('wx_session_key_' . $openid, $sessionKey, 7200); // 2小时过期
        
        // 获取用户信息
        $userInfo = $this->auth->getUserinfo();
        $userInfo['is_new_user'] = $isNewUser;
        // getUserinfo已经包含了mobile和role_type字段，如果没有则从数据库查询
        if (!isset($userInfo['mobile'])) {
            $userMobile = \think\Db::name('user')->where('id', $user->id)->value('mobile');
            $userInfo['mobile'] = $userMobile ?: '';
        }
        if (!isset($userInfo['role_type'])) {
            $userRoleType = \think\Db::name('user')->where('id', $user->id)->value('role_type');
            // 如果没有设置角色类型，根据客户端类型设置默认值
            $defaultRoleType = ($clientType === 'client') ? 'user' : 'weironzheng';
            $userInfo['role_type'] = $userRoleType ?: $defaultRoleType;
        }
        
        $this->success('登录成功', $userInfo);
    }
    
    /**
     * 处理头像URL（如果是临时路径则返回空）
     * 注意：微信小程序的临时文件路径无法从服务器下载，需要前端先上传
     */
    private function downloadAndUploadAvatar($avatarUrl)
    {
        // 如果是微信临时路径，返回空（前端应该已经上传过了）
        if (strpos($avatarUrl, 'tmp/') !== false || strpos($avatarUrl, 'http://tmp/') !== false) {
            // 微信小程序的临时文件路径，无法从服务器下载
            // 如果前端没有上传成功，这里返回空，用户可以在设置页面重新上传
            return '';
        }
        
        // 如果已经是服务器路径（以/uploads/开头），直接返回
        if (strpos($avatarUrl, '/uploads/') === 0) {
            return $avatarUrl;
        }
        
        // 如果是完整的HTTP/HTTPS URL（非临时路径），尝试下载并上传
        if (preg_match('/^https?:\/\//', $avatarUrl) && strpos($avatarUrl, 'tmp') === false) {
            try {
                // 下载图片
                $imageData = @file_get_contents($avatarUrl);
                if (!$imageData) {
                    return '';
                }
                
                // 保存到临时文件
                $tempFile = sys_get_temp_dir() . '/' . uniqid() . '.jpg';
                file_put_contents($tempFile, $imageData);
                
                // 使用Upload类上传
                $file = new File($tempFile, 'image/jpeg', 'avatar.jpg');
                $upload = new Upload($file);
                $attachment = $upload->upload();
                
                // 删除临时文件
                @unlink($tempFile);
                
                return $attachment->url;
            } catch (\Exception $e) {
                \think\Log::write('下载头像失败：' . $e->getMessage(), 'error');
                return '';
            }
        }
        
        return $avatarUrl;
    }
    
    /**
     * 绑定手机号
     *
     * @ApiMethod (POST)
     * @ApiParams (name="encryptedData", type="string", required=true, description="加密数据")
     * @ApiParams (name="iv", type="string", required=true, description="初始向量")
     */
    public function bindPhone()
    {
        // 此接口需要登录，通过Auth自动获取当前用户
        $user = $this->auth->getUser();
        if (!$user) {
            $this->error('请先登录');
        }
        
        $encryptedData = $this->request->post('encryptedData');
        $iv = $this->request->post('iv');
        
        if (!$encryptedData || !$iv) {
            $this->error('参数不完整');
        }
        
        // 检查用户是否有openid（微信用户）
        if (!$user->openid) {
            $this->error('当前账号不是微信用户，无法绑定手机号');
        }
        
        // 从缓存获取session_key（在wechatLogin时已存储）
        $sessionKey = cache('wx_session_key_' . $user->openid);
        if (!$sessionKey) {
            $this->error('session_key已过期，请重新登录');
        }
        
        // 解密手机号
        $aesKey = base64_decode($sessionKey);
        $aesIV = base64_decode($iv);
        $aesCipher = base64_decode($encryptedData);
        
        $result = openssl_decrypt($aesCipher, "AES-128-CBC", $aesKey, OPENSSL_RAW_DATA, $aesIV);
        if ($result === false) {
            $this->error('解密失败，请检查参数');
        }
        
        $data = json_decode($result, true);
        if (!$data || !isset($data['phoneNumber'])) {
            $this->error('解密失败，无法获取手机号');
        }
        
        $mobile = $data['phoneNumber'];
        
        // 验证手机号格式
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error('手机号格式不正确');
        }
        
        // 检查手机号是否已被使用
        $exists = \app\common\model\User::where('mobile', $mobile)->where('id', '<>', $user->id)->find();
        if ($exists) {
            $this->error('该手机号已被其他账号绑定');
        }
        
        // 更新用户手机号（使用数组方式更新避免属性不存在错误）
        $verification = $user->verification;
        $verification->mobile = 1;
        // 使用save方法传入数组更新
        $user->save([
            'mobile' => $mobile,
            'verification' => json_encode($verification)
        ]);
        
        $this->success('绑定成功', ['mobile' => $mobile]);
    }

    /**
     * 第三方登录
     *
     * @ApiMethod (POST)
     * @ApiParams (name="platform", type="string", required=true, description="平台名称")
     * @ApiParams (name="code", type="string", required=true, description="Code码")
     */
    public function third()
    {
        $url = url('user/index');
        $platform = $this->request->post("platform");
        $code = $this->request->post("code");
        $config = get_addon_config('third');
        if (!$config || !isset($config[$platform])) {
            $this->error(__('Invalid parameters'));
        }
        $app = new \addons\third\library\Application($config);
        //通过code换access_token和绑定会员
        $result = $app->{$platform}->getUserInfo(['code' => $code]);
        if ($result) {
            $loginret = \addons\third\library\Service::connect($platform, $result);
            if ($loginret) {
                $data = [
                    'userinfo'  => $this->auth->getUserinfo(),
                    'thirdinfo' => $result
                ];
                $this->success(__('Logged in successful'), $data);
            }
        }
        $this->error(__('Operation failed'), $url);
    }

    /**
     * 重置密码
     *
     * @ApiMethod (POST)
     * @ApiParams (name="mobile", type="string", required=true, description="手机号")
     * @ApiParams (name="newpassword", type="string", required=true, description="新密码")
     * @ApiParams (name="captcha", type="string", required=true, description="验证码")
     */
    public function resetpwd()
    {
        $type = $this->request->post("type", "mobile");
        $mobile = $this->request->post("mobile");
        $email = $this->request->post("email");
        $newpassword = $this->request->post("newpassword");
        $captcha = $this->request->post("captcha");
        if (!$newpassword || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        //验证Token
        if (!Validate::make()->check(['newpassword' => $newpassword], ['newpassword' => 'require|regex:\S{6,30}'])) {
            $this->error(__('Password must be 6 to 30 characters'));
        }
        if ($type == 'mobile') {
            if (!Validate::regex($mobile, "^1\d{10}$")) {
                $this->error(__('Mobile is incorrect'));
            }
            $user = \app\common\model\User::getByMobile($mobile);
            if (!$user) {
                $this->error(__('User not found'));
            }
            $ret = Sms::check($mobile, $captcha, 'resetpwd');
            if (!$ret) {
                $this->error(__('Captcha is incorrect'));
            }
            Sms::flush($mobile, 'resetpwd');
        } else {
            if (!Validate::is($email, "email")) {
                $this->error(__('Email is incorrect'));
            }
            $user = \app\common\model\User::getByEmail($email);
            if (!$user) {
                $this->error(__('User not found'));
            }
            $ret = Ems::check($email, $captcha, 'resetpwd');
            if (!$ret) {
                $this->error(__('Captcha is incorrect'));
            }
            Ems::flush($email, 'resetpwd');
        }
        //模拟一次登录
        $this->auth->direct($user->id);
        $ret = $this->auth->changepwd($newpassword, '', true);
        if ($ret) {
            $this->success(__('Reset password successful'));
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 获取用户信息（包含角色信息和标签）
     */
    public function info()
    {
        $userInfo = $this->auth->getUserinfo();
        
        // 直接从数据库获取最新的角色类型（必须绕过Auth缓存）
        $latestUser = \think\Db::name('user')->where('id', $this->auth->id)->field('role_type')->find();
        $userInfo['role_type'] = $latestUser ? $latestUser['role_type'] : '';
        
        // 处理头像URL（自动判断云存储或本地存储）
        if (!empty($userInfo['avatar'])) {
            // 检查云存储是否启用
            $useCos = is_cos_enabled();
            
            if ($useCos) {
                // 插件启用，使用cdnurl处理（可能是云存储）
                $userInfo['avatar'] = cdnurl($userInfo['avatar'], true);
            } else {
                // 插件禁用，使用本地URL
                $userInfo['avatar'] = request()->domain() . $userInfo['avatar'];
            }
        }
        
        // 获取用户标签
        $tags = \think\Db::name('user_tag')
            ->alias('ut')
            ->join('tag t', 'ut.tag_id = t.id')
            ->where('ut.user_id', $this->auth->id)
            ->where('t.status', 'normal')
            ->field('t.id, t.name, t.color')
            ->order('t.sort desc, t.id desc')
            ->select();
        
        $userInfo['tags'] = $tags ?: [];
        
        // 获取上级信息（头像和昵称）
        if (!empty($userInfo['parent_id']) && $userInfo['parent_id'] > 0) {
            $parent = \think\Db::name('user')
                ->where('id', $userInfo['parent_id'])
                ->field('id, nickname, avatar')
                ->find();
            
            if ($parent) {
                $userInfo['parent_nickname'] = $parent['nickname'];
                // 处理上级头像URL
                if (!empty($parent['avatar'])) {
                    $useCos = is_cos_enabled();
                    if ($useCos) {
                        $userInfo['parent_avatar'] = cdnurl($parent['avatar'], true);
                    } else {
                        $userInfo['parent_avatar'] = request()->domain() . $parent['avatar'];
                    }
                } else {
                    $userInfo['parent_avatar'] = '';
                }
            }
        }
        
        $this->success('获取成功', $userInfo);
    }

    /**
     * 获取佣金统计
     */
    public function commissionStats()
    {
        // 总收入
        $totalIncome = \think\Db::name('commission_log')
            ->where('user_id', $this->auth->id)
            ->where('type', 'income')
            ->sum('amount');
        
        // 总支出
        $totalExpense = \think\Db::name('commission_log')
            ->where('user_id', $this->auth->id)
            ->where('type', 'expense')
            ->sum('amount');
        
        // 总佣金 = 总收入 - 总支出
        $total = ($totalIncome ?: 0) - ($totalExpense ?: 0);
        
        // 本月收入
        $monthStart = strtotime(date('Y-m-01 00:00:00'));
        $monthIncome = \think\Db::name('commission_log')
            ->where('user_id', $this->auth->id)
            ->where('type', 'income')
            ->where('createtime', '>=', $monthStart)
            ->sum('amount');
        
        $this->success('获取成功', [
            'total' => $total,
            'total_income' => $totalIncome ?: 0,
            'total_expense' => $totalExpense ?: 0,
            'month_income' => $monthIncome ?: 0
        ]);
    }

    /**
     * 获取佣金记录（包含所有余额变动）
     */
    public function commissionLog()
    {
        $type = $this->request->get('type', ''); // income或expense
        $page = $this->request->get('page/d', 1);
        $limit = $this->request->get('limit/d', 10);
        
        $where = ['user_id' => $this->auth->id];
        
        if ($type && in_array($type, ['income', 'expense'])) {
            $where['type'] = $type;
        }
        
        $list = \think\Db::name('commission_log')
            ->where($where)
            ->order('createtime desc')
            ->page($page, $limit)
            ->select();
        
        // 关联查询订单信息
        foreach ($list as &$item) {
            if ($item['order_id']) {
                $order = \think\Db::name('order')->where('id', $item['order_id'])->find();
                $item['order_title'] = $order ? $order['title'] : '';
                $item['order_no'] = $order ? $order['order_no'] : '';
            }
        }
        
        $total = \think\Db::name('commission_log')->where($where)->count();
        
        $this->success('获取成功', [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    /**
     * 获取余额记录
     */
    public function moneyLog()
    {
        $type = $this->request->get('type', ''); // income或expense
        $page = $this->request->get('page/d', 1);
        $limit = $this->request->get('limit/d', 10);
        
        $where = ['user_id' => $this->auth->id];
        
        // 根据type筛选
        if ($type === 'income') {
            $where['money'] = ['>', 0];
        } else if ($type === 'expense') {
            $where['money'] = ['<', 0];
        }
        
        $list = \think\Db::name('user_money_log')
            ->where($where)
            ->order('createtime desc')
            ->page($page, $limit)
            ->select();
        
        // 处理数据格式
        foreach ($list as &$item) {
            $item['amount'] = number_format(abs($item['money']), 2, '.', '');
            $item['type'] = $item['money'] >= 0 ? 'income' : 'expense';
        }
        unset($item);
        
        $total = \think\Db::name('user_money_log')->where($where)->count();
        
        // 统计总收入和总支出
        $totalIncome = \think\Db::name('user_money_log')
            ->where(['user_id' => $this->auth->id, 'money' => ['>', 0]])
            ->sum('money');
        $totalExpense = abs(\think\Db::name('user_money_log')
            ->where(['user_id' => $this->auth->id, 'money' => ['<', 0]])
            ->sum('money'));
        
        $this->success('获取成功', [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'stats' => [
                'total_income' => number_format($totalIncome, 2, '.', ''),
                'total_expense' => number_format($totalExpense, 2, '.', '')
            ]
        ]);
    }

    /**
     * 申请提现（自动到账）
     */
    public function withdraw()
    {
        $amount = $this->request->post('amount/f', 0);
        $accountType = $this->request->post('account_type', 'wechat');
        $qrcodeImage = $this->request->post('qrcode_image', '');
        $realName = $this->request->post('real_name', '');
        
        // 金额基本校验
        if (!is_numeric($amount) || $amount <= 0) {
            $this->error('提现金额无效');
        }
        // 金额精度校验（最多2位小数）
        if (round($amount, 2) != $amount) {
            $this->error('提现金额最多保留2位小数');
        }
        $amount = round($amount, 2);
        
        // 获取提现商户配置
        $merchantConfig = \think\Db::name('withdraw_merchant_config')->where('id', 1)->where('status', 'normal')->find();
        $minAmount = floatval($merchantConfig['min_amount'] ?? 1);
        $maxAmount = floatval($merchantConfig['max_amount'] ?? 5000);
        $dailyLimit = intval($merchantConfig['daily_limit'] ?? 3); // 每日提现次数限制
        $dailyAmountLimit = floatval($merchantConfig['daily_amount_limit'] ?? 10000); // 每日提现金额限制
        
        // 校验提现金额范围
        if ($minAmount > 0 && $amount < $minAmount) {
            $this->error("提现金额不能少于{$minAmount}元");
        }
        if ($maxAmount > 0 && $amount > $maxAmount) {
            $this->error("单次提现金额不能超过{$maxAmount}元");
        }
        
        // 每日提现次数限制
        $todayStart = strtotime(date('Y-m-d'));
        $todayWithdrawCount = \think\Db::name('withdraw_log')
            ->where('user_id', $this->auth->id)
            ->where('createtime', '>=', $todayStart)
            ->where('status', '<>', 'failed')
            ->count();
        if ($dailyLimit > 0 && $todayWithdrawCount >= $dailyLimit) {
            $this->error("每日最多提现{$dailyLimit}次，明天再来吧");
        }
        
        // 每日提现金额限制
        $todayWithdrawAmount = \think\Db::name('withdraw_log')
            ->where('user_id', $this->auth->id)
            ->where('createtime', '>=', $todayStart)
            ->where('status', '<>', 'failed')
            ->sum('amount');
        if ($dailyAmountLimit > 0 && ($todayWithdrawAmount + $amount) > $dailyAmountLimit) {
            $remaining = max(0, $dailyAmountLimit - $todayWithdrawAmount);
            $this->error("今日提现额度剩余{$remaining}元");
        }
        
        // 获取系统配置，判断是否开启审核提现
        $systemConfig = \app\admin\model\peiwan\SystemConfig::getSystemConfig();
        $isWithdrawAudit = intval($systemConfig['is_withdraw_audit'] ?? 0);
        
        // 审核模式下验证必填参数
        if ($isWithdrawAudit) {
            if (empty($qrcodeImage)) {
                $this->error('请上传收款二维码');
            }
            if (empty($realName)) {
                $this->error('请填写真实姓名');
            }
        } else {
            // 非审核模式，只支持微信提现
            if ($accountType !== 'wechat') {
                $this->error('目前只支持微信提现');
            }
        }
        
        // 获取用户信息
        $user = $this->auth->getUser();
        
        // 非审核模式下需要绑定微信
        if (!$isWithdrawAudit && (!$user || !$user->openid)) {
            $this->error('用户未绑定微信，无法提现');
        }
        
        // 检查解锁商户配置，是否需要解锁才能提现
        $unlockConfig = \think\Db::name('unlock_merchant_config')->where('id', 1)->where('status', 'normal')->find();
        if ($unlockConfig && $unlockConfig['require_withdraw_unlock'] == 1) {
            // 检查用户的剩余解锁次数
            $userInfo = \think\Db::name('user')->where('id', $this->auth->id)->find();
            $remainingTimes = intval($userInfo['unlock_times_remaining'] ?? 0);
            
            if ($remainingTimes <= 0) {
                // 解锁次数不足，返回错误并附带解锁配置信息
                $this->error('您尚未解锁提现功能或次数已用完，请先购买解锁权限', [
                    'need_unlock' => true,
                    'unlock_price' => floatval($unlockConfig['unlock_price']),
                    'unlock_times' => intval($unlockConfig['unlock_times'])
                ]);
            }
        }
        
        \think\Db::startTrans();
        try {
            // 检查是否有进行中的提现请求（防止重复提交）
            $pendingWithdraw = \think\Db::name('withdraw_log')
                ->where('user_id', $this->auth->id)
                ->whereIn('status', ['pending', 'processing'])
                ->lock(true)
                ->find();
            if ($pendingWithdraw) {
                throw new \think\Exception('您有一笔提现正在处理中，请稍后再试');
            }
            
            // 检查用户余额
            $userInfo = \think\Db::name('user')->where('id', $this->auth->id)->lock(true)->find();
            
            // 计算提现上级分成
            $shareInfo = $this->calculateWithdrawParentShare($this->auth->id, $amount);
            $totalRequired = $amount + $shareInfo['share_amount']; // 总共需要的余额
            
            if ($userInfo['money'] < $totalRequired) {
                if ($shareInfo['enabled'] && $shareInfo['share_amount'] > 0) {
                    throw new \think\Exception('余额不足，提现' . $amount . '元需支付' . $shareInfo['share_amount'] . '元给上级，共需' . $totalRequired . '元');
                } else {
                    throw new \think\Exception('余额不足');
                }
            }
            
            // 生成提现订单号
            $outTradeNo = 'TX' . date('YmdHis') . rand(1000, 9999);
            
            // 根据模式创建不同的提现记录
            if ($isWithdrawAudit) {
                // 审核模式：先扣余额（提现金额+分成金额），创建待审核记录
                // 分成在审核通过时执行，拒绝时退回全部金额
                $totalDeduct = $amount + $shareInfo['share_amount'];
                \think\Db::name('user')->where('id', $this->auth->id)->setDec('money', $totalDeduct);
                \think\Db::name('user_money_log')->insert([
                    'user_id' => $this->auth->id,
                    'money' => -$totalDeduct,
                    'before' => $userInfo['money'],
                    'after' => $userInfo['money'] - $totalDeduct,
                    'memo' => '申请提现(待审核)' . ($shareInfo['share_amount'] > 0 ? '，含上级分成' . $shareInfo['share_amount'] . '元' : ''),
                    'createtime' => time()
                ]);
                
                $withdrawLogId = \think\Db::name('withdraw_log')->insertGetId([
                    'user_id' => $this->auth->id,
                    'amount' => $amount,
                    'account_type' => 'qrcode',
                    'account' => '',
                    'account_name' => $realName,
                    'qrcode_image' => $qrcodeImage,
                    'real_name' => $realName,
                    'withdraw_type' => 'audit',
                    'status' => 'audit_pending',
                    'out_trade_no' => $outTradeNo,
                    'parent_share_amount' => $shareInfo['share_amount'],
                    'parent_share_user_id' => $shareInfo['parent_id'],
                    'parent_share_rate' => $shareInfo['rate'],
                    'createtime' => time(),
                    'updatetime' => time()
                ]);
                
                if ($unlockConfig && $unlockConfig['require_withdraw_unlock'] == 1) {
                    \think\Db::name('user')->where('id', $this->auth->id)->where('unlock_times_remaining', '>', 0)->setDec('unlock_times_remaining', 1);
                }
                
                \think\Db::commit();
                $this->success('提现申请已提交，请等待审核');
            } else {
                // 自动模式：调用商户转账
                // 获取提现商户配置
                $merchantConfig = \think\Db::name('withdraw_merchant_config')->where('id', 1)->where('status', 'normal')->find();
                if (!$merchantConfig || empty($merchantConfig['merchant_id']) || empty($merchantConfig['api_key'])) {
                    throw new \think\Exception('提现商户配置未设置或已禁用');
                }
                
                // 创建提现记录（状态为pending，先不扣余额）
                $withdrawLogId = \think\Db::name('withdraw_log')->insertGetId([
                    'user_id' => $this->auth->id,
                    'amount' => $amount,
                    'account_type' => 'wechat',
                    'account' => $user->openid,
                    'account_name' => $user->nickname ?: '用户',
                    'withdraw_type' => 'auto',
                    'status' => 'pending', // 待确认状态
                    'out_trade_no' => $outTradeNo,
                    'createtime' => time(),
                    'updatetime' => time()
                ]);
                
                \think\Db::commit();
                
                // 调用微信转账接口
                try {
                    $certPath = !empty($merchantConfig['cert_path']) ? ROOT_PATH . 'public' . $merchantConfig['cert_path'] : '';
                    $certKeyPath = !empty($merchantConfig['cert_key_path']) ? ROOT_PATH . 'public' . $merchantConfig['cert_key_path'] : '';
                    
                    $transfer = new \app\common\library\WechatTransfer(
                        $merchantConfig['merchant_id'],
                        $merchantConfig['api_key'],
                        $certPath,
                        $certKeyPath
                    );
                    
                    $result = $transfer->transfer(
                        $user->openid,
                        $amount,
                        '余额提现',
                        $outTradeNo
                    );
                    
                    $transferState = $result['state'] ?? 'SUCCESS';
                    $packageInfo = $result['package_info'] ?? '';
                    $transferBillNo = $result['transfer_bill_no'] ?? '';
                    
                    // 更新提现记录
                    \think\Db::name('withdraw_log')->where('id', $withdrawLogId)->update([
                        'transfer_bill_no' => $transferBillNo,
                        'updatetime' => time()
                    ]);
                    
                    if ($transferState === 'WAIT_USER_CONFIRM' && !empty($packageInfo)) {
                        // 需要用户确认收款，此时不扣余额，等用户确认后再扣
                        \think\Db::name('withdraw_log')->where('id', $withdrawLogId)->update([
                            'status' => 'wait_confirm', // 待用户确认
                            'remark' => '等待用户确认收款'
                        ]);
                        
                        $configForAppid = \think\Db::name('system_config')->where('id', 1)->find();
                        $returnAppid = $configForAppid['wxappid'] ?? '';
                        
                        $this->success('请确认收款', [
                            'need_confirm' => true,
                            'package_info' => $packageInfo,
                            'mch_id' => $merchantConfig['merchant_id'],
                            'app_id' => $returnAppid,
                            'withdraw_id' => $withdrawLogId,
                            'transfer_bill_no' => $transferBillNo
                        ]);
                    } else {
                        // 直接成功（无需确认），扣除余额并处理分成
                        \think\Db::startTrans();
                        \think\Db::name('user')->where('id', $this->auth->id)->setDec('money', $amount);
                        \think\Db::name('user_money_log')->insert([
                            'user_id' => $this->auth->id,
                            'money' => -$amount,
                            'before' => $userInfo['money'],
                            'after' => $userInfo['money'] - $amount,
                            'memo' => '提现成功',
                            'createtime' => time()
                        ]);
                        
                        // 如果有分成，执行分成逻辑
                        if ($shareInfo['enabled'] && $shareInfo['share_amount'] > 0) {
                            $this->executeWithdrawParentShare($this->auth->id, $amount, $shareInfo, $withdrawLogId);
                        }
                        
                        \think\Db::name('withdraw_log')->where('id', $withdrawLogId)->update([
                            'status' => 'success',
                            'remark' => '转账成功，单号：' . $transferBillNo
                        ]);
                        if ($unlockConfig && $unlockConfig['require_withdraw_unlock'] == 1) {
                            \think\Db::name('user')->where('id', $this->auth->id)->where('unlock_times_remaining', '>', 0)->setDec('unlock_times_remaining', 1);
                        }
                        \think\Db::commit();
                        
                        $this->success('提现成功，已到账');
                    }
                } catch (\think\exception\HttpResponseException $e) {
                    throw $e;
                } catch (\Exception $e) {
                    // 转账失败，更新记录状态（未扣余额，无需回滚）
                    \think\Db::name('withdraw_log')->where('id', $withdrawLogId)->update([
                        'status' => 'failed',
                        'updatetime' => time(),
                        'remark' => '转账失败：' . ($e->getMessage() ?: '未知错误')
                    ]);
                    
                    $this->error('提现失败：' . ($e->getMessage() ?: '未知错误'));
                }
            }
        } catch (\think\Exception $e) {
            \think\Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * 确认提现收款成功（用户在微信确认收款后调用）
     */
    public function confirmWithdraw()
    {
        $withdrawId = $this->request->post('withdraw_id/d', 0);
        
        if ($withdrawId <= 0) {
            $this->error('参数错误');
        }
        
        \think\Db::startTrans();
        try {
            // 查找提现记录（加锁防止并发）
            $withdrawLog = \think\Db::name('withdraw_log')
                ->where('id', $withdrawId)
                ->where('user_id', $this->auth->id)
                ->where('status', 'wait_confirm')
                ->lock(true)
                ->find();
            
            if (!$withdrawLog) {
                throw new \think\Exception('提现记录不存在或已处理');
            }
            
            $amount = floatval($withdrawLog['amount']);
            // 获取用户信息并加锁
            $userInfo = \think\Db::name('user')->where('id', $this->auth->id)->lock(true)->find();
            
            // 计算提现上级分成
            $shareInfo = $this->calculateWithdrawParentShare($this->auth->id, $amount);
            $totalRequired = $amount + $shareInfo['share_amount'];
            
            // 再次检查余额（包含分成金额）
            if ($userInfo['money'] < $totalRequired) {
                if ($shareInfo['enabled'] && $shareInfo['share_amount'] > 0) {
                    throw new \think\Exception('余额不足，提现' . $amount . '元需支付' . $shareInfo['share_amount'] . '元给上级，共需' . $totalRequired . '元');
                } else {
                    throw new \think\Exception('余额不足，无法完成提现');
                }
            }
            
            // 扣除余额
            \think\Db::name('user')->where('id', $this->auth->id)->setDec('money', $amount);
            
            // 记录余额变动
            \think\Db::name('user_money_log')->insert([
                'user_id' => $this->auth->id,
                'money' => -$amount,
                'before' => $userInfo['money'],
                'after' => $userInfo['money'] - $amount,
                'memo' => '提现成功',
                'createtime' => time()
            ]);
            
            // 如果有分成，执行分成逻辑
            if ($shareInfo['enabled'] && $shareInfo['share_amount'] > 0) {
                $this->executeWithdrawParentShare($this->auth->id, $amount, $shareInfo, $withdrawId);
            }
            
            // 更新提现记录状态
            \think\Db::name('withdraw_log')->where('id', $withdrawId)->update([
                'status' => 'success',
                'updatetime' => time(),
                'remark' => '用户已确认收款'
            ]);
            
            // 扣减解锁次数
            $unlockConfig = \think\Db::name('unlock_merchant_config')->where('id', 1)->where('status', 'normal')->find();
            if ($unlockConfig && $unlockConfig['require_withdraw_unlock'] == 1) {
                \think\Db::name('user')->where('id', $this->auth->id)->where('unlock_times_remaining', '>', 0)->setDec('unlock_times_remaining', 1);
            }
            
            \think\Db::commit();
            $this->success('提现成功');
        } catch (\think\Exception $e) {
            \think\Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * 获取提现记录
     */
    public function withdrawLog()
    {
        $status = $this->request->get('status', '');
        $page = $this->request->get('page/d', 1);
        $limit = $this->request->get('limit/d', 10);
        
        $where = ['user_id' => $this->auth->id];
        
        if ($status && in_array($status, ['pending', 'processing', 'audit_pending', 'success', 'failed', 'rejected'])) {
            $where['status'] = $status;
        }
        
        $list = \think\Db::name('withdraw_log')
            ->where($where)
            ->order('createtime desc')
            ->page($page, $limit)
            ->select();
        
        // 处理图片URL
        $useCos = is_cos_enabled();
        foreach ($list as &$item) {
            if (!empty($item['qrcode_image']) && !preg_match('/^https?:\/\//i', $item['qrcode_image'])) {
                $item['qrcode_image'] = $useCos ? cdnurl($item['qrcode_image'], true) : request()->domain() . $item['qrcode_image'];
            }
        }
        unset($item);
        
        $total = \think\Db::name('withdraw_log')->where($where)->count();
        
        $this->success('获取成功', [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    /**
     * 获取提现配置（是否开启审核模式等）
     */
    public function getWithdrawConfig()
    {
        // 获取系统配置
        $systemConfig = \app\admin\model\peiwan\SystemConfig::getSystemConfig();
        $isWithdrawAudit = intval($systemConfig['is_withdraw_audit'] ?? 0);
        
        // 获取提现商户配置
        $merchantConfig = \think\Db::name('withdraw_merchant_config')->where('id', 1)->where('status', 'normal')->find();
        $minAmount = floatval($merchantConfig['min_amount'] ?? 1);
        $maxAmount = floatval($merchantConfig['max_amount'] ?? 5000);
        $dailyLimit = intval($merchantConfig['daily_limit'] ?? 3);
        $dailyAmountLimit = floatval($merchantConfig['daily_amount_limit'] ?? 10000);
        
        // 获取用户今日已提现统计
        $todayStart = strtotime(date('Y-m-d'));
        $todayWithdrawCount = \think\Db::name('withdraw_log')
            ->where('user_id', $this->auth->id)
            ->where('createtime', '>=', $todayStart)
            ->where('status', '<>', 'failed')
            ->count();
        $todayWithdrawAmount = \think\Db::name('withdraw_log')
            ->where('user_id', $this->auth->id)
            ->where('createtime', '>=', $todayStart)
            ->where('status', '<>', 'failed')
            ->sum('amount');
        
        // 计算剩余次数和额度
        $remainingCount = $dailyLimit > 0 ? max(0, $dailyLimit - $todayWithdrawCount) : -1; // -1表示不限制
        $remainingAmount = $dailyAmountLimit > 0 ? max(0, $dailyAmountLimit - $todayWithdrawAmount) : -1;
        
        $this->success('获取成功', [
            'is_withdraw_audit' => $isWithdrawAudit == 1,
            'min_amount' => $minAmount,
            'max_amount' => $maxAmount,
            'daily_limit' => $dailyLimit,
            'daily_amount_limit' => $dailyAmountLimit,
            'today_withdraw_count' => $todayWithdrawCount,
            'today_withdraw_amount' => round($todayWithdrawAmount, 2),
            'remaining_count' => $remainingCount,
            'remaining_amount' => $remainingAmount > 0 ? round($remainingAmount, 2) : $remainingAmount
        ]);
    }

    /**
     * 获取提现解锁状态和配置
     */
    public function getUnlockStatus()
    {
        $user = $this->auth->getUser();
        $userInfo = \think\Db::name('user')->where('id', $this->auth->id)->find();
        
        // 获取解锁配置
        $unlockConfig = \think\Db::name('unlock_merchant_config')->where('id', 1)->where('status', 'normal')->find();
        
        // 如果没有配置，返回无需解锁状态
        if (!$unlockConfig) {
            $this->success('获取成功', [
                'require_withdraw_unlock' => false,
                'require_recharge_unlock' => false,
                'is_unlocked' => true,
                'remaining_times' => 0,
                'unlock_price' => 0,
                'unlock_times' => 0
            ]);
        }
        
        $remainingTimes = intval($userInfo['unlock_times_remaining'] ?? 0);
        $isUnlocked = $remainingTimes > 0;
        
        $this->success('获取成功', [
            'require_withdraw_unlock' => $unlockConfig['require_withdraw_unlock'] == 1,
            'require_recharge_unlock' => $unlockConfig['require_recharge_unlock'] == 1,
            'is_unlocked' => $isUnlocked,
            'remaining_times' => $remainingTimes,
            'unlock_price' => floatval($unlockConfig['unlock_price']),
            'unlock_times' => intval($unlockConfig['unlock_times'])
        ]);
    }

    /**
     * 获取聊天解锁状态和配置
     */
    public function getChatUnlockStatus()
    {
        $userInfo = \think\Db::name('user')->where('id', $this->auth->id)->find();
        
        // 获取解锁配置
        $unlockConfig = \think\Db::name('unlock_merchant_config')->where('id', 1)->where('status', 'normal')->find();
        
        if (!$unlockConfig) {
            $this->success('获取成功', [
                'require_chat_unlock' => false,
                'is_unlocked' => true,
                'remaining_times' => 0,
                'chat_unlock_price' => 0,
                'chat_unlock_times' => 0
            ]);
        }
        
        $remainingTimes = intval($userInfo['chat_unlock_times_remaining'] ?? 0);
        $isUnlocked = $remainingTimes > 0;
        
        $this->success('获取成功', [
            'require_chat_unlock' => $unlockConfig['require_chat_unlock'] == 1,
            'is_unlocked' => $isUnlocked,
            'remaining_times' => $remainingTimes,
            'chat_unlock_price' => floatval($unlockConfig['chat_unlock_price']),
            'chat_unlock_times' => intval($unlockConfig['chat_unlock_times'])
        ]);
    }

    /**
     * 购买聊天解锁权限（微信支付）
     * @ApiMethod (POST)
     * @param int quantity 购买次数（默认1次）
     */
    public function buyChatUnlock()
    {
        $quantity = $this->request->post('quantity/d', 1);
        if ($quantity < 1) {
            $quantity = 1;
        }
        
        $unlockConfig = \think\Db::name('unlock_merchant_config')->where('id', 1)->where('status', 'normal')->find();
        
        if (!$unlockConfig) {
            $this->error('解锁配置不存在');
        }
        
        if ($unlockConfig['require_chat_unlock'] != 1) {
            $this->error('当前无需购买聊天解锁权限');
        }
        
        $unitPrice = floatval($unlockConfig['chat_unlock_price']);
        $unitTimes = intval($unlockConfig['chat_unlock_times']);
        
        $totalPrice = $unitPrice * $quantity;
        $totalTimes = $unitTimes * $quantity;
        
        if ($unitTimes <= 0) {
            $this->error('解锁配置错误，请联系管理员');
        }
        
        $user = $this->auth->getUser();
        if (!$user) {
            $this->error('用户信息获取失败，请重新登录');
        }
        
        $openid = $user->openid ?? '';
        if (empty($openid)) {
            $this->error('用户未绑定微信OpenID，无法使用微信支付');
        }
        
        // 如果价格为0，直接增加次数
        if ($totalPrice <= 0) {
            \think\Db::name('user')->where('id', $this->auth->id)->setInc('chat_unlock_times_remaining', $totalTimes);
            $userInfo = \think\Db::name('user')->where('id', $this->auth->id)->find();
            $this->success('解锁成功', [
                'remaining_times' => intval($userInfo['chat_unlock_times_remaining']),
                'unlock_times' => $totalTimes,
                'paid_amount' => 0
            ]);
        }
        
        // 检查解锁商户配置是否完整
        if (empty($unlockConfig['merchant_id']) || empty($unlockConfig['api_key'])) {
            $this->error('解锁商户配置不完整');
        }
        
        $epay = get_addon_info('epay');
        if (empty($epay) || !$epay['state']) {
            $this->error('请先在后台安装并配置微信支付宝整合插件');
        }
        
        // 创建聊天解锁订单
        $orderid = 'CL' . date("YmdHis") . sprintf("%08d", $this->auth->id) . mt_rand(1000, 9999);
        $request = \think\Request::instance();
        
        $orderData = [
            'orderid' => $orderid,
            'user_id' => $this->auth->id,
            'amount' => $totalPrice,
            'unlock_times' => $totalTimes,
            'quantity' => $quantity,
            'unlock_type' => 'chat', // 聊天解锁
            'paytype' => 'wechat',
            'status' => 'created',
            'ip' => $request->ip(),
            'createtime' => time(),
            'updatetime' => time()
        ];
        
        \think\Db::name('unlock_order')->insert($orderData);
        
        $notifyurl = $request->root(true) . '/api/user/chatUnlockNotify';
        $returnurl = $request->root(true) . '/api/user/chatUnlockReturn';
        
        $customConfig = [
            'mch_id' => $unlockConfig['merchant_id'],
            'key' => $unlockConfig['api_key'],
        ];
        
        if (!empty($unlockConfig['cert_path'])) {
            $certPath = $unlockConfig['cert_path'];
            if (strpos($certPath, '/') !== 0 && strpos($certPath, ':') === false) {
                $certPath = ROOT_PATH . $certPath;
            }
            $customConfig['cert_client'] = $certPath;
        }
        if (!empty($unlockConfig['cert_key_path'])) {
            $certKeyPath = $unlockConfig['cert_key_path'];
            if (strpos($certKeyPath, '/') !== 0 && strpos($certKeyPath, ':') === false) {
                $certKeyPath = ROOT_PATH . $certKeyPath;
            }
            $customConfig['cert_key'] = $certKeyPath;
        }
        
        $params = [
            'amount' => $totalPrice,
            'orderid' => $orderid,
            'type' => 'wechat',
            'title' => "购买聊天解锁（{$totalTimes}次）",
            'notifyurl' => $notifyurl,
            'returnurl' => $returnurl,
            'method' => 'miniapp',
            'openid' => $openid,
            'custom' => $customConfig
        ];
        
        try {
            $response = \addons\epay\library\Service::submitOrder($params);
            
            $responseData = null;
            if (is_object($response)) {
                if (method_exists($response, 'toArray')) {
                    $responseData = $response->toArray();
                } elseif (method_exists($response, 'getContent')) {
                    $content = $response->getContent();
                    $responseData = json_decode($content, true) ?: $content;
                } else {
                    $responseData = (array)$response;
                }
            } else {
                $responseData = $response;
            }
            
        } catch (\Exception $e) {
            \think\Db::name('unlock_order')->where('orderid', $orderid)->update([
                'status' => 'failed',
                'memo' => $e->getMessage() ?: '未知错误',
                'updatetime' => time()
            ]);
            $this->error('支付发起失败：' . $e->getMessage());
        }
        
        $this->success('支付发起成功', [
            'payData' => $responseData,
            'orderid' => $orderid
        ]);
    }

    /**
     * 聊天解锁支付回调
     */
    public function chatUnlockNotify()
    {
        $paytype = $this->request->param('type', 'wechat');
        $pay = \addons\epay\library\Service::checkNotify($paytype);
        if (!$pay) {
            return 'fail';
        }
        
        $data = $pay->verify();
        if (!$data) {
            return $pay->success()->send();
        }
        
        $orderid = $data['out_trade_no'];
        
        $order = \think\Db::name('unlock_order')->where('orderid', $orderid)->find();
        if (!$order) {
            return $pay->success()->send();
        }
        
        if ($order['status'] == 'paid') {
            return $pay->success()->send();
        }
        
        \think\Db::startTrans();
        try {
            \think\Db::name('unlock_order')->where('orderid', $orderid)->update([
                'status' => 'paid',
                'paytime' => time(),
                'updatetime' => time(),
                'transaction_id' => $data['transaction_id'] ?? ''
            ]);
            
            \think\Db::name('user')->where('id', $order['user_id'])->setInc('chat_unlock_times_remaining', $order['unlock_times']);
            
            \think\Db::commit();
        } catch (\Exception $e) {
            \think\Db::rollback();
            return 'fail';
        }
        
        return $pay->success()->send();
    }

    /**
     * 聊天解锁支付返回
     */
    public function chatUnlockReturn()
    {
        $this->success('支付成功');
    }

    /**
     * 获取同款小程序内容
     */
    public function getMiniappContent()
    {
        $intro = \think\Db::name('miniapp_intro')->where('id', 1)->where('status', 'normal')->find();
        
        $content = '';
        $title = '同款小程序';
        $icon = '';
        if ($intro) {
            $content = $intro['content'] ?: '';
            $title = $intro['title'] ?: '同款小程序';
            $icon = $intro['icon'] ?: '';
        }
        
        $this->success('获取成功', [
            'title' => $title,
            'icon' => $icon,
            'content' => $content
        ]);
    }

    /**
     * 购买提现解锁权限（微信支付）
     * @ApiMethod (POST)
     * @param int quantity 购买次数（默认1次）
     */
    public function buyUnlock()
    {
        // 获取购买次数
        $quantity = $this->request->post('quantity/d', 1);
        if ($quantity < 1) {
            $quantity = 1;
        }
        
        // 获取解锁配置
        $unlockConfig = \think\Db::name('unlock_merchant_config')->where('id', 1)->where('status', 'normal')->find();
        
        if (!$unlockConfig) {
            $this->error('解锁配置不存在');
        }
        
        // 检查是否需要解锁
        if ($unlockConfig['require_withdraw_unlock'] != 1 && $unlockConfig['require_recharge_unlock'] != 1) {
            $this->error('当前无需购买解锁权限');
        }
        
        $unitPrice = floatval($unlockConfig['unlock_price']); // 单次价格
        $unitTimes = intval($unlockConfig['unlock_times']); // 单次解锁次数
        
        // 根据购买次数计算总价和总次数
        $totalPrice = $unitPrice * $quantity;
        $totalTimes = $unitTimes * $quantity;
        
        if ($unitTimes <= 0) {
            $this->error('解锁配置错误，请联系管理员');
        }
        
        // 获取用户信息
        $user = $this->auth->getUser();
        if (!$user) {
            $this->error('用户信息获取失败，请重新登录');
        }
        
        // 检查openid
        $openid = $user->openid ?? '';
        if (empty($openid)) {
            $this->error('用户未绑定微信OpenID，无法使用微信支付，请重新授权登录');
        }
        
        // 检查epay插件
        $epay = get_addon_info('epay');
        if (empty($epay) || !$epay['state']) {
            $this->error('请先在后台安装并配置微信支付宝整合插件');
        }
        
        // 检查解锁商户配置（使用独立商户）
        $epayConfig = get_addon_config('epay');
        if (empty($epayConfig['wechat']['miniapp_id'])) {
            \think\Log::write('[buyUnlock] 微信小程序AppID未配置');
            $this->error('微信支付配置不完整：小程序AppID未配置');
        }
        
        // 检查解锁商户配置是否完整
        if (empty($unlockConfig['merchant_id'])) {
            \think\Log::write('[buyUnlock] 解锁商户配置：商户号未配置');
            $this->error('解锁商户配置不完整：商户号未配置');
        }
        if (empty($unlockConfig['api_key'])) {
            \think\Log::write('[buyUnlock] 解锁商户配置：API密钥未配置');
            $this->error('解锁商户配置不完整：API密钥未配置');
        }
        
        // 如果价格为0，直接增加次数
        if ($totalPrice <= 0) {
            \think\Db::name('user')->where('id', $this->auth->id)->setInc('unlock_times_remaining', $totalTimes);
            $userInfo = \think\Db::name('user')->where('id', $this->auth->id)->find();
            $this->success('解锁成功', [
                'remaining_times' => intval($userInfo['unlock_times_remaining']),
                'unlock_times' => $totalTimes,
                'paid_amount' => 0
            ]);
        }
        
        // 创建解锁订单
        $orderid = 'UL' . date("YmdHis") . sprintf("%08d", $this->auth->id) . mt_rand(1000, 9999);
        $request = \think\Request::instance();
        
        $orderData = [
            'orderid' => $orderid,
            'user_id' => $this->auth->id,
            'amount' => $totalPrice,
            'unlock_times' => $totalTimes,
            'quantity' => $quantity, // 记录购买份数
            'paytype' => 'wechat',
            'status' => 'created',
            'ip' => $request->ip(),
            'createtime' => time(),
            'updatetime' => time()
        ];
        
        \think\Db::name('unlock_order')->insert($orderData);
        
        // 发起微信支付
        $notifyurl = $request->root(true) . '/api/user/unlockNotify';
        $returnurl = $request->root(true) . '/api/user/unlockReturn';
        
        // 构建自定义商户配置（使用解锁商户配置中的独立商户）
        $customConfig = [
            'mch_id' => $unlockConfig['merchant_id'],  // 使用解锁商户的商户号
            'key' => $unlockConfig['api_key'],          // 使用解锁商户的API密钥（V2）
        ];
        
        // 如果配置了证书路径，也添加进去
        if (!empty($unlockConfig['cert_path'])) {
            $certPath = $unlockConfig['cert_path'];
            // 处理相对路径
            if (strpos($certPath, '/') !== 0 && strpos($certPath, ':') === false) {
                $certPath = ROOT_PATH . $certPath;
            }
            $customConfig['cert_client'] = $certPath;
        }
        if (!empty($unlockConfig['cert_key_path'])) {
            $certKeyPath = $unlockConfig['cert_key_path'];
            // 处理相对路径
            if (strpos($certKeyPath, '/') !== 0 && strpos($certKeyPath, ':') === false) {
                $certKeyPath = ROOT_PATH . $certKeyPath;
            }
            $customConfig['cert_key'] = $certKeyPath;
        }
        
        $params = [
            'amount' => $totalPrice,
            'orderid' => $orderid,
            'type' => 'wechat',
            'title' => "购买解锁权限（{$totalTimes}次）",
            'notifyurl' => $notifyurl,
            'returnurl' => $returnurl,
            'method' => 'miniapp',
            'openid' => $openid,
            'custom' => $customConfig
        ];
        
        try {
            $response = \addons\epay\library\Service::submitOrder($params);
            
            // 处理返回数据
            $responseData = null;
            if (is_object($response)) {
                if (method_exists($response, 'toArray')) {
                    $responseData = $response->toArray();
                } elseif (method_exists($response, 'getContent')) {
                    $content = $response->getContent();
                    $responseData = json_decode($content, true) ?: $content;
                } else {
                    $responseData = (array)$response;
                }
            } else {
                $responseData = $response;
            }
            
        } catch (\Exception $e) {
            // 更新订单状态为失败
            \think\Db::name('unlock_order')->where('orderid', $orderid)->update([
                'status' => 'failed',
                'memo' => $e->getMessage() ?: '未知错误',
                'updatetime' => time()
            ]);
            
            $displayError = $e->getMessage() ?: '支付服务异常，请检查后台解锁商户配置是否正确';
            $this->error('创建支付订单失败：' . $displayError);
        }
        
        // 返回支付参数给前端
        $this->success('创建订单成功', [
            'orderid' => $orderid,
            'payData' => $responseData
        ]);
    }
    
    /**
     * 解锁支付回调
     */
    public function unlockNotify()
    {
        $paytype = $this->request->param('paytype', 'wechat');
        
        try {
            // 获取解锁商户配置
            $unlockConfig = \think\Db::name('unlock_merchant_config')->where('id', 1)->where('status', 'normal')->find();
            
            // 构建自定义商户配置
            $customConfig = [];
            if ($unlockConfig) {
                $customConfig['mch_id'] = $unlockConfig['merchant_id'];
                $customConfig['key'] = $unlockConfig['api_key'];
                
                if (!empty($unlockConfig['cert_path'])) {
                    $certPath = $unlockConfig['cert_path'];
                    if (strpos($certPath, '/') !== 0 && strpos($certPath, ':') === false) {
                        $certPath = ROOT_PATH . $certPath;
                    }
                    $customConfig['cert_client'] = $certPath;
                }
                if (!empty($unlockConfig['cert_key_path'])) {
                    $certKeyPath = $unlockConfig['cert_key_path'];
                    if (strpos($certKeyPath, '/') !== 0 && strpos($certKeyPath, ':') === false) {
                        $certKeyPath = ROOT_PATH . $certKeyPath;
                    }
                    $customConfig['cert_key'] = $certKeyPath;
                }
            }
            
            // 使用解锁商户配置验证回调
            $pay = \addons\epay\library\Service::checkNotify($paytype, $customConfig);
            if (!$pay) {
                return 'FAIL';
            }
            
            $data = $pay->verify();
            $orderid = $data['out_trade_no'];
            $payamount = $data['total_fee'] / 100;
            
            // 查找订单
            $order = \think\Db::name('unlock_order')->where('orderid', $orderid)->find();
            if (!$order) {
                return 'FAIL';
            }
            
            if ($order['status'] != 'created') {
                return $pay->success()->send();
            }
            
            // 验证金额
            if (abs($payamount - $order['amount']) > 0.01) {
                return 'FAIL';
            }
            
            // 更新订单状态
            \think\Db::name('unlock_order')->where('orderid', $orderid)->update([
                'status' => 'paid',
                'paytime' => time(),
                'updatetime' => time()
            ]);
            
            // 增加用户解锁次数
            \think\Db::name('user')->where('id', $order['user_id'])->setInc('unlock_times_remaining', $order['unlock_times']);
            
            return $pay->success()->send();
        } catch (\Exception $e) {
            return 'FAIL';
        }
    }
    
    /**
     * 解锁支付返回页面
     */
    public function unlockReturn()
    {
        $this->success('支付完成');
    }


    /**
     * 获取上级推荐人列表（管事、组长、总管）
     */
    public function getParentList()
    {
        $list = \think\Db::name('user')
            ->where('role_type', 'in', ['guanshi', 'zuzhang', 'zongguan'])
            ->where('status', 'normal')
            ->field('id, nickname, avatar, role_type')
            ->order('id DESC')
            ->select();
        
        // 角色类型映射
        $roleMap = [
            'guanshi' => '管事',
            'zuzhang' => '组长',
            'zongguan' => '总管'
        ];
        
        // 检查云存储是否启用
        $useCos = is_cos_enabled();
        
        foreach ($list as &$item) {
            $item['role_name'] = $roleMap[$item['role_type']] ?? $item['role_type'];
            // 处理头像URL
            if (!empty($item['avatar'])) {
                if (!preg_match('/^https?:\/\//i', $item['avatar'])) {
                    $item['avatar'] = $useCos ? cdnurl($item['avatar'], true) : request()->domain() . $item['avatar'];
                }
            }
        }
        unset($item);
        
        $this->success('获取成功', ['list' => $list]);
    }

    /**
     * 获取下级用户列表
     * type: parent=上级推荐人的下级, promote=推广上级的下级
     * role_filter: 角色筛选（可选）
     */
    public function getSubordinateList()
    {
        $type = $this->request->get('type', 'parent'); // parent 或 promote
        $page = $this->request->get('page/d', 1);
        $limit = $this->request->get('limit/d', 20);
        $roleFilter = $this->request->get('role_filter', ''); // 角色筛选
        
        $userId = $this->auth->id;
        $user = $this->auth->getUser();
        $myRoleType = $user ? $user->role_type : '';
        
        // 根据当前用户身份确定可以看到的下级身份类型（推荐下级才区分）
        $allowedRoles = $this->getAllowedSubordinateRoles($myRoleType);
        
        // 根据类型确定查询条件
        if ($type === 'promote') {
            // 推广下级：promote_parent_id = 当前用户（不区分身份）
            $where = [
                'u.promote_parent_id' => $userId,
                'u.status' => 'normal'
            ];
        } else {
            // 推荐下级：parent_id = 当前用户
            $where = [
                'u.parent_id' => $userId,
                'u.status' => 'normal'
            ];
            // 如果有角色限制，添加角色筛选条件
            if (!empty($allowedRoles)) {
                if (!empty($roleFilter) && in_array($roleFilter, $allowedRoles)) {
                    $where['u.role_type'] = $roleFilter;
                } else {
                    $where['u.role_type'] = ['in', $allowedRoles];
                }
            }
        }
        
        // 查询总数
        $total = \think\Db::name('user')
            ->alias('u')
            ->where($where)
            ->count();
        
        // 查询列表（联表查询会员信息）
        $now = time();
        $list = \think\Db::name('user')
            ->alias('u')
            ->where($where)
            ->field('u.id, u.nickname, u.avatar, u.role_type, u.mobile, u.createtime')
            ->order('u.id DESC')
            ->page($page, $limit)
            ->select();
        
        // 获取用户ID列表用于批量查询会员信息
        $userIds = array_column($list, 'id');
        
        // 批量查询会员信息
        $memberMap = [];
        if (!empty($userIds)) {
            $members = \think\Db::name('user_member')
                ->where('user_id', 'in', $userIds)
                ->where('status', 'normal')
                ->where('end_time', '>', $now)
                ->field('user_id, MAX(end_time) as end_time')
                ->group('user_id')
                ->select();
            foreach ($members as $m) {
                $memberMap[$m['user_id']] = $m['end_time'];
            }
        }
        
        // 检查云存储是否启用
        $useCos = is_cos_enabled();
        
        foreach ($list as &$item) {
            // 处理头像URL
            if (!empty($item['avatar'])) {
                if (!preg_match('/^https?:\/\//i', $item['avatar'])) {
                    $item['avatar'] = $useCos ? cdnurl($item['avatar'], true) : request()->domain() . $item['avatar'];
                }
            }
            // 添加会员信息
            $item['is_vip'] = isset($memberMap[$item['id']]) ? 1 : 0;
            $item['vip_end_time'] = isset($memberMap[$item['id']]) ? $memberMap[$item['id']] : 0;
        }
        unset($item);
        
        // 同时返回两种下级的总数
        $parentWhere = ['parent_id' => $userId, 'status' => 'normal'];
        if (!empty($allowedRoles)) {
            $parentWhere['role_type'] = ['in', $allowedRoles];
        }
        $parentTotal = \think\Db::name('user')
            ->where($parentWhere)
            ->count();
        
        $promoteTotal = \think\Db::name('user')
            ->where('promote_parent_id', $userId)
            ->where('status', 'normal')
            ->count();
        
        // 返回可筛选的角色类型（推荐下级用）
        $roleOptions = [];
        if (!empty($allowedRoles)) {
            $roleNameMap = [
                'user' => '未认证',
                'dashou' => '打手',
                'jindashou' => '金牌打手',
                'shangjia' => '商家',
                'jinshangjia' => '金牌商家',
                'kefu' => '客服',
                'shouhou' => '售后',
                'guanshi' => '管事',
                'zuzhang' => '组长',
                'zongguan' => '总管'
            ];
            foreach ($allowedRoles as $role) {
                $roleOptions[] = [
                    'type' => $role,
                    'name' => isset($roleNameMap[$role]) ? $roleNameMap[$role] : $role
                ];
            }
        }
        
        $this->success('获取成功', [
            'list' => $list,
            'total' => $total,
            'parent_total' => $parentTotal,
            'promote_total' => $promoteTotal,
            'role_options' => $roleOptions,
            'my_role_type' => $myRoleType
        ]);
    }
    
    /**
     * 根据用户角色获取可以查看的下级角色类型
     */
    private function getAllowedSubordinateRoles($roleType)
    {
        // 根据邀请规则：
        // 管事：可生成打手邀请码
        // 组长：可生成打手/管事/商家邀请码
        // 总管：可生成打手/管事/商家/组长邀请码
        $roleMap = [
            'guanshi' => ['dashou'],
            'zuzhang' => ['dashou', 'guanshi', 'shangjia'],
            'zongguan' => ['dashou', 'guanshi', 'shangjia', 'zuzhang']
        ];
        
        return isset($roleMap[$roleType]) ? $roleMap[$roleType] : [];
    }
    
    /**
     * 踢出下级用户（解除上下级关系）
     * @ApiMethod (POST)
     * @ApiParams (name="user_id", type="integer", required=true, description="要踢出的用户ID")
     * @ApiParams (name="type", type="string", required=true, description="类型：parent=推荐下级, promote=推广下级")
     */
    public function kickSubordinate()
    {
        $targetUserId = $this->request->post('user_id/d', 0);
        $type = $this->request->post('type', 'parent');
        
        if ($targetUserId <= 0) {
            $this->error('参数错误');
        }
        
        $userId = $this->auth->id;
        
        // 查找目标用户
        $targetUser = \think\Db::name('user')
            ->where('id', $targetUserId)
            ->where('status', 'normal')
            ->find();
        
        if (!$targetUser) {
            $this->error('用户不存在');
        }
        
        // 根据类型验证是否是自己的下级
        if ($type === 'promote') {
            // 推广下级
            if ($targetUser['promote_parent_id'] != $userId) {
                $this->error('该用户不是您的推广下级');
            }
            // 清除推广上级关系，并将身份改为未认证
            $result = \think\Db::name('user')
                ->where('id', $targetUserId)
                ->update([
                    'promote_parent_id' => 0,
                    'role_type' => 'weironzheng'
                ]);
        } else {
            // 推荐下级
            if ($targetUser['parent_id'] != $userId) {
                $this->error('该用户不是您的推荐下级');
            }
            // 清除推荐上级关系，并将身份改为未认证
            $result = \think\Db::name('user')
                ->where('id', $targetUserId)
                ->update([
                    'parent_id' => 0,
                    'role_type' => 'weironzheng'
                ]);
        }
        
        if ($result !== false) {
            $this->success('踢出成功');
        } else {
            $this->error('踢出失败');
        }
    }

    /**
     * 更新用户资料（头像、昵称、上级推荐人、手机号）
     */
    public function updateProfile()
    {
        $avatar = $this->request->post('avatar', '');
        $nickname = $this->request->post('nickname', '');
        $parentId = $this->request->post('parent_id/d', 0);
        $mobile = $this->request->post('mobile', '');
        
        $user = $this->auth->getUser();
        if (!$user) {
            $this->error('用户信息不存在');
        }
        
        $updateData = [];
        
        if ($avatar !== '') {
            $updateData['avatar'] = $avatar;
        }
        
        if ($nickname !== '') {
            $nickname = trim($nickname);
            if (empty($nickname)) {
                $this->error('昵称不能为空');
            }
            $updateData['nickname'] = $nickname;
        }
        
        if ($parentId > 0) {
            // 验证上级推荐人是否存在且角色正确
            $parent = \think\Db::name('user')
                ->where('id', $parentId)
                ->where('role_type', 'in', ['guanshi', 'zuzhang', 'zongguan'])
                ->where('status', 'normal')
                ->find();
            
            if (!$parent) {
                $this->error('上级推荐人不存在或角色不正确');
            }
            
            // 不能设置自己为上级
            if ($parentId == $this->auth->id) {
                $this->error('不能设置自己为上级推荐人');
            }
            
            $updateData['parent_id'] = $parentId;
        }
        
        if ($mobile !== '') {
            $mobile = trim($mobile);
            // 验证手机号格式
            if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
                $this->error('手机号格式不正确');
            }
            $updateData['mobile'] = $mobile;
        }
        
        if (empty($updateData)) {
            $this->error('没有需要更新的数据');
        }
        
        $result = \think\Db::name('user')
            ->where('id', $this->auth->id)
            ->update($updateData);
        
        if ($result !== false) {
            $this->success('更新成功');
        } else {
            $this->error('更新失败');
        }
    }

    /**
     * 获取系统配置（用于前端判断是否开启认证）
     */
    public function getSystemConfig()
    {
        $config = \app\admin\model\peiwan\SystemConfig::getSystemConfig();
        $this->success('获取成功', [
            'is_auth_enabled' => isset($config['is_auth_enabled']) ? intval($config['is_auth_enabled']) : 0,
            'is_custom_order' => isset($config['is_custom_order']) ? intval($config['is_custom_order']) : 0,
            'is_audio_enabled' => isset($config['is_audio_enabled']) ? intval($config['is_audio_enabled']) : 0,
            'login_page_text' => isset($config['login_page_text']) ? strval($config['login_page_text']) : ''
        ]);
    }

    /**
     * 获取我的客服列表（商家查看自己的客服，客服查看自己）
     */
    public function getMyKefuList()
    {
        // 检查用户角色（商家和客服都可以查看）
        $user = $this->auth->getUser();
        $roleType = $user ? $user->role_type : '';
        if (!in_array($roleType, ['shangjia', 'jinshangjia', 'kefu'])) {
            $this->error('只有商家或客服可以查看客服列表');
        }
        
        // 确定查询的商家ID
        $shangjiaId = $this->auth->id;
        if ($roleType === 'kefu') {
            // 客服查看自己的上级商家
            $parentId = isset($user->parent_id) ? intval($user->parent_id) : 0;
            if ($parentId <= 0) {
                $this->success('获取成功', ['list' => []]);
            }
            $shangjiaId = $parentId;
        }
        
        $list = \think\Db::name('user')
            ->where('parent_id', $shangjiaId)
            ->where('role_type', 'kefu')
            ->where('status', 'normal')
            ->field('id, nickname, avatar, mobile, createtime, can_publish_order, can_use_merchant_balance')
            ->order('id DESC')
            ->select();
        
        // 检查云存储是否启用
        $useCos = is_cos_enabled();
        
        foreach ($list as &$item) {
            // 处理头像URL
            if (!empty($item['avatar'])) {
                if (!preg_match('/^https?:\/\//i', $item['avatar'])) {
                    $item['avatar'] = $useCos ? cdnurl($item['avatar'], true) : request()->domain() . $item['avatar'];
                }
            }
        }
        
        $this->success('获取成功', ['list' => $list]);
    }
    
    /**
     * 设置客服权限（商家设置自己的客服权限）
     */
    public function setKefuPermission()
    {
        // 检查用户角色（只有商家可以设置）
        $user = $this->auth->getUser();
        $roleType = $user ? $user->role_type : '';
        if (!in_array($roleType, ['shangjia', 'jinshangjia'])) {
            $this->error('只有商家可以设置客服权限');
        }
        
        $kefuId = $this->request->post('kefu_id/d', 0);
        $field = $this->request->post('field', '');
        $value = $this->request->post('value/d', 0);
        
        if (!$kefuId) {
            $this->error('客服ID不能为空');
        }
        
        // 允许的权限字段
        $allowedFields = ['can_publish_order', 'can_use_merchant_balance', 'can_complete_order'];
        if (!in_array($field, $allowedFields)) {
            $this->error('无效的权限字段');
        }
        
        // 检查客服是否属于当前商家
        $kefu = \think\Db::name('user')
            ->where('id', $kefuId)
            ->where('parent_id', $this->auth->id)
            ->where('role_type', 'kefu')
            ->where('status', 'normal')
            ->find();
        
        if (!$kefu) {
            $this->error('客服不存在或不属于您');
        }
        
        // 更新权限
        $result = \think\Db::name('user')
            ->where('id', $kefuId)
            ->update([
                $field => $value ? 1 : 0,
                'updatetime' => time()
            ]);
        
        if ($result !== false) {
            $this->success('设置成功');
        } else {
            $this->error('设置失败');
        }
    }
    
    /**
     * 踢出客服（商家踢出自己的客服）
     */
    public function kickKefu()
    {
        // 检查用户角色（只有商家可以踢出）
        $user = $this->auth->getUser();
        $roleType = $user ? $user->role_type : '';
        if (!in_array($roleType, ['shangjia', 'jinshangjia'])) {
            $this->error('只有商家可以踢出客服');
        }
        
        $kefuId = $this->request->post('kefu_id/d', 0);
        
        if (!$kefuId) {
            $this->error('客服ID不能为空');
        }
        
        // 检查客服是否属于当前商家
        $kefu = \think\Db::name('user')
            ->where('id', $kefuId)
            ->where('parent_id', $this->auth->id)
            ->where('role_type', 'kefu')
            ->where('status', 'normal')
            ->find();
        
        if (!$kefu) {
            $this->error('客服不存在或不属于您');
        }
        
        // 踢出客服：清除上级关系，身份改为未认证
        $result = \think\Db::name('user')
            ->where('id', $kefuId)
            ->update([
                'parent_id' => 0,
                'role_type' => 'weironzheng',
                'can_publish_order' => 0,
                'can_use_merchant_balance' => 0,
                'updatetime' => time()
            ]);
        
        if ($result !== false) {
            $this->success('踢出成功');
        } else {
            $this->error('踢出失败');
        }
    }

    /**
     * 获取打手列表（用于指定打手）
     */
    public function getPlayerList()
    {
        $keyword = $this->request->get('keyword', '');
        
        $query = \think\Db::name('user')
            ->where('role_type', 'in', ['dashou', 'jindashou'])
            ->where('status', 'normal');
        
        // 如果有关键词，搜索昵称或ID
        if (!empty($keyword)) {
            $keywordNum = is_numeric($keyword) ? intval($keyword) : 0;
            if ($keywordNum > 0) {
                // 使用闭包实现 OR 查询
                $query->where(function($q) use ($keyword, $keywordNum) {
                    $q->where('id', $keywordNum)
                      ->whereOr('nickname', 'like', '%' . $keyword . '%');
                });
            } else {
                $query->where('nickname', 'like', '%' . $keyword . '%');
            }
        }
        
        $list = $query->field('id, nickname, avatar, role_type')
            ->order('id DESC')
            ->limit(20)
            ->select();
        
        // 检查云存储是否启用
        $useCos = is_cos_enabled();
        
        // 角色类型映射
        $roleMap = [
            'dashou' => '打手',
            'jindashou' => '金牌打手'
        ];
        
        foreach ($list as &$item) {
            $item['role_name'] = $roleMap[$item['role_type']] ?? $item['role_type'];
            // 处理头像URL
            if (!empty($item['avatar'])) {
                if (!preg_match('/^https?:\/\//i', $item['avatar'])) {
                    $item['avatar'] = $useCos ? cdnurl($item['avatar'], true) : request()->domain() . $item['avatar'];
                }
            }
        }
        unset($item);
        
        $this->success('获取成功', ['list' => $list]);
    }

    /**
     * 获取/生成用户邀请码
     */
    public function getInviteCode()
    {
        $user = $this->auth->getUser();
        
        // 如果已有邀请码，直接返回
        if (!empty($user->invite_code)) {
            $this->success('获取成功', ['invite_code' => $user->invite_code]);
        }
        
        // 生成唯一邀请码（8位字母数字组合）
        $inviteCode = '';
        $maxTries = 10;
        $tries = 0;
        
        while ($tries < $maxTries) {
            $inviteCode = strtoupper(substr(md5(uniqid() . $this->auth->id . time()), 0, 8));
            
            // 检查是否已存在
            $exists = \think\Db::name('user')->where('invite_code', $inviteCode)->find();
            if (!$exists) {
                break;
            }
            $tries++;
        }
        
        if (empty($inviteCode)) {
            $this->error('邀请码生成失败，请重试');
        }
        
        // 保存邀请码
        \think\Db::name('user')->where('id', $this->auth->id)->update([
            'invite_code' => $inviteCode,
            'updatetime' => time()
        ]);
        
        $this->success('获取成功', ['invite_code' => $inviteCode]);
    }

    /**
     * 根据角色获取可生成的邀请类型
     * 规则：
     * - 管事：可生成打手邀请码
     * - 组长：可生成打手/管事/商家邀请码
     * - 总管：可生成打手/管事/商家/组长邀请码
     * - 所有人：可生成推广邀请码（不改变身份，仅绑定推广上级）
     */
    public function getInviteTypes()
    {
        $user = $this->auth->getUser();
        $roleType = $user ? $user->role_type : '';
        
        $types = [];
        
        // 所有人都可以生成推广邀请码
        $types[] = [
            'type' => 'promote',
            'name' => '推广邀请码',
            'description' => '邀请新用户注册，绑定为您的推广下级'
        ];
        
        // 根据角色添加可生成的邀请类型
        switch ($roleType) {
            case 'zongguan': // 总管
                $types[] = [
                    'type' => 'zuzhang',
                    'name' => '组长邀请码',
                    'description' => '邀请用户成为组长'
                ];
                // 继续往下执行
            case 'zuzhang': // 组长
                $types[] = [
                    'type' => 'shangjia',
                    'name' => '商家邀请码',
                    'description' => '邀请用户成为商家'
                ];
                $types[] = [
                    'type' => 'guanshi',
                    'name' => '管事邀请码',
                    'description' => '邀请用户成为管事'
                ];
                // 继续往下执行
            case 'guanshi': // 管事
                $types[] = [
                    'type' => 'dashou',
                    'name' => '打手邀请码',
                    'description' => '邀请用户成为打手'
                ];
                break;
            case 'shangjia': // 商家
            case 'jinshangjia': // 金牌商家
                $types[] = [
                    'type' => 'kefu',
                    'name' => '客服邀请码',
                    'description' => '邀请用户成为您的客服'
                ];
                break;
        }
        
        $this->success('获取成功', ['types' => $types]);
    }

    /**
     * 生成推广海报（带参小程序码）
     * 支持两种类型：
     * - poster: 海报类型，有效期内可多次使用
     * - key: 密匙类型，一次性使用
     * @ApiMethod (POST)
     * @ApiParams (name="invite_type", type="string", required=true, description="邀请类型：promote/dashou/guanshi/zuzhang/shangjia/kefu")
     * @ApiParams (name="code_type", type="string", required=false, description="邀请码类型：poster=海报(可多次使用), key=密匙(一次性)")
     */
    public function generatePoster()
    {
        $inviteType = $this->request->post('invite_type', 'promote');
        $codeType = $this->request->post('code_type', 'poster'); // 默认为海报类型
        
        // 验证邀请类型
        $validTypes = ['promote', 'dashou', 'guanshi', 'zuzhang', 'shangjia', 'kefu'];
        if (!in_array($inviteType, $validTypes)) {
            $this->error('无效的邀请类型');
        }
        
        // 验证邀请码类型
        if (!in_array($codeType, ['poster', 'key'])) {
            $codeType = 'poster';
        }
        
        $user = $this->auth->getUser();
        $userId = $this->auth->id;
        $roleType = $user ? $user->role_type : '';
        
        // 验证权限
        $allowedTypes = ['promote']; // 所有人都可以生成推广邀请码
        switch ($roleType) {
            case 'zongguan':
                $allowedTypes[] = 'zuzhang';
            case 'zuzhang':
                $allowedTypes[] = 'shangjia';
                $allowedTypes[] = 'guanshi';
            case 'guanshi':
                $allowedTypes[] = 'dashou';
                break;
            case 'shangjia':
            case 'jinshangjia':
                $allowedTypes[] = 'kefu';
                break;
        }
        
        if (!in_array($inviteType, $allowedTypes)) {
            $this->error('您没有权限生成该类型的邀请码');
        }
        
        // 获取系统配置
        $systemConfig = \think\Db::name('system_config')->where('id', 1)->find();
        if (!$systemConfig) {
            $this->error('系统设置未初始化，请联系管理员');
        }
        
        $invitePosterDays = intval($systemConfig['invite_poster_days'] ?? 7); // 默认7天
        $inviteCode = '';
        $existingRecord = null;
        
        if ($codeType === 'poster') {
            // 海报类型：检查是否有有效的海报邀请码
            $query = \think\Db::name('user_invite_code')
                ->where('user_id', $userId)
                ->where('invite_type', $inviteType)
                ->where('code_type', 'poster')
                ->where('status', 'normal');
            
            // 推广海报永不过期，身份海报有时间限制
            if ($inviteType !== 'promote') {
                $query->where('expire_time', '>', time());
            }
            
            $existingRecord = $query->order('id', 'desc')->find();
            
            if ($existingRecord) {
                // 返回已有的有效海报
                $inviteCode = $existingRecord['invite_code'];
            } else {
                // 生成新的海报邀请码
                $inviteCode = strtoupper(substr(md5(uniqid() . $userId . time() . $inviteType . mt_rand(1000, 9999)), 0, 8));
                
                // 推广海报永不过期（expire_time为null），身份海报有时间限制
                $expireTime = ($inviteType === 'promote') ? null : time() + ($invitePosterDays * 86400);
                
                \think\Db::name('user_invite_code')->insert([
                    'user_id' => $userId,
                    'invite_type' => $inviteType,
                    'invite_code' => $inviteCode,
                    'status' => 'normal',
                    'code_type' => 'poster',
                    'expire_time' => $expireTime,
                    'createtime' => time()
                ]);
            }
        } else {
            // 密匙类型：每次都生成新的一次性邀请码
            $inviteCode = strtoupper(substr(md5(uniqid() . $userId . time() . $inviteType . mt_rand(1000, 9999)), 0, 8));
            
            \think\Db::name('user_invite_code')->insert([
                'user_id' => $userId,
                'invite_type' => $inviteType,
                'invite_code' => $inviteCode,
                'status' => 'normal',
                'code_type' => 'key',
                'expire_time' => null, // 密匙不设置过期时间
                'createtime' => time()
            ]);
            
            // 密匙类型直接返回邀请码，不生成海报图片
            $this->success('生成成功', [
                'invite_code' => $inviteCode,
                'invite_type' => $inviteType,
                'code_type' => 'key',
                'is_one_time' => true // 标识一次性使用
            ]);
        }
        
        // 构建文件路径
        $saveDir = ROOT_PATH . 'public/uploads/poster/user/';
        if (!is_dir($saveDir)) {
            mkdir($saveDir, 0755, true);
        }
        
        // 文件名格式：用户ID_邀请类型_邀请码.png
        $filename = $userId . '_' . $inviteType . '_' . $inviteCode . '.png';
        $filepath = $saveDir . $filename;
        $imageUrl = '/uploads/poster/user/' . $filename;
        
        // 生成海报
        try {
            // systemConfig 已在上方获取
            // 推广海报使用点单端小程序配置
            if ($inviteType === 'promote') {
                $appid = $systemConfig['client_wxappid'] ?? '';
                $secret = $systemConfig['client_wxappsecret'] ?? '';
                $posterBgImage = $systemConfig['client_poster_image'] ?? '';
                
                if (empty($appid) || empty($secret)) {
                    $this->error('点单端小程序配置未设置，请在后台"陪玩->系统设置管理"中设置点单端AppID和AppSecret');
                }
            } else {
                // 身份海报使用陪玩端小程序配置
                $appid = $systemConfig['wxappid'] ?? '';
                $secret = $systemConfig['wxappsecret'] ?? '';
                $posterBgImage = $systemConfig['poster_image'] ?? '';
                
                if (empty($appid) || empty($secret)) {
                    $this->error('小程序配置未设置，请在后台"陪玩->系统设置管理"中设置AppID和AppSecret');
                }
            }
            
            // 获取access_token
            $accessToken = $this->getWxAccessToken($appid, $secret);
            if (!$accessToken) {
                $this->error('获取微信access_token失败');
            }
            
            // scene参数：邀请码_邀请类型
            $scene = $inviteCode . '_' . $inviteType;
            
            // 调用微信接口生成小程序码
            $url = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=" . $accessToken;
            $data = [
                'scene' => $scene,
                'page' => 'pages/index/index', // 小程序首页
                'width' => 280,
                'auto_color' => false,
                'line_color' => ['r' => 0, 'g' => 0, 'b' => 0],
                'is_hyaline' => false
            ];
            
            $result = $this->httpPost($url, json_encode($data), ['Content-Type: application/json']);
            
            // 检查是否返回错误（微信接口错误时会返回JSON）
            $jsonResult = @json_decode($result, true);
            if ($jsonResult && isset($jsonResult['errcode']) && $jsonResult['errcode'] != 0) {
                $this->error('生成小程序码失败：' . ($jsonResult['errmsg'] ?? '未知错误') . '(错误码:' . $jsonResult['errcode'] . ')');
            }
            
            // 检查返回内容是否为有效的图片数据
            // PNG文件头是 \x89PNG，JPEG文件头是 \xFF\xD8\xFF
            $isPng = strlen($result) >= 4 && substr($result, 0, 4) === "\x89PNG";
            $isJpeg = strlen($result) >= 3 && substr($result, 0, 3) === "\xFF\xD8\xFF";
            
            if (!$isPng && !$isJpeg) {
                // 尝试解析为JSON查看错误信息
                if ($jsonResult) {
                    $this->error('生成小程序码失败：' . json_encode($jsonResult, JSON_UNESCAPED_UNICODE));
                }
                $this->error('生成小程序码失败：微信接口返回无效数据');
            }
            
            // 记录图片格式
            $qrcodeFormat = $isPng ? 'png' : 'jpeg';
            
            // 如果没有配置海报背景图，直接返回二维码
            if (empty($posterBgImage)) {
                if (file_put_contents($filepath, $result) === false) {
                    $this->error('保存小程序码失败');
                }
                $fullUrl = request()->domain() . $imageUrl;
                $this->success('生成成功', [
                    'qrcode_url' => $fullUrl,
                    'invite_code' => $inviteCode,
                    'invite_type' => $inviteType,
                    'is_cached' => false
                ]);
            }
            
            // ====== 开始合成海报 ======
            
            // 保存二维码临时文件
            $qrcodeExt = $qrcodeFormat === 'png' ? 'png' : 'jpg';
            $qrcodeTempPath = $saveDir . $userId . '_qrcode_temp.' . $qrcodeExt;
            $writeResult = file_put_contents($qrcodeTempPath, $result);
            if ($writeResult === false) {
                $this->error('保存二维码临时文件失败，请检查目录权限: ' . $saveDir);
            }
            
            // 加载海报背景图
            $posterBgPath = ROOT_PATH . 'public' . $posterBgImage;
            
            if (!file_exists($posterBgPath)) {
                if (strpos($posterBgImage, 'http') === 0) {
                    $posterBgPath = $posterBgImage;
                } else {
                    @unlink($qrcodeTempPath);
                    $this->error('海报背景图片不存在: ' . $posterBgPath);
                }
            }
            
            $bgInfo = @getimagesize($posterBgPath);
            if (!$bgInfo) {
                @unlink($qrcodeTempPath);
                $this->error('无法读取海报背景图片: ' . $posterBgPath);
            }
            
            $bgWidth = $bgInfo[0];
            $bgHeight = $bgInfo[1];
            
            switch ($bgInfo[2]) {
                case IMAGETYPE_JPEG:
                    $bgImage = @imagecreatefromjpeg($posterBgPath);
                    break;
                case IMAGETYPE_PNG:
                    $bgImage = @imagecreatefrompng($posterBgPath);
                    break;
                default:
                    @unlink($qrcodeTempPath);
                    $this->error('不支持的背景图片格式');
            }
            
            if (!$bgImage) {
                @unlink($qrcodeTempPath);
                $this->error('创建背景图片失败，请检查服务器GD库配置，图片类型: ' . $bgInfo[2]);
            }
            
            // 加载二维码（根据格式选择正确的函数）
            if ($qrcodeFormat === 'png') {
                $qrcodeImage = @imagecreatefrompng($qrcodeTempPath);
            } else {
                $qrcodeImage = @imagecreatefromjpeg($qrcodeTempPath);
            }
            if (!$qrcodeImage) {
                @unlink($qrcodeTempPath);
                $this->error('创建二维码图片失败，格式: ' . $qrcodeFormat);
            }
            
            // ====== 新布局：上面海报图 + 下面信息区 ======
            $qrcodeOrigWidth = imagesx($qrcodeImage);
            $qrcodeOrigHeight = imagesy($qrcodeImage);
            
            // 缩放二维码到合适大小
            $qrcodeSize = 200;
            $qrcodeResized = imagecreatetruecolor($qrcodeSize, $qrcodeSize);
            imagealphablending($qrcodeResized, false);
            imagesavealpha($qrcodeResized, true);
            imagecopyresampled($qrcodeResized, $qrcodeImage, 0, 0, 0, 0, $qrcodeSize, $qrcodeSize, $qrcodeOrigWidth, $qrcodeOrigHeight);
            imagedestroy($qrcodeImage);
            
            // 底部信息区高度
            $bottomHeight = 280;
            $margin = 30;
            
            // 创建新画布（海报图 + 底部信息区）
            $canvasWidth = $bgWidth;
            $canvasHeight = $bgHeight + $bottomHeight;
            $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);
            
            // 填充白色背景
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);
            
            // 1. 上部：复制海报背景图
            imagecopy($canvas, $bgImage, 0, 0, 0, 0, $bgWidth, $bgHeight);
            imagedestroy($bgImage);
            
            // 2. 下部右侧：放置二维码
            $qrcodeX = $canvasWidth - $qrcodeSize - $margin;
            $qrcodeY = $bgHeight + ($bottomHeight - $qrcodeSize) / 2;
            imagecopy($canvas, $qrcodeResized, $qrcodeX, $qrcodeY, 0, 0, $qrcodeSize, $qrcodeSize);
            imagedestroy($qrcodeResized);
            
            // 3. 下部左侧：用户头像
            $avatarSize = 100;
            $avatarX = $margin;
            $avatarY = $bgHeight + $margin + 60; // 下移60像素
            
            $avatarUrl = $user->avatar ?? '';
            if (!empty($avatarUrl)) {
                $avatarPath = ROOT_PATH . 'public' . $avatarUrl;
                if (strpos($avatarUrl, 'http') === 0) {
                    $avatarPath = $avatarUrl;
                }
                $avatarInfo = @getimagesize($avatarPath);
                if ($avatarInfo) {
                    $avatarImage = null;
                    if ($avatarInfo[2] == IMAGETYPE_JPEG) {
                        $avatarImage = @imagecreatefromjpeg($avatarPath);
                    } elseif ($avatarInfo[2] == IMAGETYPE_PNG) {
                        $avatarImage = @imagecreatefrompng($avatarPath);
                    }
                    if ($avatarImage) {
                        $avatarResized = imagecreatetruecolor($avatarSize, $avatarSize);
                        imagealphablending($avatarResized, false);
                        imagesavealpha($avatarResized, true);
                        $transparent = imagecolorallocatealpha($avatarResized, 255, 255, 255, 127);
                        imagefill($avatarResized, 0, 0, $transparent);
                        imagecopyresampled($avatarResized, $avatarImage, 0, 0, 0, 0, $avatarSize, $avatarSize, imagesx($avatarImage), imagesy($avatarImage));
                        imagecopy($canvas, $avatarResized, $avatarX, $avatarY, 0, 0, $avatarSize, $avatarSize);
                        imagedestroy($avatarImage);
                        imagedestroy($avatarResized);
                    }
                }
            }
            
            // 4. 添加文字
            $fontPath = ROOT_PATH . 'public/static/fonts/msyh.ttf';
            // 备用字体路径（只检查项目目录内的）
            $alternateFonts = [
                ROOT_PATH . 'public/static/fonts/simhei.ttf',
                ROOT_PATH . 'public/static/fonts/simsun.ttf',
                ROOT_PATH . 'vendor/topthink/think-captcha/assets/zhttfs/1.ttf', // 验证码组件自带的中文字体
            ];
            
            if (!file_exists($fontPath)) {
                foreach ($alternateFonts as $altFont) {
                    if (file_exists($altFont)) {
                        $fontPath = $altFont;
                        break;
                    }
                }
            }
            
            $textColor = imagecolorallocate($canvas, 51, 51, 51);
            $grayColor = imagecolorallocate($canvas, 102, 102, 102);
            
            $nickname = $user->nickname ?? '用户' . $userId;
            $inviteTypeNames = ['promote' => '推广', 'dashou' => '打手', 'guanshi' => '管事', 'zuzhang' => '组长', 'shangjia' => '商家', 'kefu' => '客服'];
            $typeName = $inviteTypeNames[$inviteType] ?? $inviteType;
            
            // 计算到期时间（推广海报永不过期，不显示有效期）
            $expireText = '';
            if ($inviteType !== 'promote') {
                if ($existingRecord && isset($existingRecord['expire_time'])) {
                    $expireTime = date('Y-m-d H:i', $existingRecord['expire_time']);
                } else {
                    $expireTime = date('Y-m-d H:i', time() + ($invitePosterDays * 86400));
                }
                $expireText = '有效期至: ' . $expireTime;
            }
            
            if (file_exists($fontPath)) {
                // 用户昵称（头像右侧）
                $textX = $avatarX + $avatarSize + 20;
                $textY = $avatarY + 35;
                @imagettftext($canvas, 22, 0, $textX, $textY, $textColor, $fontPath, $nickname);
                
                // 邀请信息
                @imagettftext($canvas, 16, 0, $textX, $textY + 40, $grayColor, $fontPath, '邀请您成为' . $typeName);
                
                // 底部中间：到期时间（仅身份海报显示）
                if (!empty($expireText)) {
                    $bbox = @imagettfbbox(14, 0, $fontPath, $expireText);
                    if ($bbox) {
                        $textWidth = $bbox[2] - $bbox[0];
                        $expireX = ($canvasWidth - $textWidth) / 2;
                    } else {
                        $expireX = $margin;
                    }
                    $expireY = $canvasHeight - $margin;
                    @imagettftext($canvas, 14, 0, $expireX, $expireY, $grayColor, $fontPath, $expireText);
                }
            } else {
                // 没有TTF字体，使用内置函数（只能显示英文和数字）
                $textX = $avatarX + $avatarSize + 20;
                $textY = $avatarY + 20;
                
                // 使用内置字体（5是最大的内置字体）
                imagestring($canvas, 5, $textX, $textY, 'User: ' . $userId, $textColor);
                imagestring($canvas, 4, $textX, $textY + 25, 'Type: ' . $typeName, $grayColor);
                
                // 到期时间（仅身份海报显示）
                if (!empty($expireText)) {
                    $expireX = ($canvasWidth - strlen($expireText) * 7) / 2;
                    imagestring($canvas, 4, $expireX, $canvasHeight - $margin - 10, $expireText, $grayColor);
                }
            }
            
            // 保存合成海报
            $saveResult = @imagepng($canvas, $filepath);
            imagedestroy($canvas);
            @unlink($qrcodeTempPath);
            
            if (!$saveResult) {
                $this->error('保存海报图片失败，请检查目录权限');
            }
            
            $fullUrl = request()->domain() . $imageUrl;
            
            $this->success('生成成功', [
                'qrcode_url' => $fullUrl,
                'invite_code' => $inviteCode,
                'invite_type' => $inviteType,
                'is_cached' => false
            ]);
            
        } catch (\think\exception\HttpResponseException $e) {
            // HttpResponseException 是正常的响应流程，直接重新抛出
            throw $e;
        } catch (\Throwable $e) {
            $this->error('生成海报失败：' . ($e->getMessage() ?: '图像处理错误，请检查GD库配置和字体文件'));
        }
    }

    /**
     * 获取微信access_token
     */
    protected function getWxAccessToken($appId, $appSecret)
    {
        // 尝试从缓存获取
        $cacheKey = 'wx_access_token_' . $appId;
        $accessToken = cache($cacheKey);
        
        if ($accessToken) {
            return $accessToken;
        }
        
        // 请求微信接口获取
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appId}&secret={$appSecret}";
        $result = file_get_contents($url);
        $data = json_decode($result, true);
        
        if (isset($data['access_token'])) {
            // 缓存7000秒（微信access_token有效期7200秒）
            cache($cacheKey, $data['access_token'], 7000);
            return $data['access_token'];
        }
        
        return false;
    }

    /**
     * HTTP POST请求
     */
    protected function httpPost($url, $data, $headers = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        return $result;
    }

    /**
     * 更换身份（重置为未认证）
     */
    public function resetRole()
    {
        $user = $this->auth->getUser();
        if (!$user) {
            $this->error('用户信息不存在');
        }
        
        // 将用户角色重置为未认证
        $result = \think\Db::name('user')
            ->where('id', $user->id)
            ->update([
                'role_type' => 'weironzheng',
                'updatetime' => time()
            ]);
        
        if ($result !== false) {
            $this->success('身份已重置，请联系您的上级提供身份海报');
        } else {
            $this->error('操作失败');
        }
    }

    /**
     * 绑定邀请码（已登录用户扫码后更新身份和上级）
     */
    public function bindInvite()
    {
        $scene = $this->request->post('scene', '');
        
        if (empty($scene)) {
            $this->error('邀请码不能为空');
        }
        
        $user = $this->auth->getUser();
        if (!$user) {
            $this->error('用户信息不存在');
        }
        
        // 只有未认证用户才能绑定
        if ($user->role_type !== 'weironzheng') {
            $this->error('您已有身份，无法重复绑定');
        }
        
        // 解析scene参数：格式为 invite_code_invite_type
        $parts = explode('_', urldecode($scene));
        $inviteCode = $parts[0] ?? '';
        $inviteType = $parts[1] ?? 'promote';
        
        if (empty($inviteCode)) {
            $this->error('无效的邀请码');
        }
        
        // 查找邀请人
        $inviter = \think\Db::name('user')
            ->where('invite_code', $inviteCode)
            ->where('status', 'normal')
            ->find();
        
        if (!$inviter) {
            $this->error('邀请码无效或邀请人不存在');
        }
        
        // 不能绑定自己
        if ($inviter['id'] == $user->id) {
            $this->error('不能绑定自己的邀请码');
        }
        
        // 确定新角色
        $validRoles = ['dashou', 'guanshi', 'zuzhang', 'shangjia'];
        $newRoleType = 'dashou'; // 默认为打手
        
        if ($inviteType && $inviteType !== 'promote' && in_array($inviteType, $validRoles)) {
            $newRoleType = $inviteType;
        }
        
        // 更新用户信息（身份海报只绑定层级上级，不绑定推广上级）
        $updateData = [
            'role_type' => $newRoleType,
            'parent_id' => $inviter['id'], // 绑定层级上级
            'updatetime' => time()
        ];
        
        $result = \think\Db::name('user')
            ->where('id', $user->id)
            ->update($updateData);
        
        if ($result !== false) {
            $roleNames = [
                'dashou' => '打手',
                'guanshi' => '管事',
                'zuzhang' => '组长',
                'shangjia' => '商家'
            ];
            $roleName = $roleNames[$newRoleType] ?? $newRoleType;
            
            $this->success('绑定成功，您的身份已更新为' . $roleName, [
                'role_type' => $newRoleType,
                'parent_id' => $inviter['id'],
                'parent_nickname' => $inviter['nickname']
            ]);
        } else {
            $this->error('绑定失败');
        }
    }
    
    /**
     * 完成注册（欢迎页面提交）
     * @ApiMethod (POST)
     * @ApiParams (name="invite_code", type="string", required=false, description="邀请码")
     * @ApiParams (name="mobile", type="string", required=true, description="手机号")
     */
    public function completeRegister()
    {
        $inviteCode = $this->request->post('invite_code', '');
        $mobile = $this->request->post('mobile', '');
        
        $user = $this->auth->getUser();
        
        // 验证手机号格式
        if (empty($mobile)) {
            $this->error('请输入手机号');
        }
        if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
            $this->error('手机号格式不正确');
        }
        
        // 检查手机号是否已被他人使用
        $existUser = \think\Db::name('user')
            ->where('mobile', $mobile)
            ->where('id', '<>', $user->id)
            ->find();
        if ($existUser) {
            $this->error('该手机号已被使用');
        }
        
        $updateData = [
            'mobile' => $mobile,
            'updatetime' => time()
        ];
        
        $inviteInfo = null;
        $inviter = null;
        
        // 如果有邀请码，处理邀请码绑定
        if (!empty($inviteCode)) {
            // 先查找邀请码表
            $inviteInfo = \think\Db::name('user_invite_code')
                ->where('invite_code', $inviteCode)
                ->where('status', 'normal')
                ->find();
            
            if ($inviteInfo) {
                // 检查邀请码类型和有效期
                $codeType = $inviteInfo['code_type'] ?? 'key';
                
                // 海报类型需要检查有效期
                if ($codeType === 'poster' && !empty($inviteInfo['expire_time'])) {
                    if ($inviteInfo['expire_time'] < time()) {
                        $this->error('该邀请码已过期，请联系邀请人重新生成');
                    }
                }
                
                // 找到了邀请码
                $inviter = \think\Db::name('user')
                    ->where('id', $inviteInfo['user_id'])
                    ->where('status', 'normal')
                    ->find();
                
                if (!$inviter) {
                    $this->error('邀请人不存在或已被禁用');
                }
                
                // 不能绑定自己
                if ($inviter['id'] == $user->id) {
                    $this->error('不能使用自己的邀请码');
                }
                
                // 根据邀请码类型处理
                if ($codeType === 'key') {
                    // 密匙类型：标记为已使用（一次性）
                    \think\Db::name('user_invite_code')
                        ->where('id', $inviteInfo['id'])
                        ->update([
                            'status' => 'used',
                            'used_by' => $user->id,
                            'usetime' => time()
                        ]);
                }
                // 海报类型（poster）不标记为已使用，可多次使用
                
                // 根据邀请类型设置用户身份
                $inviteType = $inviteInfo['invite_type'];
                $validRoles = ['dashou', 'guanshi', 'zuzhang', 'shangjia', 'kefu'];
                
                if ($inviteType === 'promote') {
                    // 推广类型，仅绑定推广上级，不改变身份
                    $updateData['promote_parent_id'] = $inviter['id'];
                } elseif (in_array($inviteType, $validRoles)) {
                    // 身份类型，设置用户身份并绑定层级上级（不绑定推广上级）
                    $updateData['role_type'] = $inviteType;
                    $updateData['parent_id'] = $inviter['id'];
                }
            } else {
                // 在旧的用户邀请码字段中查找（兼容旧逻辑）
                $inviter = \think\Db::name('user')
                    ->where('invite_code', $inviteCode)
                    ->where('status', 'normal')
                    ->find();
                
                if ($inviter) {
                    if ($inviter['id'] == $user->id) {
                        $this->error('不能使用自己的邀请码');
                    }
                    // 旧逻辑：仅绑定推广上级
                    if (empty($user->promote_parent_id)) {
                        $updateData['promote_parent_id'] = $inviter['id'];
                    }
                } else {
                    $this->error('邀请码无效或已被使用');
                }
            }
        }
        
        // 更新用户信息
        $result = \think\Db::name('user')
            ->where('id', $user->id)
            ->update($updateData);
        
        if ($result !== false) {
            // 获取更新后的用户信息
            $newUserInfo = \think\Db::name('user')->where('id', $user->id)->find();
            
            $roleNames = [
                'weironzheng' => '未认证',
                'dashou' => '打手',
                'jindashou' => '金牌打手',
                'guanshi' => '管事',
                'zuzhang' => '组长',
                'zongguan' => '总管',
                'shangjia' => '商家',
                'jinshangjia' => '金牌商家',
                'kefu' => '客服',
                'shouhou' => '售后'
            ];
            
            $responseData = [
                'mobile' => $mobile,
                'role_type' => $newUserInfo['role_type'],
                'role_name' => $roleNames[$newUserInfo['role_type']] ?? $newUserInfo['role_type']
            ];
            
            if ($inviter) {
                $responseData['parent_id'] = $inviter['id'];
                $responseData['parent_nickname'] = $inviter['nickname'];
            }
            
            $this->success('注册完成', $responseData);
        } else {
            $this->error('保存失败');
        }
    }
    
    /**
     * 计算提现上级分成金额
     * @param int $userId 用户ID
     * @param float $amount 提现金额
     * @return array ['enabled' => bool, 'share_amount' => float, 'parent_id' => int, 'parent_role' => string, 'rate' => float]
     */
    private function calculateWithdrawParentShare($userId, $amount)
    {
        $result = [
            'enabled' => false,
            'share_amount' => 0,
            'parent_id' => 0,
            'parent_role' => '',
            'rate' => 0
        ];
        
        // 获取系统配置
        $systemConfig = \think\Db::name('system_config')->where('id', 1)->find();
        if (!$systemConfig || intval($systemConfig['is_withdraw_parent_share'] ?? 0) !== 1) {
            return $result; // 未开启分成
        }
        
        // 获取用户信息
        $user = \think\Db::name('user')->where('id', $userId)->find();
        if (!$user || intval($user['parent_id'] ?? 0) <= 0) {
            return $result; // 无上级
        }
        
        // 获取上级信息
        $parent = \think\Db::name('user')->where('id', $user['parent_id'])->where('status', 'normal')->find();
        if (!$parent) {
            return $result; // 上级不存在或状态异常
        }
        
        $parentRoleType = $parent['role_type'] ?? '';
        $rate = 0;
        
        // 根据上级角色获取分成比例
        switch ($parentRoleType) {
            case 'guanshi':
                $rate = floatval($systemConfig['withdraw_share_rate_guanshi'] ?? 0);
                break;
            case 'zuzhang':
                $rate = floatval($systemConfig['withdraw_share_rate_zuzhang'] ?? 0);
                break;
            case 'zongguan':
                $rate = floatval($systemConfig['withdraw_share_rate_zongguan'] ?? 0);
                break;
            default:
                return $result; // 上级角色不是管事/组长/总管
        }
        
        if ($rate <= 0) {
            return $result; // 比例为0不分成
        }
        
        $shareAmount = round($amount * $rate / 100, 2);
        if ($shareAmount <= 0) {
            return $result;
        }
        
        $result['enabled'] = true;
        $result['share_amount'] = $shareAmount;
        $result['parent_id'] = $parent['id'];
        $result['parent_role'] = $parentRoleType;
        $result['rate'] = $rate;
        
        return $result;
    }
    
    /**
     * 执行提现上级分成
     * @param int $userId 用户ID
     * @param float $amount 提现金额
     * @param array $shareInfo 分成信息（来自 calculateWithdrawParentShare）
     * @param int $withdrawLogId 提现记录ID
     */
    private function executeWithdrawParentShare($userId, $amount, $shareInfo, $withdrawLogId = 0)
    {
        if (!$shareInfo['enabled'] || $shareInfo['share_amount'] <= 0 || $shareInfo['parent_id'] <= 0) {
            return;
        }
        
        $shareAmount = $shareInfo['share_amount'];
        $parentId = $shareInfo['parent_id'];
        $parentRole = $shareInfo['parent_role'];
        $rate = $shareInfo['rate'];
        
        // 获取用户当前余额
        $userInfo = \think\Db::name('user')->where('id', $userId)->find();
        $userMoney = floatval($userInfo['money'] ?? 0);
        
        // 扣除用户分成金额
        \think\Db::name('user')->where('id', $userId)->setDec('money', $shareAmount);
        
        // 记录用户余额变动
        $roleNames = ['guanshi' => '管事', 'zuzhang' => '组长', 'zongguan' => '总管'];
        $roleName = $roleNames[$parentRole] ?? $parentRole;
        \think\Db::name('user_money_log')->insert([
            'user_id' => $userId,
            'money' => -$shareAmount,
            'before' => $userMoney,
            'after' => $userMoney - $shareAmount,
            'memo' => '提现上级分成（' . $rate . '%给' . $roleName . '）',
            'createtime' => time()
        ]);
        
        // 给上级增加余额
        $parent = \think\Db::name('user')->where('id', $parentId)->find();
        $parentMoney = floatval($parent['money'] ?? 0);
        \think\Db::name('user')->where('id', $parentId)->setInc('money', $shareAmount);
        
        // 记录上级余额变动
        $userNickname = $userInfo['nickname'] ?? '用户' . $userId;
        \think\Db::name('user_money_log')->insert([
            'user_id' => $parentId,
            'money' => $shareAmount,
            'before' => $parentMoney,
            'after' => $parentMoney + $shareAmount,
            'memo' => '下级提现分成：来自' . $userNickname . '的提现（' . $rate . '%）',
            'createtime' => time()
        ]);
        
        // 记录佣金日志
        \think\Db::name('commission_log')->insert([
            'user_id' => $parentId,
            'order_id' => 0,
            'amount' => $shareAmount,
            'type' => 'income',
            'memo' => '下级提现分成（' . $rate . '%）- 来自' . $userNickname,
            'createtime' => time()
        ]);
    }
}

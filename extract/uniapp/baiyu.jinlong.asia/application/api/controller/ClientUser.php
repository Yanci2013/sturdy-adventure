<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;
use fast\Random;

/**
 * 点单端用户接口
 * 独立的登录和海报生成，使用点单端专用的小程序配置
 */
class ClientUser extends Api
{
    protected $noNeedLogin = ['wechatLogin'];
    protected $noNeedRight = ['*'];

    /**
     * 点单端微信登录
     * @ApiMethod (POST)
     * @ApiParams (name="code", type="string", required=true, description="微信登录code")
     * @ApiParams (name="nickname", type="string", required=false, description="微信昵称")
     * @ApiParams (name="avatar", type="string", required=false, description="微信头像")
     */
    public function wechatLogin()
    {
        $code = $this->request->post('code');
        $nickname = $this->request->post('nickname', '');
        $avatar = $this->request->post('avatar', '');
        $scene = $this->request->post('scene', ''); // 小程序码scene参数（推广码）
        
        if (!$code) {
            $this->error('缺少code参数');
        }
        
        // 从系统设置表读取点单端小程序配置
        $systemConfig = Db::name('system_config')->where('id', 1)->find();
        
        if (!$systemConfig) {
            $this->error('系统设置未初始化，请联系管理员');
        }
        
        // 使用点单端专用的appid和secret
        $appid = $systemConfig['client_wxappid'] ?? '';
        $secret = $systemConfig['client_wxappsecret'] ?? '';
        
        if (!$appid || !$secret) {
            $this->error('点单端小程序配置未设置，请在后台"系统设置->点单端配置"中设置AppID和AppSecret');
        }
        
        // 调用微信接口获取openid
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$secret}&js_code={$code}&grant_type=authorization_code";
        $result = file_get_contents($url);
        $wxData = json_decode($result, true);
        
        if (isset($wxData['errcode'])) {
            $this->error('微信登录失败：' . ($wxData['errmsg'] ?? '未知错误'));
        }
        
        $openid = $wxData['openid'] ?? '';
        $sessionKey = $wxData['session_key'] ?? '';
        
        if (!$openid) {
            $this->error('获取openid失败');
        }
        
        // 点单端直接使用 openid 字段（与陷玩端用户独立）
        $user = \app\common\model\User::where('openid', $openid)->find();
        $isNewUser = false;
        
        // 解析scene参数获取推广人信息
        // 支持两种格式：
        // 1. 直接用户ID（如 "83"）
        // 2. 邀请码_类型 格式（如 "A3B5C7D9_promote"）
        $promoteParentId = 0;
        if (!empty($scene)) {
            if (strpos($scene, '_') !== false) {
                // 邀请码_类型 格式，从邀请码表查找
                $parts = explode('_', $scene);
                $inviteCode = $parts[0];
                
                // 从邀请码表查找
                $inviteInfo = Db::name('user_invite_code')
                    ->where('invite_code', $inviteCode)
                    ->where('status', 'normal')
                    ->find();
                
                if ($inviteInfo) {
                    // 检查有效期
                    $codeType = $inviteInfo['code_type'] ?? 'key';
                    if ($codeType === 'poster' && !empty($inviteInfo['expire_time']) && $inviteInfo['expire_time'] < time()) {
                        // 海报已过期，不绑定
                    } else {
                        $promoteParentId = $inviteInfo['user_id'];
                    }
                }
            } else {
                // 直接用户ID格式
                $promoterId = intval($scene);
                if ($promoterId > 0) {
                    $promoter = Db::name('user')->where('id', $promoterId)->where('status', 'normal')->find();
                    if ($promoter) {
                        $promoteParentId = $promoter['id'];
                    }
                }
            }
        }
        
        if (!$user) {
            // 新用户，创建账号
            $username = 'client_' . substr($openid, -8) . Random::numeric(4);
            $password = Random::alnum(16);
            $user = \app\common\model\User::create([
                'username' => $username,
                'nickname' => $nickname ?: '点单用户' . Random::numeric(6),
                'avatar'   => $avatar ?: '',
                'password' => $password,
                'openid'   => $openid,
                'role_type' => 'user', // 点单端用户默认为 user 角色
                'promote_parent_id' => $promoteParentId,
                'status'   => 'normal'
            ]);
            $isNewUser = true;
        } else {
            // 老用户：如果没有推广上级且有推广人，则绑定
            if ($promoteParentId > 0 && empty($user->promote_parent_id)) {
                $user->promote_parent_id = $promoteParentId;
            }
            $user->save();
        }
        
        // 登录用户
        $ret = $this->auth->direct($user->id);
        if (!$ret) {
            $this->error($this->auth->getError());
        }
        
        // 保存session_key到缓存
        cache('wx_client_session_key_' . $openid, $sessionKey, 7200);
        
        // 获取用户信息
        $userInfo = $this->auth->getUserinfo();
        $userInfo['is_new_user'] = $isNewUser;
        
        $this->success('登录成功', $userInfo);
    }

    /**
     * 生成点单端推广海报（永久）
     * 每个用户只生成一次，之后直接返回已生成的海报
     * @ApiMethod (POST)
     */
    public function generatePoster()
    {
        $user = $this->auth->getUser();
        $userId = $this->auth->id;
        
        // 构建文件路径
        $saveDir = ROOT_PATH . 'public/uploads/poster/client/';
        if (!is_dir($saveDir)) {
            mkdir($saveDir, 0755, true);
        }
        
        // 文件名格式：用户ID_client.png
        $filename = $userId . '_client.png';
        $filepath = $saveDir . $filename;
        $posterUrl = '/uploads/poster/client/' . $filename;
        
        // 检查是否已存在海报
        if (file_exists($filepath) && filesize($filepath) > 0) {
            $fullUrl = request()->domain() . $posterUrl;
            $this->success('获取成功', [
                'poster_url' => $fullUrl,
                'is_cached' => true
            ]);
        }
        
        // 获取系统配置
        $systemConfig = Db::name('system_config')->where('id', 1)->find();
        if (!$systemConfig) {
            $this->error('系统设置未初始化');
        }
        
        $appid = $systemConfig['client_wxappid'] ?? '';
        $secret = $systemConfig['client_wxappsecret'] ?? '';
        $posterBgImage = $systemConfig['client_poster_image'] ?? ''; // 点单端专用海报图片
        
        if (!$appid || !$secret) {
            $this->error('点单端小程序配置未设置');
        }
        
        if (!$posterBgImage) {
            $this->error('推广海报背景图片未配置，请在后台系统设置中上传');
        }
        
        // 生成小程序码
        try {
            // 获取access_token
            $tokenUrl = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$secret}";
            $tokenResult = file_get_contents($tokenUrl);
            $tokenData = json_decode($tokenResult, true);
            
            if (!isset($tokenData['access_token'])) {
                $this->error('获取access_token失败：' . ($tokenData['errmsg'] ?? '未知错误'));
            }
            
            $accessToken = $tokenData['access_token'];
            
            // 生成小程序码（永久有效）
            // scene参数：用户ID，用于绑定推广关系
            $qrcodeUrl = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token={$accessToken}";
            $qrcodeData = [
                'scene' => strval($userId), // 推广人ID
                'page' => 'pages/index/index', // 扫码进入的页面
                'width' => 280,
                'auto_color' => false,
                'line_color' => ['r' => 0, 'g' => 0, 'b' => 0],
                'is_hyaline' => false
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $qrcodeUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($qrcodeData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $qrcodeResult = curl_exec($ch);
            curl_close($ch);
            
            // 检查是否返回的是图片
            $jsonCheck = json_decode($qrcodeResult, true);
            if ($jsonCheck && isset($jsonCheck['errcode'])) {
                $this->error('生成小程序码失败：' . ($jsonCheck['errmsg'] ?? '未知错误'));
            }
            
            // 保存小程序码临时文件
            $qrcodeTempPath = $saveDir . $userId . '_qrcode_temp.png';
            file_put_contents($qrcodeTempPath, $qrcodeResult);
            
            // 合成海报
            $posterBgPath = ROOT_PATH . 'public' . $posterBgImage;
            if (!file_exists($posterBgPath)) {
                // 尝试完整URL
                if (strpos($posterBgImage, 'http') === 0) {
                    $posterBgPath = $posterBgImage;
                } else {
                    $this->error('海报背景图片不存在');
                }
            }
            
            // 加载背景图
            $bgInfo = getimagesize($posterBgPath);
            $bgWidth = $bgInfo[0];
            $bgHeight = $bgInfo[1];
            
            switch ($bgInfo[2]) {
                case IMAGETYPE_JPEG:
                    $bgImage = imagecreatefromjpeg($posterBgPath);
                    break;
                case IMAGETYPE_PNG:
                    $bgImage = imagecreatefrompng($posterBgPath);
                    break;
                default:
                    $this->error('不支持的背景图片格式，请使用JPG或PNG');
            }
            
            // 加载二维码
            $qrcodeImage = imagecreatefrompng($qrcodeTempPath);
            $qrcodeWidth = imagesx($qrcodeImage);
            $qrcodeHeight = imagesy($qrcodeImage);
            
            // 计算二维码位置（右下角，留出边距）
            $margin = 40; // 边距
            $qrcodeX = $bgWidth - $qrcodeWidth - $margin;
            $qrcodeY = $bgHeight - $qrcodeHeight - $margin - 100; // 额外留出底部空间给用户信息
            
            // 合成图片
            imagecopy($bgImage, $qrcodeImage, $qrcodeX, $qrcodeY, 0, 0, $qrcodeWidth, $qrcodeHeight);
            
            // 加载用户头像
            $avatarUrl = $user->avatar ?? '';
            if (!empty($avatarUrl)) {
                $avatarPath = ROOT_PATH . 'public' . $avatarUrl;
                if (strpos($avatarUrl, 'http') === 0) {
                    $avatarPath = $avatarUrl;
                }
                $avatarInfo = @getimagesize($avatarPath);
                if ($avatarInfo) {
                    $avatarSize = 120;
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
                        imagecopyresampled($avatarResized, $avatarImage, 0, 0, 0, 0, $avatarSize, $avatarSize, imagesx($avatarImage), imagesy($avatarImage));
                        $avatarX = $margin;
                        $avatarY = $bgHeight - $avatarSize - $margin - 80;
                        imagecopy($bgImage, $avatarResized, $avatarX, $avatarY, 0, 0, $avatarSize, $avatarSize);
                        imagedestroy($avatarImage);
                        imagedestroy($avatarResized);
                    }
                }
            }
            
            // 添加用户信息文字（可选）
            $textColor = imagecolorallocate($bgImage, 51, 51, 51);
            $fontPath = ROOT_PATH . 'public/static/fonts/msyh.ttf'; // 微软雅黑字体
            
            if (file_exists($fontPath)) {
                $nickname = $user->nickname ?: '用户' . $userId;
                // 昵称文字位置（左下角，头像右侧）
                $textX = $margin + 140;
                $textY = $bgHeight - $margin - 130;
                imagettftext($bgImage, 20, 0, $textX, $textY, $textColor, $fontPath, $nickname);
                imagettftext($bgImage, 16, 0, $textX, $textY + 35, $textColor, $fontPath, '邀请您一起加入');
            }
            
            // 保存合成后的海报
            imagepng($bgImage, $filepath);
            
            // 清理
            imagedestroy($bgImage);
            imagedestroy($qrcodeImage);
            @unlink($qrcodeTempPath);
            
            $fullUrl = request()->domain() . $posterUrl;
            $this->success('生成成功', [
                'poster_url' => $fullUrl,
                'is_cached' => false
            ]);
            
        } catch (\Exception $e) {
            $this->error('生成海报失败：' . $e->getMessage());
        }
    }

    /**
     * 获取用户信息
     * @ApiMethod (GET)
     */
    public function info()
    {
        $userInfo = $this->auth->getUserinfo();
        $this->success('获取成功', $userInfo);
    }

    /**
     * 更新用户资料
     * @ApiMethod (POST)
     * @ApiParams (name="nickname", type="string", required=false, description="昵称")
     * @ApiParams (name="avatar", type="string", required=false, description="头像")
     * @ApiParams (name="mobile", type="string", required=false, description="手机号")
     */
    public function updateProfile()
    {
        $user = $this->auth->getUser();
        
        $nickname = $this->request->post('nickname', '');
        $avatar = $this->request->post('avatar', '');
        $mobile = $this->request->post('mobile', '');
        
        $updateData = [];
        
        if ($nickname) {
            $updateData['nickname'] = $nickname;
        }
        
        if ($avatar) {
            $updateData['avatar'] = $avatar;
        }
        
        if ($mobile) {
            // 验证手机号格式
            if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
                $this->error('手机号格式不正确');
            }
            $updateData['mobile'] = $mobile;
        }
        
        if (empty($updateData)) {
            $this->error('没有需要更新的内容');
        }
        
        // 更新用户信息
        Db::name('user')->where('id', $user->id)->update($updateData);
        
        $this->success('更新成功');
    }
}

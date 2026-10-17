<?php
/**
 * 自动创建网站配置项
 * 访问此文件即可自动创建 site_title 和 site_icon 配置
 */

require_once __DIR__ . '/../application/config.php';

$config = [
    [
        'name' => 'site_title',
        'group' => 'basic',
        'title' => '网站标题',
        'type' => 'string',
        'value' => '电竞',
    ],
    [
        'name' => 'site_icon',
        'group' => 'basic',
        'title' => '网站图标',
        'type' => 'image',
        'value' => '',
    ],
];

$db = new \think\Db();

echo "开始检查配置项...\n";

foreach ($config as $item) {
    $exists = $db::name('config')->where('name', $item['name'])->find();
    if ($exists) {
        echo "[跳过] {$item['name']} 已存在\n";
    } else {
        $result = $db::name('config')->insert($item);
        if ($result) {
            echo "[创建] {$item['name']} 创建成功\n";
        } else {
            echo "[失败] {$item['name']} 创建失败\n";
        }
    }
}

// 刷新配置缓存
try {
    \app\common\model\Config::refreshFile();
    echo "[完成] 配置缓存已刷新\n";
} catch (\Exception $e) {
    echo "[警告] 缓存刷新失败: " . $e->getMessage() . "\n";
}

echo "\n完成！请刷新后台基础配置页面。\n";

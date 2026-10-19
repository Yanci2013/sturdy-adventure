<?php
session_start();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>搭建同款系统 | 广州小熠科技网络</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        techBlue: '#00c6ff',
                        techPurple: '#9b7bff',
                        darkBg: '#0f172a',
                    }
                }
            }
        }
    </script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1a103d 100%);
            min-height: 100vh;
            font-family: system-ui, sans-serif;
        }
        .glass-card {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 20px;
        }
        .gradient-text {
            background: linear-gradient(90deg, #00c6ff, #9b7bff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .qrcode-box {
            background: #ffffff;
            border-radius: 20px;
        }
        .btn-tech {
            background: linear-gradient(90deg, #00c6ff, #9b7bff);
            transition: all 0.3s ease;
        }
        .btn-tech:hover {
            transform: translateY(-3px);
            box-shadow: 0 0 20px rgba(0,198,255,0.4);
        }
    </style>
    <link rel="stylesheet" href="../assets/css/theme-v2.css">
</head>
<body class="text-white px-4 py-8">
<!-- V2 加载动画 -->
<div class="v2-loader-overlay" id="v2Loader">
    <div class="v2-loader-logo-wrap">
        <div class="v2-loader-ring"></div>
        <div class="v2-loader-ring"></div>
        <div class="v2-loader-logo">小</div>
    </div>
    <div class="v2-loader-text">
        <span>加</span><span>载</span><span>中</span><span>...</span>
    </div>
    <div class="v2-loader-progress">
        <div class="v2-loader-progress-bar"></div>
    </div>
</div>

    <div class="max-w-lg mx-auto">
        <!-- 头部标题 -->
        <div class="text-center mb-10">
            <h1 class="text-[28px] font-bold gradient-text mb-3">搭建同款系统</h1>
            <p class="text-gray-300 text-sm">广州小熠科技网络 · 专业系统搭建服务</p>
        </div>

        <!-- 系统介绍卡片 -->
        <div class="glass-card p-6 mb-8">
            <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
                <i class="fas fa-cube text-techBlue"></i>
                系统介绍
            </h2>
            <p class="text-gray-300 text-sm leading-relaxed">
                可搭建同款全套接单系统、邀请分佣系统、福利活动系统，包含完整后台管理、用户端页面、支付回调、自动分佣等全部功能，一站式部署，售后全程指导。
            </p>
        </div>

        <!-- 群信息卡片 -->
        <div class="glass-card p-6 mb-8">
            <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
                <i class="fas fa-users text-techPurple"></i>
                加入专属交流群
            </h2>
            <div class="qrcode-box p-5 mb-5">
                <div class="text-center">
                    <h3 class="text-lg font-bold text-gray-800 mb-1">小熠的秘密基地</h3>
                    <p class="text-gray-500 text-sm mb-4">群号：1098139048</p>
                    <!-- 直接使用你提供的外网图片链接 -->
                    <img src="https://s3.bmp.ovh/2026/05/18/UCIwlUK7.jpg" class="w-full rounded-xl" alt="QQ群二维码">
                    <p class="text-gray-600 text-xs mt-3">扫码二维码，一键加入群聊</p>
                </div>
            </div>

            <!-- 一键加群按钮 -->
            <a href="https://qm.qq.com/q/LLQ8XFsuIg" target="_blank" class="block w-full text-center py-4 rounded-xl btn-tech text-white font-bold text-lg">
                <i class="fab fa-qq mr-2"></i>
                点击一键加入QQ群
            </a>
        </div>

        <!-- 温馨提示 -->
        <div class="text-center text-gray-400 text-xs mt-6">
            进群即可获取系统搭建教程、源码咨询、售后答疑
        </div>
    </div>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
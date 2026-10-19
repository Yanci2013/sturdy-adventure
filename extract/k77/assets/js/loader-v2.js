/* ============================================================
   小熠网络 - 白蓝主题 V3
   加载动画 + DIY配置 + 实用功能
   ============================================================ */
(function() {
  'use strict';
  var CONFIG = {
    minDisplayTime: 700,
    logoText: '小'
  };
  var DIY_CONFIG = {
    general: {},
    footer: {},
    feature: {}
  };
  var SERVER_CONFIG = window.V3_SERVER_CONFIG || {};
  var BASE_URL = window.V3_BASE_URL || '';
  if (SERVER_CONFIG) {
    DIY_CONFIG = Object.assign(DIY_CONFIG, SERVER_CONFIG);
  }
  try {
    var saved = localStorage.getItem('v3_diy_config');
    if (saved) {
      var parsed = JSON.parse(saved);
      for (var k in parsed) {
        if (parsed.hasOwnProperty(k)) {
          if (typeof parsed[k] === 'object' && !Array.isArray(parsed[k])) {
            DIY_CONFIG[k] = Object.assign(DIY_CONFIG[k] || {}, parsed[k]);
          } else {
            DIY_CONFIG[k] = parsed[k];
          }
        }
      }
    }
  } catch(e) {}
  function applyDIYConfig() {
    if (DIY_CONFIG.general && DIY_CONFIG.general.bg_image) {
      document.body.style.backgroundImage = 'url(' + DIY_CONFIG.general.bg_image + ')';
      document.body.style.backgroundSize = 'cover';
      document.body.style.backgroundPosition = 'center';
      document.body.style.backgroundAttachment = 'fixed';
    }
    var footer = document.querySelector('.flat-white-footer');
    if (footer && DIY_CONFIG.footer) {
      if (DIY_CONFIG.footer.bg_image) {
        footer.style.backgroundImage = 'url(' + DIY_CONFIG.footer.bg_image + ')';
        footer.style.backgroundSize = 'cover';
        footer.style.backgroundPosition = 'center';
      }
      if (DIY_CONFIG.footer.bg_color) {
        footer.style.background = DIY_CONFIG.footer.bg_color;
      }
      var navItems = footer.querySelectorAll('.footer-item');
      navItems.forEach(function(item) {
        var key = item.dataset.key;
        if (!key) return;
        var defaultImg = item.querySelector('.f-icon-default');
        var activeImg = item.querySelector('.f-icon-active');
        if (DIY_CONFIG.footer[key + '_icon_default'] && defaultImg) {
          defaultImg.src = DIY_CONFIG.footer[key + '_icon_default'];
        }
        if (DIY_CONFIG.footer[key + '_icon_active'] && activeImg) {
          activeImg.src = DIY_CONFIG.footer[key + '_icon_active'];
        }
        if (DIY_CONFIG.footer[key + '_text']) {
          var textEl = item.querySelector('.f-text');
          if (textEl) textEl.textContent = DIY_CONFIG.footer[key + '_text'];
        }
      });
    }
    if (DIY_CONFIG.general && DIY_CONFIG.general.bg_music &&
        DIY_CONFIG.general.bg_music_enabled == '1') {
      initBackgroundMusic(DIY_CONFIG.general.bg_music);
    }
  }
  var bgAudio = null;
  var musicBtn = null;
  function initBackgroundMusic(url) {
    if (bgAudio) return;
    try {
      bgAudio = new Audio(url);
      bgAudio.loop = true;
      bgAudio.volume = 0.35;
      musicBtn = document.createElement('button');
      musicBtn.className = 'v3-music-player';
      musicBtn.innerHTML = '<i class="fas fa-music"></i>';
      musicBtn.title = '背景音乐';
      musicBtn.onclick = function(e) {
        e.stopPropagation();
        if (bgAudio.paused) {
          bgAudio.play().catch(function(){});
          musicBtn.classList.add('playing');
          musicBtn.innerHTML = '<i class="fas fa-pause"></i>';
        } else {
          bgAudio.pause();
          musicBtn.classList.remove('playing');
          musicBtn.innerHTML = '<i class="fas fa-music"></i>';
        }
      };
      document.body.appendChild(musicBtn);
      var tryPlay = function() {
        bgAudio.play().then(function() {
          musicBtn.classList.add('playing');
          musicBtn.innerHTML = '<i class="fas fa-pause"></i>';
        }).catch(function(){});
        document.removeEventListener('click', tryPlay);
        document.removeEventListener('touchstart', tryPlay);
      };
      document.addEventListener('click', tryPlay);
      document.addEventListener('touchstart', tryPlay);
    } catch(e) {}
  }
  function createLoader() {
    var overlay = document.createElement('div');
    overlay.className = 'v2-loader-overlay';
    overlay.id = 'v2Loader';
    overlay.innerHTML =
      '<div class="v2-loader-logo-wrap">' +
        '<div class="v2-loader-ring"></div>' +
        '<div class="v2-loader-ring"></div>' +
        '<div class="v2-loader-ring"></div>' +
        '<div class="v2-loader-logo">' + CONFIG.logoText + '</div>' +
      '</div>' +
      '<div class="v2-loader-text">' +
        '<span>加</span><span>载</span><span>中</span><span>...</span>' +
      '</div>' +
      '<div class="v2-loader-progress">' +
        '<div class="v2-loader-progress-bar"></div>' +
      '</div>';
    return overlay;
  }
  function showLoader() {
    if (document.getElementById('v2Loader')) return;
    var loader = createLoader();
    document.body.appendChild(loader);
    loader.dataset.startTime = Date.now();
  }
  function hideLoader() {
    var loader = document.getElementById('v2Loader');
    if (!loader) return;
    var startTime = parseInt(loader.dataset.startTime) || Date.now();
    var elapsed = Date.now() - startTime;
    var remaining = Math.max(0, CONFIG.minDisplayTime - elapsed);
    setTimeout(function() {
      loader.classList.add('hidden');
      setTimeout(function() {
        if (loader.parentNode) loader.parentNode.removeChild(loader);
      }, 500);
    }, remaining);
  }
  var loaderEnabled = false;
  if (loaderEnabled) {
    if (document.readyState === 'loading') {
      showLoader();
      document.addEventListener('DOMContentLoaded', function() {
        applyDIYConfig();
        window.addEventListener('load', hideLoader);
        setTimeout(hideLoader, 3000);
      });
    } else {
      showLoader();
      applyDIYConfig();
      setTimeout(hideLoader, 500);
    }
  } else {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', applyDIYConfig);
    } else {
      applyDIYConfig();
    }
  }
  var transitionEnabled = !(DIY_CONFIG.feature && DIY_CONFIG.feature.page_transition == '0');
  if (transitionEnabled) {
    document.addEventListener('click', function(e) {
      var link = e.target.closest('a[href]');
      if (link && link.href && !link.target &&
          !link.href.startsWith('javascript:') &&
          !link.href.startsWith('#') &&
          !link.hasAttribute('data-no-loader')) {
        if (link.href.split('#')[0] === window.location.href.split('#')[0]) return;
        if (link.hostname !== window.location.hostname) return;
        if (loaderEnabled) showLoader();
      }
    });
  }
  window.V2Loader = { show: showLoader, hide: hideLoader };
  window.V3DIY = {
    setConfig: function(config) {
      try {
        for (var k in config) {
          if (config.hasOwnProperty(k)) {
            if (typeof config[k] === 'object' && !Array.isArray(config[k])) {
              DIY_CONFIG[k] = Object.assign(DIY_CONFIG[k] || {}, config[k]);
            } else {
              DIY_CONFIG[k] = config[k];
            }
          }
        }
        localStorage.setItem('v3_diy_config', JSON.stringify(DIY_CONFIG));
        applyDIYConfig();
      } catch(e) {}
    },
    getConfig: function() { return DIY_CONFIG; },
    playMusic: function(url) { initBackgroundMusic(url); },
    refresh: function() { applyDIYConfig(); }
  };
})();

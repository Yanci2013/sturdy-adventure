/*require.config({
    paths: {
    },
    shim: {
    }
});*/


require([], function (undefined) {
    // 后台切换主题，先直接刷新
    if (Config.modulename == 'admin' && Config.controllername == 'index' && Config.actionname == 'index') {
        $('.skin-list .clearfix.full-opacity-hover').on('click', function () {
            setTimeout(() => {
                location.reload();
            }, 100)
        })
    }
});
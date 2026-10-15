define(['jquery', 'bootstrap', 'backend', 'form', 'fast'], function ($, undefined, Backend, Form, Fast) {
    console.log('msgtemplate.js loaded');
    var Controller = {
        index: function () {
            console.log('msgtemplate index called');
            Form.api.bindevent($("#edit-form"));

            // 测试推送
            $(document).on('click', '#btn-test-send', function () {
                console.log('btn-test-send clicked');
                var thugId = $.trim($('#test_thug_id').val());
                if (!thugId) {
                    Layer.msg('请输入打手ID', {icon: 5});
                    return false;
                }
                var $btn = $(this);
                var $result = $('#test-result');
                $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> 发送中...');
                $result.text('');
                $.ajax({
                    url: Fast.api.fixurl('general/msgtemplate/test'),
                    type: 'POST',
                    dataType: 'json',
                    data: {thug_id: thugId},
                    success: function (ret) {
                        if (ret.code === 1) {
                            $result.html('<span class="text-success">' + ret.msg + '</span>');
                        } else {
                            $result.html('<span class="text-danger">' + ret.msg + '</span>');
                        }
                        $btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> 发送测试');
                    },
                    error: function () {
                        $result.html('<span class="text-danger">请求失败，请检查网络</span>');
                        $btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> 发送测试');
                    }
                });
                return false;
            });
        }
    };
    return Controller;
});

define(['jquery', 'bootstrap', 'backend', 'form'], function ($, undefined, Backend, Form) {
    var Controller = {
        change: function () {
            Form.api.bindevent($("#balance-form"), function (data) {
                Fast.api.close(data);
            });
        }
    };
    return Controller;
});

define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'withdrawal/index' + location.search,
                    add_url: 'withdrawal/add',
                    edit_url: 'withdrawal/edit',
                    //del_url: 'withdrawal/del',
                    multi_url: 'withdrawal/multi',
                    import_url: 'withdrawal/import',
                    table: 'withdrawal',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        //{field: 'user_id', title: __('User_id')},
                        {field: 'type', title: __('类型'), searchList: {"1":__('用户'),"2":__('打手')}, formatter: Table.api.formatter.status},
                        {field: 'channel', title: __('Channel'), searchList: {"alipay":"支付宝","wechat":"微信","bank":"银行卡"}, formatter: Table.api.formatter.normal},
                        {field: 'user.nickname', title: __('User.nickname'), operate: 'LIKE'},
                        {field: 'money', title: __('Money'), operate:'BETWEEN'},
                        {field: 'price', title: __('Price'), operate:'BETWEEN'},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'account', title: __('Account'), operate: 'LIKE'},
                        {field: 'bank_name', title: __('Bank_name'), operate: 'LIKE'},
                        {field: 'images', title: __('收款码'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.images},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        {field: 'remarks', title: __('Remarks'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});

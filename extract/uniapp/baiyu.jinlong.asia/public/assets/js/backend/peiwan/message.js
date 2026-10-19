define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'peiwan/message/index' + location.search,
                    add_url: 'peiwan/message/add',
                    edit_url: 'peiwan/message/edit',
                    del_url: 'peiwan/message/del',
                    multi_url: 'peiwan/message/multi',
                    import_url: 'peiwan/message/import',
                    table: 'message',
                }
            });

            var table = $("#table");

            // 用户信息格式化器
            var userFormatter = function(value, row, index, field) {
                var fieldName = field.replace('_id', '').replace('_user', '');
                var user = row[fieldName + 'user'];
                if (!user) return '-';
                var html = '<div style="display:flex;align-items:center;">';
                html += '<img src="' + (user.avatar || '/assets/img/avatar.png') + '" style="width:30px;height:30px;border-radius:50%;margin-right:5px;">';
                html += '<span>' + (user.nickname || '-') + '</span>';
                html += '<span style="color:#999;margin-left:5px;">(ID:' + user.id + ')</span>';
                html += '</div>';
                return html;
            };

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'from_user_id', title: '发送者', operate: false, formatter: function(value, row, index) {
                            return userFormatter(value, row, index, 'from_user_id');
                        }},
                        {field: 'to_user_id', title: '接收者', operate: false, formatter: function(value, row, index) {
                            return userFormatter(value, row, index, 'to_user_id');
                        }},
                        {field: 'order.order_no', title: '关联订单', operate: 'LIKE'},
                        {field: 'msg_type', title: __('Msg_type'), searchList: {"text":__('Msg_type text'),"image":__('Msg_type image'),"file":__('Msg_type file')}, formatter: Table.api.formatter.normal},
                        {field: 'is_read', title: __('Is_read'), searchList: {"0":__('Is_read 0'),"1":__('Is_read 1')}, formatter: Table.api.formatter.normal},
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

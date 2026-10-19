define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'peiwan/user_member/index' + location.search,
                    add_url: 'peiwan/user_member/add',
                    edit_url: 'peiwan/user_member/edit',
                    del_url: 'peiwan/user_member/del',
                    multi_url: 'peiwan/user_member/multi',
                    import_url: 'peiwan/user_member/import',
                    table: 'user_member',
                }
            });

            var table = $("#table");

            // 用户信息格式化器
            var userFormatter = function(value, row) {
                var user = row.user;
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
                        {field: 'user_id', title: '用户', operate: false, formatter: userFormatter},
                        {field: 'start_time', title: __('Start_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'end_time', title: __('End_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'status', title: __('Status'), searchList: {"normal":__('Normal'),"expired":__('Expired'),"pending":"待支付"}, formatter: Table.api.formatter.status},
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

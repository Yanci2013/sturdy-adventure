define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'peiwan/deduction_log/index' + location.search,
                    add_url: 'peiwan/deduction_log/add',
                    edit_url: 'peiwan/deduction_log/edit',
                    del_url: 'peiwan/deduction_log/del',
                    multi_url: 'peiwan/deduction_log/multi',
                    import_url: 'peiwan/deduction_log/import',
                    table: 'deduction_log',
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
                        {field: 'order.order_no', title: '关联订单', operate: 'LIKE'},
                        {field: 'user_id', title: '用户', operate: false, formatter: userFormatter},
                        {field: 'amount', title: __('Amount'), operate:'BETWEEN'},
                        {field: 'type', title: __('Type'), searchList: {"fine":__('Type fine'),"refund":__('Type refund')}, formatter: Table.api.formatter.normal},
                        {field: 'reason', title: __('Reason'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
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

define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'finance/balancelog/index' + location.search,
                    del_url: 'finance/balancelog/del',
                    table: 'user_balance_log',
                }
            });

            var table = $("#table");

            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                sortOrder: 'desc',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: 'ID', sortable: true},
                        {field: 'user_id', title: '用户ID', sortable: true},
                        {field: 'user.nickname', title: '用户昵称', operate: 'LIKE'},
                        {field: 'user.mobile', title: '手机号', operate: 'LIKE'},
                        {field: 'balance', title: '变动金额', sortable: true, formatter: function(value) {
                            var num = parseFloat(value);
                            if (num > 0) return '<span style="color:#18b566">+' + value + '</span>';
                            if (num < 0) return '<span style="color:#e25c3b">' + value + '</span>';
                            return value;
                        }},
                        {field: 'before', title: '变动前余额'},
                        {field: 'after', title: '变动后余额'},
                        {field: 'type_text', title: '类型', operate: false, formatter: function(value, row) {
                            var colors = {'增加': 'success', '减少': 'danger', '修改': 'info'};
                            return '<span class="label label-' + (colors[value] || 'default') + '">' + value + '</span>';
                        }},
                        {field: 'memo', title: '备注', operate: 'LIKE', class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'createtime', title: '时间', operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: Table.api.formatter.datetime, sortable: true},
                        {field: 'operate', title: '操作', table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate, buttons: []}
                    ]
                ]
            });

            Table.api.bindevent(table);
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});

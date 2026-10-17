define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'finance/levelorder/index' + location.search,
                    del_url: 'finance/levelorder/del',
                    table: 'level_order',
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
                        {field: 'level.name', title: '会员等级', operate: 'LIKE'},
                        {field: 'order_no', title: '订单号', operate: 'LIKE'},
                        {field: 'price', title: '金额', sortable: true, formatter: function(value) {
                            return '<span style="color:#e25c3b">￥' + parseFloat(value).toFixed(2) + '</span>';
                        }},
                        {field: 'days', title: '天数', sortable: true, formatter: function(value) {
                            return value + '天';
                        }},
                        {field: 'status_text', title: '状态', operate: false, formatter: function(value) {
                            var color = value === '已支付' ? 'success' : 'warning';
                            return '<span class="label label-' + color + '">' + value + '</span>';
                        }},
                        {field: 'createtime', title: '创建时间', operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: Table.api.formatter.datetime, sortable: true},
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

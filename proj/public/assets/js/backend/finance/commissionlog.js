define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'finance/commissionlog/index' + location.search,
                    table: 'thug_commission_log',
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
                        {field: 'thug_id', title: '打手ID', sortable: true},
                        {field: 'thug.name', title: '打手昵称', operate: 'LIKE'},
                        {field: 'order_id', title: '订单ID', operate: 'LIKE'},
                        {field: 'type_text', title: '类型', operate: false, formatter: function(value, row) {
                            var colors = {
                                '1': 'info',
                                '2': 'success',
                                '3': 'warning',
                                '4': 'primary',
                                '5': 'danger'
                            };
                            return '<span class="label label-' + (colors[row.type] || 'default') + '">' + value + '</span>';
                        }},
                        {field: 'amount', title: '变动金额', sortable: true, formatter: function(value) {
                            return '<span style="font-weight:600">¥' + value + '</span>';
                        }},
                        {field: 'frozen_before', title: '冻结前', operate: false},
                        {field: 'frozen_after', title: '冻结后', operate: false},
                        {field: 'available_before', title: '可用前', operate: false},
                        {field: 'available_after', title: '可用后', operate: false},
                        {field: 'memo', title: '备注', operate: 'LIKE', class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'createtime', title: '时间', operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: Table.api.formatter.datetime, sortable: true}
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

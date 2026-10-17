define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'finance/ordercommission/index' + location.search,
                    table: 'order',
                }
            });

            var table = $("#table");

            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'o.id',
                sortOrder: 'desc',
                commonSearch: true,
                columns: [
                    [
                        {field: 'id', title: 'ID', sortable: true},
                        {field: 'order_no', title: '订单号', operate: 'LIKE'},
                        {field: 'title', title: '商品标题', operate: false},
                        {field: 'user.nickname', title: '下单用户', operate: 'LIKE', formatter: function(value, row) {
                            return (row.user_name || '-') + (row.user_mobile ? '<br><small class="text-muted">' + row.user_mobile + '</small>' : '');
                        }},
                        {field: 'thug.name', title: '接单打手', operate: 'LIKE', formatter: function(value, row) {
                            return row.thug_name || '-';
                        }},
                        {field: 'pay_price', title: '订单金额', operate: false, sortable: true, formatter: function(value) {
                            return '<span style="font-weight:600">¥' + value + '</span>';
                        }},
                        {field: 'received_price', title: '打手佣金', operate: false, sortable: true, formatter: function(value) {
                            return '<span style="color:#18b566;font-weight:600">¥' + value + '</span>';
                        }},
                        {field: 'platform_fee', title: '平台抽成', operate: false, formatter: function(value) {
                            return '<span style="color:#e25c3b;font-weight:600">¥' + value + '</span>';
                        }},
                        {field: 'status', title: '状态', searchList: {'2':'待开始','3':'代练中','4':'待确认','5':'已完成','7':'已退款'}, formatter: function(value, row) {
                            var colors = {'待付款':'default','待接单':'info','待开始':'info','代练中':'primary','待确认':'warning','已完成':'success','待售后':'danger','已退款':'danger','已取消':'default'};
                            var text = row.status_text || '未知';
                            return '<span class="label label-' + (colors[text] || 'default') + '">' + text + '</span>';
                        }},
                        {field: 'paytime', title: '支付时间', operate: false},
                        {field: 'confirmtime', title: '确认时间', operate: false}
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

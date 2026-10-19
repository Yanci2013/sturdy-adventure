define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'peiwan/aftersale_order/index' + location.search,
                    detail_url: 'peiwan/aftersale_order/detail',
                    table: 'order',
                }
            });

            var table = $("#table");

            // 用户信息格式化器
            var userFormatter = function(value, row, index, field) {
                var user = row[field.replace('_id', '')];
                if (!user) return '-';
                var html = '<div style="display:flex;align-items:center;">';
                html += '<img src="' + (user.avatar || '/assets/img/avatar.png') + '" style="width:30px;height:30px;border-radius:50%;margin-right:5px;">';
                html += '<span>' + (user.nickname || '-') + '</span>';
                html += '</div>';
                return html;
            };

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                sortOrder: 'desc',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'order_no', title: '订单号', operate: 'LIKE'},
                        {field: 'shangjia_id', title: '发单人', operate: false, formatter: function(value, row, index) {
                            return userFormatter(value, row, index, 'shangjia_id');
                        }},
                        {field: 'dashou_id', title: '接单人', operate: false, formatter: function(value, row, index) {
                            return userFormatter(value, row, index, 'dashou_id');
                        }},
                        {field: 'game.name', title: '游戏', operate: 'LIKE'},
                        {field: 'price', title: '金额', operate: false, formatter: function(value, row) {
                            return '<span class="text-danger">¥' + parseFloat(value).toFixed(2) + '</span>';
                        }},
                        {field: 'status', title: '订单状态', searchList: {"daijiedan":"待接单","daifuwu":"待服务","daijiesuan":"待结算","yiwancheng":"已完成","guanbizhong":"关闭中","kefujieru":"客服介入","yichexiao":"已撤销"}, formatter: Table.api.formatter.status},
                        {field: 'intervene_count', title: '处理次数', operate: false, formatter: function(value, row) {
                            return '<span class="badge badge-info">' + (value || 0) + '</span>';
                        }},
                        {field: 'handler_name', title: '处理人', operate: false},
                        {field: 'last_intervene_time', title: '最后处理时间', operate: false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: function(value, row, index) {
                            var html = [];
                            // 查看详情按钮
                            html.push('<a href="javascript:;" class="btn btn-info btn-xs btn-detail" data-id="' + row.id + '" title="查看详情"><i class="fa fa-eye"></i> 查看详情</a>');
                            return html.join(' ');
                        }}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            
            // 绑定查看详情按钮事件
            table.on('click', '.btn-detail', function() {
                var id = $(this).data('id');
                Fast.api.open('peiwan/aftersale_order/detail?ids=' + id, '售后订单详情', {
                    area: ['95%', '90%']
                });
            });
        },
        detail: function () {
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

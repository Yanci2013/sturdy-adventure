define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'peiwan/order/index' + location.search,
                    add_url: 'peiwan/order/add',
                    edit_url: 'peiwan/order/edit',
                    del_url: 'peiwan/order/del',
                    multi_url: 'peiwan/order/multi',
                    import_url: 'peiwan/order/import',
                    detail_url: 'peiwan/order/detail',
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
                        {field: 'order_no', title: __('Order_no'), operate: 'LIKE'},
                        {field: 'shangjia_id', title: '商家', operate: false, formatter: function(value, row, index) {
                            return userFormatter(value, row, index, 'shangjia_id');
                        }},
                        {field: 'dashou_id', title: '打手', operate: false, formatter: function(value, row, index) {
                            return userFormatter(value, row, index, 'dashou_id');
                        }},
                        {field: 'game.name', title: '游戏', operate: 'LIKE'},
                        {field: 'server.server_name', title: '区服', operate: 'LIKE'},
                        {field: 'order_type', title: __('Order_type'), searchList: {"standard":__('Order_type standard'),"custom":__('Order_type custom')}, formatter: Table.api.formatter.normal},
                        {field: 'original_price', title: '原价', operate:'BETWEEN', formatter: function(value, row) {
                            return value ? '¥' + parseFloat(value).toFixed(2) : '-';
                        }},
                        {field: 'price', title: '折扣价', operate:'BETWEEN', formatter: function(value, row) {
                            return '¥' + parseFloat(value).toFixed(2);
                        }},
                        {field: 'status', title: __('Status'), searchList: {"daijiedan":__('Status daijiedan'),"daifuwu":__('Status daifuwu'),"daijiesuan":__('Status daijiesuan'),"yiwancheng":__('Status yiwancheng'),"guanbizhong":__('Status guanbizhong'),"kefujieru":__('Status kefujieru'),"yichexiao":__('Status yichexiao')}, formatter: Table.api.formatter.status},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: function(value, row, index) {
                            var that = $.fn.bootstrapTable.defaults;
                            var html = [];
                            // 查看详情按钮
                            html.push('<a href="javascript:;" class="btn btn-info btn-xs btn-detail" data-id="' + row.id + '" title="查看详情"><i class="fa fa-eye"></i></a>');
                            // 编辑按钮
                            if (that.extend.edit_url) {
                                html.push('<a href="javascript:;" class="btn btn-success btn-xs btn-editone" data-id="' + row.id + '" title="编辑"><i class="fa fa-pencil"></i></a>');
                            }
                            // 删除按钮
                            if (that.extend.del_url) {
                                html.push('<a href="javascript:;" class="btn btn-danger btn-xs btn-delone" data-id="' + row.id + '" title="删除"><i class="fa fa-trash"></i></a>');
                            }
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
                Fast.api.open('peiwan/order/detail?ids=' + id, '订单详情', {
                    area: ['90%', '90%']
                });
            });
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

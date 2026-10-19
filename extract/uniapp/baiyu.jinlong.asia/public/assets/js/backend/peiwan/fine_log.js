define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'peiwan/fine_log/index' + location.search,
                    revoke_url: 'peiwan/fine_log/revoke',
                    table: 'fine_log',
                }
            });

            var table = $("#table");

            // 商家信息格式化器
            var fromUserFormatter = function(value, row) {
                var user = row.from_user;
                if (!user) return '-';
                var html = '<div style="display:flex;align-items:center;">';
                html += '<img src="' + (user.avatar || '/assets/img/avatar.png') + '" style="width:30px;height:30px;border-radius:50%;margin-right:5px;">';
                html += '<span>' + (user.nickname || '-') + '</span>';
                html += '<span style="color:#999;margin-left:5px;">(ID:' + user.id + ')</span>';
                html += '</div>';
                return html;
            };

            // 打手信息格式化器
            var toUserFormatter = function(value, row) {
                var user = row.to_user;
                if (!user) return '-';
                var html = '<div style="display:flex;align-items:center;">';
                html += '<img src="' + (user.avatar || '/assets/img/avatar.png') + '" style="width:30px;height:30px;border-radius:50%;margin-right:5px;">';
                html += '<span>' + (user.nickname || '-') + '</span>';
                html += '<span style="color:#999;margin-left:5px;">(ID:' + user.id + ')</span>';
                html += '</div>';
                return html;
            };

            // 状态格式化器
            var statusFormatter = function(value, row) {
                if (value === 'pending') {
                    return '<span class="label label-warning">待缴纳</span>';
                } else if (value === 'paid') {
                    return '<span class="label label-success">已缴纳</span>';
                }
                return value;
            };

            // 操作列格式化器
            var operateFormatter = function(value, row, index) {
                var html = '';
                html += '<a href="javascript:;" class="btn btn-danger btn-xs btn-revoke" data-id="' + row.id + '" data-status="' + row.status + '"><i class="fa fa-undo"></i> 撤销</a>';
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
                        {field: 'order.order_no', title: '关联订单', operate: 'LIKE'},
                        {field: 'from_user_id', title: '商家', operate: false, formatter: fromUserFormatter},
                        {field: 'to_user_id', title: '打手', operate: false, formatter: toUserFormatter},
                        {field: 'amount', title: __('Amount'), operate:'BETWEEN'},
                        {field: 'reason', title: __('Reason'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'status', title: __('Status'), searchList: {"pending":__('Status pending'),"paid":__('Status paid')}, formatter: statusFormatter},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'pay_time', title: __('Pay_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: {}, formatter: operateFormatter}
                    ]
                ]
            });

            // 撤销罚款事件
            table.on('click', '.btn-revoke', function() {
                var id = $(this).data('id');
                var status = $(this).data('status');
                var confirmMsg = '确定要撤销这笔罚款吗？';
                if (status === 'paid') {
                    confirmMsg = '该罚款已缴纳，撤销后将退回金额给被罚款人。确定要撤销吗？';
                }
                Layer.confirm(confirmMsg, {icon: 3, title: '撤销罚款'}, function(index) {
                    $.ajax({
                        url: 'peiwan/fine_log/revoke/ids/' + id,
                        type: 'POST',
                        dataType: 'json',
                        success: function(res) {
                            if (res.code === 1) {
                                Toastr.success(res.msg);
                                table.bootstrapTable('refresh');
                            } else {
                                Toastr.error(res.msg);
                            }
                        },
                        error: function() {
                            Toastr.error('操作失败');
                        }
                    });
                    Layer.close(index);
                });
            });

            // 为表格绑定事件
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
